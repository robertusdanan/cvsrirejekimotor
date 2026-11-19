<?php
declare(strict_types=1);

/**
 * Migrasi satu kali: Supabase -> MySQL (cPanel).
 * Perintah Danan 2026-11-19: database penuh MySQL, Supabase ditinggalkan.
 *
 * CARA JALAN (pilih satu):
 *   1. cPanel Terminal (disarankan, tanpa token):
 *        php ~/public_html/scripts/migrate_supabase_to_mysql.php
 *   2. Web (butuh header token yang sama dengan endpoint upload gambar):
 *        curl -H "X-Upload-Token: <IMAGE_UPLOAD_TOKEN>" \
 *          https://cvsrirejekimotor.com/scripts/migrate_supabase_to_mysql.php
 *
 * Yang dilakukan:
 *   - Membuat semua tabel MySQL dari database/mysql_schema.sql (bila belum ada).
 *   - Menyalin SEMUA baris dari Supabase ke MySQL (products + product_images,
 *     brands, vehicles, categories, marketplaces, company_profile, hero_slides,
 *     admin_users, login_throttle, admin_audit_log, statistik_kunjungan,
 *     statistik_kunjungan_ip). ID/UUID dipertahankan apa adanya.
 *   - Idempotent (upsert berdasar PK/kunci unik) — aman dijalankan ulang.
 *   - Memverifikasi jumlah baris MySQL vs Supabase per tabel, lalu mencetak
 *     laporan JSON. TIDAK menghapus apa pun di Supabase (Supabase tetap
 *     utuh sebagai cadangan sampai Danan mematikannya sendiri).
 *
 * Keamanan: TIDAK ada secret di file ini (kredensial dibaca dari
 * ~/private/.env via config()). Bila DB MySQL belum dikonfigurasi,
 * skrip berhenti dengan pesan jelas. Bila dijalankan via web tanpa
 * token yang benar -> 401.
 */

// --- Auth (hanya untuk pemanggilan via web; CLI bebas token) ---
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/SupabaseWriter.php';
require __DIR__ . '/../src/MySQLWriter.php';
require __DIR__ . '/../src/MySQLAdminUsers.php';

function migrate_fail(int $code, string $msg): void
{
    if (php_sapi_name() !== 'cli') {
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $msg]);
    } else {
        fwrite(STDERR, "ERROR: {$msg}\n");
    }
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    $expected = (string) (config('IMAGE_UPLOAD_TOKEN') ?? '');
    if ($expected === '') {
        migrate_fail(401, 'unauthorized (token belum dikonfigurasi)');
    }
    $provided = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
    if (!hash_equals($expected, (string) $provided)) {
        migrate_fail(401, 'unauthorized');
    }
}

$report = ['ok' => true, 'tables' => [], 'errors' => []];

try {
    $supa = new SupabaseWriter(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));
} catch (Throwable $e) {
    migrate_fail(500, 'gagal membuat SupabaseWriter: ' . $e->getMessage());
}

try {
    $pdo = MySQLWriter::pdo();
} catch (Throwable $e) {
    migrate_fail(500, 'gagal konek MySQL: ' . $e->getMessage());
}

// --- 1. Buat tabel dari skema (bila belum ada) ---
$schemaFile = __DIR__ . '/../database/mysql_schema.sql';
if (!is_file($schemaFile)) {
    migrate_fail(500, 'file skema tidak ditemukan: database/mysql_schema.sql');
}
$schema = (string) file_get_contents($schemaFile);
// Buang baris komentar SQL (-- ...) dan blok /* ... */ di awal file,
// lalu pisah per statement. (Tanpa ini, blok komentar -- di awal file
// menyatu dengan CREATE TABLE pertama saat di-split, dan tabel products
// tidak pernah dibuat — FK product_images lalu gagal.)
$lines = explode("\n", $schema);
$lines = array_filter($lines, static function ($ln) {
    return strpos(ltrim($ln), '--') !== 0;
});
$schema = implode("\n", $lines);
$schema = (string) preg_replace('#/\*.*?\*/#s', '', $schema);
$stmts = array_filter(array_map('trim', explode(";\n", $schema)));
$created = 0;
foreach ($stmts as $sql) {
    if ($sql === '' || stripos($sql, 'CREATE TABLE') !== 0) {
        continue;
    }
    $pdo->exec($sql);
    $created++;
}
$report['tables_created'] = $created;

// Matikan FK check selama migrasi (urutan insert jadi bebas; dihidupkan lagi di akhir).
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// --- Helper: baca semua baris Supabase per halaman (limit/offset) ---
$fetchAll = static function (string $table, string $select = '*', int $pageSize = 500) use ($supa): array {
    $out = [];
    $offset = 0;
    while (true) {
        $rows = $supa->request(
            'GET',
            "/rest/v1/{$table}?select={$select}&limit={$pageSize}&offset={$offset}"
        );
        // GAGAL KERAS bila baca Supabase error (kunci salah / jaringan putus):
        // JANGAN diam-diam memigrasi 0 baris lalu melapor "cocok".
        if (!is_array($rows) || isset($rows['error']) || isset($rows['message'])) {
            $msg = is_array($rows) ? (string) ($rows['error'] ?? $rows['message'] ?? 'unknown') : 'bukan array';
            migrate_fail(500, "baca Supabase tabel {$table} gagal: {$msg}");
        }
        if ($rows === []) {
            break;
        }
        foreach ($rows as $r) {
            $out[] = $r;
        }
        if (count($rows) < $pageSize) {
            break;
        }
        $offset += $pageSize;
    }
    return $out;
};

$countMysql = static function (string $table) use ($pdo): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
};

$verify = static function (string $table, int $supaCount, int $mysqlCount) use (&$report): void {
    $report['tables'][$table] = [
        'supabase' => $supaCount,
        'mysql' => $mysqlCount,
        'match' => $supaCount >= 0 && $supaCount === $mysqlCount,
    ];
};

// --- 2. products (+ product_images menempel) ---
$writer = new MySQLWriter($pdo);
$products = $fetchAll('products', '*,product_images(*)', 500);
$imagesTotal = 0;
foreach ($products as $p) {
    $imgs = is_array($p['product_images'] ?? null) ? $p['product_images'] : [];
    unset($p['product_images']);
    $saved = $writer->upsertProduct($p);
    if ($saved === null) {
        $report['errors'][] = 'upsertProduct gagal: ' . ($p['kode_barang'] ?? '?');
        continue;
    }
    // Sinkron gambar (hapus + tulis ulang) supaya persis sama dengan Supabase.
    $normImgs = [];
    foreach ($imgs as $im) {
        $normImgs[] = [
            'id' => $im['id'] ?? null,
            'path' => $im['path'] ?? '',
            'primary' => (bool) ($im['is_primary'] ?? false),
            'sort_order' => (int) ($im['sort_order'] ?? 0),
            'created_at' => $im['created_at'] ?? null,
        ];
    }
    $writer->syncProductImages($saved['id'], $normImgs);
    $imagesTotal += count($normImgs);
}
$verify('products', count($products), $countMysql('products'));
$verify('product_images', $imagesTotal, $countMysql('product_images'));

// --- 3. Taksonomi: brands / vehicles / categories ---
foreach (['brands', 'vehicles', 'categories'] as $t) {
    $rows = $fetchAll($t, '*', 500);
    $ins = $pdo->prepare(
        $t === 'brands'
            ? 'INSERT INTO brands (id, name, short_name, sort_order, created_at) VALUES (?, ?, ?, ?, ?) ' .
              'ON DUPLICATE KEY UPDATE short_name = VALUES(short_name), sort_order = VALUES(sort_order)'
            : ($t === 'vehicles'
                ? 'INSERT INTO vehicles (id, name, brand, sort_order, created_at) VALUES (?, ?, ?, ?, ?) ' .
                  'ON DUPLICATE KEY UPDATE brand = VALUES(brand), sort_order = VALUES(sort_order)'
                : 'INSERT INTO categories (id, name, slug, parent_id, description, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?) ' .
                  'ON DUPLICATE KEY UPDATE slug = VALUES(slug), parent_id = VALUES(parent_id), description = VALUES(description), sort_order = VALUES(sort_order)')
    );
    foreach ($rows as $r) {
        if ($t === 'brands') {
            $ins->execute([
                (string) $r['id'], (string) $r['name'], $r['short_name'] ?? null,
                (int) ($r['sort_order'] ?? 0), MySQLWriter::toMysqlDatetime($r['created_at'] ?? null),
            ]);
        } elseif ($t === 'vehicles') {
            $ins->execute([
                (string) $r['id'], (string) $r['name'], $r['brand'] ?? null,
                (int) ($r['sort_order'] ?? 0), MySQLWriter::toMysqlDatetime($r['created_at'] ?? null),
            ]);
        } else {
            $ins->execute([
                (string) $r['id'], (string) $r['name'], $r['slug'] ?? null, $r['parent_id'] ?? null,
                $r['description'] ?? null, (int) ($r['sort_order'] ?? 0),
                MySQLWriter::toMysqlDatetime($r['created_at'] ?? null),
            ]);
        }
    }
    $verify($t, count($rows), $countMysql($t));
}

// --- 4. marketplaces / company_profile / hero_slides ---
$marketplaces = $fetchAll('marketplaces', '*', 500);
foreach ($marketplaces as $m) {
    $st = $pdo->prepare(
        'INSERT INTO marketplaces (id, name, slug, url, icon, is_active, sort_order, created_at, updated_at) ' .
        'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE ' .
        'name = VALUES(name), url = VALUES(url), icon = VALUES(icon), is_active = VALUES(is_active), sort_order = VALUES(sort_order)'
    );
    $st->execute([
        (int) $m['id'], (string) $m['name'], (string) $m['slug'], (string) $m['url'],
        (string) ($m['icon'] ?? 'shopping-bag'), (bool) ($m['is_active'] ?? true) ? 1 : 0,
        (int) ($m['sort_order'] ?? 0),
        MySQLWriter::toMysqlDatetime($m['created_at'] ?? null),
        MySQLWriter::toMysqlDatetime($m['updated_at'] ?? null),
    ]);
}
$verify('marketplaces', count($marketplaces), $countMysql('marketplaces'));

$profiles = $fetchAll('company_profile', '*', 10);
foreach ($profiles as $p) {
    $writer->saveCompanyProfile([
        'company_name' => $p['company_name'] ?? 'CV Sri Rejeki Motor',
        'tagline' => $p['tagline'] ?? null,
        'description' => $p['description'] ?? null,
        'address' => $p['address'] ?? null,
        'whatsapp' => $p['whatsapp'] ?? null,
        'phone' => $p['phone'] ?? null,
        'email' => $p['email'] ?? null,
        'google_maps_embed' => $p['google_maps_embed'] ?? null,
        'operational_hours' => $p['operational_hours'] ?? null,
        'updated_at' => $p['updated_at'] ?? null,
    ]);
}
$verify('company_profile', count($profiles), $countMysql('company_profile'));

$slides = $fetchAll('hero_slides', '*', 500);
if ($slides !== []) {
    $writer->saveHeroSlides($slides);
}
$verify('hero_slides', count($slides), $countMysql('hero_slides'));

// --- 5. admin_users (ID + password_hash dipertahankan apa adanya) ---
$users = $fetchAll('admin_users', '*', 100);
foreach ($users as $u) {
    $st = $pdo->prepare(
        'INSERT INTO admin_users (id, username, password_hash, role, created_at, updated_at) ' .
        'VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE ' .
        'password_hash = VALUES(password_hash), role = VALUES(role)'
    );
    $st->execute([
        (string) $u['id'], (string) $u['username'], (string) $u['password_hash'],
        (string) ($u['role'] ?? 'superadmin'),
        MySQLWriter::toMysqlDatetime($u['created_at'] ?? null),
        MySQLWriter::toMysqlDatetime($u['updated_at'] ?? null),
    ]);
}
$verify('admin_users', count($users), $countMysql('admin_users'));

// --- 6. login_throttle + admin_audit_log ---
$throttles = $fetchAll('login_throttle', '*', 500);
foreach ($throttles as $t) {
    $st = $pdo->prepare(
        'INSERT INTO login_throttle (ip, attempts, locked_until) VALUES (?, ?, ?) ' .
        'ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until)'
    );
    $st->execute([
        (string) $t['ip'], (int) ($t['attempts'] ?? 0),
        MySQLWriter::toMysqlDatetime($t['locked_until'] ?? null),
    ]);
}
$verify('login_throttle', count($throttles), $countMysql('login_throttle'));

$audit = $fetchAll('admin_audit_log', '*', 500);
foreach ($audit as $a) {
    $st = $pdo->prepare(
        'INSERT INTO admin_audit_log (id, admin_username, action, detail, created_at) ' .
        'VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE admin_username = VALUES(admin_username), action = VALUES(action), detail = VALUES(detail)'
    );
    $st->execute([
        (int) $a['id'], (string) ($a['admin_username'] ?? 'unknown'),
        (string) ($a['action'] ?? ''), (string) ($a['detail'] ?? ''),
        MySQLWriter::toMysqlDatetime($a['created_at'] ?? null),
    ]);
}
$verify('admin_audit_log', count($audit), $countMysql('admin_audit_log'));

// --- 7. Statistik kunjungan ---
$statHarian = $fetchAll('statistik_kunjungan', '*', 500);
foreach ($statHarian as $s) {
    $st = $pdo->prepare(
        'INSERT INTO statistik_kunjungan (tanggal, pengunjung) VALUES (?, ?) ' .
        'ON DUPLICATE KEY UPDATE pengunjung = VALUES(pengunjung)'
    );
    $st->execute([(string) $s['tanggal'], (int) ($s['pengunjung'] ?? 0)]);
}
$verify('statistik_kunjungan', count($statHarian), $countMysql('statistik_kunjungan'));

$statIp = $fetchAll('statistik_kunjungan_ip', '*', 500);
foreach ($statIp as $s) {
    $st = $pdo->prepare(
        'INSERT IGNORE INTO statistik_kunjungan_ip (tanggal, ip_hash) VALUES (?, ?)'
    );
    $st->execute([(string) $s['tanggal'], (string) $s['ip_hash']]);
}
$verify('statistik_kunjungan_ip', count($statIp), $countMysql('statistik_kunjungan_ip'));

// --- Selesai: hidupkan lagi FK check + rangkuman ---
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$allMatch = true;
foreach ($report['tables'] as $t) {
    if (empty($t['match'])) {
        $allMatch = false;
    }
}
$report['all_match'] = $allMatch;
$report['note'] = $allMatch
    ? 'Migrasi selesai, semua jumlah baris cocok dengan Supabase.'
    : 'Ada tabel yang jumlahnya TIDAK cocok — periksa sebelum beralih ke MySQL.';

if (php_sapi_name() === 'cli') {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

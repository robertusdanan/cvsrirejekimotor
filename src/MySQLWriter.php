<?php
declare(strict_types=1);

/**
 * MySQL writer — pengganti SupabaseWriter (per Danan 2026-11-19:
 * database penuh MySQL di cPanel, Supabase free tier ditinggalkan).
 *
 * Interface publiknya SENGAJA disamakan dengan SupabaseWriter
 * (getProducts, getProductByKode, upsertProduct, syncProductImages,
 * getMarketplaces, saveMarketplaces, getCompanyProfile, saveCompanyProfile,
 * getHeroSlides, saveHeroSlides) supaya call site hampir tidak berubah.
 *
 * Yang TIDAK ada di sini (dan memang tidak boleh ditiru dari REST):
 * - request() generik ala PostgREST — diganti metode eksplisit
 *   getTaxonomyTable / upsertTaxonomyRows (dipakai CacheBuilder),
 *   getLoginThrottle / saveLoginThrottle / clearLoginThrottle /
 *   logAudit (dipakai Auth), dan catatKunjungan / bacaStatistik
 *   (dipakai StatistikKunjungan; dulu dua fungsi RPC Postgres).
 * - callRpc() — logika kedua fungsi RPC ditulis langsung di sini
 *   (tanggal memakai zona Asia/Jakarta, sama seperti fungsi aslinya).
 *
 * Koneksi: PDO singleton (satu koneksi per request), charset utf8mb4,
 * ERRMODE_EXCEPTION. Kredensial dari config DB_HOST/DB_PORT/DB_NAME/
 * DB_USER/DB_PASS (~/private/.env). Bila belum dikonfigurasi -> exception
 * jelas (fail-closed), bukan koneksi diam-diam ke database yang salah.
 *
 * Pemetaan tipe (Supabase -> MySQL): UUID -> CHAR(36) (nilai dipertahankan),
 * TIMESTAMPTZ -> DATETIME (UTC, dinormalkan ke 'Y-m-d H:i:s'),
 * JSONB -> JSON (NULL dibaca sebagai [] / default), BOOLEAN -> TINYINT(1).
 */
final class MySQLWriter
{
    /** @var PDO|null */
    private static $sharedPdo = null;

    /** @var PDO */
    private $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? self::pdo();
    }

    /**
     * Koneksi PDO bersama (singleton). Dipakai juga oleh MySQLAdminUsers
     * supaya admin + katalog memakai satu koneksi yang sama.
     */
    public static function pdo(): PDO
    {
        if (self::$sharedPdo !== null) {
            return self::$sharedPdo;
        }
        $host = (string) (config('DB_HOST') ?: 'localhost');
        $port = (string) (config('DB_PORT') ?: '3306');
        $name = (string) (config('DB_NAME') ?: '');
        $user = (string) (config('DB_USER') ?: '');
        $pass = (string) (config('DB_PASS') ?: '');
        if ($name === '' || $user === '') {
            throw new RuntimeException(
                'MySQL belum dikonfigurasi: isi DB_NAME, DB_USER, DB_PASS di ~/private/.env'
            );
        }
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$sharedPdo = $pdo;
        return $pdo;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Normalkan nilai tanggal (ISO-8601 Supabase / 'Y-m-d H:i:s' / timestamp) ke DATETIME MySQL (UTC). */
    public static function toMysqlDatetime($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }
        $ts = strtotime((string) $v);
        if ($ts === false) {
            return null;
        }
        return gmdate('Y-m-d H:i:s', $ts);
    }

    /** Jam sekarang (UTC) dalam format DATETIME MySQL. */
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** UUID v4 (untuk id baris baru; format sama seperti gen_random_uuid()). */
    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** Tanggal hari ini dalam zona Asia/Jakarta (dipakai statistik kunjungan). */
    public static function todayJakarta(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    private static function asBool($v): bool
    {
        return (bool) $v;
    }

    private static function asJson($v): string
    {
        if ($v === null || $v === '') {
            return '[]';
        }
        if (is_string($v)) {
            $d = json_decode($v, true);
            return is_array($d) ? $v : '[]';
        }
        if (is_array($v)) {
            return (string) json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return '[]';
    }

    private static function fromJson($v, $default = []): array
    {
        if ($v === null || $v === '') {
            return $default;
        }
        if (is_array($v)) {
            return $v;
        }
        $d = json_decode((string) $v, true);
        return is_array($d) ? $d : $default;
    }

    /** Bentuk baris produk seperti yang dikembalikan Supabase (marketplaces array, featured bool, product_images menempel). */
    private function normalizeProduct(array $r, array $imagesByProduct = []): array
    {
        $r['marketplaces'] = self::fromJson($r['marketplaces'] ?? null, []);
        $r['featured'] = self::asBool($r['featured'] ?? false);
        $r['stok'] = isset($r['stok']) ? (float) $r['stok'] : 0.0;
        $r['harga_jual'] = isset($r['harga_jual']) ? (int) $r['harga_jual'] : 0;
        $r['sort_order'] = isset($r['sort_order']) ? (int) $r['sort_order'] : 0;
        $r['product_images'] = $imagesByProduct[$r['id']] ?? [];
        return $r;
    }

    /** Ambil semua gambar produk (dikelompokkan per product_id, urut sort_order). */
    private function allImagesGrouped(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, product_id, path, is_primary, sort_order, created_at ' .
            'FROM product_images ORDER BY product_id ASC, sort_order ASC, created_at ASC'
        )->fetchAll();
        $out = [];
        foreach ($rows as $im) {
            $im['is_primary'] = self::asBool($im['is_primary'] ?? false);
            $im['sort_order'] = (int) ($im['sort_order'] ?? 0);
            $out[$im['product_id']][] = $im;
        }
        return $out;
    }

    /** Ambil gambar untuk satu produk (atau satu kode via join). */
    private function imagesForProduct(string $productId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, product_id, path, is_primary, sort_order, created_at ' .
            'FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, created_at ASC'
        );
        $st->execute([$productId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$im) {
            $im['is_primary'] = self::asBool($im['is_primary'] ?? false);
            $im['sort_order'] = (int) ($im['sort_order'] ?? 0);
        }
        return $rows;
    }

    // =========================================================================
    // PRODUCTS & PRODUCT IMAGES
    // =========================================================================

    public function getProducts(?string $search = null, ?int $limit = null, int $offset = 0): array
    {
        $sql = 'SELECT id, kode_barang, nama_produk, nama_mobil, nama_lain, merk, category, ' .
            'stok, harga_jual, description, featured, sort_order, marketplaces, created_at, updated_at ' .
            'FROM products';
        $params = [];
        if ($search !== null && $search !== '') {
            $sql .= ' WHERE kode_barang LIKE ? OR nama_produk LIKE ? OR merk LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' ORDER BY sort_order ASC, created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ? OFFSET ?';
        }
        $st = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $st->bindValue($i++, $p, PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $st->bindValue($i++, max(1, $limit), PDO::PARAM_INT);
            $st->bindValue($i++, max(0, $offset), PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();
        $grouped = $this->allImagesGrouped();
        $out = [];
        foreach ($rows as $r) {
            $out[] = $this->normalizeProduct($r, $grouped);
        }
        return $out;
    }

    /** Jumlah produk (untuk pagination) — pencarian yang sama seperti getProducts. */
    public function countProducts(?string $search = null): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM products';
        $params = [];
        if ($search !== null && $search !== '') {
            $sql .= ' WHERE kode_barang LIKE ? OR nama_produk LIKE ? OR merk LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return (int) ($r['c'] ?? 0);
    }

    public function getProductByKode(string $kode): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, kode_barang, nama_produk, nama_mobil, nama_lain, merk, category, ' .
            'stok, harga_jual, description, featured, sort_order, marketplaces, created_at, updated_at ' .
            'FROM products WHERE kode_barang = ? LIMIT 1'
        );
        $st->execute([$kode]);
        $r = $st->fetch();
        if ($r === false) {
            return null;
        }
        return $this->normalizeProduct($r, [$r['id'] => $this->imagesForProduct($r['id'])]);
    }

    public function getProductById(string $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, kode_barang, nama_produk, nama_mobil, nama_lain, merk, category, ' .
            'stok, harga_jual, description, featured, sort_order, marketplaces, created_at, updated_at ' .
            'FROM products WHERE id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $r = $st->fetch();
        if ($r === false) {
            return null;
        }
        return $this->normalizeProduct($r, [$r['id'] => $this->imagesForProduct($r['id'])]);
    }

    public function upsertProduct(array $data): ?array
    {
        $id = !empty($data['id']) ? (string) $data['id'] : self::uuid();
        $kode = trim((string) ($data['kode_barang'] ?? ''));
        if ($kode === '') {
            return null;
        }
        $now = self::now();
        $createdAt = self::toMysqlDatetime($data['created_at'] ?? null) ?? $now;
        $updatedAt = self::toMysqlDatetime($data['updated_at'] ?? null) ?? $now;
        $sql = 'INSERT INTO products ' .
            '(id, kode_barang, nama_produk, nama_mobil, nama_lain, merk, category, stok, harga_jual, ' .
            'description, featured, sort_order, marketplaces, created_at, updated_at) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE ' .
            'nama_produk = VALUES(nama_produk), nama_mobil = VALUES(nama_mobil), ' .
            'nama_lain = VALUES(nama_lain), merk = VALUES(merk), category = VALUES(category), ' .
            'stok = VALUES(stok), harga_jual = VALUES(harga_jual), description = VALUES(description), ' .
            'featured = VALUES(featured), sort_order = VALUES(sort_order), ' .
            'marketplaces = VALUES(marketplaces), updated_at = VALUES(updated_at)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $id,
            $kode,
            (string) ($data['nama_produk'] ?? ''),
            $data['nama_mobil'] ?? null,
            $data['nama_lain'] ?? null,
            $data['merk'] ?? null,
            $data['category'] ?? null,
            (float) ($data['stok'] ?? 0),
            (int) ($data['harga_jual'] ?? 0),
            $data['description'] ?? null,
            self::asBool($data['featured'] ?? false) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            self::asJson($data['marketplaces'] ?? []),
            $createdAt,
            $updatedAt,
        ]);
        return $this->getProductByKode($kode);
    }

    public function deleteProduct(string $kodeOrId): bool
    {
        try {
            $this->pdo->beginTransaction();
            // Hapus gambar dulu (FK CASCADE juga menangani, tapi eksplisit lebih aman).
            $st = $this->pdo->prepare(
                'DELETE FROM product_images WHERE product_id IN ' .
                '(SELECT id FROM products WHERE id = ? OR kode_barang = ?)'
            );
            $st->execute([$kodeOrId, $kodeOrId]);
            $st = $this->pdo->prepare('DELETE FROM products WHERE id = ? OR kode_barang = ?');
            $st->execute([$kodeOrId, $kodeOrId]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('MySQLWriter::deleteProduct gagal: ' . $e->getMessage());
            return false;
        }
    }

    public function syncProductImages(string $productId, array $images): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
            $st->execute([$productId]);
            if ($images !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO product_images (id, product_id, path, is_primary, sort_order, created_at) ' .
                    'VALUES (?, ?, ?, ?, ?, ?)'
                );
                $now = self::now();
                foreach ($images as $idx => $img) {
                    $ins->execute([
                        !empty($img['id']) ? (string) $img['id'] : self::uuid(),
                        $productId,
                        (string) ($img['path'] ?? ''),
                        (bool) ($img['primary'] ?? $img['is_primary'] ?? ($idx === 0)) ? 1 : 0,
                        (int) ($img['sort_order'] ?? $idx),
                        self::toMysqlDatetime($img['created_at'] ?? null) ?? $now,
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // TAXONOMY (brands / vehicles / categories) — dipakai CacheBuilder
    // =========================================================================

    /** Daftar tabel taksonomi yang boleh dibaca/ditulis lewat metode ini. */
    private const TAXONOMY_TABLES = ['brands', 'vehicles', 'categories'];

    /** Kolom yang boleh dipilih per tabel taksonomi (mencegah injeksi via nama kolom). */
    private const TAXONOMY_COLS = [
        'brands' => ['id', 'name', 'short_name', 'sort_order', 'created_at'],
        'vehicles' => ['id', 'name', 'brand', 'sort_order', 'created_at'],
        'categories' => ['id', 'name', 'slug', 'parent_id', 'description', 'sort_order', 'created_at'],
    ];

    public function getTaxonomyTable(string $table, string $cols): array
    {
        if (!in_array($table, self::TAXONOMY_TABLES, true)) {
            return [];
        }
        $allowed = self::TAXONOMY_COLS[$table];
        $wanted = array_map('trim', explode(',', $cols));
        $picked = [];
        foreach ($wanted as $c) {
            if (in_array($c, $allowed, true)) {
                $picked[] = '`' . $c . '`';
            }
        }
        if ($picked === []) {
            return [];
        }
        $sql = 'SELECT ' . implode(', ', $picked) . ' FROM `' . $table . '` ' .
            'ORDER BY sort_order ASC, name ASC LIMIT 1000';
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Upsert baris taksonomi (konflik di `name`): baris baru dari produk
     * (mis. merk/kendaraan/kategori yang belum ada di registry).
     * Tiap baris: ['name' => ..., 'sort_order' => ...] + kolom opsional.
     */
    public function upsertTaxonomyRows(string $table, array $rows): void
    {
        if (!in_array($table, self::TAXONOMY_TABLES, true) || $rows === []) {
            return;
        }
        $allowed = self::TAXONOMY_COLS[$table];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $cols = ['id', 'name'];
            $vals = [self::uuid(), $name];
            $updates = [];
            foreach (['short_name', 'brand', 'slug', 'parent_id', 'description', 'sort_order'] as $c) {
                if (!in_array($c, $allowed, true)) {
                    continue;
                }
                if (array_key_exists($c, $row)) {
                    $cols[] = $c;
                    $vals[] = $c === 'sort_order' ? (int) $row[$c] : $row[$c];
                    $updates[] = '`' . $c . '` = VALUES(`' . $c . '`)';
                }
            }
            // sort_order selalu ada (default 9999 supaya nilai baru muncul di akhir).
            if (!in_array('sort_order', $cols, true)) {
                $cols[] = 'sort_order';
                $vals[] = (int) ($row['sort_order'] ?? 9999);
            }
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $colList = '`' . implode('`, `', $cols) . '`';
            $sql = "INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders}) " .
                'ON DUPLICATE KEY UPDATE ' . ($updates === [] ? 'name = VALUES(name)' : implode(', ', $updates));
            $st = $this->pdo->prepare($sql);
            $st->execute($vals);
        }
    }

    // =========================================================================
    // MARKETPLACES
    // =========================================================================

    public function getMarketplaces(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, slug, url, icon, is_active, sort_order, created_at, updated_at ' .
            'FROM marketplaces ORDER BY sort_order ASC, created_at ASC'
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['is_active'] = self::asBool($r['is_active'] ?? true);
            $r['sort_order'] = (int) ($r['sort_order'] ?? 0);
        }
        return $rows;
    }

    public function saveMarketplaces(array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM marketplaces');
            if ($items !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO marketplaces (name, slug, url, icon, is_active, sort_order) ' .
                    'VALUES (?, ?, ?, ?, ?, ?)'
                );
                foreach ($items as $idx => $item) {
                    $ins->execute([
                        (string) ($item['name'] ?? 'Marketplace'),
                        (string) ($item['slug'] ?? ('mp-' . ($idx + 1))),
                        (string) ($item['url'] ?? '#'),
                        (string) ($item['icon'] ?? 'shopping-bag'),
                        (bool) ($item['active'] ?? $item['is_active'] ?? true) ? 1 : 0,
                        (int) ($item['sort_order'] ?? $idx),
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // COMPANY PROFILE
    // =========================================================================

    public function getCompanyProfile(): array
    {
        $st = $this->pdo->query(
            'SELECT id, company_name, tagline, description, address, whatsapp, phone, email, ' .
            'google_maps_embed, operational_hours, updated_at FROM company_profile WHERE id = 1 LIMIT 1'
        );
        $r = $st->fetch();
        if (is_array($r) && !empty($r)) {
            $r['operational_hours'] = self::fromJson($r['operational_hours'] ?? null, [
                'workdays' => 'Senin - Sabtu: 08:00 - 17:00',
                'sunday' => 'Minggu / Hari Libur: Tutup',
            ]);
            return $r;
        }
        return [
            'id' => 1,
            'company_name' => 'CV Sri Rejeki Motor',
            'tagline' => 'Pusat Suku Cadang & Onderdil Mobil Terlengkap',
            'description' => 'Pusat suku cadang dan onderdil mobil terlengkap di Tulungagung.',
            'address' => 'Jl. Dr. Sutomo No. 42, Tulungagung, Jawa Timur',
            'whatsapp' => '6281234567890',
            'phone' => '0355-321456',
            'email' => 'info@srirejekimotor.com',
            'google_maps_embed' => '',
            'operational_hours' => [
                'workdays' => 'Senin - Sabtu: 08:00 - 17:00',
                'sunday' => 'Minggu / Hari Libur: Tutup',
            ],
        ];
    }

    public function saveCompanyProfile(array $data): void
    {
        $data['id'] = 1;
        $data['updated_at'] = self::now();
        $sql = 'INSERT INTO company_profile ' .
            '(id, company_name, tagline, description, address, whatsapp, phone, email, google_maps_embed, operational_hours, updated_at) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE ' .
            'company_name = VALUES(company_name), tagline = VALUES(tagline), ' .
            'description = VALUES(description), address = VALUES(address), ' .
            'whatsapp = VALUES(whatsapp), phone = VALUES(phone), email = VALUES(email), ' .
            'google_maps_embed = VALUES(google_maps_embed), operational_hours = VALUES(operational_hours), ' .
            'updated_at = VALUES(updated_at)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            1,
            (string) ($data['company_name'] ?? 'CV Sri Rejeki Motor'),
            $data['tagline'] ?? null,
            $data['description'] ?? null,
            $data['address'] ?? null,
            $data['whatsapp'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['google_maps_embed'] ?? null,
            isset($data['operational_hours'])
                ? (is_string($data['operational_hours']) ? $data['operational_hours'] : (string) json_encode($data['operational_hours'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                : null,
            self::toMysqlDatetime($data['updated_at'] ?? null) ?? self::now(),
        ]);
    }

    // =========================================================================
    // HERO SLIDES
    // =========================================================================

    public function getHeroSlides(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, title, subtitle, media_type, media_url, button_text, button_url, is_active, sort_order, created_at, updated_at ' .
            'FROM hero_slides ORDER BY sort_order ASC, created_at DESC'
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['is_active'] = self::asBool($r['is_active'] ?? true);
            $r['sort_order'] = (int) ($r['sort_order'] ?? 0);
        }
        return $rows;
    }

    public function saveHeroSlides(array $slides): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM hero_slides');
            if ($slides !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO hero_slides ' .
                    '(id, title, subtitle, media_type, media_url, button_text, button_url, is_active, sort_order) ' .
                    'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($slides as $idx => $s) {
                    $ins->execute([
                        !empty($s['id']) ? (string) $s['id'] : self::uuid(),
                        (string) ($s['title'] ?? ''),
                        (string) ($s['subtitle'] ?? ''),
                        (string) ($s['media_type'] ?? 'image'),
                        (string) ($s['media_url'] ?? $s['image_url'] ?? $s['video_url'] ?? ''),
                        (string) ($s['button_text'] ?? 'Lihat Produk'),
                        (string) ($s['button_url'] ?? '/produk'),
                        (bool) ($s['is_active'] ?? true) ? 1 : 0,
                        (int) ($s['sort_order'] ?? $idx),
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // LOGIN THROTTLE + AUDIT LOG (dipakai Auth)
    // =========================================================================

    /** @return array{attempts:int,locked_until:?string}|null */
    public function getLoginThrottle(string $ip): ?array
    {
        $st = $this->pdo->prepare('SELECT attempts, locked_until FROM login_throttle WHERE ip = ? LIMIT 1');
        $st->execute([$ip]);
        $r = $st->fetch();
        if ($r === false) {
            return null;
        }
        return ['attempts' => (int) $r['attempts'], 'locked_until' => $r['locked_until']];
    }

    public function saveLoginThrottle(string $ip, int $attempts, ?string $lockedUntil): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO login_throttle (ip, attempts, locked_until) VALUES (?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until)'
        );
        $st->execute([$ip, $attempts, self::toMysqlDatetime($lockedUntil)]);
    }

    public function clearLoginThrottle(string $ip): void
    {
        $st = $this->pdo->prepare('DELETE FROM login_throttle WHERE ip = ?');
        $st->execute([$ip]);
    }

    public function logAudit(string $username, string $action, string $detail = ''): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO admin_audit_log (admin_username, action, detail) VALUES (?, ?, ?)'
        );
        $st->execute([$username, $action, $detail]);
    }

    // =========================================================================
    // STATISTIK KUNJUNGAN (pengganti fungsi RPC catat_kunjungan / baca_statistik)
    // =========================================================================

    /**
     * Catat 1 kunjungan untuk hash IP ini (bila belum terhitung hari ini),
     * lalu kembalikan jumlah hari ini + total. Sama seperti fungsi RPC
     * Postgres aslinya: dedup per IP per hari (WIB), jejak dedup lama
     * dibuang, INSERT IGNORE membuat dua request bersamaan tetap 1x.
     *
     * @return array{hari_ini:int,total:int}|null
     */
    public function catatKunjungan(string $hash): ?array
    {
        $today = self::todayJakarta();
        try {
            $this->pdo->beginTransaction();
            $st = $this->pdo->prepare('DELETE FROM statistik_kunjungan_ip WHERE tanggal < ?');
            $st->execute([$today]);
            $st = $this->pdo->prepare(
                'INSERT IGNORE INTO statistik_kunjungan_ip (tanggal, ip_hash) VALUES (?, ?)'
            );
            $st->execute([$today, $hash]);
            $baru = $st->rowCount() > 0;
            if ($baru) {
                $st = $this->pdo->prepare(
                    'INSERT INTO statistik_kunjungan (tanggal, pengunjung) VALUES (?, 1) ' .
                    'ON DUPLICATE KEY UPDATE pengunjung = pengunjung + 1, updated_at = NOW()'
                );
                $st->execute([$today]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('MySQLWriter::catatKunjungan gagal: ' . $e->getMessage());
            return null;
        }
        return $this->bacaStatistik();
    }

    /** Baca angka untuk footer tanpa menulis apa pun. @return array{hari_ini:int,total:int}|null */
    public function bacaStatistik(): ?array
    {
        $today = self::todayJakarta();
        try {
            $st = $this->pdo->prepare('SELECT pengunjung FROM statistik_kunjungan WHERE tanggal = ? LIMIT 1');
            $st->execute([$today]);
            $r = $st->fetch();
            $hariIni = $r !== false ? (int) $r['pengunjung'] : 0;
            $total = (int) $this->pdo->query('SELECT COALESCE(SUM(pengunjung), 0) FROM statistik_kunjungan')->fetchColumn();
            return ['hari_ini' => $hariIni, 'total' => $total];
        } catch (Throwable $e) {
            error_log('MySQLWriter::bacaStatistik gagal: ' . $e->getMessage());
            return null;
        }
    }
}

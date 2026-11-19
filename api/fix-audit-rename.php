<?php
declare(strict_types=1);

/**
 * Eksekusi audit prefiks merk + varian penulisan (Danan 2026-11-19:
 * "Jalankan audit, copot IDN juga").
 *
 * 1) DROP UNIQUE uk_products_kode_barang (kunci = ID, kode boleh sama).
 * 2) Rename kode_barang ke ejaan kanonis (100 rename: 41 standalone +
 *    anggota 23 kelompok satu-part + 9 rename tunggal + 1 simpan_kanonis).
 * 3) Hapus 3 duplikat sejati (TE48632-27010, ME508085FP, 90919-01059CSR).
 * 4) Rebuild cache + kembalikan peta redirect (slug lama -> slug baru)
 *    untuk dimasukkan ke $legacyRedirects di pages/produk_detail.php.
 *
 * POST + X-Upload-Token (sama seperti applier lain). Satu transaksi
 * untuk rename+hapus; rebuild cache sesudahnya. Bila guard gagal,
 * TIDAK ada yang diubah.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/MySQLWriter.php';
require __DIR__ . '/../src/CacheBuilder.php';
require __DIR__ . '/../src/R2WriteClient.php';
require __DIR__ . '/../src/ImageHandler.php';

header('Content-Type: application/json');

function fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'method not allowed');
}

$expected = (string) (config('IMAGE_UPLOAD_TOKEN') ?? '');
if ($expected === '') {
    fail(401, 'unauthorized (token belum dikonfigurasi)');
}
$provided = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals($expected, (string) $provided)) {
    fail(401, 'unauthorized');
}

// --- Rencana rename: kode lama => kode baru ---
$renames = [
    '04465-0K100' => '04465-0K100',
    '04465-BZ130-001' => '04465-BZ130',
    '04465-BZ130-008' => '04465-BZ130',
    '04465-BZ300-001' => '04465-BZ300',
    '044650K100' => '04465-0K100',
    '04465BZ300' => '04465-BZ300',
    '04491-87502' => '04491-87502',
    '04491-87502-008' => '04491-87502',
    '04495-0K040' => '04495-0K040',
    '04495-87601' => '04495-87601',
    '04495-87601-008' => '04495-87601',
    '04495-BZ010' => '04495-BZ010',
    '04495-BZ010-008' => '04495-BZ010',
    '04495-BZ100-001' => '04495-BZ100',
    '04495-BZ100-008' => '04495-BZ100',
    '04495-OK140' => '04495-0K140',
    '044950K040' => '04495-0K040',
    '044950K140' => '04495-0K140',
    '31470-OK020' => '31470-0K020',
    '31470-OK040' => '31470-0K040',
    '31470-OK080' => '31470-0K080',
    '31470-OK090' => '31470-0K090',
    '47410-87507' => '47410-87507',
    '47410-87507-008' => '47410-87507',
    '48632-OK010' => '48632-0K010',
    '48654-OK010' => '48654-0K010',
    '48655-OK010' => '48655-0K010',
    '48750-79030' => '48750-79030',
    '48750-79030-000' => '48750-79030',
    '52119-BZ330-001' => '52119-BZ330',
    '52119-BZN60-001' => '52119-BZN60',
    '52119BZ330' => '52119-BZ330',
    '52119BZN60' => '52119-BZN60',
    '555SB-7152' => 'SB-7152',
    '555SB-T552' => 'SB-T552',
    '6-878322550' => '6-87832-255-0',
    '9004A-91068-001' => '9004A-91068',
    '9004A91068' => '9004A-91068',
    '9091901059' => '90919-01059',
    'DENSOXU22PR9' => 'XU22PR9',
    'GAPMH024000' => 'MH024000',
    'GAPMW030499' => 'MW030499',
    'GPME-700136' => 'ME700136',
    'GPME-701492' => 'ME701492',
    'GW-MB161068' => 'MB161068',
    'GWMW030499' => 'MW030499',
    'I5-11721 016-0' => '5-11721 016-0',
    'I5-44347 004-0' => '5-44347 004-0',
    'I5-87411 891-1' => '5-87411 891-1',
    'I6-41521 035-0' => '1-41521-035-0',
    'I6-87832 255-0' => '6-87832-255-0',
    'I6-87870 028-1' => '5-87870-028-1',
    'I8-94223 366-1' => '8-94223 366-1',
    'I8-94340 597-0' => '8-94340 597-0',
    'I8-94408 840-3' => '8-94408-840-3',
    'I8-97352 889-0' => '8-97352 889-0',
    'I9-41521 044-1' => '9-41521-044-1',
    'IBK-M106-RR-LH' => 'M106-RR-LH',
    'IBK-M106-RR-RH' => 'M106-RR-RH',
    'JADE-17201-E0080' => '17201-E0080',
    'JADE-ME212182' => 'ME212182',
    'JADE1698098425A' => '1698098425A',
    'JADE17201-E0680' => '17201-E0680',
    'JDC51300-77310' => '51300-77310',
    'LKSNI756' => 'NI756',
    'LKSNI904' => 'NI904',
    'MB109936' => 'MB109936',
    'MB109936MARUGO' => 'MB109936',
    'MB301529' => 'MB301529',
    'MB301529GW' => 'MB301529',
    'MB489484' => 'MB489484',
    'MC837547FP' => 'MC837547',
    'MC886180FP' => 'MC886180',
    'MECM191X68471' => 'M191X68471',
    'MH024000' => 'MH024000',
    'MH024000SK' => 'MH024000',
    'MK499638' => 'MK499638',
    'MK499638A' => 'MK499638',
    'MK499638IDN' => 'MK499638',
    'MK529942' => 'MK529942',
    'MK529942IDN' => 'MK529942',
    'MSD-MB161342' => 'MB161342',
    'MSD-ME031888' => 'ME031888',
    'MSD48068-87515' => '48068-87515',
    'MSD48069-87501' => '48069-87501',
    'MSD48069-87515' => '48069-87515',
    'MT160889GW' => 'MT160889',
    'NGKBKR6E' => 'BKR6E',
    'NGKBKR6E11' => 'BKR6E11',
    'NGKBP5ES' => 'BP5ES',
    'NGKBP5ES11' => 'BP5ES11',
    'NGKDCPR7EA9' => 'DCPR7EA9',
    'NGKKR6A10' => 'KR6A10',
    'NGKLZFR5BI-11' => 'LZFR5BI-11',
    'NSKB20141C3' => 'B20141C3',
    'TE-MB109936' => 'MB109936',
    'TE8-94226-557-2' => '8-94226-557-2',
    'TE8-94408-840-3' => '8-94408-840-3',
    'TEMB489484' => 'MB489484',
    'UTM-MD050103' => 'MD050103',
];
$hapus = [
    'TE48632-27010',
    'ME508085FP',
    '90919-01059CSR',
];

$writer = new MySQLWriter();
$pdo = MySQLWriter::pdo();

// --- Guard + muat baris target (id, kode, nama) ---
$targets = []; // kode lama => ['id'=>..., 'nama'=>...]
$stmt = $pdo->prepare('SELECT id, kode_barang, nama_produk FROM products WHERE kode_barang = ?');
foreach (array_keys($renames) as $old) {
    $stmt->execute([$old]);
    $r = $stmt->fetch();
    if ($r === false) {
        fail(400, 'guard gagal: kode lama tidak ditemukan: ' . $old);
    }
    $targets[$old] = ['id' => (string) $r['id'], 'nama' => (string) $r['nama_produk']];
}
$hapusRows = []; // kode => ['id'=>..., 'images'=>[path...]]
$stmtImg = $pdo->prepare('SELECT path FROM product_images WHERE product_id = ?');
foreach ($hapus as $hk) {
    $stmt->execute([$hk]);
    $r = $stmt->fetch();
    if ($r === false) {
        fail(400, 'guard gagal: kode hapus tidak ditemukan: ' . $hk);
    }
    $stmtImg->execute([(string) $r['id']]);
    $imgs = [];
    foreach ($stmtImg->fetchAll() as $im) {
        if (!empty($im['path'])) { $imgs[] = (string) $im['path']; }
    }
    $hapusRows[$hk] = ['id' => (string) $r['id'], 'images' => $imgs];
}
// Slug lama (untuk redirect): slugify(nama + '-' + kode lama).
// Tidak ada tabrakan slug di katalog (dicek 2026-11-19), jadi tanpa -2/-3.
$oldSlugs = [];
foreach ($targets as $old => $t) {
    $oldSlugs[$old] = slugify($t['nama'] . '-' . $old);
}

// --- DROP UNIQUE uk_products_kode_barang (bila masih ada) ---
$idx = $pdo->query("SHOW INDEX FROM products WHERE Key_name = 'uk_products_kode_barang'")->fetchAll();
$uniqueDropped = false;
if ($idx !== []) {
    $pdo->exec('ALTER TABLE products DROP INDEX uk_products_kode_barang');
    $uniqueDropped = true;
}

// --- Rename + hapus dalam SATU transaksi ---
$renamed = 0; $deleted = 0; $r2Deleted = 0;
try {
    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE products SET kode_barang = ? WHERE id = ?');
    foreach ($renames as $old => $new) {
        $upd->execute([$new, $targets[$old]['id']]);
        $renamed += (int) $upd->rowCount();
    }
    $imgHandler = new ImageHandler();
    $delImg = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
    $delProd = $pdo->prepare('DELETE FROM products WHERE id = ?');
    foreach ($hapusRows as $hk => $hr) {
        foreach ($hr['images'] as $p) {
            try { $imgHandler->deleteR2File($p); $r2Deleted++; } catch (Throwable $e) {}
        }
        // JANGAN pakai $writer->deleteProduct() di sini: ia membuka
        // transaksinya sendiri (begin/commit) dan akan meng-commit
        // transaksi luar ini prematur. Hapus langsung via SQL.
        $delImg->execute([$hr['id']]);
        $delProd->execute([$hr['id']]);
        $deleted++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fail(500, 'transaksi gagal (rollback): ' . $e->getMessage());
}

// --- Rebuild cache ---
try {
    @set_time_limit(180);
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildProducts();
    $cacheRebuild = 'ok';
} catch (Throwable $e) {
    fail(500, 'rename+hapus OK tapi rebuild cache gagal: ' . $e->getMessage());
}

// --- Peta redirect: slug lama -> slug baru (dari index cache baru) ---
$cache = new Cache();
$indexData = $cache->read('products/index.json');
$indexItems = is_array($indexData['items'] ?? null) ? $indexData['items'] : [];
$kodeToSlug = [];
foreach ($indexItems as $it) {
    $k = trim((string) ($it['kode_barang'] ?? ''));
    if ($k !== '' && !empty($it['slug'])) { $kodeToSlug[$k] = (string) $it['slug']; }
    foreach (is_array($it['variants'] ?? null) ? $it['variants'] : [] as $v) {
        $vk = trim((string) ($v['kode_barang'] ?? ''));
        if ($vk !== '' && !empty($it['slug'])) { $kodeToSlug[$vk] = (string) $it['slug']; }
    }
}
$redirects = [];
foreach ($renames as $old => $new) {
    $newSlug = $kodeToSlug[$new] ?? null;
    $oldSlug = $oldSlugs[$old];
    if ($newSlug !== null && $oldSlug !== '' && $oldSlug !== $newSlug) {
        $redirects[$oldSlug] = $newSlug;
    }
}

echo json_encode([
    'ok' => true,
    'unique_dropped' => $uniqueDropped,
    'renamed' => $renamed,
    'deleted' => $deleted,
    'r2_deleted' => $r2Deleted,
    'cache_rebuild' => $cacheRebuild,
    'redirects' => $redirects,
]);

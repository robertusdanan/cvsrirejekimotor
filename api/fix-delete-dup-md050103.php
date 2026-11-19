<?php
// Applier sekali pakai: hapus duplikat AP-ATM01-MD050103-1UTM (UTM, Rp350.000,
// AS TEMPLAR, L300) — baris IDENTIK dengan MD050103 (nama, merk, harga,
// kendaraan, deskripsi semua sama; hanya kode yang beda). Danan 2026-11-19:
// "oke berarti hapuslah duplicate nya, tampilkan yang MD050103".
// - Hapus baris produk + baris product_images-nya via MySQLWriter::deleteProduct
//   (membuka transaksi SENDIRI — JANGAN dibungkus transaksi luar).
// - Hapus file R2 di bawah products/{uuid-duplikat}/ (list + delete batch,
//   best-effort; file orphan tidak terlihat di situs).
// - Rebuild cache. Redirect slug lama -> as-templar-md050103 ditambahkan ke
//   $legacyRedirects di pages/produk_detail.php (push yang sama).
// POST + header X-Upload-Token == config('IMAGE_UPLOAD_TOKEN'). Hapus file ini sesudah jalan.
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/MySQLWriter.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/CacheBuilder.php';
require_once __DIR__ . '/../src/R2WriteClient.php';

header('Content-Type: application/json');
$token = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals((string)config('IMAGE_UPLOAD_TOKEN'), $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$pdo = MySQLWriter::pdo();
$out = ['ok' => true];
$st = $pdo->prepare('SELECT id, kode_barang, nama_produk, merk, harga_jual, nama_mobil FROM products WHERE kode_barang IN (?, ?) ORDER BY kode_barang');
$st->execute(['AP-ATM01-MD050103-1UTM', 'MD050103']);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$out['guard'] = ['ditemukan' => count($rows), 'baris' => $rows];
if (count($rows) !== 2) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: kedua kode harus ada';
    echo json_encode($out);
    exit;
}
$dup = null;
$keep = null;
foreach ($rows as $r) {
    if ($r['kode_barang'] === 'AP-ATM01-MD050103-1UTM') {
        $dup = $r;
    } else {
        $keep = $r;
    }
}
if ($dup['merk'] !== $keep['merk'] || $dup['nama_produk'] !== $keep['nama_produk'] || (int)$dup['harga_jual'] !== (int)$keep['harga_jual']) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: kedua baris tidak identik (merk/nama/harga beda)';
    echo json_encode($out);
    exit;
}
$st = $pdo->prepare('SELECT path FROM product_images WHERE product_id = ?');
$st->execute([$dup['id']]);
$out['gambar_duplikat'] = $st->fetchAll(PDO::FETCH_COLUMN);
$writer = new MySQLWriter();
if (!$writer->deleteProduct('AP-ATM01-MD050103-1UTM')) {
    $out['ok'] = false;
    $out['error'] = 'deleteProduct gagal';
    echo json_encode($out);
    exit;
}
$out['dihapus'] = $dup['id'];
try {
    $r2 = new R2WriteClient(
        (string)config('R2_ACCESS_KEY_ID'),
        (string)config('R2_SECRET_ACCESS_KEY'),
        (string)config('R2_S3_CLIENTS'),
        (string)config('R2_BUCKET')
    );
    $keys = $r2->listAllObjects('products/' . $dup['id'] . '/');
    $out['r2_keys'] = $keys;
    if ($keys !== []) {
        $out['r2_delete'] = $r2->deleteObjectsBatch($keys);
    }
} catch (Throwable $e) {
    $out['r2_delete'] = 'GAGAL (non-fatal, file orphan tidak terlihat): ' . $e->getMessage();
}
try {
    $cache = new Cache();
    $builder = new CacheBuilder(new MySQLWriter(), $cache);
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
    $idx = $cache->read('products/index.json');
    $out['cache_produk'] = $idx['count'] ?? null;
    $keepSlug = null;
    foreach (($idx['items'] ?? []) as $it) {
        if (($it['kode_barang'] ?? '') === 'MD050103') {
            $keepSlug = $it['slug'] ?? null;
            break;
        }
    }
    $out['slug_kept'] = $keepSlug;
    if ($keepSlug === null) {
        $out['ok'] = false;
        $out['error'] = 'MD050103 tidak ditemukan di index sesudah rebuild';
    }
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
}
echo json_encode($out);

<?php
// Applier sekali pakai: strip prefix SNC dari SNCMK384470 -> MK384470 (SANYCO,
// BRAKE BOOSTER, Canter, Rp1.300.000). SNC = Sanyco (merk sudah SANYCO, hanya
// kode yang membawa prefix). Nomor MK384470 terverifikasi kuat sebagai brake
// booster Canter (cross-ref 865-03701). Danan 2026-11-19: "iya MK384470,
// disini SNC yang berarti Sanyco".
// Gambar menempel pada ID produk (bukan kode), jadi rename tidak mengganggu
// gambar. Redirect slug lama -> slug baru ditambahkan ke $legacyRedirects di
// pages/produk_detail.php (push yang sama).
// POST + header X-Upload-Token == config('IMAGE_UPLOAD_TOKEN'). Hapus file ini sesudah jalan.
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/MySQLWriter.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/CacheBuilder.php';

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
$st = $pdo->prepare('SELECT id, kode_barang, nama_produk, merk, harga_jual, nama_mobil FROM products WHERE kode_barang = ?');
$st->execute(['SNCMK384470']);
$row = $st->fetch(PDO::FETCH_ASSOC);
$out['guard'] = ['ditemukan' => $row ? 1 : 0, 'baris' => $row];
if (!$row) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: SNCMK384470 tidak ada';
    echo json_encode($out);
    exit;
}
$st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE kode_barang = ?');
$st->execute(['MK384470']);
if ((int)$st->fetchColumn() !== 0) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: MK384470 sudah ada';
    echo json_encode($out);
    exit;
}
$out['slug_lama'] = slugify($row['nama_produk'] . '-' . $row['kode_barang']);
$st = $pdo->prepare("UPDATE products SET kode_barang = 'MK384470' WHERE id = ?");
$st->execute([$row['id']]);
$out['diubah'] = $st->rowCount();
try {
    $cache = new Cache();
    $builder = new CacheBuilder(new MySQLWriter(), $cache);
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
    $idx = $cache->read('products/index.json');
    $out['cache_produk'] = $idx['count'] ?? null;
    $found = null;
    foreach (($idx['items'] ?? []) as $it) {
        if (($it['kode_barang'] ?? '') === 'MK384470') {
            $found = $it;
            break;
        }
    }
    if ($found === null) {
        $out['ok'] = false;
        $out['error'] = 'MK384470 tidak ditemukan di index sesudah rebuild';
    } else {
        $out['slug_baru'] = $found['slug'] ?? null;
        $out['gambar'] = $found['primary_image'] ?? null;
    }
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
}
echo json_encode($out);

<?php
// Applier sekali pakai: gabungkan 90919T1004 (TOYOTA, Rp37.500) + K20HRU11DENSO
// (DENSO, Rp20.000) menjadi SATU produk — keduanya busi fisik yang sama
// (DENSO K20HR-U11). Danan 2026-11-19: "oke gabungkan".
// Kanonis = 90919-T1004 (nomor OE Toyota, sesuai pola audit: bentuk OE pabrikan
// kendaraan menang). Kedua baris tetap terpisah (kunci = ID), dibedakan merk.
// POST + header X-Upload-Token == config('IMAGE_UPLOAD_TOKEN'). Hapus file ini sesudah jalan.
// PENTING: JALANKAN HANYA SESUDAH upload batch 32 selesai — upload mencari gambar
// berdasarkan kode_barang lama.
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
$st = $pdo->prepare('SELECT id, kode_barang, nama_produk, merk FROM products WHERE kode_barang IN (?, ?) ORDER BY id');
$st->execute(['90919T1004', 'K20HRU11DENSO']);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$out['guard'] = ['ditemukan' => count($rows), 'baris' => $rows];
if (count($rows) !== 2) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: kedua kode harus ada';
    echo json_encode($out);
    exit;
}
$st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE kode_barang = ?');
$st->execute(['90919-T1004']);
if ((int)$st->fetchColumn() !== 0) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: 90919-T1004 sudah ada';
    echo json_encode($out);
    exit;
}
$oldSlugs = [];
foreach ($rows as $r) {
    $oldSlugs[] = slugify($r['nama_produk'] . '-' . $r['kode_barang']);
}
$out['slug_lama'] = $oldSlugs;
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('UPDATE products SET kode_barang = ? WHERE id = ?');
    foreach ($rows as $r) {
        $st->execute(['90919-T1004', $r['id']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $out['ok'] = false;
    $out['error'] = 'transaksi gagal: ' . $e->getMessage();
    echo json_encode($out);
    exit;
}
$out['diubah'] = 2;
try {
    $cache = new Cache();
    $builder = new CacheBuilder(new MySQLWriter(), $cache);
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
    $idx = $cache->read('products/index.json');
    $newSlug = null;
    foreach (($idx['items'] ?? []) as $it) {
        if (($it['kode_barang'] ?? '') === '90919-T1004') {
            $newSlug = $it['slug'] ?? null;
            break;
        }
    }
    $out['slug_baru'] = $newSlug;
    if ($newSlug === null) {
        $out['ok'] = false;
        $out['error'] = 'slug baru tidak ditemukan di index';
    }
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
}
echo json_encode($out);

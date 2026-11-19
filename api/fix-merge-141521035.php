<?php
// Applier sekali pakai: samakan kode 1-41521-035-0 (ISUZU, Rp495.000, AS SATELIT
// ORS, Elf NMR71) + MSD-I-1-41521-035 (MITSUDA, Rp425.000, AS SATELIT/71,
// Elf NKR66) menjadi SATU kode "1-41521-035" — pin fisik yang sama, hanya
// berbeda merk. Danan 2026-11-19: "samakan kode barangnya menjadi 1-41521-035.
// hanya berbeda merk".
// - UPDATE kedua baris: kode_barang='1-41521-035', nama_produk='AS SATELIT'
//   (nama diseragamkan supaya kartu gabungan deterministik — tanpa ini nama
//   kartu jadi coin-flip antara "AS SATELIT ORS" vs "AS SATELIT/71" tergantung
//   urutan baris; nama_lain keduanya sudah identik "AS SATELIT | SATELLITE SHAFT").
// - Kedua baris tetap ada (kunci = ID), dibedakan merk -> satu kartu dengan
//   2 varian (ISUZU Rp495.000, MITSUDA Rp425.000), masing-masing bawa gambar
//   + kendaraannya sendiri. Slug baru deterministik: as-satelit-1-41521-035.
// - Redirect slug lama -> slug baru ditambahkan ke $legacyRedirects di
//   pages/produk_detail.php (push yang sama).
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
$st = $pdo->prepare('SELECT id, kode_barang, nama_produk, merk, harga_jual, nama_mobil FROM products WHERE kode_barang IN (?, ?) ORDER BY kode_barang');
$st->execute(['1-41521-035-0', 'MSD-I-1-41521-035']);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$out['guard'] = ['ditemukan' => count($rows), 'baris' => $rows];
if (count($rows) !== 2) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: kedua kode harus ada';
    echo json_encode($out);
    exit;
}
$merks = [];
foreach ($rows as $r) {
    $merks[] = $r['merk'];
}
sort($merks);
if ($merks !== ['ISUZU', 'MITSUDA']) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: merk harus ISUZU + MITSUDA, dapat: ' . implode(',', $merks);
    echo json_encode($out);
    exit;
}
$st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE kode_barang = '1-41521-035'");
$st->execute();
if ((int)$st->fetchColumn() !== 0) {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: 1-41521-035 sudah ada';
    echo json_encode($out);
    exit;
}
$out['slug_lama'] = [];
foreach ($rows as $r) {
    $out['slug_lama'][] = slugify($r['nama_produk'] . '-' . $r['kode_barang']);
}
$pdo->beginTransaction();
try {
    $st = $pdo->prepare("UPDATE products SET kode_barang = '1-41521-035', nama_produk = 'AS SATELIT' WHERE id = ?");
    foreach ($rows as $r) {
        $st->execute([$r['id']]);
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
    $out['cache_produk'] = $idx['count'] ?? null;
    $merged = null;
    foreach (($idx['items'] ?? []) as $it) {
        if (($it['kode_barang'] ?? '') === '1-41521-035') {
            $merged = $it;
            break;
        }
    }
    if ($merged === null) {
        $out['ok'] = false;
        $out['error'] = '1-41521-035 tidak ditemukan di index sesudah rebuild';
    } else {
        $out['slug_baru'] = $merged['slug'] ?? null;
        $out['varian'] = $merged['variants'] ?? null;
    }
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
}
echo json_encode($out);

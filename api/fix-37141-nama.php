<?php
// Applier sekali pakai: bersihkan "DINA SAURUS" dari nama_produk 31420-37141 (Danan 2026-11-19:
// "oke bersihkan sekalian"). Kanonis nomor ini = Dyna XZU 412/420 (bukan Dyna Saurus);
// nama_mobil sudah "Toyota Dyna" (diisi applier fix-snc-mka.php). Sesudah ini:
// nama_produk = "MASTER KOPLING ATAS CM ASSY", slug baru =
// "master-kopling-atas-cm-assy-31420-37141" (redirect ditangani di produk_detail.php).
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
$st = $pdo->prepare('SELECT id, kode_barang, nama_produk, nama_mobil FROM products WHERE kode_barang = ?');
$st->execute(['31420-37141']);
$row = $st->fetch(PDO::FETCH_ASSOC);
$out['guard'] = [
    'ada' => $row !== false,
    'nama_produk_saat_ini' => $row['nama_produk'] ?? null,
    'nama_mobil_saat_ini' => $row['nama_mobil'] ?? null,
];
if (!$row || $row['nama_produk'] !== 'MASTER KOPLING ATAS CM ASSY DINA SAURUS' || $row['nama_mobil'] !== 'Toyota Dyna') {
    $out['ok'] = false;
    $out['error'] = 'guard gagal: nama_produk/nama_mobil tidak sesuai yang diharapkan';
    echo json_encode($out);
    exit;
}
$st = $pdo->prepare('UPDATE products SET nama_produk = ? WHERE id = ?');
$st->execute(['MASTER KOPLING ATAS CM ASSY', $row['id']]);
$out['nama_produk_dibersihkan'] = $st->rowCount();
try {
    $builder = new CacheBuilder(new MySQLWriter(), new Cache());
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
}
echo json_encode($out);

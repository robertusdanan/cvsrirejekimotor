<?php
declare(strict_types=1);

/**
 * Satu-kali: hapus kata "terlengkap" dari deskripsi perusahaan.
 * - company_profile.description (id=1):
 *   "Pusat suku cadang dan onderdil mobil terlengkap di Tulungagung
 *   dengan kualitas terjamin dan pengiriman ke seluruh Indonesia."
 *   ->
 *   "Pusat suku cadang dan onderdil mobil di Tulungagung dengan
 *   kualitas terjamin dan pengiriman ke seluruh Indonesia."
 * - Lalu rebuild cache company (company/profile.json).
 * - Dijalankan sekali via POST + X-Upload-Token, lalu HAPUS file ini.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/MySQLWriter.php';
require __DIR__ . '/../src/CacheBuilder.php';

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

$writer = new MySQLWriter();
$pdo = $writer->pdo();
$row = $pdo->query('SELECT description FROM company_profile WHERE id = 1 LIMIT 1')->fetch();
$old = is_array($row) ? (string) ($row['description'] ?? '') : '';
$needle = 'mobil terlengkap di Tulungagung';
if (strpos($old, $needle) === false) {
    fail(409, 'deskripsi saat ini tidak mengandung teks yang diharapkan; tidak diubah. current=' . mb_substr($old, 0, 120));
}
$new = str_replace($needle, 'mobil di Tulungagung', $old);
$st = $pdo->prepare('UPDATE company_profile SET description = ? WHERE id = 1');
$st->execute([$new]);
if ($st->rowCount() === 0) {
    fail(500, 'update tidak mengubah baris apa pun');
}
try {
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildCompany();
} catch (Throwable $e) {
    fail(500, 'deskripsi terupdate tapi rebuild company gagal: ' . $e->getMessage());
}
echo json_encode(['ok' => true, 'old' => $old, 'new' => $new]);

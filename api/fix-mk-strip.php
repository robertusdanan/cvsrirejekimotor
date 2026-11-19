<?php
declare(strict_types=1);

/**
 * ONE-TIME FIX (2026-11-19, perintah Danan 14:32 — "boleh dicopot jika
 * memang MK itu sudah kamu pastikan adalah dari merk... teliti dulu"):
 * Copot prefiks "MK" (merk MK Kashiyama) dari 28 kode barang yang sudah
 * DIVERIFIKASI KUAT (audit-work/mk-verification.json: 27 kuat + MKK1264
 * yang sudah diputuskan Danan 14:26). Pola: MKKxxxx -> Kxxxx, MKDxxxx ->
 * Dxxxx, kecuali MKKD4029M -> D4029M (copot "MKK", bukan "MK").
 *
 * YANG TIDAK ikut di sini (jangan dicopot): MKK-BSYK1267 + MKD9090
 * (belum terverifikasi), MKD206901 + MKD225301 + MKD6160H02
 * (tidak langsung — tahan sampai ada bukti tambahan).
 *
 * Hanya mengubah kode_barang (UNIQUE) — nama/harga/merk/kendaraan/gambar
 * TIDAK berubah. Slug ikut berubah (slug = nama + kode), jadi URL lama
 * di-redirect 301 di produk_detail.php (28 entri, push yang sama).
 * Gambar R2 TIDAK tersentuh (kunci R2 = UUID produk, bukan kode).
 *
 * Auth: header X-Upload-Token == IMAGE_UPLOAD_TOKEN (sama seperti
 * api/image-upload.php). POST only. Fail-closed 401 bila token kosong.
 *
 * SETELAH dijalankan + terverifikasi, HAPUS file ini dari repo + public_html
 * (seperti api/migrate.php dulu) — jangan dibiarkan hidup di server.
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
    fail(405, 'method not allowed (pakai POST)');
}

$expected = (string) (config('IMAGE_UPLOAD_TOKEN') ?? '');
if ($expected === '') {
    fail(401, 'unauthorized (token belum dikonfigurasi)');
}
$provided = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals($expected, (string) $provided)) {
    fail(401, 'unauthorized');
}

// [kode_lama, kode_baru] — 27 terverifikasi kuat + MKK1264 (Danan 14:26).
$renames = [
    ['MKK0021', 'K0021'],
    ['MKK2367', 'K2367'],
    ['MKK2411', 'K2411'],
    ['MKK2427', 'K2427'],
    ['MKK4434', 'K4434'],
    ['MKK4452', 'K4452'],
    ['MKK5547', 'K5547'],
    ['MKK6618A', 'K6618A'],
    ['MKK6664', 'K6664'],
    ['MKK6741', 'K6741'],
    ['MKK9003', 'K9003'],
    ['MKK9910', 'K9910'],
    ['MKKD4029M', 'D4029M'],
    ['MKD0044', 'D0044'],
    ['MKD0051M', 'D0051M'],
    ['MKD1250M', 'D1250M'],
    ['MKD1288M', 'D1288M'],
    ['MKD225201', 'D225201'],
    ['MKD2260M', 'D2260M'],
    ['MKD2355', 'D2355'],
    ['MKD5070M', 'D5070M'],
    ['MKD5073M', 'D5073M'],
    ['MKD5091M', 'D5091M'],
    ['MKD5168M', 'D5168M'],
    ['MKD6014', 'D6014'],
    ['MKD6168', 'D6168'],
    ['MKD9019M', 'D9019M'],
    ['MKK1264', 'K1264'],
];

$writer = new MySQLWriter();
$pdo = MySQLWriter::pdo();
$out = [];

// 0. Guard: tiap kode lama HARUS ada; tiap kode baru TIDAK BOLEH ada
//    (kode_barang UNIQUE — tabrakan akan menggagalkan seluruh transaksi).
foreach ($renames as [$old, $new]) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE kode_barang = ?');
    $st->execute([$old]);
    if ((int) $st->fetchColumn() === 0) {
        fail(500, "guard gagal: kode lama $old tidak ditemukan di DB");
    }
    $st->execute([$new]);
    if ((int) $st->fetchColumn() > 0) {
        fail(500, "guard gagal: kode baru $new SUDAH ADA di DB (tabrakan)");
    }
}
$out['guard'] = 'ok (28 kode lama ada, 28 kode baru belum ada)';

// 1. Rename semua dalam SATU transaksi.
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('UPDATE products SET kode_barang = ? WHERE kode_barang = ?');
    $done = 0;
    foreach ($renames as [$old, $new]) {
        $st->execute([$new, $old]);
        $done += $st->rowCount();
    }
    $pdo->commit();
    $out['rename'] = "$done baris diubah";
    if ($done !== count($renames)) {
        // Seharusnya tidak terjadi (guard sudah lolos) — laporkan bila iya.
        $out['rename_warning'] = "diharapkan " . count($renames) . " baris, diubah $done";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('fix-mk-strip gagal: ' . $e->getMessage());
    fail(500, 'gagal: ' . $e->getMessage());
}

// 2. Rebuild cache (slug baru + file produk lama dihapus sebagai yatim).
try {
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    error_log('fix-mk-strip rebuild gagal: ' . $e->getMessage());
    fail(500, 'DB ok tapi rebuild cache gagal: ' . $e->getMessage());
}

echo json_encode(['ok' => true] + $out);

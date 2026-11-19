<?php
declare(strict_types=1);

/**
 * ONE-TIME FIX (2026-11-19 — tiga keputusan Danan yang sudah disetujui):
 * 1. Rename 47201-OK240 -> 47201-0K240 (Danan 14:53: "OK240" nol
 *    exact-match; semua sumber pakai "0K240", ejaan kanonis Toyota.
 *    OK010/OK020 TETAP — SANYCO memakai ejaan "OK" untuk keduanya).
 * 2. Koreksi label kendaraan 074-1/075-1 (Danan 15:06 "setuju ganti"):
 *    8-97100-074-1: "Isuzu Elf NKR66" -> "Isuzu Elf 6.5T/7.9T"
 *    (Pinsin: ELF 6.5T/7.9T); 8-97100-075-1: "Isuzu Elf NHR55" ->
 *    "Isuzu Elf NKR66" (Pinsin: NKR66; Shopee 075-1 juga ELF 6.5T/7.9T).
 * 3. Hapus B-TBM-S01 (Danan 15:06 "setuju ganti"): B-TBM-S01 ("5K",
 *    BIRKENS Rp475.000) dan B-TBM-S15 ("GRAND 5K", BIRKENS Rp500.000)
 *    sama-sama mengarah ke OE 47201-38010 = SATU barang. Per aturan
 *    duplikat (sama part + sama merk, ambil harga tertinggi, hapus
 *    duplikat): simpan B-TBM-S15, hapus B-TBM-S01. Keduanya skip
 *    batch 27 (nol exact-match, belum bergambar) — penghapusan bersih.
 *
 * Hanya mengubah kode_barang/nama_mobil + 1 baris dihapus. Slug OK240
 * ikut berubah, jadi URL lama di-redirect 301 di produk_detail.php
 * (1 entri, push yang sama). Gambar R2 TIDAK tersentuh (kunci R2 =
 * UUID produk, bukan kode; B-TBM-S01 tidak punya gambar).
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

$writer = new MySQLWriter();
$pdo = MySQLWriter::pdo();
$out = [];

// 0. Guard.
$st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE kode_barang = ?');
$st->execute(['47201-OK240']);
if ((int) $st->fetchColumn() === 0) {
    fail(500, 'guard gagal: 47201-OK240 tidak ditemukan di DB');
}
$st->execute(['47201-0K240']);
if ((int) $st->fetchColumn() > 0) {
    fail(500, 'guard gagal: 47201-0K240 SUDAH ADA di DB (tabrakan)');
}
$st = $pdo->prepare('SELECT nama_mobil FROM products WHERE kode_barang = ?');
$st->execute(['8-97100-074-1']);
$r074 = $st->fetchColumn();
if ($r074 === false) {
    fail(500, 'guard gagal: 8-97100-074-1 tidak ditemukan di DB');
}
if ($r074 !== 'Isuzu Elf NKR66') {
    fail(500, "guard gagal: 8-97100-074-1 nama_mobil = '$r074' (diharapkan 'Isuzu Elf NKR66')");
}
$st->execute(['8-97100-075-1']);
$r075 = $st->fetchColumn();
if ($r075 === false) {
    fail(500, 'guard gagal: 8-97100-075-1 tidak ditemukan di DB');
}
if ($r075 !== 'Isuzu Elf NHR55') {
    fail(500, "guard gagal: 8-97100-075-1 nama_mobil = '$r075' (diharapkan 'Isuzu Elf NHR55')");
}
$st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE kode_barang = ?');
$st->execute(['B-TBM-S01']);
if ((int) $st->fetchColumn() === 0) {
    fail(500, 'guard gagal: B-TBM-S01 tidak ditemukan di DB');
}
$st->execute(['B-TBM-S15']);
if ((int) $st->fetchColumn() === 0) {
    fail(500, 'guard gagal: B-TBM-S15 (penyimpan) tidak ditemukan di DB');
}
$st = $pdo->prepare(
    'SELECT COUNT(*) FROM product_images WHERE product_id IN ' .
    '(SELECT id FROM products WHERE kode_barang = ?)'
);
$st->execute(['B-TBM-S01']);
$imgS01 = (int) $st->fetchColumn();
if ($imgS01 > 0) {
    fail(500, "guard gagal: B-TBM-S01 punya $imgS01 gambar (diharapkan 0)");
}
$out['guard'] = 'ok (OK240 ada, 0K240 belum ada, 074-1/075-1 label sesuai, B-TBM-S01 ada 0 gambar, B-TBM-S15 ada)';

// 1. Semua perubahan dalam SATU transaksi.
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('UPDATE products SET kode_barang = ? WHERE kode_barang = ?');
    $st->execute(['47201-0K240', '47201-OK240']);
    $out['rename_ok240'] = $st->rowCount() . ' baris';
    $st = $pdo->prepare('UPDATE products SET nama_mobil = ? WHERE kode_barang = ?');
    $st->execute(['Isuzu Elf 6.5T/7.9T', '8-97100-074-1']);
    $out['kendaraan_074_1'] = $st->rowCount() . ' baris';
    $st->execute(['Isuzu Elf NKR66', '8-97100-075-1']);
    $out['kendaraan_075_1'] = $st->rowCount() . ' baris';
    $st = $pdo->prepare('DELETE FROM products WHERE kode_barang = ?');
    $st->execute(['B-TBM-S01']);
    $out['hapus_b_tbm_s01'] = $st->rowCount() . ' baris';
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('fix-ok240-074075-btbm gagal: ' . $e->getMessage());
    fail(500, 'gagal: ' . $e->getMessage());
}

// 2. Rebuild cache (slug OK240 baru + file produk B-TBM-S01 dihapus sebagai yatim).
try {
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    error_log('fix-ok240-074075-btbm rebuild gagal: ' . $e->getMessage());
    fail(500, 'DB ok tapi rebuild cache gagal: ' . $e->getMessage());
}

echo json_encode(['ok' => true] + $out);

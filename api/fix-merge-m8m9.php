<?php
declare(strict_types=1);

/**
 * ONE-TIME FIX (2026-11-19, perintah Danan — rencana merge disetujui "setuju"):
 *  - M8: hapus 8-94226-557 (duplikat TE8-94226-557-2; nomor dasar sama
 *    8-94226-557, sufiks -2 = revisi; merk sama Terada; harga sama Rp55.000)
 *    + hapus gambar R2-nya (4 varian) + redirect lama -> TE8-94226-557-2
 *  - M9: hapus TEMB-109936 (duplikat TE-MB109936; nomor dasar sama MB109936;
 *    merk TEMB kosong; harga sama Rp65.000)
 *    + hapus gambar R2-nya (4 varian) + redirect lama -> halaman MB109936
 *    + TEMB-109936 dikeluarkan dari grup merge MB109936 di CacheBuilder
 *      (grup jadi 2 anggota; dilakukan di push yang sama, bukan di sini)
 *  - rebuild cache (produk 997 -> 995; merge_reduction 10 -> 9)
 *
 * M1-M6 TIDAK dieksekusi di sini (syarat ukur stok fisik dulu).
 * Grup merge cache G1 (04495-0K140) TIDAK di sini (hanya edit CacheBuilder).
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
require __DIR__ . '/../src/ImageHandler.php';

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

// Pasangan hapus/simpan: [kode_hapus, kode_simpan, harga_harus_sama]
$pairs = [
    ['8-94226-557', 'TE8-94226-557-2', 55000],
    ['TEMB-109936', 'TE-MB109936', 65000],
];

// 0. Guard: kedua produk hapus + kedua produk simpan harus ada dengan harga yang diharapkan.
foreach ($pairs as [$del, $keep, $harga]) {
    foreach ([$del, $keep] as $kode) {
        $st = $pdo->prepare('SELECT kode_barang, merk, harga_jual FROM products WHERE kode_barang = ?');
        $st->execute([$kode]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            fail(500, "guard gagal: produk $kode tidak ditemukan di DB");
        }
        if ((int) $row['harga_jual'] !== $harga) {
            fail(500, "guard gagal: harga $kode = Rp" . $row['harga_jual'] . ", diharapkan Rp$harga");
        }
    }
}
$out['guard'] = 'ok (4 produk ada, harga sesuai)';

// 1. Baca path gambar kedua produk hapus (sebelum baris DB dihapus).
$imgPaths = [];
foreach ($pairs as [$del]) {
    $st = $pdo->prepare(
        'SELECT path FROM product_images WHERE product_id IN ' .
        '(SELECT id FROM products WHERE kode_barang = ?)'
    );
    $st->execute([$del]);
    $paths = $st->fetchAll(PDO::FETCH_COLUMN);
    $imgPaths[$del] = $paths;
    $out['gambar_' . $del] = count($paths);
}

// 2. Hapus baris DB (product_images + products) dalam SATU transaksi.
try {
    $pdo->beginTransaction();
    foreach ($pairs as [$del]) {
        $st = $pdo->prepare(
            'DELETE FROM product_images WHERE product_id IN ' .
            '(SELECT id FROM products WHERE kode_barang = ?)'
        );
        $st->execute([$del]);
        $st = $pdo->prepare('DELETE FROM products WHERE kode_barang = ?');
        $st->execute([$del]);
        $out['hapus_' . $del] = 'dihapus (' . $st->rowCount() . ' baris)';
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('fix-merge-m8m9 gagal: ' . $e->getMessage());
    fail(500, 'gagal: ' . $e->getMessage());
}

// 3. Hapus varian R2 untuk gambar yang tadi dibaca (semua 4 varian per gambar).
//    R2 tidak bisa rollback; kegagalan di sini hanya warning (file jadi yatim,
//    tidak memengaruhi DB/cache) — laporkan di output.
$imgHandler = new ImageHandler();
$r2ok = 0;
$r2warn = [];
foreach ($imgPaths as $del => $paths) {
    foreach ($paths as $path) {
        try {
            $imgHandler->deleteR2File((string) $path);
            $r2ok++;
        } catch (Throwable $e) {
            $r2warn[] = $del . ': ' . $e->getMessage();
            error_log('fix-merge-m8m9 R2 warning: ' . $e->getMessage());
        }
    }
}
$out['r2_dihapus'] = $r2ok;
if ($r2warn !== []) {
    $out['r2_warning'] = $r2warn;
}

// 4. Rebuild cache (produk 997 -> 995; merge_reduction 10 -> 9).
try {
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    error_log('fix-merge-m8m9 rebuild gagal: ' . $e->getMessage());
    fail(500, 'DB ok tapi rebuild cache gagal: ' . $e->getMessage());
}

echo json_encode(['ok' => true] + $out);

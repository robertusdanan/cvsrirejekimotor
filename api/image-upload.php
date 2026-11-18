<?php
declare(strict_types=1);

/**
 * Upload gambar produk langsung ke R2 — untuk script otomatis (Lyvi),
 * TANPA login admin (admin memakai Turnstile Cloudflare yang tidak bisa
 * dilalui bot).
 *
 * Auth: header X-Upload-Token harus sama dengan IMAGE_UPLOAD_TOKEN di
 * ~/private/.env. Bila token belum dikonfigurasi -> 401 (fail-closed).
 * Token ini BUKAN kredensial R2 — kredensial R2 tetap dibaca server
 * dari .env dan tidak pernah keluar dari server.
 *
 * POST multipart (upload satu gambar):
 *   image        file gambar (jpg/png/webp, maks 8 MB)
 *   kode_barang  Kode Barang produk (wajib; "_" boleh untuk "/")
 *   replace      1 = timpa gambar lama (hapus dari R2), kosong = tolak bila
 *                  produk sudah punya gambar
 *   append       1 = tambah sebagai gambar ke-2 dst (bila sudah ada gambar)
 *
 * POST (rebuild cache saja, tanpa gambar):
 *   action=rebuild
 *
 * Alur upload: cari produk -> upload via ImageHandler (4 varian WebP ke
 * R2) -> sync product_images di Supabase -> rebuild cache produk
 * (terverifikasi) -> JSON {ok, kode_barang, product_id, path, url}.
 * Bila rebuild cache gagal, upload tetap dilaporkan berhasil TAPI dengan
 * cache_warning yang jelas, supaya tidak ada gambar yang "hilang" diam-diam.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/SupabaseWriter.php';
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

// --- Auth: token rahasia, fail-closed bila belum dikonfigurasi ---
$expected = (string) (config('IMAGE_UPLOAD_TOKEN') ?? '');
if ($expected === '') {
    error_log('image-upload: IMAGE_UPLOAD_TOKEN belum dikonfigurasi di server.');
    fail(401, 'unauthorized (token belum dikonfigurasi)');
}
$provided = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals($expected, (string) $provided)) {
    error_log('image-upload: token tidak valid dari ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    fail(401, 'unauthorized');
}

$writer = new SupabaseWriter(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));

// --- Aksi rebuild saja (dipakai script batch di akhir) ---
if (($_POST['action'] ?? '') === 'rebuild') {
    try {
        @set_time_limit(120);
        $builder = new CacheBuilder($writer, new Cache());
        $builder->rebuildProducts();
        echo json_encode(['ok' => true, 'action' => 'rebuild']);
    } catch (Throwable $e) {
        error_log('image-upload rebuild gagal: ' . $e->getMessage());
        fail(500, 'rebuild cache gagal: ' . $e->getMessage());
    }
    exit;
}

// --- Upload satu gambar ---
$file = $_FILES['image'] ?? null;
if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail(400, 'file image belum dipilih atau gagal di-upload');
}
if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
    fail(400, 'ukuran gambar maksimal 8 MB');
}

$kode = trim((string) ($_POST['kode_barang'] ?? ''));
if ($kode === '') {
    fail(400, 'kode_barang wajib diisi');
}
$replace = !empty($_POST['replace']);
$append = !empty($_POST['append']);

set_time_limit(300);

// Cari produk (coba kode persis, lalu "_" -> "/" seperti import admin).
$product = $writer->getProductByKode($kode);
if ($product === null && str_contains($kode, '_')) {
    $product = $writer->getProductByKode(str_replace('_', '/', $kode));
}
if ($product === null) {
    fail(404, 'produk tidak ditemukan: ' . $kode);
}
$productId = (string) ($product['id'] ?? '');
$kodeAsli = (string) ($product['kode_barang'] ?? $kode);
$existing = is_array($product['product_images'] ?? null) ? $product['product_images'] : [];

if ($existing !== [] && !$replace && !$append) {
    fail(409, 'produk sudah punya gambar (pakai replace=1 untuk menimpa, append=1 untuk menambah)');
}

// Upload ke R2 (4 varian WebP; path yang disimpan = varian medium).
$imgHandler = new ImageHandler();
try {
    $res = $imgHandler->uploadAndConvertWebP($file, 'products/' . $productId);
} catch (Throwable $e) {
    error_log('image-upload R2 gagal untuk ' . $kodeAsli . ': ' . $e->getMessage());
    fail(500, 'upload ke R2 gagal: ' . $e->getMessage());
}
if (empty($res['path'])) {
    fail(500, 'upload ke R2 gagal: tidak ada path gambar yang dihasilkan');
}
$newPath = (string) $res['path'];

// Susun daftar gambar baru untuk disinkronkan.
if ($replace && $existing !== []) {
    foreach ($existing as $old) {
        if (!empty($old['path'])) {
            try {
                $imgHandler->deleteR2File((string) $old['path']);
            } catch (Throwable $e) {
                // Hapus R2 best-effort; jangan gagalkan upload.
            }
        }
    }
    $images = [['path' => $newPath, 'primary' => true]];
} elseif ($append && $existing !== []) {
    $images = [];
    foreach ($existing as $old) {
        if (!empty($old['path'])) {
            $images[] = ['path' => (string) $old['path'], 'primary' => (bool) ($old['is_primary'] ?? false)];
        }
    }
    $images[] = ['path' => $newPath, 'primary' => false];
} else {
    $images = [['path' => $newPath, 'primary' => true]];
}

try {
    $writer->syncProductImages($productId, $images);
} catch (Throwable $e) {
    error_log('image-upload sync Supabase gagal untuk ' . $kodeAsli . ': ' . $e->getMessage());
    fail(500, 'gagal menyimpan ke Supabase: ' . $e->getMessage());
}

// Rebuild cache produk (terverifikasi) supaya gambar langsung tampil.
$cacheWarning = null;
try {
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildProducts();
} catch (Throwable $e) {
    $cacheWarning = 'Gambar tersimpan di R2 + Supabase, tetapi rebuild cache gagal: ' . $e->getMessage();
    error_log('image-upload: ' . $cacheWarning);
}

echo json_encode([
    'ok' => true,
    'kode_barang' => $kodeAsli,
    'product_id' => $productId,
    'path' => $newPath,
    'url' => $res['url'] ?? '',
    'cache_warning' => $cacheWarning,
]);

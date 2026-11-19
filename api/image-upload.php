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
 *   merk         (opsional) bila kode punya >1 varian, lampirkan hanya ke
 *                  varian bermerk ini; bila tidak disebut, gambar yang
 *                  sama dilampirkan ke SEMUA varian kode itu.
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
require __DIR__ . '/../src/MySQLWriter.php';
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

$writer = new MySQLWriter();

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
// Kode boleh sama untuk beberapa baris (kunci = ID, Danan 2026-11-19):
// - bila parameter POST 'merk' disebut -> lampirkan HANYA ke varian
//   bermerk itu (cocok persis, case-insensitive);
// - bila tidak disebut dan kodenya punya >1 baris -> lampirkan gambar
//   yang SAMA ke SEMUA varian (gambarnya gambar part yang sama).
$merk = trim((string) ($_POST['merk'] ?? ''));
$candidates = $writer->getProductsByKode($kode);
if ($candidates === [] && str_contains($kode, '_')) {
    $candidates = $writer->getProductsByKode(str_replace('_', '/', $kode));
}
if ($candidates === []) {
    fail(404, 'produk tidak ditemukan: ' . $kode);
}
$targets = [];
if ($merk !== '') {
    foreach ($candidates as $c) {
        if (strcasecmp(trim((string) ($c['merk'] ?? '')), $merk) === 0) {
            $targets[] = $c;
            break;
        }
    }
    if ($targets === []) {
        $merks = [];
        foreach ($candidates as $c) {
            $merks[] = ($c['merk'] ?? '(tanpa merk)');
        }
        fail(409, 'kode ' . $kode . ' tidak punya varian bermerk "' . $merk . '" — varian yang ada: ' . implode(', ', $merks));
    }
} else {
    $targets = $candidates;
}
$product = $targets[0];
$productId = (string) ($product['id'] ?? '');
$kodeAsli = (string) ($product['kode_barang'] ?? $kode);
$existing = [];
foreach ($targets as $t) {
    foreach (is_array($t['product_images'] ?? null) ? $t['product_images'] : [] as $im) {
        if (!empty($im['path'])) {
            $existing[] = $im;
        }
    }
}

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

// Susun daftar gambar baru untuk disinkronkan (per target; bila kode
// punya >1 varian, gambar yang sama dilampirkan ke semua varian).
if ($replace && $existing !== []) {
    $seenDel = [];
    foreach ($existing as $old) {
        $p = (string) ($old['path'] ?? '');
        if ($p === '' || isset($seenDel[$p])) {
            continue;
        }
        $seenDel[$p] = true;
        try {
            $imgHandler->deleteR2File($p);
        } catch (Throwable $e) {
            // Hapus R2 best-effort; jangan gagalkan upload.
        }
    }
}
$productIds = [];
foreach ($targets as $t) {
    $tid = (string) ($t['id'] ?? '');
    if ($tid === '') {
        continue;
    }
    $productIds[] = $tid;
    $tExisting = is_array($t['product_images'] ?? null) ? $t['product_images'] : [];
    if ($replace && $tExisting !== []) {
        $images = [['path' => $newPath, 'primary' => true]];
    } elseif ($append && $tExisting !== []) {
        $images = [];
        foreach ($tExisting as $old) {
            if (!empty($old['path'])) {
                $images[] = ['path' => (string) $old['path'], 'primary' => (bool) ($old['is_primary'] ?? false)];
            }
        }
        $images[] = ['path' => $newPath, 'primary' => false];
    } else {
        $images = [['path' => $newPath, 'primary' => true]];
    }
    try {
        $writer->syncProductImages($tid, $images);
    } catch (Throwable $e) {
        error_log('image-upload sync Supabase gagal untuk ' . $kodeAsli . ' (' . $tid . '): ' . $e->getMessage());
        fail(500, 'gagal menyimpan ke Supabase: ' . $e->getMessage());
    }
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
    'product_ids' => $productIds,
    'path' => $newPath,
    'url' => $res['url'] ?? '',
    'cache_warning' => $cacheWarning,
]);

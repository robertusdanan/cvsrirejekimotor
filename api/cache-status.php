<?php
declare(strict_types=1);

/**
 * Status cache produk — untuk Lyvi mengecek kesegaran cache LANGSUNG,
 * tanpa memindah folder cache ke public_html (cache menyimpan angka stok
 * yang tidak boleh tampil ke publik, jadi cache tetap di ~/private/).
 *
 * Auth: header X-Upload-Token harus sama dengan IMAGE_UPLOAD_TOKEN di
 * ~/private/.env (token yang sama dengan endpoint upload gambar).
 * Bila token belum dikonfigurasi -> 401 (fail-closed).
 *
 * GET (read-only, tidak mengubah apa pun):
 *   Mengembalikan jumlah produk + jumlah bergambar di cache vs di Supabase
 *   (live), waktu rebuild terakhir, dan apakah cache segar. TIDAK
 *   mengembalikan angka stok, harga beli, atau data sensitif lain.
 *
 * Contoh:
 *   curl -s -H "X-Upload-Token: <token>" \
 *     "https://cvsrirejekimotor.com/api/cache-status.php"
 *   -> {"ok":true,"fresh":true,
 *       "cache":{"products":1000,"imaged":63,"version":42,"updated_at":"..."},
 *       "live":{"products":1000,"imaged":63},"checked_at":"..."}
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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    fail(405, 'method not allowed (pakai GET)');
}

// --- Auth: token rahasia, fail-closed bila belum dikonfigurasi ---
$expected = (string) (config('IMAGE_UPLOAD_TOKEN') ?? '');
if ($expected === '') {
    error_log('cache-status: IMAGE_UPLOAD_TOKEN belum dikonfigurasi di server.');
    fail(401, 'unauthorized (token belum dikonfigurasi)');
}
$provided = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals($expected, (string) $provided)) {
    error_log('cache-status: token tidak valid dari ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    fail(401, 'unauthorized');
}

$cache = new Cache();

// --- Baca cache: products/index.json + _meta/products.json ---
$indexData = $cache->read('products/index.json');
$indexItems = is_array($indexData['items'] ?? null) ? $indexData['items'] : [];
$cacheProducts = count($indexItems);
$cacheImaged = 0;
foreach ($indexItems as $it) {
    if (!empty($it['primary_image'])) {
        $cacheImaged++;
    }
}
$meta = $cache->read('_meta/products.json') ?? [];
$cacheVersion = (int) ($meta['version'] ?? 0);
$cacheUpdatedAt = $meta['updated_at'] ?? null;

// --- Produk gabungan (varian): 19 baris Supabase -> 9 kartu kanonis ---
// Cache menyimpan produk KANONIS (990), sedangkan Supabase menyimpan
// 1000 baris. Selisihnya = jumlah redirect (slug lama -> slug kanonis).
// Untuk gambar: bila 2 varian dalam 1 kartu sama-sama bergambar (mis.
// DAIMARU + TOYOTA di 04465-0K100), live menghitung 2 baris bergambar
// tapi cache menghitung 1 kartu bergambar. Reduksi = (varian bergambar - 1)
// per kartu gabungan.
$redirectData = $cache->read('products/redirects.json');
$redirectCount = is_array($redirectData['redirects'] ?? null) ? count($redirectData['redirects']) : 0;
$imagedReduction = 0;
foreach ($indexItems as $it) {
    if (empty($it['variants'])) {
        continue;
    }
    $n = 0;
    foreach ($it['variants'] as $v) {
        if (!empty($v['primary_image'])) {
            $n++;
        }
    }
    if ($n > 1) {
        $imagedReduction += ($n - 1);
    }
}

// --- Baca live: Supabase products (+ product_images yang menempel) ---
$writer = new MySQLWriter();
try {
    $rows = $writer->getProducts();
} catch (Throwable $e) {
    error_log('cache-status getProducts gagal: ' . $e->getMessage());
    fail(500, 'gagal membaca Supabase: ' . $e->getMessage());
}
if (!is_array($rows)) {
    fail(500, 'gagal membaca Supabase (respons bukan array)');
}
$liveProducts = count($rows);
$liveImaged = 0;
foreach ($rows as $r) {
    $imgs = is_array($r['product_images'] ?? null) ? $r['product_images'] : [];
    $has = false;
    foreach ($imgs as $im) {
        if (!empty($im['path'])) {
            $has = true;
            break;
        }
    }
    if ($has) {
        $liveImaged++;
    }
}

// Kartu yang diharapkan = produk live dikurangi produk yang digabung.
// JANGAN pakai jumlah redirect: bila kode kanonis adalah bentuk OEM bersih
// yang bukan kode anggota (mis. '8-94408-840-3'), semua anggota punya
// redirect sehingga redirect > penggabungan (11 vs 10) dan fresh jadi salah.
$mergeReduction = CacheBuilder::mergeReduction();
$expectedProducts = $liveProducts - $mergeReduction;
$expectedImaged = $liveImaged - $imagedReduction;
$fresh = ($cacheProducts === $expectedProducts) && ($cacheImaged === $expectedImaged);

echo json_encode([
    'ok' => true,
    'fresh' => $fresh,
    'cache' => [
        'products' => $cacheProducts,
        'imaged' => $cacheImaged,
        'version' => $cacheVersion,
        'updated_at' => $cacheUpdatedAt,
    ],
    'live' => [
        'products' => $liveProducts,
        'imaged' => $liveImaged,
    ],
    'merged' => [
        'redirects' => $redirectCount,
        'merge_reduction' => $mergeReduction,
        'expected_products' => $expectedProducts,
        'expected_imaged' => $expectedImaged,
    ],
    'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
]);

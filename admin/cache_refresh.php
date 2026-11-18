<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$resource = $_POST['resource'] ?? 'all';
$builder = cacheBuilder();

$ok = true;
try {
    // Rebuild 1000 produk + tulis ~1000 file: beri waktu lebih dari batas
    // default web supaya tidak mati di tengah jalan.
    @set_time_limit(120);
    @ignore_user_abort(true);
    switch ($resource) {
        case 'products':
            $builder->rebuildProducts();
            break;
        case 'company':
            $builder->rebuildCompany();
            break;
        case 'marketplaces':
            $builder->rebuildMarketplaces();
            break;
        default:
            $builder->rebuildAll();
            break;
    }
    Auth::audit('cache_refresh', $resource);
} catch (Throwable $e) {
    $ok = false;
    error_log('Cache refresh failed: ' . $e->getMessage());
}

// Beri tahu hasilnya di dashboard (dulu: redirect diam-diam, admin tidak
// tahu refresh berhasil atau gagal).
header('Location: /admin/index.php?cache=' . ($ok ? 'ok' : 'fail'));

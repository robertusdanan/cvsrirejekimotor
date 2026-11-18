<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$resource = $_POST['resource'] ?? 'all';
$builder = cacheBuilder();

try {
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
    error_log('Cache refresh failed: ' . $e->getMessage());
}

header('Location: /admin/index.php');

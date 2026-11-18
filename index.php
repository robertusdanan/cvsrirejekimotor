<?php
declare(strict_types=1);

// Handle static files and directory index files directly when using PHP built-in web server
if (php_sapi_name() === 'cli-server') {
    $urlPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($urlPath !== '/' && $urlPath !== '/index.php') {
        $filePath = __DIR__ . $urlPath;
        if (is_file($filePath)) {
            return false;
        }
        if (is_dir($filePath) && is_file(rtrim($filePath, '/') . '/index.php')) {
            return false;
        }
    }
}

try {
    require __DIR__ . '/bootstrap.php';
} catch (Throwable $e) {
    // Bootstrap itself failed (e.g. broken/incompatible code in ../private).
    // Do NOT try to render pages/error.php here: it needs templates/header.php,
    // which calls cache() and other helpers that bootstrap.php never finished
    // defining. Rendering it anyway causes a second, uncaught fatal error and
    // the raw generic "HTTP ERROR 500" page instead of a friendly message.
    error_log('Bootstrap failure: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Terjadi Kendala | CV Sri Rejeki Motor</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#f7f7f7;color:#222;'
        . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px;box-sizing:border-box;text-align:center}'
        . '.box{max-width:480px}h1{font-size:1.4rem;margin-bottom:8px}'
        . 'a{display:inline-block;margin-top:16px;padding:10px 20px;background:#222;color:#fff;'
        . 'text-decoration:none;border-radius:6px}</style></head><body>'
        . '<div class="box"><h1>Situs Sedang Dalam Perbaikan</h1>'
        . '<p>Kami sedang memperbaiki gangguan teknis. Silakan coba lagi beberapa saat lagi.</p>'
        . '<a href="/">Muat Ulang</a></div></body></html>';
    exit;
}

try {
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $segments = $path === '' ? [] : explode('/', $path);

    if ($segments === []) {
        require __DIR__ . '/pages/home.php';
    } elseif ($path === 'favicon.ico') {
        $faviconPath = __DIR__ . '/favicon.ico';
        if (is_file($faviconPath)) {
            header('Content-Type: image/x-icon');
            header('Cache-Control: public, max-age=604800, immutable');
            header('Content-Length: ' . filesize($faviconPath));
            readfile($faviconPath);
        } else {
            http_response_code(404);
        }
    } elseif ($segments[0] === 'cpanel-deploy.php' || $segments[0] === 'cpanel-deploy') {
        $targetFile = __DIR__ . '/cpanel-deploy.php';
        if (is_file($targetFile)) {
            require $targetFile;
        } else {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'cpanel-deploy.php file not found on server']);
        }
    } elseif ($segments[0] === 'cpanel-setup-key.php' || $segments[0] === 'cpanel-setup-key') {
        $targetFile = __DIR__ . '/cpanel-setup-key.php';
        if (is_file($targetFile)) {
            require $targetFile;
        } else {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'cpanel-setup-key.php file not found on server']);
        }
    } elseif ($segments[0] === 'storage' && count($segments) > 1) {
        require __DIR__ . '/pages/storage_serve.php';
    } elseif ($segments[0] === 'produk' && count($segments) === 1) {
        require __DIR__ . '/pages/produk_list.php';
    } elseif ($segments[0] === 'produk' && count($segments) === 2) {
        $slug = $segments[1];
        require __DIR__ . '/pages/produk_detail.php';
    } elseif ($segments[0] === 'tentang-kami' && count($segments) === 1) {
        require __DIR__ . '/pages/tentang.php';
    } elseif ($segments[0] === 'kontak' && count($segments) === 1) {
        require __DIR__ . '/pages/kontak.php';
    } elseif ($path === 'sitemap.xml') {
        header('Content-Type: application/xml; charset=UTF-8');
        $index = cache()->read('products/index.json')['items'] ?? [];
        $base = 'https://cvsrirejekimotor.com';
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (['' => '1.0', 'produk' => '0.9', 'tentang-kami' => '0.5', 'kontak' => '0.5'] as $p => $pr) {
            echo '<url><loc>' . $base . '/' . $p . '</loc><priority>' . $pr . '</priority></url>' . "\n";
        }
        foreach ($index as $p) {
            $loc = $base . '/produk/' . htmlspecialchars($p['slug'] ?? '', ENT_XML1, 'UTF-8');
            $mod = htmlspecialchars(substr((string) ($p['updated_at'] ?? ''), 0, 10), ENT_XML1, 'UTF-8');
            echo '<url><loc>' . $loc . '</loc>';
            if ($mod !== '') { echo '<lastmod>' . $mod . '</lastmod>'; }
            echo '<priority>0.8</priority></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    } elseif ($segments[0] === 'admin') {
        $adminSubPath = implode('/', array_slice($segments, 1));
        $targetFile = __DIR__ . '/admin/' . ($adminSubPath === '' ? 'index.php' : $adminSubPath);
        if (is_file($targetFile)) {
            require $targetFile;
        } else {
            http_response_code(404);
            require __DIR__ . '/pages/not_found.php';
        }
    } else {
        http_response_code(404);
        require __DIR__ . '/pages/not_found.php';
    }
} catch (Throwable $e) {
    error_log('Public site error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (is_file(__DIR__ . '/pages/error.php')) {
        require __DIR__ . '/pages/error.php';
    } else {
        echo '<h1>500 Internal Server Error</h1><p>System error encountered.</p>';
    }
}

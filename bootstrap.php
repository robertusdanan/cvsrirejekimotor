<?php
declare(strict_types=1);

/**
 * PHP 7.4+ compatibility polyfills for functions added in PHP 8.0+.
 * Declared first so that ANY file using these (including admin pages and
 * config.php) can rely on them without crashing on shared hosting that
 * hasn't migrated to PHP 8.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $diff = strlen($haystack) - strlen($needle);
        return $diff >= 0 && substr_compare($haystack, $needle, $diff, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}

/**
 * Returns the absolute path to the `private/` directory.
 * Checks the parent directory FIRST (dirname(__DIR__) . '/private'), since
 * the intended production layout keeps private/ as a sibling of public_html
 * (outside the web root, for security). Falls back to a local
 * __DIR__ . '/private' only if no sibling copy exists, so a stray/duplicate
 * folder accidentally left inside public_html can never shadow the real
 * data and config again.
 */
if (!function_exists('privatePath')) {
    function privatePath(string $subpath = ''): string
    {
        static $baseDir = null;
        if ($baseDir === null) {
            $parentPrivate = dirname(__DIR__) . '/private';
            $localPrivate  = __DIR__ . '/private';
            if (is_dir($parentPrivate)) {
                $baseDir = $parentPrivate;
            } elseif (is_dir($localPrivate)) {
                $baseDir = $localPrivate;
            } else {
                $baseDir = $parentPrivate;
            }
        }
        return $subpath !== '' ? $baseDir . '/' . ltrim($subpath, '/') : $baseDir;
    }
}

require_once privatePath('config/config.php');
require_once privatePath('src/Cache.php');
require_once privatePath('src/R2WriteClient.php');

if (!function_exists('cache')) {
    function cache(): Cache
    {
        static $cache = null;
        if ($cache === null) {
            $cache = new Cache();
        }
        return $cache;
    }
}

if (!function_exists('render')) {
    function render(string $template, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/templates/header.php';
        require __DIR__ . "/templates/{$template}.php";
        require __DIR__ . '/templates/footer.php';
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rupiah')) {
    function rupiah(?int $value): string
    {
        if ($value === null) {
            return '-';
        }
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}

if (!function_exists('mediaUrl')) {
    function mediaUrl(?string $path): string
    {
        $path = trim((string) $path, '/');
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $r2Url = rtrim((string) config('R2_PUBLIC_URL'), '/');
        if ($r2Url !== '') {
            return "{$r2Url}/{$path}";
        }
        return "/storage/{$path}";
    }
}

if (!function_exists('heroStorageDir')) {
    function heroStorageDir(): string
    {
        return rtrim((string) config('STORAGE_PATH'), '/') . '/hero';
    }
}

if (!function_exists('heroManifestPath')) {
    function heroManifestPath(): string
    {
        return heroStorageDir() . '/manifest.json';
    }
}

if (!function_exists('heroManifest')) {
    function heroManifest(): array
    {
        $path = heroManifestPath();
        if (!is_file($path)) {
            return ['settings' => [], 'items' => []];
        }
        $raw = @file_get_contents($path);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return ['settings' => [], 'items' => []];
        }
        $data['settings'] = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $data['items'] = is_array($data['items'] ?? null) ? $data['items'] : [];
        return $data;
    }
}

if (!function_exists('heroActiveSlides')) {
    function heroActiveSlides(): array
    {
        $manifest = heroManifest();
        $items = array_values(array_filter($manifest['items'], static function ($item) {
            return !empty($item['active']) && !empty($item['file']) && !empty($item['type']);
        }));
        usort($items, static function ($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });
        return $items;
    }
}

if (!function_exists('companyInitials')) {
    function companyInitials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'TO';
        }
        $skip = ['CV', 'PT', 'UD', 'CO', 'TOKO'];
        $words = preg_split('/\s+/', $name) ?: [];
        $letters = [];
        foreach ($words as $w) {
            if (in_array(mb_strtoupper($w), $skip, true)) {
                continue;
            }
            $letters[] = mb_substr($w, 0, 1);
            if (count($letters) >= 3) {
                break;
            }
        }
        if (!$letters) {
            $letters = [mb_substr($name, 0, 1)];
        }
        return mb_strtoupper(implode('', $letters));
    }
}

if (!function_exists('assetUrl')) {
    function assetUrl(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $r2Url = config('R2_PUBLIC_URL');
        if ($r2Url !== null && $r2Url !== '') {
            return rtrim($r2Url, '/') . '/' . $cleanPath;
        }
        return '/' . $cleanPath;
    }
}

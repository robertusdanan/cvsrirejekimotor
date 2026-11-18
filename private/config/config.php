<?php
declare(strict_types=1);

/**
 * PHP 7.4+ compatibility polyfills for functions added in PHP 8.0+.
 * Declared FIRST in config.php so the env parser below can use them
 * even if the user's cPanel shared hosting is still on PHP 7.4 and
 * our top-level bootstrap.php has not yet been included.
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
 * Config loader. Reads private/.env (OUTSIDE public_html).
 * Never echo, log, or return raw env values to the client.
 */

function env_load(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $vars[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $vars;
}

$envPath = __DIR__ . '/../.env';

// Auto-create private/.env if missing on initial cPanel deployment
if (!is_file($envPath)) {
    $defaultEnvContent = "SUPABASE_URL=https://tnmatvbvbaffqbzilxoz.supabase.co\n"
        . "R2_PUBLIC_URL=https://pub-7ce4b96396f24d399964a0f6f76805a7.r2.dev\n"
        . "R2_BUCKET=sri-rejeki-motor\n"
        . "COOKIE_SECURE=true\n";
    @file_put_contents($envPath, $defaultEnvContent);
    @chmod($envPath, 0600);
}

$ENV = env_load($envPath);

function config(string $key, $default = null)
{
    global $ENV;
    $val = isset($ENV[$key]) ? $ENV[$key] : getenv($key);
    if ($val !== false && $val !== null && $val !== '') {
        return (string) $val;
    }

    // Production fallbacks to prevent 500 error if .env is missing key
    // NOTE: DEPLOY_WEBHOOK_TOKEN is intentionally NOT listed here: no fallback, ever.
    $defaults = [
        'SUPABASE_URL'         => 'https://tnmatvbvbaffqbzilxoz.supabase.co',
        'R2_PUBLIC_URL'        => 'https://pub-7ce4b96396f24d399964a0f6f76805a7.r2.dev',
        'R2_BUCKET'            => 'sri-rejeki-motor',
        'COOKIE_SECURE'        => 'true',
    ];

    if (isset($defaults[$key])) {
        return $defaults[$key];
    }

    // Auto-resolve storage/cache/sqlite paths if not explicitly set
    if ($key === 'SQLITE_PATH') {
        $dir = realpath(__DIR__ . '/../data');
        if ($dir === false) {
            $dir = __DIR__ . '/../data';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $dir . '/website.sqlite';
    }
    if ($key === 'CACHE_PATH') {
        $dir = realpath(__DIR__ . '/../cache');
        if ($dir === false) {
            $dir = __DIR__ . '/../cache';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $dir;
    }
    if ($key === 'STORAGE_PATH') {
        $dir = realpath(__DIR__ . '/../storage');
        if ($dir === false) {
            $dir = __DIR__ . '/../storage';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $dir;
    }
    if ($key === 'DATA_PATH') {
        $dir = realpath(__DIR__ . '/../data');
        if ($dir === false) {
            $dir = __DIR__ . '/../data';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $dir;
    }

    return $default;
}

// Auto-create required cache subdirectories
$cacheDir = config('CACHE_PATH');
if ($cacheDir) {
    foreach (['products', 'homepage', 'company', 'marketplaces', 'gudang', 'brands', 'vehicles', 'categories'] as $sub) {
        $p = $cacheDir . '/' . $sub;
        if (!is_dir($p)) {
            @mkdir($p, 0755, true);
        }
    }
}

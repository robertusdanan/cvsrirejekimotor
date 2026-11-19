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
if (!function_exists('array_is_list')) {
    // array_is_list() baru ada di PHP 8.1; webhook deploy jalan di PHP 7.4.
    // Tanpa ini, rebuild cache fatal ("Call to undefined function
    // array_is_list()") begitu ada tabel registry yang berisi baris —
    // terbukti di deploy e560303 (16:45 WIB): git sync OK, cache gagal.
    function array_is_list(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Config loader. Secrets (.env) dan runtime state (cache, data, sqlite)
 * tinggal di direktori `private/` SEJAJAR public_html — DI LUAR web root.
 * File ini (kode) tinggal di repo, tapi .env TIDAK PERNAH dibaca dari
 * dalam public_html: privateDir() selalu mengutamakan sibling private/.
 *
 * Layout yang didukung:
 *   /home/USER/private/.env        <- produksi (cPanel), prioritas 1
 *   <repo>/private/.env            <- dev lokal / staging, prioritas 2
 *
 * Never echo, log, or return raw env values to the client.
 */

if (!function_exists('privateDir')) {
    function privateDir(): string
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        // Prioritas 1: sibling private/ di luar public_html.
        // config.php ada di <root>/config/, jadi home = dirname(__DIR__, 2).
        $sibling = dirname(__DIR__, 2) . '/private';
        if (is_file($sibling . '/.env')) {
            $dir = $sibling;
            return $dir;
        }
        // Prioritas 2: private/ di dalam repo (dev lokal / staging).
        $local = __DIR__ . '/../private';
        if (is_file($local . '/.env')) {
            $dir = $local;
            return $dir;
        }
        // Tidak ada .env di mana pun: pakai sibling bila foldernya ada
        // (auto-create di bawah akan mengisinya dengan default publik),
        // kalau tidak, pakai private/ lokal sebagai fallback terakhir.
        $dir = is_dir($sibling) ? $sibling : (realpath($local) ?: $local);
        return $dir;
    }
}

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

$envPath = privateDir() . '/.env';

// Auto-create private/.env if missing on initial cPanel deployment
// (hanya bila folder private/ sudah ada — jangan pernah membuat folder
//  private/ di sembarang tempat).
if (!is_file($envPath) && is_dir(privateDir())) {
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
        // MySQL (cPanel) — pengganti Supabase per Danan 2026-11-19.
        // DB_NAME/DB_USER/DB_PASS TIDAK punya fallback: MySQLWriter
        // melempar error jelas bila ketiganya belum diisi di ~/private/.env.
        'DB_HOST'              => 'localhost',
        'DB_PORT'              => '3306',
    ];

    if (isset($defaults[$key])) {
        return $defaults[$key];
    }

    // Auto-resolve cache/sqlite paths RELATIF KE private/ (runtime state
    // tetap di luar public_html supaya tidak tertimpa tiap deploy).
    // STORAGE_PATH DIHAPUS (2026-11-18): galeri produk R2-only, tidak ada
    // lagi mirror lokal di server.
    if ($key === 'SQLITE_PATH') {
        $dir = realpath(privateDir() . '/data');
        if ($dir === false) {
            $dir = privateDir() . '/data';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $dir . '/website.sqlite';
    }
    if ($key === 'CACHE_PATH') {
        $dir = realpath(privateDir() . '/cache');
        if ($dir === false) {
            $dir = privateDir() . '/cache';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $dir;
    }
    if ($key === 'DATA_PATH') {
        $dir = realpath(privateDir() . '/data');
        if ($dir === false) {
            $dir = privateDir() . '/data';
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

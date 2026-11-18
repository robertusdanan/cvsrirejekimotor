<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// Kode dimuat dari repo (public_html/config, public_html/src) — ikut
// ter-deploy otomatis. Secrets tetap dibaca config.php dari sibling
// ~/private/.env (lihat config/config.php).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SupabaseAdminUsers.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Supabase.php';
require_once __DIR__ . '/../src/SupabaseWriter.php';
require_once __DIR__ . '/../src/XlsxReader.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/CacheBuilder.php';
require_once __DIR__ . '/../src/ImageHandler.php';
require_once __DIR__ . '/../src/R2WriteClient.php';

Auth::startSecureSession();

if (!function_exists('db')) {
    function db(): PDO
    {
        return Database::connection();
    }
}

if (!function_exists('supabaseWriter')) {
    function supabaseWriter(): SupabaseWriter
    {
        static $writer = null;
        if ($writer === null) {
            $writer = new SupabaseWriter(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));
        }
        return $writer;
    }
}

if (!function_exists('supabaseAdminUsers')) {
    function supabaseAdminUsers(): SupabaseAdminUsers
    {
        static $client = null;
        if ($client === null) {
            $client = new SupabaseAdminUsers(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));
        }
        return $client;
    }
}

if (!function_exists('cacheBuilder')) {
    function cacheBuilder(): CacheBuilder
    {
        static $builder = null;
        if ($builder === null) {
            $builder = new CacheBuilder(supabaseWriter());
        }
        return $builder;
    }
}

if (!function_exists('safeRebuildProduct')) {
    // Rebuild cache produk TANPA menggagalkan halaman admin bila rebuild
    // gagal (mis. Supabase lambat / timeout). Mengembalikan true bila cache
    // berhasil diperbarui, false bila gagal — pemanggil WAJIB menampilkan
    // $GLOBALS['cacheRebuildWarning'] ke admin supaya kegagalan terlihat,
    // bukan diam-diam (dulu: rebuild gagal = cache situs tetap lama tanpa
    // ada yang tahu).
    function safeRebuildProduct(int $configId): bool
    {
        try {
            // Rebuild 1000 produk + tulis ~1000 file: beri waktu lebih dari
            // batas default web (30 dtk) supaya tidak mati di tengah jalan.
            @set_time_limit(120);
            @ignore_user_abort(true);
            cacheBuilder()->rebuildProducts();
            unset($GLOBALS['cacheRebuildWarning']);
            return true;
        } catch (Throwable $e) {
            error_log("Cache rebuild failed for product_config {$configId}: " . $e->getMessage());
            $GLOBALS['cacheRebuildWarning'] = 'Data tersimpan, tetapi cache belum diperbarui: ' . $e->getMessage() . ' '
                . 'Coba "Refresh Semua Cache" di Dashboard beberapa saat lagi.';
            return false;
        }
    }
}

if (!function_exists('safeRebuildAllProducts')) {
    function safeRebuildAllProducts(): bool
    {
        try {
            @set_time_limit(120);
            @ignore_user_abort(true);
            cacheBuilder()->rebuildProducts();
            unset($GLOBALS['cacheRebuildWarning']);
            return true;
        } catch (Throwable $e) {
            error_log('Full product cache rebuild failed: ' . $e->getMessage());
            $GLOBALS['cacheRebuildWarning'] = 'Data tersimpan ke Supabase, tetapi cache produk belum diperbarui: ' . $e->getMessage() . ' '
                . 'Coba "Refresh Semua Cache" di Dashboard beberapa saat lagi.';
            return false;
        }
    }
}

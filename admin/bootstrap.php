<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

require_once privatePath('config/config.php');
require_once privatePath('src/Database.php');
require_once privatePath('src/SupabaseAdminUsers.php');
require_once privatePath('src/Auth.php');
require_once privatePath('src/Supabase.php');
require_once privatePath('src/SupabaseWriter.php');
require_once privatePath('src/XlsxReader.php');
require_once privatePath('src/Cache.php');
require_once privatePath('src/CacheBuilder.php');
require_once privatePath('src/ImageHandler.php');
require_once privatePath('src/R2WriteClient.php');

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
    function safeRebuildProduct(int $configId): void
    {
        try {
            cacheBuilder()->rebuildProducts();
        } catch (Throwable $e) {
            error_log("Cache rebuild failed for product_config {$configId}: " . $e->getMessage());
            $GLOBALS['cacheRebuildWarning'] = 'Data tersimpan, tetapi cache belum sepenuhnya diperbarui karena Supabase sedang tidak terjangkau. '
                . 'Coba "Refresh Semua Cache" di Dashboard beberapa saat lagi.';
        }
    }
}

if (!function_exists('safeRebuildAllProducts')) {
    function safeRebuildAllProducts(): void
    {
        try {
            cacheBuilder()->rebuildProducts();
        } catch (Throwable $e) {
            error_log('Full product cache rebuild failed: ' . $e->getMessage());
            $GLOBALS['cacheRebuildWarning'] = 'Data tersimpan ke Supabase, tetapi cache produk belum sepenuhnya diperbarui karena Supabase sedang tidak terjangkau. '
                . 'Coba "Refresh Semua Cache" di Dashboard beberapa saat lagi.';
        }
    }
}

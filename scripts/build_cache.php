<?php
declare(strict_types=1);

/**
 * CLI entry point to rebuild the public JSON cache from Supabase.
 *
 * Usage:
 *   php build_cache.php                      -> rebuild everything
 *   php build_cache.php --resource=products
 *   php build_cache.php --resource=company
 *   php build_cache.php --resource=marketplaces
 *
 * Also intended to be triggered by cPanel Cron every 10-15 minutes as a
 * reconciliation safety net in case a webhook call was missed.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/MySQLWriter.php';
require __DIR__ . '/../src/CacheBuilder.php';

$options = getopt('', ['resource::']);
$resource = $options['resource'] ?? null;

$builder = new CacheBuilder(
    new MySQLWriter(),
    new Cache()
);

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

    echo "Cache rebuild complete (" . date('c') . ")." . PHP_EOL;
} catch (Throwable $e) {
    // Cache::write() is atomic (tmp + rename), so a failed rebuild leaves the
    // previous last-known-good cache untouched.
    error_log('build_cache.php failed: ' . $e->getMessage());
    fwrite(STDERR, "Cache rebuild GAGAL: " . $e->getMessage() . ". Cache lama (jika ada) tetap dipakai." . PHP_EOL);
    exit(1);
}

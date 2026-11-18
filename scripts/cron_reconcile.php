<?php
declare(strict_types=1);

/**
 * Cron safety net: periodically rebuilds the entire public JSON cache directly
 * from Supabase, in case a webhook call was missed (network hiccup, etc.).
 *
 * Recommended cPanel schedule: every 15 minutes.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/SupabaseWriter.php';
require __DIR__ . '/../src/CacheBuilder.php';

$builder = new CacheBuilder(
    new SupabaseWriter(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY')),
    new Cache()
);

try {
    $builder->rebuildAll();
    echo "Cron reconcile complete (" . date('c') . ")." . PHP_EOL;
} catch (Throwable $e) {
    error_log('cron_reconcile.php failed: ' . $e->getMessage());
    fwrite(STDERR, "Cron reconcile failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

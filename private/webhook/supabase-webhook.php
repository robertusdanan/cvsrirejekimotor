<?php
declare(strict_types=1);

/**
 * Receives Supabase Database Webhook calls (configure via SQL trigger + pg_net,
 * see supabase_webhook_setup.sql, on INSERT/UPDATE/DELETE for tables:
 * products, product_images, marketplaces, company_profile, hero_slides).
 *
 * Any change on those tables triggers a full cache rebuild from Supabase.
 */

// Tables that actually feed the public cache (schema v2). Any webhook call
// for a table outside this list is acknowledged but ignored.
const WATCHED_TABLES = [
    'products',
    'product_images',
    'marketplaces',
    'company_profile',
    'hero_slides',
];

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/SupabaseWriter.php';
require __DIR__ . '/../src/CacheBuilder.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

// Shared-secret validation via custom header, e.g. X-Webhook-Secret: <WEBHOOK_SECRET>
$providedSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if (!hash_equals((string) config('WEBHOOK_SECRET'), $providedSecret)) {
    http_response_code(401);
    error_log('Webhook rejected: invalid secret from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) || !isset($payload['table'], $payload['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed payload']);
    exit;
}

if (!in_array($payload['table'], WATCHED_TABLES, true)) {
    http_response_code(200); // acknowledge but ignore unrelated tables
    echo json_encode(['status' => 'ignored', 'table' => $payload['table']]);
    exit;
}

try {
    $writer = new SupabaseWriter(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildAll();

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    error_log('Webhook processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'internal error']);
}
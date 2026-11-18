<?php
declare(strict_types=1);

/**
 * Thin public entry point. All real logic (secret validation, cache rebuild)
 * lives in webhook/supabase-webhook.php.
 * Configure this file's full URL in Supabase -> Database -> Webhooks.
 */
require __DIR__ . '/../webhook/supabase-webhook.php';

<?php
declare(strict_types=1);

/**
 * Thin public entry point. All real logic (secret validation, cache rebuild)
 * lives in private/webhook/supabase-webhook.php, outside public_html.
 * Configure this file's full URL in Supabase -> Database -> Webhooks.
 */
require __DIR__ . '/../private/webhook/supabase-webhook.php';

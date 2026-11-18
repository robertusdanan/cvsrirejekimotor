<?php
/**
 * cpanel-deploy.php — Webhook Auto-Deployment for CV Sri Rejeki Motor
 * Menerima trigger push dari GitHub Webhook dan mengeksekusi git sync + cache refresh.
 * Build version: 1.0.5 (token dari .env/environment saja, tanpa hardcode; tanpa fallback username)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

// Deploy token is NEVER hardcoded: it must come from the server environment
// (DEPLOY_WEBHOOK_TOKEN), from private/.env, or from private/deploy-token.txt.
// All three live OUTSIDE public_html and are never committed to git.
// If no token is configured, deployment is refused (fail-closed).
$expectedToken = (string) (getenv('DEPLOY_WEBHOOK_TOKEN') ?: '');
if ($expectedToken === '') {
    foreach ([dirname(__DIR__) . '/private/.env', __DIR__ . '/private/.env'] as $envFile) {
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if (strpos($line, 'DEPLOY_WEBHOOK_TOKEN=') === 0) {
                    $expectedToken = trim(substr($line, strlen('DEPLOY_WEBHOOK_TOKEN=')), " \t\n\r\0\x0B\"'");
                    break 2;
                }
            }
        }
    }
}
if ($expectedToken === '') {
    $tokenFile = dirname(__DIR__) . '/private/deploy-token.txt';
    if (is_file($tokenFile)) {
        $expectedToken = trim((string) file_get_contents($tokenFile));
    }
}
$providedToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Token deployment tidak valid.'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Diagnostic mode: read error_log if requested
if (isset($_GET['action']) && $_GET['action'] === 'read_error_log') {
    $logs = [];
    $logFiles = [
        __DIR__ . '/error_log',
        __DIR__ . '/admin/error_log',
        dirname(__DIR__) . '/error_log',
        dirname(__DIR__) . '/repositories/cvsrirejekimotor/error_log',
    ];
    foreach ($logFiles as $lf) {
        if (is_file($lf)) {
            $lines = file($lf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logs[basename(dirname($lf)) . '/' . basename($lf)] = array_slice($lines ?: [], -30);
        }
    }
    echo json_encode(['status' => 'debug', 'php_version' => PHP_VERSION, 'error_logs' => $logs], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/*
 * ┌─────────────────────────────────────────────────────────────┐
 * │  Resolve the true cPanel user's home and public_html dirs  │
 * │  (cPanel sets USER env var at runtime; fall back to known) │
 * └─────────────────────────────────────────────────────────────┘
 */
$currentUser = getenv('USER');
if (empty($currentUser) || $currentUser === false) {
    // Try shell 'whoami' as a fallback
    $whoami = @trim((string) @shell_exec('whoami 2>/dev/null'));
    $currentUser = !empty($whoami) ? $whoami : '';
}
if (empty($currentUser)) {
    // Parse from script path /home/USERNAME/repositories/cvsrirejekimotor
    $thisDir = __DIR__;
    if (preg_match('#^/home/([^/]+)/#', $thisDir, $m)) {
        $currentUser = $m[1];
    }
}
// No hardcoded username fallback: if the user cannot be detected from the
// environment, whoami, or the script path, refuse instead of guessing.
if (empty($currentUser)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Tidak dapat mendeteksi user cPanel.'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$homeDir = '/home/' . $currentUser;
$repoDir = $homeDir . '/repositories/cvsrirejekimotor';
$publicHtml = $homeDir . '/public_html';

$output = [];
$output['time'] = date('Y-m-d H:i:s');
$output['php_version'] = PHP_VERSION;
$output['debug'] = [
    'currentUser'   => $currentUser,
    'homeDir'       => $homeDir,
    'repoDir'       => $repoDir,
    'publicHtml'    => $publicHtml,
    'is_dir_repoDir'      => is_dir($repoDir),
    'is_dir_publicHtml'   => is_dir($publicHtml),
    'repoDir_has_git'     => is_dir($repoDir . '/.git'),
];

// ── 1. Git Sync ──────────────────────────────────────────────────────
if (is_dir($repoDir . '/.git')) {
    $gitBin = '/usr/bin/git';
    if (!is_executable($gitBin)) {
        $gitBin = '/usr/local/cpanel/3rdparty/bin/git';
        if (!is_executable($gitBin)) {
            $gitBin = 'git';
        }
    }

    // Set HOME so git can find credentials/ssh config
    $cmdEnv = "HOME=" . escapeshellarg($homeDir);
    $cmdPull = $cmdEnv . " cd " . escapeshellarg($repoDir) . " && {$gitBin} fetch origin main 2>&1 && {$gitBin} reset --hard origin/main 2>&1";
    $resPull = function_exists('shell_exec') ? @shell_exec($cmdPull) : 'shell_exec disabled';
    $output['git_sync'] = trim((string)$resPull);
} else {
    $output['git_sync'] = 'repoDir not found with .git: ' . $repoDir;
}

// ── 2. Sync files to public_html ─────────────────────────────────────
if (!is_dir($publicHtml)) {
    @mkdir($publicHtml, 0755, true);
}

if (is_dir($repoDir)) {
    // Copy repository contents (excluding .git, .gitignore, .cpanel.yml) to public_html
    $cmdCp = "/bin/cp -a " . escapeshellarg($repoDir . '/.') . " " . escapeshellarg($publicHtml . '/') . " 2>&1";
    $resCp = function_exists('shell_exec') ? @shell_exec($cmdCp) : 'shell_exec disabled';
    $output['cp_sync'] = trim((string)$resCp);

    // Remove git artifacts from public_html
    @shell_exec("/bin/rm -rf " . escapeshellarg($publicHtml . '/.git') . " " . escapeshellarg($publicHtml . '/.gitignore') . " " . escapeshellarg($publicHtml . '/.cpanel.yml') . " 2>/dev/null");

    // Fix permissions for suPHP / LiteSpeed
    @shell_exec("/bin/find " . escapeshellarg($publicHtml) . " -type d -exec chmod 755 {} + 2>/dev/null");
    @shell_exec("/bin/find " . escapeshellarg($publicHtml) . " -type f -exec chmod 644 {} + 2>/dev/null");
} else {
    $output['cp_sync'] = 'repoDir does not exist: ' . $repoDir;
}

// ── 3. Cache rebuild ────────────────────────────────────────────────
try {
    $privatePath = $homeDir . '/private';
    if (!is_dir($privatePath)) {
        $privatePath = $publicHtml . '/private';
    }

    $configLoaded = false;
    if (is_file($privatePath . '/config/config.php')) {
        require_once $privatePath . '/config/config.php';
        if (is_file($privatePath . '/src/SupabaseWriter.php')) {
            require_once $privatePath . '/src/SupabaseWriter.php';
        }
        if (is_file($privatePath . '/src/Cache.php')) {
            require_once $privatePath . '/src/Cache.php';
        }
        if (is_file($privatePath . '/src/CacheBuilder.php')) {
            require_once $privatePath . '/src/CacheBuilder.php';
        }
        $configLoaded = true;
    } elseif (is_file($publicHtml . '/bootstrap.php')) {
        require_once $publicHtml . '/bootstrap.php';
        $configLoaded = true;
    }

    if (function_exists('cacheBuilder')) {
        cacheBuilder()->rebuildAll();
        $output['cache_rebuild'] = 'success';
    } elseif ($configLoaded && function_exists('config')) {
        $writer = new SupabaseWriter(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));
        $cache = new Cache();
        $builder = new CacheBuilder($writer, $cache);
        $builder->rebuildAll();
        $output['cache_rebuild'] = 'success';
    } else {
        $output['cache_rebuild'] = 'skipped (no config/cache loaded)';
    }
} catch (Throwable $e) {
    $output['cache_rebuild'] = 'error: ' . $e->getMessage();
}

$output['status'] = 'deployed';

echo json_encode([
    'status' => 'success',
    'details' => $output
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

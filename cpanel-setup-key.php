<?php
/**
 * cpanel-setup-key.php — Standalone SSH Deploy Key Generator for cPanel
 * ZERO external dependencies to guarantee no HTTP 500 errors.
 * Build version: 1.0.1 (token dari .env/environment saja, tanpa hardcode)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: text/html; charset=utf-8');

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
    echo '<h2 style="font-family:sans-serif;color:#c53030;padding:20px;">403 Forbidden: Token Akses Tidak Valid</h2>';
    exit;
}

/**
 * Resolve the true cPanel user's home directory.
 */
$currentUser = getenv('USER');
if (empty($currentUser)) {
    $currentUser = trim((string) @shell_exec('whoami 2>/dev/null')) ?: '';
}
if ($currentUser === '') {
    // Last resort: guess from home directory path
    $homePath = getenv('HOME');
    if ($homePath !== false && $homePath !== '') {
        $currentUser = basename($homePath);
    }
}
if ($currentUser === '' || $currentUser === getenv('USER')) {
    // If USER env is "root" or empty, try to detect from the script location
    $scriptParent = dirname(__DIR__);
    if (preg_match('#^/home/([^/]+)/#', $scriptParent, $m)) {
        $currentUser = $m[1];
    } else {
        http_response_code(500);
        echo '<h2 style="font-family:sans-serif;color:#c53030;padding:20px;">500: Tidak dapat mendeteksi user cPanel.</h2>';
        exit;
    }
}

$homeDir = '/home/' . $currentUser;
$sshDir = $homeDir . '/.ssh';
$keyPath = $sshDir . '/id_rsa_cpanel_deploy';
$pubKeyPath = $keyPath . '.pub';

$message = '';
$pubKeyContent = '';

// Ensure .ssh directory exists
if (!is_dir($sshDir)) {
    @mkdir($sshDir, 0700, true);
}

// Generate key if not exists
if (!file_exists($keyPath)) {
    $cmd = "ssh-keygen -t ed25519 -f " . escapeshellarg($keyPath) . " -N '' -C 'cPanel Deploy Key for cvsrirejekimotor' 2>&1";
    $output = function_exists('shell_exec') ? shell_exec($cmd) : 'shell_exec disabled';
    if (file_exists($keyPath)) {
        $message = "SSH Key baru berhasil dibuat!";
        @chmod($keyPath, 0600);
        @chmod($pubKeyPath, 0644);
    } else {
        $message = "Gagal membuat SSH key. Periksa izin folder .ssh.";
        if (isset($output)) {
            $message .= " Detail: " . $output;
        }
    }
} else {
    $message = "SSH Key sudah ada di server.";
    @chmod($keyPath, 0600);
    @chmod($pubKeyPath, 0644);
}

// Read public key
if (file_exists($pubKeyPath)) {
    $pubKeyContent = trim((string) @file_get_contents($pubKeyPath));
}

if ($pubKeyContent === '') {
    $pubKeyContent = "SSH Key belum dapat dibuat secara otomatis (shell_exec mungkin dibatasi host).\n\nSilakan kontak penyedia hosting Anda untuk membuat SSH Deploy Key secara manual dengan perintah:\nssh-keygen -t ed25519 -C 'cPanel Deploy Key'\n\nKemudian salin public key yang dihasilkan.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup SSH Deploy Key - CV Sri Rejeki Motor</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8f9fa; color: #111; padding: 20px; line-height: 1.6; }
        .card { max-width: 750px; margin: 20px auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 28px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h1 { font-size: 20px; margin-top: 0; color: #0f172a; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        textarea { width: 100%; height: 140px; font-family: monospace; font-size: 13px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f1f5f9; box-sizing: border-box; }
        ol { padding-left: 20px; }
        li { margin-bottom: 8px; }
        code { background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge"><?= htmlspecialchars($message) ?></span>
        <h1>SSH Public Key untuk GitHub Deploy Key</h1>
        <p>Salin <strong>Public Key</strong> di bawah ini, lalu tambahkan ke Repository GitHub Anda:</p>
        <textarea readonly onclick="this.select()"><?= htmlspecialchars($pubKeyContent) ?></textarea>

        <h3>Langkah Selanjutnya di GitHub:</h3>
        <ol>
            <li>Buka GitHub Repository <code>cvsrirejekimotor</code>.</li>
            <li>Masuk ke <strong>Settings</strong> &rarr; <strong>Deploy keys</strong> &rarr; <strong>Add deploy key</strong>.</li>
            <li>Judul / Title: <code>cPanel Shared Hosting</code></li>
            <li>Paste Public Key di atas ke kolom <strong>Key</strong>.</li>
            <li>Klik <strong>Add key</strong>.</li>
        </ol>

        <h3>Langkah di cPanel Git™ Version Control:</h3>
        <p>Setelah Deploy Key terpasang, gunakan Clone URL ini di cPanel (tanpa password):</p>
        <p><code>git@github.com:robertusdanan/cvsrirejekimotor.git</code></p>
    </div>
</body>
</html>

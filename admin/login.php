<?php
require __DIR__ . '/bootstrap.php';

/**
 * Verifikasi token Cloudflare Turnstile di sisi server.
 *
 * Secret key dibaca dari private/.env (TURNSTILE_SECRET_KEY) — TIDAK PERNAH
 * ditulis di repo (repo ini publik). Site key bersifat publik dan tertulis
 * langsung di widget HTML di bawah.
 *
 * Bila secret belum diisi di .env, verifikasi server dilewati agar login
 * tidak terkunci; verifikasi aktif otomatis begitu secret diisi. Isi secret
 * SEGERA setelah deploy agar proteksi penuh.
 */
function verifyTurnstile(?string $token): bool
{
    $secret = config('TURNSTILE_SECRET_KEY');
    if ($secret === null || $secret === '') {
        error_log('Turnstile: TURNSTILE_SECRET_KEY belum dikonfigurasi di .env — verifikasi server dilewati.');
        return true;
    }
    if ($token === null || $token === '') {
        return false;
    }
    $post = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $post,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($res === false) {
        error_log('Turnstile: siteverify tidak terjangkau.');
        return false;
    }
    $data = json_decode($res, true);
    return is_array($data) && ($data['success'] ?? false) === true;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!verifyTurnstile($_POST['cf-turnstile-response'] ?? null)) {
        $error = 'Verifikasi keamanan gagal. Silakan coba lagi.';
    } elseif (Auth::attempt($username, $password)) {
        Auth::audit('login');
        header('Location: /admin/index.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><title>Login Admin | CV Sri Rejeki Motor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<?php $adminCssVersion = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: time(); ?>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= (int) $adminCssVersion ?>">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="admin-login-body">
  <form method="post" class="admin-login-form" onsubmit="return !document.getElementById('loginBtn').disabled">
    <img src="/assets/img/logoputih.webp" alt="CV Sri Rejeki Motor" class="admin-login-logo">
    <h1>Login Admin</h1>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <label>Username <input type="text" name="username" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <div class="cf-turnstile" data-sitekey="0x4AAAAAAGGmJ33qNECk0rX9" data-theme="dark" data-callback="turnstileSuccess" data-expired-callback="turnstileExpired" data-error-callback="turnstileError"></div>
    <button type="submit" class="btn btn-primary" id="loginBtn" disabled>Masuk</button>
  </form>
  <script>
    function turnstileSuccess() { document.getElementById('loginBtn').disabled = false; }
    function turnstileExpired() { document.getElementById('loginBtn').disabled = true; }
    function turnstileError() { document.getElementById('loginBtn').disabled = true; }
  </script>
</body>
</html>

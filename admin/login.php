<?php
require __DIR__ . '/bootstrap.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (Auth::attempt($username, $password)) {
        Auth::audit('login');
        header('Location: /admin/index.php');
        exit;
    }
    $error = 'Username atau password salah.';
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><title>Login Admin | CV Sri Rejeki Motor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-login-body">
  <form method="post" class="admin-login-form">
    <h1>Login Admin</h1>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <label>Username <input type="text" name="username" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit" class="btn btn-primary">Masuk</button>
  </form>
</body>
</html>

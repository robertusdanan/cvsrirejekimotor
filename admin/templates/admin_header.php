<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin | CV Sri Rejeki Motor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<?php $adminCssVersion = @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: time(); ?>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= (int) $adminCssVersion ?>">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <img src="/assets/img/logoputih.webp" alt="" class="admin-brand-logo">
      <span>SRM Admin</span>
      <a href="/" target="_blank" class="admin-view-site" title="Buka Website Publik">&#8599; Lihat Web</a>
    </div>
    <nav>
      <span class="admin-nav-label">Utama</span>
      <a href="/admin/index.php" class="<?= $currentPage === 'index.php' ? 'is-active' : '' ?>">Dashboard</a>
      <span class="admin-nav-label">Katalog</span>
      <a href="/admin/products.php" class="<?= ($currentPage === 'products.php' || $currentPage === 'product_edit.php' || $currentPage === 'product_new.php') ? 'is-active' : '' ?>">Produk Website</a>
      <a href="/admin/marketplaces.php" class="<?= $currentPage === 'marketplaces.php' ? 'is-active' : '' ?>">Marketplace</a>
      <span class="admin-nav-label">Toko</span>
      <a href="/admin/company_profile.php" class="<?= $currentPage === 'company_profile.php' ? 'is-active' : '' ?>">Company Profile</a>
      <a href="/admin/hero.php" class="<?= $currentPage === 'hero.php' ? 'is-active' : '' ?>">Hero Beranda</a>
      <?php if (Auth::isSuperAdmin()): ?>
      <span class="admin-nav-label">Sistem</span>
      <a href="/admin/migrasi.php" class="<?= ($currentPage === 'migrasi.php' || $currentPage === 'migrasi_template.php') ? 'is-active' : '' ?>">Migrasi Data</a>
      <a href="/admin/users.php" class="<?= $currentPage === 'users.php' ? 'is-active' : '' ?>">Pengguna Admin</a>
      <?php endif; ?>
    </nav>
    <div class="admin-meta" style="padding:16px 24px;color:#9198A2;font-size:0.75rem;border-top:1px solid rgba(255,255,255,0.06);">
      <strong><?= e($_SESSION['admin_username'] ?? '') ?></strong><br>
      <span style="color:#D8B877;"><?= Auth::isSuperAdmin() ? 'Super Admin' : 'Staff' ?></span>
    </div>
    <form method="post" action="/admin/logout.php" class="admin-logout">
      <button type="submit" class="btn-link">Keluar Sistem</button>
    </form>
  </aside>
  <div class="admin-content">

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
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <span>SRM Admin</span>
      <a href="/" target="_blank" class="admin-view-site" title="Buka Website Publik">&#8599; Lihat Web</a>
    </div>
    <nav>
      <a href="/admin/index.php" class="<?= $currentPage === 'index.php' ? 'is-active' : '' ?>">Dashboard</a>
      <a href="/admin/products.php" class="<?= ($currentPage === 'products.php' || $currentPage === 'product_edit.php' || $currentPage === 'product_new.php') ? 'is-active' : '' ?>">Produk Website</a>
      <a href="/admin/marketplaces.php" class="<?= $currentPage === 'marketplaces.php' ? 'is-active' : '' ?>">Marketplace</a>
      <a href="/admin/company_profile.php" class="<?= $currentPage === 'company_profile.php' ? 'is-active' : '' ?>">Company Profile</a>
      <a href="/admin/hero.php" class="<?= $currentPage === 'hero.php' ? 'is-active' : '' ?>">Hero Beranda</a>
      <?php if (Auth::isSuperAdmin()): ?>
      <a href="/admin/migrasi.php" class="<?= ($currentPage === 'migrasi.php' || $currentPage === 'migrasi_template.php') ? 'is-active' : '' ?>">Migrasi Data</a>
      <a href="/admin/users.php" class="<?= $currentPage === 'users.php' ? 'is-active' : '' ?>">Pengguna Admin</a>
      <?php endif; ?>
    </nav>
    <div class="admin-meta" style="padding:16px 24px;color:#9198A2;font-size:0.75rem;border-top:1px solid rgba(255,255,255,0.06);">
      <strong><?= e($_SESSION['admin_username'] ?? '') ?></strong><br>
      <span style="color:var(--a-brass);"><?= Auth::isSuperAdmin() ? 'Super Admin' : 'Staff' ?></span>
    </div>
    <form method="post" action="/admin/logout.php" class="admin-logout">
      <button type="submit" class="btn-link">Keluar Sistem</button>
    </form>
  </aside>
  <div class="admin-content">

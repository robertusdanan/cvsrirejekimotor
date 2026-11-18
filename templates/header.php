<?php
// Hitung kunjungan hari ini (anonim, dedup per IP). Tidak pernah menggagalkan halaman.
if (function_exists('statistikKunjungan')) {
    statistikKunjungan()->catat();
}
$company = cache()->read('company/profile.json') ?? [];
$pageTitle = $pageTitle ?? ($company['company_name'] ?? 'CV Sri Rejeki Motor');
$brandName = $company['company_name'] ?? 'CV Sri Rejeki Motor';
$brandInitials = companyInitials($brandName);
$hasLogo = is_file(__DIR__ . '/../assets/img/logo.webp');
$hasLogoLight = is_file(__DIR__ . '/../assets/img/logoputih.webp');
$bodyClass = $bodyClass ?? '';

$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentUri = '/' . ltrim($currentUri, '/');
?><!DOCTYPE html>
<html lang="id" class="no-js">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>
(function (d) {
  d.classList.replace('no-js', 'js');
  try {
    if (sessionStorage.getItem('srm_intro_seen')) {
      d.classList.add('intro-seen');
    } else {
      sessionStorage.setItem('srm_intro_seen', '1');
    }
  } catch (e) {}
})(document.documentElement);
</script>
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($company['description'] ?? 'Toko Onderdil Mobil terpercaya - CV Sri Rejeki Motor') ?>">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="shortcut icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,600&family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php $cssVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= (int) $cssVersion ?>">
</head>
<body class="<?= e(trim($bodyClass)) ?>">

<div class="preloader" id="preloader" aria-hidden="true">
  <div class="preloader-plate">
    <?php if ($hasLogo): ?>
      <span class="preloader-medallion">
        <img src="/assets/img/logo.webp" alt="" class="preloader-logo">
      </span>
    <?php else: ?>
      <span class="preloader-ring"></span>
      <span class="preloader-mark"><?= e($brandInitials) ?></span>
      <span class="preloader-flash"></span>
    <?php endif; ?>
    <span class="preloader-brand"><?= e($brandName) ?></span>
    <span class="preloader-kicker">Toko Onderdil Mobil</span>
    <span class="preloader-rule"></span>
  </div>
  <div class="preloader-panel preloader-panel-l"></div>
  <div class="preloader-panel preloader-panel-r"></div>
</div>

<header class="site-header">
  <div class="container header-inner">
    <a href="/" class="brand" aria-label="<?= e($brandName) ?> Beranda">
      <?php if ($hasLogo): ?>
        <img src="/assets/img/logo.webp" alt="<?= e($brandName) ?>" class="brand-logo brand-logo-dark">
        <?php if ($hasLogoLight): ?>
          <img src="/assets/img/logoputih.webp" alt="<?= e($brandName) ?>" class="brand-logo brand-logo-light">
        <?php endif; ?>
      <?php else: ?>
        <span class="brand-tag-mark"><?= e($brandInitials) ?></span>
        <span class="brand-text"><?= e($brandName) ?></span>
      <?php endif; ?>
    </a>

    <button type="button" class="nav-toggle" aria-label="Buka menu navigasi" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <div class="nav-scrim"></div>
    <nav class="main-nav" id="mainNav">
      <a href="/" class="<?= ($currentUri === '/' || $currentUri === '/index.php') ? 'is-active' : '' ?>">Beranda</a>
      <a href="/produk" class="<?= str_starts_with($currentUri, '/produk') ? 'is-active' : '' ?>">Katalog Produk</a>
      <a href="/tentang-kami" class="<?= str_starts_with($currentUri, '/tentang-kami') ? 'is-active' : '' ?>">Tentang Kami</a>
      <a href="/kontak" class="<?= str_starts_with($currentUri, '/kontak') ? 'is-active' : '' ?>">Kontak &amp; Lokasi</a>
      <?php if (!empty($company['whatsapp'])): ?>
        <?php
          $cleanWa = preg_replace('/[^0-9]/', '', $company['whatsapp']);
          if (str_starts_with($cleanWa, '0')) {
              $cleanWa = '62' . substr($cleanWa, 1);
          }
        ?>
        <a href="https://wa.me/<?= e($cleanWa) ?>" target="_blank" rel="noopener noreferrer" class="nav-cta-wa">
          <span>WhatsApp Kami</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
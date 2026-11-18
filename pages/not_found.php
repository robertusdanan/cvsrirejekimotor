<?php
$pageTitle = 'Halaman Tidak Ditemukan | CV Sri Rejeki Motor';
require __DIR__ . '/../templates/header.php';
?>
<section class="container page-content reveal">
  <div class="error-plate">
    <span class="error-tag">404 : NOT FOUND</span>
    <h1>Halaman Tidak Ditemukan</h1>
    <p>Maaf, produk atau halaman yang Anda tuju tidak tersedia atau telah dipindahkan.</p>
    <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
      <a href="/produk" class="btn btn-primary">Lihat Katalog Produk <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
      <a href="/" class="btn btn-outline">Kembali ke Beranda</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../templates/footer.php'; ?>

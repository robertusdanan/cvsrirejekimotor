<?php
$pageTitle = 'Terjadi Kendala | CV Sri Rejeki Motor';
require __DIR__ . '/../templates/header.php';
?>
<section class="container page-content reveal">
  <div class="error-plate">
    <span class="error-tag error-tag-warn">500 : SYSTEM NOTICE</span>
    <h1>Terjadi Kendala Teknis</h1>
    <p>Sistem sedang memperbarui data atau mengalami kendala sementara. Silakan coba muat ulang halaman beberapa saat lagi.</p>
    <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
      <a href="/" class="btn btn-primary">Kembali ke Beranda <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
      <a href="/kontak" class="btn btn-outline">Hubungi Dukungan</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../templates/footer.php'; ?>

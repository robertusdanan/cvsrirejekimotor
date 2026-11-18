</main>
<?php
$company = cache()->read('company/profile.json') ?? [];
$cleanWa = !empty($company['whatsapp']) ? preg_replace('/[^0-9]/', '', $company['whatsapp']) : '';
if (str_starts_with($cleanWa, '0')) {
    $cleanWa = '62' . substr($cleanWa, 1);
}
?>
<footer class="site-footer">
  <div class="container footer-inner">
    <!-- Kolom 1: Brand & Identity -->
    <div class="footer-col footer-col-brand">
      <div class="footer-brand-header">
        <?php $footerLogo = is_file(__DIR__ . '/../assets/img/logoputih.webp') ? '/assets/img/logoputih.webp' : '/assets/img/logo.webp'; ?>
        <?php if (is_file(__DIR__ . '/../assets/img/logo.webp')): ?>
          <a href="/" class="footer-logo-chip" aria-label="Beranda <?= e($company['company_name'] ?? 'CV Sri Rejeki Motor') ?>">
            <img src="<?= e($footerLogo) ?>" alt="<?= e($company['company_name'] ?? 'CV Sri Rejeki Motor') ?>" class="footer-logo-img">
          </a>
        <?php endif; ?>
        <div class="footer-brand-text">
          <strong class="footer-brand-title"><?= e($company['company_name'] ?? 'CV Sri Rejeki Motor') ?></strong>
          <p class="footer-brand-tagline"><?= e($company['tagline'] ?? 'Pusat Suku Cadang & Onderdil Mobil Terlengkap') ?></p>
        </div>
      </div>
      <p class="footer-desc">
        <?= e(mb_strimwidth($company['description'] ?? 'Menyediakan aneka suku cadang dan onderdil mobil berkualitas tinggi untuk berbagai tipe kendaraan pribadi, komersial, dan armada.', 0, 160, '...')) ?>
      </p>
      <div class="footer-badge-trust">
        <span class="trust-pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Produk Original &amp; OEM</span>
        <span class="trust-pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Layanan Kirim Seluruh Indonesia</span>
      </div>
    </div>

    <!-- Kolom 2: Navigasi Cepat -->
    <div class="footer-col">
      <h4 class="footer-heading">Navigasi Utama</h4>
      <ul class="footer-links">
        <li><a href="/">Beranda Utama</a></li>
        <li><a href="/produk">Katalog Suku Cadang</a></li>
        <li><a href="/tentang-kami">Profil Tentang Kami</a></li>
        <li><a href="/kontak">Kontak &amp; Lokasi Gerai</a></li>
      </ul>
    </div>

    <!-- Kolom 3: Layanan & Jam Operasional -->
    <div class="footer-col">
      <h4 class="footer-heading">Jam Operasional</h4>
      <div class="footer-hours-box">
        <p class="hours-row">
          <span class="hours-day">Senin - Sabtu</span>
          <span class="hours-time">08.00 - 17.00 WIB</span>
        </p>
        <p class="hours-row">
          <span class="hours-day">Minggu &amp; Libur</span>
          <span class="hours-time">Tutup / Chat WA</span>
        </p>
      </div>
      <p class="footer-note-wa">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        Konsultasi via WhatsApp tersedia di luar jam operasional toko fisik.
      </p>
    </div>

    <!-- Kolom 4: Hubungi Kami -->
    <div class="footer-col">
      <h4 class="footer-heading">Hubungi Kami</h4>
      <div class="footer-contact">
        <?php if (!empty($company['address'])): ?>
          <div class="footer-contact-item">
            <span class="footer-icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
            <span><?= e($company['address']) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($company['whatsapp'])): ?>
          <div class="footer-contact-item">
            <span class="footer-icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></span>
            <a href="https://wa.me/<?= e($cleanWa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp: <?= e($company['whatsapp']) ?></a>
          </div>
        <?php endif; ?>
        <?php if (!empty($company['phone'])): ?>
          <div class="footer-contact-item">
            <span class="footer-icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $company['phone'])) ?>">Telp: <?= e($company['phone']) ?></a>
          </div>
        <?php endif; ?>
        <?php if (!empty($company['email'])): ?>
          <div class="footer-contact-item">
            <span class="footer-icon" aria-hidden="true"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
            <a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Kolom 5: Statistik Pengunjung -->
    <div class="footer-col">
      <h4 class="footer-heading">Statistik</h4>
      <?php $stats = function_exists('statistikKunjungan') ? statistikKunjungan()->baca() : ['hari_ini' => 0, 'total' => 0]; ?>
      <div class="footer-stats-box">
        <p class="stats-row">
          <span class="stats-label">Pengunjung Hari Ini</span>
          <span class="stats-value"><?= number_format((int) $stats['hari_ini'], 0, ',', '.') ?></span>
        </p>
        <p class="stats-row">
          <span class="stats-label">Total Pengunjung</span>
          <span class="stats-value"><?= number_format((int) $stats['total'], 0, ',', '.') ?></span>
        </p>
      </div>
      <p class="footer-note-stats">Dihitung otomatis setiap kunjungan, tanpa perlu login.</p>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <div class="copyright">
        &copy; <?= date('Y') ?> <?= e($company['company_name'] ?? 'CV Sri Rejeki Motor') ?>. Hak Cipta Dilindungi.
      </div>
      <button type="button" class="back-to-top" id="backToTop" aria-label="Kembali ke atas">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
        <span>Atas</span>
      </button>
    </div>
  </div>
</footer>

<?php if (!empty($cleanWa)): ?>
<a href="https://wa.me/<?= e($cleanWa) ?>" class="floating-wa" target="_blank" rel="noopener noreferrer" aria-label="Konsultasi WhatsApp">
  <span class="floating-wa-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></span>
  <span class="floating-wa-label">Konsultasi Onderdil</span>
</a>
<?php endif; ?>

<?php $jsVersion = @filemtime(__DIR__ . '/../assets/js/main.js') ?: time(); ?>
<script src="/assets/js/main.js?v=<?= (int) $jsVersion ?>" defer></script>
</body>
</html>
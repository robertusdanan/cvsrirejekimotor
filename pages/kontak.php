<?php
$company = cache()->read('company/profile.json') ?? [];
$pageTitle = 'Kontak & Lokasi Gerai | ' . ($company['company_name'] ?? 'CV Sri Rejeki Motor');
$cleanWa = !empty($company['whatsapp']) ? preg_replace('/[^0-9]/', '', $company['whatsapp']) : '';
if (str_starts_with($cleanWa, '0')) {
    $cleanWa = '62' . substr($cleanWa, 1);
}

require __DIR__ . '/../templates/header.php';
?>
<section class="container page-content reveal">
  <div class="page-header-block">
    <span class="section-eyebrow">Hubungi Kami</span>
    <h1><span class="section-marker"></span>Kontak &amp; Lokasi Gerai</h1>
    <p class="page-subtitle">Punya pertanyaan seputar ketersediaan suku cadang atau konsultasi nomor part? Kami siap membantu Anda.</p>
  </div>

  <div class="contact-cards-grid">
    <?php if (!empty($company['whatsapp'])): ?>
      <div class="contact-card contact-card-highlight">
        <div class="contact-card-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
        <h3>WhatsApp Resmi</h3>
        <p class="contact-card-val"><?= e($company['whatsapp']) ?></p>
        <p class="contact-card-desc">Respon cepat untuk tanya stok, harga, &amp; pemesanan langsung.</p>
        <a href="https://wa.me/<?= e($cleanWa) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-small-action">
          <span>Chat WhatsApp</span> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    <?php endif; ?>

    <?php if (!empty($company['phone'])): ?>
      <div class="contact-card">
        <div class="contact-card-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
        <h3>Saluran Telepon</h3>
        <p class="contact-card-val"><?= e($company['phone']) ?></p>
        <p class="contact-card-desc">Hubungi langsung via telepon pada jam kerja toko.</p>
        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $company['phone'])) ?>" class="btn btn-outline btn-small-action">
          Telepon Sekarang
        </a>
      </div>
    <?php endif; ?>

    <?php if (!empty($company['email'])): ?>
      <div class="contact-card">
        <div class="contact-card-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <h3>Email Bisnis</h3>
        <p class="contact-card-val"><?= e($company['email']) ?></p>
        <p class="contact-card-desc">Untuk penawaran institusi, rekanan bengkel, &amp; kerjasama.</p>
        <a href="mailto:<?= e($company['email']) ?>" class="btn btn-outline btn-small-action">
          Kirim Email
        </a>
      </div>
    <?php endif; ?>

    <div class="contact-card">
      <div class="contact-card-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
      <h3>Alamat Gerai</h3>
      <p class="contact-card-val"><?= e($company['address'] ?? 'Kunjungi toko kami') ?></p>
      <p class="contact-card-desc">Melayani pembelian langsung di counter toko.</p>
      <?php if (!empty($company['google_maps_url'])): ?>
        <a href="<?= e($company['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-small-action">
          Buka Google Maps
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($company['google_maps_embed_url']) || !empty($company['google_maps_url'])): ?>
    <div class="contact-map-section reveal" style="margin-top: 48px;">
      <span class="section-eyebrow">Peta Navigasi</span>
      <h2><span class="section-marker"></span>Lokasi Toko Fisik</h2>
      <?php if (!empty($company['google_maps_embed_url'])): ?>
        <div class="map-embed" style="margin-top: 16px;">
          <iframe
            src="<?= e($company['google_maps_embed_url']) ?>"
            loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Peta Lokasi CV Sri Rejeki Motor"></iframe>
        </div>
      <?php endif; ?>

      <?php if (!empty($company['google_maps_url'])): ?>
        <div style="margin-top: 16px;">
          <a href="<?= e($company['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline">
            <span>Petunjuk Arah via Google Maps</span> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>

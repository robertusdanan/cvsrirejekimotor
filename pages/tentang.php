<?php
$company = cache()->read('company/profile.json') ?? [];
$pageTitle = 'Tentang Kami | ' . ($company['company_name'] ?? 'CV Sri Rejeki Motor');
$cleanWa = !empty($company['whatsapp']) ? preg_replace('/[^0-9]/', '', $company['whatsapp']) : '';
if (str_starts_with($cleanWa, '0')) {
    $cleanWa = '62' . substr($cleanWa, 1);
}

require __DIR__ . '/../templates/header.php';
?>
<section class="container page-content reveal">
  <div class="page-header-block">
    <span class="section-eyebrow">Profil &amp; Dedikasi</span>
    <h1><span class="section-marker"></span>Tentang Kami</h1>
    <p class="page-subtitle"><?= e($company['tagline'] ?? 'Pusat Suku Cadang & Onderdil Mobil Terpercaya') ?></p>
  </div>

  <div class="about-grid">
    <div class="about-main-card">
      <h2 class="about-title"><?= e($company['company_name'] ?? 'CV Sri Rejeki Motor') ?></h2>
      <div class="about-body-text">
        <p>
          <?= nl2br(e($company['description'] ?: 'CV Sri Rejeki Motor adalah penyedia suku cadang dan onderdil mobil terkemuka yang berfokus pada penyediaan produk berkualitas tinggi untuk beragam jenis kendaraan pribadi, komersial, maupun armada perusahaan.')) ?>
        </p>
        <p>
          Kami bertekad menjadi mitra handal bagi pemilik kendaraan, montir, dan bengkel rekanan dengan menyediakan suku cadang lengkap, harga kompetitif, serta kemudahan transaksi online maupun offline.
        </p>
      </div>

      <div class="about-pillars">
        <div class="pillar-item">
          <div class="pillar-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
          <h4>Kualitas Terpercaya</h4>
          <p>Hanya menyediakan produk berkualitas dari merk resmi dan OEM teruji.</p>
        </div>
        <div class="pillar-item">
          <div class="pillar-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
          <h4>Katalog Lengkap</h4>
          <p>Suku cadang mesin, rem, transmisi, kelistrikan, hingga bodi dan aksesoris.</p>
        </div>
        <div class="pillar-item">
          <div class="pillar-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
          <h4>Pelayanan Ramah</h4>
          <p>Konsultasi nomor part dan kecocokan tipe mobil dibantu oleh staf berpengalaman.</p>
        </div>
      </div>
    </div>

    <div class="about-side-card">
      <div class="side-info-box">
        <h3>Informasi Kontak Toko</h3>
        <ul class="side-contact-list">
          <?php if (!empty($company['address'])): ?>
            <li>
              <strong>Alamat Gerai:</strong>
              <span><?= e($company['address']) ?></span>
            </li>
          <?php endif; ?>
          <?php if (!empty($company['whatsapp'])): ?>
            <li>
              <strong>WhatsApp:</strong>
              <a href="https://wa.me/<?= e($cleanWa) ?>" target="_blank" rel="noopener noreferrer"><?= e($company['whatsapp']) ?></a>
            </li>
          <?php endif; ?>
          <?php if (!empty($company['phone'])): ?>
            <li>
              <strong>Telepon:</strong>
              <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $company['phone'])) ?>"><?= e($company['phone']) ?></a>
            </li>
          <?php endif; ?>
          <?php if (!empty($company['email'])): ?>
            <li>
              <strong>Email:</strong>
              <a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a>
            </li>
          <?php endif; ?>
        </ul>

        <div class="side-cta-wrap">
          <a href="/produk" class="btn btn-primary btn-block">Lihat Katalog Onderdil <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
          <a href="/kontak" class="btn btn-outline btn-block" style="margin-top: 10px;">Hubungi Kami</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>

<?php
$company = cache()->read('company/profile.json') ?? [];
$featured = cache()->read('homepage/featured.json')['items'] ?? [];
$brands = cache()->read('brands/index.json')['items'] ?? [];
$categories = cache()->read('categories/index.json')['items'] ?? [];

$pageTitle = ($company['company_name'] ?? 'CV Sri Rejeki Motor') . ' | Toko Onderdil & Suku Cadang Mobil Terpercaya';
$bodyClass = 'has-hero';

$heroSlides = heroActiveSlides();
$heroSettings = heroManifest()['settings'];
$heroKicker = trim((string) ($heroSettings['kicker'] ?? '')) ?: 'Toko Onderdil Mobil ¡¤ Terpercaya & Lengkap';
$heroHeading = trim((string) ($heroSettings['heading_override'] ?? '')) ?: ($company['company_name'] ?? 'CV Sri Rejeki Motor');
$heroTagline = trim((string) ($company['tagline'] ?? '')) ?: 'Pusat Suku Cadang & Onderdil Mobil Berkualitas untuk Berbagai Merk Kendaraan';
$heroInterval = max(3000, (int) ($heroSettings['interval_ms'] ?? 6000));
$heroOverlayOpacity = max(0, min(1, (float) ($heroSettings['overlay_opacity'] ?? 0.65)));

$cleanWa = !empty($company['whatsapp']) ? preg_replace('/[^0-9]/', '', $company['whatsapp']) : '';
if (str_starts_with($cleanWa, '0')) {
    $cleanWa = '62' . substr($cleanWa, 1);
}

require __DIR__ . '/../templates/header.php';
?>
<section class="hero" data-interval="<?= (int) $heroInterval ?>">
  <div class="hero-media" aria-hidden="true">
    <?php if ($heroSlides): ?>
      <?php foreach ($heroSlides as $i => $slide): ?>
        <div class="hero-slide <?= $i === 0 ? 'is-active' : '' ?>">
          <?php if ($slide['type'] === 'video'): ?>
          <video class="hero-slide-media" src="<?= e(mediaUrl($slide['file'])) ?>" muted loop playsinline <?= $i === 0 ? 'autoplay preload="auto"' : 'preload="none"' ?>></video>
          <?php else: ?>
          <div class="hero-slide-media hero-slide-image" style="background-image:url('<?= e(mediaUrl($slide['file'])) ?>')"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="hero-slide is-active">
        <div class="hero-slide-media hero-slide-fallback"></div>
      </div>
    <?php endif; ?>
  </div>
  <div class="hero-overlay" style="--hero-overlay-opacity: <?= e((string) $heroOverlayOpacity) ?>"></div>

  <div class="hero-content">

    <p class="hero-kicker"><?= e($heroKicker) ?></p>
    <h1><?= e($heroHeading) ?></h1>
    <p class="tagline"><?= e($heroTagline) ?></p>

    <!-- Quick Search Bar in Hero -->
    <form action="/produk" method="get" class="hero-search-form">
      <div class="hero-search-wrap">
        <input type="text" name="q" placeholder="Cari nama barang, kode part, atau tipe mobil..." autocomplete="off">
        <button type="submit" class="btn btn-primary btn-hero-search">
          <span>Cari Onderdil</span>
        </button>
      </div>
    </form>

    <div class="hero-actions">
      <a href="/produk" class="btn btn-primary">
        <span class="btn-label-desktop">Lihat Katalog Lengkap</span>
        <span class="btn-label-mobile">Cari Onderdil</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <?php if (!empty($cleanWa)): ?>
        <a href="https://wa.me/<?= e($cleanWa) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-hero-wa">
          <span>Tanya Admin</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      <?php else: ?>
        <a href="/tentang-kami" class="btn btn-outline">Tentang Kami <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></a>
      <?php endif; ?>
    </div>
    <div class="hero-trust">
      <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg> Produk Original &amp; OEM</span>
      <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg> Pengiriman ke Seluruh Indonesia</span>
      <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg> Bantuan Cek Part Number</span>
    </div>

  </div>

  <?php if (count($heroSlides) > 1): ?>
    <div class="hero-dots" role="tablist" aria-label="Pilih tampilan hero">
      <?php foreach ($heroSlides as $i => $slide): ?>
        <button type="button" class="hero-dot<?= $i === 0 ? ' is-active' : '' ?>" data-index="<?= (int) $i ?>" aria-label="Tampilan <?= (int) $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <a href="#keunggulan" class="hero-scroll-cue" aria-label="Gulir ke bawah">
    <span class="hero-scroll-shell"><span class="hero-scroll-dot"></span></span>
    <span class="hero-scroll-label">Gulir</span>
  </a>
</section>

<!-- Quick Brands / Category Explorer -->
<?php if (!empty($brands) || !empty($categories)): ?>
<section class="container brand-explorer reveal">
  <span class="section-eyebrow">Eksplorasi Suku Cadang</span>
  <h2><span class="section-marker"></span>Cari Berdasarkan Merek &amp; Kategori</h2>
  
  <?php if (!empty($brands)): ?>
    <div class="pill-group">
      <span class="pill-label">Merek Populer:</span>
      <div class="pill-list">
        <?php foreach (array_slice($brands, 0, 10) as $b): ?>
          <a href="/produk?merk=<?= urlencode($b) ?>" class="tag-pill"><?= e($b) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($categories)): ?>
    <div class="pill-group" style="margin-top: 14px;">
      <span class="pill-label">Kategori Suku Cadang:</span>
      <div class="pill-list">
        <?php foreach (array_slice($categories, 0, 10) as $c): ?>
          <a href="/produk?kategori=<?= urlencode($c) ?>" class="tag-pill tag-pill-alt"><?= e($c) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($featured)): ?>
<section class="featured container reveal">
  <div class="section-header-flex">
    <div>
      <span class="section-eyebrow">Pilihan Terbaik</span>
      <h2><span class="section-marker"></span>Produk Unggulan</h2>
    </div>
    <a href="/produk" class="section-link-all">Lihat Semua (<?= count($featured) ?>+) &rarr;</a>
  </div>

  <div class="product-grid">
    <?php foreach ($featured as $i => $p): ?>
      <?php
        $priceFinal = $p['harga_final'] ?? $p['harga_jual'] ?? 0;
        $priceOriginal = $p['harga_jual'] ?? 0;
        $hasDiscount = !empty($p['harga_final']) && $p['harga_final'] < $priceOriginal;
      ?>
      <a class="product-card reveal" style="--reveal-delay: <?= min($i, 7) * 0.05 ?>s" href="/produk/<?= e($p['slug']) ?>">
        <div class="product-card-image">
          <?php if (!empty($p['images'][0]['path'])): ?>
            <img src="<?= e(mediaUrl($p['images'][0]['path'])) ?>" alt="<?= e($p['nama_produk']) ?>" loading="lazy">
          <?php elseif (!empty($p['primary_image'])): ?>
            <img src="<?= e(mediaUrl($p['primary_image'])) ?>" alt="<?= e($p['nama_produk']) ?>" loading="lazy">
          <?php else: ?>
            <div class="no-image">
              <span class="no-image-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
              <span>Foto Belum Tersedia</span>
            </div>
          <?php endif; ?>

          <?php if ($hasDiscount): ?>
            <span class="card-badge card-badge-promo">PROMO</span>
          <?php elseif (!empty($p['featured'])): ?>
            <span class="card-badge card-badge-featured">UNGGULAN</span>
          <?php endif; ?>
        </div>
        <div class="product-card-body">
          <p class="part-code"><?= !empty($p['kode_barang']) ? 'PN. ' . e($p['kode_barang']) : '&nbsp;' ?></p>
          <h3><?= e($p['nama_produk']) ?></h3>
          <p class="meta">
            <?php if (!empty($p['merk'])): ?><span class="meta-brand"><?= e($p['merk']) ?></span><?php endif; ?>
            <?php if (!empty($p['nama_mobil'])): ?><span class="meta-car">¡¤ <?= e($p['nama_mobil']) ?></span><?php endif; ?>
          </p>
          <div class="price-wrap">
            <span class="price"><?= rupiah($priceFinal) ?></span>
            <?php if ($hasDiscount): ?>
              <span class="price-original"><?= rupiah($priceOriginal) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Section Keunggulan / Value Proposition -->
<section id="keunggulan" class="features-bar container reveal">
  <div class="features-grid">
    <div class="feature-item">
      <div class="feature-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
      <div class="feature-text">
        <h3>Stok Beragam &amp; Lengkap</h3>
        <p>Ribuan pilihan suku cadang mesin, kaki-kaki, bodi, dan elektrikal untuk berbagai merek kendaraan.</p>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
      <div class="feature-text">
        <h3>Jaminan Kualitas Prima</h3>
        <p>Semua produk dipilih dengan standar mutu tinggi demi keamanan dan performa maksimal kendaraan Anda.</p>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
      <div class="feature-text">
        <h3>Pengiriman Cepat &amp; Aman</h3>
        <p>Didukung opsi pengiriman ekspres ke seluruh wilayah dan packing terlindungi ekstra.</p>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
      <div class="feature-text">
        <h3>Bantuan Cek Part Number</h3>
        <p>Bingung kecocokan tipe onderdil? Konsultasikan nomor rangka &amp; kode part langsung dengan kami.</p>
      </div>
    </div>
  </div>
</section>

<!-- Profil & Lokasi Gerai Fisik (Split Modern Layout) -->
<section id="profil" class="container reveal">
  <div class="studio-split">
    <!-- Kolom Kiri: Profil Ringkas -->
    <div class="studio-card">
      <div>
        <span class="section-eyebrow">Profil Usaha</span>
        <h2><span class="section-marker"></span><?= e($company['company_name'] ?? 'CV Sri Rejeki Motor') ?></h2>
        <p class="profile-desc">
          <?= nl2br(e($company['description'] ?: 'CV Sri Rejeki Motor adalah pusat penyedia suku cadang dan onderdil mobil terpercaya. Kami melayani kebutuhan eceran maupun partai untuk perorangan, bengkel, armada niaga, dan instansi dengan jaminan mutu terbaik.')) ?>
        </p>
      </div>
      <div class="profile-actions">
        <a href="/tentang-kami" class="btn btn-outline">Tentang Kami</a>
        <a href="/kontak" class="btn btn-primary">Hubungi Kami</a>
      </div>
    </div>

    <!-- Kolom Kanan: Gerai Toko & Peta -->
    <div class="studio-card">
      <div>
        <span class="section-eyebrow">Gerai Fisik</span>
        <h2><span class="section-marker"></span>Kunjungi Toko</h2>
        <?php if (!empty($company['address'])): ?>
          <p style="font-size:0.9rem;color:var(--steel);margin:12px 0;display:flex;gap:8px;align-items:flex-start;">
            <span style="color:var(--blue);flex-shrink:0;margin-top:3px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
            <span><?= e($company['address']) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <?php if (!empty($company['google_maps_embed_url'])): ?>
        <div class="map-frame">
          <iframe src="<?= e($company['google_maps_embed_url']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Peta Lokasi Gerai"></iframe>
        </div>
      <?php endif; ?>

      <?php if (!empty($company['google_maps_url'])): ?>
        <div style="margin-top:auto;">
          <a href="<?= e($company['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-block">
            <span>Buka Navigasi di Google Maps</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php
require __DIR__ . '/../templates/footer.php';
<?php
$product = cache()->read("products/{$slug}.json");

if ($product === null) {
    http_response_code(404);
    require __DIR__ . '/not_found.php';
    return;
}

$company = cache()->read('company/profile.json') ?? [];
$cleanWa = !empty($company['whatsapp']) ? preg_replace('/[^0-9]/', '', $company['whatsapp']) : '';
if (str_starts_with($cleanWa, '0')) {
    $cleanWa = '62' . substr($cleanWa, 1);
}

$pageTitle = $product['nama_produk']
    . (!empty($product['merk']) ? ' ' . $product['merk'] : '')
    . ' | Jual Suku Cadang Mobil | CV Sri Rejeki Motor';
$priceFinal = $product['harga_final'] ?? $product['harga_jual'] ?? 0;
$priceOriginal = $product['harga_jual'] ?? 0;
$hasDiscount = !empty($product['harga_final']) && $product['harga_final'] < $priceOriginal;

// Pre-compose WhatsApp inquiry message
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'srirejekimotor.com') . '/produk/' . $product['slug'];
$waText = "Halo CV Sri Rejeki Motor, saya tertarik dengan suku cadang:\n"
        . "*{$product['nama_produk']}*\n"
        . (!empty($product['kode_barang']) ? "Kode Part (PN): {$product['kode_barang']}\n" : '')
        . (!empty($product['merk']) ? "Merek: {$product['merk']}\n" : '')
        . ($priceFinal > 0 ? "Harga: " . rupiah($priceFinal) . "\n\n" : '')
        . ($priceFinal > 0 ? "Apakah barang ini masih tersedia dan cocok untuk kendaraan saya?\n" : "Berapa harga barang ini? Apakah masih tersedia dan cocok untuk kendaraan saya?\n")
        . "Link: {$currentUrl}";
$waUrl = "https://wa.me/{$cleanWa}?text=" . urlencode($waText);

// SEO: deskripsi + canonical + Open Graph unik per produk
$descParts = [];
if (!empty($product['merk'])) { $descParts[] = $product['merk']; }
if (!empty($product['category'])) { $descParts[] = $product['category']; }
$descBase = 'Jual ' . $product['nama_produk']
    . ($descParts ? ' ' . implode(' ', $descParts) : '')
    . ' — Part Number ' . ($product['kode_barang'] ?? '')
    . ($priceFinal > 0 ? '. Harga ' . rupiah($priceFinal) : '. Harga tanyakan via WhatsApp')
    . '. Onderdil mobil original/OEM, kirim ke seluruh Indonesia.';
$pageDescription = !empty($product['description'])
    ? mb_strimwidth(trim(strip_tags($product['description'])), 0, 160, '')
    : $descBase;
$pageCanonical = 'https://cvsrirejekimotor.com/produk/' . $product['slug'];
$pageOgType = 'product';
if (!empty($product['images'][0]['path'])) {
    $pageOgImage = mediaUrl($product['images'][0]['path']);
}

require __DIR__ . '/../templates/header.php';
?>
<div class="container breadcrumb-wrap">
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Beranda</a>
    <span class="breadcrumb-sep"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
    <a href="/produk">Katalog Produk</a>
    <span class="breadcrumb-sep"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
    <span class="breadcrumb-current"><?= e($product['nama_produk']) ?></span>
  </nav>
</div>

<section class="container product-detail">
  <div class="product-gallery reveal">
    <?php if (!empty($product['images'])): ?>
      <div class="gallery-main">
        <img id="mainImage" src="<?= e(mediaUrl($product['images'][0]['path'])) ?>" alt="<?= e($product['nama_produk']) ?>" fetchpriority="high">
      </div>
      <?php if (count($product['images']) > 1): ?>
      <div class="product-gallery-thumbs">
        <?php foreach ($product['images'] as $idx => $img): ?>
          <button type="button" class="thumb-btn <?= $idx === 0 ? 'is-active' : '' ?>" onclick="document.getElementById('mainImage').src='<?= e(mediaUrl($img['path'])) ?>'; document.querySelectorAll('.thumb-btn').forEach(b => b.classList.remove('is-active')); this.classList.add('is-active');" aria-label="Foto produk <?= $idx + 1 ?>">
            <img src="<?= e(mediaUrl($img['path'])) ?>" alt="" loading="lazy">
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="no-image large">
        <span class="no-image-icon" style="font-size: 3rem;" aria-hidden="true"><svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
        <span style="margin-top: 12px; font-weight: 500;">Foto Produk Belum Diunggah</span>
      </div>
    <?php endif; ?>

    <div class="detail-guarantee-card">
      <div class="guarantee-item">
        <span class="guarantee-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
        <div>
          <strong>Suku Cadang Terjamin</strong>
          <p>Kualitas teruji dan siap dipasang pada kendaraan Anda.</p>
        </div>
      </div>
      <div class="guarantee-item">
        <span class="guarantee-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
        <div>
          <strong>Packing Ekstra Rapi</strong>
          <p>Pengiriman dengan perlindungan aman ke seluruh daerah.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="product-info reveal" style="--reveal-delay: 0.1s">
    <div class="product-meta-top">
      <?php if (!empty($product['category'])): ?>
        <span class="category-badge"><?= e($product['category']) ?></span>
      <?php endif; ?>
      <?php if (!empty($product['featured'])): ?>
        <span class="category-badge badge-accent">Produk Unggulan</span>
      <?php endif; ?>
    </div>

    <h1><?= e($product['nama_produk']) ?></h1>

    <?php if (!empty($product['kode_barang'])): ?>
      <div class="part-code-box">
        <span class="part-code-label">Part Number (PN):</span>
        <code class="part-code-val" id="pnCode"><?= e($product['kode_barang']) ?></code>
        <button type="button" class="btn-copy-pn" onclick="navigator.clipboard.writeText('<?= e($product['kode_barang']) ?>'); this.innerText='Tersalin!'; setTimeout(()=>this.innerText='Salin PN', 2000);" title="Salin Kode Part">
          Salin PN
        </button>
      </div>
    <?php endif; ?>

    <div class="price-box">
      <?php if ($priceFinal > 0): ?>
        <div class="price-large"><?= rupiah($priceFinal) ?></div>
        <?php if ($hasDiscount): ?>
          <div class="price-original-large"><?= rupiah($priceOriginal) ?></div>
          <span class="discount-pill">Hemat <?= rupiah($priceOriginal - $priceFinal) ?></span>
        <?php endif; ?>
      <?php else: ?>
        <div class="price-large">Tanya Harga</div>
      <?php endif; ?>
    </div>

    <div class="stock-status-wrap">
      <span class="stock-badge stock-contact"><span class="badge-dot"></span> Konfirmasi Ketersediaan via WhatsApp</span>
    </div>

    <!-- Spesifikasi Ringkas -->
    <div class="specs-table-wrap">
      <h3 class="specs-heading">Spesifikasi Onderdil</h3>
      <table class="specs-table">
        <tbody>
          <?php if (!empty($product['merk'])): ?>
            <tr><th>Merek Suku Cadang</th><td><?= e($product['merk']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($product['nama_mobil'])): ?>
            <tr><th>Kompatibilitas Kendaraan</th><td><?= e($product['nama_mobil']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($product['nama_lain'])): ?>
            <tr><th>Nama Alternatif / Lain</th><td><?= e($product['nama_lain']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($product['category'])): ?>
            <tr><th>Kategori Sistem</th><td><?= e($product['category']) ?></td></tr>
          <?php endif; ?>
          <tr><th>Kondisi</th><td>Baru (Original / OEM Berkualitas)</td></tr>
        </tbody>
      </table>
    </div>

    <?php if (!empty($product['description'])): ?>
      <div class="product-description-section">
        <h3>Deskripsi Produk</h3>
        <div class="description-text"><?= nl2br(e($product['description'])) ?></div>
      </div>
    <?php endif; ?>

    <!-- Action Buttons: WhatsApp & Marketplace -->
    <div class="product-actions-box">
      <?php if (!empty($cleanWa)): ?>
        <a class="btn btn-primary btn-cta-wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer">
          <span class="wa-btn-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></span>
          <span>Pesan / Tanya via WhatsApp</span>
        </a>
      <?php endif; ?>

      <?php if (!empty($product['marketplaces'])): ?>
        <div class="marketplace-section">
          <p class="marketplace-title">Atau beli lewat Marketplace Resmi:</p>
          <div class="marketplace-buttons">
            <?php foreach ($product['marketplaces'] as $m): ?>
              <?php
                $url = $m['url'];
                $safe = preg_match('#^https?://#i', $url) === 1;
              ?>
              <?php if ($safe): ?>
                <a class="btn btn-marketplace" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">
                  <span>Beli di <?= e($m['name']) ?></span>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<script type="application/ld+json">
<?= json_encode(array_filter([
  '@context' => 'https://schema.org',
  '@type' => 'Product',
  'name' => $product['nama_produk'],
  'image' => !empty($product['images'][0]['path']) ? mediaUrl($product['images'][0]['path']) : null,
  'description' => $pageDescription,
  'sku' => $product['kode_barang'] ?? null,
  'brand' => !empty($product['merk']) ? ['@type' => 'Brand', 'name' => $product['merk']] : null,
  'category' => $product['category'] ?? null,
  'offers' => $priceFinal > 0 ? [
    '@type' => 'Offer',
    'url' => $pageCanonical,
    'priceCurrency' => 'IDR',
    'price' => $priceFinal,
    'itemCondition' => 'https://schema.org/NewCondition',
  ] : null,
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>
<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'BreadcrumbList',
  'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => 'https://cvsrirejekimotor.com/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Katalog Produk', 'item' => 'https://cvsrirejekimotor.com/produk'],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $product['nama_produk'], 'item' => $pageCanonical],
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>

<?php
require __DIR__ . '/../templates/footer.php';

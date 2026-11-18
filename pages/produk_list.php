<?php
$pageTitle = 'Katalog Suku Cadang & Onderdil | CV Sri Rejeki Motor';

$index = cache()->read('products/index.json')['items'] ?? [];
$brands = cache()->read('brands/index.json')['items'] ?? [];
$vehicles = cache()->read('vehicles/index.json')['items'] ?? [];
$categories = cache()->read('categories/index.json')['items'] ?? [];

$q = trim($_GET['q'] ?? '');
$brand = trim($_GET['merk'] ?? '');
$vehicle = trim($_GET['kendaraan'] ?? '');
$category = trim($_GET['kategori'] ?? '');

$hasFilter = ($q !== '' || $brand !== '' || $vehicle !== '' || $category !== '');

$filtered = array_filter($index, function ($p) use ($q, $brand, $vehicle, $category) {
    if ($q !== '') {
        $searchSpace = ($p['nama_produk'] ?? '') . ' ' .
                       ($p['kode_barang'] ?? '') . ' ' .
                       ($p['merk'] ?? '') . ' ' .
                       ($p['nama_mobil'] ?? '') . ' ' .
                       ($p['nama_lain'] ?? '') . ' ' .
                       ($p['category'] ?? '');
        if (stripos($searchSpace, $q) === false) {
            return false;
        }
    }
    if ($brand !== '' && ($p['merk'] ?? '') !== $brand) {
        return false;
    }
    if ($vehicle !== '' && ($p['nama_mobil'] ?? '') !== $vehicle) {
        return false;
    }
    if ($category !== '' && ($p['category'] ?? '') !== $category) {
        return false;
    }
    return true;
});

require __DIR__ . '/../templates/header.php';
?>
<section class="container catalog reveal">
  <div class="catalog-head">
    <div>
      <span class="section-eyebrow">Pusat Suku Cadang Mobil</span>
      <h1><span class="section-marker"></span>Katalog Produk</h1>
    </div>
    <div class="result-badge">
      <span><?= count($filtered) ?></span> produk ditemukan
    </div>
  </div>

  <div class="filter-card">
    <form class="filter-bar" method="get" action="/produk">
      <div class="filter-field filter-field-search">
        <label for="searchQuery" class="sr-only">Cari Produk</label>
        <input type="text" id="searchQuery" name="q" placeholder="Cari nama barang, kode part (PN), merek, atau tipe mobil..." value="<?= e($q) ?>">
      </div>

      <div class="filter-controls">
        <?php if ($brands): ?>
        <select name="merk" aria-label="Filter Merek">
          <option value="">Semua Merek</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= e($b) ?>" <?= $brand === $b ? 'selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <?php if ($vehicles): ?>
        <select name="kendaraan" aria-label="Filter Kendaraan">
          <option value="">Semua Kendaraan</option>
          <?php foreach ($vehicles as $v): ?>
            <option value="<?= e($v) ?>" <?= $vehicle === $v ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <?php if ($categories): ?>
        <select name="kategori" aria-label="Filter Kategori">
          <option value="">Semua Kategori</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-filter">Terapkan Filter</button>
        <?php if ($hasFilter): ?>
          <a href="/produk" class="btn btn-outline btn-filter-reset">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?php if (!empty($filtered)): ?>
    <div class="product-grid">
      <?php $i = 0; foreach ($filtered as $p): $i++; ?>
        <?php
          $priceFinal = $p['harga_final'] ?? $p['harga_jual'] ?? 0;
          $priceOriginal = $p['harga_jual'] ?? 0;
          $hasDiscount = !empty($p['harga_final']) && $p['harga_final'] < $priceOriginal;
        ?>
        <a class="product-card reveal" style="--reveal-delay: <?= min($i % 8, 7) * 0.04 ?>s" href="/produk/<?= e($p['slug']) ?>">
          <div class="product-card-image">
            <?php if (!empty($p['primary_image'])): ?>
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
              <?php if (!empty($p['nama_mobil'])): ?><span class="meta-car">· <?= e($p['nama_mobil']) ?></span><?php endif; ?>
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
  <?php else: ?>
    <div class="catalog-empty-state reveal">
      <div class="empty-state-plate">
        <span class="empty-state-icon" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
        <h3>Produk Tidak Ditemukan</h3>
        <p>Tidak ada onderdil yang cocok dengan kata kunci atau filter yang Anda pilih.</p>
        <?php if ($hasFilter): ?>
          <a href="/produk" class="btn btn-primary" style="margin-top: 16px;">Lihat Semua Produk</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php
require __DIR__ . '/../templates/footer.php';

<?php
$pageTitle = 'Katalog Suku Cadang & Onderdil | CV Sri Rejeki Motor';

// SEO: canonical + meta dinamis per filter; hasil pencarian internal tidak diindeks
$pageCanonical = 'https://cvsrirejekimotor.com/produk';

// Nomor WhatsApp untuk tombol chat di kartu produk
$company = cache()->read('company/profile.json') ?? [];
$cleanWa = !empty($company['whatsapp']) ? preg_replace('/[^0-9]/', '', $company['whatsapp']) : '';
if (str_starts_with($cleanWa, '0')) {
    $cleanWa = '62' . substr($cleanWa, 1);
}

$index = cache()->read('products/index.json')['items'] ?? [];
foreach ($index as &$p) { $p['nama_produk'] = displayProductName($p['nama_produk'] ?? null); }
unset($p);
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

// SEO: halaman hasil pencarian internal jangan diindeks, tapi ikuti link-nya
if ($q !== '') {
    $pageRobots = 'noindex,follow';
} elseif ($brand !== '' || $category !== '' || $vehicle !== '') {
    $bits = array_filter([$brand, $category, $vehicle]);
    $pageTitle = 'Suku Cadang ' . implode(' ', $bits) . ' | CV Sri Rejeki Motor';
    $pageDescription = 'Jual suku cadang mobil ' . implode(' ', $bits)
        . ' — onderdil original/OEM, harga bersaing, kirim ke seluruh Indonesia. Tanya stok via WhatsApp.';
}

// Paginasi: 24 produk per halaman (1.000 kartu sekaligus terlalu berat)
$totalFiltered = count($filtered);
$perPage = 24;
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
$page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$filtered = array_slice(array_values($filtered), ($page - 1) * $perPage, $perPage);

require __DIR__ . '/../templates/header.php';
?>
<section class="container catalog reveal">
  <div class="catalog-head">
    <div>
      <span class="section-eyebrow">Pusat Suku Cadang Mobil</span>
      <h1><span class="section-marker"></span>Katalog Produk</h1>
    </div>
    <div class="result-badge">
      <span><?= $totalFiltered ?></span> produk ditemukan
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
          $cardWaText = "Halo CV Sri Rejeki Motor, saya tertarik dengan suku cadang:\n"
              . '*' . $p['nama_produk'] . "*\n"
              . (!empty($p['kode_barang']) ? 'Kode Part (PN): ' . $p['kode_barang'] . "\n" : '')
              . ($priceFinal > 0 ? '' : "Berapa harga barang ini?\n")
              . "Apakah barang ini masih tersedia?\n"
              . 'Link: https://cvsrirejekimotor.com/produk/' . $p['slug'];
          $cardWaUrl = $cleanWa !== '' ? 'https://wa.me/' . $cleanWa . '?text=' . urlencode($cardWaText) : '';
        ?>
        <article class="product-card reveal" style="--reveal-delay: <?= min($i % 8, 7) * 0.04 ?>s">
          <a class="product-card-image" href="/produk/<?= e($p['slug']) ?>" aria-label="<?= e($p['nama_produk']) ?>">
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
          </a>
          <div class="product-card-body">
            <p class="part-code"><?= !empty($p['kode_barang']) ? 'PN. ' . e($p['kode_barang']) : '&nbsp;' ?></p>
            <h3><a href="/produk/<?= e($p['slug']) ?>"><?= e($p['nama_produk']) ?></a></h3>
            <p class="meta">
              <?php if (!empty($p['merk'])): ?><span class="meta-brand"><?= e($p['merk']) ?></span><?php endif; ?>
              <?php if (!empty($p['nama_mobil'])): ?><span class="meta-car">· <?= e($p['nama_mobil']) ?></span><?php endif; ?>
            </p>
            <div class="price-wrap">
              <?php if ($priceFinal > 0): ?>
                <span class="price"><?= rupiah($priceFinal) ?></span>
                <?php if ($hasDiscount): ?>
                  <span class="price-original"><?= rupiah($priceOriginal) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="price">Tanya Harga</span>
              <?php endif; ?>
            </div>
            <div class="product-card-actions">
              <a class="btn btn-outline btn-card" href="/produk/<?= e($p['slug']) ?>">Detail Barang</a>
              <?php if ($cardWaUrl !== ''): ?>
                <a class="btn btn-primary btn-card" href="<?= e($cardWaUrl) ?>" target="_blank" rel="noopener noreferrer">Chat WhatsApp</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Navigasi halaman katalog">
        <?php if ($page > 1): ?>
          <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">&larr; Sebelumnya</a>
        <?php endif; ?>
        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
          <?php if ($pg === $page): ?>
            <span class="page-link is-current" aria-current="page"><?= $pg ?></span>
          <?php elseif ($pg === 1 || $pg === $totalPages || abs($pg - $page) <= 2): ?>
            <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $pg]))) ?>"><?= $pg ?></a>
          <?php elseif ($pg === $page - 3 || $pg === $page + 3): ?>
            <span class="page-ellipsis">&hellip;</span>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a class="page-link" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Berikutnya &rarr;</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
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

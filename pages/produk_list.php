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

// Pencarian fleksibel: query dipecah jadi kata-kata, tiap kata dinormalisasi
// (huruf kecil + buang semua non-alfanumerik). Jadi "4875079030" menemukan
// "48750-79030", dan "baut roda canter" cocok bila tiap katanya ada di salah
// satu field (kode/nama/mobil/merk) — tidak harus berurutan dalam satu field.
$queryWords = [];
if ($q !== '') {
    foreach (preg_split('/\s+/', $q) as $w) {
        $nw = preg_replace('/[^a-z0-9]/', '', strtolower((string) $w));
        if ($nw !== '') {
            $queryWords[] = $nw;
        }
    }
}

$hasFilter = ($q !== '' || $brand !== '' || $vehicle !== '' || $category !== '');

$filtered = array_filter($index, function ($p) use ($queryWords, $brand, $vehicle, $category) {
    if (!empty($queryWords)) {
        // Ruang pencarian = gabungan 4 field utama (+ nama_lain/kategori +
        // varian), dinormalisasi dengan cara yang sama seperti kata kunci.
        $searchSpace = ($p['nama_produk'] ?? '') . ' ' .
                       ($p['kode_barang'] ?? '') . ' ' .
                       ($p['merk'] ?? '') . ' ' .
                       ($p['nama_mobil'] ?? '') . ' ' .
                       ($p['nama_lain'] ?? '') . ' ' .
                       ($p['category'] ?? '');
        if (!empty($p['variants'])) {
            foreach ($p['variants'] as $v) {
                $searchSpace .= ' ' . ($v['kode_barang'] ?? '') . ' ' . ($v['merk'] ?? '') . ' ' . ($v['nama_produk'] ?? '') . ' ' . ($v['nama_mobil'] ?? '');
            }
        }
        $searchSpace = preg_replace('/[^a-z0-9]/', '', strtolower($searchSpace));
        foreach ($queryWords as $nw) {
            if (strpos($searchSpace, $nw) === false) {
                return false;
            }
        }
    }
    if ($brand !== '') {
        $brandMatch = ($p['merk'] ?? '') === $brand;
        if (!$brandMatch && !empty($p['variants'])) {
            foreach ($p['variants'] as $v) {
                if (($v['merk'] ?? '') === $brand) {
                    $brandMatch = true;
                    break;
                }
            }
        }
        if (!$brandMatch) {
            return false;
        }
    }
    if ($vehicle !== '') {
        // nama_mobil berisi daftar kendaraan dipisah koma — cocok bila
        // kendaraan yang dipilih adalah SALAH SATU dari daftar itu (bukan
        // harus sama persis dengan seluruh string).
        $vehList = array_map('trim', explode(',', (string) ($p['nama_mobil'] ?? '')));
        if (!in_array($vehicle, $vehList, true)) {
            return false;
        }
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

// Urutan tampil: produk BERGAMBAR dulu, lalu yang belum bergambar; di dalam
// tiap kelompok urutan DIACAK (seed = tanggal + filter, supaya paginasi
// stabil dalam sehari tapi urutan berubah tiap hari — katalog terlihat segar
// dan bukan urutan yang itu-itu saja).
$filtered = array_values($filtered);
$withPhoto = [];
$withoutPhoto = [];
foreach ($filtered as $p) {
    $hasPhoto = !empty($p['primary_image']) || !empty($p['images']);
    if ($hasPhoto) {
        $withPhoto[] = $p;
    } else {
        $withoutPhoto[] = $p;
    }
}
$shuffleSeed = (int) crc32(date('Y-m-d') . '|' . $q . '|' . $brand . '|' . $vehicle . '|' . $category);
mt_srand($shuffleSeed);
shuffle($withPhoto);
shuffle($withoutPhoto);
$filtered = array_merge($withPhoto, $withoutPhoto);

// Paginasi: 24 produk per halaman (1.000 kartu sekaligus terlalu berat)
$totalFiltered = count($filtered);
$perPage = 24;
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
$page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$filtered = array_slice(array_values($filtered), ($page - 1) * $perPage, $perPage);

// Label opsi dropdown taksonomi: nama resmi (+ singkatan bila belum ada di
// nama) + jumlah produk. Item cache baru berbentuk objek {value, name,
// short_name, count}; cache lama (string polos) tetap didukung.
if (!function_exists('taxonomyLabel')) {
    function taxonomyLabel($item): string
    {
        if (is_string($item)) {
            return $item;
        }
        $label = (string) ($item['name'] ?? $item['value'] ?? '');
        $short = $item['short_name'] ?? null;
        if ($short !== null && $short !== '' && stripos($label, (string) $short) === false) {
            $label .= ' (' . $short . ')';
        }
        if (isset($item['count'])) {
            $label .= ' (' . (int) $item['count'] . ')';
        }
        return $label;
    }
}

// Nama polos opsi taksonomi (tanpa singkatan & jumlah) — dipakai sebagai isi
// input combobox saat sebuah filter sedang aktif.
if (!function_exists('taxonomyName')) {
    function taxonomyName($item): string
    {
        if (is_string($item)) {
            return $item;
        }
        return (string) ($item['name'] ?? $item['value'] ?? '');
    }
}

// Nama opsi yang sedang terpilih (untuk isi awal input combobox).
if (!function_exists('comboSelectedName')) {
    function comboSelectedName($items, $selected): string
    {
        if ($selected === '' || $selected === null || !$items) {
            return '';
        }
        foreach ($items as $it) {
            $v = is_array($it) ? (string) ($it['value'] ?? '') : (string) $it;
            if ($v === (string) $selected) {
                return taxonomyName($it);
            }
        }
        return '';
    }
}

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
        <input type="text" id="searchQuery" name="q" placeholder="Cari part, kode, merek, atau mobil..." value="<?= e($q) ?>">
      </div>

      <div class="filter-controls">
        <?php if ($brands): ?>
        <div class="combo" data-combo>
          <input type="text" class="combo-input" placeholder="Cari merek..." autocomplete="off" aria-label="Filter Merek" value="<?= e(comboSelectedName($brands, $brand)) ?>">
          <input type="hidden" name="merk" value="<?= e($brand) ?>">
          <ul class="combo-list" hidden>
            <li data-value="" data-label="">Semua Merek</li>
            <?php foreach ($brands as $b): $bv = is_array($b) ? (string) ($b['value'] ?? '') : (string) $b; ?>
              <li data-value="<?= e($bv) ?>" data-label="<?= e(taxonomyName($b)) ?>"><?= e(taxonomyLabel($b)) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if ($vehicles): ?>
        <div class="combo" data-combo>
          <input type="text" class="combo-input" placeholder="Cari kendaraan..." autocomplete="off" aria-label="Filter Kendaraan" value="<?= e(comboSelectedName($vehicles, $vehicle)) ?>">
          <input type="hidden" name="kendaraan" value="<?= e($vehicle) ?>">
          <ul class="combo-list" hidden>
            <li data-value="" data-label="">Semua Kendaraan</li>
            <?php foreach ($vehicles as $v): $vv = is_array($v) ? (string) ($v['value'] ?? '') : (string) $v; ?>
              <li data-value="<?= e($vv) ?>" data-label="<?= e(taxonomyName($v)) ?>"><?= e(taxonomyLabel($v)) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if ($categories): ?>
        <div class="combo" data-combo>
          <input type="text" class="combo-input" placeholder="Cari kategori..." autocomplete="off" aria-label="Filter Kategori" value="<?= e(comboSelectedName($categories, $category)) ?>">
          <input type="hidden" name="kategori" value="<?= e($category) ?>">
          <ul class="combo-list" hidden>
            <li data-value="" data-label="">Semua Kategori</li>
            <?php foreach ($categories as $c): $cv = is_array($c) ? (string) ($c['value'] ?? '') : (string) $c; ?>
              <li data-value="<?= e($cv) ?>" data-label="<?= e(taxonomyName($c)) ?>"><?= e(taxonomyLabel($c)) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
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
          // Data varian (pilih merk) untuk pill + JS: harga, gambar, PN, WA per merk.
          $variants = $p['variants'] ?? [];
          $hasVariants = count($variants) > 1; // 1 varian = produk tunggal, pill tidak perlu
          $variantData = [];
          foreach ($variants as $v) {
              $vPrice = (int) ($v['harga_jual'] ?? 0);
              $vWaText = "Halo CV Sri Rejeki Motor, saya tertarik dengan suku cadang:\n"
                  . '*' . $p['nama_produk'] . "*\n"
                  . (!empty($v['kode_barang']) ? 'Kode Part (PN): ' . $v['kode_barang'] . "\n" : '')
                  . ($vPrice > 0 ? '' : "Berapa harga barang ini?\n")
                  . "Apakah barang ini masih tersedia?\n"
                  . 'Link: https://cvsrirejekimotor.com/produk/' . $p['slug'];
              $variantData[] = [
                  'kode_barang' => $v['kode_barang'] ?? '',
                  'merk' => $v['merk'] ?? '',
                  'label' => !empty($v['merk']) ? $v['merk'] : ($v['kode_barang'] ?? ''),
                  'price_text' => $vPrice > 0 ? rupiah($vPrice) : 'Tanya Harga',
                  'image_url' => !empty($v['primary_image']) ? mediaUrl($v['primary_image']) : '',
                  'wa_url' => $cleanWa !== '' ? 'https://wa.me/' . $cleanWa . '?text=' . urlencode($vWaText) : '',
              ];
          }
        ?>
        <article class="product-card reveal<?= $hasVariants ? ' has-variants' : '' ?>" style="--reveal-delay: <?= min($i % 8, 7) * 0.04 ?>s">
          <a class="product-card-image" href="/produk/<?= e($p['slug']) ?>" aria-label="<?= e($p['nama_produk']) ?>">
            <?php if (!empty($p['primary_image'])): ?>
            <img class="variant-img" src="<?= e(mediaUrl($p['primary_image'])) ?>" alt="<?= e($p['nama_produk']) ?>" loading="lazy">
            <?php else: ?>
            <img class="variant-img logo-fallback" src="/assets/img/logo.webp" alt="CV Sri Rejeki Motor" loading="lazy">
            <?php endif; ?>

            <?php if ($hasDiscount): ?>
              <span class="card-badge card-badge-promo">PROMO</span>
            <?php elseif (!empty($p['featured'])): ?>
              <span class="card-badge card-badge-featured">UNGGULAN</span>
            <?php endif; ?>
          </a>
          <div class="product-card-body">
            <p class="part-code variant-pn"><?= !empty($p['kode_barang']) ? 'PN. ' . e($p['kode_barang']) : '&nbsp;' ?></p>
            <h3><a href="/produk/<?= e($p['slug']) ?>"><?= e($p['nama_produk']) ?></a></h3>
            <p class="meta">
              <?php if (!empty($p['merk'])): ?><span class="meta-brand variant-merk"><?= e($p['merk']) ?></span><?php endif; ?>
              <?php if (!empty($p['nama_mobil'])): ?><span class="meta-car">· <?= e($p['nama_mobil']) ?></span><?php endif; ?>
            </p>
            <?php if ($hasVariants): ?>
              <div class="variant-pills" role="group" aria-label="Pilih merek">
                <?php foreach ($variantData as $vi => $vd): ?>
                  <button type="button" class="variant-pill<?= $vi === 0 ? ' is-active' : '' ?>"
                    data-kode="<?= e($vd['kode_barang']) ?>"
                    data-merk="<?= e($vd['merk']) ?>"
                    data-price="<?= e($vd['price_text']) ?>"
                    data-image="<?= e($vd['image_url']) ?>"
                    data-wa="<?= e($vd['wa_url']) ?>"><?= e($vd['label']) ?></button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="price-wrap">
              <?php if ($priceFinal > 0): ?>
                <span class="price variant-price"><?= rupiah($priceFinal) ?></span>
                <?php if ($hasDiscount): ?>
                  <span class="price-original"><?= rupiah($priceOriginal) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="price variant-price">Tanya Harga</span>
              <?php endif; ?>
            </div>
            <div class="product-card-actions">
              <a class="btn btn-outline btn-card" href="/produk/<?= e($p['slug']) ?>"><span class="btn-label-desktop">Detail Barang</span><span class="btn-label-mobile">Detail</span></a>
              <?php if ($cardWaUrl !== ''): ?>
                <a class="btn btn-primary btn-card variant-wa" href="<?= e($cardWaUrl) ?>" target="_blank" rel="noopener noreferrer"><span class="btn-label-desktop">Chat WhatsApp</span><span class="btn-label-mobile">Chat WA</span></a>
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
<script>
// Pilih merk (produk gabungan): ganti harga, gambar, PN, merk, dan link WA
// di kartu tanpa reload. Satu listener untuk semua kartu.
document.addEventListener('click', function (e) {
  var pill = e.target.closest('.variant-pill');
  if (!pill) return;
  var card = pill.closest('.product-card');
  if (!card) return;
  card.querySelectorAll('.variant-pill').forEach(function (b) { b.classList.remove('is-active'); });
  pill.classList.add('is-active');
  var kode = pill.getAttribute('data-kode') || '';
  var merk = pill.getAttribute('data-merk') || '';
  var priceText = pill.getAttribute('data-price') || '';
  var imgUrl = pill.getAttribute('data-image') || '';
  var waUrl = pill.getAttribute('data-wa') || '';
  var pn = card.querySelector('.variant-pn');
  if (pn) pn.textContent = kode ? 'PN. ' + kode : '\u00a0';
  var metaBrand = card.querySelector('.variant-merk');
  if (metaBrand) {
    if (merk) { metaBrand.textContent = merk; metaBrand.style.display = ''; }
    else { metaBrand.style.display = 'none'; }
  }
  var priceEl = card.querySelector('.variant-price');
  if (priceEl) priceEl.textContent = priceText;
  var img = card.querySelector('.variant-img');
  if (img) {
    if (imgUrl) { img.src = imgUrl; img.classList.remove('logo-fallback'); }
    else { img.src = '/assets/img/logo.webp'; img.classList.add('logo-fallback'); }
    img.style.display = '';
  }
  var wa = card.querySelector('.variant-wa');
  if (wa && waUrl) wa.href = waUrl;
});
</script>
<?php
require __DIR__ . '/../templates/footer.php';

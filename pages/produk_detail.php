<?php
$product = cache()->read("products/{$slug}.json");
if ($product !== null) { $product['nama_produk'] = displayProductName($product['nama_produk'] ?? null); }

if ($product === null) {
    // Slug lama dari rename/hapus (peta kurasi — dicek PERTAMA, diutamakan
    // atas redirects.json yang bisa basi). Termasuk 2 baris duplikat yang
    // dihapus dari DB (Danan 2026-11-19):
    // 8-94223-366-TE dan TEMT-141213 — tetap 301 ke slug kanonis.
    // + 2 baris duplikat rencana merge M8/M9 (disetujui Danan "setuju"):
    // 8-94226-557 -> TE8-94226-557-2; TEMB-109936 -> halaman MB109936.
    $legacyRedirects = [
        'angker-starter-gpme-700136' => 'angker-starter-me700136',
        'angker-starter-gpme-701492' => 'angker-starter-me701492',
        'as-pinion-msd-mb161342' => 'as-pinion-mb161342',
        'as-roda-71-jade1698098425a' => 'as-roda-71-1698098425a',
        'as-roda-dalam-lksni756' => 'as-roda-dalam-ni756',
        'as-roda-luar-lksni904' => 'as-roda-luar-ni904',
        'as-roda-ors-mk499638a' => 'as-roda-ors-mk499638',
        'as-roda-ors-mk499638idn' => 'as-roda-ors-mk499638',
        'as-satelit-71-msd-i-1-41521-035' => 'as-satelit-1-41521-035',
        'as-satelit-i9-41521-044-1' => 'as-satelit-9-41521-044-1',
        'as-satelit-ors-1-41521-035-0' => 'as-satelit-1-41521-035',
        'as-satelit-ors-i6-41521-035-0' => 'as-satelit-1-41521-035',
        'as-templar-ap-atm01-md050103-1utm' => 'as-templar-md050103',
        'as-templar-utm-md050103' => 'as-templar-md050103',
        'ball-joint-lh-msd48069-87501' => 'ball-joint-lh-48069-87501',
        'ball-joint-lh-msd48069-87515' => 'ball-joint-lh-48069-87515',
        'ball-joint-low-555sb-7152' => 'ball-joint-low-sb-7152',
        'ball-joint-low-555sb-t552' => 'ball-joint-low-sb-t552',
        'ball-joint-rh-msd48068-87515' => 'ball-joint-rh-48068-87515',
        'baut-nap-ps-hijau-obohan-gw-mb161068' => 'baut-nap-ps-hijau-obohan-mb161068',
        'baut-pulley-askruk-i8-94340-597-0' => 'baut-pulley-askruk-8-94340-597-0',
        'baut-roda-belakang-kanan-i5-87411-891-1' => 'baut-roda-belakang-kanan-5-87411-891-1',
        'baut-roda-buildup-rr-lh-ibk-m106-rr-lh' => 'baut-roda-buildup-rr-lh-m106-rr-lh',
        'baut-roda-buildup-rr-rh-ibk-m106-rr-rh' => 'baut-roda-buildup-rr-rh-m106-rr-rh',
        'baut-roda-mb301529gw' => 'baut-roda-mb301529',
        'bearing-b20-141c3-nskb20141c3' => 'bearing-b20-141c3-b20141c3',
        'bendix-starter-mit-l-mecm191x68471' => 'bendix-starter-mit-l-m191x68471',
        'bos-bellcrank-te-temb489484' => 'bos-bellcrank-te-mb489484',
        'bos-lower-arm-besar-te-48655-ok010' => 'bos-lower-arm-besar-te-48655-0k010',
        'bos-lower-arm-i8-94223-366-1' => 'bos-lower-arm-8-94223-366-1',
        'bos-lower-arm-kecil-te-48654-ok010' => 'bos-lower-arm-kecil-te-48654-0k010',
        'bos-lower-arm-mb109936marugo' => 'bos-lower-arm-te-mb109936',
        'bos-lower-arm-te-te-mb109936' => 'bos-lower-arm-te-mb109936',
        'bos-stir-t5-extra-ors-48750-79030-000' => 'bos-center-arm-te-48750-79030',
        'bos-upper-arm-te-48632-ok010' => 'bos-upper-arm-te-48632-0k010',
        'bos-upper-arm-troper-te-8-94226-557' => 'bush-arm-upper-te8-94226-557-2',
        'bosh-arm-atas-te8-94408-840-3' => 'bosh-arm-atas-8-94408-840-3',
        'bosh-bellcrank-ors-mb489484' => 'bos-bellcrank-te-mb489484',
        'bosh-klep-tbr-ors-i5-11721-016-0' => 'bosh-klep-tbr-ors-5-11721-016-0',
        'bosh-lower-arm-te-8-94223-366-te' => 'bos-lower-arm-te-8-94223-366te',
        'bosh-lower-arm-te-temt-141213' => 'bos-lower-arm-te-mt141213',
        'bosh-pen-piston-ors-i8-97352-889-0' => 'bosh-pen-piston-ors-8-97352-889-0',
        'bosh-pen-rod-rack-ors-i5-44347-004-0' => 'bosh-pen-rod-rack-ors-5-44347-004-0',
        'bosh-per-panjang-ps-gapmh024000' => 'bosh-per-panjang-ps-sk-mh024000',
        'bosh-per-panjang-ps-gapmw030499' => 'bosh-per-panjang-ps-mw030499',
        'bosh-per-panjang-ps-gwmw030499' => 'bosh-per-panjang-ps-mw030499',
        'bosh-per-panjang-ps-sk-mh024000sk' => 'bosh-per-panjang-ps-sk-mh024000',
        'bosh-per-panjang-ps-wtb-mh024000' => 'bosh-per-panjang-ps-sk-mh024000',
        'bosh-per-pendek-ps-mt160889gw' => 'bosh-per-pendek-ps-mt160889',
        'bosh-sentrik-mb109936' => 'bos-lower-arm-te-mb109936',
        'bosh-upper-arm-i8-94408-840-3' => 'bosh-arm-atas-8-94408-840-3',
        'brake-booster-jdc51300-77310' => 'brake-booster-51300-77310',
        'brake-drum-blk-ors-mk529942idn' => 'brake-drum-blk-ors-mk529942',
        'brake-lining-i6-87870-028-1' => 'brake-lining-5-87870-028-1',
        'brake-pad-04465-0k100' => 'brake-pad-ors-04465-0k100',
        'brake-pad-04465-bz130-008' => 'brake-pad-g-max-04465-bz130',
        'brake-pad-04491-87502-008' => 'brake-pad-04491-87502',
        'brake-pad-avz-fwd-ors-04465bz300' => 'brake-pad-avz-fwd-ors-04465-bz300',
        'brake-pad-euro-2-mkd5091m' => 'brake-pad-euro-2-d5091m',
        'brake-pad-fwd-ors-04465-bz300-001' => 'brake-pad-avz-fwd-ors-04465-bz300',
        'brake-pad-g-max-04465-bz130-001' => 'brake-pad-g-max-04465-bz130',
        'brake-pad-mkd0044' => 'brake-pad-d0044',
        'brake-pad-mkd5070m' => 'brake-pad-d5070m',
        'brake-pad-mkd5168m' => 'brake-pad-d5168m',
        'brake-pad-mkd6168' => 'brake-pad-d6168',
        'brake-pad-mkd9019m' => 'brake-pad-d9019m',
        'brake-pad-mkk-mkkd4029m' => 'brake-pad-mkk-d4029m',
        'brake-pad-new-g-1-8-mkd1250m' => 'brake-pad-new-g-1-8-d1250m',
        'brake-pad-old-1-5-mkd1288m' => 'brake-pad-old-1-5-d1288m',
        'brake-pad-old-fr-mkd5073m' => 'brake-pad-old-fr-d5073m',
        'brake-pad-old-glx-frt-mkd6014' => 'brake-pad-old-glx-frt-d6014',
        'brake-pad-old-mkd0051m' => 'brake-pad-old-d0051m',
        'brake-pad-old-mkd2260m' => 'brake-pad-old-d2260m',
        'brake-pad-old-old-mkd225201' => 'brake-pad-old-old-d225201',
        'brake-pad-ors-044650k100' => 'brake-pad-ors-04465-0k100',
        'brake-pad-reborn-mkd2355' => 'brake-pad-reborn-d2355',
        'brake-shoe-04495-87601-008' => 'brake-shoe-04495-87601',
        'brake-shoe-04495-bz010-008' => 'brake-shoe-blk-04495-bz010',
        'brake-shoe-04495-bz100-008' => 'brake-shoe-blk-g-max-04495-bz100',
        'brake-shoe-044950k040' => 'brake-shoe-04495-0k040',
        'brake-shoe-blk-04495-0k040' => 'brake-shoe-04495-0k040',
        'brake-shoe-blk-6-878322550' => 'brake-shoe-blk-6-87832-255-0',
        'brake-shoe-blk-g-max-04495-bz100-001' => 'brake-shoe-blk-g-max-04495-bz100',
        'brake-shoe-blk-ors-mc837547fp' => 'brake-shoe-blk-ors-mc837547',
        'brake-shoe-blk-reborn-04495-ok140' => 'brake-shoe-blk-reborn-04495-0k140',
        'brake-shoe-dpn-47410-87507' => 'brake-shoe-front-47410-87507',
        'brake-shoe-fr-mkk6618a' => 'brake-shoe-fr-k6618a',
        'brake-shoe-fr-ors-mc886180fp' => 'brake-shoe-fr-ors-mc886180',
        'brake-shoe-front-47410-87507-008' => 'brake-shoe-front-47410-87507',
        'brake-shoe-gt-ferosa-04495-87601' => 'brake-shoe-04495-87601',
        'brake-shoe-i6-87832-255-0' => 'brake-shoe-blk-6-87832-255-0',
        'brake-shoe-mkk1264' => 'brake-shoe-k1264',
        'brake-shoe-mkk2367' => 'brake-shoe-k2367',
        'brake-shoe-mkk2411' => 'brake-shoe-k2411',
        'brake-shoe-mkk4434' => 'brake-shoe-k4434',
        'brake-shoe-mkk4452' => 'brake-shoe-k4452',
        'brake-shoe-mkk5547' => 'brake-shoe-k5547',
        'brake-shoe-mkk6664' => 'brake-shoe-k6664',
        'brake-shoe-mkk6741' => 'brake-shoe-k6741',
        'brake-shoe-mkk9003' => 'brake-shoe-k9003',
        'brake-shoe-mkk9910' => 'brake-shoe-k9910',
        'brake-shoe-reborn-mkk2427' => 'brake-shoe-reborn-k2427',
        'brake-shoe-reborn-ors-044950k140' => 'brake-shoe-blk-reborn-04495-0k140',
        'brake-shoe-rr-mkk0021' => 'brake-shoe-rr-k0021',
        'bumper-bemper-depan-2019-52119-bzn60-001' => 'bumper-bemper-depan-2019-ors-52119-bzn60',
        'bumper-bemper-depan-2019-ors-52119bzn60' => 'bumper-bemper-depan-2019-ors-52119-bzn60',
        'bumper-bemper-depan-vvti-601-52119-bz330-001' => 'bumper-bemper-depan-vvti-601-52119-bz330',
        'bumper-depan-ors-52119bz330' => 'bumper-bemper-depan-vvti-601-52119-bz330',
        'bush-arm-lower-temb-109936' => 'bosh-sentrik-mb109936',
        'bush-arm-upper-te8-94226-557-2' => 'bush-arm-upper-8-94226-557-2',
        'bush-klep-ex-msd-me031888' => 'bush-klep-ex-me031888',
        'busi-avz-fwd-sc16hr11-ors-9004a91068' => 'busi-sc16hr11-grand-new-9004a-91068',
        'busi-bkr6e-ngkbkr6e' => 'busi-bkr6e-bkr6e',
        'busi-bkr6e11-ngkbkr6e11' => 'busi-bkr6e11-bkr6e11',
        'busi-bp5es-ngkbp5es' => 'busi-bp5es-bp5es',
        'busi-bp5es11-ngkbp5es11' => 'busi-bp5es11-bp5es11',
        'busi-dcpr7ea9-ngkdcpr7ea9' => 'busi-dcpr7ea9-dcpr7ea9',
        'busi-kf-w16ex-u-9091901059' => 'busi-kf-w16ex-u-90919-01059',
        'busi-lzfr5bi-11-ngklzfr5bi-11' => 'busi-lzfr5bi-11-lzfr5bi-11',
        'busi-ngkkr6a10' => 'busi-kr6a10',
        'busi-sc16hr11-grand-new-9004a-91068-001' => 'busi-sc16hr11-grand-new-9004a-91068',
        'busi-xu22pr9-densoxu22pr9' => 'busi-xu22pr9-xu22pr9',
        'cartridge-turbo-125-ht-24v-jade-17201-e0080' => 'cartridge-turbo-125-ht-24v-17201-e0080',
        'cartridge-turbo-jade-me212182' => 'cartridge-turbo-me212182',
        'catridge-turbo-130-ht12v-jade17201-e0680' => 'catridge-turbo-130-ht12v-17201-e0680',
        'co-assy-bensin-31470-ok020' => 'co-assy-bensin-31470-0k020',
        'co-assy-bensin-reborn-31470-ok090' => 'co-assy-bensin-reborn-31470-0k090',
        'co-assy-diesel-31470-ok040' => 'co-assy-diesel-31470-0k040',
        'co-assy-reborn-diesel-31470-ok080' => 'co-assy-reborn-diesel-31470-0k080',
        'master-rem-bm-assy-reborn-matic-47201-ok240' => 'master-rem-bm-assy-reborn-matic-47201-0k240',
        'master-kopling-bawah-co-assy-1-5-snc30620-00q0e' => 'master-kopling-bawah-co-assy-1-5-30620-00q0e',
        'master-kopling-atas-cm-assy-14b-snc31420-26020' => 'master-kopling-atas-cm-assy-14b-31420-26020',
        'master-kopling-atas-cm-assy-dina-saurus-snc31420-37141' => 'master-kopling-atas-cm-assy-31420-37141',
        'busi-k20hru11-ors-90919t1004' => 'busi-k20hru11-90919-t1004',
        'busi-k20hru11-k20hru11denso' => 'busi-k20hru11-90919-t1004',
        'master-kopling-atas-cm-assy-dina-saurus-31420-37141' => 'master-kopling-atas-cm-assy-31420-37141',
        'master-kopling-atas-cm-assy-sncmc113030' => 'master-kopling-atas-cm-assy-mc113030',
        'master-kopling-atas-cm-assy-sncme507832' => 'master-kopling-atas-cm-assy-ors-me507832',
        'master-kopling-bawah-co-assy-sncme601290' => 'master-kopling-bawah-co-assy-me601290',
    ];
    if (!empty($legacyRedirects[$slug])) {
        header('Location: /produk/' . $legacyRedirects[$slug], true, 301);
        exit;
    }
    // Slug lama dari varian yang sudah digabung -> redirect 301 ke slug kanonis.
    // Dicek SETELAH legacy: redirects.json bisa basi sesudah rename massal
    // (entri lama menunjuk slug kanonis lama yang kini 404); peta legacy
    // yang dikurasi selalu benar dan diutamakan.
    $redirects = cache()->read('products/redirects.json')['redirects'] ?? [];
    if (!empty($redirects[$slug])) {
        header('Location: /produk/' . $redirects[$slug], true, 301);
        exit;
    }
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

// Data varian (pilih merk) untuk pill + JS: harga, galeri, PN, merk, WA per merk.
$variants = $product['variants'] ?? [];
$hasVariants = count($variants) > 1; // 1 varian = produk tunggal, pill tidak perlu
$variantData = [];
foreach ($variants as $v) {
    $vPrice = (int) ($v['harga_jual'] ?? 0);
    $vImgs = [];
    foreach (($v['images'] ?? []) as $im) {
        if (!empty($im['path'])) {
            $vImgs[] = mediaUrl($im['path']);
        }
    }
    $vWaText = "Halo CV Sri Rejeki Motor, saya tertarik dengan suku cadang:\n"
        . "*{$product['nama_produk']}*\n"
        . (!empty($v['kode_barang']) ? "Kode Part (PN): {$v['kode_barang']}\n" : '')
        . (!empty($v['merk']) ? "Merek: {$v['merk']}\n" : '')
        . ($vPrice > 0 ? "Harga: " . rupiah($vPrice) . "\n\n" : '')
        . ($vPrice > 0 ? "Apakah barang ini masih tersedia dan cocok untuk kendaraan saya?\n" : "Berapa harga barang ini? Apakah masih tersedia dan cocok untuk kendaraan saya?\n")
        . "Link: {$currentUrl}";
    $variantData[] = [
        'kode_barang' => $v['kode_barang'] ?? '',
        'merk' => $v['merk'] ?? '',
        'label' => !empty($v['merk']) ? $v['merk'] : ($v['kode_barang'] ?? ''),
        'harga_jual' => $vPrice,
        'price_text' => $vPrice > 0 ? rupiah($vPrice) : 'Tanya Harga',
        'images' => $vImgs,
        'wa_url' => $cleanWa !== '' ? "https://wa.me/{$cleanWa}?text=" . urlencode($vWaText) : '',
    ];
}

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
    <div id="variantGallery">
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
      <div class="gallery-main">
        <img class="logo-fallback" src="/assets/img/logo.webp" alt="CV Sri Rejeki Motor" fetchpriority="high">
      </div>
    <?php endif; ?>
    </div>

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
        <button type="button" class="btn-copy-pn" id="copyPnBtn" data-kode="<?= e($product['kode_barang']) ?>" onclick="navigator.clipboard.writeText(this.getAttribute('data-kode')); this.innerText='Tersalin!'; setTimeout(()=>this.innerText='Salin PN', 2000);" title="Salin Kode Part">
          Salin PN
        </button>
      </div>
    <?php endif; ?>

    <?php if ($hasVariants): ?>
      <div class="variant-pills variant-pills-detail" role="group" aria-label="Pilih merek">
        <?php foreach ($variantData as $vi => $vd): ?>
          <button type="button" class="variant-pill<?= $vi === 0 ? ' is-active' : '' ?>" data-variant="<?= $vi ?>"><?= e($vd['label']) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="price-box">
      <?php if ($priceFinal > 0): ?>
        <div class="price-large" id="variantPrice"><?= rupiah($priceFinal) ?></div>
        <?php if ($hasDiscount): ?>
          <div class="price-original-large"><?= rupiah($priceOriginal) ?></div>
          <span class="discount-pill">Hemat <?= rupiah($priceOriginal - $priceFinal) ?></span>
        <?php endif; ?>
      <?php else: ?>
        <div class="price-large" id="variantPrice">Tanya Harga</div>
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
            <tr id="specMerkRow"><th>Merek Suku Cadang</th><td id="variantMerk"><?= e($product['merk']) ?></td></tr>
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
        <a class="btn btn-primary btn-cta-wa" id="variantWa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer">
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

<?php
// Produk Terkait: merk atau kategori sama (maks. 4), bukan produk ini sendiri
$related = [];
$allProducts = cache()->read('products/index.json')['items'] ?? [];
foreach ($allProducts as &$p) { $p['nama_produk'] = displayProductName($p['nama_produk'] ?? null); }
unset($p);
foreach ($allProducts as $rp) {
    if (($rp['slug'] ?? '') === $product['slug']) {
        continue;
    }
    $sameMerk = !empty($product['merk']) && !empty($rp['merk']) && $rp['merk'] === $product['merk'];
    $sameCat = !empty($product['category']) && !empty($rp['category']) && $rp['category'] === $product['category'];
    if ($sameMerk || $sameCat) {
        $related[] = $rp;
    }
    if (count($related) >= 4) {
        break;
    }
}
?>
<?php if (!empty($related)): ?>
<section class="container related-products reveal">
  <div class="section-head">
    <div>
      <span class="section-eyebrow">Mungkin Anda Butuhkan Juga</span>
      <h2>Produk Terkait</h2>
    </div>
    <a href="/produk" class="section-link-all">Lihat Semua &rarr;</a>
  </div>
  <div class="product-grid">
    <?php foreach ($related as $i => $p): ?>
      <?php
        $relPrice = $p['harga_final'] ?? $p['harga_jual'] ?? 0;
        $relWaText = "Halo CV Sri Rejeki Motor, saya tertarik dengan suku cadang:\n"
            . '*' . $p['nama_produk'] . "*\n"
            . (!empty($p['kode_barang']) ? 'Kode Part (PN): ' . $p['kode_barang'] . "\n" : '')
            . ($relPrice > 0 ? '' : "Berapa harga barang ini?\n")
            . "Apakah barang ini masih tersedia?\n"
            . 'Link: https://cvsrirejekimotor.com/produk/' . $p['slug'];
        $relWaUrl = $cleanWa !== '' ? 'https://wa.me/' . $cleanWa . '?text=' . urlencode($relWaText) : '';
      ?>
      <article class="product-card reveal" style="--reveal-delay: <?= min($i, 7) * 0.05 ?>s">
        <a class="product-card-image" href="/produk/<?= e($p['slug']) ?>" aria-label="<?= e($p['nama_produk']) ?>">
          <?php if (!empty($p['primary_image'])): ?>
            <img src="<?= e(mediaUrl($p['primary_image'])) ?>" alt="<?= e($p['nama_produk']) ?>" loading="lazy">
          <?php else: ?>
            <img class="logo-fallback" src="/assets/img/logo.webp" alt="CV Sri Rejeki Motor" loading="lazy">
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
            <?php if ($relPrice > 0): ?>
              <span class="price"><?= rupiah($relPrice) ?></span>
            <?php else: ?>
              <span class="price">Tanya Harga</span>
            <?php endif; ?>
          </div>
          <div class="product-card-actions">
            <a class="btn btn-outline btn-card" href="/produk/<?= e($p['slug']) ?>">Detail Barang</a>
            <?php if ($relWaUrl !== ''): ?>
              <a class="btn btn-primary btn-card" href="<?= e($relWaUrl) ?>" target="_blank" rel="noopener noreferrer">Chat WhatsApp</a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

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

<?php if ($hasVariants): ?>
<script>
var VARIANT_DATA = <?= json_encode($variantData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
var PRODUCT_NAME = <?= json_encode($product['nama_produk'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
// Pilih merk (produk gabungan): ganti harga, galeri, PN, merk, dan link WA.
document.addEventListener('click', function (e) {
  var pill = e.target.closest('.variant-pill');
  if (!pill) return;
  var idx = parseInt(pill.getAttribute('data-variant') || '0', 10);
  var v = VARIANT_DATA[idx];
  if (!v) return;
  document.querySelectorAll('.variant-pill').forEach(function (b) { b.classList.remove('is-active'); });
  pill.classList.add('is-active');
  // PN + tombol salin
  var pn = document.getElementById('pnCode');
  if (pn) pn.textContent = v.kode_barang;
  var copyBtn = document.getElementById('copyPnBtn');
  if (copyBtn) copyBtn.setAttribute('data-kode', v.kode_barang);
  // Harga
  var priceEl = document.getElementById('variantPrice');
  if (priceEl) priceEl.textContent = v.price_text;
  // Merk di spek
  var merkRow = document.getElementById('specMerkRow');
  var merkTd = document.getElementById('variantMerk');
  if (merkRow && merkTd) {
    if (v.merk) { merkTd.textContent = v.merk; merkRow.style.display = ''; }
    else { merkRow.style.display = 'none'; }
  }
  // Galeri
  var gal = document.getElementById('variantGallery');
  if (gal) {
    if (v.images && v.images.length > 0) {
      var html = '<div class="gallery-main"><img id="mainImage" src="' + v.images[0] + '" alt="' + PRODUCT_NAME.replace(/"/g, '&quot;') + '" fetchpriority="high"></div>';
      if (v.images.length > 1) {
        html += '<div class="product-gallery-thumbs">';
        v.images.forEach(function (u, i) {
          html += '<button type="button" class="thumb-btn' + (i === 0 ? ' is-active' : '') + '" onclick="document.getElementById(\'mainImage\').src=\'' + u + '\'; document.querySelectorAll(\'.thumb-btn\').forEach(b => b.classList.remove(\'is-active\')); this.classList.add(\'is-active\');" aria-label="Foto produk ' + (i + 1) + '"><img src="' + u + '" alt="" loading="lazy"></button>';
        });
        html += '</div>';
      }
      gal.innerHTML = html;
    } else {
      gal.innerHTML = '<div class="gallery-main"><img class="logo-fallback" src="/assets/img/logo.webp" alt="CV Sri Rejeki Motor"></div>';
    }
  }
  // WA
  var wa = document.getElementById('variantWa');
  if (wa && v.wa_url) wa.href = v.wa_url;
});
</script>
<?php endif; ?>

<?php
require __DIR__ . '/../templates/footer.php';

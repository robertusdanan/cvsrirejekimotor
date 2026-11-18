<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = supabaseWriter();
$products = $sw->getProducts();

$totalProduk = count($products);
$totalStok = 0;
$produkFeatured = 0;

foreach ($products as $p) {
    $totalStok += (float) ($p['stok'] ?? 0);
    if (!empty($p['featured'])) {
        $produkFeatured++;
    }
}

// Status kesegaran cache (2026-11-18): bandingkan data LIVE (Supabase)
// dengan yang tersimpan di cache publik. Bila angkanya sama -> cache segar;
// bila beda -> cache basi dan perlu Refresh. Angka live dihitung dari
// $products yang sudah dimuat di atas (tanpa query tambahan).
$liveWithImages = 0;
foreach ($products as $p) {
    if (!empty($p['product_images'])) {
        $liveWithImages++;
    }
}
$cacheIndex = cache()->read('products/index.json');
$cacheItems = is_array($cacheIndex['items'] ?? null) ? $cacheIndex['items'] : [];
$cacheWithImages = 0;
foreach ($cacheItems as $ci) {
    if (!empty($ci['primary_image'])) {
        $cacheWithImages++;
    }
}
$cacheMeta = cache()->read('_meta/products.json');
$cacheFresh = (count($cacheItems) === $totalProduk && $cacheWithImages === $liveWithImages);
$cacheUpdatedAt = $cacheMeta['updated_at'] ?? null;

$pageTitle = 'Dashboard Admin';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-800">Selamat Datang di Panel Admin</h1>
    <p class="text-sm text-slate-500">Ringkasan statistik katalog produk dan inventaris toko CV Sri Rejeki Motor.</p>
</div>

<?php if (isset($_GET['cache']) && $_GET['cache'] === 'ok'): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm">
        Cache berhasil diperbarui — situs publik kini menampilkan data terbaru.
    </div>
<?php elseif (isset($_GET['cache']) && $_GET['cache'] === 'fail'): ?>
    <div class="mb-6 rounded-lg bg-amber-50 p-4 border border-amber-200 text-amber-800 text-sm">
        Refresh cache gagal. Cek error_log atau coba lagi beberapa saat lagi.
    </div>
<?php endif; ?>

<div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 mb-8">
    <h2 class="text-lg font-bold text-slate-800 mb-2">Cache Situs Publik</h2>
    <p class="text-sm text-slate-500 mb-4">Perubahan data/gambar di admin seharusnya memperbarui cache otomatis. Bila situs masih menampilkan data lama, tekan tombol ini untuk membangun ulang seluruh cache dari Supabase.</p>
    <?php if ($cacheFresh): ?>
        <div class="mb-4 rounded-lg bg-emerald-50 p-3 border border-emerald-200 text-emerald-800 text-sm">
            <strong>Cache segar.</strong> Live: <?= number_format($totalProduk) ?> produk (<?= number_format($liveWithImages) ?> bergambar) — Cache: <?= number_format(count($cacheItems)) ?> produk (<?= number_format($cacheWithImages) ?> bergambar).<?php if ($cacheUpdatedAt): ?> Terakhir rebuild: <?= htmlspecialchars($cacheUpdatedAt) ?>.<?php endif; ?>
        </div>
    <?php else: ?>
        <div class="mb-4 rounded-lg bg-amber-50 p-3 border border-amber-200 text-amber-800 text-sm">
            <strong>Cache tidak segar!</strong> Live: <?= number_format($totalProduk) ?> produk (<?= number_format($liveWithImages) ?> bergambar) — Cache: <?= number_format(count($cacheItems)) ?> produk (<?= number_format($cacheWithImages) ?> bergambar). Tekan "Refresh Semua Cache" di bawah.
        </div>
    <?php endif; ?>
    <form method="POST" action="/admin/cache_refresh.php">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="resource" value="all">
        <button type="submit" class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Refresh Semua Cache</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 admin-stat-card">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 admin-stat-label">Total Produk</div>
        <div class="mt-2 text-3xl font-extrabold text-slate-900 admin-stat-num"><?= number_format($totalProduk) ?></div>
        <div class="mt-1 text-xs text-slate-500">Tersimpan di Supabase Database</div>
    </div>
    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 admin-stat-card">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 admin-stat-label">Total Stok Fisik</div>
        <div class="mt-2 text-3xl font-extrabold text-amber-600 admin-stat-num"><?= number_format($totalStok) ?></div>
        <div class="mt-1 text-xs text-slate-500">Unit item siap kirim</div>
    </div>
    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 admin-stat-card">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 admin-stat-label">Produk Unggulan</div>
        <div class="mt-2 text-3xl font-extrabold text-indigo-600 admin-stat-num"><?= number_format($produkFeatured) ?></div>
        <div class="mt-1 text-xs text-slate-500">Ditampilkan di Halaman Utama</div>
    </div>
</div>

<div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200">
    <h2 class="text-lg font-bold text-slate-800 mb-4">Akses Cepat Pengelolaan</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/admin/products.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition admin-quick-card">
            <div class="font-semibold text-slate-800">Kelola Produk</div>
            <div class="text-xs text-slate-500 mt-1">Tambah, edit, atau hapus item catalog</div>
        </a>
        <a href="/admin/company_profile.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition admin-quick-card">
            <div class="font-semibold text-slate-800">Profil Perusahaan</div>
            <div class="text-xs text-slate-500 mt-1">Update WhatsApp, alamat & kontak</div>
        </a>
        <a href="/admin/hero.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition admin-quick-card">
            <div class="font-semibold text-slate-800">Banner / Video Hero</div>
            <div class="text-xs text-slate-500 mt-1">Ganti media promo di paling atas</div>
        </a>
        <a href="/admin/marketplaces.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition admin-quick-card">
            <div class="font-semibold text-slate-800">Link Marketplace</div>
            <div class="text-xs text-slate-500 mt-1">Atur link Tokopedia & Shopee</div>
        </a>
    </div>
</div>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

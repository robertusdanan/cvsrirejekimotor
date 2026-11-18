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

$pageTitle = 'Dashboard Admin';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-800">Selamat Datang di Panel Admin</h1>
    <p class="text-sm text-slate-500">Ringkasan statistik katalog produk dan inventaris toko CV Sri Rejeki Motor.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Produk</div>
        <div class="mt-2 text-3xl font-extrabold text-slate-900"><?= number_format($totalProduk) ?></div>
        <div class="mt-1 text-xs text-slate-500">Tersimpan di Supabase Database</div>
    </div>
    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Stok Fisik</div>
        <div class="mt-2 text-3xl font-extrabold text-amber-600"><?= number_format($totalStok) ?></div>
        <div class="mt-1 text-xs text-slate-500">Unit item siap kirim</div>
    </div>
    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Produk Unggulan</div>
        <div class="mt-2 text-3xl font-extrabold text-indigo-600"><?= number_format($produkFeatured) ?></div>
        <div class="mt-1 text-xs text-slate-500">Ditampilkan di Halaman Utama</div>
    </div>
</div>

<div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200">
    <h2 class="text-lg font-bold text-slate-800 mb-4">Akses Cepat Pengelolaan</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/admin/products.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition">
            <div class="font-semibold text-slate-800">Kelola Produk</div>
            <div class="text-xs text-slate-500 mt-1">Tambah, edit, atau hapus item catalog</div>
        </a>
        <a href="/admin/company_profile.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition">
            <div class="font-semibold text-slate-800">Profil Perusahaan</div>
            <div class="text-xs text-slate-500 mt-1">Update WhatsApp, alamat & kontak</div>
        </a>
        <a href="/admin/hero.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition">
            <div class="font-semibold text-slate-800">Banner / Video Hero</div>
            <div class="text-xs text-slate-500 mt-1">Ganti media promo di paling atas</div>
        </a>
        <a href="/admin/marketplaces.php" class="rounded-lg border border-slate-200 p-4 hover:border-amber-500 hover:bg-amber-50/50 transition">
            <div class="font-semibold text-slate-800">Link Marketplace</div>
            <div class="text-xs text-slate-500 mt-1">Atur link Tokopedia & Shopee</div>
        </a>
    </div>
</div>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

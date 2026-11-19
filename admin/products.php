<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = dbWriter();
$q = trim($_GET['q'] ?? '');

// Pagination: maksimal 100 produk per halaman (katalog ~1000 produk).
// Pencarian tetap di server (LIKE di MySQL) sehingga mencari SEMUA produk,
// bukan hanya halaman yang sedang tampil.
$perPage = 100;
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalProducts = $sw->countProducts($q);
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Menghapus produk dari MySQL & Cloudflare R2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $id = trim($_POST['id'] ?? '');
        $kode = trim($_POST['kode'] ?? '');
        $target = $id !== '' ? $id : $kode;

        if ($target !== '') {
            $product = $sw->getProductById($target) ?? $sw->getProductByKode($target);
            if ($product) {
                // Delete image files from Cloudflare R2
                if (!empty($product['product_images']) && is_array($product['product_images'])) {
                    $imgHandler = new ImageHandler();
                    foreach ($product['product_images'] as $img) {
                        if (!empty($img['path'])) {
                            $imgHandler->deleteR2File($img['path']);
                        }
                    }
                }
                // Delete product from MySQL
                $sw->deleteProduct($product['id']);
            }
            // Rebuild cache publik (aman). Bila gagal, beri tahu lewat
            // redirect supaya daftar produk menampilkan warning.
            $cacheOk = safeRebuildAllProducts();
            header('Location: /admin/products.php?deleted=1' . ($cacheOk ? '' : '&cache=stale'));
            exit;
        }
    }
}

$products = $sw->getProducts($q, $perPage, $offset);
$pageTitle = 'Kelola Produk';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Daftar Produk</h1>
        <p class="text-sm text-slate-500">Kelola katalog produk dan harga langsung di MySQL.</p>
    </div>
    <div class="flex flex-wrap gap-2">
    <a href="/admin/products_import.php" class="inline-flex items-center gap-2 rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Import Massal (.xlsx)
    </a>
    <a href="/admin/products_images_import.php" class="inline-flex items-center gap-2 rounded-lg bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        Import Gambar (.zip)
    </a>
    <a href="/admin/product_new.php" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Produk Baru
    </a>
    </div>
</div>

<?php if (isset($_GET['deleted'])): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm">
        Produk berhasil dihapus dari MySQL dan Cloudflare R2!
    </div>
<?php endif; ?>

<?php if (isset($_GET['notfound'])): ?>
    <div class="mb-6 rounded-lg bg-amber-50 p-4 border border-amber-200 text-amber-800 text-sm">
        Produk tidak ditemukan — mungkin sudah dihapus atau tautan kedaluwarsa. Coba muat ulang daftar lalu klik Edit lagi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['cache']) && $_GET['cache'] === 'stale'): ?>
    <div class="mb-6 rounded-lg bg-amber-50 p-4 border border-amber-200 text-amber-800 text-sm">
        Data sudah tersimpan/dihapus, tetapi cache situs belum diperbarui (rebuild gagal). Gunakan "Refresh Semua Cache" di Dashboard.
    </div>
<?php endif; ?>

<div class="mb-6 rounded-xl bg-white p-4 shadow-sm border border-slate-200">
    <form method="GET" class="flex items-center gap-3">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari kode barang, nama produk, atau merk..." class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
        <button type="submit" class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-medium text-white hover:bg-slate-900 transition">Cari</button>
        <?php if ($q !== ''): ?>
            <a href="/admin/products.php" class="text-sm text-slate-500 hover:text-slate-800 underline">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="overflow-hidden rounded-xl bg-white shadow-sm border border-slate-200 admin-table-wrap">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-6 py-3.5 font-semibold">Foto</th>
                <th class="px-6 py-3.5 font-semibold">Kode Barang</th>
                <th class="px-6 py-3.5 font-semibold">Nama Produk</th>
                <th class="px-6 py-3.5 font-semibold">Merk / Mobil</th>
                <th class="px-6 py-3.5 font-semibold">Harga</th>
                <th class="px-6 py-3.5 font-semibold text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-slate-400">
                        Belum ada produk. Klik <strong>Tambah Produk Baru</strong> untuk menambahkan.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php
                    $imgs = is_array($p['product_images'] ?? null) ? $p['product_images'] : [];
                    $primaryImg = null;
                    foreach ($imgs as $i) {
                        if (!empty($i['is_primary'])) {
                            $primaryImg = $i['path'];
                            break;
                        }
                    }
                    if ($primaryImg === null && !empty($imgs[0]['path'])) {
                        $primaryImg = $imgs[0]['path'];
                    }
                    ?>
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="px-6 py-4">
                            <?php if ($primaryImg): ?>
                                <img src="<?= htmlspecialchars(rtrim((string) config('R2_PUBLIC_URL'), '/') . '/' . ltrim($primaryImg, '/')) ?>" loading="lazy" class="h-12 w-12 rounded-lg object-cover border border-slate-200" alt="">
                            <?php else: ?>
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-slate-100 text-slate-400 text-xs font-mono">NO IMG</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 font-mono font-semibold text-slate-900"><?= htmlspecialchars($p['kode_barang']) ?></td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-900"><?= htmlspecialchars($p['nama_produk']) ?></div>
                            <?php if (!empty($p['category'])): ?>
                                <span class="inline-block rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 mt-0.5"><?= htmlspecialchars($p['category']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div><?= htmlspecialchars($p['merk'] ?? '-') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($p['nama_mobil'] ?? '-') ?></div>
                        </td>
                        <td class="px-6 py-4 font-mono font-semibold text-amber-600">Rp <?= number_format((int) ($p['harga_jual'] ?? 0), 0, ',', '.') ?></td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="/admin/product_edit.php?id=<?= urlencode((string) $p['id']) ?>" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition">Edit</a>
                                <form method="POST" onsubmit="return confirm('Yakin hapus produk ini?');" class="inline">
                                    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $p['id']) ?>">
                                    <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <?php
    $from = $totalProducts > 0 ? $offset + 1 : 0;
    $to = min($offset + $perPage, $totalProducts);
    $pageLink = static function (int $p) use ($q): string {
        $qs = $q !== '' ? ('q=' . urlencode($q) . '&') : '';
        return '/admin/products.php?' . $qs . 'page=' . $p;
    };
    // Nomor halaman: tampilkan maksimal 7 (halaman aktif + tetangga + awal/akhir).
    $pages = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2) {
            $pages[] = $i;
        } elseif (end($pages) !== '…') {
            $pages[] = '…';
        }
    }
    ?>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 shadow-sm border border-slate-200">
        <p class="text-sm text-slate-500">
            Menampilkan <strong class="text-slate-800"><?= $from ?>–<?= $to ?></strong> dari
            <strong class="text-slate-800"><?= number_format($totalProducts) ?></strong> produk<?= $q !== '' ? ' untuk pencarian “' . htmlspecialchars($q) . '”' : '' ?>
        </p>
        <nav class="flex items-center gap-1.5" aria-label="Pagination produk">
            <?php if ($page > 1): ?>
                <a href="<?= $pageLink($page - 1) ?>" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition">&laquo; Sebelumnya</a>
            <?php endif; ?>
            <?php foreach ($pages as $pNum): ?>
                <?php if ($pNum === '…'): ?>
                    <span class="px-1.5 text-xs text-slate-400">…</span>
                <?php elseif ($pNum === $page): ?>
                    <span class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white"><?= $pNum ?></span>
                <?php else: ?>
                    <a href="<?= $pageLink($pNum) ?>" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition"><?= $pNum ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= $pageLink($page + 1) ?>" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition">Berikutnya &raquo;</a>
            <?php endif; ?>
        </nav>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

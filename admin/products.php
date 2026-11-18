<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = supabaseWriter();
$q = trim($_GET['q'] ?? '');

// Menghapus produk dari Supabase & Cloudflare R2
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
                // Delete product from Supabase
                $sw->deleteProduct($product['id']);
            }
            // Rebuild public cache
            cacheBuilder()->rebuildProducts();
            header('Location: /admin/products.php?deleted=1');
            exit;
        }
    }
}

$products = $sw->getProducts($q);
$pageTitle = 'Kelola Produk';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Daftar Produk</h1>
        <p class="text-sm text-slate-500">Kelola katalog produk, stok, dan harga langsung di Supabase.</p>
    </div>
    <a href="/admin/product_new.php" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Produk Baru
    </a>
</div>

<?php if (isset($_GET['deleted'])): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm">
        Produk berhasil dihapus dari Supabase dan Cloudflare R2!
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
                <th class="px-6 py-3.5 font-semibold">Stok</th>
                <th class="px-6 py-3.5 font-semibold">Harga</th>
                <th class="px-6 py-3.5 font-semibold text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-10 text-center text-slate-400">
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
                                <img src="<?= htmlspecialchars(rtrim((string) config('R2_PUBLIC_URL'), '/') . '/' . ltrim($primaryImg, '/')) ?>" class="h-12 w-12 rounded-lg object-cover border border-slate-200" alt="">
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
                        <td class="px-6 py-4 font-semibold text-slate-800"><?= number_format((float) ($p['stok'] ?? 0)) ?></td>
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

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = dbWriter();
$flash = null;
$error = null;
$cacheWarning = null;

$id = trim($_GET['id'] ?? $_POST['id'] ?? '');
$product = $id !== '' ? $sw->getProductById($id) : null;

if (!$product) {
    header('Location: /admin/products.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'Sesi tidak valid, silakan coba lagi.';
    } else {
        $nama = trim($_POST['nama_produk'] ?? '');
        if ($nama === '') {
            $error = 'Nama produk wajib diisi.';
        } else {
            $updateData = [
                'id'          => $product['id'],
                'kode_barang' => $product['kode_barang'],
                'nama_produk' => $nama,
                'nama_mobil'  => trim($_POST['nama_mobil'] ?? '') ?: null,
                'nama_lain'   => trim($_POST['nama_lain'] ?? '') ?: null,
                'merk'        => trim($_POST['merk'] ?? '') ?: null,
                'category'    => trim($_POST['category'] ?? '') ?: null,
                'stok'        => (float) ($product['stok'] ?? 0),
                'harga_jual'  => (int) ($_POST['harga_jual'] ?? 0),
                'description' => trim($_POST['description'] ?? '') ?: null,
                'featured'    => isset($_POST['featured']) ? true : false,
                'updated_at'  => date('c'),
            ];

            $sw->upsertProduct($updateData);

            // Handle New Uploaded Images
            $imgHandler = new ImageHandler();
            $existingImages = is_array($product['product_images'] ?? null) ? $product['product_images'] : [];

            // Check deleted images
            $keepPaths = $_POST['keep_images'] ?? [];
            $finalImages = [];

            foreach ($existingImages as $existingImg) {
                if (in_array($existingImg['path'], $keepPaths, true)) {
                    $finalImages[] = [
                        'path'    => $existingImg['path'],
                        'primary' => (bool) ($existingImg['is_primary'] ?? false),
                    ];
                } else {
                    // Delete from R2
                    $imgHandler->deleteR2File($existingImg['path']);
                }
            }

            // Upload additional images
            if (!empty($_FILES['new_images']['name'][0])) {
                $files = $_FILES['new_images'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $singleFile = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];
                        $res = $imgHandler->uploadAndConvertWebP($singleFile, 'products/' . $product['id']);
                        if (!empty($res['path'])) {
                            $finalImages[] = [
                                'path'    => $res['path'],
                                'primary' => empty($finalImages),
                            ];
                        }
                    }
                }
            }

            $sw->syncProductImages($product['id'], $finalImages);

            // Rebuild cache publik (aman: bila rebuild gagal, data tetap
            // tersimpan dan warning ditampilkan — jangan pakai rebuildProducts()
            // langsung di sini, dulu sekali gagal = halaman error + cache lama).
            safeRebuildAllProducts();
            $cacheWarning = $GLOBALS['cacheRebuildWarning'] ?? null;
            $flash = 'Perubahan produk berhasil disimpan!';
            $product = $sw->getProductById($product['id']);
        }
    }
}

$pageTitle = 'Edit Produk: ' . $product['kode_barang'];
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Edit Produk</h1>
        <p class="text-sm text-slate-500 font-mono">Kode: <?= htmlspecialchars($product['kode_barang']) ?></p>
    </div>
    <a href="/admin/products.php" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition">Kembali</a>
</div>

<?php if ($flash): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm font-medium">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<?php if ($cacheWarning): ?>
    <div class="mb-6 rounded-lg bg-amber-50 p-4 border border-amber-200 text-amber-800 text-sm">
        <?= htmlspecialchars($cacheWarning) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 rounded-lg bg-rose-50 p-4 border border-rose-200 text-rose-800 text-sm">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $product['id']) ?>">

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Informasi Utama Produk</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Kode Barang</label>
                <input type="text" disabled value="<?= htmlspecialchars($product['kode_barang']) ?>" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm font-mono text-slate-500 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Produk *</label>
                <input type="text" name="nama_produk" required value="<?= htmlspecialchars($product['nama_produk']) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Peruntukan Mobil</label>
                <input type="text" name="nama_mobil" value="<?= htmlspecialchars($product['nama_mobil'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Lain / Part Number</label>
                <input type="text" name="nama_lain" value="<?= htmlspecialchars($product['nama_lain'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Merk</label>
                <input type="text" name="merk" value="<?= htmlspecialchars($product['merk'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Kategori</label>
                <input type="text" name="category" value="<?= htmlspecialchars($product['category'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Harga Jual (Rp)</label>
                <input type="number" step="100" name="harga_jual" value="<?= htmlspecialchars((string) ($product['harga_jual'] ?? 0)) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Deskripsi Produk</label>
            <textarea name="description" rows="4" class="w-full rounded-lg border border-slate-300 p-3.5 text-sm focus:border-amber-500 focus:ring-amber-500"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <input type="checkbox" id="featured" name="featured" value="1" <?= !empty($product['featured']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
            <label for="featured" class="text-sm font-medium text-slate-800">Tampilkan sebagai Produk Unggulan di Halaman Utama</label>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Foto Produk Saat Ini</h2>

        <?php
        $imgs = is_array($product['product_images'] ?? null) ? $product['product_images'] : [];
        ?>

        <?php if (!empty($imgs)): ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <?php foreach ($imgs as $img): ?>
                    <div class="relative rounded-lg border border-slate-200 p-2 text-center group">
                        <img src="<?= htmlspecialchars(rtrim((string) config('R2_PUBLIC_URL'), '/') . '/' . ltrim($img['path'], '/')) ?>" class="h-28 w-full object-cover rounded-md mb-2" alt="">
                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                            <input type="checkbox" name="keep_images[]" value="<?= htmlspecialchars($img['path']) ?>" checked class="rounded border-slate-300 text-amber-600">
                            Simpan Foto Ini
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-400">Belum ada foto produk.</p>
        <?php endif; ?>

        <div class="pt-2 border-t border-slate-100">
            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Tambah Foto Baru</label>
            <input type="file" name="new_images[]" multiple accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="/admin/products.php" class="rounded-lg bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition">Batal</a>
        <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Simpan Perubahan</button>
    </div>
</form>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

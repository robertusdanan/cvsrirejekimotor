<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = supabaseWriter();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'Sesi tidak valid, silakan coba lagi.';
    } else {
        $kode = trim($_POST['kode_barang'] ?? '');
        $nama = trim($_POST['nama_produk'] ?? '');

        if ($kode === '' || $nama === '') {
            $error = 'Kode barang dan Nama produk wajib diisi.';
        } else {
            // Check existing
            $existing = $sw->getProductByKode($kode);
            if ($existing) {
                $error = "Kode barang '{$kode}' sudah digunakan oleh produk lain.";
            } else {
                // Prepare product data for Supabase
                $prodData = [
                    'kode_barang' => $kode,
                    'nama_produk' => $nama,
                    'nama_mobil'  => trim($_POST['nama_mobil'] ?? '') ?: null,
                    'nama_lain'   => trim($_POST['nama_lain'] ?? '') ?: null,
                    'merk'        => trim($_POST['merk'] ?? '') ?: null,
                    'category'    => trim($_POST['category'] ?? '') ?: null,
                    'stok'        => (float) ($_POST['stok'] ?? 0),
                    'harga_jual'  => (int) ($_POST['harga_jual'] ?? 0),
                    'description' => trim($_POST['description'] ?? '') ?: null,
                    'featured'    => isset($_POST['featured']) ? true : false,
                    'updated_at'  => date('c'),
                ];

                $created = $sw->upsertProduct($prodData);
                if (!$created || empty($created['id'])) {
                    $error = 'Gagal menyimpan produk ke Supabase database.';
                } else {
                    $productId = $created['id'];

                    // Upload images to Cloudflare R2
                    $imgHandler = new ImageHandler();
                    $images = [];

                    if (!empty($_FILES['images']['name'][0])) {
                        $files = $_FILES['images'];
                        $primaryIndex = (int) ($_POST['primary_image_index'] ?? 0);

                        for ($i = 0; $i < count($files['name']); $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $singleFile = [
                                    'name'     => $files['name'][$i],
                                    'type'     => $files['type'][$i],
                                    'tmp_name' => $files['tmp_name'][$i],
                                    'error'    => $files['error'][$i],
                                    'size'     => $files['size'][$i],
                                ];
                                $res = $imgHandler->uploadAndConvertWebP($singleFile, 'products/' . $productId);
                                if (!empty($res['path'])) {
                                    $images[] = [
                                        'path'    => $res['path'],
                                        'primary' => ($i === $primaryIndex),
                                    ];
                                }
                            }
                        }
                    }

                    if (!empty($images)) {
                        $sw->syncProductImages($productId, $images);
                    }

                    // Rebuild cache publik (aman). Bila gagal, beri tahu lewat
                    // redirect supaya daftar produk menampilkan warning.
                    $cacheOk = safeRebuildAllProducts();
                    header('Location: /admin/products.php?success=1' . ($cacheOk ? '' : '&cache=stale'));
                    exit;
                }
            }
        }
    }
}

$pageTitle = 'Tambah Produk Baru';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Tambah Produk Baru</h1>
        <p class="text-sm text-slate-500">Isi data produk di bawah. Gambar akan dikompres & disimpan di Cloudflare R2.</p>
    </div>
    <a href="/admin/products.php" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition">Kembali</a>
</div>

<?php if ($error): ?>
    <div class="mb-6 rounded-lg bg-rose-50 p-4 border border-rose-200 text-rose-800 text-sm">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Informasi Utama Produk</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Kode Barang *</label>
                <input type="text" name="kode_barang" required placeholder="Contoh: SRM-FIL-001" value="<?= htmlspecialchars($_POST['kode_barang'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Produk *</label>
                <input type="text" name="nama_produk" required placeholder="Contoh: Filter Oli Avanza / Xenia" value="<?= htmlspecialchars($_POST['nama_produk'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Peruntukan Mobil</label>
                <input type="text" name="nama_mobil" placeholder="Contoh: Toyota Avanza, Daihatsu Xenia" value="<?= htmlspecialchars($_POST['nama_mobil'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Lain / Part Number</label>
                <input type="text" name="nama_lain" placeholder="Contoh: 15601-BZ010" value="<?= htmlspecialchars($_POST['nama_lain'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Merk</label>
                <input type="text" name="merk" placeholder="Contoh: Toyota Genuine Parts / Denso" value="<?= htmlspecialchars($_POST['merk'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Kategori</label>
                <input type="text" name="category" placeholder="Contoh: Filter, Rem, Mesin, Kelistrikan" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Stok Tersedia</label>
                <input type="number" step="1" name="stok" value="<?= htmlspecialchars($_POST['stok'] ?? '10') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Harga Jual (Rp)</label>
                <input type="number" step="100" name="harga_jual" placeholder="45000" value="<?= htmlspecialchars($_POST['harga_jual'] ?? '0') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 font-mono">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Deskripsi Produk</label>
            <textarea name="description" rows="4" placeholder="Tuliskan spesifikasi, keunggulan, atau catatan pemasangan..." class="w-full rounded-lg border border-slate-300 p-3.5 text-sm focus:border-amber-500 focus:ring-amber-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <input type="checkbox" id="featured" name="featured" value="1" <?= isset($_POST['featured']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
            <label for="featured" class="text-sm font-medium text-slate-800">Tampilkan sebagai Produk Unggulan di Halaman Utama</label>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Foto Produk</h2>
        <div>
            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Upload Foto Produk</label>
            <input type="file" name="images[]" multiple accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="/admin/products.php" class="rounded-lg bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition">Batal</a>
        <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Simpan Produk</button>
    </div>
</form>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

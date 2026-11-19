<?php
/**
 * Import gambar produk massal dari file .zip.
 *
 * Cara pakai:
 *  1. Siapkan file .zip berisi gambar produk (JPG/PNG/WebP, maks 8 MB per file).
 *  2. Nama file HARUS sama dengan Kode Barang produk (tanpa spasi), contoh:
 *       45022-TG1-T00.jpg            -> gambar utama produk 45022-TG1-T00
 *       45022-TG1-T00-2.jpg          -> gambar kedua produk yang sama
 *     Kode Barang yang mengandung "/" ditulis dengan "_" di nama file,
 *     contoh: MSD-FE/PSFR  ->  MSD-FE_PSFR.jpg
 *  3. Klik "Mulai Import Gambar".
 *
 * Aturan:
 *  - Produk yang tidak ditemukan di MySQL dilewati (tercantum di laporan).
 *  - Produk yang SUDAH punya gambar dilewati, KECUALI opsi "Timpa gambar
 *    yang sudah ada" dicentang (gambar lama dihapus dari R2 lalu diganti).
 *  - Gambar dikonversi ke WebP (thumbnail/medium/large/original) dan
 *    di-upload ke R2, lalu cache produk di-rebuild otomatis.
 *  - Gambar HARUS bentuk asli onderdilnya (bukan foto kardus/kemasan).
 */
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = dbWriter();
$report = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        set_time_limit(300);
        try {
            $report = runImagesImport($sw);
        } catch (Throwable $e) {
            $error = 'Import gambar gagal: ' . $e->getMessage();
        }
    }
}

/** @return array<string, mixed> */
function runImagesImport(MySQLWriter $sw): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Ekstensi ZipArchive tidak aktif di server ini.');
    }
    $zipFile = $_FILES['zip'] ?? null;
    if ($zipFile === null || ($zipFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File .zip belum dipilih atau gagal di-upload.');
    }
    $ext = strtolower(pathinfo((string) $zipFile['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        throw new RuntimeException('File harus berekstensi .zip.');
    }
    if ((int) $zipFile['size'] > 50 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file maksimal 50 MB.');
    }
    $replace = !empty($_POST['replace']);

    $tmpDir = sys_get_temp_dir() . '/imgimport_' . bin2hex(random_bytes(6));
    if (!mkdir($tmpDir, 0750, true)) {
        throw new RuntimeException('Tidak bisa membuat folder kerja sementara.');
    }

    // --- Peta produk: KODE (huruf besar) -> produk ---
    $byKode = [];
    foreach ($sw->getProducts() as $p) {
        $kode = trim((string) ($p['kode_barang'] ?? ''));
        if ($kode === '') {
            continue;
        }
        $byKode[strtoupper($kode)] = [
            'id' => (string) $p['id'],
            'kode' => $kode,
            'has_images' => !empty($p['product_images']),
            'images' => is_array($p['product_images'] ?? null) ? $p['product_images'] : [],
        ];
    }

    $stats = [
        'files' => 0, 'matched_products' => 0, 'images_uploaded' => 0,
        'skipped_has_images' => 0, 'unknown' => 0, 'failed' => 0,
    ];
    $unknownKodes = [];
    $errors = [];
    $grouped = []; // product id => [ ['tmp' => path, 'idx' => int, 'name' => file], ... ]

    try {
        $zip = new ZipArchive();
        if ($zip->open((string) $zipFile['tmp_name']) !== true) {
            throw new RuntimeException('File .zip tidak bisa dibuka.');
        }
        $allowedExt = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || substr($name, -1) === '/') {
                continue; // folder
            }
            $base = basename($name);
            if ($base === '' || $base[0] === '.' || stripos($base, '__MACOSX') !== false) {
                continue;
            }
            $fileExt = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (!isset($allowedExt[$fileExt])) {
                continue;
            }
            $stats['files']++;
            $stem = pathinfo($base, PATHINFO_FILENAME);
            $idx = 1;
            // Cari dulu dengan nama file utuh (kode barang bisa berakhiran
            // "-2", contoh TE8-94226-557-2); bila tidak ketemu, baru anggap
            // akhiran -N/_N sebagai nomor urut gambar ke-N.
            $product = findProductByKode($byKode, $stem);
            if ($product === null && preg_match('/[-_](\d+)$/', $stem, $m)) {
                $idx = (int) $m[1];
                $stem = substr($stem, 0, -strlen($m[0]));
                $product = findProductByKode($byKode, $stem);
            }
            if ($product === null) {
                $stats['unknown']++;
                if (count($unknownKodes) < 20) {
                    $unknownKodes[] = $stem;
                }
                continue;
            }
            $extracted = $tmpDir . '/' . $product['id'] . '_' . $idx . '.' . $fileExt;
            if ($zip->extractTo($tmpDir, $name) === false
                || !is_file($tmpDir . '/' . $name)) {
                $stats['failed']++;
                if (count($errors) < 20) {
                    $errors[] = $base . ': gagal mengekstrak dari zip.';
                }
                continue;
            }
            // extractTo memakai path di dalam zip; pindahkan ke nama aman.
            rename($tmpDir . '/' . $name, $extracted);
            $grouped[$product['id']][] = ['tmp' => $extracted, 'idx' => $idx, 'name' => $base];
        }
        $zip->close();

        $imgHandler = new ImageHandler();
        foreach ($grouped as $productId => $files) {
            $product = null;
            foreach ($byKode as $p) {
                if ($p['id'] === $productId) {
                    $product = $p;
                    break;
                }
            }
            if ($product === null) {
                continue;
            }
            if ($product['has_images'] && !$replace) {
                $stats['skipped_has_images']++;
                continue;
            }
            usort($files, static fn($a, $b) => $a['idx'] <=> $b['idx']);
            $images = [];
            $ok = true;
            foreach ($files as $f) {
                $mime = @mime_content_type($f['tmp']) ?: 'image/jpeg';
                $entry = [
                    'name' => $f['name'],
                    'type' => $mime,
                    'tmp_name' => $f['tmp'],
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($f['tmp']),
                ];
                try {
                    $res = $imgHandler->uploadAndConvertWebP($entry, 'products/' . $productId);
                } catch (Throwable $e) {
                    $stats['failed']++;
                    if (count($errors) < 20) {
                        $errors[] = $f['name'] . ': ' . $e->getMessage();
                    }
                    $ok = false;
                    continue;
                }
                if (empty($res['path'])) {
                    $stats['failed']++;
                    if (count($errors) < 20) {
                        $errors[] = $f['name'] . ': tidak ada path gambar yang dihasilkan.';
                    }
                    $ok = false;
                    continue;
                }
                $images[] = ['path' => $res['path'], 'primary' => empty($images)];
                $stats['images_uploaded']++;
            }
            if ($images === []) {
                continue;
            }
            if ($replace && $product['has_images']) {
                foreach ($product['images'] as $old) {
                    if (!empty($old['path'])) {
                        try {
                            $imgHandler->deleteR2File((string) $old['path']);
                        } catch (Throwable $e) {
                            // Hapus R2 best-effort; jangan gagalkan import.
                        }
                    }
                }
            }
            try {
                $sw->syncProductImages($productId, $images);
                $stats['matched_products']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                if (count($errors) < 20) {
                    $errors[] = $product['kode'] . ': gagal menyimpan ke MySQL.';
                }
            }
            unset($ok);
        }
    } finally {
        removeDirRecursive($tmpDir);
    }

    // --- Rebuild cache publik ---
    safeRebuildAllProducts();
    $cacheWarning = $GLOBALS['cacheRebuildWarning'] ?? null;

    try {
        Auth::audit('products_images_import', "produk {$stats['matched_products']}, gambar {$stats['images_uploaded']}, gagal {$stats['failed']}, tidak-dikenal {$stats['unknown']}");
    } catch (Throwable $e) {
        // Audit tidak boleh menggagalkan laporan import.
    }

    return [
        'files' => $stats['files'],
        'matched_products' => $stats['matched_products'],
        'images_uploaded' => $stats['images_uploaded'],
        'skipped_has_images' => $stats['skipped_has_images'],
        'unknown' => $stats['unknown'],
        'unknown_kodes' => $unknownKodes,
        'failed' => $stats['failed'],
        'errors' => $errors,
        'cache_warning' => $cacheWarning,
    ];
}

/**
 * Cari produk berdasarkan nama file (tanpa ekstensi). Cocok persis dulu,
 * lalu coba tukar "_" <-> "/" (kode barang yang mengandung "/").
 *
 * @param array<string, array<string, mixed>> $byKode
 */
function findProductByKode(array $byKode, string $stem): ?array
{
    $key = strtoupper(trim($stem));
    if (isset($byKode[$key])) {
        return $byKode[$key];
    }
    $alt = strtoupper(str_replace('_', '/', $key));
    if (isset($byKode[$alt])) {
        return $byKode[$alt];
    }
    $alt2 = strtoupper(str_replace('/', '_', $key));
    if (isset($byKode[$alt2])) {
        return $byKode[$alt2];
    }
    return null;
}

function removeDirRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

$allProducts = $sw->getProducts();
$totalProducts = count($allProducts);
$withImages = 0;
foreach ($allProducts as $p) {
    if (!empty($p['product_images'])) {
        $withImages++;
    }
}
$pageTitle = 'Import Gambar Produk Massal';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Import Gambar Produk Massal</h1>
    <p class="text-sm text-slate-500">Upload gambar produk sekaligus dari file .zip — nama file harus sama dengan Kode Barang.</p>
</div>

<?php if ($error !== null): ?>
    <div class="mb-6 rounded-lg bg-rose-50 p-4 border border-rose-200 text-rose-800 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($report !== null): ?>
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-3">Hasil Import Gambar</h2>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">File gambar di zip</dt><dd class="text-xl font-bold"><?= (int) $report['files'] ?></dd></div>
            <div class="rounded-lg bg-emerald-50 p-3"><dt class="text-slate-500">Produk terisi gambar</dt><dd class="text-xl font-bold text-emerald-700"><?= (int) $report['matched_products'] ?></dd></div>
            <div class="rounded-lg bg-sky-50 p-3"><dt class="text-slate-500">Gambar di-upload</dt><dd class="text-xl font-bold text-sky-700"><?= (int) $report['images_uploaded'] ?></dd></div>
            <div class="rounded-lg <?= $report['failed'] > 0 ? 'bg-rose-50' : 'bg-slate-50' ?> p-3"><dt class="text-slate-500">Gagal</dt><dd class="text-xl font-bold <?= $report['failed'] > 0 ? 'text-rose-700' : '' ?>"><?= (int) $report['failed'] ?></dd></div>
        </dl>
        <ul class="mt-4 text-sm text-slate-600 space-y-1">
            <li>Dilewati (produk sudah punya gambar): <strong><?= (int) $report['skipped_has_images'] ?></strong></li>
            <li>Kode tidak dikenal (dilewati): <strong><?= (int) $report['unknown'] ?></strong><?= count($report['unknown_kodes']) > 0 ? ' — ' . htmlspecialchars(implode(', ', $report['unknown_kodes'])) : '' ?></li>
        </ul>
        <?php if (!empty($report['errors'])): ?>
            <div class="mt-4 rounded-lg bg-rose-50 p-3 border border-rose-200 text-rose-800 text-sm">
                <strong>Error:</strong>
                <ul class="list-disc ml-5 mt-1">
                    <?php foreach ($report['errors'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($report['cache_warning'])): ?>
            <div class="mt-4 rounded-lg bg-amber-50 p-3 border border-amber-200 text-amber-800 text-sm"><?= htmlspecialchars($report['cache_warning']) ?></div>
        <?php else: ?>
            <p class="mt-4 text-sm text-emerald-700 font-semibold">Cache produk berhasil di-rebuild. Cek katalog di <a class="underline" href="/produk" target="_blank" rel="noopener">/produk</a>.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="rounded-xl bg-white p-5 shadow-sm border border-slate-200">
    <p class="text-sm text-slate-600 mb-4">
        Produk di website saat ini: <strong><?= (int) $totalProducts ?></strong> —
        sudah punya gambar: <strong><?= (int) $withImages ?></strong>,
        belum punya gambar: <strong><?= (int) ($totalProducts - $withImages) ?></strong>.
    </p>
    <form method="post" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="action" value="import">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="zip">File .zip berisi gambar <span class="text-rose-600">*</span></label>
            <input id="zip" name="zip" type="file" accept=".zip" required
                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-600 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-amber-700">
            <p class="text-xs text-slate-500 mt-1">Maks. 50 MB. Tiap gambar maks. 8 MB (JPG/PNG/WebP).</p>
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="replace" value="1" class="mt-0.5 rounded border-slate-300 text-amber-600">
            <span><strong>Timpa gambar yang sudah ada</strong> — bila tidak dicentang, produk yang sudah punya gambar dilewati dan gambar lamanya tetap dipakai.</span>
        </label>
        <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            <strong class="text-slate-800">Aturan penamaan file di dalam .zip:</strong>
            <ul class="list-disc ml-5 mt-1 space-y-0.5">
                <li>Nama file = <strong>Kode Barang</strong>, contoh: <code>45022-TG1-T00.jpg</code></li>
                <li>Gambar ke-2 dan seterusnya: tambah <code>-2</code>, <code>-3</code> — contoh: <code>45022-TG1-T00-2.jpg</code></li>
                <li>Kode Barang yang mengandung "/" ditulis dengan "_" — contoh: <code>MSD-FE/PSFR</code> menjadi <code>MSD-FE_PSFR.jpg</code></li>
                <li><strong>Gambar harus bentuk asli onderdilnya</strong> — bukan foto kardus/kemasan, bukan barang yang salah.</li>
            </ul>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">
            Mulai Import Gambar
        </button>
        <a href="/admin/products.php" class="ml-2 text-sm font-semibold text-slate-600 hover:text-slate-800">Kembali ke Daftar Produk</a>
    </form>
</div>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

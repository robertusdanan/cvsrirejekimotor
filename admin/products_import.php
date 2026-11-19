<?php
/**
 * Import produk massal dari export Accurate (.xlsx).
 *
 * Cara pakai:
 *  1. Upload file export Accurate (sheet "Barang & Jasa").
 *  2. Opsional: upload file JSON deskripsi (products-mapped.json) — deskripsi
 *     dicocokkan berdasarkan Kode Barang.
 *  3. Klik "Mulai Import". Halaman membaca HANYA kolom berikut:
 *       - Kode Barang, Nama Barang, Merek Barang, Kategori Barang,
 *         Def. Hrg. Jual Satuan #1 (harga jual).
 *     Harga Beli TIDAK PERNAH dibaca/ditampilkan. Stok di website TIDAK
 *     ditampilkan (stok produk lama dipertahankan, produk baru stok = 0).
 *  4. Upsert per Kode Barang (produk lama di-update, produk baru ditambah),
 *     lalu cache produk di-rebuild otomatis.
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
            $report = runImport($sw);
        } catch (Throwable $e) {
            $error = 'Import gagal: ' . $e->getMessage();
        }
    }
}

/** @return array<string, mixed> */
function runImport(MySQLWriter $sw): array
{
    $xlsx = $_FILES['xlsx'] ?? null;
    if ($xlsx === null || ($xlsx['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File .xlsx belum dipilih atau gagal di-upload.');
    }
    $ext = strtolower(pathinfo((string) $xlsx['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        throw new RuntimeException('File harus berekstensi .xlsx (export Accurate).');
    }
    if ((int) $xlsx['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file maksimal 10 MB.');
    }
    $tmpPath = (string) $xlsx['tmp_name'];
    if (!is_file($tmpPath)) {
        throw new RuntimeException('File upload tidak ditemukan di server.');
    }

    // --- Deskripsi (opsional, JSON: {"items":[{"kode_barang","description"}]} atau {kode: deskripsi}) ---
    $descriptions = [];
    $descFile = $_FILES['descriptions'] ?? null;
    if ($descFile !== null && ($descFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $dExt = strtolower(pathinfo((string) $descFile['name'], PATHINFO_EXTENSION));
        if ($dExt !== 'json') {
            throw new RuntimeException('File deskripsi harus berekstensi .json.');
        }
        $raw = file_get_contents((string) $descFile['tmp_name']);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('File deskripsi bukan JSON yang valid.');
        }
        $items = $decoded['items'] ?? $decoded;
        foreach ($items as $key => $val) {
            if (is_array($val) && isset($val['kode_barang'])) {
                $descriptions[trim((string) $val['kode_barang'])] = trim((string) ($val['description'] ?? ''));
            } elseif (is_string($val)) {
                $descriptions[trim((string) $key)] = trim($val);
            }
        }
    }

    // --- Baca sheet "Barang & Jasa" ---
    $rows = XlsxReader::readSheet($tmpPath, 'Barang & Jasa');

    $colMap = null; // ['kode' => 'A', 'nama' => 'B', ...]
    $parsed = [];   // kode => row data (terakhir menang bila duplikat)
    $stats = [
        'read' => 0, 'duplicates' => 0, 'skipped_empty' => 0,
        'skipped_inactive' => 0, 'skipped_non_inv' => 0,
    ];
    $errors = [];

    foreach ($rows as $row) {
        $cells = $row['cells'];
        if ($colMap === null) {
            $colMap = detectColumns($cells);
            if ($colMap === null) {
                throw new RuntimeException(
                    'Header tidak dikenali. Pastikan file adalah export Accurate '
                    . 'dengan kolom: Kode Barang, Nama Barang, Merek Barang, Kategori Barang, '
                    . 'Def. Hrg. Jual Satuan #1.'
                );
            }
            continue;
        }

        $stats['read']++;
        $kode = trim((string) ($cells[$colMap['kode']] ?? ''));
        $nama = trim((string) ($cells[$colMap['nama']] ?? ''));
        if ($kode === '' || $nama === '') {
            $stats['skipped_empty']++;
            continue;
        }
        if ($colMap['nonaktif'] !== null) {
            $na = strtolower(trim((string) ($cells[$colMap['nonaktif']] ?? '')));
            if (in_array($na, ['ya', 'y', 'true', '1'], true)) {
                $stats['skipped_inactive']++;
                continue;
            }
        }
        if ($colMap['jenis'] !== null) {
            $jenis = strtoupper(trim((string) ($cells[$colMap['jenis']] ?? '')));
            if ($jenis !== '' && $jenis !== 'INV') {
                $stats['skipped_non_inv']++;
                continue;
            }
        }

        $hargaRaw = trim((string) ($cells[$colMap['harga']] ?? ''));
        $harga = (int) preg_replace('/[^\d]/', '', $hargaRaw);
        $merk = trim((string) ($cells[$colMap['merk']] ?? ''));
        $category = trim((string) ($cells[$colMap['category']] ?? ''));

        if (isset($parsed[$kode])) {
            $stats['duplicates']++;
        }
        $parsed[$kode] = [
            'kode_barang' => $kode,
            'nama_produk' => $nama,
            'merk' => $merk !== '' ? $merk : null,
            'category' => $category !== '' ? $category : null,
            'harga_jual' => $harga,
            'description' => ($descriptions[$kode] ?? '') !== '' ? $descriptions[$kode] : null,
            'row' => $row['row'],
        ];
    }

    if ($colMap === null) {
        throw new RuntimeException('File .xlsx kosong atau tidak ada baris yang bisa dibaca.');
    }
    if ($parsed === []) {
        throw new RuntimeException('Tidak ada produk yang bisa di-import (semua baris kosong/tidak aktif).');
    }

    // --- Produk yang sudah ada di Supabase (untuk hitung baru vs update + jaga stok) ---
    $existing = [];
    foreach ($sw->getProducts() as $p) {
        if (!empty($p['kode_barang'])) {
            $existing[trim((string) $p['kode_barang'])] = true;
        }
    }

    // --- Upsert bulk per 100 baris ---
    $payload = [];
    $nNew = 0;
    $nUpdated = 0;
    $zeroPrice = [];
    $noBrand = [];
    foreach ($parsed as $kode => $item) {
        $isNew = !isset($existing[$kode]);
        if ($isNew) {
            $nNew++;
        } else {
            $nUpdated++;
        }
        if ($item['harga_jual'] === 0) {
            $zeroPrice[] = $kode;
        }
        if ($item['merk'] === null) {
            $noBrand[] = $kode;
        }
        $row = [
            'kode_barang' => $item['kode_barang'],
            'nama_produk' => $item['nama_produk'],
            'merk' => $item['merk'],
            'category' => $item['category'],
            'harga_jual' => $item['harga_jual'],
            'description' => $item['description'],
        ];
        if ($isNew) {
            $row['stok'] = 0; // produk lama: stok dipertahankan (tidak dikirim)
        }
        $payload[] = $row;
    }

    $failed = 0;
    foreach (array_chunk($payload, 100) as $chunk) {
        $res = $sw->request(
            'POST',
            '/rest/v1/products?on_conflict=kode_barang',
            $chunk,
            ['Prefer: return=representation,resolution=merge-duplicates']
        );
        if (isset($res['error']) || isset($res['message'])) {
            // Fallback: upsert satu per satu untuk mengisolasi baris bermasalah.
            foreach ($chunk as $single) {
                $one = $sw->upsertProduct($single);
                if ($one === null) {
                    $failed++;
                    if (count($errors) < 20) {
                        $errors[] = 'Kode ' . $single['kode_barang'] . ': gagal disimpan ke Supabase.';
                    }
                }
            }
        }
    }

    // --- Rebuild cache publik ---
    safeRebuildAllProducts();
    $cacheWarning = $GLOBALS['cacheRebuildWarning'] ?? null;

    try {
        Auth::audit('products_import', "baru {$nNew}, update {$nUpdated}, gagal {$failed}, dibaca {$stats['read']}");
    } catch (Throwable $e) {
        // Audit tidak boleh menggagalkan laporan import.
    }

    return [
        'read' => $stats['read'],
        'parsed' => count($parsed),
        'new' => $nNew,
        'updated' => $nUpdated,
        'failed' => $failed,
        'duplicates' => $stats['duplicates'],
        'skipped_empty' => $stats['skipped_empty'],
        'skipped_inactive' => $stats['skipped_inactive'],
        'skipped_non_inv' => $stats['skipped_non_inv'],
        'zero_price' => $zeroPrice,
        'no_brand' => $noBrand,
        'errors' => $errors,
        'desc_loaded' => count($descriptions),
        'cache_warning' => $cacheWarning,
    ];
}

/**
 * Deteksi kolom dari baris header. Mengembalikan null bila kolom wajib
 * (kode/nama/harga) tidak ditemukan.
 *
 * @param array<string, string> $cells
 * @return array<string, string|null>|null
 */
function detectColumns(array $cells): ?array
{
    $norm = [];
    foreach ($cells as $col => $val) {
        $key = preg_replace('/\s+/', ' ', strtolower(trim((string) $val)));
        $norm[$key] = $col;
    }
    $need = [
        'kode' => ['kode barang'],
        'nama' => ['nama barang'],
        'merk' => ['merek barang'],
        'category' => ['kategori barang'],
        'harga' => ['def. hrg. jual satuan #1'],
    ];
    $map = [];
    foreach ($need as $field => $names) {
        $found = null;
        foreach ($names as $name) {
            if (isset($norm[$name])) {
                $found = $norm[$name];
                break;
            }
        }
        if ($found === null) {
            return null;
        }
        $map[$field] = $found;
    }
    $map['nonaktif'] = $norm['non aktif'] ?? null;
    $map['jenis'] = $norm['jenis barang'] ?? null;
    return $map;
}

$currentCount = count($sw->getProducts());
$pageTitle = 'Import Produk Massal';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Import Produk Massal</h1>
    <p class="text-sm text-slate-500">Import katalog dari export Accurate (.xlsx) ke Supabase, lalu cache website di-rebuild otomatis.</p>
</div>

<?php if ($error !== null): ?>
    <div class="mb-6 rounded-lg bg-rose-50 p-4 border border-rose-200 text-rose-800 text-sm"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($report !== null): ?>
    <div class="mb-6 rounded-xl bg-white p-5 shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-3">Hasil Import</h2>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Baris dibaca</dt><dd class="text-xl font-bold"><?= (int) $report['read'] ?></dd></div>
            <div class="rounded-lg bg-emerald-50 p-3"><dt class="text-slate-500">Produk baru</dt><dd class="text-xl font-bold text-emerald-700"><?= (int) $report['new'] ?></dd></div>
            <div class="rounded-lg bg-sky-50 p-3"><dt class="text-slate-500">Produk di-update</dt><dd class="text-xl font-bold text-sky-700"><?= (int) $report['updated'] ?></dd></div>
            <div class="rounded-lg <?= $report['failed'] > 0 ? 'bg-rose-50' : 'bg-slate-50' ?> p-3"><dt class="text-slate-500">Gagal disimpan</dt><dd class="text-xl font-bold <?= $report['failed'] > 0 ? 'text-rose-700' : '' ?>"><?= (int) $report['failed'] ?></dd></div>
        </dl>
        <ul class="mt-4 text-sm text-slate-600 space-y-1">
            <li>Duplikat kode di dalam file (baris terakhir yang dipakai): <strong><?= (int) $report['duplicates'] ?></strong></li>
            <li>Dilewati (kode/nama kosong): <strong><?= (int) $report['skipped_empty'] ?></strong></li>
            <li>Dilewati (non-aktif): <strong><?= (int) $report['skipped_inactive'] ?></strong></li>
            <li>Dilewati (bukan barang dagang / Jenis &ne; INV): <strong><?= (int) $report['skipped_non_inv'] ?></strong></li>
            <li>Deskripsi dari file JSON yang terpasang: <strong><?= (int) $report['desc_loaded'] ?></strong> kode</li>
            <li>Harga 0 (tampil sebagai "Tanya Harga"): <strong><?= count($report['zero_price']) ?></strong><?= count($report['zero_price']) > 0 ? ' — ' . htmlspecialchars(implode(', ', $report['zero_price'])) : '' ?></li>
            <li>Tanpa merk: <strong><?= count($report['no_brand']) ?></strong><?= count($report['no_brand']) > 0 ? ' — ' . htmlspecialchars(implode(', ', $report['no_brand'])) : '' ?></li>
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
        Produk di website saat ini: <strong><?= (int) $currentCount ?></strong>.
        Import memakai <strong>Kode Barang</strong> sebagai kunci: produk dengan kode yang sama akan di-update, kode baru akan ditambah.
    </p>
    <form method="post" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="action" value="import">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="xlsx">File export Accurate (.xlsx) <span class="text-rose-600">*</span></label>
            <input id="xlsx" name="xlsx" type="file" accept=".xlsx" required
                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-600 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-amber-700">
            <p class="text-xs text-slate-500 mt-1">Sheet "Barang &amp; Jasa". Maks. 10 MB.</p>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1" for="descriptions">File deskripsi (.json) — opsional</label>
            <input id="descriptions" name="descriptions" type="file" accept=".json"
                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
            <p class="text-xs text-slate-500 mt-1">File JSON deskripsi produk (dicocokkan per Kode Barang). Bila tidak di-upload, deskripsi dikosongkan.</p>
        </div>
        <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            <strong class="text-slate-800">Kolom yang dibaca dari file:</strong>
            <ul class="list-disc ml-5 mt-1 space-y-0.5">
                <li>Kode Barang, Nama Barang, Merek Barang, Kategori Barang</li>
                <li>Harga jual HANYA dari <strong>Def. Hrg. Jual Satuan #1</strong></li>
                <li><strong>Harga Beli tidak pernah dibaca atau ditampilkan.</strong></li>
                <li>Stok tidak ditampilkan di website; stok produk lama dipertahankan, produk baru stok = 0.</li>
                <li>Baris dengan Non Aktif = YA dan Jenis &ne; INV dilewati.</li>
                <li>Harga 0 tetap ditampilkan di website sebagai "Tanya Harga".</li>
            </ul>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">
            Mulai Import
        </button>
        <a href="/admin/products.php" class="ml-2 text-sm font-semibold text-slate-600 hover:text-slate-800">Kembali ke Daftar Produk</a>
    </form>
</div>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

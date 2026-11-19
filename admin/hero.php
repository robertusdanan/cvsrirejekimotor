<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = dbWriter();
$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    $titles = $_POST['title'] ?? [];
    $subtitles = $_POST['subtitle'] ?? [];
    $mediaTypes = $_POST['media_type'] ?? [];
    $existingMediaUrls = $_POST['existing_media_url'] ?? [];
    $buttonTexts = $_POST['button_text'] ?? [];
    $buttonUrls = $_POST['button_url'] ?? [];
    $actives = $_POST['is_active'] ?? [];

    $slides = [];
    $imgHandler = new ImageHandler();

    for ($i = 0; $i < count($titles); $i++) {
        $title = trim($titles[$i]);
        $mType = trim($mediaTypes[$i] ?? 'image');
        $mUrl = trim($existingMediaUrls[$i] ?? '');

        // Check if new file uploaded for this slide
        if (!empty($_FILES['hero_files']['name'][$i]) && $_FILES['hero_files']['error'][$i] === UPLOAD_ERR_OK) {
            $singleFile = [
                'name'     => $_FILES['hero_files']['name'][$i],
                'type'     => $_FILES['hero_files']['type'][$i],
                'tmp_name' => $_FILES['hero_files']['tmp_name'][$i],
                'error'    => $_FILES['hero_files']['error'][$i],
                'size'     => $_FILES['hero_files']['size'][$i],
            ];

            if ($mType === 'video') {
                $mUrl = $imgHandler->uploadDirectFile($singleFile, 'hero');
            } else {
                $res = $imgHandler->uploadAndConvertWebP($singleFile, 'hero');
                $mUrl = $res['url'] ?? $res['path'] ?? $mUrl;
            }
        }

        if ($mUrl !== '') {
            $slides[] = [
                'title'       => $title,
                'subtitle'    => trim($subtitles[$i] ?? ''),
                'media_type'  => $mType,
                'media_url'   => $mUrl,
                'button_text' => trim($buttonTexts[$i] ?? 'Lihat Produk'),
                'button_url'  => trim($buttonUrls[$i] ?? '/produk'),
                'is_active'   => isset($actives[$i]),
            ];
        }
    }

    $sw->saveHeroSlides($slides);
    cacheBuilder()->rebuildHero();
    $flash = 'Banner / Video Hero berhasil diperbarui di MySQL!';
}

$slides = $sw->getHeroSlides();
if (empty($slides)) {
    $slides = [
        [
            'title'       => 'Pusat Suku Cadang Mobil Terlengkap',
            'subtitle'    => 'Menyediakan onderdil asli dan suku cadang presisi untuk berbagai jenis kendaraan.',
            'media_type'  => 'image',
            'media_url'   => '',
            'button_text' => 'Jelajahi Katalog',
            'button_url'  => '/produk',
            'is_active'   => true,
        ],
    ];
}

$pageTitle = 'Kelola Banner / Video Hero';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Kelola Banner / Video Hero</h1>
        <p class="text-sm text-slate-500">Atur tampilan slide/video promosi di bagian paling atas halaman utama.</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm font-medium">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

    <?php foreach ($slides as $idx => $s): ?>
        <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
            <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Slide #<?= $idx + 1 ?></h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Judul Slide</label>
                    <input type="text" name="title[]" value="<?= htmlspecialchars($s['title'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Sub-Judul / Deskripsi</label>
                    <input type="text" name="subtitle[]" value="<?= htmlspecialchars($s['subtitle'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Tipe Media</label>
                    <select name="media_type[]" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
                        <option value="image" <?= ($s['media_type'] ?? 'image') === 'image' ? 'selected' : '' ?>>Gambar / Foto (WebP)</option>
                        <option value="video" <?= ($s['media_type'] ?? '') === 'video' ? 'selected' : '' ?>>Video MP4 (Streaming Direct R2)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">URL Media R2 Saat Ini</label>
                    <input type="text" name="existing_media_url[]" value="<?= htmlspecialchars($s['media_url'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-mono focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Ganti File Media (Foto / Video)</label>
                    <input type="file" name="hero_files[]" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Teks Tombol Aksi</label>
                    <input type="text" name="button_text[]" value="<?= htmlspecialchars($s['button_text'] ?? 'Lihat Produk') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" id="active_<?= $idx ?>" name="is_active[<?= $idx ?>]" value="1" <?= !empty($s['is_active']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                <label for="active_<?= $idx ?>" class="text-sm font-medium text-slate-800">Aktifkan Slide Ini</label>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="flex justify-end">
        <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Simpan Banner Hero</button>
    </div>
</form>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$flash = null;
$error = null;

// Sumber kebenaran hero homepage adalah manifest
// ~/private/data/hero/manifest.json (dibaca home.php lewat heroManifest()).
// Dulu admin ini membaca/menulis tabel hero_slides (MySQL) yang KOSONG dan
// tidak pernah dibaca homepage — akibatnya hero aktif tidak terdeteksi di
// sini. Sekarang admin membaca + menulis manifest yang sama dengan homepage.
$manifest = heroManifest();
$settings = $manifest['settings'];
$items = $manifest['items'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    $imgHandler = new ImageHandler();
    $existingFiles = $_POST['existing_file'] ?? [];
    $mediaTypes = $_POST['media_type'] ?? [];
    $actives = $_POST['is_active'] ?? [];
    $sortOrders = $_POST['sort_order'] ?? [];
    $deletes = $_POST['delete_slide'] ?? [];

    $newItems = [];
    $count = max(count($existingFiles), count($mediaTypes));
    for ($i = 0; $i < $count; $i++) {
        if (!empty($deletes[$i])) {
            continue; // slide dihapus
        }
        $file = trim((string) ($existingFiles[$i] ?? ''));
        $mType = trim((string) ($mediaTypes[$i] ?? 'image')) === 'video' ? 'video' : 'image';

        // File baru menggantikan file lama (bila diunggah).
        if (!empty($_FILES['hero_files']['name'][$i]) && $_FILES['hero_files']['error'][$i] === UPLOAD_ERR_OK) {
            $singleFile = [
                'name'     => $_FILES['hero_files']['name'][$i],
                'type'     => $_FILES['hero_files']['type'][$i],
                'tmp_name' => $_FILES['hero_files']['tmp_name'][$i],
                'error'    => $_FILES['hero_files']['error'][$i],
                'size'     => $_FILES['hero_files']['size'][$i],
            ];
            try {
                if ($mType === 'video') {
                    $file = $imgHandler->uploadDirectFile($singleFile, 'hero');
                } else {
                    $res = $imgHandler->uploadAndConvertWebP($singleFile, 'hero');
                    $file = $res['url'] ?? $res['path'] ?? $file;
                }
            } catch (Throwable $e) {
                $error = 'Upload gagal untuk slide #' . ($i + 1) . ': ' . $e->getMessage();
                break;
            }
        }

        if ($file === '') {
            continue; // baris kosong tanpa file = bukan slide
        }
        $newItems[] = [
            'file'       => $file,
            'type'       => $mType,
            'active'     => isset($actives[$i]),
            'sort_order' => (int) ($sortOrders[$i] ?? count($newItems)),
        ];
    }

    if ($error === null) {
        // Urutkan berdasarkan sort_order supaya rotasi mengikuti urutan.
        usort($newItems, static function ($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });
        foreach ($newItems as $idx => &$it) {
            $it['sort_order'] = $idx;
        }
        unset($it);

        $intervalSec = (int) ($_POST['interval_sec'] ?? 6);
        $intervalSec = max(3, min(60, $intervalSec));
        $newSettings = [
            'kicker'           => trim((string) ($_POST['kicker'] ?? ($settings['kicker'] ?? ''))),
            'heading_override' => trim((string) ($_POST['heading_override'] ?? ($settings['heading_override'] ?? ''))),
            'interval_ms'      => $intervalSec * 1000,
            'overlay_opacity'  => max(0, min(1, (float) ($_POST['overlay_opacity'] ?? ($settings['overlay_opacity'] ?? 0.65)))),
        ];
        $ok = saveHeroManifest(['settings' => $newSettings, 'items' => $newItems]);
        if ($ok) {
            $flash = 'Banner / Video Hero berhasil diperbarui! Homepage sekarang menampilkan ' . count(array_filter($newItems, static function ($it) {
                return !empty($it['active']);
            })) . ' slide aktif.';
            $manifest = heroManifest();
            $settings = $manifest['settings'];
            $items = $manifest['items'];
        } else {
            $error = 'Gagal menyimpan manifest hero. Periksa izin tulis folder ~/private/data/hero/.';
        }
    }
}

$activeCount = count(array_filter($items, static function ($it) {
    return !empty($it['active']) && !empty($it['file']);
}));

$pageTitle = 'Kelola Banner / Video Hero';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Kelola Banner / Video Hero</h1>
        <p class="text-sm text-slate-500">Atur tampilan slide/video promosi di bagian paling atas halaman utama.</p>
        <p class="mt-1 text-sm font-medium <?= $activeCount > 0 ? 'text-emerald-600' : 'text-amber-600' ?>">
            <?= $activeCount > 0 ? $activeCount . ' slide aktif di homepage saat ini.' : 'Belum ada slide aktif di homepage.' ?>
        </p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm font-medium">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 rounded-lg bg-rose-50 p-4 border border-rose-200 text-rose-800 text-sm">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

    <?php foreach ($items as $idx => $it): ?>
        <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
            <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Slide #<?= $idx + 1 ?> <?= !empty($it['active']) ? '<span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">AKTIF</span>' : '<span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded">NONAKTIF</span>' ?></h2>
            <input type="hidden" name="existing_file[]" value="<?= htmlspecialchars((string) ($it['file'] ?? '')) ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Tipe Media</label>
                    <select name="media_type[]" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
                        <option value="image" <?= ($it['type'] ?? 'image') === 'image' ? 'selected' : '' ?>>Gambar / Foto (WebP)</option>
                        <option value="video" <?= ($it['type'] ?? '') === 'video' ? 'selected' : '' ?>>Video MP4 (Streaming Direct R2)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Urutan Tampil</label>
                    <input type="number" min="0" name="sort_order[]" value="<?= (int) ($it['sort_order'] ?? $idx) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 font-mono">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">File Media Saat Ini</label>
                    <?php if (!empty($it['file'])): ?>
                        <?php if (($it['type'] ?? '') === 'video'): ?>
                            <video src="<?= htmlspecialchars(mediaUrl((string) $it['file'])) ?>" class="max-h-40 rounded-lg border border-slate-200" muted loop playsinline controls></video>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars(mediaUrl((string) $it['file'])) ?>" class="max-h-40 rounded-lg border border-slate-200" alt="">
                        <?php endif; ?>
                        <p class="mt-1 text-xs font-mono text-slate-400 break-all"><?= htmlspecialchars((string) $it['file']) ?></p>
                    <?php else: ?>
                        <p class="text-sm text-slate-400">Belum ada file.</p>
                    <?php endif; ?>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Ganti File Media (Foto / Video)</label>
                    <input type="file" name="hero_files[]" accept="image/*,video/mp4,video/webm,video/mov" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-5 pt-2">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                    <input type="checkbox" name="is_active[<?= $idx ?>]" value="1" <?= !empty($it['active']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    Aktifkan Slide Ini
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-medium text-rose-600">
                    <input type="checkbox" name="delete_slide[<?= $idx ?>]" value="1" class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    Hapus Slide Ini
                </label>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="rounded-xl bg-white p-6 shadow-sm border border-dashed border-slate-300 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Tambah Slide Baru</h2>
        <input type="hidden" name="existing_file[]" value="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Tipe Media</label>
                <select name="media_type[]" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
                    <option value="image">Gambar / Foto (WebP)</option>
                    <option value="video">Video MP4 (Streaming Direct R2)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Urutan Tampil</label>
                <input type="number" min="0" name="sort_order[]" value="<?= count($items) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 font-mono">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">File Media</label>
                <input type="file" name="hero_files[]" accept="image/*,video/mp4,video/webm,video/mov" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition">
                <p class="mt-1 text-xs text-slate-400">Kosongkan bila tidak menambah slide.</p>
            </div>
        </div>
        <div class="flex items-center gap-2 pt-2">
            <input type="checkbox" id="active_new" name="is_active[<?= count($items) ?>]" value="1" checked class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
            <label for="active_new" class="text-sm font-medium text-slate-800">Aktifkan Slide Ini</label>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Pengaturan Hero</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Interval Rotasi (detik, 3–60)</label>
                <input type="number" min="3" max="60" name="interval_sec" value="<?= (int) round(((int) ($settings['interval_ms'] ?? 6000)) / 1000) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Opasitas Overlay (0–1)</label>
                <input type="number" step="0.05" min="0" max="1" name="overlay_opacity" value="<?= htmlspecialchars((string) ($settings['overlay_opacity'] ?? 0.65)) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Kicker (teks kecil di atas judul)</label>
                <input type="text" name="kicker" value="<?= htmlspecialchars((string) ($settings['kicker'] ?? '')) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Judul Hero (kosongkan = judul default)</label>
                <input type="text" name="heading_override" value="<?= htmlspecialchars((string) ($settings['heading_override'] ?? '')) ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500">
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Simpan Banner Hero</button>
    </div>
</form>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

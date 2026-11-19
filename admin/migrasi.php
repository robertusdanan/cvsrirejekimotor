<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    cacheBuilder()->rebuildAll();
    $flash = 'Seluruh cache publik berhasil diperbarui dari MySQL Database!';
}

$pageTitle = 'Sinkronisasi Cache';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Sinkronisasi Cache Website</h1>
    <p class="text-sm text-slate-500">Regenerasi file JSON publik dari MySQL Database.</p>
</div>

<?php if ($flash): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm font-medium">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 max-w-xl">
    <h2 class="text-base font-bold text-slate-800 mb-2">Perbarui Cache Publik</h2>
    <p class="text-sm text-slate-600 mb-6">Secara otomatis, setiap penambahan/perubahan produk, profil, atau hero slide di admin akan langsung memperbarui cache publik. Gunakan tombol di bawah ini jika Anda ingin melakukan regenerasi total secara manual.</p>

    <form method="POST">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Jalankan Regenerasi Cache Sekarang</button>
    </form>
</div>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

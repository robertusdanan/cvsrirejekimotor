<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = dbWriter();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    $names = $_POST['name'] ?? [];
    $urls = $_POST['url'] ?? [];
    $icons = $_POST['icon'] ?? [];
    $actives = $_POST['active'] ?? [];

    $items = [];
    for ($i = 0; $i < count($names); $i++) {
        $n = trim($names[$i]);
        if ($n !== '') {
            $items[] = [
                'name'      => $n,
                'slug'      => slugify($n),
                'url'       => trim($urls[$i] ?? '#'),
                'icon'      => trim($icons[$i] ?? 'shopping-bag'),
                'is_active' => isset($actives[$i]),
            ];
        }
    }

    $sw->saveMarketplaces($items);
    cacheBuilder()->rebuildMarketplaces();
    $flash = 'Daftar marketplace berhasil diperbarui di MySQL!';
}

$marketplacesData = $sw->getMarketplaces();
$items = !empty($marketplacesData) ? $marketplacesData : [
    ['name' => 'Tokopedia', 'slug' => 'tokopedia', 'url' => 'https://tokopedia.com', 'icon' => 'shopping-bag', 'is_active' => true],
    ['name' => 'Shopee', 'slug' => 'shopee', 'url' => 'https://shopee.co.id', 'icon' => 'shopping-cart', 'is_active' => true],
];

$pageTitle = 'Kelola Marketplace';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Kelola Marketplace</h1>
        <p class="text-sm text-slate-500">Atur link toko online resmi (Tokopedia, Shopee, Bukalapak, TikTok Shop) di MySQL.</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm font-medium">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<form method="POST" class="space-y-6">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Daftar Marketplace Resmi</h2>

        <div id="mp-container" class="space-y-3">
            <?php foreach ($items as $idx => $m): ?>
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-3 bg-slate-50/50">
                    <input type="text" name="name[]" placeholder="Nama Marketplace" value="<?= htmlspecialchars($m['name'] ?? '') ?>" class="w-48 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-amber-500">
                    <input type="url" name="url[]" placeholder="https://tokopedia.com/..." value="<?= htmlspecialchars($m['url'] ?? '') ?>" class="flex-1 min-w-[200px] rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-amber-500 font-mono">
                    <input type="text" name="icon[]" placeholder="icon name" value="<?= htmlspecialchars($m['icon'] ?? 'shopping-bag') ?>" class="w-32 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-amber-500 font-mono">
                    <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="active[<?= $idx ?>]" value="1" <?= !empty($m['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-amber-600">
                        Aktif
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Simpan Marketplace</button>
    </div>
</form>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

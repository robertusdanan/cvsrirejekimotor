<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$sw = dbWriter();
$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? null)) {
    $profileData = [
        'company_name'      => trim($_POST['company_name'] ?? 'CV Sri Rejeki Motor'),
        'tagline'           => trim($_POST['tagline'] ?? ''),
        'description'       => trim($_POST['description'] ?? ''),
        'address'           => trim($_POST['address'] ?? ''),
        'whatsapp'          => trim($_POST['whatsapp'] ?? ''),
        'phone'             => trim($_POST['phone'] ?? ''),
        'email'             => trim($_POST['email'] ?? ''),
        'google_maps_embed' => trim($_POST['google_maps_embed'] ?? ''),
        'operational_hours' => [
            'workdays' => trim($_POST['workdays'] ?? 'Senin - Sabtu: 08:00 - 17:00'),
            'sunday'   => trim($_POST['sunday'] ?? 'Minggu / Hari Libur: Tutup'),
        ],
    ];

    $sw->saveCompanyProfile($profileData);
    cacheBuilder()->rebuildCompany();
    $flash = 'Profil perusahaan berhasil diperbarui di MySQL!';
}

$p = $sw->getCompanyProfile();
$hours = is_array($p['operational_hours'] ?? null) ? $p['operational_hours'] : [];

$pageTitle = 'Profil Perusahaan';
require __DIR__ . '/templates/admin_header.php';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Profil Perusahaan</h1>
        <p class="text-sm text-slate-500">Kelola identitas usaha, alamat, kontak WhatsApp, dan jam operasional di MySQL.</p>
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
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Identitas Utama Usaha</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Perusahaan / Toko</label>
                <input type="text" name="company_name" required value="<?= htmlspecialchars($p['company_name'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Tagline</label>
                <input type="text" name="tagline" value="<?= htmlspecialchars($p['tagline'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Deskripsi Singkat Usaha</label>
            <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 p-3.5 text-sm focus:border-amber-500 focus:ring-amber-500"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Informasi Kontak & Alamat</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nomor WhatsApp (Contoh: 6281234567890)</label>
                <input type="text" name="whatsapp" value="<?= htmlspecialchars($p['whatsapp'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Telepon Kantor</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($p['phone'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Email Resmi</label>
                <input type="email" name="email" value="<?= htmlspecialchars($p['email'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Alamat Fisik Gerai</label>
            <textarea name="address" rows="2" class="w-full rounded-lg border border-slate-300 p-3.5 text-sm focus:border-amber-500 focus:ring-amber-500"><?= htmlspecialchars($p['address'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Google Maps Embed URL / iFrame Src</label>
            <input type="text" name="google_maps_embed" value="<?= htmlspecialchars($p['google_maps_embed'] ?? '') ?>" placeholder="https://www.google.com/maps/embed?..." class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500 font-mono">
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
        <h2 class="text-lg font-bold text-slate-800 border-b border-slate-100 pb-3">Jam Operasional Gerai</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Senin - Sabtu</label>
                <input type="text" name="workdays" value="<?= htmlspecialchars($hours['workdays'] ?? 'Senin - Sabtu: 08:00 - 17:00') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Minggu / Hari Libur</label>
                <input type="text" name="sunday" value="<?= htmlspecialchars($hours['sunday'] ?? 'Minggu / Hari Libur: Tutup') ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-amber-500 focus:ring-amber-500">
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 transition">Simpan Profil</button>
    </div>
</form>

<?php require __DIR__ . '/templates/admin_footer.php'; ?>

<?php
// Applier sekali pakai: copot prefiks SNC + 3 koreksi nama_mobil (Danan 2026-11-19).
// (1) SNCME601290 -> ME601290 (nomor Mitsubishi valid, ME601290 belum ada di katalog).
// (2) Hapus SNC30620-00Q0E (duplikat persis 30620-00Q0E: SANYCO, Livina, Rp450.000,
//     30620-00Q0E sudah bergambar batch 28; SNC 0 gambar).
// (3) SK41201 nama_mobil Daihatsu Delta -> Toyota Dyna Rino (Danan: "iya Dyna Rino").
// (4) SK44221 nama_mobil (kosong) -> Toyota Kijang Super KF50 (Danan: "benar Kijang KF50 5K").
// (5) 8-97941-515-0 nama_mobil Isuzu Traga -> Isuzu D-Max (Danan: "Iya D Max").
// (6) SK83781-2: TIDAK disentuh — Danan: "sesuai yang di katalog" (kit slave, sudah benar).
// POST + header X-Upload-Token == config('IMAGE_UPLOAD_TOKEN'). Hapus file ini sesudah jalan.
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/MySQLWriter.php';
require_once __DIR__ . '/../src/CacheBuilder.php';
require_once __DIR__ . '/../src/ImageHandler.php';

header('Content-Type: application/json');
$token = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals((string)config('IMAGE_UPLOAD_TOKEN'), $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$pdo = MySQLWriter::pdo();
$out = ['ok' => true];

// --- Guard: baca state live ---
$q = function (string $kode) use ($pdo) {
    $st = $pdo->prepare('SELECT id, kode_barang, nama_produk, nama_mobil, merk, harga_jual FROM products WHERE kode_barang = ?');
    $st->execute([$kode]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
};
$snc30620 = $q('SNC30620-00Q0E');
$base30620 = $q('30620-00Q0E');
$sncme = $q('SNCME601290');
$me = $q('ME601290');
$sk41201 = $q('SK41201');
$sk44221 = $q('SK44221');
$dm515 = $q('8-97941-515-0');
$guard = [];
$guard['snc30620_ada'] = count($snc30620) === 1;
$guard['base30620_ada'] = count($base30620) === 1;
$guard['snc30620_duplikat_persis'] = $guard['snc30620_ada'] && $guard['base30620_ada']
    && $snc30620[0]['merk'] === $base30620[0]['merk']
    && (int)$snc30620[0]['harga_jual'] === (int)$base30620[0]['harga_jual']
    && $snc30620[0]['nama_mobil'] === $base30620[0]['nama_mobil'];
$guard['sncme_ada'] = count($sncme) === 1;
$guard['me601290_belum_ada'] = count($me) === 0;
$guard['sk41201_delta'] = count($sk41201) === 1 && $sk41201[0]['nama_mobil'] === 'Daihatsu Delta';
$guard['sk44221_kosong'] = count($sk44221) === 1 && ($sk44221[0]['nama_mobil'] === null || $sk44221[0]['nama_mobil'] === '');
$guard['dm515_traga'] = count($dm515) === 1 && $dm515[0]['nama_mobil'] === 'Isuzu Traga';
$out['guard'] = $guard;
foreach ($guard as $k => $v) {
    if (!$v) {
        $out['ok'] = false;
        $out['error'] = 'guard gagal: ' . $k;
        echo json_encode($out);
        exit;
    }
}
// SNC30620-00Q0E harus 0 gambar (yang bergambar dipertahankan = 30620-00Q0E)
$st = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
$st->execute([$snc30620[0]['id']]);
$out['gambar_snc30620'] = (int)$st->fetchColumn();
if ($out['gambar_snc30620'] !== 0) {
    $out['ok'] = false;
    $out['error'] = 'SNC30620-00Q0E punya gambar, batal';
    echo json_encode($out);
    exit;
}

// --- Transaksi: rename + hapus + 3 koreksi nama_mobil + 2 kendaraan baru ---
$pdo->beginTransaction();
try {
    // (1) Rename SNCME601290 -> ME601290
    $st = $pdo->prepare('UPDATE products SET kode_barang = ? WHERE id = ?');
    $st->execute(['ME601290', $sncme[0]['id']]);
    $out['rename_sncme601290'] = $st->rowCount();

    // (2) Hapus SNC30620-00Q0E (direct SQL — deleteProduct() punya transaksi sendiri)
    $st = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
    $st->execute([$snc30620[0]['id']]);
    $st = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $st->execute([$snc30620[0]['id']]);
    $out['hapus_snc30620'] = $st->rowCount();

    // (3)-(5) Koreksi nama_mobil
    $st = $pdo->prepare('UPDATE products SET nama_mobil = ? WHERE id = ?');
    $st->execute(['Toyota Dyna Rino', $sk41201[0]['id']]);
    $out['nama_sk41201'] = $st->rowCount();
    $st->execute(['Toyota Kijang Super KF50', $sk44221[0]['id']]);
    $out['nama_sk44221'] = $st->rowCount();
    $st->execute(['Isuzu D-Max', $dm515[0]['id']]);
    $out['nama_8_97941_515_0'] = $st->rowCount();

    // Kendaraan baru ke registry (Isuzu D-Max sudah di new-vehicles.json; Dyna Rino baru)
    $st = $pdo->prepare("INSERT INTO vehicles (name, brand, sort_order) VALUES (?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM vehicles)) ON DUPLICATE KEY UPDATE name = name");
    $st->execute(['Toyota Dyna Rino', 'Toyota']);
    $out['kendaraan_dyna_rino'] = $st->rowCount();
    $st->execute(['Isuzu D-Max', 'Isuzu']);
    $out['kendaraan_dmax'] = $st->rowCount();

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $out['ok'] = false;
    $out['error'] = 'transaksi gagal: ' . $e->getMessage();
    echo json_encode($out);
    exit;
}

// --- Redirect: slug lama -> slug kanonis baru ---
$out['redirects'] = [
    'master-kopling-bawah-co-assy-1-5-snc30620-00q0e' => 'master-kopling-bawah-co-assy-1-5-30620-00q0e',
    'master-kopling-bawah-co-assy-sncme601290' => 'master-kopling-bawah-co-assy-me601290',
];

// --- Rebuild cache ---
try {
    $builder = new CacheBuilder();
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
}
echo json_encode($out);

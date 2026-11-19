<?php
// Applier sekali pakai: copot prefiks SNC — 4 kode Master Kopling Atas (Danan 2026-11-19:
// "oke copot prefiksnya, pastikan code hasil copot itu memang tetap valid").
// (1) SNC31420-26020 -> HAPUS (duplikat persis 31420-26020: SANYCO, Hino Rino, Rp200.000;
//     keduanya 0 gambar).
// (2) SNC31420-37141 -> 31420-37141 (nomor Toyota valid: Cylinder Assy Clutch Master,
//     Dyna XZU 412/420, Megazip + PartSouq; nama_mobil diisi "Toyota Dyna").
// (3) SNCMC113030 -> MC113030 (nomor Mitsubishi valid: Clutch Master Cylinder Canter
//     FE3/FE4/FE5/FE6, bore 7/8", cross-ref ME607345/MB165130; MB165130 sudah ada di
//     katalog sebagai produk terpisah — kemungkinan duplikat, TIDAK digabung di sini,
//     perlu keputusan Danan).
// (4) SNCME507832 -> ME507832 (nomor valid; ME507832 sudah ada sebagai MITSUBISHI KTB
//     Rp1.465.000 — baris SANYCO Rp275.000 jadi varian kedua, kunci = ID).
// POST + header X-Upload-Token == config('IMAGE_UPLOAD_TOKEN'). Hapus file ini sesudah jalan.
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/MySQLWriter.php';
require_once __DIR__ . '/../src/Cache.php';
require_once __DIR__ . '/../src/CacheBuilder.php';

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

$q = function (string $kode) use ($pdo) {
    $st = $pdo->prepare('SELECT id, kode_barang, nama_produk, nama_mobil, merk, harga_jual FROM products WHERE kode_barang = ?');
    $st->execute([$kode]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
};
$a = $q('SNC31420-26020'); $a2 = $q('31420-26020');
$b = $q('SNC31420-37141'); $b2 = $q('31420-37141');
$c = $q('SNCMC113030'); $c2 = $q('MC113030');
$d = $q('SNCME507832'); $d2 = $q('ME507832');
$guard = [];
$guard['snc26020_ada'] = count($a) === 1;
$guard['base26020_ada'] = count($a2) === 1;
$guard['snc26020_duplikat_persis'] = $guard['snc26020_ada'] && $guard['base26020_ada']
    && $a[0]['merk'] === $a2[0]['merk']
    && (int)$a[0]['harga_jual'] === (int)$a2[0]['harga_jual']
    && $a[0]['nama_mobil'] === $a2[0]['nama_mobil'];
$guard['snc37141_ada'] = count($b) === 1;
$guard['base37141_belum_ada'] = count($b2) === 0;
$guard['sncmc_ada'] = count($c) === 1;
$guard['mc113030_belum_ada'] = count($c2) === 0;
$guard['sncme507832_ada'] = count($d) === 1;
$guard['me507832_ada'] = count($d2) === 1;
$guard['me507832_beda_merk'] = $guard['me507832_ada'] && $d[0]['merk'] !== $d2[0]['merk'];
$out['guard'] = $guard;
foreach ($guard as $k => $v) {
    if (!$v) {
        $out['ok'] = false;
        $out['error'] = 'guard gagal: ' . $k;
        echo json_encode($out);
        exit;
    }
}
$st = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
$st->execute([$a[0]['id']]);
$out['gambar_snc26020'] = (int)$st->fetchColumn();
if ($out['gambar_snc26020'] !== 0) {
    $out['ok'] = false;
    $out['error'] = 'SNC31420-26020 punya gambar, batal';
    echo json_encode($out);
    exit;
}

// Slug lama (untuk redirect) — dihitung dari nama_produk + kode lama.
$oldSlugs = [];
foreach ([$a[0], $b[0], $c[0], $d[0]] as $row) {
    $oldSlugs[$row['kode_barang']] = slugify($row['nama_produk'] . '-' . $row['kode_barang']);
}
$out['old_slugs'] = $oldSlugs;

$pdo->beginTransaction();
try {
    $st = $pdo->prepare('UPDATE products SET kode_barang = ?, nama_mobil = ? WHERE id = ?');
    $st->execute(['31420-37141', 'Toyota Dyna', $b[0]['id']]);
    $out['rename_snc31420_37141'] = $st->rowCount();
    $st = $pdo->prepare('UPDATE products SET kode_barang = ? WHERE id = ?');
    $st->execute(['MC113030', $c[0]['id']]);
    $out['rename_sncmc113030'] = $st->rowCount();
    $st->execute(['ME507832', $d[0]['id']]);
    $out['rename_sncme507832'] = $st->rowCount();
    $st = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
    $st->execute([$a[0]['id']]);
    $st = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $st->execute([$a[0]['id']]);
    $out['hapus_snc31420_26020'] = $st->rowCount();
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $out['ok'] = false;
    $out['error'] = 'transaksi gagal: ' . $e->getMessage();
    echo json_encode($out);
    exit;
}

// Rebuild cache (CacheBuilder butuh MySQLWriter — JANGAN new CacheBuilder() kosong).
try {
    $builder = new CacheBuilder(new MySQLWriter());
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
    echo json_encode($out);
    exit;
}

// Redirect: slug lama -> slug kanonis baru (dibaca dari index hasil rebuild).
$out['redirects'] = [];
try {
    $cache = new Cache();
    $idx = $cache->read('products/index.json');
    $byKode = [];
    foreach (($idx['items'] ?? []) as $it) {
        $byKode[$it['kode_barang']] = $it['slug'];
    }
    $map = [
        'SNC31420-26020' => '31420-26020',
        'SNC31420-37141' => '31420-37141',
        'SNCMC113030' => 'MC113030',
        'SNCME507832' => 'ME507832',
    ];
    foreach ($map as $oldKode => $newKode) {
        if (isset($oldSlugs[$oldKode], $byKode[$newKode])) {
            $out['redirects'][$oldSlugs[$oldKode]] = $byKode[$newKode];
        }
    }
} catch (Throwable $e) {
    $out['redirects_error'] = $e->getMessage();
}
echo json_encode($out);

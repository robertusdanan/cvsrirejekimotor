<?php
// Applier sekali pakai: normalisasi merk supplier (Danan 2026-11-19, dari screenshot
// daftar merk): "Sam Bap"/"Sam Hja" -> "SAM" (Bap + Hja itu supplier, bukan merk),
// "SH JRM" -> "SH" (JRM itu supplier), "TERADA" -> "Terada" (duplikat karena beda
// huruf besar/kecil: 'Terada' 24 + 'TERADA' 2), "BMS/SPR" -> "BMS" (satu daftar
// merk BMS saja; pencarian BMS nanti menemukan produk BMS/SPR).
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
$maps = [
    ['SAM BAP', 'SAM'],
    ['SAM HJA', 'SAM'],
    ['SH JRM', 'SH'],
    ['TERADA', 'Terada'],
    ['BMS/SPR', 'BMS'],
];
$expected = ['SAM BAP' => 5, 'SAM HJA' => 3, 'SH JRM' => 2, 'TERADA' => 2, 'BMS/SPR' => 2];
$out['guard'] = [];
foreach ($maps as [$lama, $baru]) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE merk = ?');
    $st->execute([$lama]);
    $n = (int)$st->fetchColumn();
    $out['guard'][$lama] = $n;
    if ($n !== $expected[$lama]) {
        $out['ok'] = false;
        $out['error'] = "guard gagal: merk '$lama' ada $n baris, diharapkan {$expected[$lama]}";
        echo json_encode($out);
        exit;
    }
}
$out['diubah'] = [];
$pdo->beginTransaction();
try {
    foreach ($maps as [$lama, $baru]) {
        $st = $pdo->prepare('UPDATE products SET merk = ? WHERE merk = ?');
        $st->execute([$baru, $lama]);
        $out['diubah']["$lama -> $baru"] = $st->rowCount();
    }
    // Hapus baris registry merk lama (tidak dipakai lagi); sisipkan SAM + SH
    // dengan sort_order yang sama seperti posisi lamanya (49 dan 53).
    $st = $pdo->prepare("DELETE FROM brands WHERE LOWER(name) IN ('sam bap','sam hja','sh jrm','bms/spr')");
    $st->execute();
    $out['brands_dihapus'] = $st->rowCount();
    foreach ([['SAM', 49], ['SH', 53]] as [$nama, $sort]) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM brands WHERE LOWER(name) = LOWER(?)');
        $st->execute([$nama]);
        if ((int)$st->fetchColumn() === 0) {
            $st = $pdo->prepare('INSERT INTO brands (name, sort_order) VALUES (?, ?)');
            $st->execute([$nama, $sort]);
            $out['brands_disisp'] = ($out['brands_disisp'] ?? 0) + $st->rowCount();
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $out['ok'] = false;
    $out['error'] = 'transaksi gagal: ' . $e->getMessage();
    echo json_encode($out);
    exit;
}
try {
    $builder = new CacheBuilder(new MySQLWriter(), new Cache());
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    $out['cache_rebuild'] = 'GAGAL: ' . $e->getMessage();
    $out['ok'] = false;
}
echo json_encode($out);

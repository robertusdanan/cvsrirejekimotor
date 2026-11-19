<?php
declare(strict_types=1);

/**
 * ONE-TIME FIX (2026-11-19, perintah Danan):
 *  - merk 'TE' -> 'Terada' (TE = Terada; 28 produk)
 *  - merk 'MEC JP' -> 'MEC' (JP = Jepang, asal saja; bukan merk terpisah)
 *  - hapus baris brands 'TE' + 'MEC JP' yang jadi yatim (Terada & MEC sudah ada)
 *  - hapus produk 8-94908-840 (TYPO dari 8-94408-840-3; duplikat Terada
 *    harga lebih rendah dari TE8-94408-840-3 Rp55.000; 0 gambar)
 *  - rebuild cache (agar merge 8-94408-840-3 + merk baru tampil di website)
 *
 * Auth: header X-Upload-Token == IMAGE_UPLOAD_TOKEN (sama seperti
 * api/image-upload.php). POST only. Fail-closed 401 bila token kosong.
 *
 * SETELAH dijalankan + terverifikasi, HAPUS file ini dari repo + public_html
 * (seperti api/migrate.php dulu) — jangan dibiarkan hidup di server.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Cache.php';
require __DIR__ . '/../src/MySQLWriter.php';
require __DIR__ . '/../src/CacheBuilder.php';

header('Content-Type: application/json');

function fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'method not allowed (pakai POST)');
}

$expected = (string) (config('IMAGE_UPLOAD_TOKEN') ?? '');
if ($expected === '') {
    fail(401, 'unauthorized (token belum dikonfigurasi)');
}
$provided = $_SERVER['HTTP_X_UPLOAD_TOKEN'] ?? '';
if (!hash_equals($expected, (string) $provided)) {
    fail(401, 'unauthorized');
}

$writer = new MySQLWriter();
$pdo = MySQLWriter::pdo();
$out = [];

try {
    $pdo->beginTransaction();

    // 1. merk TE -> Terada
    $st = $pdo->prepare("UPDATE products SET merk = 'Terada' WHERE merk = 'TE'");
    $st->execute();
    $out['merk_te_ke_terada'] = $st->rowCount();

    // 2. merk MEC JP -> MEC
    $st = $pdo->prepare("UPDATE products SET merk = 'MEC' WHERE merk = 'MEC JP'");
    $st->execute();
    $out['merk_mecjp_ke_mec'] = $st->rowCount();

    // 3. hapus baris brands 'TE' (bila tak ada lagi produk bermerk TE)
    $n = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE merk = 'TE'")->fetchColumn();
    if ($n === 0) {
        $st = $pdo->prepare("DELETE FROM brands WHERE name = 'TE'");
        $st->execute();
        $out['brands_te_dihapus'] = $st->rowCount();
    } else {
        $out['brands_te_dihapus'] = 0;
        $out['brands_te_note'] = "masih $n produk bermerk TE";
    }

    // 4. hapus baris brands 'MEC JP' (bila tak ada lagi produk bermerk MEC JP)
    $n = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE merk = 'MEC JP'")->fetchColumn();
    if ($n === 0) {
        $st = $pdo->prepare("DELETE FROM brands WHERE name = 'MEC JP'");
        $st->execute();
        $out['brands_mecjp_dihapus'] = $st->rowCount();
    } else {
        $out['brands_mecjp_dihapus'] = 0;
    }

    // 5. hapus 8-94908-840 (typo; duplikat Terada harga lebih rendah; harus 0 gambar)
    $st = $pdo->prepare("SELECT id FROM products WHERE kode_barang = '8-94908-840'");
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $out['hapus_8_94908_840'] = 'tidak ditemukan (mungkin sudah dihapus)';
    } else {
        $pid = (string) $row['id'];
        $st2 = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
        $st2->execute([$pid]);
        $imgCount = (int) $st2->fetchColumn();
        $out['hapus_8_94908_840_gambar'] = $imgCount;
        if ($imgCount > 0) {
            throw new Exception('8-94908-840 masih punya ' . $imgCount . ' gambar, batal hapus');
        }
        $st2 = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        $st2->execute([$pid]);
        $st2 = $pdo->prepare("DELETE FROM products WHERE kode_barang = '8-94908-840'");
        $st2->execute();
        $out['hapus_8_94908_840'] = 'dihapus (' . $st2->rowCount() . ' baris)';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('fix-merk-te gagal: ' . $e->getMessage());
    fail(500, 'gagal: ' . $e->getMessage());
}

// 6. rebuild cache (merge 8-94408-840-3 + merk baru tampil)
try {
    $builder = new CacheBuilder($writer, new Cache());
    $builder->rebuildProducts();
    $out['cache_rebuild'] = 'ok';
} catch (Throwable $e) {
    error_log('fix-merk-te rebuild gagal: ' . $e->getMessage());
    fail(500, 'DB ok tapi rebuild cache gagal: ' . $e->getMessage());
}

echo json_encode(['ok' => true] + $out);

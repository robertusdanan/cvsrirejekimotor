<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();
Auth::requireSuperAdmin();

// Column order/names here MUST stay in sync with MIGRASI_GUDANG_COLUMNS in
// migrasi.php and with the `gudang` table columns in supabase_schema.sql.
const MIGRASI_TEMPLATE_COLUMNS = [
    'kode_barang', 'nama_produk', 'nama_mobil', 'nama_lain', 'merk',
    'stok', 'harga_jual', 'harga_final', 'diskon_pct',
];

const MIGRASI_TEMPLATE_EXAMPLE = [
    'BRG-0001', 'Kampas Rem Depan', 'Avanza', 'Kampas Rem Set', 'Aspira',
    '25', '150000', '135000', '10',
];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="template_migrasi_gudang.csv"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
fputcsv($out, MIGRASI_TEMPLATE_COLUMNS);
fputcsv($out, MIGRASI_TEMPLATE_EXAMPLE);
fclose($out);

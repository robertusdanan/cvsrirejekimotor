<?php
declare(strict_types=1);
// DIAGNOSTIK SEMENTARA — hapus setelah dipakai. Cek ekstensi PostgreSQL di PHP server.
header('Content-Type: application/json');
echo json_encode([
    'php_version' => PHP_VERSION,
    'pdo_pgsql' => extension_loaded('pdo_pgsql'),
    'pgsql' => extension_loaded('pgsql'),
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'has_pgsql_ext' => array_values(array_filter(get_loaded_extensions(), static function ($e) {
        return stripos($e, 'pgsql') !== false || stripos($e, 'pdo') !== false;
    })),
]);

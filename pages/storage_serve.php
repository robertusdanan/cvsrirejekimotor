<?php
declare(strict_types=1);

/**
 * Streams a file from STORAGE_PATH. Reached as /storage/products/{id}/{size}/{file}.webp
 * Using PHP instead of a symlink avoids any dependency on symlink support/permissions,
 * which is inconsistent across shared hosting and unavailable by default on Windows.
 */

$relative = implode('/', array_slice($segments, 1));

// Prevent path traversal — only allow the exact character set our own filenames use.
if ($relative === '' || !preg_match('#^[A-Za-z0-9_\-./]+$#', $relative) || str_contains($relative, '..')) {
    http_response_code(404);
    exit;
}

$fullPath = rtrim(config('STORAGE_PATH'), '/') . '/' . $relative;
$realStorageRoot = realpath(rtrim(config('STORAGE_PATH'), '/'));
$realFullPath = realpath($fullPath);

if ($realFullPath === false || $realStorageRoot === false || !str_starts_with($realFullPath, $realStorageRoot)) {
    http_response_code(404);
    exit;
}

$extension = strtolower(pathinfo($realFullPath, PATHINFO_EXTENSION));
$mimeMap = [
    'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    // Hero background media (see /admin/hero.php) can also be a video.
    'mp4' => 'video/mp4', 'webm' => 'video/webm',
];
$mime = $mimeMap[$extension] ?? null;

if ($mime === null) {
    http_response_code(404);
    exit;
}

$size = filesize($realFullPath);
$isVideo = str_starts_with($mime, 'video/');

// Videos need byte-range support so the browser can start playback instantly
// and seek without downloading the whole file first.
if ($isVideo && !empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $start = $m[1] === '' ? 0 : (int) $m[1];
    $end = $m[2] === '' ? $size - 1 : (int) $m[2];
    $end = min($end, $size - 1);

    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }

    $length = $end - $start + 1;
    http_response_code(206);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . $length);
    header('Cache-Control: public, max-age=2592000, immutable');

    $handle = fopen($realFullPath, 'rb');
    fseek($handle, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = min(8192, $remaining);
        echo fread($handle, $chunk);
        $remaining -= $chunk;
        flush();
    }
    fclose($handle);
    exit;
}

header('Content-Type: ' . $mime);
if ($isVideo) {
    header('Accept-Ranges: bytes');
}
header('Cache-Control: public, max-age=2592000, immutable'); // filenames are content-addressed (random id), safe to cache long-term
header('Content-Length: ' . $size);
readfile($realFullPath);

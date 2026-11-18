<?php
declare(strict_types=1);

/**
 * ImageHandler — R2-ONLY (2026-11-18).
 *
 * Semua gambar/video produk & hero HANYA disimpan di Cloudflare R2
 * (bucket sri-rejeki-motor). TIDAK ADA lagi mirror lokal di server:
 * tidak ada tulis/hapus ke STORAGE_PATH. Resize dilakukan di memori,
 * hasilnya ditulis ke file temp, di-upload ke R2, lalu file temp dihapus.
 *
 * Konsekuensi: kredensial R2 (R2_ACCESS_KEY_ID / R2_SECRET_ACCESS_KEY
 * di ~/private/.env) WAJIB terisi — upload gagal dengan pesan jelas
 * bila R2 belum dikonfigurasi, supaya tidak ada gambar yang "berhasil"
 * di-upload tapi tidak bisa ditampilkan.
 */
final class ImageHandler
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 8 * 1024 * 1024; // 8MB raw upload from camera
    private const SIZES = [
        'thumbnail' => 320,
        'medium'    => 800,
        'large'     => 1600,
        // 'original' is stored as-is (re-encoded to WebP) for archival/backup purposes.
    ];

    /** R2 client, atau null bila kredensial R2 belum dikonfigurasi. */
    private static function r2Client(): ?R2WriteClient
    {
        if (!config('R2_ACCESS_KEY_ID') || !config('R2_SECRET_ACCESS_KEY')) {
            return null;
        }
        return new R2WriteClient(
            config('R2_ACCESS_KEY_ID'),
            config('R2_SECRET_ACCESS_KEY'),
            config('R2_S3_CLIENTS'),
            config('R2_BUCKET')
        );
    }

    private static function requireR2(): R2WriteClient
    {
        $r2 = self::r2Client();
        if ($r2 === null) {
            throw new RuntimeException(
                'Upload gambar memerlukan Cloudflare R2 — kredensial R2 belum dikonfigurasi di server (~/private/.env).'
            );
        }
        return $r2;
    }

    /**
     * Handles a single uploaded file ($_FILES['photo']) for a given product_config.id
     * (used as the storage folder key regardless of whether the product's master data
     * comes from Supabase or was entered manually). Returns the relative file paths
     * to store in product_images.file_path, or throws on validation failure.
     *
     * R2-only: tiap varian di-resize di memori, ditulis ke file temp, di-upload
     * ke R2, lalu file temp dihapus. Tidak ada file yang tersisa di server.
     */
    public static function handleUpload(array $fileEntry, string $productKey): array
    {
        if (!isset($fileEntry['tmp_name']) || $fileEntry['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload gagal atau tidak lengkap.');
        }
        if ($fileEntry['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Ukuran file terlalu besar (maks 8MB).');
        }

        // Validate actual image content, not just extension/declared MIME.
        $imageInfo = @getimagesize($fileEntry['tmp_name']);
        if ($imageInfo === false) {
            throw new RuntimeException('File bukan gambar yang valid.');
        }
        $mime = $imageInfo['mime'];
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Format gambar tidak didukung. Gunakan JPEG, PNG, atau WebP.');
        }

        $source = self::loadImage($fileEntry['tmp_name'], $mime);
        if ($source === false) {
            throw new RuntimeException('Gagal memproses gambar.');
        }

        $r2 = self::requireR2();
        $id = bin2hex(random_bytes(8)); // generated identifier, never trust the user's filename
        $paths = [];

        try {
            foreach (self::SIZES as $label => $maxDimension) {
                $key = "products/{$productKey}/{$label}/{$id}.webp";
                self::uploadResized($r2, $source, $maxDimension, $key, 82);
                $paths[$label] = $key;
            }

            // Store the original too (re-encoded, not the raw untrusted upload bytes).
            self::uploadResized($r2, $source, PHP_INT_MAX, "products/{$productKey}/original/{$id}.webp", 90);
            $paths['original'] = "products/{$productKey}/original/{$id}.webp";
        } finally {
            imagedestroy($source);
        }

        return $paths; // caller decides which variant path(s) to store in product_images
    }

    /**
     * Resize satu varian di memori -> tulis ke file temp -> upload ke R2 ->
     * hapus file temp. $maxDimension = PHP_INT_MAX berarti tanpa resize.
     */
    private static function uploadResized(R2WriteClient $r2, $source, int $maxDimension, string $key, int $quality): void
    {
        $resized = self::resize($source, $maxDimension);
        $tmp = tempnam(sys_get_temp_dir(), 'srm_img_');
        if ($tmp === false) {
            imagedestroy($resized);
            throw new RuntimeException('Gagal membuat file sementara untuk upload.');
        }
        try {
            imagewebp($resized, $tmp, $quality);
            $r2->putObjectFromFile($key, $tmp, 'image/webp');
        } finally {
            imagedestroy($resized);
            @unlink($tmp);
        }
    }

    /**
     * Instance wrapper used by the admin pages (product_new, product_edit,
     * hero). $folder is 'products/{product-id}' or 'hero'.
     *
     * Returns the stored variants plus 'path' (the display variant) and
     * 'url' (its full public URL). Callers store $res['path'] in
     * product_images / hero_slides.
     */
    public function uploadAndConvertWebP(array $fileEntry, string $folder): array
    {
        $key = preg_replace('#^products/#', '', trim($folder, '/'));
        $paths = self::handleUpload($fileEntry, (string) $key);
        $path = $paths['medium'] ?? $paths['large'] ?? $paths['original'] ?? '';
        return array_merge($paths, [
            'path' => $path,
            'url'  => $path !== '' ? self::publicUrl($path) : '',
        ]);
    }

    /**
     * Stores a video file as-is (no re-encode) for hero slides. Returns the
     * full public URL of the stored file. R2-only: file di-copy ke temp,
     * di-upload ke R2, lalu temp dihapus.
     */
    public function uploadDirectFile(array $fileEntry, string $folder): string
    {
        if (!isset($fileEntry['tmp_name']) || $fileEntry['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload gagal atau tidak lengkap.');
        }
        if ($fileEntry['size'] > 64 * 1024 * 1024) {
            throw new RuntimeException('Ukuran file terlalu besar (maks 64MB).');
        }
        $ext = strtolower(pathinfo((string) ($fileEntry['name'] ?? 'file'), PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'webm', 'mov'], true)) {
            throw new RuntimeException('Format video tidak didukung. Gunakan MP4 atau WebM.');
        }
        $r2 = self::requireR2();
        $id = bin2hex(random_bytes(8));
        $key = trim($folder, '/') . '/' . $id . '.' . $ext;
        $tmp = tempnam(sys_get_temp_dir(), 'srm_vid_');
        if ($tmp === false) {
            throw new RuntimeException('Gagal membuat file sementara untuk upload.');
        }
        try {
            // move_uploaded_file gagal untuk file yang bukan hasil upload
            // HTTP (mis. hasil ekstrak .zip di import massal) — copy jadi fallback.
            if (!@move_uploaded_file($fileEntry['tmp_name'], $tmp)
                && !@copy($fileEntry['tmp_name'], $tmp)) {
                throw new RuntimeException('Gagal menyimpan video.');
            }
            $mime = $ext === 'webm' ? 'video/webm' : 'video/mp4';
            $r2->putObjectFromFile($key, $tmp, $mime);
        } finally {
            @unlink($tmp);
        }
        return self::publicUrl($key);
    }

    /**
     * Deletes one stored image from R2 (R2-only, tidak ada mirror lokal).
     * Removes every size variant sharing the same file id, since the admin
     * deletes a whole image (not one variant).
     */
    public function deleteR2File(string $path): void
    {
        $key = ltrim($path, '/');
        $keys = [$key];
        if (preg_match('#^(products/.+/)(thumbnail|medium|large|original)/([^/]+\.webp)$#', $key, $m)) {
            foreach (['thumbnail', 'medium', 'large', 'original'] as $label) {
                $keys[] = $m[1] . $label . '/' . $m[3];
            }
            $keys = array_values(array_unique($keys));
        }
        $r2 = self::r2Client();
        if ($r2 === null) {
            error_log('R2 delete warning: kredensial R2 belum dikonfigurasi, hapus dilewati untuk ' . $key);
            return;
        }
        foreach ($keys as $k) {
            try {
                $r2->deleteObject($k);
            } catch (Throwable $e) {
                error_log('R2 delete warning: ' . $e->getMessage());
            }
        }
    }

    /**
     * Full public URL for an R2 key (same rule as the public mediaUrl()).
     * Kept here because ImageHandler is also loaded by contexts that never
     * include the public bootstrap (e.g. the Supabase webhook handler).
     * Tanpa R2_PUBLIC_URL, kembalikan '' (tidak ada lagi fallback /storage/).
     */
    private static function publicUrl(string $key): string
    {
        $key = trim($key, '/');
        if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return $key;
        }
        $r2Url = rtrim((string) config('R2_PUBLIC_URL'), '/');
        if ($r2Url !== '') {
            return "{$r2Url}/{$key}";
        }
        return '';
    }

    private static function loadImage(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                return @imagecreatefrompng($path);
            case 'image/webp':
                return @imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    private static function resize($source, int $maxDimension)
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxDimension / max($width, $height));
        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        return $canvas;
    }
}

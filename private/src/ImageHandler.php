<?php
declare(strict_types=1);

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

    /**
     * Handles a single uploaded file ($_FILES['photo']) for a given product_config.id
     * (used as the storage folder key regardless of whether the product's master data
     * comes from Supabase or was entered manually). Returns the relative file paths
     * to store in product_images.file_path, or throws on validation failure.
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

        $id = bin2hex(random_bytes(8)); // generated identifier, never trust the user's filename
        $storageRoot = rtrim(config('STORAGE_PATH'), '/') . "/products/{$productKey}";
        $paths = [];

        $r2 = null;
        if (config('R2_ACCESS_KEY_ID') && config('R2_SECRET_ACCESS_KEY')) {
            $r2 = new R2WriteClient(
                config('R2_ACCESS_KEY_ID'),
                config('R2_SECRET_ACCESS_KEY'),
                config('R2_S3_CLIENTS'),
                config('R2_BUCKET')
            );
        }

        foreach (self::SIZES as $label => $maxDimension) {
            $dir = "{$storageRoot}/{$label}";
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $resized = self::resize($source, $maxDimension);
            $filename = "{$id}.webp";
            $fullPath = "{$dir}/{$filename}";
            imagewebp($resized, $fullPath, 82);
            imagedestroy($resized);
            $paths[$label] = "products/{$productKey}/{$label}/{$filename}";

            if ($r2 !== null) {
                try {
                    $r2->putObjectFromFile("products/{$productKey}/{$label}/{$filename}", $fullPath, 'image/webp');
                } catch (Throwable $e) {
                    error_log('R2 upload warning: ' . $e->getMessage());
                }
            }
        }

        // Store the original too (re-encoded, not the raw untrusted upload bytes).
        $originalDir = "{$storageRoot}/original";
        if (!is_dir($originalDir)) {
            mkdir($originalDir, 0750, true);
        }
        $originalPath = "{$originalDir}/{$id}.webp";
        imagewebp($source, $originalPath, 90);
        $paths['original'] = "products/{$productKey}/original/{$id}.webp";

        if ($r2 !== null) {
            try {
                $r2->putObjectFromFile("products/{$productKey}/original/{$id}.webp", $originalPath, 'image/webp');
            } catch (Throwable $e) {
                error_log('R2 upload warning: ' . $e->getMessage());
            }
        }

        imagedestroy($source);

        return $paths; // caller decides which variant path(s) to store in product_images
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

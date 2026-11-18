<?php
declare(strict_types=1);

/**
 * Persistent local cache. Not TTL-based: a cache file stays valid until explicitly
 * invalidated/regenerated (by webhook or cron). Writes are atomic (tmp file + rename)
 * so the public site never reads a half-written file.
 */
final class Cache
{
    public $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? config('CACHE_PATH'), '/');
    }

    public function read(string $relativePath): ?array
    {
        $full = $this->basePath . '/' . ltrim($relativePath, '/');
        if (!is_file($full)) {
            return null;
        }
        $contents = @file_get_contents($full);
        if ($contents === false) {
            return null;
        }
        $data = json_decode($contents, true);
        return is_array($data) ? $data : null;
    }

    /** Atomic write: write to temp file in same directory, then rename. */
    public function write(string $relativePath, array $data): void
    {
        $full = $this->basePath . '/' . ltrim($relativePath, '/');
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $tmp = $full . '.tmp-' . bin2hex(random_bytes(6));
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new RuntimeException("Failed to encode cache payload for {$relativePath}");
        }

        $bytes = file_put_contents($tmp, $payload, LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException("Failed to write temp cache file: {$tmp}");
        }

        if (!rename($tmp, $full)) {
            @unlink($tmp);
            throw new RuntimeException("Failed to atomically move cache file into place: {$full}");
        }
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->basePath . '/' . ltrim($relativePath, '/'));
    }

    /**
     * Records the last rebuild time/version for a resource, purely for
     * diagnostics (e.g. showing "cache last refreshed at ..." in the admin
     * panel). Stored as a small JSON file alongside the other cache files —
     * no database involved, so there is nothing here to keep in sync with
     * Supabase.
     */
    public function bumpVersion(string $resource): void
    {
        try {
            $current = $this->read('_meta/' . $resource . '.json') ?? ['version' => 0];
            $this->write('_meta/' . $resource . '.json', [
                'version'    => ((int) ($current['version'] ?? 0)) + 1,
                'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        } catch (Throwable $e) {
            error_log('bumpVersion skipped: ' . $e->getMessage());
        }
    }
}

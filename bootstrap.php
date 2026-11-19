<?php
declare(strict_types=1);

/**
 * PHP 7.4+ compatibility polyfills for functions added in PHP 8.0+.
 * Declared first so that ANY file using these (including admin pages and
 * config.php) can rely on them without crashing on shared hosting that
 * hasn't migrated to PHP 8.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $diff = strlen($haystack) - strlen($needle);
        return $diff >= 0 && substr_compare($haystack, $needle, $diff, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('array_is_list')) {
    // array_is_list() baru ada di PHP 8.1; webhook deploy jalan di PHP 7.4.
    // Tanpa ini, rebuild cache fatal ("Call to undefined function
    // array_is_list()") begitu ada tabel registry yang berisi baris.
    function array_is_list(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Returns the absolute path to the sibling `private/` directory.
 * Checks the parent directory FIRST (dirname(__DIR__) . '/private'), since
 * the production layout keeps private/ as a sibling of public_html
 * (outside the web root, for secrets + runtime state). Falls back to a local
 * __DIR__ . '/private' only if no sibling copy exists, so a stray/duplicate
 * folder accidentally left inside public_html can never shadow the real
 * data and config again.
 *
 * NOTE (2026-11-18): kode (config.php, src/) TIDAK LAGI dimuat dari sini —
 * semuanya tinggal di repo (public_html/config, public_html/src) dan
 * di-deploy otomatis. privatePath() hanya dipakai untuk hal yang memang
 * harus di luar web root: secrets (.env) + runtime state (cache, data)
 * + fallback legacy manifest hero.
 */
if (!function_exists('privatePath')) {
    function privatePath(string $subpath = ''): string
    {
        static $baseDir = null;
        if ($baseDir === null) {
            $parentPrivate = dirname(__DIR__) . '/private';
            $localPrivate  = __DIR__ . '/private';
            if (is_dir($parentPrivate)) {
                $baseDir = $parentPrivate;
            } elseif (is_dir($localPrivate)) {
                $baseDir = $localPrivate;
            } else {
                $baseDir = $parentPrivate;
            }
        }
        return $subpath !== '' ? $baseDir . '/' . ltrim($subpath, '/') : $baseDir;
    }
}

// Kode dimuat dari repo (public_html/config, public_html/src) — ikut
// ter-deploy otomatis di setiap push. Secrets tetap dibaca config.php
// dari sibling ~/private/.env (lihat config/config.php).
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/Cache.php';
require_once __DIR__ . '/src/R2WriteClient.php';
// MySQLWriter + StatistikKunjungan: require kondisional + stub,
// supaya footer tetap tampil (0/0) bila file sedang bermasalah.
if (is_file(__DIR__ . '/src/MySQLWriter.php')) {
    require_once __DIR__ . '/src/MySQLWriter.php';
}
if (is_file(__DIR__ . '/src/StatistikKunjungan.php')) {
    require_once __DIR__ . '/src/StatistikKunjungan.php';
}
if (!class_exists('StatistikKunjungan')) {
    // Fallback tanpa-op: footer menampilkan 0/0 sampai file asli di-upload.
    final class StatistikKunjungan
    {
        public function catat(): void {}
        public function baca(): array { return ['hari_ini' => 0, 'total' => 0]; }
    }
}

if (!function_exists('cache')) {
    function cache(): Cache
    {
        static $cache = null;
        if ($cache === null) {
            $cache = new Cache();
        }
        return $cache;
    }
}

if (!function_exists('statistikKunjungan')) {
    function statistikKunjungan(): StatistikKunjungan
    {
        static $stats = null;
        if ($stats === null) {
            $stats = new StatistikKunjungan();
        }
        return $stats;
    }
}

if (!function_exists('render')) {
    function render(string $template, array $vars = []): void
    {
        extract($vars);
        require __DIR__ . '/templates/header.php';
        require __DIR__ . "/templates/{$template}.php";
        require __DIR__ . '/templates/footer.php';
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rupiah')) {
    function rupiah(?int $value): string
    {
        if ($value === null) {
            return '-';
        }
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}

if (!function_exists('mediaUrl')) {
    function mediaUrl(?string $path): string
    {
        $path = trim((string) $path, '/');
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $r2Url = rtrim((string) config('R2_PUBLIC_URL'), '/');
        if ($r2Url !== '') {
            return "{$r2Url}/{$path}";
        }
        // R2-only (2026-11-18): tidak ada lagi fallback /storage/ — galeri
        // produk hanya tersimpan di Cloudflare R2.
        return '';
    }
}

if (!function_exists('heroManifestPath')) {
    /**
     * Manifest hero tinggal di runtime state: ~/private/data/hero/manifest.json
     * (di luar public_html supaya tidak tertimpa tiap deploy; ditulis manual/
     * admin, bukan dari repo).
     */
    function heroManifestPath(): string
    {
        return rtrim((string) config('DATA_PATH'), '/') . '/hero/manifest.json';
    }
}

if (!function_exists('heroManifest')) {
    function heroManifest(): array
    {
        $path = heroManifestPath();
        if (!is_file($path)) {
            // Fallback legacy ke lokasi lama (~/private/storage/hero/)
            // bila file baru belum dipindah — hapus fallback ini setelah
            // manifest dipindah ke data/hero/.
            $legacy = privatePath('storage/hero/manifest.json');
            if (is_file($legacy)) {
                $path = $legacy;
            }
        }
        if (!is_file($path)) {
            return ['settings' => [], 'items' => []];
        }
        $raw = @file_get_contents($path);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return ['settings' => [], 'items' => []];
        }
        $data['settings'] = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $data['items'] = is_array($data['items'] ?? null) ? $data['items'] : [];
        return $data;
    }
}

if (!function_exists('heroActiveSlides')) {
    function heroActiveSlides(): array
    {
        $manifest = heroManifest();
        $items = array_values(array_filter($manifest['items'], static function ($item) {
            return !empty($item['active']) && !empty($item['file']) && !empty($item['type']);
        }));
        usort($items, static function ($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });
        return $items;
    }
}

if (!function_exists('companyInitials')) {
    function companyInitials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'TO';
        }
        $skip = ['CV', 'PT', 'UD', 'CO', 'TOKO'];
        $words = preg_split('/\s+/', $name) ?: [];
        $letters = [];
        foreach ($words as $w) {
            if (in_array(mb_strtoupper($w), $skip, true)) {
                continue;
            }
            $letters[] = mb_substr($w, 0, 1);
            if (count($letters) >= 3) {
                break;
            }
        }
        if (!$letters) {
            $letters = [mb_substr($name, 0, 1)];
        }
        return mb_strtoupper(implode('', $letters));
    }
}

if (!function_exists('assetUrl')) {
    function assetUrl(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $r2Url = config('R2_PUBLIC_URL');
        if ($r2Url !== null && $r2Url !== '') {
            return rtrim($r2Url, '/') . '/' . $cleanPath;
        }
        return '/' . $cleanPath;
    }
}
if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}

if (!function_exists('displayProductName')) {
    /**
     * Nama produk seperti yang ditampilkan di website. Data sumber (export
     * Accurate / database) dipertahankan apa adanya; koreksi ejaan hanya di
     * lapisan tampil, supaya nama yang dilihat pengunjung selalu benar dan
     * tidak hilang saat import ulang.
     */
    function displayProductName(?string $name): string
    {
        $name = $name ?? '';
        // "ACK FULL SET" di export Accurate adalah typo dari "PACK FULL SET"
        // (gasket set overhaul mesin PS100, kode ME999855).
        return str_ireplace('ACK FULL SET', 'PACK FULL SET', $name);
    }
}

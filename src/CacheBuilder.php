<?php
declare(strict_types=1);

/**
 * Core cache-building logic reading from dedicated Supabase tables:
 *  - products & product_images
 *  - company_profile
 *  - marketplaces
 *  - hero_slides
 */

// slugify() juga didefinisikan di bootstrap.php (guarded di kedua tempat).
// CacheBuilder dipakai LANGSUNG oleh cpanel-deploy.php (langkah 3),
// webhook Supabase, dan scripts/*.php TANPA bootstrap.php — jadi definisi
// di sini wajib ada. Tanpa ini rebuildAll() gagal dengan
// "Call to undefined function slugify()" dan katalog tetap kosong.
// Ditemukan 2026-11-18 dari log delivery webhook GitHub setelah restruktur
// (deploy 84e25f01: cache_rebuild = error, padahal key Supabase live sehat).
if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}

final class CacheBuilder
{
    public $writer;
    public $cache;

    public function __construct(
        SupabaseWriter $writer,
        ?Cache $cache = null
    ) {
        $this->writer = $writer;
        $this->cache = $cache ?? new Cache();
    }

    public function rebuildAll(): void
    {
        $errors = [];

        try {
            $this->rebuildProducts();
        } catch (Throwable $e) {
            $errors[] = 'products: ' . $e->getMessage();
        }

        try {
            $this->rebuildCompany();
        } catch (Throwable $e) {
            $errors[] = 'company: ' . $e->getMessage();
        }

        try {
            $this->rebuildMarketplaces();
        } catch (Throwable $e) {
            $errors[] = 'marketplaces: ' . $e->getMessage();
        }

        try {
            $this->rebuildHero();
        } catch (Throwable $e) {
            $errors[] = 'hero: ' . $e->getMessage();
        }

        $this->cache->write('manifest.json', ['generated_at' => date('c'), 'source' => 'supabase_v2']);

        if ($errors !== []) {
            throw new RuntimeException('rebuildAll partial error: ' . implode(' | ', $errors));
        }
    }

    public function rebuildProducts(): array
    {
        $rows = $this->writer->getProducts();
        $allPublic = [];
        $slugs = [];

        foreach ($rows as $row) {
            $kode = trim((string) ($row['kode_barang'] ?? ''));
            $nama = trim((string) ($row['nama_produk'] ?? ''));
            if ($kode === '' || $nama === '') {
                continue;
            }

            $slug = slugify($nama . '-' . $kode);
            $base = $slug;
            $n = 1;
            while (isset($slugs[$slug])) {
                $slug = $base . '-' . (++$n);
            }
            $slugs[$slug] = true;

            $images = [];
            $rawImages = is_array($row['product_images'] ?? null) ? $row['product_images'] : [];
            usort($rawImages, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

            foreach ($rawImages as $img) {
                if (!empty($img['path'])) {
                    $images[] = [
                        'path'    => $img['path'],
                        'primary' => (bool) ($img['is_primary'] ?? false),
                    ];
                }
            }

            $mps = is_array($row['marketplaces'] ?? null) ? $row['marketplaces'] : (is_string($row['marketplaces'] ?? null) ? json_decode($row['marketplaces'], true) : []);

            $product = [
                'id'           => $row['id'] ?? $kode,
                'slug'         => $slug,
                'kode_barang'  => $kode,
                'nama_produk'  => $nama,
                'nama_mobil'   => !empty($row['nama_mobil']) ? $row['nama_mobil'] : null,
                'nama_lain'    => !empty($row['nama_lain']) ? $row['nama_lain'] : null,
                'merk'         => !empty($row['merk']) ? $row['merk'] : null,
                'category'     => !empty($row['category']) ? $row['category'] : null,
                'stok'         => (float) ($row['stok'] ?? 0),
                'harga_jual'   => (int) ($row['harga_jual'] ?? 0),
                'description'  => $row['description'] ?? null,
                'featured'     => (bool) ($row['featured'] ?? false),
                'sort_order'   => (int) ($row['sort_order'] ?? 0),
                'images'       => $images,
                'marketplaces' => is_array($mps) ? $mps : [],
                'updated_at'   => $row['updated_at'] ?? date('c'),
            ];

            $this->cache->write("products/{$slug}.json", $product);
            $allPublic[] = $product;
        }

        usort($allPublic, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        $index = array_map(function ($p) {
            $primaryImg = null;
            foreach ($p['images'] as $img) {
                if (!empty($img['primary'])) {
                    $primaryImg = $img['path'];
                    break;
                }
            }
            if ($primaryImg === null && !empty($p['images'])) {
                $primaryImg = $p['images'][0]['path'];
            }
            return [
                'id'            => $p['id'],
                'slug'          => $p['slug'],
                'nama_produk'   => $p['nama_produk'],
                'kode_barang'   => $p['kode_barang'],
                'merk'          => $p['merk'],
                'nama_mobil'    => $p['nama_mobil'],
                'category'      => $p['category'],
                'harga_jual'    => $p['harga_jual'],
                'stok'          => $p['stok'],
                'primary_image' => $primaryImg,
            ];
        }, $allPublic);

        $this->cache->write('products/index.json', ['count' => count($index), 'items' => $index]);

        $featured = array_values(array_filter($allPublic, fn ($p) => $p['featured']));
        $this->cache->write('homepage/featured.json', ['items' => array_slice($featured, 0, 12)]);

        $this->cache->write('brands/index.json', ['items' => array_values(array_unique(array_filter(array_column($index, 'merk'))))]);
        $this->cache->write('vehicles/index.json', ['items' => array_values(array_unique(array_filter(array_column($index, 'nama_mobil'))))]);
        $this->cache->write('categories/index.json', ['items' => array_values(array_unique(array_filter(array_column($index, 'category'))))]);

        // Remove orphan product files
        $dir = rtrim((string) config('CACHE_PATH'), '/') . '/products';
        if (is_dir($dir)) {
            $validSlugs = array_keys($slugs);
            foreach (glob($dir . '/*.json') ?: [] as $f) {
                if ($f === $dir . '/index.json') {
                    continue;
                }
                $s = basename($f, '.json');
                if (!in_array($s, $validSlugs, true)) {
                    @unlink($f);
                }
            }
        }

        $this->cache->bumpVersion('products');
        $this->cache->bumpVersion('homepage');

        return $allPublic;
    }

    public function rebuildCompany(): void
    {
        $data = $this->writer->getCompanyProfile();
        $this->cache->write('company/profile.json', $data);
        $this->cache->bumpVersion('company');
    }

    public function rebuildMarketplaces(): void
    {
        $rows = $this->writer->getMarketplaces();
        $this->cache->write('marketplaces/index.json', ['items' => $rows]);
        $this->cache->bumpVersion('marketplaces');
    }

    public function rebuildHero(): void
    {
        $slides = $this->writer->getHeroSlides();
        $this->cache->write('homepage/hero.json', ['items' => $slides]);
        $this->cache->bumpVersion('homepage');
    }
}

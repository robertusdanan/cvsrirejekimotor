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

// array_is_list() baru ada di PHP 8.1; webhook deploy jalan di PHP 7.4.
// Definisi di SINI (bukan hanya di config.php) karena file ini dimuat
// langsung oleh cpanel-deploy.php langkah 3 — terbukti di deploy e560303
// & f27a84a: polyfill di config.php tidak berlaku saat rebuild, rebuild
// fatal ("Call to undefined function array_is_list()") begitu ada tabel
// registry yang berisi baris, dan cache tidak pernah ter-rebuild.
if (!function_exists('array_is_list')) {
    function array_is_list(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
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

        $this->writeTaxonomyIndexes($index);

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

    /**
     * Index taksonomi untuk dropdown filter /produk (brands, vehicles,
     * categories). Satu baris = SATU nilai, lengkap dengan jumlah produknya,
     * supaya pengunjung tidak bingung:
     * - vehicles: nama_mobil berisi daftar dipisah koma ("Livina, Xpander,
     *   Raize") -> dipecah per kendaraan, jadi tiap kendaraan jadi satu
     *   opsi yang mencakup SEMUA produknya (bukan 1 produk).
     * - brands/categories: satu nilai per produk, dihitung kemunculannya.
     *
     * Sumber nilai = data produk (otomatis mendeteksi apa yang ada di produk);
     * tabel registry Supabase (brands/vehicles/categories) dipakai untuk nama
     * resmi + singkatan + urutan, bila tabelnya sudah ada. Bila tabel belum
     * ada (belum dijalankan di SQL Editor), fallback ke nilai produk apa
     * adanya — filter tetap jalan, hanya tanpa nama resmi/singkatan.
     * Nilai produk yang belum ada di tabel disisipkan (best-effort) supaya
     * registry selalu lengkap — KECUALI categories: registry kategori
     * dikurasi (taksonomi baru), jangan sampai kategori sampah Accurate
     * (mis. "GENUINE PARTS", "SANYCO") tersisip otomatis.
     */
    private function writeTaxonomyIndexes(array $index): void
    {
        // --- Merek: satu nilai per produk ---
        $brandCounts = [];
        foreach ($index as $p) {
            $m = trim((string) ($p['merk'] ?? ''));
            if ($m !== '') {
                $brandCounts[$m] = ($brandCounts[$m] ?? 0) + 1;
            }
        }
        $brandRows = $this->readTaxonomyTable('brands', 'name,short_name,sort_order');
        $this->cache->write('brands/index.json', ['items' => $this->taxonomyItems($brandCounts, $brandRows)]);
        $this->syncTaxonomyTable('brands', $brandCounts, $brandRows);

        // --- Kendaraan: pecah daftar koma per kendaraan ---
        $vehCounts = [];
        foreach ($index as $p) {
            foreach (explode(',', (string) ($p['nama_mobil'] ?? '')) as $v) {
                $v = trim($v);
                if ($v !== '') {
                    $vehCounts[$v] = ($vehCounts[$v] ?? 0) + 1;
                }
            }
        }
        $vehRows = $this->readTaxonomyTable('vehicles', 'name,brand,sort_order');
        $this->cache->write('vehicles/index.json', ['items' => $this->taxonomyItems($vehCounts, $vehRows)]);
        $this->syncTaxonomyTable('vehicles', $vehCounts, $vehRows);

        // --- Kategori: satu nilai per produk (tanpa auto-sisip) ---
        $catCounts = [];
        foreach ($index as $p) {
            $c = trim((string) ($p['category'] ?? ''));
            if ($c !== '') {
                $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
            }
        }
        $catRows = $this->readTaxonomyTable('categories', 'name,sort_order');
        $this->cache->write('categories/index.json', ['items' => $this->taxonomyItems($catCounts, $catRows)]);
    }

    /** Baca tabel registry; gagal (tabel belum ada) = array kosong (fallback). */
    private function readTaxonomyTable(string $table, string $cols): array
    {
        try {
            $res = $this->writer->request(
                'GET',
                '/rest/v1/' . $table . '?select=' . $cols . '&order=sort_order.asc,name.asc&limit=1000'
            );
        } catch (Throwable $e) {
            error_log('taxonomy read ' . $table . ' gagal: ' . $e->getMessage());
            return [];
        }
        if (!is_array($res) || isset($res['message']) || isset($res['error']) || !array_is_list($res)) {
            return [];
        }
        return $res;
    }

    /** Gabungkan hitungan produk dengan baris registry (nama resmi + urutan). */
    private function taxonomyItems(array $counts, array $rows): array
    {
        $byName = [];
        foreach ($rows as $r) {
            $byName[strtolower(trim((string) ($r['name'] ?? '')))] = $r;
        }
        $items = [];
        foreach ($counts as $value => $count) {
            $row = $byName[strtolower((string) $value)] ?? null;
            // (string): kunci array PHP mengubah string numerik ("555") jadi
            // int — paksa kembali ke string supaya JSON + filter konsisten.
            $items[] = [
                'value' => (string) $value,
                'name' => ($row['name'] ?? '') !== '' ? (string) $row['name'] : (string) $value,
                'short_name' => $row['short_name'] ?? null,
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
                'count' => $count,
            ];
        }
        usort($items, function ($a, $b) {
            $sa = $a['sort_order'];
            $sb = $b['sort_order'];
            if ($sa !== null && $sb !== null && $sa !== $sb) {
                return $sa <=> $sb;
            }
            if ($sa !== null) {
                return -1;
            }
            if ($sb !== null) {
                return 1;
            }
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });
        return $items;
    }

    /** Sisipkan nilai produk yang belum ada di registry (auto-detect). Gagal = diam. */
    private function syncTaxonomyTable(string $table, array $counts, array $rows): void
    {
        try {
            $known = [];
            foreach ($rows as $r) {
                $known[strtolower(trim((string) ($r['name'] ?? '')))] = true;
            }
            $missing = [];
            foreach (array_keys($counts) as $value) {
                if (!isset($known[strtolower((string) $value)])) {
                    // sort_order besar supaya nilai baru muncul di akhir, bukan di atas.
                    $missing[] = ['name' => $value, 'sort_order' => 9999];
                }
            }
            if ($missing === []) {
                return;
            }
            $this->writer->request(
                'POST',
                '/rest/v1/' . $table . '?on_conflict=name',
                $missing,
                ['Prefer: return=minimal,resolution=merge-duplicates']
            );
        } catch (Throwable $e) {
            error_log('taxonomy sync ' . $table . ' gagal: ' . $e->getMessage());
        }
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

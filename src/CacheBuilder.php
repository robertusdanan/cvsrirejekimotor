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

    /**
     * MERGE_GROUPS — produk yang sebenarnya SAMA (kode barang di Accurate
     * ditulis berbeda: strip/huruf/awalan merk), digabung jadi SATU produk
     * kanonis dengan pilihan merk (variants).
     *
     * Format: kode kanonis => daftar kode anggota (urutan = urutan varian;
     * anggota PERTAMA = kanonis/default). Kode kanonis = bentuk OEM paling
     * bersih. Dua baris TIDAK BISA memegang kode yang sama di Supabase
     * (kode_barang UNIQUE), jadi penggabungan dilakukan di lapisan
     * cache/website — semua 1000 baris tetap ada di Supabase.
     *
     * Grup 2 & 6: kedua anggota bermerk SAMA (TE) dengan harga berbeda
     * (kemungkinan salah ketik di Accurate) -> digabung jadi SATU varian TE
     * dengan harga TERENDAH.
     */
    private const MERGE_GROUPS = [
        'MB109936' => ['MB109936', 'TE-MB109936', 'TEMB-109936'],
        '8-94223-366TE' => ['8-94223-366TE', '8-94223-366-TE'],
        '04465-0K100' => ['04465-0K100', '044650K100'],
        '04495-0K040' => ['04495-0K040', '044950K040'],
        'MB489484' => ['MB489484', 'TEMB489484'],
        'MT141213' => ['MT141213', 'TEMT-141213'],
        '48632-27010' => ['48632-27010', 'TE48632-27010'],
        'GAPMW030499' => ['GAPMW030499', 'GWMW030499'],
        'MH024000' => ['MH024000', 'GAPMH024000'],
    ];

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

        // --- Kelompokkan baris: anggota grup varian vs standalone ---
        $memberToGroup = [];
        foreach (self::MERGE_GROUPS as $canonical => $members) {
            foreach ($members as $m) {
                $memberToGroup[$m] = $canonical;
            }
        }
        $grouped = [];
        $standalone = [];
        foreach ($rows as $row) {
            $kode = trim((string) ($row['kode_barang'] ?? ''));
            if ($kode === '') {
                continue;
            }
            $canon = $memberToGroup[$kode] ?? null;
            if ($canon !== null) {
                $grouped[$canon][] = $row;
            } else {
                $standalone[] = $row;
            }
        }

        $allPublic = [];
        $slugs = [];
        $redirects = [];

        // Produk standalone: seperti sebelumnya.
        foreach ($standalone as $row) {
            $product = $this->buildSingleProduct($row, $slugs);
            if ($product !== null) {
                $allPublic[] = $product;
            }
        }

        // Produk gabungan: satu kanonis + variants per merk.
        foreach ($grouped as $canonicalKode => $members) {
            $product = $this->buildMergedProduct($canonicalKode, $members, $slugs, $redirects);
            if ($product !== null) {
                $allPublic[] = $product;
            }
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
            // primary_image: gambar varian default; bila varian default
            // belum bergambar, pakai gambar varian lain yang ada supaya
            // kartu tidak kosong padahal ada varian yang bergambar.
            if ($primaryImg === null && !empty($p['variants'])) {
                foreach ($p['variants'] as $v) {
                    if (!empty($v['primary_image'])) {
                        $primaryImg = $v['primary_image'];
                        break;
                    }
                }
            }
            $item = [
                'id' => $p['id'],
                'slug' => $p['slug'],
                'nama_produk' => $p['nama_produk'],
                'kode_barang' => $p['kode_barang'],
                'merk' => $p['merk'],
                'nama_mobil' => $p['nama_mobil'],
                'nama_lain' => $p['nama_lain'],
                'category' => $p['category'],
                'harga_jual' => $p['harga_jual'],
                'stok' => $p['stok'],
                'primary_image' => $primaryImg,
            ];
            if (!empty($p['variants'])) {
                $item['variants'] = array_map(function ($v) {
                    return [
                        'kode_barang' => $v['kode_barang'],
                        'merk' => $v['merk'],
                        'harga_jual' => $v['harga_jual'],
                        'primary_image' => $v['primary_image'],
                    ];
                }, $p['variants']);
            }
            return $item;
        }, $allPublic);

        $this->cache->write('products/index.json', ['count' => count($index), 'items' => $index]);

        // VERIFIKASI (2026-11-18): baca ulang index yang baru ditulis dan
        // pastikan isinya cocok dengan yang baru dibangun — cache TIDAK BOLEH
        // diam-diam basi. Bila jumlah produk / jumlah produk bergambar tidak
        // cocok, gagalkan rebuild supaya admin melihat error yang jelas,
        // bukan situs yang menampilkan data lama tanpa ada yang sadar.
        $verify = $this->cache->read('products/index.json');
        $verifyItems = is_array($verify['items'] ?? null) ? $verify['items'] : [];
        $expectWithImage = 0;
        foreach ($allPublic as $pp) {
            $has = !empty($pp['images']);
            if (!$has && !empty($pp['variants'])) {
                foreach ($pp['variants'] as $v) {
                    if (!empty($v['images'])) {
                        $has = true;
                        break;
                    }
                }
            }
            if ($has) {
                $expectWithImage++;
            }
        }
        $gotWithImage = 0;
        foreach ($verifyItems as $vi) {
            if (!empty($vi['primary_image'])) {
                $gotWithImage++;
            }
        }
        if (count($verifyItems) !== count($allPublic) || $gotWithImage !== $expectWithImage) {
            throw new RuntimeException(
                'Verifikasi cache gagal: index berisi ' . count($verifyItems) . '/' . count($allPublic)
                . ' produk dan ' . $gotWithImage . '/' . $expectWithImage . ' bergambar.'
            );
        }

        $featured = array_values(array_filter($allPublic, fn ($p) => $p['featured']));
        $this->cache->write('homepage/featured.json', ['items' => array_slice($featured, 0, 12)]);

        $this->writeTaxonomyIndexes($index);

        // Redirects: slug lama (varian non-kanonis) -> slug kanonis.
        if ($redirects !== []) {
            $this->cache->write('products/redirects.json', ['redirects' => $redirects]);
        }

        // Remove orphan product files (kecuali index.json + redirects.json)
        $dir = rtrim((string) config('CACHE_PATH'), '/') . '/products';
        if (is_dir($dir)) {
            $validSlugs = array_keys($slugs);
            foreach (glob($dir . '/*.json') ?: [] as $f) {
                if ($f === $dir . '/index.json' || $f === $dir . '/redirects.json') {
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

    /** Bangun SATU produk dari SATU baris (tanpa grup). Null bila kode/nama kosong. */
    private function buildSingleProduct(array $row, array &$slugs): ?array
    {
        $kode = trim((string) ($row['kode_barang'] ?? ''));
        $nama = trim((string) ($row['nama_produk'] ?? ''));
        if ($kode === '' || $nama === '') {
            return null;
        }
        $slug = $this->uniqueSlug(slugify($nama . '-' . $kode), $slugs);
        $product = [
            'id' => $row['id'] ?? $kode,
            'slug' => $slug,
            'kode_barang' => $kode,
            'nama_produk' => $nama,
            'nama_mobil' => !empty($row['nama_mobil']) ? $row['nama_mobil'] : null,
            'nama_lain' => !empty($row['nama_lain']) ? $row['nama_lain'] : null,
            'merk' => !empty($row['merk']) ? $row['merk'] : null,
            'category' => !empty($row['category']) ? $row['category'] : null,
            'stok' => (float) ($row['stok'] ?? 0),
            'harga_jual' => (int) ($row['harga_jual'] ?? 0),
            'description' => $row['description'] ?? null,
            'featured' => (bool) ($row['featured'] ?? false),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'images' => $this->rowImages($row),
            'marketplaces' => $this->rowMarketplaces($row),
            'updated_at' => $row['updated_at'] ?? date('c'),
        ];
        $this->cache->write("products/{$slug}.json", $product);
        return $product;
    }

    /**
     * Bangun SATU produk kanonis dari beberapa baris anggota grup.
     * $members = baris anggota (urutan = urutan MERGE_GROUPS; anggota
     * pertama = kanonis/default). Mengisi $redirects dengan
     * slug-lama-anggota => slug-kanonis. Null bila tidak ada anggota valid.
     */
    private function buildMergedProduct(string $canonicalKode, array $members, array &$slugs, array &$redirects): ?array
    {
        $order = self::MERGE_GROUPS[$canonicalKode] ?? [];
        $byKode = [];
        foreach ($members as $m) {
            $k = trim((string) ($m['kode_barang'] ?? ''));
            if ($k !== '') {
                $byKode[$k] = $m;
            }
        }
        $ordered = [];
        foreach ($order as $k) {
            if (isset($byKode[$k])) {
                $ordered[] = $byKode[$k];
            }
        }
        foreach ($byKode as $k => $m) {
            if (!in_array($k, $order, true)) {
                $ordered[] = $m;
            }
        }
        if ($ordered === []) {
            return null;
        }
        $primary = $ordered[0];

        // Field bersama dari anggota kanonis (pertama); bila kosong, ambil
        // dari anggota lain yang punya nilai.
        $nama = trim((string) ($primary['nama_produk'] ?? ''));
        $namaMobil = !empty($primary['nama_mobil']) ? $primary['nama_mobil'] : null;
        $namaLain = !empty($primary['nama_lain']) ? $primary['nama_lain'] : null;
        $category = !empty($primary['category']) ? $primary['category'] : null;
        $description = $primary['description'] ?? null;
        $featured = (bool) ($primary['featured'] ?? false);
        $sortOrder = (int) ($primary['sort_order'] ?? 0);
        $updatedAt = $primary['updated_at'] ?? date('c');
        foreach ($ordered as $m) {
            if ($nama === '') {
                $nama = trim((string) ($m['nama_produk'] ?? ''));
            }
            if ($namaMobil === null && !empty($m['nama_mobil'])) {
                $namaMobil = $m['nama_mobil'];
            }
            if ($namaLain === null && !empty($m['nama_lain'])) {
                $namaLain = $m['nama_lain'];
            }
            if ($category === null && !empty($m['category'])) {
                $category = $m['category'];
            }
            if (($description === null || $description === '') && !empty($m['description'])) {
                $description = $m['description'];
            }
            if (!$featured && !empty($m['featured'])) {
                $featured = true;
            }
            if ($sortOrder === 0 && !empty($m['sort_order'])) {
                $sortOrder = (int) $m['sort_order'];
            }
        }
        if ($nama === '') {
            return null;
        }

        $slug = $this->uniqueSlug(slugify($nama . '-' . $canonicalKode), $slugs);

        // Variants: satu per merk (gabungkan anggota bermerk sama -> harga terendah).
        $variantsByMerk = [];
        foreach ($ordered as $m) {
            $mkode = trim((string) ($m['kode_barang'] ?? ''));
            $merk = !empty($m['merk']) ? trim((string) $m['merk']) : null;
            $harga = (int) ($m['harga_jual'] ?? 0);
            $imgs = $this->rowImages($m);
            $key = $merk !== null ? strtolower($merk) : ('__kode__:' . $mkode);
            if (!isset($variantsByMerk[$key])) {
                $variantsByMerk[$key] = [
                    'kode_barang' => $mkode,
                    'merk' => $merk,
                    'harga_jual' => $harga,
                    'images' => $imgs,
                    'primary_image' => $this->primaryImage($imgs),
                ];
            } else {
                if ($harga < $variantsByMerk[$key]['harga_jual']) {
                    $variantsByMerk[$key]['harga_jual'] = $harga;
                    $variantsByMerk[$key]['kode_barang'] = $mkode;
                }
                $seen = [];
                foreach ($variantsByMerk[$key]['images'] as $im) {
                    $seen[$im['path']] = true;
                }
                foreach ($imgs as $im) {
                    if (!isset($seen[$im['path']])) {
                        $variantsByMerk[$key]['images'][] = $im;
                        $seen[$im['path']] = true;
                    }
                }
                $variantsByMerk[$key]['primary_image'] = $this->primaryImage($variantsByMerk[$key]['images']);
            }
        }
        $variants = array_values($variantsByMerk);
        $default = $variants[0];

        // Redirects: slug lama tiap anggota non-kanonis -> slug kanonis.
        foreach ($ordered as $m) {
            $mkode = trim((string) ($m['kode_barang'] ?? ''));
            $mnama = trim((string) ($m['nama_produk'] ?? ''));
            if ($mkode === '' || $mnama === '' || $mkode === $canonicalKode) {
                continue;
            }
            $oldSlug = slugify($mnama . '-' . $mkode);
            if ($oldSlug !== '' && $oldSlug !== $slug) {
                $redirects[$oldSlug] = $slug;
            }
        }

        $product = [
            'id' => $primary['id'] ?? $canonicalKode,
            'slug' => $slug,
            'kode_barang' => $canonicalKode,
            'nama_produk' => $nama,
            'nama_mobil' => $namaMobil,
            'nama_lain' => $namaLain,
            'merk' => $default['merk'],
            'category' => $category,
            'stok' => (float) ($primary['stok'] ?? 0),
            'harga_jual' => $default['harga_jual'],
            'description' => $description,
            'featured' => $featured,
            'sort_order' => $sortOrder,
            'images' => $default['images'],
            'marketplaces' => $this->rowMarketplaces($primary),
            'updated_at' => $updatedAt,
            'variants' => $variants,
        ];
        $this->cache->write("products/{$slug}.json", $product);
        return $product;
    }

    /** Slug unik (tambah -2, -3 bila bentrok). */
    private function uniqueSlug(string $slug, array &$slugs): string
    {
        $base = $slug;
        $n = 1;
        while (isset($slugs[$slug])) {
            $slug = $base . '-' . (++$n);
        }
        $slugs[$slug] = true;
        return $slug;
    }

    /** Gambar produk dari baris Supabase (urut sort_order). */
    private function rowImages(array $row): array
    {
        $images = [];
        $rawImages = is_array($row['product_images'] ?? null) ? $row['product_images'] : [];
        usort($rawImages, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        foreach ($rawImages as $img) {
            if (!empty($img['path'])) {
                $images[] = [
                    'path' => $img['path'],
                    'primary' => (bool) ($img['is_primary'] ?? false),
                ];
            }
        }
        return $images;
    }

    /** Gambar utama dari daftar gambar (primary, else pertama). */
    private function primaryImage(array $images): ?string
    {
        foreach ($images as $img) {
            if (!empty($img['primary'])) {
                return $img['path'];
            }
        }
        return !empty($images) ? $images[0]['path'] : null;
    }

    /** Marketplaces dari baris (array, atau JSON string). */
    private function rowMarketplaces(array $row): array
    {
        $mps = is_array($row['marketplaces'] ?? null) ? $row['marketplaces'] : (is_string($row['marketplaces'] ?? null) ? json_decode($row['marketplaces'], true) : []);
        return is_array($mps) ? $mps : [];
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
        // --- Merek: satu nilai per produk; produk gabungan dihitung di
        // SETIAP merk variannya (jadi filter merk TOYOTA menemukan produk
        // 04465-0K100 yang digabung, begitu juga DAIMARU). ---
        $brandCounts = [];
        foreach ($index as $p) {
            if (!empty($p['variants'])) {
                foreach ($p['variants'] as $v) {
                    $m = trim((string) ($v['merk'] ?? ''));
                    if ($m !== '') {
                        $brandCounts[$m] = ($brandCounts[$m] ?? 0) + 1;
                    }
                }
            } else {
                $m = trim((string) ($p['merk'] ?? ''));
                if ($m !== '') {
                    $brandCounts[$m] = ($brandCounts[$m] ?? 0) + 1;
                }
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

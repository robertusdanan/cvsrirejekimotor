<?php
declare(strict_types=1);

/**
 * Clean Supabase REST Client interfacing with dedicated tables:
 *  - products
 *  - product_images
 *  - marketplaces
 *  - company_profile
 *  - hero_slides
 *  - admin_users
 */
final class SupabaseWriter
{
    public $url;
    public $key;

    public function __construct(?string $url = null, ?string $key = null)
    {
        $this->url = rtrim($url ?? (string) config('SUPABASE_URL'), '/');
        $this->key = $key ?? (string) config('SUPABASE_SERVICE_ROLE_KEY');
    }

    /**
     * Send HTTP request to Supabase REST API.
     */
    public function request(string $method, string $endpoint, ?array $body = null, array $extraHeaders = []): array
    {
        $url = $this->url . $endpoint;
        $headers = array_merge([
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ], $extraHeaders);

        $opts = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ];

        if ($body !== null) {
            $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);

        if ($res === false) {
            return ['error' => 'Connection failed to Supabase'];
        }

        $decoded = json_decode($res, true);
        return is_array($decoded) ? $decoded : [];
    }

    // =========================================================================
    // PRODUCTS & PRODUCT IMAGES
    // =========================================================================

    public function getProducts(?string $search = null): array
    {
        $endpoint = '/rest/v1/products?select=*,product_images(*)&order=sort_order.asc,created_at.desc';
        if ($search !== null && $search !== '') {
            $s = rawurlencode('%' . $search . '%');
            $endpoint .= '&or=(kode_barang.ilike.' . $s . ',nama_produk.ilike.' . $s . ',merk.ilike.' . $s . ')';
        }

        $rows = $this->request('GET', $endpoint);
        return is_array($rows) && !isset($rows['error']) ? $rows : [];
    }

    public function getProductByKode(string $kode): ?array
    {
        $k = rawurlencode($kode);
        $rows = $this->request('GET', '/rest/v1/products?select=*,product_images(*)&kode_barang=eq.' . $k . '&limit=1');
        return is_array($rows) && !empty($rows[0]) ? $rows[0] : null;
    }

    public function getProductById(string $id): ?array
    {
        $i = rawurlencode($id);
        $rows = $this->request('GET', '/rest/v1/products?select=*,product_images(*)&id=eq.' . $i . '&limit=1');
        return is_array($rows) && !empty($rows[0]) ? $rows[0] : null;
    }

    public function upsertProduct(array $data): ?array
    {
        // Upsert on unique column `kode_barang`
        $rows = $this->request('POST', '/rest/v1/products?on_conflict=kode_barang', [$data], [
            'Prefer: return=representation,resolution=merge-duplicates',
        ]);
        return is_array($rows) && !empty($rows[0]) ? $rows[0] : null;
    }

    public function deleteProduct(string $kodeOrId): bool
    {
        // Delete images first or CASCADE handles it if product deleted
        $res = $this->request('DELETE', '/rest/v1/products?or=(id.eq.' . rawurlencode($kodeOrId) . ',kode_barang.eq.' . rawurlencode($kodeOrId) . ')');
        return !isset($res['error']);
    }

    public function syncProductImages(string $productId, array $images): void
    {
        // Delete existing images for this product
        $this->request('DELETE', '/rest/v1/product_images?product_id=eq.' . rawurlencode($productId));

        if ($images === []) {
            return;
        }

        $rows = [];
        foreach ($images as $idx => $img) {
            $rows[] = [
                'product_id' => $productId,
                'path'       => $img['path'],
                'is_primary' => (bool) ($img['primary'] ?? ($idx === 0)),
                'sort_order' => $idx,
            ];
        }

        $this->request('POST', '/rest/v1/product_images', $rows);
    }

    // =========================================================================
    // MARKETPLACES
    // =========================================================================

    public function getMarketplaces(): array
    {
        $rows = $this->request('GET', '/rest/v1/marketplaces?select=*&order=sort_order.asc,created_at.asc');
        return is_array($rows) && !isset($rows['error']) ? $rows : [];
    }

    public function saveMarketplaces(array $items): void
    {
        // Clear and replace marketplaces
        $this->request('DELETE', '/rest/v1/marketplaces?id=gt.0');
        if ($items === []) {
            return;
        }

        $rows = [];
        foreach ($items as $idx => $item) {
            $rows[] = [
                'name'       => $item['name'] ?? 'Marketplace',
                'slug'       => $item['slug'] ?? ('mp-' . ($idx + 1)),
                'url'        => $item['url'] ?? '#',
                'icon'       => $item['icon'] ?? 'shopping-bag',
                'is_active'  => (bool) ($item['active'] ?? $item['is_active'] ?? true),
                'sort_order' => $idx,
            ];
        }

        $this->request('POST', '/rest/v1/marketplaces', $rows);
    }

    // =========================================================================
    // COMPANY PROFILE
    // =========================================================================

    public function getCompanyProfile(): array
    {
        $rows = $this->request('GET', '/rest/v1/company_profile?id=eq.1&limit=1');
        if (is_array($rows) && !empty($rows[0])) {
            return $rows[0];
        }
        return [
            'id'                  => 1,
            'company_name'        => 'CV Sri Rejeki Motor',
            'tagline'             => 'Pusat Suku Cadang & Onderdil Mobil Terlengkap',
            'description'         => 'Pusat suku cadang dan onderdil mobil terlengkap di Tulungagung.',
            'address'             => 'Jl. Dr. Sutomo No. 42, Tulungagung, Jawa Timur',
            'whatsapp'            => '6281234567890',
            'phone'               => '0355-321456',
            'email'               => 'info@srirejekimotor.com',
            'google_maps_embed'   => '',
            'operational_hours'   => [
                'workdays' => 'Senin - Sabtu: 08:00 - 17:00',
                'sunday'   => 'Minggu / Hari Libur: Tutup',
            ],
        ];
    }

    public function saveCompanyProfile(array $data): void
    {
        $data['id'] = 1;
        $data['updated_at'] = date('c');
        $this->request('POST', '/rest/v1/company_profile?on_conflict=id', [$data], [
            'Prefer: return=representation,resolution=merge-duplicates',
        ]);
    }

    // =========================================================================
    // HERO SLIDES
    // =========================================================================

    public function getHeroSlides(): array
    {
        $rows = $this->request('GET', '/rest/v1/hero_slides?select=*&order=sort_order.asc,created_at.desc');
        return is_array($rows) && !isset($rows['error']) ? $rows : [];
    }

    public function saveHeroSlides(array $slides): void
    {
        // Clear existing slides and re-insert
        $this->request('DELETE', '/rest/v1/hero_slides?id=neq.00000000-0000-0000-0000-000000000000');
        if ($slides === []) {
            return;
        }

        $rows = [];
        foreach ($slides as $idx => $s) {
            $rows[] = [
                'title'       => $s['title'] ?? '',
                'subtitle'    => $s['subtitle'] ?? '',
                'media_type'  => $s['media_type'] ?? 'image',
                'media_url'   => $s['media_url'] ?? $s['image_url'] ?? $s['video_url'] ?? '',
                'button_text' => $s['button_text'] ?? 'Lihat Produk',
                'button_url'  => $s['button_url'] ?? '/produk',
                'is_active'   => (bool) ($s['is_active'] ?? true),
                'sort_order'  => $idx,
            ];
        }

        $this->request('POST', '/rest/v1/hero_slides', $rows);
    }
}

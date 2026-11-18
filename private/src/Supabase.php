<?php
declare(strict_types=1);

/**
 * Minimal read-only Supabase REST client for the `gudang` table.
 *
 * IMPORTANT:
 * - This class is READ-ONLY by design. There is intentionally no insert/update/delete
 *   method, because the website must never write to Supabase (per project rules).
 * - Uses the service_role key because RLS status on `gudang` was not confirmed;
 *   this file must NEVER be included from anything under public_html directly,
 *   and the key must never be echoed, logged, or returned in any response body.
 */
final class Supabase
{
    public $url;
    public $serviceKey;

    public function __construct(string $url, string $serviceKey)
    {
        $this->url = rtrim($url, '/');
        $this->serviceKey = $serviceKey;
    }

    /**
     * Fetch all rows from `gudang`, optionally filtered/paginated.
     * $select uses PostgREST column syntax, e.g. "id,kode_barang,nama_produk".
     */
    public function fetchGudang(string $select = '*', int $limit = 1000, int $offset = 0): array
    {
        $query = http_build_query([
            'select' => $select,
            'limit'  => $limit,
            'offset' => $offset,
            'order'  => 'updated_at.desc',
        ]);

        return $this->request('GET', "/rest/v1/gudang?{$query}");
    }

    public function fetchGudangById(string $id): ?array
    {
        $query = http_build_query(['id' => 'eq.' . $id, 'select' => '*']);
        $rows = $this->request('GET', "/rest/v1/gudang?{$query}");
        return $rows[0] ?? null;
    }

    /** Fetch rows updated after a given ISO8601 timestamp — used by cron reconciliation. */
    public function fetchGudangUpdatedSince(string $isoTimestamp): array
    {
        $query = http_build_query([
            'select'     => '*',
            'updated_at' => 'gt.' . $isoTimestamp,
            'order'      => 'updated_at.asc',
        ]);
        return $this->request('GET', "/rest/v1/gudang?{$query}");
    }

    private function request(string $method, string $path): array
    {
        $ch = curl_init($this->url . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'apikey: ' . $this->serviceKey,
                'Authorization: Bearer ' . $this->serviceKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Supabase request failed: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            // Do not leak response body (could echo internal details); log server-side only.
            error_log("Supabase error [{$status}] on {$path}");
            throw new RuntimeException("Supabase returned HTTP {$status}");
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }
}

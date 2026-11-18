<?php
declare(strict_types=1);

/**
 * Supabase REST client for the `admin_users` table only — this is where
 * admin panel login accounts (username/password_hash/role) live.
 *
 * Kept as its own class (not merged with Supabase.php/SupabaseWriter.php,
 * which are scoped to `gudang` only) so each table's access pattern stays
 * simple and auditable. Always uses the service_role key; RLS on
 * `admin_users` denies anon/authenticated entirely (see supabase_admin_users.sql).
 *
 * Used by Auth.php (login) and admin/users.php (account management).
 * Never include this from anything reachable without Auth::requireLogin().
 */
final class SupabaseAdminUsers
{
    public $url;
    public $serviceKey;

    public function __construct(string $url, string $serviceKey)
    {
        $this->url = rtrim($url, '/');
        $this->serviceKey = $serviceKey;
    }

    public function findByUsername(string $username): ?array
    {
        $query = http_build_query(['username' => 'eq.' . $username, 'select' => '*', 'limit' => 1]);
        $rows = $this->request('GET', "/rest/v1/admin_users?{$query}");
        return $rows[0] ?? null;
    }

    public function findById(string $id): ?array
    {
        $query = http_build_query(['id' => 'eq.' . $id, 'select' => '*', 'limit' => 1]);
        $rows = $this->request('GET', "/rest/v1/admin_users?{$query}");
        return $rows[0] ?? null;
    }

    /** All accounts, oldest first. Expected to be a small list (a handful of staff). */
    public function listAll(): array
    {
        $query = http_build_query(['select' => '*', 'order' => 'created_at.asc']);
        return $this->request('GET', "/rest/v1/admin_users?{$query}");
    }

    public function create(string $username, string $passwordHash, string $role): array
    {
        $rows = $this->request('POST', '/rest/v1/admin_users', [
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
        ], ['Prefer: return=representation']);
        return $rows[0] ?? [];
    }

    public function updateRole(string $id, string $role): void
    {
        $query = http_build_query(['id' => 'eq.' . $id]);
        $this->request('PATCH', "/rest/v1/admin_users?{$query}", ['role' => $role]);
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $query = http_build_query(['id' => 'eq.' . $id]);
        $this->request('PATCH', "/rest/v1/admin_users?{$query}", ['password_hash' => $passwordHash]);
    }

    public function delete(string $id): void
    {
        $query = http_build_query(['id' => 'eq.' . $id]);
        $this->request('DELETE', "/rest/v1/admin_users?{$query}");
    }

    private function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $ch = curl_init($this->url . $path);
        $options = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => array_merge([
                'apikey: ' . $this->serviceKey,
                'Authorization: Bearer ' . $this->serviceKey,
                'Content-Type: application/json',
            ], $extraHeaders),
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Permintaan ke Supabase (admin_users) gagal: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            error_log("SupabaseAdminUsers error [{$status}] on {$path}: " . $response);
            $decoded = json_decode($response, true);
            $message = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : "HTTP {$status}";
            throw new RuntimeException('Supabase menolak permintaan: ' . $message);
        }

        $data = json_decode((string) $response, true);
        return is_array($data) ? $data : [];
    }
}

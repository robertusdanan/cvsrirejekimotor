<?php
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/SupabaseAdminUsers.php';

$pdo = Database::connection();

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source TEXT NOT NULL DEFAULT 'supabase' CHECK (source IN ('supabase','manual')),
    gudang_id TEXT UNIQUE,     -- references gudang.id in Supabase; NULL when source = 'manual'
    slug TEXT NOT NULL UNIQUE,
    visible INTEGER NOT NULL DEFAULT 0,
    featured INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published')),
    sort_order INTEGER NOT NULL DEFAULT 0,
    public_description TEXT DEFAULT '',
    category_label TEXT DEFAULT '',
    -- The following columns are the actual master data ONLY for source = 'manual'
    -- (products that exist purely on the website, never written to Supabase).
    -- For source = 'supabase' they are ignored; that data comes from `gudang` instead.
    manual_nama_produk TEXT DEFAULT '',
    manual_kode_barang TEXT DEFAULT '',
    manual_merk TEXT DEFAULT '',
    manual_nama_mobil TEXT DEFAULT '',
    manual_nama_lain TEXT DEFAULT '',
    manual_stok NUMERIC DEFAULT 0,
    manual_harga_jual INTEGER DEFAULT 0,
    manual_harga_final INTEGER,
    manual_diskon_pct NUMERIC DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- All product-scoped child data keys off product_config.id (NOT gudang_id), so it
-- works identically whether the product's master data comes from Supabase or was
-- entered manually by an admin.
CREATE TABLE IF NOT EXISTS product_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_config_id INTEGER NOT NULL REFERENCES product_config(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,     -- relative R2 key, e.g. products/{id}/medium/{file}.webp
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_primary INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_product_images_config ON product_images (product_config_id);

CREATE TABLE IF NOT EXISTS marketplaces (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    logo TEXT DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS product_marketplaces (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_config_id INTEGER NOT NULL REFERENCES product_config(id) ON DELETE CASCADE,
    marketplace_id INTEGER NOT NULL REFERENCES marketplaces(id) ON DELETE CASCADE,
    url TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_product_marketplaces_config ON product_marketplaces (product_config_id);

CREATE TABLE IF NOT EXISTS company_profile (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    company_name TEXT NOT NULL DEFAULT 'CV Sri Rejeki Motor',
    tagline TEXT DEFAULT '',
    description TEXT DEFAULT '',
    phone TEXT DEFAULT '',
    whatsapp TEXT DEFAULT '',
    email TEXT DEFAULT '',
    address TEXT DEFAULT '',
    google_maps_url TEXT DEFAULT '',
    google_maps_embed_url TEXT DEFAULT '',
    social_links_json TEXT DEFAULT '{}'
);
INSERT OR IGNORE INTO company_profile (id, company_name, google_maps_url)
    VALUES (1, 'CV Sri Rejeki Motor', 'https://maps.app.goo.gl/ca129Y1Y5yv4SGoK8');
-- NOTE: google_maps_embed_url must be filled in manually via admin using the
-- src="..." value from Google Maps -> Share -> Embed a map (NOT the short share link,
-- which cannot be embedded directly in an iframe).

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT DEFAULT ''
);

-- NOTE: admin_users (login accounts) is NOT created here anymore — it now
-- lives in Supabase. Run supabase_admin_users.sql once in the Supabase SQL
-- Editor to create it and seed your first Super Admin account. See the
-- migration block below for upgrading an existing local install.

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_username TEXT NOT NULL,
    action TEXT NOT NULL,
    detail TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS login_throttle (
    ip TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT DEFAULT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS cache_log (
    resource TEXT PRIMARY KEY,
    version INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL);

// One-time upgrade path: installs from before admin_users moved to Supabase
// still have a local `admin_users` table with real accounts in it. Offer to
// copy them into Supabase (password_hash is bcrypt — copied as-is, no need
// to know anyone's plaintext password) so nobody gets locked out.
$hasLegacyTable = (bool) $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'")->fetchColumn();
if ($hasLegacyTable) {
    $legacyUsers = $pdo->query('SELECT id, username, password_hash, role, created_at FROM admin_users')->fetchAll(PDO::FETCH_ASSOC);
    if ($legacyUsers !== [] && php_sapi_name() === 'cli') {
        echo count($legacyUsers) . " akun admin lama ditemukan di SQLite lokal (dari sebelum login dipindah ke Supabase):" . PHP_EOL;
        foreach ($legacyUsers as $u) {
            echo "  - {$u['username']} ({$u['role']})" . PHP_EOL;
        }
        echo "Pindahkan semua ke Supabase sekarang? (y/n): ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) === 'y') {
            $client = new SupabaseAdminUsers(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));
            try {
                $existing = $client->listAll();
            } catch (Throwable $e) {
                echo "Gagal menghubungi Supabase: {$e->getMessage()}" . PHP_EOL;
                echo "Pastikan sudah menjalankan supabase_admin_users.sql di Supabase SQL Editor terlebih dahulu, lalu jalankan ulang migrate.php." . PHP_EOL;
                $existing = null;
            }
            if ($existing !== null) {
                $existingUsernames = array_map(static fn ($u) => $u['username'], $existing);
                $moved = 0;
                foreach ($legacyUsers as $u) {
                    if (in_array($u['username'], $existingUsernames, true)) {
                        echo "  Lewati '{$u['username']}' — username ini sudah ada di Supabase." . PHP_EOL;
                        continue;
                    }
                    $role = $u['role'] === 'superadmin' ? 'superadmin' : 'staff';
                    $client->create($u['username'], $u['password_hash'], $role);
                    $moved++;
                    echo "  Dipindahkan: {$u['username']} ({$role})" . PHP_EOL;
                }
                echo "{$moved} akun berhasil dipindahkan ke Supabase." . PHP_EOL;

                // Rename (never drop) the old table so nothing reads from it
                // by accident, but the data isn't destroyed either.
                $pdo->exec('ALTER TABLE admin_users RENAME TO admin_users_legacy_migrated');
                echo "Tabel lokal lama diganti nama menjadi 'admin_users_legacy_migrated' (bukan dihapus, hanya arsip)." . PHP_EOL;
            }
        }
    }
}

echo "Migration complete: " . config('SQLITE_PATH') . PHP_EOL;

if (!$hasLegacyTable) {
    try {
        $client = new SupabaseAdminUsers(config('SUPABASE_URL'), config('SUPABASE_SERVICE_ROLE_KEY'));
        $count = count($client->listAll());
        if ($count === 0) {
            echo "Belum ada akun admin di Supabase. Jalankan supabase_admin_users.sql di Supabase SQL Editor untuk membuat akun Super Admin pertama." . PHP_EOL;
        }
    } catch (Throwable $e) {
        echo "Catatan: tidak bisa mengecek akun admin di Supabase (" . $e->getMessage() . "). "
            . "Pastikan sudah menjalankan supabase_admin_users.sql." . PHP_EOL;
    }
}

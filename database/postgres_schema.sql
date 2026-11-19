-- ============================================================================
-- CV SRI REJEKI MOTOR — POSTGRESQL SCHEMA (cPanel)
-- Pengganti Supabase (free tier) per perintah Danan 2026-11-19.
-- (Pivot 2026-11-19: Danan memerintahkan "migrasi dan gunakan saja
-- PostgreSQL" — database sudah dibuat di cPanel: gmramlnu_cvsrirejekimotor.)
-- Dijalankan otomatis oleh scripts/migrate_supabase_to_postgres.php
-- (membuat tabel bila belum ada), atau manual via phpPgAdmin.
--
-- Catatan pemetaan dari Supabase (Postgres) ke Postgres cPanel:
-- - UUID            -> CHAR(36) (nilai UUID dipertahankan apa adanya,
--                    supaya semua relasi tetap utuh)
-- - TIMESTAMPTZ     -> TIMESTAMP (disimpan UTC; kode PHP menormalkan
--                    string ISO-8601 ke 'Y-m-d H:i:s' sebelum tulis)
-- - JSONB           -> JSONB (NULL = kosong; kode PHP mengartikan NULL
--                    sebagai [] / nilai default)
-- - BOOLEAN         -> BOOLEAN
-- - BIGSERIAL       -> BIGSERIAL
-- - SERIAL          -> SERIAL
-- - NUMERIC(10,2)   -> DECIMAL(10,2)
-- - Fungsi RPC statistik (catat_kunjungan / baca_statistik) TIDAK
--   dibuat sebagai stored procedure — logikanya ada di PostgresWriter
--   (catatKunjungan / bacaStatistik), memakai zona Asia/Jakarta.
-- - updated_at TIDAK memakai ON UPDATE CURRENT_TIMESTAMP (Postgres
--   tidak punya); updated_at di-set eksplisit oleh kode PHP di upsert.
-- ============================================================================

CREATE TABLE IF NOT EXISTS products (
    id CHAR(36) NOT NULL,
    kode_barang VARCHAR(100) NOT NULL,
    nama_produk VARCHAR(255) NOT NULL,
    nama_mobil VARCHAR(255) NULL,
    nama_lain VARCHAR(255) NULL,
    merk VARCHAR(100) NULL,
    category VARCHAR(100) NULL,
    stok DECIMAL(10,2) NOT NULL DEFAULT 0,
    harga_jual BIGINT NOT NULL DEFAULT 0,
    description TEXT NULL,
    featured BOOLEAN NOT NULL DEFAULT false,
    sort_order INT NOT NULL DEFAULT 0,
    marketplaces JSONB NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uk_products_kode_barang UNIQUE (kode_barang)
);
CREATE INDEX IF NOT EXISTS idx_products_featured ON products (featured);
CREATE INDEX IF NOT EXISTS idx_products_merk ON products (merk);
CREATE INDEX IF NOT EXISTS idx_products_category ON products (category);

CREATE TABLE IF NOT EXISTS product_images (
    id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL,
    path TEXT NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT false,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_product_images_product ON product_images (product_id);

CREATE TABLE IF NOT EXISTS brands (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE (name)
);

CREATE TABLE IF NOT EXISTS vehicles (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    brand VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE (name)
);

CREATE TABLE IF NOT EXISTS categories (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    parent_id CHAR(36) NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE (name)
);
CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories (parent_id);
-- TANPA FK self-referencing parent_id (sama seperti Supabase):
-- urutan insert tidak boleh gagal hanya karena induk belum ada.

CREATE TABLE IF NOT EXISTS marketplaces (
    id SERIAL NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    url TEXT NOT NULL,
    icon VARCHAR(100) NOT NULL DEFAULT 'shopping-bag',
    is_active BOOLEAN NOT NULL DEFAULT true,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE (slug)
);

CREATE TABLE IF NOT EXISTS company_profile (
    id INT NOT NULL DEFAULT 1,
    company_name VARCHAR(255) NOT NULL DEFAULT 'CV Sri Rejeki Motor',
    tagline VARCHAR(255) NULL DEFAULT 'Pusat Suku Cadang & Onderdil Mobil Terlengkap',
    description TEXT NULL,
    address TEXT NULL,
    whatsapp VARCHAR(50) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(100) NULL,
    google_maps_embed TEXT NULL,
    operational_hours JSONB NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS hero_slides (
    id CHAR(36) NOT NULL,
    title VARCHAR(255) NULL,
    subtitle VARCHAR(255) NULL,
    media_type VARCHAR(20) NOT NULL DEFAULT 'image',
    media_url TEXT NOT NULL,
    button_text VARCHAR(100) NOT NULL DEFAULT 'Lihat Produk',
    button_url TEXT NOT NULL DEFAULT '/produk',
    is_active BOOLEAN NOT NULL DEFAULT true,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS admin_users (
    id CHAR(36) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'superadmin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE (username)
);

CREATE TABLE IF NOT EXISTS login_throttle (
    ip VARCHAR(45) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ip)
);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGSERIAL NOT NULL,
    admin_username TEXT NOT NULL,
    action TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
CREATE INDEX IF NOT EXISTS idx_admin_audit_log_created ON admin_audit_log (created_at);

CREATE TABLE IF NOT EXISTS statistik_kunjungan (
    tanggal DATE NOT NULL,
    pengunjung INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tanggal)
);

CREATE TABLE IF NOT EXISTS statistik_kunjungan_ip (
    tanggal DATE NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tanggal, ip_hash)
);

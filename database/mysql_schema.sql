-- ============================================================================
-- CV SRI REJEKI MOTOR — MYSQL SCHEMA (cPanel)
-- Pengganti Supabase (free tier) per perintah Danan 2026-11-19.
-- Dijalankan otomatis oleh scripts/migrate_supabase_to_mysql.php
-- (membuat tabel bila belum ada), atau manual via phpMyAdmin.
--
-- Catatan pemetaan dari Supabase (Postgres) ke MySQL:
-- - UUID            -> CHAR(36) (nilai UUID dipertahankan apa adanya,
--                    supaya semua relasi tetap utuh)
-- - TIMESTAMPTZ     -> DATETIME (disimpan UTC; kode PHP menormalkan
--                    string ISO-8601 ke 'Y-m-d H:i:s' sebelum tulis)
-- - JSONB           -> JSON (NULL = kosong; kode PHP mengartikan NULL
--                    sebagai [] / nilai default)
-- - BOOLEAN         -> TINYINT(1)
-- - BIGSERIAL       -> BIGINT AUTO_INCREMENT
-- - SERIAL          -> INT AUTO_INCREMENT
-- - NUMERIC(10,2)   -> DECIMAL(10,2)
-- - ON CONFLICT     -> ON DUPLICATE KEY UPDATE (di kode PHP)
-- - Fungsi RPC statistik (catat_kunjungan / baca_statistik) TIDAK
--   dibuat sebagai stored procedure — logikanya ada di MySQLWriter
--   (catatKunjungan / bacaStatistik), memakai zona Asia/Jakarta.
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
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    marketplaces JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_products_kode_barang (kode_barang),
    KEY idx_products_featured (featured),
    KEY idx_products_merk (merk),
    KEY idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
    id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL,
    path TEXT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_images_product (product_id),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brands (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_brands_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    brand VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_vehicles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    parent_id CHAR(36) NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categories_name (name),
    KEY idx_categories_parent (parent_id)
    -- TANPA FK self-referencing parent_id (sama seperti Supabase):
    -- urutan insert tidak boleh gagal hanya karena induk belum ada.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplaces (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    url TEXT NOT NULL,
    icon VARCHAR(100) NOT NULL DEFAULT 'shopping-bag',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_marketplaces_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    operational_hours JSON NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hero_slides (
    id CHAR(36) NOT NULL,
    title VARCHAR(255) NULL,
    subtitle VARCHAR(255) NULL,
    media_type VARCHAR(20) NOT NULL DEFAULT 'image',
    media_url TEXT NOT NULL,
    button_text VARCHAR(100) NOT NULL DEFAULT 'Lihat Produk',
    button_url TEXT NOT NULL DEFAULT '/produk',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id CHAR(36) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'superadmin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_throttle (
    ip VARCHAR(45) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT NOT NULL AUTO_INCREMENT,
    admin_username TEXT NOT NULL,
    action TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_audit_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statistik_kunjungan (
    tanggal DATE NOT NULL,
    pengunjung INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statistik_kunjungan_ip (
    tanggal DATE NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tanggal, ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

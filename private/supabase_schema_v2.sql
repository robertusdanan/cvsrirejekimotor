-- ====================================================================
-- SUPABASE SQL DDL FOR CV SRI REJEKI MOTOR
-- Run this directly in your Supabase SQL Editor:
-- Supabase Dashboard -> SQL Editor -> New Query -> Run
-- ====================================================================

-- 1. DROP OLD TABLES (if present)
DROP TABLE IF EXISTS product_images CASCADE;
DROP TABLE IF EXISTS products CASCADE;
DROP TABLE IF EXISTS marketplaces CASCADE;
DROP TABLE IF EXISTS company_profile CASCADE;
DROP TABLE IF EXISTS hero_slides CASCADE;
DROP TABLE IF EXISTS admin_users CASCADE;
DROP TABLE IF EXISTS gudang CASCADE;

-- 2. PRODUCTS TABLE
CREATE TABLE products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    kode_barang VARCHAR(100) UNIQUE NOT NULL,
    nama_produk VARCHAR(255) NOT NULL,
    nama_mobil VARCHAR(255),
    nama_lain VARCHAR(255),
    merk VARCHAR(100),
    category VARCHAR(100),
    stok NUMERIC(10, 2) DEFAULT 0,
    harga_jual BIGINT DEFAULT 0,
    description TEXT,
    featured BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    marketplaces JSONB DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 3. PRODUCT IMAGES TABLE
CREATE TABLE product_images (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID REFERENCES products(id) ON DELETE CASCADE,
    path TEXT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 4. MARKETPLACES TABLE
CREATE TABLE marketplaces (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    url TEXT NOT NULL,
    icon VARCHAR(100) DEFAULT 'shopping-bag',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 5. COMPANY PROFILE TABLE
CREATE TABLE company_profile (
    id INT PRIMARY KEY DEFAULT 1,
    company_name VARCHAR(255) NOT NULL DEFAULT 'CV Sri Rejeki Motor',
    tagline VARCHAR(255) DEFAULT 'Pusat Suku Cadang & Onderdil Mobil Terlengkap',
    description TEXT,
    address TEXT,
    whatsapp VARCHAR(50),
    phone VARCHAR(50),
    email VARCHAR(100),
    google_maps_embed TEXT,
    operational_hours JSONB DEFAULT '{"workdays": "Senin - Sabtu: 08:00 - 17:00", "sunday": "Minggu / Hari Libur: Tutup"}'::jsonb,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Insert default row for company profile
INSERT INTO company_profile (id, company_name, tagline, description, address, whatsapp, phone, email, google_maps_embed)
VALUES (
    1,
    'CV Sri Rejeki Motor',
    'Pusat Suku Cadang & Onderdil Mobil Terlengkap',
    'Pusat suku cadang dan onderdil mobil terlengkap di Tulungagung dengan kualitas terjamin dan pengiriman ke seluruh Indonesia.',
    'Jl. Dr. Sutomo No. 42, Tulungagung, Jawa Timur',
    '6281234567890',
    '0355-321456',
    'info@srirejekimotor.com',
    ''
) ON CONFLICT (id) DO NOTHING;

-- 6. HERO SLIDES TABLE
CREATE TABLE hero_slides (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255),
    subtitle VARCHAR(255),
    media_type VARCHAR(20) DEFAULT 'image', -- 'image' or 'video'
    media_url TEXT NOT NULL,
    button_text VARCHAR(100) DEFAULT 'Lihat Produk',
    button_url TEXT DEFAULT '/produk',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 7. ADMIN USERS TABLE
CREATE TABLE admin_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'superadmin',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Insert default superadmin (password: admin123)
-- Change password after first login!
INSERT INTO admin_users (username, password_hash, role)
VALUES ('admin', '$2y$10$e8R6.Q1VqY0c2k.8l9hMbeC5U9kG1/4mG2Q0bO.1N8jK9L0M1N2O3', 'superadmin')
ON CONFLICT (username) DO NOTHING;

-- INDEXES FOR FAST QUERIES
CREATE INDEX idx_products_kode ON products(kode_barang);
CREATE INDEX idx_products_featured ON products(featured);
CREATE INDEX idx_product_images_product ON product_images(product_id);

-- ENABLE ROW LEVEL SECURITY AND PERMIT ALL FOR SERVICE ROLE / ANON REST
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE product_images ENABLE ROW LEVEL SECURITY;
ALTER TABLE marketplaces ENABLE ROW LEVEL SECURITY;
ALTER TABLE company_profile ENABLE ROW LEVEL SECURITY;
ALTER TABLE hero_slides ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_users ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Public Read Products" ON products FOR SELECT USING (true);
CREATE POLICY "Public Read Product Images" ON product_images FOR SELECT USING (true);
CREATE POLICY "Public Read Marketplaces" ON marketplaces FOR SELECT USING (true);
CREATE POLICY "Public Read Company Profile" ON company_profile FOR SELECT USING (true);
CREATE POLICY "Public Read Hero Slides" ON hero_slides FOR SELECT USING (true);

CREATE POLICY "Service Role All Products" ON products FOR ALL USING (true);
CREATE POLICY "Service Role All Product Images" ON product_images FOR ALL USING (true);
CREATE POLICY "Service Role All Marketplaces" ON marketplaces FOR ALL USING (true);
CREATE POLICY "Service Role All Company Profile" ON company_profile FOR ALL USING (true);
CREATE POLICY "Service Role All Hero Slides" ON hero_slides FOR ALL USING (true);
CREATE POLICY "Service Role All Admin Users" ON admin_users FOR ALL USING (true);

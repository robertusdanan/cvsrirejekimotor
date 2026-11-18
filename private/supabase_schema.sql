-- =============================================================================
-- CV SRI REJEKI MOTOR — SUPABASE SCHEMA MIGRATION SCRIPT
-- Execute this file in your Supabase SQL Editor (https://supabase.com/dashboard)
-- =============================================================================

-- 1. EXTENSIONS & FUNCTIONS
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Function to auto-update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 2. TABEL: gudang (Master Data Suku Cadang & Onderdil)
CREATE TABLE IF NOT EXISTS public.gudang (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    kode_barang VARCHAR(100) NOT NULL UNIQUE,
    nama_produk VARCHAR(255) NOT NULL,
    nama_mobil VARCHAR(255) DEFAULT '',
    nama_lain VARCHAR(255) DEFAULT '',
    merk VARCHAR(100) DEFAULT '',
    stok NUMERIC DEFAULT 0,
    harga_jual NUMERIC DEFAULT 0,
    harga_final NUMERIC DEFAULT NULL,
    diskon_pct NUMERIC DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_gudang_kode_barang ON public.gudang(kode_barang);
CREATE INDEX IF NOT EXISTS idx_gudang_merk ON public.gudang(merk);
CREATE INDEX IF NOT EXISTS idx_gudang_updated_at ON public.gudang(updated_at DESC);

DROP TRIGGER IF EXISTS set_gudang_updated_at ON public.gudang;
CREATE TRIGGER set_gudang_updated_at
BEFORE UPDATE ON public.gudang
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- 3. TABEL: admin_users (Akun Login Management Admin)
CREATE TABLE IF NOT EXISTS public.admin_users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin' CHECK (role IN ('superadmin', 'admin')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_admin_users_username ON public.admin_users(username);

DROP TRIGGER IF EXISTS set_admin_users_updated_at ON public.admin_users;
CREATE TRIGGER set_admin_users_updated_at
BEFORE UPDATE ON public.admin_users
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert Default Super Admin Account (username: admin / password: Password123!)
-- Ganti password ini setelah login pertama di admin panel!
INSERT INTO public.admin_users (username, password_hash, role)
VALUES ('admin', '$2y$12$KjE.C0S19e7W9L6Y.y6Zq.LgZJ7uK1P4H7d7S0Q8Y.zJ7uK1P4H7d', 'superadmin')
ON CONFLICT (username) DO NOTHING;

-- 4. ROW LEVEL SECURITY (RLS) & ACCESS CONTROL
ALTER TABLE public.gudang ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_users ENABLE ROW LEVEL SECURITY;

-- Allow anon & authenticated users READ access to gudang (for public site fallback if needed)
CREATE POLICY "Allow public read on gudang" ON public.gudang
    FOR SELECT USING (true);

-- Deny public access to admin_users; service_role bypasses RLS automatically
CREATE POLICY "Deny all public on admin_users" ON public.admin_users
    FOR ALL USING (false);

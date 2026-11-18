-- Tabel registry MERK (nama resmi + singkatan) — CV Sri Rejeki Motor
-- Dijalankan SEKALI di Supabase dashboard -> SQL Editor.
-- Diisi dari 1000 produk (distinct merk). Produk merujuk lewat kolom
-- products.merk (string); filter dropdown membaca tabel ini via cache.
-- Bila nanti ada merk baru di produk, CacheBuilder menyisipkannya
-- otomatis (best-effort) saat rebuild.

CREATE TABLE IF NOT EXISTS brands (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL UNIQUE,
  short_name text,
  sort_order int NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now()
);

INSERT INTO brands (name, short_name, sort_order) VALUES ('555', NULL, 1) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Akebono', NULL, 2) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Asco', NULL, 3) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Aspira', NULL, 4) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Autoparts', NULL, 5) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Birkens', NULL, 6) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('BMS', NULL, 7) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('BMS/SPR', 'BMS', 8) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Daido', NULL, 9) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Daihatsu', NULL, 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Daimaru', NULL, 11) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Denso', NULL, 12) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('DNY', NULL, 13) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Emgi', NULL, 14) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('F-16', NULL, 15) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Fuji', NULL, 16) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('GAP', NULL, 17) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('GP', NULL, 18) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('GW', NULL, 19) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Hino', NULL, 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('HKT', NULL, 21) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('IBK', NULL, 22) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Isuzu', NULL, 23) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Jade', NULL, 24) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('JDC', NULL, 25) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('JH', NULL, 26) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Jinbo', NULL, 27) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('KOYO', NULL, 28) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Laisin', NULL, 29) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('LKS', NULL, 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Marugo', NULL, 31) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('MEC', NULL, 32) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('MEC JP', 'MEC', 33) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Mitsubishi KTB', 'KTB', 34) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Mitsubishi MMKSI', 'MMKSI', 35) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Mitsuda', NULL, 36) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('MK Kashiyama', 'MK', 37) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('MRC', NULL, 38) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('MSA Gbox', 'MSA', 39) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('New Era', NULL, 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('NGK', NULL, 41) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Nock', NULL, 42) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('NSK', NULL, 43) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('NTB', NULL, 44) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Okayama', NULL, 45) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Okayama Gold', NULL, 46) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Ratu Motor', 'RM', 47) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('RCA', NULL, 48) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Sam Bap', 'SAM', 49) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Sam Hja', 'SAM', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Sanyco', NULL, 51) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Seiken', NULL, 52) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('SH JRM', 'SH', 53) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Sieg Klang', NULL, 54) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Sportshot', NULL, 55) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Stecker', NULL, 56) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Suzuki', NULL, 57) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Taiho', NULL, 58) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('TDW', NULL, 59) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('TE', NULL, 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Terada', NULL, 61) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Toyota', NULL, 62) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('UTM', NULL, 63) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Vaagen', NULL, 64) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Wakasa', NULL, 65) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Wima Tiga Berlian', 'WIMA', 66) ON CONFLICT (name) DO NOTHING;
INSERT INTO brands (name, short_name, sort_order) VALUES ('Wong Coco', 'WCM', 67) ON CONFLICT (name) DO NOTHING;

-- ---- Verifikasi (hasilnya tampil di SQL Editor) ----
SELECT count(*) AS total_merk FROM brands;
SELECT name, short_name FROM brands ORDER BY sort_order;

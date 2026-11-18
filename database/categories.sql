-- ============================================================
-- Tabel kategori khusus untuk toko CV Sri Rejeki Motor
-- Cara pakai: buka Supabase Dashboard -> SQL Editor -> tempel
-- seluruh isi file ini -> Run. Aman dijalankan ulang
-- (CREATE TABLE IF NOT EXISTS + ON CONFLICT DO NOTHING).
--
-- Isi: 15 grup induk + 119 kategori daun (dua level), nama
-- Bahasa Indonesia agar mudah dicari pengunjung.
--
-- Setelah tabel terisi, isi products.category dari
-- category-mapping.json (part_type -> kategori daun).
-- Produk yang kategorinya belum ada: tambahkan kategori baru
-- ke tabel ini (INSERT satu baris), jangan pakai nama merk
-- atau kode model sebagai kategori.
-- ============================================================

CREATE TABLE IF NOT EXISTS categories (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text UNIQUE NOT NULL,
  slug text UNIQUE NOT NULL,
  parent_id uuid REFERENCES categories(id) ON DELETE SET NULL,
  description text,
  sort_order integer DEFAULT 0,
  created_at timestamptz DEFAULT now()
);

CREATE INDEX IF NOT EXISTS categories_parent_id_idx ON categories(parent_id);

-- ---- Grup induk (level 1) ----
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Mesin', 'mesin', NULL, 'Komponen mesin, pengapian dalam, dan sistem pelumasan mesin.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Sistem Rem', 'sistem-rem', NULL, 'Semua komponen sistem pengereman cakram dan tromol.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Transmisi & Kopling', 'transmisi-kopling', NULL, 'Kopling, transmisi, gardan, dan poros penggerak.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kemudi & Kaki-Kaki', 'kemudi-kaki-kaki', NULL, 'Kemudi, suspensi, dan komponen kaki-kaki.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kelistrikan', 'kelistrikan', NULL, 'Aki, pengapian, dinamo, kabel, dan kelistrikan kendaraan.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Pendingin & Bahan Bakar', 'pendingin-bahan-bakar', NULL, 'Radiator, pendingin mesin, dan sistem bahan bakar.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Filter', 'filter', NULL, 'Semua jenis filter kendaraan.', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Oli & Cairan', 'oli-cairan', NULL, 'Oli, minyak, dan cairan kendaraan.', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bodi & Eksterior', 'bodi-eksterior', NULL, 'Bodi, panel luar, dan aksesori eksterior.', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Interior', 'interior', NULL, 'Komponen interior dan kenyamanan kabin.', 100) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Lampu, Kaca & Wiper', 'lampu-kaca-wiper', NULL, 'Lampu, kaca, dan wiper kendaraan.', 110) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Knalpot', 'knalpot', NULL, 'Sistem pembuangan gas kendaraan.', 120) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('AC & Pemanas', 'ac-pemanas', NULL, 'Sistem pendingin kabin (AC) dan pemanas.', 130) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Pengikat & Hardware', 'pengikat-hardware', NULL, 'Baut, mur, klem, dan dudukan.', 140) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Aksesori & Perkakas', 'aksesori-perkakas', NULL, 'Aksesori kendaraan dan perkakas bengkel.', 150) ON CONFLICT (name) DO NOTHING;

-- ---- Kategori daun (level 2) ----
-- Mesin
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Paking & Gasket Mesin', 'paking-gasket-mesin', (SELECT id FROM categories WHERE slug = 'mesin'), 'Paking dan gasket untuk overhaul dan servis mesin.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Piston & Ring Piston', 'piston-ring-piston', (SELECT id FROM categories WHERE slug = 'mesin'), 'Piston, ring piston, dan pin piston.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kruk As', 'kruk-as', (SELECT id FROM categories WHERE slug = 'mesin'), 'Kruk as (crankshaft) dan komponen poros engkol mesin.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Noken As', 'noken-as', (SELECT id FROM categories WHERE slug = 'mesin'), 'Noken as (camshaft) pengatur buka-tutup katup mesin.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Pelatuk Klep & As Templar', 'pelatuk-klep-as-templar', (SELECT id FROM categories WHERE slug = 'mesin'), 'Pelatuk klep dan as templar (rocker arm shaft).', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Valve Lifter & Botolan Klep', 'valve-lifter-botolan-klep', (SELECT id FROM categories WHERE slug = 'mesin'), 'Botolan klep (valve lifter) penerus gerakan camshaft.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Timing Belt', 'timing-belt', (SELECT id FROM categories WHERE slug = 'mesin'), 'Timing belt / sabuk keteng penggerak camshaft.', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Timing Chain', 'timing-chain', (SELECT id FROM categories WHERE slug = 'mesin'), 'Rantai timing (timing chain) penghubung crankshaft dan camshaft.', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Turbocharger', 'turbocharger', (SELECT id FROM categories WHERE slug = 'mesin'), 'Turbocharger dan cartridge turbo (inti turbo).', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Karburator', 'karburator', (SELECT id FROM categories WHERE slug = 'mesin'), 'Karburator pencampur bahan bakar dan udara.', 100) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Injektor & Sistem Injeksi', 'injektor-sistem-injeksi', (SELECT id FROM categories WHERE slug = 'mesin'), 'Injektor dan komponen sistem injeksi bahan bakar.', 110) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Pompa Oli', 'pompa-oli', (SELECT id FROM categories WHERE slug = 'mesin'), 'Pompa oli pelumas mesin.', 120) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Gigi Balancer', 'gigi-balancer', (SELECT id FROM categories WHERE slug = 'mesin'), 'Gigi balancer penyeimbang getaran mesin.', 130) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Gigi & Poros Mesin', 'gigi-poros-mesin', (SELECT id FROM categories WHERE slug = 'mesin'), 'Gigi dan poros di dalam mesin (selain balancer).', 140) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Sensor Mesin', 'sensor-mesin', (SELECT id FROM categories WHERE slug = 'mesin'), 'Sensor pembaca kondisi mesin.', 150) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Termostat', 'termostat', (SELECT id FROM categories WHERE slug = 'mesin'), 'Termostat pengatur suhu cairan pendingin mesin.', 160) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Engine Mounting', 'engine-mounting', (SELECT id FROM categories WHERE slug = 'mesin'), 'Dudukan mesin (engine mounting) peredam getaran.', 170) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Sabuk Penggerak', 'sabuk-penggerak', (SELECT id FROM categories WHERE slug = 'mesin'), 'Sabuk penggerak / fan belt alternator, pompa, dan kompresor.', 180) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Seal & O-Ring', 'seal-o-ring', (SELECT id FROM categories WHERE slug = 'mesin'), 'Seal dan O-ring pencegah kebocoran oli dan cairan.', 190) ON CONFLICT (name) DO NOTHING;

-- Sistem Rem
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kampas Rem Cakram', 'kampas-rem-cakram', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Kampas rem (brake pad) untuk rem cakram.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kampas Rem Tromol', 'kampas-rem-tromol', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Kampas rem (brake shoe) untuk rem tromol.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kampas Rem / Brake Lining', 'kampas-rem-brake-lining', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Kampas rem (brake lining) gesek tromol dan piringan.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Master Rem', 'master-rem', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Master rem (brake master) pengubah tekanan pedal menjadi hidrolik.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Booster Rem', 'booster-rem', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Booster rem (servo) penguat tekanan pedal rem.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kaliper Rem', 'kaliper-rem', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Kaliper rem penjepit kampas ke piringan.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kit Kaliper Rem', 'kit-kaliper-rem', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Kit perbaikan kaliper rem berisi seal dan komponen servis.', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Piringan Rem Cakram', 'piringan-rem-cakram', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Piringan rem (brake disc) yang dijepit kampas rem.', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Tromol Rem', 'tromol-rem', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Tromol rem (brake drum) yang ditekan kampas rem.', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Selang Rem', 'selang-rem', (SELECT id FROM categories WHERE slug = 'sistem-rem'), 'Selang rem penyalur minyak rem ke kaliper dan silinder roda.', 100) ON CONFLICT (name) DO NOTHING;

-- Transmisi & Kopling
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Master Kopling Atas', 'master-kopling-atas', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Master kopling atas (clutch master) pengubah tekanan pedal kopling.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Master Kopling Bawah', 'master-kopling-bawah', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Master kopling bawah (clutch operating/release) penerima tekanan hidrolik.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kampas Kopling', 'kampas-kopling', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Kampas kopling penerus tenaga mesin ke transmisi.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Matahari Kopling', 'matahari-kopling', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Matahari kopling (pressure plate / dekrup).', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Laher Kopling', 'laher-kopling', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Laher kopling (release bearing).', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Komponen Kopling', 'komponen-kopling', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Garpu, baut, dan komponen kopling lainnya.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kabel Kopling', 'kabel-kopling', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Kabel kopling penarik garpu kopling.', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Gigi Transmisi', 'gigi-transmisi', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Gigi transmisi dan komponen girboks.', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Poros & As Penggerak', 'poros-as-penggerak', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Poros dan as penggerak: as roda, as gardan, main shaft.', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Gardan & Diferensial', 'gardan-diferensial', (SELECT id FROM categories WHERE slug = 'transmisi-kopling'), 'Gardan, diferensial, dan as satelit di dalamnya.', 100) ON CONFLICT (name) DO NOTHING;

-- Kemudi & Kaki-Kaki
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Ball Joint', 'ball-joint', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Ball joint penghubung lengan ayun dengan knuckle roda.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Tie Rod', 'tie-rod', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Tie rod penghubung rack steer dengan roda.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('As Stabilizer', 'as-stabilizer', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'As stabilizer penghubung suspensi kiri dan kanan.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('As King Pin', 'as-king-pin', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'As king pin poros engsel kemudi roda depan.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('As Sentrik & Upper Arm', 'as-sentrik-upper-arm', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'As sentrik (upper arm shaft) poros lengan ayun atas.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Shock Absorber', 'shock-absorber', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Shock absorber peredam guncangan suspensi.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Per & Pegas', 'per-pegas', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Per dan pegas penahan beban suspensi.', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bos & Bushing', 'bos-bushing', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Bos dan bushing bantalan kaki-kaki dan kemudi.', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bearing & Laher', 'bearing-laher', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Bearing dan laher tumpuan putaran poros dan roda.', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Boot & Karet Pelindung', 'boot-karet-pelindung', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Boot dan karet pelindung joint dan poros.', 100) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bellcrank', 'bellcrank', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Bellcrank (tuas engkol) penerus gerakan kemudi dan rem.', 110) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Velg & Roda', 'velg-roda', (SELECT id FROM categories WHERE slug = 'kemudi-kaki-kaki'), 'Velg, pelek, dan komponen roda.', 120) ON CONFLICT (name) DO NOTHING;

-- Kelistrikan
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Busi & Pengapian', 'busi-pengapian', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Busi pemercik api ruang bakar dan komponen pengapian.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Koil Pengapian', 'koil-pengapian', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Koil pengapian penghasil tegangan tinggi untuk busi.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Alternator & Dinamo Ampere', 'alternator-dinamo-ampere', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Alternator (dinamo ampere) pengisi aki.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Dinamo Starter', 'dinamo-starter', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Dinamo starter pemutar mesin saat kunci kontak diputar.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bendik Starter', 'bendik-starter', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Bendik starter (pinion) penghubung dinamo starter dan roda gila.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Regulator Alternator', 'regulator-alternator', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Regulator (IC regulator) penstabil tegangan output alternator.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bostel & Brush Holder', 'bostel-brush-holder', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Bostel (dudukan arang/brush) dinamo ampere.', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Aki & Accu', 'aki-accu', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Aki (accu) penyimpan dan penyuplai listrik kendaraan.', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kabel & Soket', 'kabel-soket', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Kabel dan soket sambungan kelistrikan kendaraan.', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Saklar & Switch', 'saklar-switch', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Saklar dan switch pengatur aliran listrik.', 100) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Sensor Kendaraan', 'sensor-kendaraan', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Sensor pembaca kondisi kendaraan untuk sistem kontrol.', 110) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Sekring & Relay', 'sekring-relay', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Sekring dan relay pengaman rangkaian listrik.', 120) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Klakson', 'klakson', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Klakson (horn) isyarat suara kendaraan.', 130) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Alarm & Keamanan', 'alarm-keamanan', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Alarm dan sistem keamanan kendaraan.', 140) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Antena Radio', 'antena-radio', (SELECT id FROM categories WHERE slug = 'kelistrikan'), 'Antena penangkap sinyal radio kendaraan.', 150) ON CONFLICT (name) DO NOTHING;

-- Pendingin & Bahan Bakar
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Radiator', 'radiator', (SELECT id FROM categories WHERE slug = 'pendingin-bahan-bakar'), 'Radiator pendingin cairan pendingin mesin.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kipas Radiator', 'kipas-radiator', (SELECT id FROM categories WHERE slug = 'pendingin-bahan-bakar'), 'Kipas radiator dan motor kipas pendingin.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Pompa Air', 'pompa-air', (SELECT id FROM categories WHERE slug = 'pendingin-bahan-bakar'), 'Pompa air (water pump) sirkulasi cairan pendingin.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Selang & Hose', 'selang-hose', (SELECT id FROM categories WHERE slug = 'pendingin-bahan-bakar'), 'Selang dan hose penyalur cairan dan udara.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Tangki Bahan Bakar', 'tangki-bahan-bakar', (SELECT id FROM categories WHERE slug = 'pendingin-bahan-bakar'), 'Tangki bahan bakar dan komponennya.', 50) ON CONFLICT (name) DO NOTHING;

-- Filter
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Filter Oli', 'filter-oli', (SELECT id FROM categories WHERE slug = 'filter'), 'Filter oli penyaring kotoran dari oli mesin.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Filter Udara', 'filter-udara', (SELECT id FROM categories WHERE slug = 'filter'), 'Filter udara penyaring debu sebelum masuk ruang bakar.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Filter Bahan Bakar', 'filter-bahan-bakar', (SELECT id FROM categories WHERE slug = 'filter'), 'Filter bahan bakar penyaring kotoran solar dan bensin.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Filter AC & Kabin', 'filter-ac-kabin', (SELECT id FROM categories WHERE slug = 'filter'), 'Filter kabin/AC penyaring udara masuk ke kabin.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Filter Umum', 'filter-umum', (SELECT id FROM categories WHERE slug = 'filter'), 'Filter umum dan baut/dudukan filter.', 50) ON CONFLICT (name) DO NOTHING;

-- Oli & Cairan
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Oli Mesin', 'oli-mesin', (SELECT id FROM categories WHERE slug = 'oli-cairan'), 'Oli mesin pelumas komponen dalam mesin.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Oli Transmisi & ATF', 'oli-transmisi-atf', (SELECT id FROM categories WHERE slug = 'oli-cairan'), 'Oli transmisi dan ATF untuk transmisi otomatis.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Oli Gardan', 'oli-gardan', (SELECT id FROM categories WHERE slug = 'oli-cairan'), 'Oli gardan (diferensial).', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Minyak Rem', 'minyak-rem', (SELECT id FROM categories WHERE slug = 'oli-cairan'), 'Minyak rem (brake fluid) untuk sistem hidrolik rem.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Air Radiator & Coolant', 'air-radiator-coolant', (SELECT id FROM categories WHERE slug = 'oli-cairan'), 'Air radiator dan coolant pendingin mesin.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Gemuk & Grease', 'gemuk-grease', (SELECT id FROM categories WHERE slug = 'oli-cairan'), 'Gemuk (grease) pelumas bearing dan joint.', 60) ON CONFLICT (name) DO NOTHING;

-- Bodi & Eksterior
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bumper', 'bumper', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Bumper pelindung bodi dari benturan.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Spion', 'spion', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Spion kaca pandang belakang dan samping.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Panel Depan / Bull Head', 'panel-depan-bull-head', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Panel depan (bull head) rangka dudukan radiator.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Body Strip & Lis Bodi', 'body-strip-lis-bodi', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Body strip (lis bodi) pelindung dan pemanis sisi bodi.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Panel Atap', 'panel-atap', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Panel atap kendaraan.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Pintu & Komponen Pintu', 'pintu-komponen-pintu', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Pintu dan komponen pintu kendaraan.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Grille', 'grille', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Grille depan kendaraan.', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Fender & Spakbor', 'fender-spakbor', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Fender dan spakbor pelindung roda.', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bancik & Step', 'bancik-step', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Bancik (pijakan kaki samping/step) untuk naik ke kabin.', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Cover Mobil', 'cover-mobil', (SELECT id FROM categories WHERE slug = 'bodi-eksterior'), 'Cover mobil (sarung mobil) pelindung bodi.', 100) ON CONFLICT (name) DO NOTHING;

-- Interior
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Jok & Cover Jok', 'jok-cover-jok', (SELECT id FROM categories WHERE slug = 'interior'), 'Jok dan cover jok kendaraan.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Dashboard', 'dashboard', (SELECT id FROM categories WHERE slug = 'interior'), 'Dashboard dan komponen panel depan kabin.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Sandaran Tangan', 'sandaran-tangan', (SELECT id FROM categories WHERE slug = 'interior'), 'Sandaran tangan (arm rest) kabin.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Asbak', 'asbak', (SELECT id FROM categories WHERE slug = 'interior'), 'Asbak kendaraan.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Karpet', 'karpet', (SELECT id FROM categories WHERE slug = 'interior'), 'Karpet lantai kabin.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Setir & Komponen Setir', 'setir-komponen-setir', (SELECT id FROM categories WHERE slug = 'interior'), 'Setir dan komponen sistem kemudi di kabin.', 60) ON CONFLICT (name) DO NOTHING;

-- Lampu, Kaca & Wiper
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Lampu Depan', 'lampu-depan', (SELECT id FROM categories WHERE slug = 'lampu-kaca-wiper'), 'Lampu depan utama kendaraan.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Lampu Belakang', 'lampu-belakang', (SELECT id FROM categories WHERE slug = 'lampu-kaca-wiper'), 'Lampu belakang, rem, dan sein.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Lampu Kendaraan', 'lampu-kendaraan', (SELECT id FROM categories WHERE slug = 'lampu-kaca-wiper'), 'Lampu kendaraan dan rumah lampu (batok).', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bohlam', 'bohlam', (SELECT id FROM categories WHERE slug = 'lampu-kaca-wiper'), 'Bohlam (bulb) lampu kendaraan.', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kaca Kendaraan', 'kaca-kendaraan', (SELECT id FROM categories WHERE slug = 'lampu-kaca-wiper'), 'Kaca depan, samping, dan belakang kendaraan.', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Wiper', 'wiper', (SELECT id FROM categories WHERE slug = 'lampu-kaca-wiper'), 'Wiper pembersih kaca dari air dan kotoran.', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Motor Wiper', 'motor-wiper', (SELECT id FROM categories WHERE slug = 'lampu-kaca-wiper'), 'Motor penggerak wiper.', 70) ON CONFLICT (name) DO NOTHING;

-- Knalpot
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Knalpot & Muffler', 'knalpot-muffler', (SELECT id FROM categories WHERE slug = 'knalpot'), 'Knalpot dan muffler peredam suara gas buang.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Header & Pipa Knalpot', 'header-pipa-knalpot', (SELECT id FROM categories WHERE slug = 'knalpot'), 'Header dan pipa knalpot.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Catalytic Converter', 'catalytic-converter', (SELECT id FROM categories WHERE slug = 'knalpot'), 'Catalytic converter pengolah gas buang.', 30) ON CONFLICT (name) DO NOTHING;

-- AC & Pemanas
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kompresor AC', 'kompresor-ac', (SELECT id FROM categories WHERE slug = 'ac-pemanas'), 'Kompresor AC pendingin kabin.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Kondensor AC', 'kondensor-ac', (SELECT id FROM categories WHERE slug = 'ac-pemanas'), 'Kondensor AC pelepas panas freon.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Evaporator AC', 'evaporator-ac', (SELECT id FROM categories WHERE slug = 'ac-pemanas'), 'Evaporator AC pendingin udara kabin.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Selang AC', 'selang-ac', (SELECT id FROM categories WHERE slug = 'ac-pemanas'), 'Selang AC penyalur freon.', 40) ON CONFLICT (name) DO NOTHING;

-- Pengikat & Hardware
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Baut & Mur', 'baut-mur', (SELECT id FROM categories WHERE slug = 'pengikat-hardware'), 'Baut dan mur pengikat komponen kendaraan.', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Klem & Klip', 'klem-klip', (SELECT id FROM categories WHERE slug = 'pengikat-hardware'), 'Klem dan klip pengikat pipa, selang, dan panel.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Bracket & Dudukan', 'bracket-dudukan', (SELECT id FROM categories WHERE slug = 'pengikat-hardware'), 'Bracket dan dudukan penopang komponen.', 30) ON CONFLICT (name) DO NOTHING;

-- Aksesori & Perkakas
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Perkakas & Alat Bengkel', 'perkakas-alat-bengkel', (SELECT id FROM categories WHERE slug = 'aksesori-perkakas'), 'Perkakas dan alat bengkel (sealant gun, kunci, dll).', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Alat Ukur & Pengukur', 'alat-ukur-pengukur', (SELECT id FROM categories WHERE slug = 'aksesori-perkakas'), 'Alat ukur: tekanan angin ban, tegangan, dll.', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Cover & Pelindung', 'cover-pelindung', (SELECT id FROM categories WHERE slug = 'aksesori-perkakas'), 'Cover dan pelindung komponen kendaraan.', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO categories (name, slug, parent_id, description, sort_order) VALUES ('Aksesori Kendaraan', 'aksesori-kendaraan', (SELECT id FROM categories WHERE slug = 'aksesori-perkakas'), 'Aksesori kendaraan lainnya.', 40) ON CONFLICT (name) DO NOTHING;

-- ---- Verifikasi (hasilnya tampil di SQL Editor) ----
SELECT count(*) AS total_kategori FROM categories;
SELECT count(*) AS grup_induk FROM categories WHERE parent_id IS NULL;
SELECT count(*) AS kategori_daun FROM categories WHERE parent_id IS NOT NULL;
SELECT p.name AS grup, count(c.id) AS jumlah_daun
FROM categories p LEFT JOIN categories c ON c.parent_id = p.id
WHERE p.parent_id IS NULL GROUP BY p.id, p.name, p.sort_order ORDER BY p.sort_order;

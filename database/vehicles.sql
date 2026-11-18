-- Tabel registry KENDARAAN (satu baris = satu kendaraan) — CV Sri Rejeki Motor
-- Dijalankan SEKALI di Supabase dashboard -> SQL Editor.
-- Diisi dari 1000 produk: nama_mobil dipecah per koma, jadi tiap kendaraan
-- berdiri sendiri (bukan lagi satu baris berisi beberapa kendaraan).
-- Produk merujuk lewat kolom products.nama_mobil (daftar dipisah koma);
-- filter dropdown membaca tabel ini via cache dan mencocokkan per kendaraan.

CREATE TABLE IF NOT EXISTS vehicles (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL UNIQUE,
  brand text,
  sort_order int NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now()
);

INSERT INTO vehicles (name, brand, sort_order) VALUES ('B100', NULL, 1) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Ayla', 'Daihatsu', 2) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Delta', 'Daihatsu', 3) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Espass', 'Daihatsu', 4) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Gran Max', 'Daihatsu', 5) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Gran Max S40', 'Daihatsu', 6) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Gran Max S401', 'Daihatsu', 7) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Hijet S75', 'Daihatsu', 8) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Luxio', 'Daihatsu', 9) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Sigra', 'Daihatsu', 10) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Taft', 'Daihatsu', 11) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Taft F70', 'Daihatsu', 12) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Taft F73', 'Daihatsu', 13) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Taruna', 'Daihatsu', 14) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Terios', 'Daihatsu', 15) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Xenia', 'Daihatsu', 16) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Zebra', 'Daihatsu', 17) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Zebra S88', 'Daihatsu', 18) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Zebra S89', 'Daihatsu', 19) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Daihatsu Zebra S91', 'Daihatsu', 20) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Hino', 'Hino', 21) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Hino 300 Series', 'Hino', 22) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Hino Dutro', 'Hino', 23) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Hino Dutro 130HD', 'Hino', 24) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Hino Dutro 130HT', 'Hino', 25) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Hino Rino', 'Hino', 26) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Honda Brio', 'Honda', 27) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Honda City', 'Honda', 28) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Honda Jazz', 'Honda', 29) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Honda Jazz GE8', 'Honda', 30) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Honda Mobilio', 'Honda', 31) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf KAD51', 'Isuzu', 32) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf KBD25', 'Isuzu', 33) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf KBD26', 'Isuzu', 34) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf NHR', 'Isuzu', 35) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf NHR55', 'Isuzu', 36) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf NKR', 'Isuzu', 37) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf NKR55', 'Isuzu', 38) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf NKR66', 'Isuzu', 39) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf NKR71', 'Isuzu', 40) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Elf NMR71', 'Isuzu', 41) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Giga', 'Isuzu', 42) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Panther', 'Isuzu', 43) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Panther PTH23', 'Isuzu', 44) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Panther PTH25', 'Isuzu', 45) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Panther TBR52', 'Isuzu', 46) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Panther TBR54', 'Isuzu', 47) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Panther TBR541', 'Isuzu', 48) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Isuzu Traga', 'Isuzu', 49) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter', 'Mitsubishi', 50) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter 120PS', 'Mitsubishi', 51) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter 125PS', 'Mitsubishi', 52) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter 136PS', 'Mitsubishi', 53) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter FE111', 'Mitsubishi', 54) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter FE119', 'Mitsubishi', 55) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter FE447', 'Mitsubishi', 56) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter FE71', 'Mitsubishi', 57) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter FE73', 'Mitsubishi', 58) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter FE74', 'Mitsubishi', 59) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter FE86', 'Mitsubishi', 60) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter PS100', 'Mitsubishi', 61) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter PS110', 'Mitsubishi', 62) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter PS120', 'Mitsubishi', 63) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter PS135', 'Mitsubishi', 64) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter PS2', 'Mitsubishi', 65) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter PS3', 'Mitsubishi', 66) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter T200', 'Mitsubishi', 67) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Canter T210', 'Mitsubishi', 68) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Colt', 'Mitsubishi', 69) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Colt Diesel Ragasa', 'Mitsubishi', 70) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Colt L300 Diesel L038', 'Mitsubishi', 71) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Colt L300 Diesel L039', 'Mitsubishi', 72) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Colt T120', 'Mitsubishi', 73) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Colt T120SS', 'Mitsubishi', 74) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Fuso F700', 'Mitsubishi', 75) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Galant', 'Mitsubishi', 76) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Kuda', 'Mitsubishi', 77) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi L300', 'Mitsubishi', 78) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Lancer', 'Mitsubishi', 79) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Mirage', 'Mitsubishi', 80) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Pajero', 'Mitsubishi', 81) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Triton', 'Mitsubishi', 82) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Mitsubishi Xpander', 'Mitsubishi', 83) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Nissan Grand Livina', 'Nissan', 84) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Nissan Livina', 'Nissan', 85) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki APV', 'Suzuki', 86) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Carry', 'Suzuki', 87) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Carry Futura', 'Suzuki', 88) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Carry Futura ST130', 'Suzuki', 89) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Carry ST100', 'Suzuki', 90) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Ertiga', 'Suzuki', 91) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Escudo', 'Suzuki', 92) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Esteem', 'Suzuki', 93) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Jimny', 'Suzuki', 94) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Karimun', 'Suzuki', 95) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Katana', 'Suzuki', 96) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Vitara', 'Suzuki', 97) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki Wagon R', 'Suzuki', 98) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Suzuki XL7', 'Suzuki', 99) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota', 'Toyota', 100) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Agya', 'Toyota', 101) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza', 'Toyota', 102) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza/Daihatsu Xenia F500', NULL, 103) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza/Daihatsu Xenia F600', NULL, 104) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza/Daihatsu Xenia F601', NULL, 105) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza/Daihatsu Xenia F65', NULL, 106) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza/Daihatsu Xenia F651', NULL, 107) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza/Daihatsu Xenia F652', NULL, 108) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Avanza/Daihatsu Xenia F653', NULL, 109) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Calya', 'Toyota', 110) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Dyna', 'Toyota', 111) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Dyna 125LHT', 'Toyota', 112) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Fortuner', 'Toyota', 113) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Fortuner GUN165', 'Toyota', 114) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Fortuner TGN51', 'Toyota', 115) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Hiace', 'Toyota', 116) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Hilux', 'Toyota', 117) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Hilux Vigo RZN147', 'Toyota', 118) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang', 'Toyota', 119) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang Innova', 'Toyota', 120) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang KF10', 'Toyota', 121) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang KF20', 'Toyota', 122) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang KF40', 'Toyota', 123) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang LF60', 'Toyota', 124) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang LF80', 'Toyota', 125) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Kijang Super KF50', 'Toyota', 126) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Raize', 'Toyota', 127) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Rush', 'Toyota', 128) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Rush F800', 'Toyota', 129) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Veloz', 'Toyota', 130) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Vios', 'Toyota', 131) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Vios NCP150', 'Toyota', 132) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Vios/Yaris NCP91', NULL, 133) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Yaris', 'Toyota', 134) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('Toyota Yaris NCP15', 'Toyota', 135) ON CONFLICT (name) DO NOTHING;
INSERT INTO vehicles (name, brand, sort_order) VALUES ('W100', NULL, 136) ON CONFLICT (name) DO NOTHING;

-- ---- Verifikasi (hasilnya tampil di SQL Editor) ----
SELECT count(*) AS total_kendaraan FROM vehicles;
SELECT name, brand FROM vehicles ORDER BY sort_order;

# SRM (CV Sri Rejeki Motor) — Panduan Instalasi di cPanel Hosting

Dokumen ini menjelaskan cara deploy project ini ke hosting cPanel dari nol, termasuk struktur folder, konfigurasi, migrasi database, cron job, dan webhook Supabase.

## Daftar Isi

1. [Arsitektur Singkat](#1-arsitektur-singkat)
2. [Prasyarat](#2-prasyarat)
3. [Struktur Folder di cPanel](#3-struktur-folder-di-cpanel)
4. [Langkah Instalasi](#4-langkah-instalasi)
5. [Isi File `private/.env`](#5-isi-file-privateenv)
6. [Setup Cron Job](#6-setup-cron-job)
7. [Setup Database Webhook Supabase](#7-setup-database-webhook-supabase)
8. [Checklist Setelah Deploy](#8-checklist-setelah-deploy)
9. [Kalau Sudah Pernah Testing di XAMPP/Localhost](#9-kalau-sudah-pernah-testing-di-xampplocalhost)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Arsitektur Singkat

Supaya tidak salah upload folder, pahami dulu pembagian ini:

| Bagian | Isi | Ditaruh di |
|---|---|---|
| **`srm/`** (folder ini) | Kode publik website (halaman, admin panel, aset CSS/JS) | **DI DALAM** `public_html` (isinya langsung, bukan foldernya) |
| **`private/`** | Konfigurasi (`.env`), database SQLite lokal, cache, file upload produk, script CLI | **DI LUAR** `public_html`, sejajar dengannya |
| **Supabase** | Data produk (`gudang`) dan akun login admin (`admin_users`) | Cloud, bukan di hosting sama sekali |

Kenapa `private/` harus di luar `public_html`: supaya file `.env` (isinya kredensial Supabase) dan database SQLite **tidak bisa diakses lewat browser** oleh siapa pun.

Login admin (`admin_users`) dan data produk (`gudang`) tersimpan di **Supabase**, bukan di hosting. Artinya kalau Anda sudah setup Supabase-nya (lewat `supabase_schema.sql` dan `supabase_admin_users.sql`), **tidak perlu setup ulang** akun/produk setiap deploy ke server baru — cukup arahkan `.env` ke project Supabase yang sama.

---

## 2. Prasyarat

Sebelum mulai, pastikan sudah punya:

- [ ] Akses cPanel hosting (username, password, domain sudah aktif)
- [ ] Project Supabase yang sudah dijalankan `supabase_schema.sql` (tabel `gudang`) dan `supabase_admin_users.sql` (tabel `admin_users`, sudah ada minimal 1 akun Super Admin)
- [ ] Catatan `SUPABASE_URL` dan `SUPABASE_SERVICE_ROLE_KEY` dari project Supabase tersebut (Supabase Dashboard → Project Settings → API)
- [ ] File project ini (folder `srm/` dan `private/`) siap di komputer Anda

---

## 3. Struktur Folder di cPanel

Hasil akhir di server harus seperti ini (contoh dengan username cPanel `srmuser`):

```
/home/srmuser/
├── public_html/          <-- isi dari folder srm/ (LANGSUNG isinya, bukan foldernya)
│   ├── admin/
│   ├── assets/
│   ├── pages/
│   ├── templates/
│   ├── webhook/
│   ├── index.php
│   ├── bootstrap.php
│   ├── .htaccess
│   └── favicon.ico
│
└── private/               <-- folder private/ dari project, SEJAJAR dengan public_html
    ├── config/
    ├── data/               (akan berisi website.sqlite setelah migrate.php dijalankan)
    ├── cache/
    ├── storage/
    ├── scripts/
    ├── src/
    ├── webhook/
    └── .env                (buat manual, lihat bagian 5)
```

**Poin paling penting:** `private/` TIDAK BOLEH ada di dalam `public_html`. Kalau salah taruh, data sensitif (termasuk password Supabase) bisa diakses siapa saja lewat URL `https://domainanda.com/private/.env`.

---

## 4. Langkah Instalasi

### 4.1 Set Versi & Ekstensi PHP

1. Login cPanel → buka **"MultiPHP Manager"** atau **"Select PHP Version"**.
2. Pilih **PHP 8.1 atau lebih baru** untuk domain ini (kode memakai fitur PHP 8.0+ seperti `match()` dan union return type).
3. Masuk ke **"Extensions"**, pastikan tercentang:
   - `zip` (wajib — dipakai untuk membaca file `.xlsx` di menu Migrasi Data)
   - `xml`, `simplexml`
   - `curl` (wajib — semua komunikasi ke Supabase lewat ini)
   - `pdo_sqlite`, `sqlite3`
   - `mbstring`
   - `gd` (untuk resize/optimasi foto produk)

   Kebanyakan hosting cPanel sudah mengaktifkan semua ini secara default, tapi tetap cek dulu.

### 4.2 Upload File

Gunakan **File Manager** cPanel (upload `.zip` lalu Extract) atau **FTP/SFTP** (FileZilla, dll):

1. Upload **isi** folder `srm/` ke `public_html/` (bukan folder `srm` itu sendiri — isinya langsung).
2. Upload folder `private/` ke `/home/USERNAME/private/` (sejajar dengan `public_html`, **bukan** di dalamnya).

### 4.3 Buat & Isi `private/.env`

File `.env` sengaja **tidak** ikut di dalam paket project (demi keamanan). Buat file baru bernama `.env` langsung di dalam `private/` (bukan `private/config/`). Isinya lihat [bagian 5](#5-isi-file-privateenv).

### 4.4 Set Permission Folder

Lewat File Manager (klik kanan folder → *Change Permissions*) atau SSH (`chmod`), set folder berikut agar bisa ditulis oleh PHP:

```bash
chmod 755 private/data private/cache private/storage private/storage/products
```

Kalau nanti muncul error `failed to open stream: Permission denied`, ini yang perlu dicek ulang (coba naikkan ke `775` kalau `755` belum cukup, tergantung konfigurasi hosting).

### 4.5 Jalankan `migrate.php`

Buka cPanel → **"Terminal"** (atau SSH kalau diaktifkan hosting Anda):

```bash
cd ~/private
php scripts/migrate.php
```

Script ini membuat tabel-tabel SQLite lokal (`product_config`, `marketplaces`, `company_profile`, `settings`, `admin_audit_log`, dll) di server ini. **Ini wajib dijalankan di setiap server baru** (SQLite-nya lokal per-server, tidak sinkron otomatis seperti Supabase).

Kalau script mendeteksi tabel `admin_users` lama (dari versi sebelum login dipindah ke Supabase) dan menanyakan mau dipindahkan — jawab **`n`** kalau akun Anda sudah ada di Supabase dari migrasi sebelumnya.

### 4.6 Cek PHP CLI Path (kalau `php` tidak dikenali)

Kalau `php scripts/migrate.php` gagal dengan pesan "command not found", cPanel biasanya butuh path lengkap ke versi PHP CLI, contoh:

```bash
/usr/local/bin/php81 scripts/migrate.php
```

Path persisnya bisa dilihat di halaman **"Select PHP Version"** cPanel, biasanya ada contoh path CLI di bagian bawah halaman.

---

## 5. Isi File `private/.env`

```env
APP_ENV=production
APP_URL=https://domainanda.com

SUPABASE_URL=https://xxxxxxxxxxxx.supabase.co
SUPABASE_ANON_KEY=isi-dengan-anon-key-dari-supabase
SUPABASE_SERVICE_ROLE_KEY=isi-dengan-service-role-key-dari-supabase

SQLITE_PATH=/home/USERNAME/private/data/website.sqlite
CACHE_PATH=/home/USERNAME/private/cache
STORAGE_PATH=/home/USERNAME/private/storage

WEBHOOK_SECRET=buat-string-acak-yang-panjang-dan-rahasia
DEPLOY_WEBHOOK_TOKEN=isi-dengan-token-acak-panjang

COOKIE_SECURE=true
```

**Catatan penting per-baris:**

- `SUPABASE_URL` / `SUPABASE_ANON_KEY` / `SUPABASE_SERVICE_ROLE_KEY` — **harus sama** dengan project Supabase yang sudah Anda setup (tempat tabel `gudang` dan `admin_users` sudah ada). Ambil dari Supabase Dashboard → Project Settings → API.
- `SQLITE_PATH`, `CACHE_PATH`, `STORAGE_PATH` — pakai **path absolut** di server, ganti `USERNAME` dengan username cPanel Anda. Cek path home directory yang benar lewat File Manager kalau ragu.
- `WEBHOOK_SECRET` — string acak bebas (misal hasil dari [1Password/keychain generator], atau `php -r "echo bin2hex(random_bytes(32));"`). Nilai ini juga akan dipakai di setup webhook Supabase (bagian 7).
- `COOKIE_SECURE=true` — **wajib `true`** di production (beda dari waktu testing di `http://localhost`, yang perlu `false`). Kalau di-set `true` tapi situsnya belum HTTPS, login admin tidak akan berfungsi.

---

## 6. Setup Cron Job

cPanel → **"Cron Jobs"** → tambah cron baru:

- **Jadwal:** setiap 10–15 menit (contoh: `*/15 * * * *`)
- **Command:**
  ```bash
  php /home/USERNAME/private/scripts/cron_reconcile.php
  ```
  (Sesuaikan `php` dengan path CLI PHP yang benar, lihat [4.6](#46-cek-php-cli-path-kalau-php-tidak-dikenali) kalau perlu path lengkap.)

Cron ini adalah **jaring pengaman** — mengecek perubahan data `gudang` di Supabase yang mungkin terlewat kalau webhook (bagian 7) gagal terkirim. Bukan mekanisme sinkron utama, jadi tidak masalah kalau jaraknya agak jarang.

---

## 7. Setup Database Webhook Supabase

Ini mekanisme utama supaya perubahan data di Supabase (`gudang`) langsung ter-refresh cache-nya di website, tanpa nunggu cron.

1. Buka **Supabase Dashboard → Database → Webhooks → Create a new hook**.
2. **Table:** `gudang`
3. **Events:** Insert, Update, Delete
4. **Type:** HTTP Request, **Method:** POST
5. **URL:** `https://domainanda.com/webhook/supabase.php`
6. **HTTP Headers:** tambahkan header custom `X-Webhook-Secret` dengan value **sama persis** dengan `WEBHOOK_SECRET` di `private/.env`.

---

## 8. Checklist Setelah Deploy

- [ ] Buka `https://domainanda.com` → halaman utama tampil normal (bukan 500/blank)
- [ ] Buka `https://domainanda.com/private/.env` → harus **404 / tidak bisa diakses** (kalau muncul isinya, segera pindahkan folder `private/` keluar dari `public_html`!)
- [ ] Buka `https://domainanda.com/admin/login.php` → bisa login dengan akun Super Admin yang sudah ada di Supabase
- [ ] Cek halaman **Produk Website** di admin → tabel "Produk dari Supabase (Gudang)" menampilkan data (kalau kosong, cek `SUPABASE_URL`/key di `.env`, atau lakukan import data lewat menu **Migrasi Data**)
- [ ] Cek menu **Migrasi Data** & **Pengguna** hanya muncul untuk akun dengan role **Super Admin**
- [ ] Test upload 1 foto produk lewat admin, pastikan tersimpan & tampil (mengetes permission folder `private/storage`)

---

## 9. Kalau Sudah Pernah Testing di XAMPP/Localhost

Kalau sebelumnya sudah sempat isi data (company profile, marketplace links, foto produk) di XAMPP dan mau dibawa ke cPanel supaya tidak input ulang dari nol:

1. Upload juga file `private/data/website.sqlite` dari XAMPP Anda ke `private/data/` di cPanel (**menimpa** file yang dibuat `migrate.php` di langkah 4.5 — jalankan `migrate.php` dulu baru timpa file ini, supaya tabel-tabel barunya tetap lengkap).
2. Upload juga isi folder `private/storage/products/` dari XAMPP (foto-foto produk yang sudah diupload).
3. Akun login (`admin_users`) **tidak perlu** dipindahkan lagi — sudah ada di Supabase sejak migrasi sebelumnya, otomatis bisa dipakai dari server manapun.

---

## 10. Troubleshooting

**`Class "ZipArchive" not found`** saat upload file di menu Migrasi Data
→ Ekstensi PHP `zip` belum aktif. Aktifkan lewat cPanel → Select PHP Version → Extensions → centang `zip`.

**Halaman admin blank / error 500**
→ Cek folder `private/` sudah di path yang benar (sejajar `public_html`, bukan di dalamnya) dan `private/.env` sudah ada & lengkap. Cek juga PHP error log di cPanel (Metrics → Errors).

**Login gagal terus padahal password benar**
→ Cek `SUPABASE_URL` / `SUPABASE_SERVICE_ROLE_KEY` di `.env` sudah benar dan sesuai project yang punya tabel `admin_users`. Cek juga `COOKIE_SECURE=true` dan situs sudah berjalan di HTTPS (bukan HTTP).

**Tabel "Produk dari Supabase" kosong**
→ Belum ada data ter-import di project Supabase ini, atau `.env` mengarah ke project Supabase yang salah. Import data lewat menu **Migrasi Data** (Accurate `.xlsx` atau CSV generik).

**`php: command not found` di Terminal cPanel**
→ Pakai path lengkap ke PHP CLI, misal `/usr/local/bin/php81`. Cek path yang benar di halaman "Select PHP Version" cPanel.

**Perubahan data di Supabase tidak langsung muncul di website**
→ Cek webhook di Supabase Dashboard sudah ter-setup dengan benar (bagian 7) dan `X-Webhook-Secret` cocok dengan `WEBHOOK_SECRET` di `.env`. Sebagai fallback, cron (bagian 6) akan menyusulkan perubahan dalam 10–15 menit.

---

## 11. Keamanan: token & secret tidak boleh di-commit (2026-11-18)

- **Token deploy (`DEPLOY_WEBHOOK_TOKEN`) TIDAK PERNAH di-hardcode.** `cpanel-deploy.php` dan
  `cpanel-setup-key.php` membacanya dari environment, `private/.env`, atau
  `private/deploy-token.txt` (ketiganya di luar `public_html` dan tidak masuk git).
  Tanpa token yang cocok, deploy ditolak (fail-closed).
- **`private/.env` TIDAK BOLEH masuk git.** File ini hanya hidup di server (cPanel
  `~/private/.env`, chmod 0600). `.gitignore` sudah menutup `private/.env`,
  `private/deploy-token.txt`, `.env`, dan `error_log`.
- **Riwayat git lama masih menyimpan secret lama.** Menghapus file dari HEAD tidak
  menghapus history. Setelah commit ini, ROTASI wajib: kunci Supabase
  (`SUPABASE_SERVICE_ROLE_KEY`, `SUPABASE_SECRET_KEY`), kredensial R2
  (`R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_TOKEN_VALUE`), `WEBHOOK_SECRET`
  (update juga header webhook di Supabase), dan `DEPLOY_WEBHOOK_TOKEN`.
- **Webhook GitHub -> cPanel:** di repo Settings -> Webhooks, Payload URL
  `https://DOMAIN/cpanel-deploy.php?token=TOKEN_BARU`, Content type `application/json`,
  event push ke `main` saja. Prasyarat di cPanel: repo ter-clone di
  `~/repositories/cvsrirejekimotor` (deploy key read-only via `cpanel-setup-key.php`),
  dan `~/private/.env` berisi kunci hasil rotasi + `DEPLOY_WEBHOOK_TOKEN` baru.

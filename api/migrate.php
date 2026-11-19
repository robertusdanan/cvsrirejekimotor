<?php
declare(strict_types=1);

/**
 * Web-accessible wrapper untuk migrasi satu kali Supabase -> MySQL.
 *
 * /scripts/ diblokir .htaccess untuk akses web langsung (403 by design),
 * jadi endpoint api/ ini yang menjalankan skrip migrasi yang sama.
 * Auth: header X-Upload-Token (sama seperti endpoint upload gambar).
 *
 * HAPUS file ini setelah migrasi berhasil + Push 2 live.
 * (Skrip migrasi aslinya tetap di scripts/migrate_supabase_to_mysql.php.)
 */

require __DIR__ . '/../scripts/migrate_supabase_to_mysql.php';

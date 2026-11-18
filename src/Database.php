<?php
declare(strict_types=1);

/**
 * Local SQLite database — source of truth for website-specific data
 * (visibility, featured, description, images, marketplace links, company profile).
 *
 * This is NOT a cache. It is never rebuilt from Supabase and must be included
 * in backups. Supabase `gudang` data is never written back here except by
 * reference (gudang_id), and this class never writes to Supabase.
 */
final class Database
{
    public static $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $path = config('SQLITE_PATH');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
        }
        return self::$pdo;
    }
}

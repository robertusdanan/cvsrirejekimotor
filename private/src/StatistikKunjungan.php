<?php
declare(strict_types=1);

/**
 * Statistik pengunjung footer (Danan, 2026-11-18).
 *
 * - Menghitung SEMUA pengunjung, anonim tanpa login.
 * - Dedup per IP per hari (WIB): 1 IP = 1 kunjungan/hari. Tutup browser lalu buka
 *   lagi tetap terhitung 1x dalam sehari.
 * - IP ASLI TIDAK PERNAH DISIMPAN: yang dikirim ke Supabase hanya hash HMAC-SHA256
 *   dari IP + WEBHOOK_SECRET (server-side). Hash tidak bisa dibalik menjadi IP.
 *   File dedup lokal juga hanya berisi hash, dan hanya untuk hari ini.
 * - Bot/crawler tidak dihitung.
 * - PENGECUALIAN dari aturan lama "website tidak pernah menulis ke Supabase"
 *   (lihat Supabase.php): pengecualian ini eksplisit per perintah Danan 2026-11-18,
 *   dan tulisannya TERBATAS pada tabel statistik_kunjungan(_ip) lewat dua fungsi
 *   RPC (catat_kunjungan, baca_statistik). Tidak ada tulis lain ke Supabase.
 * - Semua kegagalan (Supabase down, tabel/fungsi belum dibuat) ditelan: footer
 *   tetap tampil dengan angka terakhir yang tersimpan lokal, atau 0.
 */
final class StatistikKunjungan
{
    private $writer;
    private $cache;

    private const CACHE_FILE = 'statistik.json';
    private const IP_FILE_PREFIX = 'statistik_ip_';

    public function __construct(?SupabaseWriter $writer = null, ?Cache $cache = null)
    {
        $this->writer = $writer ?? (class_exists('SupabaseWriter') ? new SupabaseWriter() : null);
        $this->cache = $cache ?? cache();
    }

    /** Tanggal "hari ini" dalam WIB. */
    public function hariIni(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    /** IP pengunjung: Cloudflare -> X-Forwarded-For (entri pertama) -> REMOTE_ADDR. */
    public function ipPengunjung(): string
    {
        $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
    }

    /** Hash IP (HMAC-SHA256 + secret server-side). Bukan IP, dan tidak bisa dibalik. */
    public function hashIp(string $ip): string
    {
        $salt = (string) config('WEBHOOK_SECRET');
        if ($salt === '') {
            $salt = 'sri-rejeki-motor-stats-v1';
        }
        return hash_hmac('sha256', $ip, $salt);
    }

    private function isBot(): bool
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return $ua !== '' && (bool) preg_match('/bot|crawl|spider|slurp|facebookexternal|googlebot|bingbot|yandex|duckduckbot|baiduspider|semrush|ahrefs/i', $ua);
    }

    /** Catat kunjungan (sekali per IP per hari). Aman dipanggil di setiap request. */
    public function catat(): void
    {
        try {
            if ($this->isBot()) {
                return;
            }
            $ip = $this->ipPengunjung();
            if ($ip === '') {
                return;
            }
            $today = $this->hariIni();
            $hash = $this->hashIp($ip);
            if ($this->writer === null) {
                return;
            }
            // Dedup lokal dulu: bila hash IP ini sudah tercatat hari ini, jangan panggil Supabase.
            if (in_array($hash, $this->bacaIpHariIni($today), true)) {
                return;
            }
            $row = $this->writer->callRpc('catat_kunjungan', ['p_ip_hash' => $hash]);
            if ($row !== null) {
                $this->tulisCache([
                    'tanggal' => $today,
                    'hari_ini' => (int) ($row['hari_ini'] ?? 0),
                    'total' => (int) ($row['total'] ?? 0),
                ]);
                $this->tambahIpHariIni($today, $hash);
            }
        } catch (Throwable $e) {
            error_log('StatistikKunjungan::catat skipped: ' . $e->getMessage());
        }
    }

    /** Baca angka untuk footer: cache lokal bila tanggalnya hari ini, bila tidak RPC baca. */
    public function baca(): array
    {
        try {
            $cached = $this->cache->read(self::CACHE_FILE);
            if (is_array($cached) && ($cached['tanggal'] ?? '') === $this->hariIni()) {
                return [
                    'hari_ini' => (int) ($cached['hari_ini'] ?? 0),
                    'total' => (int) ($cached['total'] ?? 0),
                ];
            }
            $row = $this->writer !== null ? $this->writer->callRpc('baca_statistik') : null;
            if ($row !== null) {
                $data = [
                    'tanggal' => $this->hariIni(),
                    'hari_ini' => (int) ($row['hari_ini'] ?? 0),
                    'total' => (int) ($row['total'] ?? 0),
                ];
                $this->tulisCache($data);
                return $data;
            }
            if (is_array($cached)) {
                return [
                    'hari_ini' => (int) ($cached['hari_ini'] ?? 0),
                    'total' => (int) ($cached['total'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            error_log('StatistikKunjungan::baca skipped: ' . $e->getMessage());
        }
        return ['hari_ini' => 0, 'total' => 0];
    }

    private function tulisCache(array $data): void
    {
        try {
            $this->cache->write(self::CACHE_FILE, $data);
        } catch (Throwable $e) {
            error_log('StatistikKunjungan::tulisCache skipped: ' . $e->getMessage());
        }
    }

    private function ipFileName(string $tanggal): string
    {
        return self::IP_FILE_PREFIX . $tanggal . '.json';
    }

    /** Daftar hash IP yang sudah tercatat pada tanggal itu (file lokal, hanya hash). */
    private function bacaIpHariIni(string $tanggal): array
    {
        $rows = $this->cache->read($this->ipFileName($tanggal));
        return is_array($rows) ? array_values($rows) : [];
    }

    private function tambahIpHariIni(string $tanggal, string $hash): void
    {
        try {
            $rows = $this->bacaIpHariIni($tanggal);
            if (in_array($hash, $rows, true)) {
                return;
            }
            $rows[] = $hash;
            $this->cache->write($this->ipFileName($tanggal), $rows);
            $this->buangFileIpLama($tanggal);
        } catch (Throwable $e) {
            error_log('StatistikKunjungan::tambahIpHariIni skipped: ' . $e->getMessage());
        }
    }

    /** Hapus file dedup IP tanggal lain (hanya hari ini yang dibutuhkan). */
    private function buangFileIpLama(string $tanggalAktif): void
    {
        $dir = rtrim($this->cache->basePath, '/');
        $files = glob($dir . '/' . self::IP_FILE_PREFIX . '*.json');
        if (!is_array($files)) {
            return;
        }
        $keep = $this->ipFileName($tanggalAktif);
        foreach ($files as $file) {
            if (basename((string) $file) !== $keep) {
                @unlink((string) $file);
            }
        }
    }
}

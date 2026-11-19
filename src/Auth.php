<?php
declare(strict_types=1);

final class Auth
{
    public static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // COOKIE_SECURE should be "true" in production (HTTPS) and "false" for local
        // testing over plain http:// (e.g. XAMPP on http://localhost), since browsers
        // refuse to send secure cookies over a non-HTTPS connection.
        $secure = strtolower(config('COOKIE_SECURE', 'true')) !== 'false';
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_name('srm_admin_session');
        session_start();
    }

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300; // 5 minutes

    public static $adminUsers = null;

    private static function adminUsers(): MySQLAdminUsers
    {
        if (self::$adminUsers === null) {
            self::$adminUsers = new MySQLAdminUsers();
        }
        return self::$adminUsers;
    }

    /** Shared PostgreSQL client for login_throttle / admin_audit_log. */
    private static function writer(): MySQLWriter
    {
        static $writer = null;
        if ($writer === null) {
            $writer = new MySQLWriter();
        }
        return $writer;
    }

    public static function attempt(string $username, string $password): bool
    {
        // Login accounts, the per-IP brute-force throttle (login_throttle) and the
        // audit log (admin_audit_log) all live in PostgreSQL now — no local storage.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $row = null;
        try {
            $row = self::writer()->getLoginThrottle($ip);
        } catch (Throwable $e) {
            // Throttle table unreachable: fail open on the throttle check only,
            // the password check below still applies.
            error_log('Auth::attempt: throttle lookup failed: ' . $e->getMessage());
        }

        if ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            usleep(300000);
            return false; // still locked out
        }

        try {
            $user = self::adminUsers()->findByUsername($username);
        } catch (Throwable $e) {
            error_log('Auth::attempt: PostgreSQL lookup failed: ' . $e->getMessage());
            usleep(300000);
            return false; // fail closed if Supabase is unreachable
        }

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            usleep(300000); // slow down brute-force attempts
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            $lockedUntil = $attempts >= self::MAX_ATTEMPTS
                ? gmdate('Y-m-d\TH:i:s\Z', time() + self::LOCKOUT_SECONDS)
                : null;
            try {
                self::writer()->saveLoginThrottle($ip, $attempts, $lockedUntil);
            } catch (Throwable $e) {
                error_log('Auth::attempt: throttle update failed: ' . $e->getMessage());
            }
            return false;
        }

        // Successful login resets the throttle counter.
        try {
            self::writer()->clearLoginThrottle($ip);
        } catch (Throwable $e) {
            error_log('Auth::attempt: throttle reset failed: ' . $e->getMessage());
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role'] = $user['role'] ?? 'staff';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function role(): string
    {
        return $_SESSION['admin_role'] ?? 'staff';
    }

    public static function isSuperAdmin(): bool
    {
        return self::role() === 'superadmin';
    }

    /**
     * Restricts a page to superadmin only. Must be called after requireLogin().
     * Staff accounts get a plain 403 rather than a silent redirect, so it's
     * obvious the page exists but access is denied (not just missing).
     */
    public static function requireSuperAdmin(): void
    {
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            require __DIR__ . '/../admin/templates/admin_header.php';
            echo '<h1>Akses Ditolak</h1><p class="admin-meta">Halaman ini hanya bisa diakses oleh Super Admin.</p>';
            require __DIR__ . '/../admin/templates/admin_footer.php';
            exit;
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function audit(string $action, string $detail = ''): void
    {
        try {
            self::writer()->logAudit(
                $_SESSION['admin_username'] ?? 'unknown',
                $action,
                $detail
            );
        } catch (Throwable $e) {
            error_log('audit skipped: ' . $action . ' — ' . $e->getMessage());
        }
    }
}

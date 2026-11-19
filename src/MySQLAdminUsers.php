<?php
declare(strict_types=1);

/**
 * MySQL client untuk tabel `admin_users` — tempat akun login panel admin
 * (username/password_hash/role) tinggal. Pengganti SupabaseAdminUsers
 * (per Danan 2026-11-19: database penuh MySQL di cPanel).
 *
 * Interface publiknya SENGAJA disamakan persis dengan SupabaseAdminUsers
 * (findByUsername, findById, listAll, create, updateRole, updatePassword,
 * delete) supaya admin/users.php dan Auth.php tidak perlu berubah
 * selain nama class yang dipakai.
 *
 * Dipakai oleh Auth.php (login) dan admin/users.php (manajemen akun).
 * Jangan include dari halaman yang tidak melewati Auth::requireLogin().
 */
final class MySQLAdminUsers
{
    /** @var PDO */
    private $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? MySQLWriter::pdo();
    }

    public function findByUsername(string $username): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, created_at, updated_at ' .
            'FROM admin_users WHERE username = ? LIMIT 1'
        );
        $st->execute([$username]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    public function findById(string $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, created_at, updated_at ' .
            'FROM admin_users WHERE id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    public function listAll(): array
    {
        return $this->pdo->query(
            'SELECT id, username, password_hash, role, created_at, updated_at ' .
            'FROM admin_users ORDER BY created_at ASC'
        )->fetchAll();
    }

    public function create(string $username, string $passwordHash, string $role): array
    {
        $id = MySQLWriter::uuid();
        $st = $this->pdo->prepare(
            'INSERT INTO admin_users (id, username, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$id, $username, $passwordHash, $role]);
        $row = $this->findById($id);
        return $row ?? ['id' => $id, 'username' => $username, 'password_hash' => $passwordHash, 'role' => $role];
    }

    public function updateRole(string $id, string $role): void
    {
        $st = $this->pdo->prepare('UPDATE admin_users SET role = ? WHERE id = ?');
        $st->execute([$role, $id]);
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $st = $this->pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        $st->execute([$passwordHash, $id]);
    }

    public function delete(string $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM admin_users WHERE id = ?');
        $st->execute([$id]);
    }
}

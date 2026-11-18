<?php
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();
Auth::requireSuperAdmin();

$client = supabaseAdminUsers();
$flash = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        http_response_code(400);
        exit('CSRF token tidak valid.');
    }
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? 'staff') === 'superadmin' ? 'superadmin' : 'staff';

            if ($username === '' || strlen($password) < 8) {
                $error = 'Username wajib diisi dan password minimal 8 karakter.';
            } elseif ($client->findByUsername($username) !== null) {
                $error = "Username '{$username}' sudah dipakai.";
            } else {
                $client->create($username, password_hash($password, PASSWORD_DEFAULT), $role);
                Auth::audit('admin_user_created', $username . ' (' . $role . ')');
                $flash = "Pengguna '{$username}' berhasil ditambahkan.";
            }
        } elseif ($action === 'change_role') {
            $id = (string) ($_POST['id'] ?? '');
            $role = ($_POST['role'] ?? 'staff') === 'superadmin' ? 'superadmin' : 'staff';

            $all = $client->listAll();
            $target = null;
            foreach ($all as $u) {
                if ($u['id'] === $id) {
                    $target = $u;
                    break;
                }
            }
            $superadminCount = count(array_filter($all, static fn ($u) => $u['role'] === 'superadmin'));

            if ($target === null) {
                $error = 'Pengguna tidak ditemukan.';
            } elseif ($target['role'] === 'superadmin' && $role === 'staff' && $superadminCount <= 1) {
                $error = 'Tidak bisa mengubah role ini — minimal harus ada satu Super Admin.';
            } else {
                $client->updateRole($id, $role);
                Auth::audit('admin_user_role_changed', $target['username'] . ' -> ' . $role);
                $flash = "Role '{$target['username']}' diperbarui menjadi " . ($role === 'superadmin' ? 'Super Admin' : 'Staff') . '.';
            }
        } elseif ($action === 'reset_password') {
            $id = (string) ($_POST['id'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            $target = $client->findById($id);
            if ($target === null) {
                $error = 'Pengguna tidak ditemukan.';
            } elseif (strlen($password) < 8) {
                $error = 'Password baru minimal 8 karakter.';
            } else {
                $client->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
                Auth::audit('admin_user_password_reset', $target['username']);
                $flash = "Password untuk '{$target['username']}' berhasil diganti.";
            }
        } elseif ($action === 'delete') {
            $id = (string) ($_POST['id'] ?? '');

            $all = $client->listAll();
            $target = null;
            foreach ($all as $u) {
                if ($u['id'] === $id) {
                    $target = $u;
                    break;
                }
            }
            $superadminCount = count(array_filter($all, static fn ($u) => $u['role'] === 'superadmin'));

            if ($target === null) {
                $error = 'Pengguna tidak ditemukan.';
            } elseif ($id === (string) $_SESSION['admin_id']) {
                $error = 'Tidak bisa menghapus akun Anda sendiri saat sedang login.';
            } elseif ($target['role'] === 'superadmin' && $superadminCount <= 1) {
                $error = 'Tidak bisa menghapus Super Admin terakhir.';
            } else {
                $client->delete($id);
                Auth::audit('admin_user_deleted', $target['username']);
                $flash = "Pengguna '{$target['username']}' dihapus.";
            }
        }
    } catch (Throwable $e) {
        error_log('users.php: ' . $e->getMessage());
        $error = 'Gagal menghubungi Supabase: ' . $e->getMessage();
    }
}

try {
    $users = $client->listAll();
} catch (Throwable $e) {
    error_log('users.php: ' . $e->getMessage());
    $users = [];
    $error = $error ?? ('Gagal memuat daftar pengguna dari Supabase: ' . $e->getMessage());
}

require __DIR__ . '/templates/admin_header.php';
?>
<h1>Kelola Pengguna Admin</h1>
<p class="admin-meta">
  <strong>Super Admin</strong> memiliki akses penuh ke seluruh pengaturan sistem.
  <strong>Staff</strong> dapat mengelola katalog produk, marketplace, informasi profil, dan hero beranda.
</p>

<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>
<?php if ($error): ?><p class="flash" style="background:#fbe7e8;color:#8c1c22;border-left-color:#8c1c22;"><?= e($error) ?></p><?php endif; ?>

<h2>Tambah Pengguna Baru</h2>
<form method="post" class="admin-panel">
  <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
  <input type="hidden" name="action" value="add">
  <label>Username <input type="text" name="username" required autocomplete="off"></label>
  <label>Password (minimal 8 karakter) <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
  <label>Role
    <select name="role">
      <option value="staff">Staff</option>
      <option value="superadmin">Super Admin</option>
    </select>
  </label>
  <button type="submit" class="btn btn-primary">Tambah Pengguna</button>
</form>

<h2>Daftar Pengguna</h2>
<table class="admin-table">
  <thead><tr><th>Username</th><th>Role</th><th>Dibuat</th><th>Ganti Password</th><th>Aksi</th></tr></thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['username']) ?><?= $u['id'] === (string) $_SESSION['admin_id'] ? ' <span class="badge badge-accent">Anda</span>' : '' ?></td>
        <td>
          <form method="post" class="admin-inline-form" style="margin:0;">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
            <select name="role" onchange="this.form.submit()">
              <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
              <option value="superadmin" <?= $u['role'] === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
            </select>
          </form>
        </td>
        <td><?= e((string) $u['created_at']) ?></td>
        <td>
          <form method="post" class="admin-inline-form" style="margin:0;" onsubmit="return confirm('Ganti password untuk <?= e($u['username']) ?>?');">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
            <input type="password" name="password" placeholder="Password baru" minlength="8" required autocomplete="new-password">
            <button type="submit" class="btn-small">Ganti</button>
          </form>
        </td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Hapus pengguna <?= e($u['username']) ?>? Tindakan ini tidak bisa dibatalkan.');">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
            <button type="submit" class="btn-link btn-link-danger">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/templates/admin_footer.php'; ?>

<?php
session_start();
require_once '../includes/functions.php';
require_once '../config/database.php';

// Admin authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin-login.php");
    exit;
}

$message = '';
$messageType = '';

// Handle create admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    if (empty($username) || empty($password) || empty($fullName) || empty($email)) {
        $message = "❌ Semua field harus diisi!";
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = "❌ Password minimal 6 karakter!";
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Format email tidak valid!";
        $messageType = 'error';
    } else {
        if (createAdminUser($username, $password, $fullName, $email)) {
            $message = "✅ Admin berhasil ditambahkan!";
            $messageType = 'success';
        } else {
            $message = "❌ Username sudah digunakan!";
            $messageType = 'error';
        }
    }
}

// Handle update admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $userId = intval($_POST['user_id'] ?? 0);
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $updateData = [
        'full_name' => $fullName,
        'email' => $email,
        'is_active' => $isActive
    ];

    if (!empty($password)) {
        $updateData['password'] = $password;
    }

    if (updateAdminUser($userId, $updateData)) {
        $message = "✅ Admin berhasil diperbarui!";
        $messageType = 'success';
    } else {
        $message = "❌ Gagal memperbarui admin!";
        $messageType = 'error';
    }
}

// Handle delete admin
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $userId = intval($_GET['id']);

    // Prevent deleting yourself
    if ($userId === $_SESSION['admin_id']) {
        $message = "❌ Tidak bisa menghapus akun sendiri!";
        $messageType = 'error';
    } else {
        if (deleteAdminUser($userId)) {
            $message = "✅ Admin berhasil dihapus!";
            $messageType = 'success';
        } else {
            $message = "❌ Gagal menghapus admin!";
            $messageType = 'error';
        }
    }
}

// Get all admin users
$admins = getAllAdminUsers();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Admin - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>◆ ABLE.ID</h3>
            </div>
            <div class="sidebar-section-title">Menu</div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="generate-token.php" class="nav-item">
                    <span>🔑</span> Generate Token
                </a>
                <a href="manage-tokens.php" class="nav-item">
                    <span>📋</span> Kelola Token
                </a>
            </nav>
            <div class="sidebar-section-title">Data</div>
            <nav class="sidebar-nav">
                <a href="manage-questions.php" class="nav-item">
                    <span>❓</span> Kelola Soal
                </a>
                <a href="view-results.php" class="nav-item">
                    <span>📈</span> Lihat Hasil
                </a>
                <a href="manage-admins.php" class="nav-item active">
                    <span>👥</span> Manage Admin
                </a>
            </nav>
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="database-maintenance.php" class="nav-item">
                    <span>🔧</span> Database Maint.
                </a>
                <a href="../index.php" class="nav-item">
                    <span>🏠</span> Ke Website
                </a>
                <a href="../logout.php" class="nav-item nav-logout">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Topbar -->
            <div class="admin-topbar">
                <div class="admin-topbar-left">👥 Kelola Admin</div>
                <div class="admin-topbar-right">
                    <button onclick="showCreateForm()" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px;">
                        + Tambah Admin
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- Create Admin Form (Hidden by default) -->
                <div class="card" id="createForm" style="display: none; margin-bottom: 24px; border: 2px solid #667eea;">
                    <div style="padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 8px 8px 0 0;">
                        <h3 style="margin: 0; font-size: 16px;">➕ Tambah Admin Baru</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create">

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Username</label>
                                    <input type="text" name="username" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Nama Lengkap</label>
                                    <input type="text" name="full_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Email</label>
                                    <input type="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Password</label>
                                    <input type="password" name="password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                </div>
                            </div>

                            <div style="display: flex; gap: 12px;">
                                <button type="submit" class="btn btn-success">✅ Tambah Admin</button>
                                <button type="button" onclick="hideCreateForm()" class="btn btn-secondary">❌ Batal</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Admins List -->
                <div class="section-header">
                    <h2 class="section-title">👥 Daftar Admin (<?= count($admins) ?> total)</h2>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">👤 Username</th>
                                    <th style="width: 25%;">📧 Nama Lengkap</th>
                                    <th style="width: 25%;">✉️ Email</th>
                                    <th style="width: 15%;">📅 Dibuat</th>
                                    <th style="width: 10%;">📊 Status</th>
                                    <th style="width: 10%;">⚙️ Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($admins) > 0): ?>
                                    <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td style="font-family: monospace; font-weight: 600; color: #667eea;">
                                            <?= htmlspecialchars($admin['username']) ?>
                                            <?php if ($admin['id'] === $_SESSION['admin_id']): ?>
                                                <span style="font-size: 10px; background: #667eea; color: white; padding: 2px 6px; border-radius: 10px;">YOU</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($admin['full_name']) ?></td>
                                        <td><?= htmlspecialchars($admin['email']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($admin['created_at'])) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $admin['is_active'] ? 'success' : 'danger' ?>">
                                                <?= $admin['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                                <button onclick="editAdmin(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['full_name']) ?>', '<?= htmlspecialchars($admin['email']) ?>', <?= $admin['is_active'] ? 1 : 0 ?>)" class="btn-action btn-action-warning" title="Edit">
                                                    ✏️
                                                </button>
                                                <?php if ($admin['id'] !== $_SESSION['admin_id']): ?>
                                                    <a href="?action=delete&id=<?= $admin['id'] ?>" class="btn-action btn-action-danger" onclick="return confirm('Hapus admin ini?');" title="Delete">
                                                        🗑️
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px 20px; color: #999;">
                                            <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                                            Tidak ada admin
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px;">
            <h3 style="margin: 0 0 20px 0; color: #333;">✏️ Edit Admin</h3>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">Username</label>
                        <input type="text" id="edit_username" readonly style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f5f5f5;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">Nama Lengkap</label>
                        <input type="text" name="full_name" id="edit_full_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">Email</label>
                        <input type="email" name="email" id="edit_email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">Password Baru (kosongkan jika tidak diubah)</label>
                        <input type="password" name="password" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Akun Aktif</span>
                    </label>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-success">✅ Simpan</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">❌ Batal</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .btn-action {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-action-warning {
            background: #ffeaa7;
            color: #d63031;
        }

        .btn-action-warning:hover {
            background: #ffde57;
        }

        .btn-action-danger {
            background: #fee;
            color: #c00;
        }

        .btn-action-danger:hover {
            background: #fcc;
        }
    </style>

    <script>
        function showCreateForm() {
            document.getElementById('createForm').style.display = 'block';
            document.getElementById('createForm').scrollIntoView({ behavior: 'smooth' });
        }

        function hideCreateForm() {
            document.getElementById('createForm').style.display = 'none';
        }

        function editAdmin(id, username, fullName, email, isActive) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_is_active').checked = isActive == 1;

            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>

<?php
session_start();
require_once '../includes/functions.php';
require_once '../config/database.php';

// Admin authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin-login.php");
    exit;
}

$db = getDBConnection();
$message = '';
$message_type = '';

// --- FITUR AUTO HAPUS TOKEN EXPIRED (TESTING: 1 MENIT) ---
// Menghapus token yang belum digunakan (is_used = 0) DAN dibuat lebih dari 1 menit yang lalu
try {
    // Ubah INTERVAL 1 MINUTE menjadi INTERVAL 30 DAY untuk production nanti
    // $autoDeleteStmt = $db->query("DELETE FROM tokens WHERE is_used = 0 AND created_at < (NOW() - INTERVAL 1 MINUTE)");
    $autoDeleteStmt = $db->query("DELETE FROM tokens WHERE is_used = 0 AND created_at < (NOW() - INTERVAL 30 DAY)");
    // Opsional: Uncomment baris bawah jika ingin pesan notifikasi auto-delete muncul
    if ($autoDeleteStmt->rowCount() > 0) {
        $message = $autoDeleteStmt->rowCount() . " token expired (test 1 menit) otomatis dihapus.";
        $message_type = 'info';
    }
} catch (Exception $e) {
    // Silent error agar tidak mengganggu flow utama
}

// --- HANDLE BULK DELETE (HAPUS BANYAK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete_selected') {
    if (isset($_POST['selected_tokens']) && is_array($_POST['selected_tokens'])) {
        $idsToDelete = array_map('intval', $_POST['selected_tokens']);
        if (!empty($idsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            
            // Cek dulu apakah ada token yang sedang digunakan (opsional, tergantung kebijakan)
            // Di sini kita izinkan hapus paksa atau bisa difilter
            
            try {
                // Hapus jawaban & hasil tes terkait dulu (Foreign Key Constraint)
                $stmt1 = $db->prepare("DELETE FROM user_answers WHERE token_id IN ($placeholders)");
                $stmt1->execute($idsToDelete);
                
                $stmt2 = $db->prepare("DELETE FROM test_results WHERE token_id IN ($placeholders)");
                $stmt2->execute($idsToDelete);
                
                // Hapus tokennya
                $stmt3 = $db->prepare("DELETE FROM tokens WHERE id IN ($placeholders)");
                $stmt3->execute($idsToDelete);
                
                $message = count($idsToDelete) . " token berhasil dihapus!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Gagal menghapus token terpilih: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// --- HANDLE DELETE ALL (HAPUS SEMUA) ---
if (isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    try {
        // Kosongkan tabel terkait dulu
        $db->query("TRUNCATE TABLE user_answers"); // Atau DELETE FROM jika TRUNCATE gagal karena FK
        $db->query("DELETE FROM test_results");   // DELETE lebih aman untuk FK
        $db->query("DELETE FROM tokens");         // Hapus semua token
        
        // Reset Auto Increment (Opsional)
        // $db->query("ALTER TABLE tokens AUTO_INCREMENT = 1"); 

        $message = "SEMUA token berhasil dihapus bersih!";
        $message_type = 'success';
    } catch (Exception $e) {
        // Fallback jika TRUNCATE gagal karena foreign key checks
        try {
            $db->query("DELETE FROM user_answers");
            $db->query("DELETE FROM test_results");
            $db->query("DELETE FROM tokens");
            $message = "SEMUA token berhasil dihapus bersih!";
            $message_type = 'success';
        } catch (Exception $ex) {
            $message = "Gagal menghapus semua data: " . $ex->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle delete single (Satu per satu)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['token_id'])) {
    $tokenId = intval($_GET['token_id']);
    // Cek status deleted (opsional: cek is_used)
    try {
        // Hapus data terkait
        $db->prepare("DELETE FROM user_answers WHERE token_id = ?")->execute([$tokenId]);
        $db->prepare("DELETE FROM test_results WHERE token_id = ?")->execute([$tokenId]);
        
        $deleteStmt = $db->prepare("DELETE FROM tokens WHERE id = ?");
        if ($deleteStmt->execute([$tokenId])) {
            $message = "Token berhasil dihapus!";
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = "Gagal menghapus token: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle reset single
if (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['token_id'])) {
    $tokenId = intval($_GET['token_id']);
    try {
        $db->prepare("DELETE FROM user_answers WHERE token_id = ?")->execute([$tokenId]);
        $db->prepare("DELETE FROM test_results WHERE token_id = ?")->execute([$tokenId]);
        
        $resetStmt = $db->prepare("
            UPDATE tokens 
            SET is_used = 0, user_name = NULL, user_email = NULL, test_started_at = NULL, test_completed_at = NULL
            WHERE id = ?
        ");
        $resetStmt->execute([$tokenId]);
        $message = "Token berhasil direset!";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "Gagal reset token!";
        $message_type = 'error';
    }
}

// Handle generate token logic (tetap dipertahankan jika admin mau generate dari sini)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    // ... (Logika generate sama seperti sebelumnya, disingkat untuk fokus ke fitur baru) ...
    // Jika Anda ingin fitur generate tetap ada di halaman ini, biarkan kode generate lama Anda di sini.
    // Jika tidak, bisa dihapus agar fokus ke management.
}

// Pagination & Filter Logic
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (token_code LIKE ? OR user_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter !== 'all') {
    if ($filter === 'unused') {
        $where .= " AND is_used = 0 AND expires_at > NOW()";
    } elseif ($filter === 'used') {
        $where .= " AND is_used = 1";
    } elseif ($filter === 'completed') {
        $where .= " AND test_completed_at IS NOT NULL";
    } elseif ($filter === 'expired') {
        $where .= " AND expires_at < NOW()";
    }
}

// Count total
$countQuery = "SELECT COUNT(*) as total FROM tokens WHERE $where";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params); 
$totalTokens = $countStmt->fetch()['total'];
$totalPages = ceil($totalTokens / $limit);

// Get data
$tokensStmt = $db->prepare("
    SELECT 
        id, token_code, is_used, user_name, user_email, created_at, expires_at, test_completed_at,
        CASE 
            WHEN expires_at < NOW() THEN 'Expired'
            WHEN test_completed_at IS NOT NULL THEN 'Selesai'
            WHEN is_used = TRUE THEN 'Digunakan'
            ELSE 'Belum Digunakan'
        END as status
    FROM tokens 
    WHERE $where
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");
$tokensStmt->execute($params);
$tokens = $tokensStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Token - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script>
        // JavaScript untuk fitur Select All
        function toggleSelectAll(source) {
            checkboxes = document.getElementsByName('selected_tokens[]');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
            toggleBulkButton();
        }

        // JavaScript untuk mengaktifkan tombol Hapus Terpilih
        function toggleBulkButton() {
            var checkboxes = document.getElementsByName('selected_tokens[]');
            var checkedOne = Array.prototype.slice.call(checkboxes).some(x => x.checked);
            var btn = document.getElementById('btnBulkDelete');
            if (checkedOne) {
                btn.disabled = false;
                btn.classList.remove('btn-disabled');
                btn.classList.add('btn-danger');
            } else {
                btn.disabled = true;
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-disabled');
            }
        }
    </script>
    <style>
        .btn-disabled {
            background-color: #ccc !important;
            cursor: not-allowed;
            color: #666;
            border: 1px solid #bbb;
        }
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .checkbox-cell {
            text-align: center;
            width: 40px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <h3><i class="fas fa-shapes"></i> ABLE.ID</h3>
            </div>
            <div class="sidebar-section-title">Menu</div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item"><span><i class="fas fa-chart-line"></i></span> Dashboard</a>
                <a href="generate-token.php" class="nav-item"><span><i class="fas fa-key"></i></span> Generate Token</a>
                <a href="manage-tokens.php" class="nav-item active"><span><i class="fas fa-list-alt"></i></span> Kelola Token</a>
            </nav>
            <div class="sidebar-section-title">Data</div>
            <nav class="sidebar-nav">
                <a href="manage-questions.php" class="nav-item"><span><i class="fas fa-question-circle"></i></span> Kelola Soal</a>
                <a href="view-results.php" class="nav-item"><span><i class="fas fa-poll"></i></span> Lihat Hasil</a>
            </nav>
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="database-maintenance.php" class="nav-item"><span><i class="fas fa-tools"></i></span> Database Maint.</a>
                <a href="../index.php" class="nav-item"><span><i class="fas fa-home"></i></span> Ke Website</a>
                <a href="../logout.php" class="nav-item nav-logout"><span><i class="fas fa-sign-out-alt"></i></span> Logout</a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left"><i class="fas fa-tasks"></i> Manage Token</div>
                <div class="admin-topbar-right">
                    <a href="generate-token.php" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px;">
                        <i class="fas fa-plus"></i> Generate Baru
                    </a>
                </div>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : ($message_type === 'info' ? 'info' : 'danger') ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-bottom: 20px;">
                    <div style="padding: 20px;">
                        <form method="GET" action="" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px;">
                            <div class="form-group" style="margin: 0;">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari token atau nama..." style="width: 100%; padding: 10px;">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <select name="filter" style="width: 100%; padding: 10px;">
                                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="unused" <?= $filter === 'unused' ? 'selected' : '' ?>>Belum Digunakan</option>
                                    <option value="used" <?= $filter === 'used' ? 'selected' : '' ?>>Digunakan</option>
                                    <option value="completed" <?= $filter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                                    <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
                            <a href="manage-tokens.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
                        </form>
                    </div>
                </div>

                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin melakukan aksi ini pada token terpilih?');">
                    <input type="hidden" name="bulk_action" value="delete_selected">

                    <div class="actions-bar">
                        <div>
                            <button type="submit" id="btnBulkDelete" class="btn btn-disabled" disabled style="padding: 8px 16px; font-size: 13px;">
                                <i class="fas fa-trash-alt"></i> Hapus Terpilih
                            </button>
                            <span style="font-size: 13px; color: #666; margin-left: 10px;">
                                *Centang kotak di bawah untuk mengaktifkan tombol hapus
                            </span>
                        </div>
                        
                        <div style="margin-left: auto;">
                             <button type="button" onclick="confirmDeleteAll()" class="btn btn-danger" style="background: #c0392b; border: 1px solid #a93226; padding: 8px 16px; font-size: 13px;">
                                <i class="fas fa-bomb"></i> Hapus SEMUA Token
                            </button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th class="checkbox-cell">
                                            <input type="checkbox" onclick="toggleSelectAll(this)">
                                        </th>
                                        <th><i class="fas fa-ticket-alt"></i> Token</th>
                                        <th><i class="fas fa-info-circle"></i> Status</th>
                                        <th><i class="fas fa-user"></i> User</th>
                                        <th><i class="fas fa-calendar-plus"></i> Dibuat</th>
                                        <th><i class="fas fa-calendar-times"></i> Expired</th>
                                        <th><i class="fas fa-cogs"></i> Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($tokens) > 0): ?>
                                        <?php foreach ($tokens as $token): ?>
                                        <tr>
                                            <td class="checkbox-cell">
                                                <input type="checkbox" name="selected_tokens[]" value="<?= $token['id'] ?>" onclick="toggleBulkButton()">
                                            </td>
                                            <td style="font-family: monospace; font-weight: 600; color: #667eea;">
                                                <?= $token['token_code'] ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $token['status'] === 'Expired' ? 'danger' : 
                                                    ($token['status'] === 'Selesai' ? 'success' : 
                                                    ($token['status'] === 'Digunakan' ? 'warning' : 'info'))
                                                ?>">
                                                    <?= $token['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div><strong><?= htmlspecialchars($token['user_name'] ?? '-') ?></strong></div>
                                                <small style="color: #999;"><?= htmlspecialchars($token['user_email'] ?? '-') ?></small>
                                            </td>
                                            <td><?= date('d/m/y H:i', strtotime($token['created_at'])) ?></td>
                                            <td><?= date('d/m/y H:i', strtotime($token['expires_at'])) ?></td>
                                            <td>
                                                <div style="display: flex; gap: 6px;">
                                                    <a href="?action=delete&token_id=<?= $token['id'] ?>" class="btn-action btn-action-danger" onclick="return confirm('Hapus token ini?');" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php if ($token['is_used']): ?>
                                                        <a href="?action=reset&token_id=<?= $token['id'] ?>" class="btn-action btn-action-warning" onclick="return confirm('Reset token ini?');" title="Reset">
                                                            <i class="fas fa-sync-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                                Tidak ada token yang ditemukan.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form> <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 12px; padding: 6px 12px;"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <form id="deleteAllForm" method="POST" action="">
        <input type="hidden" name="action" value="delete_all">
    </form>

    <script>
        function confirmDeleteAll() {
            var confirmation = confirm("PERINGATAN KERAS!\n\nAksi ini akan MENGHAPUS SEMUA TOKEN, hasil tes, dan data user yang ada di database.\n\nData yang dihapus TIDAK BISA DIKEMBALIKAN.\n\nApakah Anda benar-benar yakin?");
            if (confirmation) {
                var doubleCheck = confirm("Konfirmasi Terakhir: Hapus SELURUH data token?");
                if (doubleCheck) {
                    document.getElementById('deleteAllForm').submit();
                }
            }
        }
    </script>
    <style>
        .btn-action {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 28px; height: 28px;
            border-radius: 4px; font-size: 12px;
            text-decoration: none; border: none; cursor: pointer;
        }
        .btn-action-danger { background: #fee; color: #c00; }
        .btn-action-danger:hover { background: #fcc; color: #900; }
        .btn-action-warning { background: #ffeaa7; color: #d63031; }
        .btn-action-warning:hover { background: #ffde57; color: #b71c1c; }
    </style>
</body>
</html>
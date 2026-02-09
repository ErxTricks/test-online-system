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

// Handle generate token from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($quantity < 1 || $quantity > 100) {
        $message = "Jumlah token harus antara 1-100!";
        $message_type = 'error';
    } else {
        try {
            // Get last sequence safely
            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(token_code, 6, 3) AS UNSIGNED)) as last_seq FROM tokens");
            $result = $stmt->fetch();
            $lastSeq = $result['last_seq'] ?? 0;

            $generated = [];
            $expiryDate = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            for ($i = 1; $i <= $quantity; $i++) {
                $token = generateToken($lastSeq + $i);
                
                $stmt = $db->prepare("INSERT INTO tokens (token_code, expires_at) VALUES (?, ?)");
                $stmt->execute([$token, $expiryDate]);
                $generated[] = $token;
            }
            
            $message = "Berhasil generate " . count($generated) . " token!";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "Gagal generate token: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['token_id'])) {
    $tokenId = intval($_GET['token_id']);
    $checkStmt = $db->prepare("SELECT is_used FROM tokens WHERE id = ?");
    $checkStmt->execute([$tokenId]);
    $token = $checkStmt->fetch();
    
    if ($token && $token['is_used']) {
        $message = "Tidak bisa menghapus token yang sudah digunakan!";
        $message_type = 'error';
    } else {
        $deleteStmt = $db->prepare("DELETE FROM tokens WHERE id = ?");
        if ($deleteStmt->execute([$tokenId])) {
            $message = "Token berhasil dihapus!";
            $message_type = 'success';
        }
    }
}

// Handle reset
if (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['token_id'])) {
    $tokenId = intval($_GET['token_id']);
    try {
        // Delete all user answers untuk token ini
        $deleteAnswersStmt = $db->prepare("DELETE FROM user_answers WHERE token_id = ?");
        $deleteAnswersStmt->execute([$tokenId]);
        
        // Delete test results
        $deleteResultsStmt = $db->prepare("DELETE FROM test_results WHERE token_id = ?");
        $deleteResultsStmt->execute([$tokenId]);
        
        // Reset token
        $resetStmt = $db->prepare("
            UPDATE tokens 
            SET is_used = 0, user_name = NULL, user_email = NULL, test_started_at = NULL, test_completed_at = NULL
            WHERE id = ?
        ");
        $resetStmt->execute([$tokenId]);
        $message = "Token berhasil direset! Semua jawaban sudah dihapus.";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "Gagal reset token!";
        $message_type = 'error';
    }
}

// Pagination
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Get tokens with search & filter
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

// Count total for pagination
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
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <h3><i class="fas fa-shapes"></i> ABLE.ID</h3>
            </div>
            
            <div class="sidebar-section-title">Menu</div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <span><i class="fas fa-chart-line"></i></span> Dashboard
                </a>
                <a href="generate-token.php" class="nav-item">
                    <span><i class="fas fa-key"></i></span> Generate Token
                </a>
                <a href="manage-tokens.php" class="nav-item active">
                    <span><i class="fas fa-list-alt"></i></span> Kelola Token
                </a>
            </nav>
            
            <div class="sidebar-section-title">Data</div>
            <nav class="sidebar-nav">
                <a href="manage-questions.php" class="nav-item">
                    <span><i class="fas fa-question-circle"></i></span> Kelola Soal
                </a>
                <a href="view-results.php" class="nav-item">
                    <span><i class="fas fa-poll"></i></span> Lihat Hasil
                </a>
            </nav>
            
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="database-maintenance.php" class="nav-item">
                    <span><i class="fas fa-tools"></i></span> Database Maint.
                </a>
                <a href="../index.php" class="nav-item">
                    <span><i class="fas fa-home"></i></span> Ke Website
                </a>
                <a href="../logout.php" class="nav-item nav-logout">
                    <span><i class="fas fa-sign-out-alt"></i></span> Logout
                </a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left"><i class="fas fa-tasks"></i> Manage Token</div>
                <div class="admin-topbar-right">
                    <a href="generate-token.php" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px;">
                        <i class="fas fa-plus"></i> Generate
                    </a>
                </div>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="margin-bottom: 24px; border: 2px solid #667eea;">
                    <div style="padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 8px 8px 0 0;">
                        <h3 style="margin: 0; font-size: 16px;"><i class="fas fa-magic"></i> Generate Token Baru</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST" action="" style="display: flex; gap: 12px; align-items: flex-end;">
                            <input type="hidden" name="action" value="generate">
                            
                            <div class="form-group" style="margin: 0; flex: 1;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;">Jumlah Token (1-100)</label>
                                <input type="number" name="quantity" min="1" max="100" value="10" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                                <i class="fas fa-check"></i> Generate
                            </button>
                        </form>
                        <p style="margin: 12px 0 0 0; font-size: 13px; color: #666;">
                            <i class="fas fa-lightbulb"></i> Token akan aktif selama 30 hari sejak dibuat
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div style="padding: 24px;">
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

                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-list-ul"></i> Daftar Token (Halaman <?= $page ?>/<?= $totalPages ?>)</h2>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;"><i class="fas fa-ticket-alt"></i> Token</th>
                                    <th style="width: 12%;"><i class="fas fa-info-circle"></i> Status</th>
                                    <th style="width: 20%;"><i class="fas fa-user"></i> User</th>
                                    <th style="width: 18%;"><i class="fas fa-calendar-plus"></i> Dibuat</th>
                                    <th style="width: 18%;"><i class="fas fa-calendar-times"></i> Expired</th>
                                    <th style="width: 17%;"><i class="fas fa-cogs"></i> Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tokens) > 0): ?>
                                    <?php foreach ($tokens as $token): ?>
                                    <tr>
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
                                        <td><?= date('d/m/Y H:i', strtotime($token['created_at'])) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($token['expires_at'])) ?></td>
                                        <td>
                                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                                <?php if (!$token['is_used'] && $token['status'] !== 'Expired'): ?>
                                                    <a href="?action=delete&token_id=<?= $token['id'] ?>" class="btn-action btn-action-danger" onclick="return confirm('Hapus token ini?');" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
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
                                        <td colspan="6" style="text-align: center; padding: 40px 20px; color: #999;">
                                            <div style="font-size: 32px; margin-bottom: 8px;"><i class="fas fa-inbox"></i></div>
                                            Tidak ada token yang sesuai
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter !== 'all' ? '&filter=' . $filter : '' ?>" class="btn btn-secondary" style="font-size: 12px; padding: 8px 12px;">« First</a>
                        <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter !== 'all' ? '&filter=' . $filter : '' ?>" class="btn btn-secondary" style="font-size: 12px; padding: 8px 12px;">‹ Prev</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <button class="btn btn-primary" style="font-size: 12px; padding: 8px 12px; cursor: default;">Page <?= $i ?></button>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter !== 'all' ? '&filter=' . $filter : '' ?>" class="btn btn-secondary" style="font-size: 12px; padding: 8px 12px;"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter !== 'all' ? '&filter=' . $filter : '' ?>" class="btn btn-secondary" style="font-size: 12px; padding: 8px 12px;">Next ›</a>
                        <a href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $filter !== 'all' ? '&filter=' . $filter : '' ?>" class="btn btn-secondary" style="font-size: 12px; padding: 8px 12px;">Last »</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-action-danger {
            background: #fee;
            color: #c00;
        }

        .btn-action-danger:hover {
            background: #fcc;
            color: #900;
        }

        .btn-action-warning {
            background: #ffeaa7;
            color: #d63031;
        }

        .btn-action-warning:hover {
            background: #ffde57;
            color: #b71c1c;
        }
    </style>
</body>
</html>
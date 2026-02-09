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
$limit = 50;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filter logs
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$where = "1=1";
$params = [];

if ($type !== 'all') {
    $where .= " AND action_type = ?";
    $params[] = $type;
}

// Get total logs
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM admin_logs WHERE $where");
$countStmt->execute($params);
$totalLogs = $countStmt->fetch()['total'];
$totalPages = ceil($totalLogs / $limit);

// Get logs
$logsStmt = $db->prepare("
    SELECT * FROM admin_logs 
    WHERE $where 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

// Get distinct action types for filter
$typesStmt = $db->query("SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type");
$actionTypes = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin</title>
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
                <a href="index.php" class="nav-item"><span><i class="fas fa-chart-line"></i></span> Dashboard</a>
                <a href="generate-token.php" class="nav-item"><span><i class="fas fa-key"></i></span> Generate Token</a>
                <a href="manage-tokens.php" class="nav-item"><span><i class="fas fa-list-alt"></i></span> Kelola Token</a>
            </nav>
            <div class="sidebar-section-title">Data</div>
            <nav class="sidebar-nav">
                <a href="manage-questions.php" class="nav-item"><span><i class="fas fa-question-circle"></i></span> Kelola Soal</a>
                <a href="view-results.php" class="nav-item"><span><i class="fas fa-poll"></i></span> Lihat Hasil</a>
            </nav>
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="logs.php" class="nav-item active"><span><i class="fas fa-history"></i></span> System Logs</a>
                <a href="database-maintenance.php" class="nav-item"><span><i class="fas fa-tools"></i></span> Database Maint.</a>
                <a href="../index.php" class="nav-item"><span><i class="fas fa-home"></i></span> Ke Website</a>
                <a href="admin-logout.php" class="nav-item nav-logout"><span><i class="fas fa-sign-out-alt"></i></span> Logout</a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left"><i class="fas fa-history"></i> System Activity Logs</div>
                <div class="admin-topbar-right">
                    <a href="logs.php" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px;">
                        <i class="fas fa-sync"></i> Refresh
                    </a>
                </div>
            </div>

            <div class="admin-content">
                <div class="card">
                    <div style="padding: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <h3 style="margin: 0; font-size: 16px;"><i class="fas fa-list-ul"></i> Riwayat Aktivitas</h3>
                        
                        <form method="GET" style="display: flex; gap: 10px;">
                            <select name="type" onchange="this.form.submit()" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                                <option value="all">Semua Aktivitas</option>
                                <?php foreach ($actionTypes as $act): ?>
                                    <option value="<?= htmlspecialchars($act) ?>" <?= $type === $act ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($act) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 20%;"><i class="fas fa-clock"></i> Waktu</th>
                                    <th style="width: 15%;"><i class="fas fa-user"></i> Admin</th>
                                    <th style="width: 15%;"><i class="fas fa-tag"></i> Tipe</th>
                                    <th style="width: 35%;"><i class="fas fa-info-circle"></i> Detail</th>
                                    <th style="width: 15%;"><i class="fas fa-laptop"></i> IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="color: #666; font-size: 13px;">
                                            <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['admin_username'] ?? 'System') ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-info" style="font-size: 11px;">
                                                <?= htmlspecialchars($log['action_type']) ?>
                                            </span>
                                        </td>
                                        <td style="color: #444;">
                                            <?= htmlspecialchars($log['details']) ?>
                                        </td>
                                        <td style="font-family: monospace; color: #666;">
                                            <?= htmlspecialchars($log['ip_address']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                                            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px;"></i><br>
                                            Tidak ada log aktivitas
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&type=<?= $type ?>" class="btn btn-secondary">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    <?php endif; ?>
                    
                    <span class="btn btn-secondary" style="background: #e9ecef; cursor: default;">
                        Halaman <?= $page ?> / <?= $totalPages ?>
                    </span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&type=<?= $type ?>" class="btn btn-secondary">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
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

// Get statistics
$statsStmt = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM tokens) as total_tokens,
        (SELECT COUNT(*) FROM tokens WHERE is_used = 1) as used_tokens,
        (SELECT COUNT(*) FROM tokens WHERE test_completed_at IS NOT NULL) as completed_tests,
        (SELECT COUNT(*) FROM tokens WHERE expires_at < NOW()) as expired_tokens,
        (SELECT COUNT(*) FROM questions) as total_questions,
        (SELECT COUNT(*) FROM test_results) as total_results
");
$stats = $statsStmt->fetch();

// Get recent results
$recentStmt = $db->query("
    SELECT 
        tr.total_score,
        tr.max_score,
        tr.percentage,
        tr.completed_at,
        t.user_name,
        t.user_email,
        up.gender,
        up.city
    FROM test_results tr
    JOIN tokens t ON tr.token_id = t.id
    LEFT JOIN user_profiles up ON t.id = up.token_id
    ORDER BY tr.completed_at DESC
    LIMIT 10
");
$recentResults = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ABLE.ID</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
                <a href="index.php" class="nav-item active">
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
                <div class="admin-topbar-left">📊 Dashboard Admin</div>
                <div class="admin-topbar-right">
                    <a href="manage-tokens.php" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px;">
                        ➕ Kelola Token
                    </a>
                </div>
            </div>

            <!-- Content -->
            <div class="admin-content">
                <!-- Statistics -->
                <div class="section-header">
                    <h2 class="section-title">📊 Statistik Umum</h2>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🎫</div>
                        <div class="stat-value"><?= $stats['total_tokens'] ?></div>
                        <div class="stat-label">Total Token</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-value"><?= $stats['used_tokens'] ?></div>
                        <div class="stat-label">Token Digunakan</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🏁</div>
                        <div class="stat-value"><?= $stats['completed_tests'] ?></div>
                        <div class="stat-label">Test Selesai</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-value"><?= $stats['expired_tokens'] ?></div>
                        <div class="stat-label">Token Expired</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">❓</div>
                        <div class="stat-value"><?= $stats['total_questions'] ?></div>
                        <div class="stat-label">Total Soal</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">📈</div>
                        <div class="stat-value"><?= $stats['total_results'] ?></div>
                        <div class="stat-label">Hasil Test</div>
                    </div>
                </div>

                <!-- Recent Results -->
                <div class="section-header">
                    <h2 class="section-title">📈 10 Hasil Test Terbaru</h2>
                    <a href="view-results.php" class="btn btn-secondary" style="font-size: 12px; padding: 8px 16px;">
                        Lihat Semua →
                    </a>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>👤 Nama</th>
                                    <th>✉️ Email</th>
                                    <th>🏙️ Kota</th>
                                    <th>📊 Skor</th>
                                    <th>📈 %</th>
                                    <th>📅 Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentResults) > 0): ?>
                                    <?php foreach ($recentResults as $result): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($result['user_name'] ?? 'Unknown') ?></strong></td>
                                        <td><?= htmlspecialchars($result['user_email'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($result['city'] ?? '-') ?></td>
                                        <td>
                                            <span style="
                                                color: <?= 
                                                    $result['percentage'] >= 75 ? '#28a745' : 
                                                    ($result['percentage'] >= 50 ? '#ffc107' : '#e74c3c')
                                                ?>;
                                                font-weight: 600;
                                            ">
                                                <?= number_format($result['total_score'], 0) ?>/<?= number_format($result['max_score'], 0) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($result['percentage'], 1) ?>%</td>
                                        <td><?= date('d/m/Y H:i', strtotime($result['completed_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px 20px; color: #999;">
                                            <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                                            Belum ada hasil test
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="section-header">
                    <h2 class="section-title">⚡ Menu Cepat</h2>
                </div>

                <div class="quick-links-grid">
                    <a href="manage-tokens.php" class="quick-link">
                        <div class="quick-link-icon">🎫</div>
                        <div class="quick-link-content">
                            <div class="quick-link-title">Kelola Token</div>
                            <div class="quick-link-desc">Buat & kelola token peserta</div>
                        </div>
                    </a>
                    <a href="view-results.php" class="quick-link">
                        <div class="quick-link-icon">📊</div>
                        <div class="quick-link-content">
                            <div class="quick-link-title">Lihat Hasil</div>
                            <div class="quick-link-desc">Lihat hasil test semua user</div>
                        </div>
                    </a>
                    <a href="manage-questions.php" class="quick-link">
                        <div class="quick-link-icon">❓</div>
                        <div class="quick-link-content">
                            <div class="quick-link-title">Kelola Soal</div>
                            <div class="quick-link-desc">Edit soal HSCL-25</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

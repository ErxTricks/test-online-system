<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

$db = getDBConnection();

// Get all test results
$stmt = $db->query("
    SELECT
        tr.*,
        t.user_name,
        t.user_email,
        up.city,
        CONCAT(up.first_name, ' ', COALESCE(up.last_name, '')) as full_name
    FROM test_results tr
    JOIN tokens t ON tr.token_id = t.id
    LEFT JOIN user_profiles up ON tr.token_id = up.token_id
    ORDER BY tr.completed_at DESC
");
$results = $stmt->fetchAll();

// Calculate statistics
$totalTests = count($results);
$avgScore = $totalTests > 0 ? array_sum(array_column($results, 'percentage')) / $totalTests : 0;
$concernCount = 0;
foreach ($results as $r) {
    if ($r['percentage'] >= 70) { // Assuming 70% as concern threshold
        $concernCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Hasil - Admin Panel</title>
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
                <a href="view-results.php" class="nav-item active">
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
                <h2>📈 Lihat Hasil Test</h2>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="export-excel.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
                        📊 Export Excel
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        ← Kembali ke Dashboard
                    </a>
                </div>
            </div>

            <!-- Content -->
            <div class="admin-content">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #667eea;">📋</div>
                        <div class="stat-info">
                            <div class="stat-label">Total Test Selesai</div>
                            <div class="stat-value"><?= $totalTests ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #28a745;">📊</div>
                        <div class="stat-info">
                            <div class="stat-label">Rata-rata Skor</div>
                            <div class="stat-value"><?= number_format($avgScore, 2) ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f5576c;">⚠️</div>
                        <div class="stat-info">
                            <div class="stat-label">Kemungkinan Masalah Mental</div>
                            <div class="stat-value"><?= $concernCount ?></div>
                        </div>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0; font-size: 16px;">📋 Daftar Hasil Test (<?= $totalTests ?> total)</h3>
                    </div>
                    <div class="table-responsive">
                        <?php if (count($results) > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Kota</th>
                                        <th style="text-align: center;">Skor</th>
                                        <th style="text-align: center;">Persentase</th>
                                        <th style="text-align: center;">Status</th>
                                        <th>Tanggal</th>
                                        <th style="text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result):
                                        $isConcern = $result['percentage'] >= 70;
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($result['full_name'] ?: $result['user_name']) ?></strong></td>
                                        <td style="font-size: 12px; color: #666;"><?= htmlspecialchars($result['user_email']) ?></td>
                                        <td style="color: #666;"><?= htmlspecialchars($result['city'] ?: '-') ?></td>
                                        <td style="text-align: center; font-weight: 600; color: #667eea;">
                                            <?= number_format($result['total_score'], 0) ?>/<?= $result['max_score'] ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                <div style="width: 50px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                                    <div style="height: 100%; background: <?= $isConcern ? '#f5576c' : '#28a745' ?>; width: <?= $result['percentage'] ?>%;"></div>
                                                </div>
                                                <span style="font-weight: 600;"><?= number_format($result['percentage'], 0) ?>%</span>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($isConcern): ?>
                                                <span class="badge badge-danger">⚠️ Perhatian</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">✅ Baik</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #666; font-size: 12px;">
                                            <?= date('d/m/Y H:i', strtotime($result['completed_at'])) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                                <a href="../result.php?token_id=<?= $result['token_id'] ?>" class="btn-action" target="_blank" onclick="window.open(this.href, 'Lihat Hasil', 'width=1200,height=800,top=100,left=100'); return false;" style="padding: 6px 10px; font-size: 12px;">
                                                    👁️ Lihat
                                                </a>
                                                <a href="export-excel-user.php?token_id=<?= $result['token_id'] ?>" class="btn-action" style="padding: 6px 10px; font-size: 12px; background: #28a745;">
                                                    📊 Excel
                                                </a>
                                                <a href="export-pdf.php?token_id=<?= $result['token_id'] ?>" class="btn-action" style="padding: 6px 10px; font-size: 12px; background: #e74c3c;">
                                                    📄 PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; padding: 60px 20px; color: #999;">
                                <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                                <p style="font-size: 16px; margin: 0;">Belum ada hasil test</p>
                                <p style="font-size: 13px; margin: 8px 0 0 0;">Tunggu hingga peserta menyelesaikan test</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
    </style>
</body>
</html>
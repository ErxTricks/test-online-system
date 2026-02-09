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

// Get all test results
$stmt = $db->query("
    SELECT
        tr.*,
        t.user_name,
        t.user_email,
        t.token_code,
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
    if ($r['percentage'] >= 70) { // Anggap >70% sebagai perlu perhatian (bisa disesuaikan)
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Custom Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon-large {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        .stat-info h4 { margin: 0 0 5px 0; color: #8898aa; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .stat-info .value { font-size: 28px; font-weight: 700; color: #32325d; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
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
                <a href="manage-tokens.php" class="nav-item"><span><i class="fas fa-list-alt"></i></span> Kelola Token</a>
            </nav>
            <div class="sidebar-section-title">Data</div>
            <nav class="sidebar-nav">
                <a href="manage-questions.php" class="nav-item"><span><i class="fas fa-question-circle"></i></span> Kelola Soal</a>
                <a href="view-results.php" class="nav-item active"><span><i class="fas fa-poll"></i></span> Lihat Hasil</a>
            </nav>
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="database-maintenance.php" class="nav-item"><span><i class="fas fa-tools"></i></span> Database Maint.</a>
                <a href="../index.php" class="nav-item"><span><i class="fas fa-home"></i></span> Ke Website</a>
                <a href="admin-logout.php" class="nav-item nav-logout"><span><i class="fas fa-sign-out-alt"></i></span> Logout</a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left"><i class="fas fa-poll"></i> Analisa Hasil Test</div>
                <div class="admin-topbar-right">
                    <a href="export-excel.php" class="btn btn-success" style="font-size: 13px;">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                </div>
            </div>

            <div class="admin-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon-large" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Total Partisipan</h4>
                            <div class="value"><?= $totalTests ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon-large" style="background: linear-gradient(135deg, #2dce89 0%, #2dcecc 100%);">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Rata-rata Skor</h4>
                            <div class="value"><?= number_format($avgScore, 1) ?>%</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon-large" style="background: linear-gradient(135deg, #f5365c 0%, #f56036 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h4>Indikasi Masalah</h4>
                            <div class="value"><?= $concernCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 16px;"><i class="fas fa-list"></i> Data Detail Peserta</h3>
                    </div>
                    <div class="table-responsive">
                        <?php if (count($results) > 0): ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Nama Peserta</th>
                                        <th>Token</th>
                                        <th>Lokasi</th>
                                        <th style="text-align: center;">Skor Akhir</th>
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
                                        <td>
                                            <div style="font-weight: 600; color: #333;"><?= htmlspecialchars($result['full_name'] ?: $result['user_name']) ?></div>
                                            <div style="font-size: 12px; color: #888;"><?= htmlspecialchars($result['user_email']) ?></div>
                                        </td>
                                        <td>
                                            <span style="font-family: monospace; background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 12px;">
                                                <?= htmlspecialchars($result['token_code']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($result['city'] ?: '-') ?></td>
                                        <td style="text-align: center;">
                                            <span style="font-weight: 700; color: <?= $isConcern ? '#e74c3c' : '#27ae60' ?>;">
                                                <?= number_format($result['percentage'], 1) ?>%
                                            </span>
                                            <div style="font-size: 11px; color: #999;">(<?= number_format($result['total_score'], 0) ?>/<?= $result['max_score'] ?>)</div>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($isConcern): ?>
                                                <span class="badge badge-danger"><i class="fas fa-exclamation-circle"></i> Perlu Perhatian</span>
                                            <?php else: ?>
                                                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 13px; color: #666;">
                                            <?= date('d/m/Y H:i', strtotime($result['completed_at'])) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <a href="../result.php?token_id=<?= $result['token_id'] ?>" target="_blank" class="btn-action" style="background: #667eea; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none;" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="export-pdf.php?token_id=<?= $result['token_id'] ?>" target="_blank" class="btn-action" style="background: #e74c3c; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none;" title="Cetak PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="padding: 40px; text-align: center; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px;"></i>
                                <p>Belum ada data hasil test.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
session_start();
require_once '../includes/functions.php';
require_once '../config/database.php';

// Cek Admin Login
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin-login.php");
    exit;
}

$message = '';
$generatedTokens = [];
$db = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = intval($_POST['quantity'] ?? 1);
    $quantity = max(1, min(100, $quantity)); // Batasi 1-100 token sekali generate
    
    // Ambil jenis tes yang dipilih
    $selected_tests = isset($_POST['selected_tests']) ? $_POST['selected_tests'] : ['HSCL-25'];
    $test_types_string = implode(',', $selected_tests);
    
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days')); // Expire 7 hari

    // 1. GENERATE PREFIX UNIK (Satu Prefix untuk Satu Batch)
    // Kita cari prefix yang belum pernah dipakai agar urutannya selalu mulai dari 0001
    $prefix = '';
    $isUnique = false;
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    // Coba generate prefix sampai nemu yang belum ada di database
    while (!$isUnique) {
        $prefix = '';
        for ($j = 0; $j < 4; $j++) {
            $prefix .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Cek di database apakah prefix ini sudah ada
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM tokens WHERE token_code LIKE ?");
        $stmtCheck->execute([$prefix . '%']);
        if ($stmtCheck->fetchColumn() == 0) {
            $isUnique = true; // Prefix aman, belum pernah dipakai
        }
    }

    // 2. LOOPING MEMBUAT TOKEN (0001 s/d Quantity)
    for ($i = 1; $i <= $quantity; $i++) {
        
        // Format: PREFIX + 0001, 0002, dst
        $tokenCode = $prefix . str_pad($i, 4, '0', STR_PAD_LEFT);

        try {
            $stmt = $db->prepare("INSERT INTO tokens (token_code, expires_at, selected_test_types, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$tokenCode, $expiresAt, $test_types_string]);
            $generatedTokens[] = $tokenCode;
            
            // Log ke admin_logs (jika tabel ada)
            try {
                $logStmt = $db->prepare("INSERT INTO admin_logs (admin_username, action_type, details, ip_address) VALUES (?, 'GENERATE_TOKEN', ?, ?)");
                $logDetails = "Generated token: $tokenCode ($test_types_string)";
                $ip = $_SERVER['REMOTE_ADDR'];
                $username = $_SESSION['admin_username'] ?? 'Admin';
                $logStmt->execute([$username, $logDetails, $ip]);
            } catch (Exception $e) {
                // Ignore log error
            }

        } catch (PDOException $e) {
            // Jika error, abaikan saja (sangat jarang terjadi karena prefix sudah dicek unik)
        }
    }

    $message = count($generatedTokens) . " token berhasil digenerate dengan prefix: <strong>$prefix</strong>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Token - Admin ABLE.ID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .token-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        .token-box {
            background: #eef2ff;
            color: #4f46e5;
            padding: 10px;
            text-align: center;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            border: 1px solid #c7d2fe;
            font-size: 14px;
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
                <a href="generate-token.php" class="nav-item active"><span><i class="fas fa-key"></i></span> Generate Token</a>
                <a href="manage-tokens.php" class="nav-item"><span><i class="fas fa-list-alt"></i></span> Kelola Token</a>
            </nav>
            
            <div class="sidebar-section-title">Data</div>
            <nav class="sidebar-nav">
                <a href="manage-questions.php" class="nav-item"><span><i class="fas fa-question-circle"></i></span> Kelola Soal</a>
                <a href="view-results.php" class="nav-item"><span><i class="fas fa-poll"></i></span> Lihat Hasil</a>
            </nav>
            
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="logs.php" class="nav-item"><span><i class="fas fa-history"></i></span> System Logs</a>
                <a href="database-maintenance.php" class="nav-item"><span><i class="fas fa-tools"></i></span> Database Maint.</a>
                <a href="../index.php" class="nav-item"><span><i class="fas fa-home"></i></span> Ke Website</a>
                <a href="admin-logout.php" class="nav-item nav-logout"><span><i class="fas fa-sign-out-alt"></i></span> Logout</a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left"><i class="fas fa-key"></i> Generate Token Baru</div>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $message ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div style="padding: 24px; border-bottom: 1px solid #e0e0e0;">
                        <h3 style="margin: 0; font-size: 16px;"><i class="fas fa-plus-circle"></i> Buat Token Test</h3>
                        <p style="margin: 5px 0 0; color: #666; font-size: 13px;">Satu batch generate akan memiliki 4 huruf depan yang sama.</p>
                    </div>
                    
                    <div style="padding: 24px;">
                        <form method="POST" action="">
                            
                            <div class="form-group">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Pilih Jenis Tes:</label>
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="selected_tests[]" value="HSCL-25" checked> 
                                        <span>HSCL-25 (Kesehatan Mental)</span>
                                    </label>
                                    <label>
                                        <input type="checkbox" name="selected_tests[]" value="VAK"> 
                                        <span>VAK (Gaya Belajar)</span>
                                    </label>
                                    <label>
                                        <input type="checkbox" name="selected_tests[]" value="DISC"> 
                                        <span>DISC (Kepribadian)</span>
                                    </label>
                                </div>
                                <small style="color: #666;">* Pilih minimal satu jenis tes untuk token ini.</small>
                            </div>

                            <div class="form-group">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Jumlah Token:</label>
                                <div style="display: flex; gap: 10px; max-width: 400px;">
                                    <input type="number" name="quantity" value="10" min="1" max="100" class="form-control" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                                        <i class="fas fa-magic"></i> Generate
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>

                <?php if (!empty($generatedTokens)): ?>
                <div class="card" style="margin-top: 24px;">
                    <div style="padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee;">
                        <h4 style="margin: 0; color: #333;"><i class="fas fa-list"></i> Hasil Token (<?= count($generatedTokens) ?>)</h4>
                    </div>
                    <div style="padding: 20px;">
                        <div class="token-grid">
                            <?php foreach($generatedTokens as $t): ?>
                                <div class="token-box"><?= $t ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 20px; text-align: right;">
                            <a href="manage-tokens.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-right"></i> Lihat Semua Token
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
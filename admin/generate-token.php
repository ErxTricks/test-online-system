<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
require_once '../includes/logger.php';

$message = '';
$generatedTokens = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = intval($_POST['quantity'] ?? 1);
    $quantity = max(1, min(100, $quantity));
    $selected_tests = $_POST['selected_tests'] ?? ['HSCL-25'];
    $test_types = implode(',', $selected_tests);
    
    $db = getDBConnection();
    $logger = getLogger($db);
    
    $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(token_code, 6, 3) AS UNSIGNED)) as last_seq FROM tokens");
    $result = $stmt->fetch();
    $lastSequence = $result['last_seq'] ?? 0;

    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_DAYS . ' days'));

    for ($i = 0; $i < $quantity; $i++) {
        $sequence = $lastSequence + $i + 1;
        $token = generateToken($sequence);

        try {
            $stmt = $db->prepare("INSERT INTO tokens (token_code, expires_at, selected_test_types) VALUES (?, ?, ?)");
            $stmt->execute([$token, $expiresAt, $test_types]);
            $generatedTokens[] = $token;
            
            // Log token generation
            $logger->logTokenGeneration($token, $test_types, $expiresAt);
            
            // Log admin action
            $adminUsername = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Unknown';
            $logger->logAdminAction($adminUsername, 'GENERATE_TOKEN', "Tests: $test_types, Expires: $expiresAt");
        } catch (PDOException $e) {
            $i--;
            $logger->logError('Token generation failed', $e->getMessage());
        }
    }

    $message = count($generatedTokens) . " token berhasil digenerate untuk test: " . implode(', ', $selected_tests) . "!";
}

$db = getDBConnection();
$stmt = $db->query("
    SELECT
        token_code,
        is_used,
        user_name,
        created_at,
        expires_at,
        test_completed_at,
        CASE
            WHEN expires_at < NOW() THEN 'Expired'
            WHEN test_completed_at IS NOT NULL THEN 'Selesai'
            WHEN is_used = TRUE THEN 'Digunakan'
            ELSE 'Belum Digunakan'
        END as status
    FROM tokens
    ORDER BY created_at DESC
    LIMIT 100
");
$tokens = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Token - Admin</title>
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
                <a href="index.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="generate-token.php" class="nav-item active">
                    <span>🔑</span> Generate Token
                </a>
                <a href="manage-tokens.php" class="nav-item">
                    <span>🔑</span> Kelola Token
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
                <div class="admin-topbar-left">🎫 Generate Token</div>
                <div class="admin-topbar-right">
                    <a href="generate-token.php" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px;">
                        ↻ Refresh
                    </a>
                </div>
            </div>

            <!-- Content -->
            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <strong>✅ Sukses!</strong> <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- Generate Form (Prioritas Pertama) -->
                <div class="card">
                    <div style="padding: 24px; border-bottom: 1px solid #e0e0e0;">
                        <h3 style="margin: 0; font-size: 16px;">🎫 Generate Token Baru</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Pilih Jenis Soal</label>
                                <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="selected_tests[]" value="HSCL-25" checked>
                                        <span>HSCL-25 (Mental Health Screening)</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="selected_tests[]" value="VAK">
                                        <span>VAK (Learning Style Test)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Jumlah Token</label>
                                <div style="display: grid; grid-template-columns: 1fr auto; gap: 12px;">
                                    <input type="number" name="quantity" value="10" min="1" max="100" required>
                                    <button type="submit" class="btn btn-success">Generate</button>
                                </div>
                                <small>Maksimal 100 token per kali generate. Token aktif <?= TOKEN_EXPIRY_DAYS ?> hari.</small>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Generated Tokens Display -->
                <?php if (count($generatedTokens) > 0): ?>
                    <div class="card">
                        <div style="padding: 24px; border-bottom: 1px solid #e0e0e0;">
                            <h3 style="margin: 0 0 16px 0; font-size: 16px;">✨ Token yang Baru Digenerate</h3>
                        </div>
                        <div style="padding: 24px;">
                            <div class="token-grid">
                                <?php foreach ($generatedTokens as $token): ?>
                                    <div class="token-box">
                                        <div class="token-code"><?= $token ?></div>
                                        <button class="btn btn-small" onclick="copyToClipboard('<?= $token ?>')">
                                            📋 Copy
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button onclick="copyAllTokens()" class="btn btn-primary" style="margin-top: 16px; width: 100%;">
                                📋 Copy Semua Token
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Token List -->
                <div class="section-header">
                    <h2 class="section-title">📋 Daftar 100 Token Terbaru</h2>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>🎫 Token</th>
                                    <th>📊 Status</th>
                                    <th>👤 User</th>
                                    <th>📅 Dibuat</th>
                                    <th>⏰ Expired</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                    <td><?= htmlspecialchars($token['user_name'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($token['created_at'])) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($token['expires_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .token-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .token-box {
            background: linear-gradient(135deg, #f5f7ff 0%, #ede9ff 100%);
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }

        .token-code {
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 700;
            font-size: 13px;
            color: #667eea;
            margin-bottom: 8px;
            word-break: break-all;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            width: 100%;
        }
    </style>

    <script>
        function copyToClipboard(token) {
            navigator.clipboard.writeText(token).then(() => {
                alert('Token copied: ' + token);
            }).catch(err => console.error('Error copying:', err));
        }

        function copyAllTokens() {
            const tokens = <?= json_encode($generatedTokens) ?>;
            const text = tokens.join('\n');
            navigator.clipboard.writeText(text).then(() => {
                alert('Semua ' + tokens.length + ' token berhasil dicopy!');
            }).catch(err => console.error('Error copying:', err));
        }
    </script>
</body>
</html>

<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
// Pastikan file logger ada, jika tidak ada, buat dummy logger atau hapus baris ini
if (file_exists('../includes/logger.php')) {
    require_once '../includes/logger.php';
}

$message = '';
$generatedTokens = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = intval($_POST['quantity'] ?? 1);
    $quantity = max(1, min(100, $quantity));
    
    $selected_tests = $_POST['selected_tests'] ?? ['HSCL-25'];
    $test_types = implode(',', $selected_tests);
    
    $db = getDBConnection();
    
    // Setup logger if exists
    $logger = null;
    if (class_exists('Logger')) {
        $logger = new Logger($db);
    }
    
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
            
            // Log if logger available
            if ($logger) {
                $logger->logTokenGeneration($token, $test_types, $expiresAt);
                $adminUsername = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Unknown';
                $logger->logAdminAction($adminUsername, 'GENERATE_TOKEN', "Tests: $test_types");
            }
        } catch (PDOException $e) {
            $i--; // Retry sequence
        }
    }

    $message = count($generatedTokens) . " token berhasil digenerate!";
}

// Get tokens list
$db = getDBConnection();
$stmt = $db->query("SELECT *, CASE WHEN expires_at < NOW() THEN 'Expired' WHEN test_completed_at IS NOT NULL THEN 'Selesai' WHEN is_used = TRUE THEN 'Digunakan' ELSE 'Belum Digunakan' END as status FROM tokens ORDER BY created_at DESC LIMIT 50");
$tokens = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Generate Token - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="sidebar-brand"><h3><i class="fas fa-shapes"></i> ABLE.ID</h3></div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item"><span><i class="fas fa-chart-line"></i></span> Dashboard</a>
                <a href="generate-token.php" class="nav-item active"><span><i class="fas fa-key"></i></span> Generate Token</a>
                <a href="manage-tokens.php" class="nav-item"><span><i class="fas fa-list-alt"></i></span> Kelola Token</a>
                <a href="manage-questions.php" class="nav-item"><span><i class="fas fa-question-circle"></i></span> Kelola Soal</a>
                <a href="view-results.php" class="nav-item"><span><i class="fas fa-poll"></i></span> Lihat Hasil</a>
                <a href="../index.php" class="nav-item"><span><i class="fas fa-home"></i></span> Ke Website</a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar"><div class="admin-topbar-left">Generate Token</div></div>
            <div class="admin-content">
                <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>

                <div class="card" style="padding: 20px;">
                    <form method="POST">
                        <div class="form-group">
                            <label>Pilih Jenis Soal</label>
                            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <label><input type="checkbox" name="selected_tests[]" value="HSCL-25" checked> HSCL-25</label>
                                <label><input type="checkbox" name="selected_tests[]" value="VAK"> VAK</label>
                                <label><input type="checkbox" name="selected_tests[]" value="DISC"> DISC</label>
                            </div>
                        </div>
                        <div class="form-group" style="display: flex; gap: 10px;">
                            <input type="number" name="quantity" value="10" min="1" max="100" style="padding: 8px;">
                            <button type="submit" class="btn btn-primary">Generate</button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($generatedTokens)): ?>
                <div class="card" style="margin-top: 20px; padding: 20px;">
                    <h4>Token Baru:</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px;">
                        <?php foreach($generatedTokens as $t): ?>
                            <div style="background: #eee; padding: 5px; text-align: center; border-radius: 4px; font-family: monospace;"><?= $t ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
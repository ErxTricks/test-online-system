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
$messageType = '';

// Handle Archive Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'archive') {
            $archiveDate = date('Y-m-d', strtotime('-6 months'));
            
            // Create archive tables if not exists
            $db->exec("
                CREATE TABLE IF NOT EXISTS user_answers_archive (
                    id INT NOT NULL,
                    token_id INT NOT NULL,
                    question_id INT NOT NULL,
                    selected_option CHAR(1) NOT NULL,
                    points_earned INT NOT NULL,
                    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_archived_at (archived_at)
                )
            ");
            
            // Archive user_answers
            $db->exec("
                INSERT INTO user_answers_archive (id, token_id, question_id, selected_option, points_earned, answered_at)
                SELECT id, token_id, question_id, selected_option, points_earned, answered_at 
                FROM user_answers WHERE answered_at < '$archiveDate'
                ON DUPLICATE KEY UPDATE archived_at = NOW()
            ");
            $deletedAnswers = $db->exec("DELETE FROM user_answers WHERE answered_at < '$archiveDate'");
            
            // Archive test_results
            $db->exec("
                CREATE TABLE IF NOT EXISTS test_results_archive (
                    id INT NOT NULL,
                    token_id INT NOT NULL,
                    total_score INT NOT NULL,
                    max_score INT NOT NULL,
                    percentage DECIMAL(5,2) NOT NULL,
                    total_questions INT NOT NULL,
                    answered_questions INT NOT NULL,
                    completed_at TIMESTAMP,
                    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_archived_at (archived_at)
                )
            ");
            
            $db->exec("
                INSERT INTO test_results_archive (id, token_id, total_score, max_score, percentage, total_questions, answered_questions, completed_at)
                SELECT id, token_id, total_score, max_score, percentage, total_questions, answered_questions, completed_at 
                FROM test_results WHERE completed_at < '$archiveDate'
                ON DUPLICATE KEY UPDATE archived_at = NOW()
            ");
            $deletedResults = $db->exec("DELETE FROM test_results WHERE completed_at < '$archiveDate'");
            
            // Optimize tables
            $db->exec("OPTIMIZE TABLE user_answers");
            $db->exec("OPTIMIZE TABLE test_results");
            
            $message = "✅ Archive berhasil! Deleted: $deletedAnswers answers, $deletedResults results";
            $messageType = 'success';
            
        } elseif ($_POST['action'] === 'backup') {
            $backupDir = __DIR__ . '/logs/backup/';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.txt";
            $filepath = $backupDir . $filename;
            
            $handle = fopen($filepath, 'w');
            fwrite($handle, "BACKUP CREATED: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, str_repeat("=", 50) . "\n\n");
            
            // Export archive data
            fwrite($handle, "USER ANSWERS ARCHIVE:\n");
            $answers = $db->query("SELECT * FROM user_answers_archive ORDER BY archived_at DESC LIMIT 500");
            foreach ($answers as $answer) {
                fwrite($handle, json_encode($answer) . "\n");
            }
            
            fclose($handle);
            $message = "✅ Backup created: $filename";
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get database statistics
try {
    $prodStats = [
        'answers' => $db->query("SELECT COUNT(*) as cnt FROM user_answers")->fetch()['cnt'],
        'results' => $db->query("SELECT COUNT(*) as cnt FROM test_results")->fetch()['cnt'],
        'profiles' => $db->query("SELECT COUNT(*) as cnt FROM user_profiles")->fetch()['cnt']
    ];
    
    $archStats = [
        'answers' => $db->query("SELECT COUNT(*) as cnt FROM user_answers_archive")->fetch()['cnt'] ?? 0,
        'results' => $db->query("SELECT COUNT(*) as cnt FROM test_results_archive")->fetch()['cnt'] ?? 0,
        'profiles' => $db->query("SELECT COUNT(*) as cnt FROM user_profiles_archive")->fetch()['cnt'] ?? 0
    ];
    
    $dbSize = $db->query("
        SELECT ROUND(SUM((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as total_mb
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = 'test_online_system'
    ")->fetch()['total_mb'];
    
} catch (Exception $e) {
    $prodStats = ['answers' => 0, 'results' => 0, 'profiles' => 0];
    $archStats = ['answers' => 0, 'results' => 0, 'profiles' => 0];
    $dbSize = 0;
}

// Get backup files list
$backupDir = __DIR__ . '/logs/backup/';
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && strpos($file, 'backup_') === 0) {
            $backupFiles[] = [
                'name' => $file,
                'size' => filesize($backupDir . $file) / 1024,
                'date' => filemtime($backupDir . $file)
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Maintenance - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .maintenance-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stat-box h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 14px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #999;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: <?= $message ? 'block' : 'none' ?>;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-archive {
            background: #667eea;
            color: white;
        }
        
        .btn-archive:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-backup {
            background: #27ae60;
            color: white;
        }
        
        .btn-backup:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            margin: 0 0 20px 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .file-list li {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-list li:last-child {
            border-bottom: none;
        }
        
        .file-name {
            font-family: monospace;
            color: #667eea;
        }
        
        .file-info {
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <span class="brand-icon">◆</span>
                <span class="brand-text">ABLE.ID</span>
            </div>
            <div class="navbar-title">Database Maintenance</div>
        </div>
    </div>
    
    <div class="maintenance-container">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="archive">
                <button type="submit" class="btn-action btn-archive" onclick="return confirm('Archive data older than 6 months? This cannot be undone!')">
                    🗂️ Archive Old Data
                </button>
            </form>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="btn-action btn-backup">
                    💾 Backup Archive to TXT
                </button>
            </form>
        </div>
        
        <!-- Production Data Stats -->
        <div class="section">
            <h2>📊 Production Data (Active)</h2>
            <div class="stats-container">
                <div class="stat-box">
                    <h3>User Answers</h3>
                    <div class="stat-value"><?= number_format($prodStats['answers']) ?></div>
                    <div class="stat-label">Active Records</div>
                </div>
                <div class="stat-box">
                    <h3>Test Results</h3>
                    <div class="stat-value"><?= number_format($prodStats['results']) ?></div>
                    <div class="stat-label">Active Records</div>
                </div>
                <div class="stat-box">
                    <h3>User Profiles</h3>
                    <div class="stat-value"><?= number_format($prodStats['profiles']) ?></div>
                    <div class="stat-label">Active Records</div>
                </div>
                <div class="stat-box">
                    <h3>Database Size</h3>
                    <div class="stat-value"><?= $dbSize ?></div>
                    <div class="stat-label">MB</div>
                </div>
            </div>
        </div>
        
        <!-- Archive Data Stats -->
        <div class="section">
            <h2>📦 Archive Data (Historical)</h2>
            <div class="stats-container">
                <div class="stat-box">
                    <h3>Archived Answers</h3>
                    <div class="stat-value"><?= number_format($archStats['answers']) ?></div>
                    <div class="stat-label">Records</div>
                </div>
                <div class="stat-box">
                    <h3>Archived Results</h3>
                    <div class="stat-value"><?= number_format($archStats['results']) ?></div>
                    <div class="stat-label">Records</div>
                </div>
                <div class="stat-box">
                    <h3>Archived Profiles</h3>
                    <div class="stat-value"><?= number_format($archStats['profiles']) ?></div>
                    <div class="stat-label">Records</div>
                </div>
            </div>
        </div>
        
        <!-- Backup Files -->
        <div class="section">
            <h2>📁 Backup Files</h2>
            <?php if (empty($backupFiles)): ?>
            <p style="color: #999;">No backup files yet. Create one to get started.</p>
            <?php else: ?>
            <ul class="file-list">
                <?php foreach ($backupFiles as $file): ?>
                <li>
                    <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                    <div class="file-info">
                        <?= number_format($file['size'], 2) ?> KB | 
                        <?= date('Y-m-d H:i:s', $file['date']) ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        
        <!-- Info Box -->
        <div class="section" style="background: #f0f4ff; border-left: 4px solid #667eea;">
            <h3>ℹ️ Database Maintenance Information</h3>
            <ul style="margin: 0; padding-left: 20px; color: #555; line-height: 1.8;">
                <li><strong>Archive:</strong> Move data older than 6 months to archive tables</li>
                <li><strong>Backup:</strong> Export archived data to TXT log files</li>
                <li><strong>Optimize:</strong> Tables are automatically optimized after archiving</li>
                <li><strong>Frequency:</strong> Run monthly for best performance</li>
                <li><strong>Storage:</strong> Archive files saved in <code>logs/backup/</code></li>
            </ul>
        </div>
    </div>
</body>
</html>

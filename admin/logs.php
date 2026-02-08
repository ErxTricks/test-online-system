<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_username'])) {
    header('Location: ../admin-login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/logger.php';

$db = getDBConnection();
$logger = getLogger($db);

$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

// Handle backup action
if ($action === 'backup' && isset($_GET['type'])) {
    $backupType = $_GET['type'];
    if ($backupType === 'database') {
        $logger->backupDatabase();
        $_SESSION['message'] = '✅ Database backup created successfully!';
        header('Location: logs.php?action=dashboard');
        exit;
    }
}

// Handle daily report
if ($action === 'report') {
    $logger->generateDailySummary();
    $_SESSION['message'] = '✅ Daily summary report generated!';
    header('Location: logs.php?action=dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs & Backup - ABLE.ID Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .logs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .log-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: #667eea;
            border-radius: 2px;
        }
        
        .log-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .log-actions .btn {
            padding: 10px 16px;
            font-size: 13px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f0f2f5;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e4e6eb;
        }
        
        .log-display {
            background: #1e1e1e;
            color: #00ff00;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #333;
        }
        
        .log-display::-webkit-scrollbar {
            width: 8px;
        }
        
        .log-display::-webkit-scrollbar-track {
            background: #333;
        }
        
        .log-display::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        
        .log-display::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }
        
        .log-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .no-data {
            padding: 40px;
            text-align: center;
            color: #999;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .backup-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
            margin-top: 12px;
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
            <div class="navbar-menu">
                <a href="index.php" class="navbar-link">Dashboard</a>
                <a href="manage-questions.php" class="navbar-link">Questions</a>
                <a href="manage-tokens.php" class="navbar-link">Tokens</a>
                <a href="view-results.php" class="navbar-link">Results</a>
                <a href="logs.php" class="navbar-link active">Logs</a>
                <a href="../logout.php" class="navbar-link logout">Logout</a>
            </div>
        </div>
    </div>

    <div class="logs-container">
        <div style="margin-bottom: 24px;">
            <h1>📊 System Logs & Backup</h1>
            <p style="color: #666; margin-top: 8px;">Monitor system activities, view logs, and manage database backups</p>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['message'] ?>
        </div>
        <?php unset($_SESSION['message']); endif; ?>

        <!-- Quick Stats -->
        <?php
        $todayTests = $db->query("SELECT COUNT(*) as count FROM test_results WHERE DATE(completed_at) = CURDATE()")->fetch()['count'];
        $totalTests = $db->query("SELECT COUNT(*) as count FROM test_results")->fetch()['count'];
        $activeTokens = $db->query("SELECT COUNT(*) as count FROM tokens WHERE is_used = 0 AND expires_at > NOW()")->fetch()['count'];
        ?>
        <div class="log-stats">
            <div class="stat-card">
                <div class="stat-value"><?= $todayTests ?></div>
                <div class="stat-label">Tests Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $totalTests ?></div>
                <div class="stat-label">Total Tests</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $activeTokens ?></div>
                <div class="stat-label">Active Tokens</div>
            </div>
        </div>

        <!-- Backup & Reports Section -->
        <div class="log-section">
            <div class="section-title">🗄️ Database Backup & Reports</div>
            <div class="log-actions">
                <a href="?action=backup&type=database" class="btn btn-primary">📥 Backup Database</a>
                <a href="?action=report" class="btn btn-primary">📊 Generate Daily Report</a>
            </div>
            <div class="backup-info">
                <strong>ℹ️ Info:</strong> Backups are stored as SQL files in the logs/backups directory. Daily reports are stored in logs/reports directory.
            </div>
        </div>

        <!-- Test Submissions Log -->
        <div class="log-section">
            <div class="section-title">📝 Test Submissions Log</div>
            <div class="log-actions">
                <a href="?action=view&log=test_submissions" class="btn btn-secondary">View Full Log</a>
            </div>
            <div class="log-display" id="test-submissions">
                <?php 
                $logContent = $logger->getLogContents('test_submissions');
                if ($logContent) {
                    $lines = array_slice(explode("\n", $logContent), -20); // Show last 20 lines
                    echo htmlspecialchars(implode("\n", $lines));
                } else {
                    echo "No test submissions logged yet.";
                }
                ?>
            </div>
        </div>

        <!-- Admin Actions Log -->
        <div class="log-section">
            <div class="section-title">👨‍💼 Admin Actions Log</div>
            <div class="log-display">
                <?php 
                $logContent = $logger->getLogContents('admin_actions');
                if ($logContent) {
                    $lines = array_slice(explode("\n", $logContent), -20); // Show last 20 lines
                    echo htmlspecialchars(implode("\n", $lines));
                } else {
                    echo "No admin actions logged yet.";
                }
                ?>
            </div>
        </div>

        <!-- Token Generation Log -->
        <div class="log-section">
            <div class="section-title">🎫 Token Generation Log</div>
            <div class="log-display">
                <?php 
                $logContent = $logger->getLogContents('token_generation');
                if ($logContent) {
                    $lines = array_slice(explode("\n", $logContent), -20); // Show last 20 lines
                    echo htmlspecialchars(implode("\n", $lines));
                } else {
                    echo "No tokens generated yet.";
                }
                ?>
            </div>
        </div>

        <!-- Error Log -->
        <div class="log-section">
            <div class="section-title">⚠️ Error Log</div>
            <div class="log-display">
                <?php 
                $logContent = $logger->getLogContents('error');
                if ($logContent) {
                    $lines = array_slice(explode("\n", $logContent), -20); // Show last 20 lines
                    echo htmlspecialchars(implode("\n", $lines));
                } else {
                    echo "No errors logged.";
                }
                ?>
            </div>
        </div>

        <!-- User Authentication Log -->
        <div class="log-section">
            <div class="section-title">🔐 User Authentication Log</div>
            <div class="log-display">
                <?php 
                $logContent = $logger->getLogContents('user_authentication');
                if ($logContent) {
                    $lines = array_slice(explode("\n", $logContent), -20); // Show last 20 lines
                    echo htmlspecialchars(implode("\n", $lines));
                } else {
                    echo "No authentication logs yet.";
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of log displays
        document.querySelectorAll('.log-display').forEach(el => {
            el.scrollTop = el.scrollHeight;
        });
    </script>
</body>
</html>

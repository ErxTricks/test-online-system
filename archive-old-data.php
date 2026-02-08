<?php
require_once '../config/database.php';
require_once '../includes/logger.php';

/**
 * Archive Old Data - Script untuk mengarsipkan data lama
 * Jalankan: php archive-old-data.php
 * atau melalui: localhost/test-online-system/archive-old-data.php
 */

$db = getDBConnection();
$archiveDate = date('Y-m-d', strtotime('-6 months')); // Archive data lebih dari 6 bulan
$backupDir = __DIR__ . '/logs/archive/';

// Buat folder jika belum ada
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$logFile = $backupDir . "archive_log_{$timestamp}.txt";
$handle = fopen($logFile, 'w');

function logMessage($handle, $message) {
    $time = date('Y-m-d H:i:s');
    $log = "[$time] $message\n";
    fwrite($handle, $log);
    echo $log;
}

try {
    logMessage($handle, "========== ARCHIVE PROCESS START ==========");
    logMessage($handle, "Archiving data older than: $archiveDate");
    logMessage($handle, "");
    
    // 1. BACKUP user_answers sebelum delete
    logMessage($handle, "📊 Processing user_answers...");
    
    // Check if archive table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'user_answers_archive'");
    if ($checkTable->rowCount() == 0) {
        logMessage($handle, "   ⚠️ Creating user_answers_archive table...");
        $db->exec("
            CREATE TABLE user_answers_archive (
                id INT NOT NULL,
                token_id INT NOT NULL,
                question_id INT NOT NULL,
                selected_option CHAR(1) NOT NULL,
                points_earned INT NOT NULL,
                answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_archived_at (archived_at),
                INDEX idx_token_id (token_id),
                FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE
            )
        ");
    }
    
    // Insert ke archive
    $insertAnswers = "
        INSERT INTO user_answers_archive (id, token_id, question_id, selected_option, points_earned, answered_at)
        SELECT id, token_id, question_id, selected_option, points_earned, answered_at 
        FROM user_answers 
        WHERE answered_at < '$archiveDate'
        ON DUPLICATE KEY UPDATE archived_at = NOW()
    ";
    $db->exec($insertAnswers);
    
    // Get count before delete
    $countAnswers = $db->query("SELECT COUNT(*) as cnt FROM user_answers WHERE answered_at < '$archiveDate'")->fetch();
    logMessage($handle, "   ✅ Archived: " . $countAnswers['cnt'] . " records");
    
    // Delete from original table
    $deleteAnswers = $db->exec("DELETE FROM user_answers WHERE answered_at < '$archiveDate'");
    logMessage($handle, "   🗑️  Deleted: $deleteAnswers records from production");
    logMessage($handle, "");
    
    // 2. BACKUP test_results
    logMessage($handle, "📈 Processing test_results...");
    
    $checkTable = $db->query("SHOW TABLES LIKE 'test_results_archive'");
    if ($checkTable->rowCount() == 0) {
        logMessage($handle, "   ⚠️ Creating test_results_archive table...");
        $db->exec("
            CREATE TABLE test_results_archive (
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
                INDEX idx_archived_at (archived_at),
                INDEX idx_token_id (token_id),
                INDEX idx_completed_at (completed_at),
                FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE
            )
        ");
    }
    
    $insertResults = "
        INSERT INTO test_results_archive (id, token_id, total_score, max_score, percentage, total_questions, answered_questions, completed_at)
        SELECT id, token_id, total_score, max_score, percentage, total_questions, answered_questions, completed_at 
        FROM test_results 
        WHERE completed_at < '$archiveDate'
        ON DUPLICATE KEY UPDATE archived_at = NOW()
    ";
    $db->exec($insertResults);
    
    $countResults = $db->query("SELECT COUNT(*) as cnt FROM test_results WHERE completed_at < '$archiveDate'")->fetch();
    logMessage($handle, "   ✅ Archived: " . $countResults['cnt'] . " records");
    
    $deleteResults = $db->exec("DELETE FROM test_results WHERE completed_at < '$archiveDate'");
    logMessage($handle, "   🗑️  Deleted: $deleteResults records from production");
    logMessage($handle, "");
    
    // 3. BACKUP user_profiles (optional - keep untuk reference)
    logMessage($handle, "👤 Processing user_profiles (backup only, not deleted)...");
    
    $checkTable = $db->query("SHOW TABLES LIKE 'user_profiles_archive'");
    if ($checkTable->rowCount() == 0) {
        logMessage($handle, "   ⚠️ Creating user_profiles_archive table...");
        $db->exec("
            CREATE TABLE user_profiles_archive AS SELECT * FROM user_profiles WHERE 1=0;
            ALTER TABLE user_profiles_archive ADD COLUMN archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
            ALTER TABLE user_profiles_archive DROP FOREIGN KEY user_profiles_ibfk_1;
            ALTER TABLE user_profiles_archive ADD PRIMARY KEY (id);
            ALTER TABLE user_profiles_archive ADD INDEX idx_archived_at (archived_at);
        ");
    }
    
    $insertProfiles = "
        INSERT INTO user_profiles_archive
        SELECT up.*, NOW() as archived_at 
        FROM user_profiles up
        WHERE up.created_at < '$archiveDate'
        AND NOT EXISTS (SELECT 1 FROM user_profiles_archive WHERE id = up.id)
    ";
    $db->exec($insertProfiles);
    
    $countProfiles = $db->query("SELECT COUNT(*) as cnt FROM user_profiles WHERE created_at < '$archiveDate'")->fetch();
    logMessage($handle, "   ✅ Backed up: " . $countProfiles['cnt'] . " records");
    logMessage($handle, "");
    
    // 4. DATABASE OPTIMIZATION
    logMessage($handle, "🔧 Optimizing tables...");
    $db->exec("OPTIMIZE TABLE user_answers");
    logMessage($handle, "   ✅ Optimized: user_answers");
    $db->exec("OPTIMIZE TABLE test_results");
    logMessage($handle, "   ✅ Optimized: test_results");
    $db->exec("OPTIMIZE TABLE user_profiles");
    logMessage($handle, "   ✅ Optimized: user_profiles");
    logMessage($handle, "");
    
    // 5. DATABASE STATISTICS
    logMessage($handle, "📊 Database Statistics:");
    $query = "SELECT TABLE_NAME, ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size_MB' 
              FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'test_online_system' 
              ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC";
    $stats = $db->query($query)->fetchAll();
    
    $totalSize = 0;
    foreach ($stats as $stat) {
        $size = floatval($stat['Size_MB']);
        $totalSize += $size;
        logMessage($handle, "   - {$stat['TABLE_NAME']}: {$size} MB");
    }
    logMessage($handle, "   📈 Total Database Size: $totalSize MB");
    logMessage($handle, "");
    
    logMessage($handle, "========== ARCHIVE PROCESS COMPLETE ==========");
    logMessage($handle, "✅ Success! All old data archived.");
    
} catch (PDOException $e) {
    logMessage($handle, "❌ ERROR: " . $e->getMessage());
    logMessage($handle, "Stack: " . $e->getTraceAsString());
} catch (Exception $e) {
    logMessage($handle, "❌ ERROR: " . $e->getMessage());
}

fclose($handle);

// Output file info
$fileSize = filesize($logFile) / 1024;
echo "\n";
echo "📝 Log file saved: logs/archive/archive_log_{$timestamp}.txt ({$fileSize} KB)\n";
echo "Navigate to it in: http://localhost/test-online-system/logs/archive/\n";
?>

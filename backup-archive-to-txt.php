<?php
require_once '../config/database.php';

/**
 * Backup Archive Data to TXT Log Files
 * Jalankan: php backup-archive-to-txt.php
 * atau: localhost/test-online-system/backup-archive-to-txt.php
 */

$db = getDBConnection();
$backupDir = __DIR__ . '/logs/backup/';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$logFile = $backupDir . "backup_{$timestamp}.txt";
$handle = fopen($logFile, 'w');

// Header
fwrite($handle, str_repeat("=", 70) . "\n");
fwrite($handle, "BACKUP ARCHIVE DATA - " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, str_repeat("=", 70) . "\n\n");

try {
    // 1. Archive user_answers count
    fwrite($handle, "1. USER ANSWERS ARCHIVE\n");
    fwrite($handle, str_repeat("-", 70) . "\n");
    
    $answersCount = $db->query("SELECT COUNT(*) as cnt FROM user_answers_archive")->fetch();
    fwrite($handle, "Total Archived Records: " . $answersCount['cnt'] . "\n");
    fwrite($handle, "Export Mode: Latest 500 records\n\n");
    
    $answers = $db->query("SELECT * FROM user_answers_archive ORDER BY archived_at DESC LIMIT 500");
    
    // Column headers
    fwrite($handle, sprintf("%-5s | %-10s | %-15s | %-5s | %-8s | %-20s | %-20s\n", 
        'ID', 'Token ID', 'Question ID', 'Optn', 'Points', 'Answered At', 'Archived At'));
    fwrite($handle, str_repeat("-", 105) . "\n");
    
    foreach ($answers as $answer) {
        fwrite($handle, sprintf("%-5d | %-10d | %-15d | %-5s | %-8d | %-20s | %-20s\n",
            $answer['id'],
            $answer['token_id'],
            $answer['question_id'],
            $answer['selected_option'],
            $answer['points_earned'],
            substr($answer['answered_at'], 0, 19),
            substr($answer['archived_at'], 0, 19)
        ));
    }
    fwrite($handle, "\n\n");
    
    // 2. Test Results Archive
    fwrite($handle, "2. TEST RESULTS ARCHIVE\n");
    fwrite($handle, str_repeat("-", 70) . "\n");
    
    $resultsCount = $db->query("SELECT COUNT(*) as cnt FROM test_results_archive")->fetch();
    fwrite($handle, "Total Archived Records: " . $resultsCount['cnt'] . "\n");
    fwrite($handle, "Export Mode: Latest 100 records\n\n");
    
    $results = $db->query("SELECT * FROM test_results_archive ORDER BY archived_at DESC LIMIT 100");
    
    // Column headers
    fwrite($handle, sprintf("%-5s | %-10s | %-8s | %-8s | %-5s | %-5s | %-5s | %-20s | %-20s\n",
        'ID', 'Token ID', 'Score', 'Max', 'Pct%', 'Tot Q', 'Ans Q', 'Completed At', 'Archived At'));
    fwrite($handle, str_repeat("-", 120) . "\n");
    
    foreach ($results as $result) {
        fwrite($handle, sprintf("%-5d | %-10d | %-8d | %-8d | %-5.1f | %-5d | %-5d | %-20s | %-20s\n",
            $result['id'],
            $result['token_id'],
            $result['total_score'],
            $result['max_score'],
            $result['percentage'],
            $result['total_questions'],
            $result['answered_questions'],
            substr($result['completed_at'], 0, 19),
            substr($result['archived_at'], 0, 19)
        ));
    }
    fwrite($handle, "\n\n");
    
    // 3. User Profiles Archive
    fwrite($handle, "3. USER PROFILES ARCHIVE\n");
    fwrite($handle, str_repeat("-", 70) . "\n");
    
    $profilesCount = $db->query("SELECT COUNT(*) as cnt FROM user_profiles_archive")->fetch();
    fwrite($handle, "Total Archived Records: " . $profilesCount['cnt'] . "\n");
    fwrite($handle, "Export Mode: Latest 100 records (JSON format)\n\n");
    
    $profiles = $db->query("SELECT * FROM user_profiles_archive ORDER BY archived_at DESC LIMIT 100");
    
    foreach ($profiles as $i => $profile) {
        fwrite($handle, "[Record " . ($i+1) . "]\n");
        fwrite($handle, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n");
    }
    
    // 4. Summary Statistics
    fwrite($handle, "\n" . str_repeat("=", 70) . "\n");
    fwrite($handle, "SUMMARY STATISTICS\n");
    fwrite($handle, str_repeat("=", 70) . "\n\n");
    
    // Production Data Stats
    fwrite($handle, "PRODUCTION DATA (Active):\n");
    $prodAnswers = $db->query("SELECT COUNT(*) as cnt FROM user_answers")->fetch();
    $prodResults = $db->query("SELECT COUNT(*) as cnt FROM test_results")->fetch();
    $prodProfiles = $db->query("SELECT COUNT(*) as cnt FROM user_profiles")->fetch();
    
    fwrite($handle, "  - user_answers: " . $prodAnswers['cnt'] . " records\n");
    fwrite($handle, "  - test_results: " . $prodResults['cnt'] . " records\n");
    fwrite($handle, "  - user_profiles: " . $prodProfiles['cnt'] . " records\n\n");
    
    // Archive Data Stats
    fwrite($handle, "ARCHIVED DATA (Historical):\n");
    fwrite($handle, "  - user_answers_archive: " . $answersCount['cnt'] . " records\n");
    fwrite($handle, "  - test_results_archive: " . $resultsCount['cnt'] . " records\n");
    fwrite($handle, "  - user_profiles_archive: " . $profilesCount['cnt'] . " records\n\n");
    
    // Database Size
    fwrite($handle, "DATABASE SIZE:\n");
    $dbSize = $db->query("
        SELECT 
            ROUND(SUM((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as total_mb
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = 'test_online_system'
    ")->fetch();
    fwrite($handle, "  - Total: " . $dbSize['total_mb'] . " MB\n");
    
    fwrite($handle, "\nGenerated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, str_repeat("=", 70) . "\n");
    
    echo "✅ Backup file created successfully!\n";
    echo "📁 Location: logs/backup/backup_{$timestamp}.txt\n";
    echo "📊 File Size: " . (filesize($logFile) / 1024) . " KB\n";
    
} catch (Exception $e) {
    fwrite($handle, "❌ ERROR: " . $e->getMessage() . "\n");
    echo "❌ Error: " . $e->getMessage();
}

fclose($handle);
?>

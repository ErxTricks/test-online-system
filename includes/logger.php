<?php
/**
 * Logging and Backup System for ABLE.ID
 * Handles: Test submissions, admin actions, errors, data backups
 */

class SystemLogger {
    private $logDir;
    private $db;
    
    public function __construct($dbConnection = null) {
        $this->logDir = __DIR__ . '/../logs';
        
        // Create logs directory if it doesn't exist
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $this->db = $dbConnection;
    }
    
    /**
     * Log test submission
     */
    public function logTestSubmission($tokenId, $tokenCode, $userName, $testTypes, $totalScore, $maxScore, $percentage) {
        $logFile = $this->logDir . '/test_submissions.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] Token: $tokenCode | User: $userName | Tests: $testTypes | Score: $totalScore/$maxScore ($percentage%) | Token ID: $tokenId\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Log admin action
     */
    public function logAdminAction($adminUsername, $action, $details) {
        $logFile = $this->logDir . '/admin_actions.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] Admin: $adminUsername | Action: $action | Details: $details\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Log token generation
     */
    public function logTokenGeneration($tokenCode, $testTypes, $expiresAt) {
        $logFile = $this->logDir . '/token_generation.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] Token Code: $tokenCode | Test Types: $testTypes | Expires: $expiresAt\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Log error
     */
    public function logError($errorMessage, $context = '') {
        $logFile = $this->logDir . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] ERROR: $errorMessage";
        if ($context) {
            $logEntry .= " | Context: $context";
        }
        $logEntry .= "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Log user authentication
     */
    public function logUserAuth($tokenCode, $userName, $action, $success) {
        $logFile = $this->logDir . '/user_authentication.log';
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = "[$timestamp] $status | Token: $tokenCode | User: $userName | Action: $action\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Backup database to text file
     */
    public function backupDatabase($filename = null) {
        if (!$this->db) {
            return false;
        }
        
        if (!$filename) {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        $backupFile = $this->logDir . '/backups/' . $filename;
        $backupDir = dirname($backupFile);
        
        // Create backups directory
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        try {
            $output = "-- Database Backup\n";
            $output .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Database: test_online_system\n\n";
            
            // Backup tables in order
            $tables = [
                'admin_users' => 'Admin Users',
                'tokens' => 'Test Tokens',
                'questions' => 'Test Questions',
                'user_answers' => 'User Answers',
                'test_results' => 'Test Results',
                'user_profiles' => 'User Profiles'
            ];
            
            foreach ($tables as $table => $description) {
                $output .= "\n-- ========================================\n";
                $output .= "-- Table: $table ($description)\n";
                $output .= "-- ========================================\n\n";
                
                // Get table structure
                $structureQuery = "SHOW CREATE TABLE `$table`";
                $stmt = $this->db->query($structureQuery);
                $structure = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $output .= $structure['Create Table'] . ";\n\n";
                
                // Get table data
                $dataQuery = "SELECT * FROM `$table`";
                $stmt = $this->db->query($dataQuery);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $output .= "-- Data for table: $table\n";
                    foreach ($rows as $row) {
                        $values = [];
                        $columns = [];
                        foreach ($row as $col => $val) {
                            $columns[] = "`$col`";
                            $values[] = is_null($val) ? 'NULL' : $this->db->quote($val);
                        }
                        $output .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $output .= "\n";
                }
            }
            
            file_put_contents($backupFile, $output);
            
            // Log backup action
            $this->logAdminAction('SYSTEM', 'DATABASE_BACKUP', "Backup file: $filename");
            
            return true;
        } catch (Exception $e) {
            $this->logError('Database backup failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate daily summary report
     */
    public function generateDailySummary() {
        $reportFile = $this->logDir . '/reports/daily_' . date('Y-m-d') . '.txt';
        $reportDir = dirname($reportFile);
        
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        try {
            $output = "========================================\n";
            $output .= "ABLE.ID DAILY SUMMARY REPORT\n";
            $output .= "Date: " . date('Y-m-d H:i:s') . "\n";
            $output .= "========================================\n\n";
            
            if ($this->db) {
                // Test Statistics
                $stmt = $this->db->query("SELECT COUNT(*) as total FROM test_results WHERE DATE(completed_at) = CURDATE()");
                $todayTests = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                $stmt = $this->db->query("SELECT AVG(percentage) as avg_score FROM test_results WHERE DATE(completed_at) = CURDATE()");
                $avgScore = $stmt->fetch(PDO::FETCH_ASSOC)['avg_score'];
                
                $stmt = $this->db->query("SELECT COUNT(*) as total FROM tokens WHERE is_used = 0 AND expires_at > NOW()");
                $activeTokens = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                $stmt = $this->db->query("SELECT COUNT(*) as total FROM tokens WHERE is_used = 0 AND expires_at < NOW()");
                $expiredTokens = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                $output .= "TEST STATISTICS (Today)\n";
                $output .= "  - Tests Completed: $todayTests\n";
                $output .= "  - Average Score: " . round($avgScore, 2) . "%\n\n";
                
                $output .= "TOKEN STATUS\n";
                $output .= "  - Active Tokens: $activeTokens\n";
                $output .= "  - Expired Tokens: $expiredTokens\n\n";
                
                // Test Type Summary
                $stmt = $this->db->query("SELECT test_types_taken, COUNT(*) as count FROM test_results WHERE DATE(completed_at) = CURDATE() GROUP BY test_types_taken");
                $testTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $output .= "TEST TYPES BREAKDOWN\n";
                foreach ($testTypes as $type) {
                    $output .= "  - {$type['test_types_taken']}: {$type['count']} tests\n";
                }
            }
            
            file_put_contents($reportFile, $output);
            return true;
        } catch (Exception $e) {
            $this->logError('Daily report generation failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get log file contents
     */
    public function getLogContents($logType) {
        $logFile = $this->logDir . '/' . $logType . '.log';
        if (file_exists($logFile)) {
            return file_get_contents($logFile);
        }
        return null;
    }
    
    /**
     * Get all log files
     */
    public function getAllLogs() {
        if (!is_dir($this->logDir)) {
            return [];
        }
        
        $logs = [];
        $files = glob($this->logDir . '/*.log');
        foreach ($files as $file) {
            $logs[basename($file)] = [
                'path' => $file,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        return $logs;
    }
}

// Helper function to get logger instance
function getLogger($db = null) {
    static $logger = null;
    if ($logger === null) {
        $logger = new SystemLogger($db);
    }
    return $logger;
}
?>

<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Pastikan hanya admin yang bisa akses
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: ../admin-login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validasi Password Admin
    $db = getDBConnection();
    // Ambil password hash dari database berdasarkan session username
    $stmt = $db->prepare("SELECT password FROM admin_users WHERE username = ?");
    $stmt->execute([$_SESSION['admin_username']]); 
    $admin = $stmt->fetch();
    
    // PERUBAHAN DI SINI: Gunakan password_verify() (Bcrypt)
    if ($admin && password_verify($password, $admin['password'])) {
        
        // Password Benar, Jalankan Aksi
        if ($action === 'backup') {
            // Logika Backup Database
            $tables = [];
            $result = $db->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $sqlScript = "";
            foreach ($tables as $table) {
                $result = $db->query("SELECT * FROM $table");
                $numFields = $result->columnCount();
                
                $sqlScript .= "DROP TABLE IF EXISTS $table;";
                $row2 = $db->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
                $sqlScript .= "\n\n" . $row2[1] . ";\n\n";
                
                for ($i = 0; $i < $numFields; $i++) {
                    while ($row = $result->fetch(PDO::FETCH_NUM)) {
                        $sqlScript .= "INSERT INTO $table VALUES(";
                        for ($j = 0; $j < $numFields; $j++) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = str_replace("\n", "\\n", $row[$j]);
                            if (isset($row[$j])) { $sqlScript .= '"' . $row[$j] . '"'; } else { $sqlScript .= '""'; }
                            if ($j < ($numFields - 1)) { $sqlScript .= ','; }
                        }
                        $sqlScript .= ");\n";
                    }
                }
                $sqlScript .= "\n\n\n";
            }
            
            $backup_file_name = 'db_backup_' . date("Y-m-d_H-i-s") . '.sql';
            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary");
            header("Content-disposition: attachment; filename=\"" . $backup_file_name . "\"");
            echo $sqlScript;
            exit;
            
        } elseif ($action === 'optimize') {
            // Logika Optimize Tables
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $db->query("OPTIMIZE TABLE $table");
            }
            $message = "Database berhasil dioptimalkan!";
            
        } elseif ($action === 'clear_logs') {
            // Logika Clear Logs (Hapus log > 30 hari)
            $stmt = $db->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            $message = "$deleted log lama berhasil dihapus.";
        }
        
    } else {
        $error = "Password Admin salah! Tindakan dibatalkan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Maintenance - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                <a href="view-results.php" class="nav-item"><span><i class="fas fa-poll"></i></span> Lihat Hasil</a>
            </nav>
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="logs.php" class="nav-item"><span><i class="fas fa-history"></i></span> System Logs</a>
                <a href="database-maintenance.php" class="nav-item active"><span><i class="fas fa-tools"></i></span> Database Maint.</a>
                <a href="../index.php" class="nav-item"><span><i class="fas fa-home"></i></span> Ke Website</a>
                <a href="admin-logout.php" class="nav-item nav-logout"><span><i class="fas fa-sign-out-alt"></i></span> Logout</a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left"><i class="fas fa-database"></i> Database Maintenance</div>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $message ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                        <i class="fas fa-times-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 style="margin: 0; font-size: 16px;"><i class="fas fa-shield-alt"></i> System Utilities (Protected)</h3>
                        <p style="margin: 5px 0 0; color: #666; font-size: 13px;">Aksi di halaman ini memerlukan konfirmasi password admin.</p>
                    </div>
                    <div style="padding: 24px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
                            
                            <div style="text-align: center; padding: 20px; border: 1px solid #eee; border-radius: 8px; background: #f9fafb;">
                                <i class="fas fa-database" style="font-size: 48px; color: #667eea; margin-bottom: 16px;"></i>
                                <h4 style="margin: 0 0 8px 0;">Backup Database</h4>
                                <p style="font-size: 13px; color: #666; margin-bottom: 16px;">Download full backup SQL database sistem.</p>
                                <button onclick="confirmAction('backup')" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-download"></i> Start Backup
                                </button>
                            </div>

                            <div style="text-align: center; padding: 20px; border: 1px solid #eee; border-radius: 8px; background: #f9fafb;">
                                <i class="fas fa-broom" style="font-size: 48px; color: #2dce89; margin-bottom: 16px;"></i>
                                <h4 style="margin: 0 0 8px 0;">Optimize Tables</h4>
                                <p style="font-size: 13px; color: #666; margin-bottom: 16px;">Bersihkan overhead dan optimasi performa DB.</p>
                                <button onclick="confirmAction('optimize')" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-bolt"></i> Run Optimize
                                </button>
                            </div>

                            <div style="text-align: center; padding: 20px; border: 1px solid #eee; border-radius: 8px; background: #f9fafb;">
                                <i class="fas fa-trash-alt" style="font-size: 48px; color: #f5365c; margin-bottom: 16px;"></i>
                                <h4 style="margin: 0 0 8px 0;">Clear System Logs</h4>
                                <p style="font-size: 13px; color: #666; margin-bottom: 16px;">Hapus log aktivitas lama untuk menghemat ruang.</p>
                                <button onclick="confirmAction('clear_logs')" class="btn btn-danger" style="width: 100%; border: 1px solid #f5365c; background: white; color: #f5365c;">
                                    <i class="fas fa-fire"></i> Clear Logs
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="passwordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="fas fa-lock" style="font-size: 48px; color: #f5365c;"></i>
                <h3 style="margin: 15px 0 5px 0;">Keamanan Diperlukan</h3>
                <p style="color: #666; font-size: 14px; margin: 0;">Masukkan password admin untuk konfirmasi aksi:</p>
                <strong id="actionName" style="display: block; margin-top: 5px; color: #333;">Action Name</strong>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction">
                <div class="form-group">
                    <input type="password" name="password" required placeholder="Masukkan Password Admin" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('passwordModal');
        const formAction = document.getElementById('formAction');
        const actionName = document.getElementById('actionName');

        function confirmAction(action) {
            let name = '';
            if(action === 'backup') name = 'Backup Database';
            if(action === 'optimize') name = 'Optimize Tables';
            if(action === 'clear_logs') name = 'Clear System Logs';

            formAction.value = action;
            actionName.textContent = name;
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
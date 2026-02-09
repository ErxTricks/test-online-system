<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validasi: Hanya Huruf dan Angka
    if (!ctype_alnum($username)) {
        $error = "Username hanya boleh berisi huruf dan angka (tanpa simbol/spasi).";
    } else {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Cek password (BCRYPT sesuai permintaan sebelumnya)
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];

            header("Location: admin/index.php");
            exit;
        } else {
            $error = "Username atau Password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ABLE.ID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 16px;
        }
        .input-group input {
            padding-left: 45px; /* Space for icon */
        }
    </style>
</head>
<body style="background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    
    <div class="login-card" style="width: 100%; max-width: 400px; padding: 40px; border-radius: 16px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="font-size: 40px; color: #667eea; margin-bottom: 10px;">
                <i class="fas fa-user-shield"></i>
            </div>
            <h2 style="margin: 0; color: #333;">Admin Login</h2>
            <p style="color: #666; font-size: 14px;">Masuk ke panel kontrol ABLE.ID</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="font-size: 14px;">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Username" required 
                       pattern="[a-zA-Z0-9]+" title="Hanya huruf dan angka diperbolehkan"
                       style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required 
                       style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">
                <i class="fas fa-sign-in-alt"></i> Masuk
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; font-size: 13px;">
            <a href="index.php" style="color: #666; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Kembali ke Website
            </a>
        </div>
    </div>

</body>
</html>
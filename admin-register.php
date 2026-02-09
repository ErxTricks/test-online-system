<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

// SECRET KEY Check (Sederhana agar tidak sembarang orang bisa register jadi admin)
// Anda bisa menghapus bagian ini jika tidak perlu "Secret Key"
$SECRET_REGISTRATION_KEY = "admin123"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $secret_key = $_POST['secret_key'] ?? '';
    
    // Validasi Username (Alphanumeric only)
    if (!ctype_alnum($username)) {
        $error = "Username hanya boleh huruf dan angka!";
    }
    // Validasi Secret Key (Optional)
    elseif ($secret_key !== $SECRET_REGISTRATION_KEY) {
        $error = "Kunci Registrasi Salah!";
    }
    else {
        $db = getDBConnection();
        
        // Cek username exist
        $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username sudah digunakan!";
        } else {
            // Insert new admin (MD5 password)
            $stmt = $db->prepare("INSERT INTO admin_users (username, password, full_name, email, is_active) VALUES (?, ?, ?, ?, 1)");
            if ($stmt->execute([$username, md5($password), $full_name, $email])) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Gagal mendaftar.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register - ABLE.ID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .input-group { position: relative; margin-bottom: 15px; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .input-group input { padding-left: 45px; }
    </style>
</head>
<body style="background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    
    <div class="login-card" style="width: 100%; max-width: 450px; padding: 40px; border-radius: 16px;">
        <div style="text-align: center; margin-bottom: 25px;">
            <h2 style="margin: 0; color: #333;"><i class="fas fa-user-plus"></i> Register Admin</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Username (Huruf & Angka)" required pattern="[a-zA-Z0-9]+" title="Hanya huruf dan angka" style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            <div class="input-group">
                <i class="fas fa-id-card"></i>
                <input type="text" name="full_name" placeholder="Nama Lengkap" required style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            <div class="input-group">
                <i class="fas fa-key"></i>
                <input type="password" name="secret_key" placeholder="Kunci Registrasi (Default: admin123)" required style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Daftar</button>
        </form>
        
        <div style="text-align: center; margin-top: 15px;">
            <a href="admin-login.php">Sudah punya akun? Login</a>
        </div>
    </div>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

$error = '';

// Jika sudah login, redirect ke admin panel
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan Password harus diisi!";
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT id, username, password FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            // Support both bcrypt and MD5 for backward compatibility
            $isPasswordValid = false;
            if ($admin) {
                // Check if it's a bcrypt hash (starts with $2y$)
                if (substr($admin['password'], 0, 4) === '$2y$') {
                    $isPasswordValid = password_verify($password, $admin['password']);
                } else {
                    // Fallback to MD5 (legacy)
                    $isPasswordValid = (md5($password) === $admin['password']);
                }
            }

            if ($isPasswordValid) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                header("Location: admin/index.php");
                exit;
            } else {
                $error = "Username atau Password salah!";
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sistem Test Online</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 20px;
        }
        
        .admin-login-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .admin-login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .admin-login-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0 0 10px 0;
        }
        
        .admin-login-header p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }
        
        .admin-login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .admin-login-btn {
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .admin-login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .admin-login-error {
            background: #fee;
            color: #c00;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #999;
        }
        
        .admin-login-back {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .admin-login-back:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-login-header">
                <h1>🔐 Admin Login</h1>
                <p>Masuk ke panel administrasi</p>
            </div>
            
            <?php if ($error): ?>
                <div class="admin-login-error">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="admin-login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Masukkan username"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Masukkan password"
                        required
                    >
                </div>
                
                <button type="submit" class="admin-login-btn">Login</button>
            </form>
            
            <div class="admin-login-footer">
                <p>Default: admin / admin123</p>
                <p style="margin-top: 10px;">Belum punya akun? <a href="admin-register.php" style="color: #667eea;">Daftar di sini</a></p>
            </div>

            <div style="text-align: center; margin-top: 15px;">
                <a href="index.php" class="admin-login-back">← Kembali ke Login Peserta</a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
session_start();
require_once 'config/database.php';

$success = '';
$error = '';

// Redirect kalau sudah login
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi input
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Semua field harus diisi!";
    } elseif (strlen($username) < 4) {
        $error = "Username minimal 4 karakter!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan konfirmasi tidak cocok!";
    } else {
        try {
            $db = getDBConnection();

            // Cek username sudah ada atau belum
            $checkStmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
            $checkStmt->execute([$username]);

            if ($checkStmt->fetch()) {
                $error = "Username sudah digunakan!";
            } else {
                // Hash password dengan bcrypt
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Insert admin baru
                $insertStmt = $db->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
                if ($insertStmt->execute([$username, $hashedPassword])) {
                    $success = "Registrasi berhasil! Silakan login.";
                } else {
                    $error = "Gagal mendaftar. Silakan coba lagi.";
                }
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - Sistem Test Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }

        .register-header p {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .register-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e0e0e0;
        }

        .register-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .register-footer a:hover {
            text-decoration: underline;
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>📝 Register Admin</h1>
            <p>Buat akun admin baru</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Masukkan username"
                    required
                    autofocus
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
                <div class="password-requirements">Minimal 4 karakter</div>
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
                <div class="password-requirements">Minimal 6 karakter</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Masukkan ulang password"
                    required
                >
            </div>

            <button type="submit" class="btn-register">Register</button>
        </form>

        <div class="register-footer">
            <p style="margin-bottom: 10px; color: #666;">Sudah punya akun?</p>
            <a href="admin-login.php">← Login Admin</a>
        </div>
    </div>
</body>
</html>

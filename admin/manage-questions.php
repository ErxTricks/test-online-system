<?php
session_start();
require_once '../includes/functions.php';
require_once '../config/database.php';

// Admin authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin-login.php");
    exit;
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $db = getDBConnection();
        
        if ($_POST['action'] === 'add') {
            $question_text = $_POST['question_text'] ?? '';
            $option_a = $_POST['option_a'] ?? '';
            $option_b = $_POST['option_b'] ?? '';
            $option_c = $_POST['option_c'] ?? '';
            $option_d = $_POST['option_d'] ?? '';
            $category = $_POST['category'] ?? '';
            $test_type = $_POST['test_type'] ?? 'HSCL-25';
            
            if (!empty($question_text) && !empty($option_a) && !empty($option_b) && !empty($option_c) && !empty($option_d) && !empty($category)) {
                $stmt = $db->prepare("
                    INSERT INTO questions 
                    (question_text, option_a, option_b, option_c, option_d, 
                     point_a, point_b, point_c, point_d, category, test_type)
                    VALUES (?, ?, ?, ?, ?, 1, 2, 3, 4, ?, ?)
                ");
                $stmt->execute([
                    $question_text,
                    $option_a,
                    $option_b,
                    $option_c,
                    $option_d,
                    $category,
                    $test_type
                ]);
                $message = "✅ Soal berhasil ditambahkan!";
            } else {
                $message = "❌ Semua field harus diisi!";
            }
        } elseif ($_POST['action'] === 'delete') {
            $question_id = $_POST['question_id'] ?? null;
            if ($question_id !== null) {
                $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([intval($question_id)]);
                $message = "✅ Soal berhasil dihapus!";
            } else {
                $message = "❌ ID soal tidak ditemukan!";
            }
        }
    }
}

// Get all questions
$db = getDBConnection();
$questions = $db->query("SELECT * FROM questions ORDER BY category, id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Soal - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <h3>◆ ABLE.ID</h3>
            </div>
            
            <div class="sidebar-section-title">Menu</div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <span>📊</span> Dashboard
                </a>
                <a href="generate-token.php" class="nav-item">
                    <span>🔑</span> Generate Token
                </a>
                <a href="manage-tokens.php" class="nav-item">
                    <span>📋</span> Kelola Token
                </a>
            </nav>
            
            <div class="sidebar-section-title">Data</div>
            <nav class="sidebar-nav">
                <a href="manage-questions.php" class="nav-item active">
                    <span>❓</span> Kelola Soal
                </a>
                <a href="view-results.php" class="nav-item">
                    <span>📈</span> Lihat Hasil
                </a>
            </nav>
            
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="database-maintenance.php" class="nav-item">
                    <span>🔧</span> Database Maint.
                </a>
                <a href="../index.php" class="nav-item">
                    <span>🏠</span> Ke Website
                </a>
                <a href="../logout.php" class="nav-item nav-logout">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Topbar -->
            <div class="admin-topbar">
                <div class="admin-topbar-left">❓ Kelola Soal HSCL-25</div>
                <div class="admin-topbar-right">
                    <a href="index.php" class="btn btn-secondary" style="font-size: 12px; padding: 8px 16px;">
                        📊 Dashboard
                    </a>
                </div>
            </div>
            <!-- Content -->
            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- Add Question Form -->
                <div class="card">
                    <div style="padding: 24px; border-bottom: 1px solid #e0e0e0;">
                        <h3 style="margin: 0; font-size: 16px;">➕ Tambah Soal HSCL-25 Baru</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div class="form-group" style="margin: 0;">
                                    <label>Jenis Soal</label>
                                    <select name="test_type" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit;">
                                        <option value="HSCL-25">HSCL-25 (Mental Health)</option>
                                        <option value="VAK">VAK (Learning Style)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Kategori</label>
                                    <select name="category" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit;">
                                        <option value="">-- Pilih Kategori --</option>
                                        <option value="Anxiety">😰 Anxiety (Kecemasan)</option>
                                        <option value="Depresi">😢 Depresi</option>
                                        <option value="Visual">👁️ Visual</option>
                                        <option value="Auditory">👂 Auditory</option>
                                        <option value="Kinesthetic">🤸 Kinesthetic</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Pertanyaan</label>
                                <textarea name="question_text" rows="3" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit;"></textarea>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi A</label>
                                    <input type="text" name="option_a" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi B</label>
                                    <input type="text" name="option_b" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi C</label>
                                    <input type="text" name="option_c" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi D</label>
                                    <input type="text" name="option_d" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                ✅ Tambah Soal
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Questions List -->
                <div class="section-header">
                    <h2 class="section-title">📋 Daftar Soal (<?= count($questions) ?> total)</h2>
                </div>

                <div class="card">
                    <div style="padding: 24px;">
                        <?php if (count($questions) > 0): ?>
                            <div style="display: grid; gap: 16px;">
                                <?php 
                                $currentCategory = '';
                                foreach ($questions as $q): 
                                    if ($currentCategory !== $q['category']):
                                        if (!empty($currentCategory)):
                                            echo '</div></div>';
                                        endif;
                                        $currentCategory = $q['category'];
                                        echo '<div style="border: 2px solid #667eea; border-radius: 8px; overflow: hidden;">';
                                        echo '<div style="background: #667eea; color: white; padding: 12px; font-weight: 600;">';
                                        echo ($q['category'] === 'Anxiety' ? '😰' : '😢') . ' ' . $q['category'];
                                        echo '</div>';
                                        echo '<div style="padding: 16px;">';
                                    endif;
                                ?>
                                    <div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 12px; border-left: 4px solid #667eea;">
                                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 12px; margin-bottom: 12px;">
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                                    #<?= $q['id'] ?> - <?= htmlspecialchars($q['question_text']) ?>
                                                </div>
                                                <div style="font-size: 12px; color: #666;">
                                                    <strong>A:</strong> <?= htmlspecialchars($q['option_a']) ?> |
                                                    <strong>B:</strong> <?= htmlspecialchars($q['option_b']) ?> |
                                                    <strong>C:</strong> <?= htmlspecialchars($q['option_c']) ?> |
                                                    <strong>D:</strong> <?= htmlspecialchars($q['option_d']) ?>
                                                </div>
                                            </div>
                                            <form method="POST" style="display: flex; gap: 6px;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                <button type="submit" class="btn-action btn-action-danger" onclick="return confirm('Yakin hapus?');">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php 
                                endforeach;
                                if (!empty($currentCategory)):
                                    echo '</div></div>';
                                endif;
                                ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #999;">
                                <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
                                Belum ada soal
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .btn-action {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-action-danger {
            background: #fee;
            color: #c00;
        }
        
        .btn-action-danger:hover {
            background: #fcc;
        }
    </style>
</body>
</html>
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
$db = getDBConnection();

// Handle form submission (Tambah & Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
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
                    $question_text, $option_a, $option_b, $option_c, $option_d, $category, $test_type
                ]);
                $message = "Soal berhasil ditambahkan!";
            } else {
                $message = "Semua field harus diisi!";
            }
        } elseif ($_POST['action'] === 'delete') {
            $question_id = $_POST['question_id'] ?? null;
            if ($question_id !== null) {
                $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([intval($question_id)]);
                $message = "Soal berhasil dihapus!";
            }
        }
    }
}

// --- LOGIKA FILTER & PAGINATION ---
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all';
$limit = 10; // Jumlah soal per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Base Query
$whereClause = "";
$params = [];

if ($filterType !== 'all') {
    $whereClause = "WHERE test_type = ?";
    $params[] = $filterType;
}

// Hitung Total Data (untuk Pagination)
$countQuery = "SELECT COUNT(*) FROM questions $whereClause";
$stmtCount = $db->prepare($countQuery);
$stmtCount->execute($params);
$totalQuestions = $stmtCount->fetchColumn();
$totalPages = ceil($totalQuestions / $limit);

// Ambil Data Soal dengan LIMIT
$query = "SELECT * FROM questions $whereClause ORDER BY test_type, category, id ASC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Ambil daftar unik jenis tes untuk dropdown filter
$typesStmt = $db->query("SELECT DISTINCT test_type FROM questions ORDER BY test_type");
$testTypesList = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Bank Soal - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .btn-page {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: #fff;
            border: 1px solid #ddd;
            color: #667eea;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-page:hover {
            background: #f0f5ff;
            border-color: #667eea;
        }
        .btn-page.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .btn-page.disabled {
            background: #f9f9f9;
            color: #ccc;
            cursor: not-allowed;
            border-color: #eee;
        }
    </style>
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
                <a href="manage-questions.php" class="nav-item active"><span><i class="fas fa-question-circle"></i></span> Kelola Soal</a>
                <a href="view-results.php" class="nav-item"><span><i class="fas fa-poll"></i></span> Lihat Hasil</a>
            </nav>
            
            <div class="sidebar-section-title">Lainnya</div>
            <nav class="sidebar-nav">
                <a href="database-maintenance.php" class="nav-item"><span><i class="fas fa-tools"></i></span> Database Maint.</a>
                <a href="../index.php" class="nav-item"><span><i class="fas fa-home"></i></span> Ke Website</a>
                <a href="admin-logout.php" class="nav-item nav-logout"><span><i class="fas fa-sign-out-alt"></i></span> Logout</a>
            </nav>
        </aside>

        <div class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar-left"><i class="fas fa-question-circle"></i> Kelola Bank Soal</div>
                <div class="admin-topbar-right">
                    <a href="index.php" class="btn btn-secondary" style="font-size: 12px; padding: 8px 16px;">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $message ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div style="padding: 24px; border-bottom: 1px solid #e0e0e0;">
                        <h3 style="margin: 0; font-size: 16px;"><i class="fas fa-plus"></i> Tambah Soal Baru</h3>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div class="form-group" style="margin: 0;">
                                    <label>Jenis Alat Tes</label>
                                    <select name="test_type" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                                        <option value="HSCL-25">HSCL-25 (Kesehatan Mental)</option>
                                        <option value="VAK">VAK (Gaya Belajar)</option>
                                        <option value="DISC">DISC (Kepribadian)</option> 
                                    </select>
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Kategori (Dimensi)</label>
                                    <select name="category" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                                        <option value="">-- Pilih Kategori --</option>
                                        <optgroup label="HSCL-25">
                                            <option value="Anxiety">Anxiety (Kecemasan)</option>
                                            <option value="Depresi">Depresi</option>
                                        </optgroup>
                                        <optgroup label="VAK">
                                            <option value="Visual">Visual</option>
                                            <option value="Auditory">Auditory</option>
                                            <option value="Kinesthetic">Kinesthetic</option>
                                        </optgroup>
                                        <optgroup label="DISC">
                                            <option value="Dominance">Dominance (D)</option>
                                            <option value="Influence">Influence (I)</option>
                                            <option value="Steadiness">Steadiness (S)</option>
                                            <option value="Compliance">Compliance (C)</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Pertanyaan</label>
                                <textarea name="question_text" rows="2" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi A (Poin 1)</label>
                                    <input type="text" name="option_a" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi B (Poin 2)</label>
                                    <input type="text" name="option_b" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi C (Poin 3)</label>
                                    <input type="text" name="option_c" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                                <div class="form-group" style="margin: 0;">
                                    <label>Opsi D (Poin 4)</label>
                                    <input type="text" name="option_d" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-save"></i> Simpan Soal
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div style="padding: 24px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 16px;"><i class="fas fa-list-ul"></i> Daftar Soal (Total: <?= $totalQuestions ?>)</h3>
                        
                        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                            <label style="font-size: 13px; color: #666;">Filter:</label>
                            <select name="filter_type" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #ddd; font-size: 13px;">
                                <option value="all">Semua Jenis Tes</option>
                                <?php foreach ($testTypesList as $type): ?>
                                    <option value="<?= $type ?>" <?= $filterType === $type ? 'selected' : '' ?>>
                                        <?= $type ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <div style="padding: 24px;">
                        <?php if (count($questions) > 0): ?>
                            <div style="display: grid; gap: 16px;">
                                <?php 
                                $currentTestType = '';
                                $currentCategory = '';
                                
                                foreach ($questions as $q): 
                                    // Header untuk Jenis Tes Baru
                                    if ($currentTestType !== $q['test_type']) {
                                        if ($currentTestType !== '') echo '</div></div>'; 
                                        $currentTestType = $q['test_type'];
                                        $currentCategory = ''; 
                                        echo '<div style="margin-bottom: 30px;">';
                                        echo '<h4 style="margin: 0 0 10px 0; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 5px; display: inline-block;">';
                                        echo '<i class="fas fa-clipboard-list"></i> ' . $q['test_type'];
                                        echo '</h4><div style="display: grid; gap: 16px;">';
                                    }

                                    // Header untuk Kategori Baru
                                    if ($currentCategory !== $q['category']):
                                        if ($currentCategory !== '') echo '</div></div>'; 
                                        $currentCategory = $q['category'];
                                        
                                        $catIcon = 'fa-circle';
                                        if($q['category'] == 'Anxiety') $catIcon = 'fa-flushed';
                                        if($q['category'] == 'Depresi') $catIcon = 'fa-sad-tear';
                                        if($q['category'] == 'Visual') $catIcon = 'fa-eye';
                                        if($q['category'] == 'Auditory') $catIcon = 'fa-headphones';
                                        if($q['category'] == 'Kinesthetic') $catIcon = 'fa-running';

                                        echo '<div style="border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; background: white;">';
                                        echo '<div style="background: #f8f9fa; color: #555; padding: 10px 15px; font-weight: 600; font-size: 14px; border-bottom: 1px solid #e0e0e0;">';
                                        echo '<i class="fas '.$catIcon.'"></i> ' . $q['category'];
                                        echo '</div>';
                                        echo '<div style="padding: 0;">';
                                    endif;
                                ?>
                                    <div style="padding: 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: start; gap: 12px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #333; margin-bottom: 6px; font-size: 14px;">
                                                <?= htmlspecialchars($q['question_text']) ?>
                                            </div>
                                            <div style="font-size: 12px; color: #888;">
                                                <span style="background: #eee; padding: 2px 6px; border-radius: 4px;">A: <?= htmlspecialchars($q['option_a']) ?></span>
                                                <span style="background: #eee; padding: 2px 6px; border-radius: 4px;">B: <?= htmlspecialchars($q['option_b']) ?></span>
                                                <span style="background: #eee; padding: 2px 6px; border-radius: 4px;">C: <?= htmlspecialchars($q['option_c']) ?></span>
                                                <span style="background: #eee; padding: 2px 6px; border-radius: 4px;">D: <?= htmlspecialchars($q['option_d']) ?></span>
                                            </div>
                                        </div>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                            <button type="submit" class="btn-action" style="background: #fee; color: #c00; width: 30px; height: 30px; border-radius: 4px; border: none; cursor: pointer;" onclick="return confirm('Yakin hapus soal ini?');" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php 
                                endforeach;
                                if ($currentTestType !== '') echo '</div></div></div>'; 
                                ?>
                            </div>

                            <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&filter_type=<?= $filterType ?>" class="btn-page"><i class="fas fa-chevron-left"></i></a>
                                <?php else: ?>
                                    <span class="btn-page disabled"><i class="fas fa-chevron-left"></i></span>
                                <?php endif; ?>

                                <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?= $i ?>&filter_type=<?= $filterType ?>" class="btn-page <?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&filter_type=<?= $filterType ?>" class="btn-page"><i class="fas fa-chevron-right"></i></a>
                                <?php else: ?>
                                    <span class="btn-page disabled"><i class="fas fa-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #999;">
                                <div style="font-size: 32px; margin-bottom: 8px;"><i class="fas fa-inbox"></i></div>
                                Belum ada soal yang sesuai filter.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
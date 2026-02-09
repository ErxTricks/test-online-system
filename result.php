<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Cek apakah Admin yang mengakses (via parameter token_id)
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
$tokenId = null;
$token = null;

if ($isAdmin && isset($_GET['token_id'])) {
    // Admin melihat hasil user tertentu
    $tokenId = intval($_GET['token_id']);
    $db = getDBConnection();
    $tokenStmt = $db->prepare("SELECT token_code FROM tokens WHERE id = ?");
    $tokenStmt->execute([$tokenId]);
    $tokenData = $tokenStmt->fetch();
    if (!$tokenData) {
        echo "Token not found!";
        exit();
    }
    $token = $tokenData['token_code'];
} elseif (isset($_SESSION['token_code'])) {
    // User melihat hasil sendiri
    $token = $_SESSION['token_code'];
} else {
    header('Location: index.php');
    exit();
}

$db = getDBConnection();

// Ambil Profil User
$userStmt = $db->prepare("SELECT * FROM user_profiles WHERE token_id = (SELECT id FROM tokens WHERE token_code = ?)");
$userStmt->execute([$token]);
$user = $userStmt->fetch();
if (!$user) { $user = []; }

// Cek jenis tes yang diambil
$tokenInfoStmt = $db->prepare("SELECT selected_test_types FROM tokens WHERE token_code = ?");
$tokenInfoStmt->execute([$token]);
$tokenInfo = $tokenInfoStmt->fetch();
$testTypesTaken = $tokenInfo['selected_test_types'] ?? 'HSCL-25';

$isHSCL = strpos($testTypesTaken, 'HSCL-25') !== false;
$isVAK = strpos($testTypesTaken, 'VAK') !== false;
$isDISC = strpos($testTypesTaken, 'DISC') !== false;

// Ambil Jawaban User
$answersStmt = $db->prepare("
    SELECT q.id, q.question_text, q.category, q.test_type, ua.points_earned as answer_value
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    WHERE ua.token_id = (SELECT id FROM tokens WHERE token_code = ?)
    ORDER BY q.test_type, q.category, q.id ASC
");
$answersStmt->execute([$token]);
$answers = $answersStmt->fetchAll();

// Ambil Data Hasil (Waktu selesai, dll)
$resultStmt = $db->prepare("SELECT * FROM test_results WHERE token_id = (SELECT id FROM tokens WHERE token_code = ?)");
$resultStmt->execute([$token]);
$result = $resultStmt->fetch();

if (!$result) { echo "Result not found!"; exit(); }

// --- LOGIKA PERHITUNGAN SKOR ---

// 1. HSCL-25
$hscl_scores = ['Anxiety' => ['score' => 0, 'count' => 0], 'Depresi' => ['score' => 0, 'count' => 0]];
// 2. VAK
$vak_scores = ['Visual' => ['score' => 0, 'count' => 0], 'Auditory' => ['score' => 0, 'count' => 0], 'Kinesthetic' => ['score' => 0, 'count' => 0]];
// 3. DISC
$disc_scores = ['Dominance' => 0, 'Influence' => 0, 'Steadiness' => 0, 'Compliance' => 0];

foreach ($answers as $answer) {
    if (isset($answer['answer_value']) && $answer['answer_value']) {
        // Hitung HSCL
        if ($answer['test_type'] === 'HSCL-25' && isset($hscl_scores[$answer['category']])) {
            $hscl_scores[$answer['category']]['score'] += $answer['answer_value'];
            $hscl_scores[$answer['category']]['count']++;
        } 
        // Hitung VAK
        elseif ($answer['test_type'] === 'VAK' && isset($vak_scores[$answer['category']])) {
            $vak_scores[$answer['category']]['score'] += $answer['answer_value'];
            $vak_scores[$answer['category']]['count']++;
        }
        // Hitung DISC
        elseif ($answer['test_type'] === 'DISC' && isset($disc_scores[$answer['category']])) {
            $disc_scores[$answer['category']] += $answer['answer_value'];
        }
    }
}

// Hitung Rata-rata HSCL & VAK
foreach ($hscl_scores as &$item) { $item['average'] = $item['count'] > 0 ? $item['score'] / $item['count'] : 0; }
foreach ($vak_scores as &$item) { $item['average'] = $item['count'] > 0 ? $item['score'] / $item['count'] : 0; }

// Interpretasi HSCL
$cutoffPoint = 1.75;
$anxietyInterpretation = $hscl_scores['Anxiety']['average'] >= $cutoffPoint ? 'Ada' : 'Tidak';
$depressionInterpretation = $hscl_scores['Depresi']['average'] >= $cutoffPoint ? 'Ada' : 'Tidak';
$hasDisorder = ($hscl_scores['Anxiety']['average'] >= $cutoffPoint || $hscl_scores['Depresi']['average'] >= $cutoffPoint);

// Statistik Umum
$totalScore = 0; $totalCount = 0;
foreach ($answers as $answer) {
    if (isset($answer['answer_value']) && $answer['answer_value']) {
        $totalScore += $answer['answer_value'];
        $totalCount++;
    }
}
$averageScore = $totalCount > 0 ? $totalScore / $totalCount : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Tes - ABLE.ID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { background: #f5f7fa; }
        .result-container { max-width: 900px; margin: 0 auto; padding: 20px; }
        
        .result-header {
            background: white; border-radius: 12px; padding: 30px; margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); text-align: center;
        }
        .result-title { font-size: 24px; font-weight: 700; color: #333; margin-bottom: 8px; }
        .result-subtitle { font-size: 14px; color: #666; margin-bottom: 10px; }

        /* Icon spacing */
        .user-info-label i, .category-name i { margin-right: 8px; width: 20px; text-align: center; }

        /* Cards */
        .score-card, .answers-section, .interpretation-card {
            background: white; border-radius: 12px; padding: 30px; margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .score-card { border-left: 6px solid #667eea; }
        
        .category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .category-card { background: #f9fafb; padding: 16px; border-radius: 8px; border-left: 4px solid #667eea; }
        .category-name { font-weight: 600; color: #333; margin-bottom: 8px; }
        .category-score { font-size: 24px; font-weight: 700; color: #667eea; margin-bottom: 8px; }
        
        /* User Info (Admin Only) */
        .user-info { background: #f9fafb; padding: 16px; border-radius: 8px; margin-top: 20px; text-align: left; }
        .user-info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
        .user-info-label { font-weight: 600; color: #666; }

        /* Buttons */
        .actions { display: flex; gap: 12px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
        .btn {
            padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none;
            cursor: pointer; border: none; font-size: 14px; transition: all 0.3s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: white; color: #667eea; border: 2px solid #667eea; }

        @media print {
            .actions, .navbar { display: none !important; }
            .result-container { box-shadow: none; border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <span class="brand-icon"><i class="fas fa-shapes"></i></span>
                <span class="brand-text">ABLE.ID</span>
            </div>
            <div class="navbar-title">Hasil Tes</div>
        </div>
    </div>

    <div class="result-container">
        <div class="result-header">
            <div class="result-title">Tes Selesai!</div>
            <div class="result-subtitle">
                Terima kasih telah berpartisipasi dalam asesmen ini.
            </div>

            <?php if ($isAdmin): ?>
            <div class="user-info">
                <div class="user-info-row">
                    <span class="user-info-label"><i class="fas fa-user"></i> Nama Lengkap</span>
                    <span class="user-info-value"><?= htmlspecialchars($user['first_name'] ?? '') ?> <?= htmlspecialchars($user['last_name'] ?? '') ?></span>
                </div>
                <div class="user-info-row">
                    <span class="user-info-label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="user-info-value"><?= htmlspecialchars($user['email'] ?? '-') ?></span>
                </div>
                <div class="user-info-row">
                    <span class="user-info-label"><i class="fas fa-map-marker-alt"></i> Lokasi</span>
                    <span class="user-info-value"><?= htmlspecialchars($user['city'] ?? '-') ?></span>
                </div>
                <div class="user-info-row">
                    <span class="user-info-label"><i class="fas fa-calendar-alt"></i> Tanggal Test</span>
                    <span class="user-info-value"><?= date('d F Y H:i', strtotime($result['completed_at'])) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isAdmin): ?>
        <div class="score-card">
            <h3><i class="fas fa-chart-bar"></i> Ringkasan Skor (Admin View)</h3>
            <div style="font-size: 48px; font-weight: 700; color: #667eea; margin-bottom: 8px;">
                <?= number_format($averageScore, 2) ?>
            </div>
            <div style="font-size: 14px; color: #666;">Rata-rata Skor (skala 1-4)</div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 20px; text-align: center;">
                <div style="background: #f9fafb; padding: 12px; border-radius: 8px;">
                    <div style="font-weight: 700; color: #667eea;"><?= $totalCount ?></div>
                    <div style="font-size: 11px;">Terjawab</div>
                </div>
                <div style="background: #f9fafb; padding: 12px; border-radius: 8px;">
                    <div style="font-weight: 700; color: #667eea;"><?= $totalScore ?></div>
                    <div style="font-size: 11px;">Total Poin</div>
                </div>
                <div style="background: #f9fafb; padding: 12px; border-radius: 8px;">
                    <div style="font-weight: 700; color: #667eea;"><?= number_format(($result['percentage'] ?? 0), 1) ?>%</div>
                    <div style="font-size: 11px;">Persentase</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="interpretation-card">
            
            <?php if ($isHSCL): ?>
            <h3 style="margin-top: 0; text-align: center;"><i class="fas fa-heartbeat"></i> Kesehatan Mental (HSCL-25)</h3>
            <div class="category-grid">
                <div class="category-card">
                    <div class="category-name"><i class="fas fa-head-side-virus"></i> Anxiety</div>
                    <div class="category-score"><?= number_format($hscl_scores['Anxiety']['average'], 2) ?></div>
                    <?php if ($isAdmin): ?>
                    <span style="font-size: 12px; font-weight: 600; padding: 4px 8px; border-radius: 4px; background: <?= $anxietyInterpretation === 'Ada' ? '#fadbd8' : '#d5f4e6' ?>; color: <?= $anxietyInterpretation === 'Ada' ? '#e74c3c' : '#27ae60' ?>;">
                        Indikasi: <?= $anxietyInterpretation ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="category-card">
                    <div class="category-name"><i class="fas fa-cloud-rain"></i> Depresi</div>
                    <div class="category-score"><?= number_format($hscl_scores['Depresi']['average'], 2) ?></div>
                    <?php if ($isAdmin): ?>
                    <span style="font-size: 12px; font-weight: 600; padding: 4px 8px; border-radius: 4px; background: <?= $depressionInterpretation === 'Ada' ? '#fadbd8' : '#d5f4e6' ?>; color: <?= $depressionInterpretation === 'Ada' ? '#e74c3c' : '#27ae60' ?>;">
                        Indikasi: <?= $depressionInterpretation ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($hasDisorder && !$isAdmin): ?>
                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; text-align: center;">
                    <strong style="color: #856404;"><i class="fas fa-info-circle"></i> Catatan:</strong>
                    <p style="margin: 5px 0 0; font-size: 13px; color: #856404;">
                        Hasil ini menunjukkan skor di atas rata-rata. Disarankan untuk berkonsultasi dengan profesional.
                    </p>
                </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($isVAK): ?>
            <h3 style="margin-top: 24px; text-align: center;"><i class="fas fa-bullseye"></i> Gaya Belajar (VAK)</h3>
            <div class="category-grid">
                <div class="category-card">
                    <div class="category-name"><i class="fas fa-palette"></i> Visual</div>
                    <div class="category-score"><?= $vak_scores['Visual']['score'] ?></div>
                </div>
                <div class="category-card">
                    <div class="category-name"><i class="fas fa-headphones"></i> Auditory</div>
                    <div class="category-score"><?= $vak_scores['Auditory']['score'] ?></div>
                </div>
                <div class="category-card">
                    <div class="category-name"><i class="fas fa-running"></i> Kinesthetic</div>
                    <div class="category-score"><?= $vak_scores['Kinesthetic']['score'] ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isDISC): ?>
            <h3 style="margin-top: 24px; text-align: center;"><i class="fas fa-id-badge"></i> Kepribadian (DISC)</h3>
            <div class="category-grid">
                <?php foreach ($disc_scores as $type => $score): ?>
                <div class="category-card">
                    <div class="category-name"><?= $type ?></div>
                    <div class="category-score"><?= $score ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php 
            // Cari skor tertinggi
            $maxScore = -1;
            $dominantType = '';
            foreach($disc_scores as $type => $score) {
                if ($score > $maxScore) {
                    $maxScore = $score;
                    $dominantType = $type;
                }
            }
            ?>
            <div style="margin-top: 16px; padding: 15px; background: #eef2ff; border-radius: 8px; text-align: center;">
                <strong>Kepribadian Dominan Anda:</strong><br>
                <span style="color: #667eea; font-size: 20px; font-weight: 700; display: block; margin-top: 5px;">
                    <?= strtoupper($dominantType) ?>
                </span>
            </div>
            <?php endif; ?>

        </div>

        <?php if ($isAdmin): ?>
        <div class="answers-section">
            <h3><i class="fas fa-list"></i> Detail Jawaban (Admin Only)</h3>
            <table class="answers-table">
                <thead>
                    <tr style="background: #f9fafb; border-bottom: 2px solid #e0e0e0;">
                        <th style="padding: 10px; text-align: left;">No</th>
                        <th style="padding: 10px; text-align: left;">Kategori</th>
                        <th style="padding: 10px; text-align: left;">Pertanyaan</th>
                        <th style="padding: 10px; text-align: left;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; foreach ($answers as $answer): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><?= $no++ ?></td>
                        <td style="padding: 10px;"><?= $answer['category'] ?></td>
                        <td style="padding: 10px; font-size: 13px;"><?= htmlspecialchars($answer['question_text']) ?></td>
                        <td style="padding: 10px; font-weight: 600; color: #667eea;"><?= $answer['answer_value'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="actions" data-html2canvas-ignore="true">
            <?php if ($isAdmin): ?>
                <!-- <a href="admin/view-results.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
                <button onclick="window.close()" class="btn btn-secondary"><i class="fas fa-times"></i> Tutup</button>
-->
            <a href="admin/view-results.php" class="btn btn-secondary"><i class="fas fa-times"></i> Tutup</a>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                    <button onclick="downloadAsPDF()" class="btn btn-primary" style="padding: 14px 30px; font-size: 16px; margin-bottom: 15px;">
                        <i class="fas fa-file-pdf"></i> Download Hasil (PDF)
                    </button>
                    
                    <p style="text-align: center; color: #666; font-size: 14px; margin: 0;">
                        Terima kasih telah mengerjakan tes.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function downloadAsPDF() {
            const element = document.querySelector('.result-container');
            const opt = {
                margin: 10,
                filename: 'hasil-tes.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
        </script>
    </div>
</body>
</html>
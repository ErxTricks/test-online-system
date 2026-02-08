<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Check if admin is accessing this (with token_id parameter)
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
$tokenId = null;
$token = null;

if ($isAdmin && isset($_GET['token_id'])) {
    // Admin accessing specific token result
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
    // User accessing their own result
    $token = $_SESSION['token_code'];
} else {
    header('Location: index.php');
    exit();
}

$db = getDBConnection();

// Get user profile
$userStmt = $db->prepare("SELECT * FROM user_profiles WHERE token_id = (SELECT id FROM tokens WHERE token_code = ?)");
$userStmt->execute([$token]);
$user = $userStmt->fetch();

// Ensure $user is an array, not false
if (!$user) {
    $user = [];
}

// Get token info to determine test types taken
$tokenInfoStmt = $db->prepare("SELECT selected_test_types FROM tokens WHERE token_code = ?");
$tokenInfoStmt->execute([$token]);
$tokenInfo = $tokenInfoStmt->fetch();
$testTypesTaken = $tokenInfo['selected_test_types'] ?? 'HSCL-25';
$isHSCL = strpos($testTypesTaken, 'HSCL-25') !== false;
$isVAK = strpos($testTypesTaken, 'VAK') !== false;

// Get all user answers
$answersStmt = $db->prepare("
    SELECT
        q.id,
        q.question_text,
        q.category,
        q.test_type,
        ua.points_earned as answer_value
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    WHERE ua.token_id = (SELECT id FROM tokens WHERE token_code = ?)
    ORDER BY q.test_type, q.category, q.id ASC
");
$answersStmt->execute([$token]);
$answers = $answersStmt->fetchAll();

// Get test result
$resultStmt = $db->prepare("
    SELECT * FROM test_results 
    WHERE token_id = (SELECT id FROM tokens WHERE token_code = ?)
");
$resultStmt->execute([$token]);
$result = $resultStmt->fetch();

if (!$result) {
    echo "Result not found!";
    exit();
}

// Calculate scores by test type and category
$hscl_scores = [
    'Anxiety' => ['score' => 0, 'count' => 0],
    'Depresi' => ['score' => 0, 'count' => 0]
];

$vak_scores = [
    'Visual' => ['score' => 0, 'count' => 0],
    'Auditory' => ['score' => 0, 'count' => 0],
    'Kinesthetic' => ['score' => 0, 'count' => 0]
];

foreach ($answers as $answer) {
    if (isset($answer['answer_value']) && $answer['answer_value']) {
        if ($answer['test_type'] === 'HSCL-25' && isset($hscl_scores[$answer['category']])) {
            $hscl_scores[$answer['category']]['score'] += $answer['answer_value'];
            $hscl_scores[$answer['category']]['count']++;
        } elseif ($answer['test_type'] === 'VAK' && isset($vak_scores[$answer['category']])) {
            $vak_scores[$answer['category']]['score'] += $answer['answer_value'];
            $vak_scores[$answer['category']]['count']++;
        }
    }
}

// Calculate averages
foreach ($hscl_scores as &$item) {
    $item['average'] = $item['count'] > 0 ? $item['score'] / $item['count'] : 0;
}
foreach ($vak_scores as &$item) {
    $item['average'] = $item['count'] > 0 ? $item['score'] / $item['count'] : 0;
}

// Interpretation for HSCL-25
$cutoffPoint = 1.75;
$anxietyInterpretation = $hscl_scores['Anxiety']['average'] >= $cutoffPoint ? 'Ada' : 'Tidak';
$depressionInterpretation = $hscl_scores['Depresi']['average'] >= $cutoffPoint ? 'Ada' : 'Tidak';

$hasDisorder = ($hscl_scores['Anxiety']['average'] >= $cutoffPoint || $hscl_scores['Depresi']['average'] >= $cutoffPoint);
if ($isHSCL && !$isVAK) {
    $interpretation = $hasDisorder ? 'Kemungkinan Gangguan Kesehatan Mental' : 'Tidak Ada Indikasi Gangguan';
    $interpretationColor = $hasDisorder ? '#e74c3c' : '#27ae60';
    $interpretationBg = $hasDisorder ? '#fadbd8' : '#d5f4e6';
} else if ($isVAK && !$isHSCL) {
    $interpretation = 'Hasil VAK Style Learning Assessment';
    $interpretationColor = '#667eea';
    $interpretationBg = '#eef2ff';
} else {
    $interpretation = 'Hasil Tes Selesai';
    $interpretationColor = '#667eea';
    $interpretationBg = '#eef2ff';
}

// Calculate overall average
$totalScore = 0;
$totalCount = 0;
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
    <title>Hasil HSCL-25 - ABLE.ID</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #f5f7fa;
        }

        .result-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .result-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .result-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .result-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 24px;
        }

        .user-info {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            text-align: left;
        }

        .user-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .user-info-row:last-child {
            border-bottom: none;
        }

        .user-info-label {
            font-weight: 600;
            color: #666;
        }

        .user-info-value {
            color: #333;
        }

        /* Score Card */
        .score-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-left: 6px solid #667eea;
        }

        .score-card h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: #333;
        }

        .score-value {
            font-size: 48px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }

        .score-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        .score-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .score-item {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        .score-item-value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }

        .score-item-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }

        /* Interpretation Card */
        .interpretation-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .interpretation-box {
            background: <?= $interpretationBg ?>;
            border: 2px solid <?= $interpretationColor ?>;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            margin-bottom: 20px;
        }

        .interpretation-title {
            font-size: 18px;
            font-weight: 700;
            color: <?= $interpretationColor ?>;
            margin-bottom: 8px;
        }

        .interpretation-text {
            font-size: 14px;
            color: #333;
            margin-bottom: 4px;
        }

        .interpretation-note {
            font-size: 12px;
            color: #666;
            margin-top: 12px;
            font-style: italic;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .category-card {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .category-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .category-score {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }

        .category-interpretation {
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 4px;
            display: inline-block;
        }

        /* Answers Table */
        .answers-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .answers-section h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: #333;
        }

        .answers-table {
            width: 100%;
            border-collapse: collapse;
        }

        .answers-table thead tr {
            background: #f9fafb;
            border-bottom: 2px solid #e0e0e0;
        }

        .answers-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }

        .answers-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
            color: #666;
        }

        .answers-table tr:hover {
            background: #f9fafb;
        }

        .answer-value {
            font-weight: 600;
            color: #667eea;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #f5f7ff;
        }

        @media (max-width: 768px) {
            .result-container {
                padding: 12px;
            }

            .score-card,
            .interpretation-card,
            .answers-section {
                padding: 20px;
            }

            .score-value {
                font-size: 36px;
            }

            .answers-table {
                font-size: 12px;
            }

            .answers-table th,
            .answers-table td {
                padding: 8px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <span class="brand-icon">◆</span>
                <span class="brand-text">ABLE.ID</span>
            </div>
            <div class="navbar-title">Hasil Tes<?= $isHSCL ? ' HSCL-25' : '' ?><?= ($isHSCL && $isVAK) ? ' + ' : '' ?><?= $isVAK ? ' VAK' : '' ?></div>
        </div>
    </div>

    <div class="result-container">
        <!-- Header -->
        <div class="result-header">
            <div class="result-title">Tes Selesai!</div>
            <div class="result-subtitle">
                <?php echo $isHSCL && $isVAK ? 'Berikut adalah hasil HSCL-25 dan VAK Learning Style Anda' : ($isVAK ? 'Berikut adalah hasil VAK Learning Style Anda' : 'Berikut adalah hasil HSCL-25 Mental Health Screening Anda'); ?>
            </div>

            <?php if ($isAdmin): ?>
            <div class="user-info">
                <div class="user-info-row">
                    <span class="user-info-label">👤 Nama Lengkap</span>
                    <span class="user-info-value">
                        <?= htmlspecialchars($user['first_name'] ?? '') ?> <?= htmlspecialchars($user['last_name'] ?? '') ?>
                    </span>
                </div>
                <div class="user-info-row">

                    <span class="user-info-label">📧 Email</span>
                    <span class="user-info-value"><?= htmlspecialchars($user['email'] ?? '-') ?></span>
                </div>
                <div class="user-info-row">
                    <span class="user-info-label">📍 Lokasi</span>
                    <span class="user-info-value">
                        <?= htmlspecialchars($user['city'] ?? '-') ?>, <?= htmlspecialchars($user['district'] ?? '-') ?>
                    </span>
                </div>
                <div class="user-info-row">
                    <span class="user-info-label">📅 Tanggal Test</span>
                    <span class="user-info-value">
                        <?= date('d F Y H:i', strtotime($result['completed_at'])) ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Score Summary (For Admin Only) -->
        <div class="score-card">
            <h3>📊 Ringkasan Skor</h3>
            <div class="score-value"><?= number_format($averageScore, 2) ?></div>
            <div class="score-label">Rata-rata Skor (skala 1-4)</div>
            
            <div class="score-grid">
                <div class="score-item">
                    <div class="score-item-value"><?= $totalCount ?></div>
                    <div class="score-item-label">Pertanyaan Terjawab</div>
                </div>
                <div class="score-item">
                    <div class="score-item-value"><?= $totalScore ?></div>
                    <div class="score-item-label">Total Poin</div>
                </div>
                <div class="score-item">
                    <div class="score-item-value"><?= number_format(($result['percentage'] ?? 0), 1) ?>%</div>
                    <div class="score-item-label">Persentase</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Interpretation -->
        <div class="interpretation-card">
            
            <?php if ($isHSCL): ?>
            <!-- HSCL-25 Category Scores -->
            <h3 style="margin-top: 0;">Skor Berdasarkan Kategori</h3>
            <div class="category-grid">
                <div class="category-card">
                    <div class="category-name">Anxiety (Kecemasan)</div>
                    <div class="category-score"><?= number_format($hscl_scores['Anxiety']['average'], 2) ?></div>
                    <?php if ($isAdmin): ?>
                    <span class="category-interpretation" style="background: <?= $anxietyInterpretation === 'Ada' ? '#fadbd8' : '#d5f4e6' ?>; color: <?= $anxietyInterpretation === 'Ada' ? '#e74c3c' : '#27ae60' ?>;">
                        Indikasi: <?= $anxietyInterpretation ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="category-card">
                    <div class="category-name">Depresi</div>
                    <div class="category-score"><?= number_format($hscl_scores['Depresi']['average'], 2) ?></div>
                    <?php if ($isAdmin): ?>
                    <span class="category-interpretation" style="background: <?= $depressionInterpretation === 'Ada' ? '#fadbd8' : '#d5f4e6' ?>; color: <?= $depressionInterpretation === 'Ada' ? '#e74c3c' : '#27ae60' ?>;">
                        Indikasi: <?= $depressionInterpretation ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isAdmin): ?>
            <div class="interpretation-box" style="margin-top: 24px;">
                <div class="interpretation-title">
                    ⚠️ Perhatian Diperlukan
                </div>
                <div class="interpretation-text">
                    <strong>Kemungkinan Gangguan Kesehatan Mental</strong>
                </div>
                <div class="interpretation-text">
                    Skor Anda mencapai atau melebihi nilai cut-off (<?= $cutoffPoint ?>), 
                    yang menunjukkan kemungkinan adanya gejala gangguan kesehatan mental.
                </div>
                <div class="interpretation-note">
                    ⓘ Hasil ini bersifat screening awal. Untuk diagnosis lebih lanjut, 
                    silahkan berkonsultasi dengan profesional kesehatan mental.
                </div>
            </div>
            <?php else: ?>
            <!-- User View - Warning Box -->
            <?php if ($hasDisorder): ?>
            <div class="interpretation-box" style="margin-top: 24px;">
                <div class="interpretation-title" style="color: #e74c3c;">
                    Perhatian Diperlukan
                </div>
                <div class="interpretation-text" style="color: #333;">
                    <strong>Kemungkinan Gangguan Kesehatan Mental</strong>
                </div>
                <div class="interpretation-text" style="color: #666; font-size: 13px;">
                    Skor Anda mencapai atau melebihi nilai cut-off, yang menunjukkan kemungkinan adanya gejala gangguan kesehatan mental.
                </div>
                <div class="interpretation-note" style="color: #666;">
                    Hasil ini bersifat screening awal. Untuk diagnosis lebih lanjut, silahkan berkonsultasi dengan profesional kesehatan mental.
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($isVAK): ?>
            <div class="interpretation-box">
                <div class="interpretation-title">🎯 Gaya Belajar Anda</div>
                <div class="interpretation-text">
                    <strong>Identifikasi Learning Style Berdasarkan VAK Assessment</strong>
                </div>
                <div class="interpretation-note">
                    Tes VAK membantu mengidentifikasi preferensi gaya belajar Anda, yang dapat membantu optimalisasi proses pembelajaran.
                </div>

                <!-- VAK Category Scores -->
                <div class="category-grid" style="margin-top: 20px;">
                    <div class="category-card">
                        <div class="category-name">🎨 Visual</div>
                        <div class="category-score"><?= $vak_scores['Visual']['score'] ?></div>
                    </div>
                    <div class="category-card">
                        <div class="category-name">🎧 Auditory</div>
                        <div class="category-score"><?= $vak_scores['Auditory']['score'] ?></div>
                    </div>
                    <div class="category-card">
                        <div class="category-name">🏃 Kinesthetic</div>
                        <div class="category-score"><?= $vak_scores['Kinesthetic']['score'] ?></div>
                    </div>
                </div>

                <?php 
                // Determine dominant learning style
                $vakScores = [
                    'Visual' => $vak_scores['Visual']['score'],
                    'Auditory' => $vak_scores['Auditory']['score'],
                    'Kinesthetic' => $vak_scores['Kinesthetic']['score']
                ];
                $dominantStyle = array_key_first($vakScores) ? array_keys($vakScores, max($vakScores))[0] : 'Seimbang';
                ?>
                <div style="margin-top: 16px; padding: 12px; background: rgba(102, 126, 234, 0.1); border-radius: 6px;">
                    <strong>Gaya Belajar Dominan:</strong> <span style="color: #667eea; font-size: 14px; font-weight: 700;"><?= $dominantStyle ?></span>
                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">
                        Anda cenderung lebih efektif belajar melalui metode pembelajaran yang menekankan gaya <?= strtolower($dominantStyle) ?>.
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Detailed Answers (Admin Only) -->
        <?php if ($isAdmin): ?>
        <div class="answers-section">
            <h3>📋 Detail Jawaban</h3>
            <table class="answers-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 10%;">Kategori</th>
                        <th style="width: 60%;">Pertanyaan</th>
                        <th style="width: 25%;">Jawaban</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $answerLabels = [
                        1 => 'Tidak sama sekali (1)',
                        2 => 'Sedikit (2)',
                        3 => 'Cukup banyak (3)',
                        4 => 'Sangat banyak (4)'
                    ];
                    ?>
                    <?php foreach ($answers as $answer): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;
                                <?= $answer['category'] === 'Anxiety' ? 'background: #fdeaea; color: #c0392b;' : 'background: #eafaf1; color: #27ae60;' ?>">
                                <?= $answer['category'] === 'Anxiety' ? '😰' : '😢' ?> <?= $answer['category'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($answer['question_text']) ?></td>
                        <td>
                            <span class="answer-value">
                                <?= $answerLabels[$answer['answer_value']] ?? '-' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="actions">
            <?php if ($isAdmin): ?>
                <a href="admin/view-results.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                <button onclick="window.close()" class="btn btn-secondary" title="Tutup jendela">Tutup</button>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            <?php else: ?>
                <p style="text-align: center; color: #666; font-size: 14px; margin-bottom: 20px;">
                    Terima kasih telah mengikuti tes. Hasil Anda telah disimpan untuk keperluan screening kesehatan mental.
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="downloadAsPDF()" class="btn btn-primary">Download PDF</button>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function downloadAsPDF() {
            const element = document.querySelector('.result-container');
            
            // Try using html2pdf library if available
            if (typeof html2pdf !== 'undefined') {
                const opt = {
                    margin: 10,
                    filename: 'hasil-tes-<?= date('Y-m-d-H-i-s') ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
                };
                html2pdf().set(opt).from(element).save();
            } else {
                // Fallback: print dialog
                window.print();
            }
        }
        </script>
    </div>
</body>
</html>

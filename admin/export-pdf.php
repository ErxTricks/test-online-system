<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if admin is logged in
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
if (!$isAdmin) {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['token_id'])) {
    exit('Token ID tidak ditemukan');
}

$tokenId = intval($_GET['token_id']);
$db = getDBConnection();

// Get user profile and test results
$stmt = $db->prepare("
    SELECT
        tr.*,
        t.user_name,
        t.user_email,
        up.*
    FROM test_results tr
    JOIN tokens t ON tr.token_id = t.id
    LEFT JOIN user_profiles up ON tr.token_id = up.token_id
    WHERE tr.token_id = ?
");
$stmt->execute([$tokenId]);
$result = $stmt->fetch();

if (!$result) {
    exit('Data test tidak ditemukan');
}

// Get all answers
$answersStmt = $db->prepare("
    SELECT
        q.id,
        q.question_text,
        q.category,
        q.test_type,
        ua.points_earned as answer_value
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    WHERE ua.token_id = ?
    ORDER BY q.test_type, q.category, q.id ASC
");
$answersStmt->execute([$tokenId]);
$answers = $answersStmt->fetchAll();

// Calculate category scores
$categoryScores = [
    'Anxiety' => ['score' => 0, 'count' => 0],
    'Depresi' => ['score' => 0, 'count' => 0]
];

foreach ($answers as $answer) {
    if (isset($answer['answer_value']) && $answer['answer_value'] && isset($categoryScores[$answer['category']])) {
        $categoryScores[$answer['category']]['score'] += $answer['answer_value'];
        $categoryScores[$answer['category']]['count']++;
    }
}

$answerLabels = [
    1 => 'Tidak sama sekali (1)',
    2 => 'Sedikit (2)',
    3 => 'Cukup banyak (3)',
    4 => 'Sangat banyak (4)'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Test - <?= htmlspecialchars($result['user_name']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #667eea;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-section h3 {
            color: #333;
            border-left: 4px solid #667eea;
            padding-left: 12px;
            margin: 0 0 15px 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: #f9fafb;
            border-radius: 4px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .info-value {
            color: #333;
        }
        .score-display {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 15px;
        }
        .category-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .category-name {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .category-score {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            .container {
                box-shadow: none;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
        }
        .print-button {
            text-align: right;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="print-button no-print">
        <button class="btn" onclick="window.print()">🖨️ Cetak PDF</button>
        <button class="btn" onclick="window.close()" style="margin-left: 10px; background: #999;">❌ Tutup</button>
    </div>

    <div class="container">
        <div class="header">
            <h1>HASIL TEST HSCL-25</h1>
            <p>Mental Health Screening Assessment</p>
        </div>

        <!-- Informasi Peserta -->
        <div class="info-section">
            <h3>📋 Informasi Peserta</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nama Peserta:</span>
                    <span class="info-value"><?= htmlspecialchars($result['user_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($result['user_email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Nama Lengkap:</span>
                    <span class="info-value"><?= htmlspecialchars_decode($result['first_name'] ?? '') . ' ' . htmlspecialchars_decode($result['last_name'] ?? '') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Kota:</span>
                    <span class="info-value"><?= htmlspecialchars($result['city'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Jenis Kelamin:</span>
                    <span class="info-value"><?= htmlspecialchars($result['gender'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tanggal Test:</span>
                    <span class="info-value"><?= date('d F Y H:i', strtotime($result['completed_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Ringkasan Hasil -->
        <div class="info-section">
            <h3>📊 Ringkasan Hasil</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Total Skor:</span>
                    <span class="info-value"><?= $result['total_score'] ?> / <?= $result['max_score'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Persentase:</span>
                    <span class="info-value"><?= number_format($result['percentage'], 2) ?>%</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Soal:</span>
                    <span class="info-value"><?= $result['total_questions'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Soal Terjawab:</span>
                    <span class="info-value"><?= $result['answered_questions'] ?></span>
                </div>
            </div>
        </div>

        <!-- Skor Berdasarkan Kategori -->
        <div class="info-section">
            <h3>📈 Skor Berdasarkan Kategori</h3>
            <div class="category-grid">
                <?php foreach ($categoryScores as $category => $data): 
                    $avg = $data['count'] > 0 ? $data['score'] / $data['count'] : 0;
                ?>
                <div class="category-card">
                    <div class="category-name"><?= $category ?></div>
                    <div class="category-score"><?= number_format($avg, 2) ?></div>
                    <div style="font-size: 12px;">Rata-rata Score</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Detail Jawaban -->
        <div class="info-section">
            <h3>📝 Detail Jawaban</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 15%;">Kategori</th>
                        <th style="width: 60%;">Pertanyaan</th>
                        <th style="width: 20%;">Jawaban</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($answers as $answer): 
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($answer['category']) ?></td>
                        <td><?= htmlspecialchars($answer['question_text']) ?></td>
                        <td><?= htmlspecialchars($answerLabels[$answer['answer_value']] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>Hasil ini bersifat screening awal. Untuk diagnosis lebih lanjut, silahkan berkonsultasi dengan profesional kesehatan mental.</p>
            <p>Laporan ini digenerate oleh ABLE.ID Test System</p>
            <p><?= date('d F Y H:i') ?></p>
        </div>
    </div>
</body>
</html>

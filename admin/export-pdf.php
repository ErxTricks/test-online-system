<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if admin is logged in
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
if (!$isAdmin) {
    header('Location: ../admin-login.php');
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
        t.token_code,
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
    <title>Laporan Hasil Test - <?= htmlspecialchars($result['user_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        body {
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background: #e9ecef;
            color: #333;
        }

        .paper {
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            background: white;
            padding: 40px 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
        }

        /* HEADER LAPORAN */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .brand-section h1 {
            margin: 0;
            font-size: 24px;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .brand-section p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #666;
        }

        .report-meta {
            text-align: right;
        }

        .report-meta div {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .report-meta .token {
            font-family: monospace;
            font-weight: 700;
            font-size: 16px;
            color: #333;
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* SECTION STYLING */
        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #667eea;
            text-transform: uppercase;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* INFO GRID */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 4px;
        }

        /* SUMMARY CARDS */
        .summary-box {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .score-highlight {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .score-highlight .label { font-size: 12px; opacity: 0.9; margin-bottom: 5px; }
        .score-highlight .value { font-size: 32px; font-weight: 700; }
        .score-highlight .sub { font-size: 11px; opacity: 0.8; }

        .stats-detail {
            flex: 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .stat-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-card .val { font-size: 18px; font-weight: 700; color: #333; }
        .stat-card .lbl { font-size: 11px; color: #666; margin-top: 4px; }

        /* TABLES */
        .answers-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .answers-table th {
            background: #667eea;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
        }

        .answers-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #444;
        }

        .answers-table tr:nth-child(even) { background: #fcfcfc; }
        .answers-table tr:hover { background: #f0f4ff; }

        .cat-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
        }
        .cat-anxiety { background: #fee2e2; color: #991b1b; }
        .cat-depresi { background: #e0f2fe; color: #075985; }

        /* FOOTER */
        .report-footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        /* PRINT CONTROL */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-print { background: #667eea; color: white; }
        .btn-print:hover { background: #5a67d8; }
        
        .btn-back { background: #e2e8f0; color: #4a5568; }
        .btn-back:hover { background: #cbd5e0; }

        @media print {
            body { background: white; padding: 0; }
            .paper { box-shadow: none; padding: 20px; max-width: 100%; }
            .no-print { display: none !important; }
            .info-value { border-bottom: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Cetak / Simpan PDF
        </button>
        <a href="view-results.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="paper">
        <div class="report-header">
            <div class="brand-section">
                <h1><i class="fas fa-shapes"></i> ABLE.ID</h1>
                <p>Assessment & Learning Platform</p>
            </div>
            <div class="report-meta">
                <div>TOKEN TEST</div>
                <div class="token"><?= htmlspecialchars($result['token_code']) ?></div>
                <div style="margin-top: 5px;"><?= date('d F Y', strtotime($result['completed_at'])) ?></div>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-user"></i> Profil Peserta</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nama Lengkap</span>
                <span class="info-value">
                    <?= htmlspecialchars_decode($result['first_name'] ?? '') . ' ' . htmlspecialchars_decode($result['last_name'] ?? '') ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($result['user_email']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lokasi</span>
                <span class="info-value">
                    <?= htmlspecialchars($result['city'] ?? '-') ?>, <?= htmlspecialchars($result['district'] ?? '') ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Jenis Kelamin</span>
                <span class="info-value">
                    <?= htmlspecialchars($result['gender'] === 'L' ? 'Laki-laki' : ($result['gender'] === 'P' ? 'Perempuan' : '-')) ?>
                </span>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-chart-pie"></i> Ringkasan Hasil</div>
        <div class="summary-box">
            <div class="score-highlight">
                <div class="label">TOTAL SKOR</div>
                <div class="value"><?= number_format($result['percentage'], 1) ?>%</div>
                <div class="sub"><?= $result['total_score'] ?> dari <?= $result['max_score'] ?> Poin</div>
            </div>
            <div class="stats-detail">
                <div class="stat-card">
                    <div class="val"><?= $result['total_questions'] ?></div>
                    <div class="lbl">Total Soal</div>
                </div>
                <div class="stat-card">
                    <div class="val"><?= $result['answered_questions'] ?></div>
                    <div class="lbl">Terjawab</div>
                </div>
                <?php foreach ($categoryScores as $cat => $data): 
                    $avg = $data['count'] > 0 ? $data['score'] / $data['count'] : 0;
                ?>
                <div class="stat-card">
                    <div class="val" style="color: #667eea;"><?= number_format($avg, 2) ?></div>
                    <div class="lbl">Rata-rata <?= $cat ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-list-alt"></i> Detail Jawaban</div>
        <table class="answers-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Kategori</th>
                    <th width="65%">Pertanyaan</th>
                    <th width="15%">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($answers as $answer): 
                    $catClass = ($answer['category'] == 'Anxiety') ? 'cat-anxiety' : 'cat-depresi';
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td>
                        <span class="cat-badge <?= $catClass ?>"><?= htmlspecialchars($answer['category']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($answer['question_text']) ?></td>
                    <td><strong><?= htmlspecialchars($answer['answer_value']) ?></strong> <span style="color:#999; font-size:10px;">/ 4</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="report-footer">
            Dokumen ini digenerate secara otomatis oleh sistem ABLE.ID.<br>
            Hasil ini merupakan indikasi awal (screening) dan bukan diagnosa klinis mutlak.
        </div>
    </div>

</body>
</html>
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

// Create Excel file using CSV format
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="hasil-test-' . htmlspecialchars($result['user_name']) . '-' . date('Y-m-d-His') . '.csv"');

// Create output
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

// Header information
fputcsv($output, ['HASIL TEST HSCL-25 MENTAL HEALTH SCREENING']);
fputcsv($output, []);

// User Information
fputcsv($output, ['Informasi Peserta']);
fputcsv($output, ['Nama', htmlspecialchars_decode($result['user_name'])]);
fputcsv($output, ['Email', $result['user_email']]);
fputcsv($output, ['Nama Lengkap', (htmlspecialchars_decode($result['first_name'] ?? '') . ' ' . htmlspecialchars_decode($result['last_name'] ?? ''))]);
fputcsv($output, ['Kota', $result['city'] ?? '-']);
fputcsv($output, ['Jenis Kelamin', $result['gender'] ?? '-']);
fputcsv($output, ['Tanggal Test', date('d F Y H:i', strtotime($result['completed_at']))]);
fputcsv($output, []);

// Test Results Summary
fputcsv($output, ['Ringkasan Hasil']);
fputcsv($output, ['Total Skor', $result['total_score']]);
fputcsv($output, ['Skor Maksimal', $result['max_score']]);
fputcsv($output, ['Persentase', number_format($result['percentage'], 2) . '%']);
fputcsv($output, ['Total Soal', $result['total_questions']]);
fputcsv($output, []);

// Category Scores
fputcsv($output, ['Skor Berdasarkan Kategori']);
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

foreach ($categoryScores as $category => $data) {
    $avg = $data['count'] > 0 ? number_format($data['score'] / $data['count'], 2) : '0.00';
    fputcsv($output, [$category, $avg]);
}
fputcsv($output, []);

// Detailed Answers
fputcsv($output, ['Detail Jawaban']);
fputcsv($output, ['No', 'Kategori', 'Pertanyaan', 'Jawaban (Score)']);

$answerLabels = [
    1 => 'Tidak sama sekali (1)',
    2 => 'Sedikit (2)',
    3 => 'Cukup banyak (3)',
    4 => 'Sangat banyak (4)'
];

$no = 1;
foreach ($answers as $answer) {
    fputcsv($output, [
        $no++,
        $answer['category'],
        htmlspecialchars_decode($answer['question_text']),
        $answerLabels[$answer['answer_value']] ?? '-'
    ]);
}

fclose($output);
exit();
?>

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

$db = getDBConnection();

// Get all test results
$stmt = $db->query("
    SELECT
        tr.*,
        t.user_name,
        t.user_email,
        up.city,
        CONCAT(up.first_name, ' ', COALESCE(up.last_name, '')) as full_name
    FROM test_results tr
    JOIN tokens t ON tr.token_id = t.id
    LEFT JOIN user_profiles up ON tr.token_id = up.token_id
    ORDER BY tr.completed_at DESC
");
$results = $stmt->fetchAll();

// Create Excel file using CSV format (compatible with Excel)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="hasil-test-' . date('Y-m-d-His') . '.csv"');

// Create output
$output = fopen('php://output', 'w');

// Set BOM for UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
$headers = [
    'Nama',
    'Email',
    'Kota',
    'Skor',
    'Skor Maksimal',
    'Persentase (%)',
    'Total Soal',
    'Tanggal Test',
    'Status'
];
fputcsv($output, $headers);

// Add data
foreach ($results as $result) {
    $isConcern = $result['percentage'] >= 70;
    $status = $isConcern ? 'Perhatian' : 'Baik';
    
    $data = [
        htmlspecialchars_decode($result['full_name'] ?: $result['user_name']),
        $result['user_email'],
        $result['city'] ?: '-',
        $result['total_score'],
        $result['max_score'],
        number_format($result['percentage'], 2),
        $result['total_questions'],
        date('d/m/Y H:i', strtotime($result['completed_at'])),
        $status
    ];
    fputcsv($output, $data);
}

fclose($output);
exit();
?>

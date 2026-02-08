<?php
require_once 'config/database.php';

$db = getDBConnection();

echo "=== Database Schema Verification ===\n\n";

// 1. Check and add test_types_taken to test_results
$result = $db->query("DESCRIBE test_results");
$columns = [];
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['Field'];
}

if (!in_array('test_types_taken', $columns)) {
    echo "Adding test_types_taken column to test_results...\n";
    $db->exec("ALTER TABLE test_results ADD COLUMN test_types_taken VARCHAR(255) DEFAULT 'HSCL-25'");
    echo "✅ Column added successfully!\n";
} else {
    echo "✅ test_types_taken column already exists\n";
}

// 2. Check and add test_type to questions
$result = $db->query("DESCRIBE questions");
$columns = [];
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['Field'];
}

if (!in_array('test_type', $columns)) {
    echo "\nAdding test_type column to questions...\n";
    $db->exec("ALTER TABLE questions ADD COLUMN test_type VARCHAR(50) DEFAULT 'HSCL-25'");
    echo "✅ Column added successfully!\n";
    
    // Set HSCL-25 for questions 1-25
    $db->exec("UPDATE questions SET test_type = 'HSCL-25' WHERE id <= 25");
    echo "✅ Set HSCL-25 for first 25 questions\n";
} else {
    echo "\n✅ test_type column already exists\n";
}

// 3. Check and add selected_test_types to tokens
$result = $db->query("DESCRIBE tokens");
$columns = [];
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['Field'];
}

if (!in_array('selected_test_types', $columns)) {
    echo "\nAdding selected_test_types column to tokens...\n";
    $db->exec("ALTER TABLE tokens ADD COLUMN selected_test_types VARCHAR(255) DEFAULT 'HSCL-25'");
    echo "✅ Column added successfully!\n";
} else {
    echo "\n✅ selected_test_types column already exists\n";
}

// 4. Check VAK questions
echo "\n=== Question Distribution ===\n";
$result = $db->query('SELECT test_type, COUNT(*) as total FROM questions GROUP BY test_type');
$rows = $result->fetchAll();

foreach($rows as $row) {
    echo $row['test_type'] . ': ' . $row['total'] . " questions\n";
}

// 5. Check if VAK questions need to be inserted
$vakCount = $db->query("SELECT COUNT(*) as total FROM questions WHERE test_type = 'VAK'")->fetch();
if ($vakCount['total'] == 0) {
    echo "\n=== Inserting VAK Questions ===\n";
    
    $vak_questions = [
        // Visual
        ['Saya lebih suka belajar melalui gambar, diagram, dan video', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        ['Saya mudah mengingat informasi jika ditulis atau divisualisasikan', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        ['Dalam presentasi, saya lebih memperhatikan slide dan visual aids', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        ['Saya suka membuat catatan dengan warna-warna cerah dan diagram', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        ['Saya bisa membayangkan tempat atau lokasi dengan detail visual', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        ['Peta dan diagram membantu saya memahami sesuatu dengan lebih baik', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        ['Saya lebih suka membaca daripada mendengarkan penjelasan lisan', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        ['Saya mencatat hal-hal penting saat orang lain berbicara', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Visual'],
        
        // Auditory
        ['Saya lebih suka belajar dengan mendengarkan penjelasan atau diskusi', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        ['Saya mudah mengingat hal-hal yang saya dengar', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        ['Saya suka berdiskusi dan berbicara tentang topik pembelajaran', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        ['Musik membantu saya berkonsentrasi saat belajar', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        ['Saya suka mendengarkan podcast atau audio book', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        ['Saya lebih suka instruksi lisan daripada instruksi tertulis', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        ['Saya suka menjelaskan hal-hal kepada orang lain secara lisan', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        ['Saya mudah terganggu oleh suara saat belajar', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Auditory'],
        
        // Kinesthetic
        ['Saya lebih suka belajar dengan melakukan atau praktik langsung', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
        ['Saya mudah belajar saat bergerak atau hands-on activities', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
        ['Saya suka mengerjakan proyek atau simulasi', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
        ['Saya tidak nyaman hanya duduk dan mendengarkan lama-lama', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
        ['Saya suka belajar sambil berjalan-jalan atau bergerak', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
        ['Saya lebih memahami sesuatu setelah mencobanya sendiri', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
        ['Saya lebih suka bermain olahraga atau aktivitas fisik', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
        ['Saya menggerakkan tangan saat berbicara', 'Sangat Setuju', 'Setuju', 'Kurang Setuju', 'Tidak Setuju', 'Kinesthetic'],
    ];
    
    $inserted = 0;
    foreach ($vak_questions as $q) {
        try {
            $stmt = $db->prepare("
                INSERT INTO questions 
                (question_text, option_a, option_b, option_c, option_d, 
                 point_a, point_b, point_c, point_d, category, test_type)
                VALUES (?, ?, ?, ?, ?, 4, 3, 2, 1, ?, 'VAK')
            ");
            $stmt->execute([
                $q[0], $q[1], $q[2], $q[3], $q[4], $q[5]
            ]);
            $inserted++;
        } catch (Exception $e) {
            echo "Error inserting question: " . $e->getMessage() . "\n";
        }
    }
    echo "✅ Inserted " . $inserted . " VAK questions\n";
} else {
    echo "\n✅ VAK questions already exist (" . $vakCount['total'] . " questions)\n";
}

echo "\n=== Database Setup Complete! ===\n";

// Final check
echo "\n=== Final Question Count ===\n";
$result = $db->query('SELECT test_type, COUNT(*) as total FROM questions GROUP BY test_type');
$rows = $result->fetchAll();

foreach($rows as $row) {
    echo $row['test_type'] . ': ' . $row['total'] . " questions\n";
}
?>

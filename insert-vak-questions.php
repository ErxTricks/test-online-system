<?php
require_once 'config/database.php';

$db = getDBConnection();

// VAK Questions - Visual, Auditory, Kinesthetic
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
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

echo "✅ " . $inserted . " soal VAK berhasil dimasukkan ke database!<br>";
echo "<a href='admin/manage-questions.php'>Kembali ke Manage Soal</a>";
?>

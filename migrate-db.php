<?php
require_once 'config/database.php';

$db = getDBConnection();

try {
    // Add test_type column to questions
    $db->exec("ALTER TABLE questions ADD COLUMN test_type VARCHAR(50) DEFAULT 'HSCL-25'");
    echo "✅ Kolom test_type ditambahkan ke questions<br>";
} catch (Exception $e) {
    echo "⚠️ test_type: " . $e->getMessage() . "<br>";
}

try {
    // Add selected_test_types column to tokens
    $db->exec("ALTER TABLE tokens ADD COLUMN selected_test_types VARCHAR(255) DEFAULT 'HSCL-25'");
    echo "✅ Kolom selected_test_types ditambahkan ke tokens<br>";
} catch (Exception $e) {
    echo "⚠️ selected_test_types: " . $e->getMessage() . "<br>";
}

try {
    // Add test_types_taken column to test_results
    $db->exec("ALTER TABLE test_results ADD COLUMN test_types_taken VARCHAR(255) DEFAULT 'HSCL-25'");
    echo "✅ Kolom test_types_taken ditambahkan ke test_results<br>";
} catch (Exception $e) {
    echo "⚠️ test_types_taken: " . $e->getMessage() . "<br>";
}

// Set existing data
try {
    $db->exec("UPDATE questions SET test_type = 'HSCL-25' WHERE id <= 25 AND test_type IS NULL");
    echo "✅ Set test_type HSCL-25 untuk questions 1-25<br>";
} catch (Exception $e) {
    echo "⚠️ Error setting HSCL-25: " . $e->getMessage() . "<br>";
}

try {
    $db->exec("UPDATE questions SET test_type = 'VAK' WHERE id > 25 AND test_type IS NULL");
    echo "✅ Set test_type VAK untuk questions 26+<br>";
} catch (Exception $e) {
    echo "⚠️ Error setting VAK: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "✅ Database migration selesai!<br>";
echo "<a href='admin/manage-questions.php'>Kembali ke Manage Soal</a>";
?>

<?php
require 'config/database.php';

$db = getDBConnection();

// Check if selected_test_types column exists in test_results
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
    echo "test_types_taken column already exists\n";
}

// Also check if test_type exists in questions
$result = $db->query("DESCRIBE questions");
$columns = [];
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['Field'];
}

if (!in_array('test_type', $columns)) {
    echo "Adding test_type column to questions...\n";
    $db->exec("ALTER TABLE questions ADD COLUMN test_type VARCHAR(50) DEFAULT 'HSCL-25'");
    echo "✅ Column added successfully!\n";
    
    // Set HSCL-25 for questions 1-25
    $db->exec("UPDATE questions SET test_type = 'HSCL-25' WHERE id <= 25");
    echo "✅ Set HSCL-25 for first 25 questions\n";
} else {
    echo "test_type column already exists\n";
}

// Check selected_test_types in tokens
$result = $db->query("DESCRIBE tokens");
$columns = [];
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $row['Field'];
}

if (!in_array('selected_test_types', $columns)) {
    echo "Adding selected_test_types column to tokens...\n";
    $db->exec("ALTER TABLE tokens ADD COLUMN selected_test_types VARCHAR(255) DEFAULT 'HSCL-25'");
    echo "✅ Column added successfully!\n";
} else {
    echo "selected_test_types column already exists\n";
}

echo "\n=== DATABASE SCHEMA CHECK COMPLETE ===\n";
?>

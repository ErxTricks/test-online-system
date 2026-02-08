<?php
require 'config/database.php';
$db = getDBConnection();

echo "=== QUESTIONS TABLE STRUCTURE ===\n";
$result = $db->query('DESCRIBE questions');
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n=== TOKENS TABLE STRUCTURE ===\n";
$result = $db->query('DESCRIBE tokens');
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n=== SAMPLE QUESTIONS (First 3) ===\n";
$result = $db->query('SELECT id, test_type, category FROM questions LIMIT 3');
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n=== TOKENS WITH SELECTED TESTS ===\n";
$result = $db->query('SELECT id, token_code, selected_test_types FROM tokens LIMIT 3');
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>

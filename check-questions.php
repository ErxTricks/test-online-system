<?php
require 'config/database.php';

$db = getDBConnection();
$result = $db->query('SELECT COUNT(*) as total, test_type FROM questions GROUP BY test_type');
$rows = $result->fetchAll();

foreach($rows as $row) {
    echo $row['test_type'] . ': ' . $row['total'] . " questions\n";
}

// Also check if selected_test_types column exists
try {
    $result = $db->query('DESCRIBE tokens');
    $columns = [];
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    if (in_array('selected_test_types', $columns)) {
        echo "\n✅ selected_test_types column exists in tokens table\n";
    } else {
        echo "\n❌ selected_test_types column MISSING from tokens table\n";
    }
} catch(Exception $e) {
    echo "Error checking tokens table: " . $e->getMessage();
}
?>

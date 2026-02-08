<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== VERIFICATION REPORT ===\n\n";

$db = getDBConnection();

// 1. Check database schema
echo "1. Database Schema Check:\n";
$tables = ['tokens', 'questions', 'test_results'];
foreach ($tables as $table) {
    $result = $db->query("DESC $table");
    $columns = [];
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    if ($table === 'tokens') {
        echo "   ✅ tokens table:\n";
        echo "      - selected_test_types: " . (in_array('selected_test_types', $columns) ? '✅ EXISTS' : '❌ MISSING') . "\n";
    } elseif ($table === 'questions') {
        echo "   ✅ questions table:\n";
        echo "      - test_type: " . (in_array('test_type', $columns) ? '✅ EXISTS' : '❌ MISSING') . "\n";
    } elseif ($table === 'test_results') {
        echo "   ✅ test_results table:\n";
        echo "      - test_types_taken: " . (in_array('test_types_taken', $columns) ? '✅ EXISTS' : '❌ MISSING') . "\n";
    }
}

// 2. Check question distribution
echo "\n2. Question Distribution:\n";
$result = $db->query('SELECT test_type, COUNT(*) as count FROM questions GROUP BY test_type ORDER BY test_type');
$rows = $result->fetchAll();
foreach($rows as $row) {
    echo "   - {$row['test_type']}: {$row['count']} questions\n";
}

// 3. Check admin panel files
echo "\n3. Admin Panel Check:\n";
$files = [
    'admin/generate-token.php' => 'Can generate tokens with test type selection',
    'admin/manage-questions.php' => 'Can manage questions with test_type field',
];
foreach ($files as $file => $description) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    echo "   " . ($exists ? "✅" : "❌") . " $file\n";
    echo "      $description\n";
}

// 4. Check test page
echo "\n4. Test Page Check:\n";
$testFile = __DIR__ . '/test.php';
$testContent = file_get_contents($testFile);
if (strpos($testContent, 'selected_test_types') !== false) {
    echo "   ✅ test.php properly uses selected_test_types\n";
} else {
    echo "   ❌ test.php does not reference selected_test_types\n";
}

// 5. Check result page improvements
echo "\n5. Result Page Check:\n";
$resultFile = __DIR__ . '/result.php';
$resultContent = file_get_contents($resultFile);
if (strpos($resultContent, '$isVAK') !== false && strpos($resultContent, '$isHSCL') !== false) {
    echo "   ✅ result.php has dynamic test type handling\n";
} else {
    echo "   ❌ result.php may not properly handle both test types\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
?>

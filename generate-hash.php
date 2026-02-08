<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash for 'admin123': " . $hash . "\n";
?>

<?php
require_once 'config/database.php';

try {
    $db = getDBConnection();

    // Add missing columns to admin_users table
    $db->exec("ALTER TABLE admin_users
        ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) DEFAULT NULL AFTER password,
        ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL AFTER full_name,
        ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER email");

    // Update the default admin user with additional info
    $stmt = $db->prepare("UPDATE admin_users SET full_name = ?, email = ?, is_active = ? WHERE username = ?");
    $stmt->execute(['Administrator', 'admin@example.com', true, 'admin']);

    echo "✅ Database schema updated successfully!\n";
    echo "✅ Admin user updated with full_name, email, and is_active fields.\n";

} catch (Exception $e) {
    echo "❌ Error updating database: " . $e->getMessage() . "\n";
}
?>

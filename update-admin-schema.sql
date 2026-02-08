s-- Update admin_users table to include missing fields
USE test_online_system;

-- Add missing columns to admin_users table
ALTER TABLE admin_users
ADD COLUMN full_name VARCHAR(100) DEFAULT NULL AFTER password,
ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER full_name,
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER email;

-- Update the default admin user with additional info
UPDATE admin_users
SET full_name = 'Administrator',
    email = 'admin@example.com',
    is_active = TRUE
WHERE username = 'admin';

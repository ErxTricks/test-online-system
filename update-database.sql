-- Update Database untuk Test Online System
-- Run script ini di phpMyAdmin untuk menambahkan tabel user_profiles

USE test_online_system;

-- Tabel untuk menyimpan data personal user
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    date_of_birth DATE,
    gender ENUM('Laki-laki', 'Perempuan') DEFAULT NULL,
    phone_number VARCHAR(20),
    religion VARCHAR(50),
    city VARCHAR(100),
    district VARCHAR(100),
    sub_district VARCHAR(100),
    address TEXT,
    occupation VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE
);

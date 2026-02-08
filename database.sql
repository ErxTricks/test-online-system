-- Database: test_online_system
CREATE DATABASE IF NOT EXISTS test_online_system;
USE test_online_system;

-- Tabel untuk menyimpan token
CREATE TABLE tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_code VARCHAR(12) UNIQUE NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    user_name VARCHAR(100) DEFAULT NULL,
    user_email VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    test_started_at TIMESTAMP NULL DEFAULT NULL,
    test_completed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_token_code (token_code),
    INDEX idx_is_used (is_used),
    INDEX idx_expires_at (expires_at)
);

-- Tabel untuk menyimpan soal test
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    point_a INT DEFAULT 4,
    point_b INT DEFAULT 3,
    point_c INT DEFAULT 2,
    point_d INT DEFAULT 1,
    category VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Tabel untuk menyimpan jawaban user
CREATE TABLE user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) NOT NULL,
    points_earned INT NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_answer (token_id, question_id)
);

-- Tabel untuk menyimpan hasil test
CREATE TABLE test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NOT NULL,
    total_score INT NOT NULL,
    max_score INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    total_questions INT NOT NULL,
    answered_questions INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE
);

-- Tabel untuk menyimpan data personal user
CREATE TABLE user_profiles (
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

-- Tabel admin
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert admin default (username: admin, password: admin123)
INSERT INTO admin_users (username, password)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert HSCL-25 soal test
-- Skala: 1 = Tidak sama sekali, 2 = Sedikit, 3 = Cukup banyak, 4 = Sangat banyak
-- Items 1-10: Anxiety, Items 11-25: Depresi
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, point_a, point_b, point_c, point_d, category) VALUES
-- Anxiety Items (1-10)
('Nervous atau khawatir', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Merasa takut', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Panik', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Mudah gelisah atau tersinggung', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Kesulitan berkonsentrasi', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Merasa tegang atau kaku', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Sakit kepala', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Menggigit kuku', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Nyeri di dada atau jantung berdebar', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
('Merasa sesak napas', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety'),
-- Depresi Items (11-25)
('Mudah menangis', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Kehilangan minat terhadap seksualitas atau kenikmatan seksual', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Kehilangan nafsu makan', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Kesulitan tidur atau tidak dapat tidur', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa tidak memiliki harapan akan masa depan', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa sedih', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa kesepian', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa tidak ada orang yang dapat diajak bicara', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa tidak bersemangat untuk melakukan apapun', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa diri anda lebih buruk dari orang lain', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Pemikiran untuk mengakhiri hidup anda', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Khawatiran yang berlebihan tentang berbagai hal', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa bahwa sesuatu yang anda lakukan membutuhkan usaha yang besar', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Tidak memiliki minat dalam banyak hal', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi'),
('Merasa tidak berharga', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi');

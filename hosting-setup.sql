-- Database Setup untuk InfinityFree Hosting
-- Database: if0_41100811_test_online
-- Gunakan phpMyAdmin untuk import file ini

-- Tabel untuk menyimpan token akses test
CREATE TABLE IF NOT EXISTS tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(100) UNIQUE NOT NULL,
    test_type ENUM('HSCL-25', 'VAK', 'Both') DEFAULT 'Both',
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel untuk menyimpan jawaban user
CREATE TABLE IF NOT EXISTS user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_option CHAR(1) NOT NULL,
    answer_points INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE
);

-- Tabel untuk menyimpan pertanyaan test
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    point_a INT DEFAULT 0,
    point_b INT DEFAULT 0,
    point_c INT DEFAULT 0,
    point_d INT DEFAULT 0,
    category VARCHAR(50),
    test_type VARCHAR(20)
);

-- Tabel untuk menyimpan hasil test
CREATE TABLE IF NOT EXISTS test_results (
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

-- Tabel admin
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert admin default (username: admin, password: admin123)
INSERT INTO admin_users (username, password) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert HSCL-25 soal test
-- Skala: 1 = Tidak sama sekali, 2 = Sedikit, 3 = Cukup banyak, 4 = Sangat banyak
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, point_a, point_b, point_c, point_d, category, test_type) VALUES
('Nervous atau khawatir', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Merasa takut', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Panik', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Mudah gelisah atau tersinggung', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Kesulitan berkonsentrasi', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Merasa tegang atau kaku', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Sakit kepala', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Menggigit kuku', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Nyeri di dada atau jantung berdebar', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Merasa sesak napas', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Anxiety', 'HSCL-25'),
('Mudah menangis', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Kehilangan minat terhadap seksualitas atau kenikmatan seksual', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Kehilangan nafsu makan', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Kesulitan tidur atau tidak dapat tidur', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa tidak memiliki harapan akan masa depan', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa sedih', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa kesepian', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa tidak ada orang yang dapat diajak bicara', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa tidak bersemangat untuk melakukan apapun', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa diri anda lebih buruk dari orang lain', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa diri anda lebih buruk dari orang lain', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Pemikiran untuk mengakhiri hidup anda', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Khawatiran yang berlebihan tentang berbagai hal', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa bahwa sesuatu yang anda lakukan membutuhkan usaha yang besar', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Tidak memiliki minat dalam banyak hal', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25'),
('Merasa tidak berharga', 'Tidak sama sekali', 'Sedikit', 'Cukup banyak', 'Sangat banyak', 1, 2, 3, 4, 'Depresi', 'HSCL-25');

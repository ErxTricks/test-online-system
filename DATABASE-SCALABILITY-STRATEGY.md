# Strategi Scalability Database

## 📋 Analisis Struktur Saat Ini

Database Anda saat ini:
- ✅ Sudah support multiple test types (HSCL-25, VAK)
- ✅ Schema sudah relatif flexible dengan field `test_type` di `questions`
- ⚠️ Belum ada archiving data lama
- ⚠️ Belum ada partitioning untuk performa
- ⚠️ User data + answers dalam satu DB (bisa overload dengan volume besar)

---

## 1️⃣ SCALABLE SCHEMA - Untuk Tambah Test Baru

### Problem Saat Ini
- `questions` table tidak punya kolom `test_type`, jadi sulit filter
- Nanti jika ada 1000+ soal, query jadi lambat

### Solusi: Update Table Questions

```sql
-- Tambah kolom test_type dan test_section
ALTER TABLE questions ADD COLUMN IF NOT EXISTS test_type VARCHAR(50) DEFAULT 'HSCL-25';
ALTER TABLE questions ADD COLUMN IF NOT EXISTS test_section VARCHAR(100) DEFAULT NULL;
ALTER TABLE questions ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT NULL;
ALTER TABLE questions ADD INDEX idx_test_type (test_type);
ALTER TABLE questions ADD INDEX idx_test_section (test_section);

-- Contoh data baru untuk test lain nanti
-- MBTI Test
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, category, test_type, test_section) 
VALUES ('Apakah Anda seorang introvert?', 'Ya', 'Tidak', 'Ragu-ragu', 'Tidak yakin', 'MBTI', 'MBTI', 'Personality');

-- Big Five Test
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, category, test_type, test_section) 
VALUES ('Seberapa openness Anda?', 'Rendah', 'Sedang', 'Tinggi', 'Sangat Tinggi', 'Openness', 'Big-Five', 'Openness');
```

### Struktur Baru untuk Test Configuration

```sql
-- Tabel baru: List test yang tersedia
CREATE TABLE IF NOT EXISTS test_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_code VARCHAR(50) UNIQUE NOT NULL,      -- 'HSCL-25', 'VAK', 'MBTI', dll
    test_name VARCHAR(100) NOT NULL,             -- 'HSCL-25 Mental Health Screening'
    description TEXT,
    total_questions INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_test_code (test_code)
);

-- Tabel baru: Categories per test
CREATE TABLE IF NOT EXISTS test_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_type_id INT NOT NULL,
    category_name VARCHAR(100) NOT NULL,        -- 'Anxiety', 'Depresi', 'Visual', etc
    category_emoji VARCHAR(10),
    description TEXT,
    FOREIGN KEY (test_type_id) REFERENCES test_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category (test_type_id, category_name)
);

-- Insert test types
INSERT INTO test_types (test_code, test_name, total_questions) VALUES 
('HSCL-25', 'HSCL-25 Mental Health Screening', 25),
('VAK', 'VAK Learning Style Assessment', 30),
('MBTI', 'Myers-Briggs Type Indicator', 93),
('Big-Five', 'Big Five Personality Test', 50);

-- Insert categories
INSERT INTO test_categories (test_type_id, category_name, category_emoji) 
SELECT id, 'Anxiety', '😰' FROM test_types WHERE test_code = 'HSCL-25';
INSERT INTO test_categories (test_type_id, category_name, category_emoji) 
SELECT id, 'Depresi', '😢' FROM test_types WHERE test_code = 'HSCL-25';
```

---

## 2️⃣ DATA ARCHIVING - Hindari Overload

### Masalah Volume Data
Jika 1000+ user per bulan × 25 soal = 25,000+ records/bulan di `user_answers`  
Dalam 1 tahun = 300,000+ records = Database lambat

### Solusi: Archive Strategy

#### A. Buat Table Archive untuk Data Lama
```sql
-- Tabel archive untuk jawaban lama
CREATE TABLE IF NOT EXISTS user_answers_archive (
    id INT NOT NULL,
    token_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) NOT NULL,
    points_earned INT NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_archived_at (archived_at)
);

-- Tabel archive untuk hasil test lama
CREATE TABLE IF NOT EXISTS test_results_archive (
    id INT NOT NULL,
    token_id INT NOT NULL,
    total_score INT NOT NULL,
    max_score INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    total_questions INT NOT NULL,
    answered_questions INT NOT NULL,
    completed_at TIMESTAMP,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_archived_at (archived_at)
);

-- Tabel archive untuk user profiles
CREATE TABLE IF NOT EXISTS user_profiles_archive (
    id INT NOT NULL,
    token_id INT NOT NULL,
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
    created_at TIMESTAMP,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_archived_at (archived_at)
);
```

#### B. PHP Script untuk Archive Data (Jalankan 1x per bulan)

File: `archive-old-data.php`

```php
<?php
require_once 'config/database.php';

$db = getDBConnection();
$archiveDate = date('Y-m-d', strtotime('-6 months')); // Archive data lebih dari 6 bulan

try {
    // Archive user_answers (lebih dari 6 bulan)
    $moveAnswers = "
        INSERT INTO user_answers_archive (id, token_id, question_id, selected_option, points_earned, answered_at)
        SELECT id, token_id, question_id, selected_option, points_earned, answered_at 
        FROM user_answers 
        WHERE answered_at < '$archiveDate'
    ";
    $db->query($moveAnswers);
    $deletedAnswers = $db->exec("DELETE FROM user_answers WHERE answered_at < '$archiveDate'");
    
    // Archive test_results
    $moveResults = "
        INSERT INTO test_results_archive (id, token_id, total_score, max_score, percentage, total_questions, answered_questions, completed_at)
        SELECT id, token_id, total_score, max_score, percentage, total_questions, answered_questions, completed_at 
        FROM test_results 
        WHERE completed_at < '$archiveDate'
    ";
    $db->query($moveResults);
    $deletedResults = $db->exec("DELETE FROM test_results WHERE completed_at < '$archiveDate'");
    
    // Archive user_profiles
    $moveProfiles = "
        INSERT INTO user_profiles_archive (id, token_id, first_name, last_name, date_of_birth, gender, phone_number, religion, city, district, sub_district, address, occupation, created_at)
        SELECT id, token_id, first_name, last_name, date_of_birth, gender, phone_number, religion, city, district, sub_district, address, occupation, created_at 
        FROM user_profiles 
        WHERE created_at < '$archiveDate'
    ";
    $db->query($moveProfiles);
    $deletedProfiles = $db->exec("DELETE FROM user_profiles WHERE created_at < '$archiveDate'");
    
    echo "✅ Archive berhasil:\n";
    echo "   - User Answers: $deletedAnswers records diarchive\n";
    echo "   - Test Results: $deletedResults records diarchive\n";
    echo "   - User Profiles: $deletedProfiles records diarchive\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
```

#### C. Backup Data Archive ke File TXT (Logging)

File: `backup-archive-to-log.php`

```php
<?php
require_once 'config/database.php';

$db = getDBConnection();
$backupDir = __DIR__ . '/logs/backup/';

// Buat folder jika belum ada
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Y-m-d_H-i-s');
$logFile = $backupDir . "archive_backup_{$timestamp}.txt";

// Export archived data ke file TXT
$handle = fopen($logFile, 'w');

// Header
fwrite($handle, "=== BACKUP ARCHIVE DATA ===\n");
fwrite($handle, "Generated: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "Archive Date Period: Sebelum " . date('Y-m-d', strtotime('-6 months')) . "\n");
fwrite($handle, str_repeat("=", 50) . "\n\n");

// Export user_answers_archive
fwrite($handle, "--- USER ANSWERS ARCHIVE ---\n");
$answers = $db->query("SELECT * FROM user_answers_archive ORDER BY archived_at DESC LIMIT 1000");
foreach ($answers as $answer) {
    fwrite($handle, json_encode($answer) . "\n");
}
fwrite($handle, "\n");

// Export test_results_archive  
fwrite($handle, "--- TEST RESULTS ARCHIVE ---\n");
$results = $db->query("SELECT * FROM test_results_archive ORDER BY archived_at DESC LIMIT 1000");
foreach ($results as $result) {
    fwrite($handle, json_encode($result) . "\n");
}
fwrite($handle, "\n");

// Export user_profiles_archive
fwrite($handle, "--- USER PROFILES ARCHIVE ---\n");
$profiles = $db->query("SELECT * FROM user_profiles_archive ORDER BY archived_at DESC LIMIT 1000");
foreach ($profiles as $profile) {
    fwrite($handle, json_encode($profile) . "\n");
}

fclose($handle);

echo "✅ Backup disimpan: $logFile\n";
echo "📊 File Size: " . filesize($logFile) / 1024 . " KB\n";
?>
```

#### D. Setup Scheduler (Automation)
Edit `crontab` untuk jalankan otomatis setiap bulan:
```bash
# Jalankan archive setiap hari 1 jam pagi
0 1 1 * * /usr/bin/php /var/www/html/test-online-system/archive-old-data.php

# Backup archive setiap hari 2 jam pagi  
0 2 1 * * /usr/bin/php /var/www/html/test-online-system/backup-archive-to-log.php
```

---

## 3️⃣ CLOUD vs LOCAL - Mana yang Dipilih?

### ✅ LOCAL HOSTING (Saat Ini - Cocok Untuk)
- Volume user: 100-1000/bulan
- Budget terbatas
- Server XAMPP/cPanel cukup besar (CPU 4+, RAM 4GB+)
- Archiving aktif (data lama dibuang)

**Estimasi capacity:** ~500,000 records/tahun = OK

### ☁️ CLOUD HOSTING (Diperlukan Jika)
- Volume user: >3000/bulan
- Budget cukup (Rp 300rb-1jt/bulan)
- Perlu redundancy & backup otomatis
- Traffic unpredictable

**Pilihan:** AWS RDS, Google Cloud SQL, DigitalOcean, atau Heroku

### 🔄 Hybrid Solution (Recommended)
1. **Local:** Gunakan untuk data aktif (3 bulan terakhir)
2. **Archive:** Pindahkan ke table archive setiap bulan
3. **Cloud Backup:** Export archive ke AWS S3 atau Google Drive setiap minggu
4. **Disaster Recovery:** Keep backup 2 tahun terakhir

---

## 4️⃣ IMPLEMENTASI ROADMAP

### ✅ Fase 1 (Sekarang)
- [ ] Tambah kolom `test_type` ke table `questions`
- [ ] Create table `test_types` dan `test_categories`
- [ ] Setup archiving script

### ✅ Fase 2 (1-2 minggu)
- [ ] Deploy archiving & logging
- [ ] Test dengan data dummy
- [ ] Setup cron jobs

### ✅ Fase 3 (Bulan depan)
- [ ] Implement cloud backup
- [ ] Monitor performa DB
- [ ] Dokumentasi lengkap

---

## 5️⃣ MONITORING & MAINTENANCE

### Monitor Size Tabel
```sql
-- Lihat ukuran tabel
SELECT 
    TABLE_NAME,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size in MB'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'test_online_system'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
```

### Check Queries Lambat
```sql
-- Enable slow query log di my.cnf
slow_query_log = 1
long_query_time = 2
```

### Optimize Tabel
```sql
-- Jalankan setiap bulan
OPTIMIZE TABLE user_answers;
OPTIMIZE TABLE test_results;
OPTIMIZE TABLE user_profiles;
```

---

## 📝 Summary

| Aspek | Solusi |
|-------|--------|
| **Test Scalability** | Table `test_types` + flexible schema |
| **Data Overload** | Archive + Archiving scripts |
| **Backup** | Export archive ke TXT logs |
| **Cloud?** | Not needed yet, local + archive sudah cukup |
| **Timeline** | Ready dalam 2 minggu |

Tanya lagi jika ada yang kurang jelas! 🚀

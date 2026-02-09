<?php
// Konfigurasi Database
define('DB_HOST', 'sql313.infinityfree.com');
define('DB_USER', 'if0_41100811');
define('DB_PASS', 'tPLE5j8SHiTs'); // Password MySQL Anda
define('DB_NAME', 'if0_41100811_test_online_system');

// Durasi test dalam menit
define('TEST_DURATION', 0);

// Masa aktif token dalam hari
define('TOKEN_EXPIRY_DAYS', 30);

// Koneksi Database
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}
?>
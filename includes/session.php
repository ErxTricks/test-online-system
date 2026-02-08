<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
function isUserLoggedIn() {
    return isset($_SESSION['token_id']) && isset($_SESSION['token_code']);
}

// Cek apakah admin sudah login
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

// Login user dengan token
function loginUser($tokenData, $userName, $userEmail) {
    $_SESSION['token_id'] = $tokenData['id'];
    $_SESSION['token_code'] = $tokenData['token_code'];
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_email'] = $userEmail;
    $_SESSION['login_time'] = time();
}

// Login admin
function loginAdmin($adminId, $adminUsername) {
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_username'] = $adminUsername;
}

// Logout
function logout() {
    session_unset();
    session_destroy();
}

// Redirect jika belum login
function requireLogin($redirectTo = 'index.php') {
    if (!isUserLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

// Redirect jika belum login admin
function requireAdminLogin($redirectTo = 'index.php') {
    if (!isAdminLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}
?>
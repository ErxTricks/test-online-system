<?php
session_start();

// Destroy session
$_SESSION = [];
session_destroy();

// Redirect to admin login
header("Location: ../admin-login.php");
exit;

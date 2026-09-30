<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 0) {
    header("Location: ../auth/login.php");
    exit();
}
?>
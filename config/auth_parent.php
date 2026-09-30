<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['school_id'])) {
    header("Location: ../auth/login.php");
    exit();
}
?>
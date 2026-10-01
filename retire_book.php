<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

if (!isset($_GET['id'])) {
    header("Location: library_books.php");
    exit();
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("UPDATE library_books SET status = 'Retired' WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $school_id]);

header("Location: library_books.php?retired=1");
exit();
?>
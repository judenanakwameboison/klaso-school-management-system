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

$check = $conn->prepare("SELECT id FROM library_books WHERE id = ? AND school_id = ?");
$check->execute([$id, $school_id]);

if ($check->rowCount() == 0) {
    header("Location: library_books.php?notfound=1");
    exit();
}

$historyCheck = $conn->prepare("SELECT COUNT(*) FROM library_issues WHERE book_id = ? AND school_id = ?");
$historyCheck->execute([$id, $school_id]);

if ($historyCheck->fetchColumn() > 0) {
    header("Location: library_books.php?blocked=1");
    exit();
}

$stmt = $conn->prepare("DELETE FROM library_books WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $school_id]);
header("Location: library_books.php?deleted=1");
exit();
?>
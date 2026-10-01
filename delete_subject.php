<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

if (!isset($_GET['id'])) {
    header("Location: subjects.php");
    exit();
}

$id = (int)$_GET['id'];

/* Confirm the subject exists and belongs to this school */
$check = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND school_id = ?");
$check->execute([$id, $school_id]);

if ($check->rowCount() == 0) {
    header("Location: subjects.php?notfound=1");
    exit();
}

/* Block deletion if this subject is assigned to any class */
$assignCheck = $conn->prepare("SELECT COUNT(*) FROM class_subjects WHERE subject_id = ? AND school_id = ?");
$assignCheck->execute([$id, $school_id]);

if ($assignCheck->fetchColumn() > 0) {
    header("Location: subjects.php?blocked=1&reason=" . urlencode('active class assignments'));
    exit();
}

/* Safe to delete */
$stmt = $conn->prepare("DELETE FROM subjects WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $school_id]);
header("Location: subjects.php?deleted=1");
exit();
?>
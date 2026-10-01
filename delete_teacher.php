<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

if (!isset($_GET['id'])) {
    header("Location: teachers.php");
    exit();
}

$id = (int)$_GET['id'];

$check = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND school_id = ?");
$check->execute([$id, $school_id]);

if ($check->rowCount() == 0) {
    header("Location: teachers.php?notfound=1");
    exit();
}

/* Block deletion if this teacher is assigned to any class/subject */
$assignCheck = $conn->prepare("SELECT COUNT(*) FROM class_subjects WHERE teacher_id = ? AND school_id = ?");
$assignCheck->execute([$id, $school_id]);

if ($assignCheck->fetchColumn() > 0) {
    header("Location: teachers.php?blocked=1&reason=" . urlencode('active class/subject assignments'));
    exit();
}

$stmt = $conn->prepare("DELETE FROM teachers WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $school_id]);
header("Location: teachers.php?deleted=1");
exit();
?>
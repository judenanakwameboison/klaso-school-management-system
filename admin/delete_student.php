<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

if (!isset($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$id = (int)$_GET['id'];

/* Confirm the student exists AND belongs to this school before deleting */
$check = $conn->prepare("SELECT id FROM students WHERE id = ? AND school_id = ?");
$check->execute([$id, $school_id]);

if ($check->rowCount() == 0) {
    header("Location: students.php?notfound=1");
    exit();
}

/* Block deletion if the student has related records anywhere in the system */
$blockers = [
    'fees' => 'fee records',
    'results' => 'exam results',
    'attendance' => 'attendance records',
    'library_issues' => 'library book history'
];

$foundBlockers = [];

foreach ($blockers as $table => $label) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE student_id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);
    if ($stmt->fetchColumn() > 0) {
        $foundBlockers[] = $label;
    }
}

if (count($foundBlockers) > 0) {
    $reason = urlencode(implode(', ', $foundBlockers));
    header("Location: students.php?blocked=1&reason=" . $reason);
    exit();
}

/* Safe to delete — no related records exist */
$stmt = $conn->prepare("DELETE FROM students WHERE id = ? AND school_id = ?");
$stmt->execute([$id, $school_id]);
header("Location: students.php?deleted=1");
exit();
?>
<?php
require_once "../config/db.php";
require_once "../config/totp.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$email = trim($_POST['email']);
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute(['email' => $email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* THE CRITICAL CHECK — must run before anything else touches $user */
if (!$user || !password_verify($password, $user['password_hash'])) {
    header("Location: login.php?error=1");
    exit();
}

/* If this user belongs to a school, confirm that school is still Active */
if ($user['school_id'] !== null) {

    $schoolCheck = $conn->prepare("SELECT status FROM schools WHERE id = ?");
    $schoolCheck->execute([$user['school_id']]);
    $school = $schoolCheck->fetch(PDO::FETCH_ASSOC);

    if (!$school || $school['status'] != 'Active') {
        header("Location: login.php?suspended=1");
        exit();
    }
}

/* Force password change first, regardless of role, if flagged */
if ($user['force_password_change'] == 1) {
    $_SESSION['pending_reset_user_id'] = $user['id'];
    header("Location: force_password_reset.php");
    exit();
}

/* Role 1 = School Admin — requires mandatory 2FA */
if ($user['role_id'] == 1) {

    if ($user['two_factor_enabled'] == 1) {
        $_SESSION['pending_2fa_user_id'] = $user['id'];
        header("Location: verify_2fa.php");
        exit();
    } else {
        $_SESSION['pending_2fa_user_id'] = $user['id'];
        header("Location: setup_2fa.php?required=1");
        exit();
    }
}

/* All other roles: no 2FA required, log in directly */
$_SESSION['user_id'] = $user['id'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];

/* Only non-superadmin roles belong to a specific school */
if ($user['school_id'] !== null) {
    $_SESSION['school_id'] = $user['school_id'];
}

switch ($user['role_id']) {

    case 0: // SUPER ADMIN — oversees the whole Klaso platform, no school_id
        header("Location: ../superadmin/dashboard.php");
        exit();

    case 2: // TEACHER
        header("Location: ../teacher/dashboard.php");
        exit();

    case 3: // STUDENT
        header("Location: ../student/dashboard.php");
        exit();

    case 4: // PARENT
        header("Location: ../parent/dashboard.php");
        exit();

    default:
        header("Location: login.php?error=1");
        exit();
}
?>
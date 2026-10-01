<?php
require_once "../config/db.php";
session_start();

/* Must have a pending login (post-password, pre-full-session) to reach this page */
if (!isset($_SESSION['pending_reset_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['pending_reset_user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['force_password_change'] != 1) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $conn->prepare("
            UPDATE users
            SET password_hash = ?, force_password_change = 0
            WHERE id = ?
        ");
        $update->execute([$hashed, $user_id]);

        unset($_SESSION['pending_reset_user_id']);

        /* Log them in properly now that password is set */
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        if ($user['school_id'] !== null) {
            $_SESSION['school_id'] = $user['school_id'];
        }

        /* Role 1 (School Admin) still needs 2FA setup next */
        if ($user['role_id'] == 1) {
            unset($_SESSION['user_id'], $_SESSION['role_id'], $_SESSION['full_name'], $_SESSION['email'], $_SESSION['school_id']);
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            header("Location: setup_2fa.php?required=1");
            exit();
        }

        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set Your Password - Klaso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --g-900:#06210F; --g-800:#0B3D1F; --g-700:#12592C; --g-600:#1B7A3E;
    --g-500:#1FA24C; --g-400:#34C566; --g-100:#E9F8EE;
    --white:#FFFFFF; --ink:#0C1B12; --slate:#5B6B60; --line:#DCEFE1;
  }
  *{ box-sizing:border-box; margin:0; padding:0; font-family:'Inter', sans-serif; }
  body{
    background: radial-gradient(circle at 30% 20%, rgba(52,197,102,0.15), transparent 50%), linear-gradient(135deg, var(--g-900), var(--g-700));
    min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;
  }
  .box{
    width:100%; max-width:420px; background:var(--white); border-radius:16px;
    padding:36px 34px 32px; box-shadow:0 24px 60px rgba(0,0,0,0.35);
  }
  .crest{
    width:52px; height:52px; border-radius:50%; margin:0 auto 20px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    display:flex; align-items:center; justify-content:center;
    font-family:'Fraunces', serif; font-weight:700; font-size:20px; color:#fff;
    box-shadow:0 0 0 4px rgba(52,197,102,0.15), 0 6px 18px rgba(31,162,76,0.4);
  }
  h2{ text-align:center; font-family:'Fraunces', serif; font-size:20px; color:var(--g-800); margin-bottom:6px; }
  .sub{ text-align:center; font-size:12.5px; color:var(--slate); margin-bottom:26px; line-height:1.5; }

  .error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    padding:12px 14px; border-radius:8px; font-size:13px; font-weight:600;
    margin-bottom:18px; text-align:center;
  }

  .field{ margin-bottom:18px; }
  .field label{display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field input{
    width:100%; padding:13px 14px; font-size:14px;
    font-family:'Inter', sans-serif; color:var(--ink);
    background:var(--g-100); border:1.5px solid var(--line); border-radius:8px;
    transition:.15s ease;
  }
  .field input:focus{ outline:none; border-color:var(--g-500); background:#fff; box-shadow:0 0 0 3px rgba(31,162,76,0.15); }
  .field .hint{ font-size:11.5px; color:var(--slate-light); margin-top:6px; }

  button{
    width:100%; padding:14px; font-size:14px; font-weight:700; color:#fff;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    border:none; border-radius:10px; cursor:pointer; transition:.15s ease;
    box-shadow:0 8px 20px rgba(31,162,76,0.3); margin-top:6px;
  }
  button:hover{ filter:brightness(1.08); }
</style>
</head>
<body>

<div class="box">
  <div class="crest">K</div>
  <h2>Set Your Password</h2>
  <div class="sub">You logged in with a temporary password. Choose a new one to continue.</div>

  <?php if($error != ""){ ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <form method="POST">
    <div class="field">
      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" required>
      <div class="hint">At least 8 characters.</div>
    </div>
    <div class="field">
      <label for="confirm_password">Confirm Password</label>
      <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit">Set Password & Continue</button>
  </form>
</div>

</body>
</html>
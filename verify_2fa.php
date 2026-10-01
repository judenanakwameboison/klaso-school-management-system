<?php
require_once "../config/db.php";
require_once "../config/totp.php";
session_start();

/* Must have passed step 1 (correct password) first */
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['pending_2fa_user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['two_factor_enabled'] != 1) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $code = trim($_POST['code']);
    $verified = false;

    if (TOTP::verifyCode($user['two_factor_secret'], $code)) {
        $verified = true;
    } else {
        /* Check backup codes as a fallback */
        $backupCodes = json_decode($user['backup_codes'], true) ?: [];
        $codeUpper = strtoupper($code);

        if (in_array($codeUpper, $backupCodes)) {
            $verified = true;

            /* Burn the used backup code so it can't be reused */
            $remaining = array_values(array_diff($backupCodes, [$codeUpper]));
            $update = $conn->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
            $update->execute([json_encode($remaining), $user_id]);
        }
    }

    if ($verified) {

        unset($_SESSION['pending_2fa_user_id']);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['school_id'] = $user['school_id'];

        header("Location: ../admin/dashboard.php");
        exit();

    } else {
        $error = "Incorrect code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Your Identity - Klaso</title>
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
    width:100%; max-width:400px; background:var(--white); border-radius:16px;
    padding:36px 34px 30px; box-shadow:0 24px 60px rgba(0,0,0,0.35);
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

  input{
    width:100%; padding:14px; font-size:22px; letter-spacing:8px; text-align:center;
    font-family:'IBM Plex Mono', monospace; font-weight:700; color:var(--g-800);
    background:var(--g-100); border:1.5px solid var(--line); border-radius:10px;
    margin-bottom:18px;
  }
  input:focus{ outline:none; border-color:var(--g-500); background:#fff; box-shadow:0 0 0 3px rgba(31,162,76,0.15); }

  button{
    width:100%; padding:14px; font-size:14px; font-weight:700; color:#fff;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    border:none; border-radius:10px; cursor:pointer; transition:.15s ease;
    box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  button:hover{ filter:brightness(1.08); }

  .hint{ text-align:center; font-size:11.5px; color:#8FA096; margin-top:18px; line-height:1.6; }
</style>
</head>
<body>

<div class="box">
  <div class="crest">K</div>
  <h2>Verify Your Identity</h2>
  <div class="sub">Enter the 6-digit code from your authenticator app.</div>

  <?php if($error != ""){ ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <form method="POST">
    <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="one-time-code" autofocus required>
    <button type="submit">Verify & Login</button>
  </form>

  <div class="hint">Lost your device? Use one of your saved backup codes instead of the 6-digit code.</div>
</div>

</body>
</html>
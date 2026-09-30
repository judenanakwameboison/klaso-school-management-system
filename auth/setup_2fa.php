<?php
require_once "../config/db.php";
require_once "../config/totp.php";
session_start();

/* Must have passed step 1 (correct password) to reach this page */
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['pending_2fa_user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* If 2FA is already enabled, this page has no business being visited directly */
if ($user['two_factor_enabled'] == 1) {
    header("Location: verify_2fa.php");
    exit();
}

$error = "";

/* Generate a secret once per setup session, keep it in session until confirmed */
if (!isset($_SESSION['setup_2fa_secret'])) {
    $_SESSION['setup_2fa_secret'] = TOTP::generateSecret();
}

$secret = $_SESSION['setup_2fa_secret'];
$provisioningUri = TOTP::getProvisioningUri($secret, $user['email'], 'Klaso');

/* STEP 2 of setup: confirm the app is working before turning 2FA on */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $code = trim($_POST['code']);

    if (TOTP::verifyCode($secret, $code)) {

        $backupCodes = TOTP::generateBackupCodes(8);

        $update = $conn->prepare("
            UPDATE users
            SET two_factor_secret = ?, two_factor_enabled = 1, backup_codes = ?
            WHERE id = ?
        ");
        $update->execute([
            $secret,
            json_encode($backupCodes),
            $user_id
        ]);

        unset($_SESSION['setup_2fa_secret']);
        $_SESSION['show_backup_codes'] = $backupCodes;

        header("Location: backup_codes.php");
        exit();

    } else {
        $error = "That code didn't match. Make sure your app's time is synced and try the newest code shown.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set Up Two-Factor Authentication - Klaso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
    width:100%; max-width:460px; background:var(--white); border-radius:16px;
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
  .sub{ text-align:center; font-size:12.5px; color:var(--slate); margin-bottom:22px; line-height:1.5; }

  .required-note{
    background:#FFF7E0; border:1.5px solid #F3E3AC; color:#8A6300;
    padding:11px 14px; border-radius:8px; font-size:12px; font-weight:600;
    margin-bottom:20px; text-align:center;
  }

  .step-label{
    font-size:10.5px; letter-spacing:1.4px; text-transform:uppercase; color:var(--g-600);
    font-weight:800; margin-bottom:10px;
  }

  .qr-wrap{display:flex; justify-content:center; padding:18px; background:var(--g-100);
    border:1.5px solid var(--line); border-radius:12px; margin-bottom:16px;
  }

  .secret-fallback{
    text-align:center; font-size:11.5px; color:var(--slate); margin-bottom:24px;
  }
  .secret-fallback code{
    display:block; margin-top:6px; font-family:'IBM Plex Mono', monospace; font-size:13px;
    font-weight:700; color:var(--g-800); letter-spacing:1.5px; word-break:break-all;
  }

  .error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    padding:12px 14px; border-radius:8px; font-size:13px; font-weight:600;
    margin-bottom:18px; text-align:center;
  }

  input{
    width:100%; padding:14px; font-size:20px; letter-spacing:8px; text-align:center;
    font-family:'IBM Plex Mono', monospace; font-weight:700; color:var(--g-800);
    background:var(--g-100); border:1.5px solid var(--line); border-radius:10px;
    margin-bottom:16px;
  }
  input:focus{ outline:none; border-color:var(--g-500); background:#fff; box-shadow:0 0 0 3px rgba(31,162,76,0.15); }

  button{
    width:100%; padding:14px; font-size:14px; font-weight:700; color:#fff;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    border:none; border-radius:10px; cursor:pointer; transition:.15s ease;
    box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  button:hover{ filter:brightness(1.08); }
</style>
</head>
<body>

<div class="box">
  <div class="crest">K</div>
  <h2>Set Up Two-Factor Authentication</h2>
  <div class="sub">This protects your admin account even if your password is ever compromised.</div>

  <?php if(isset($_GET['required'])){ ?>
    <div class="required-note">Admin accounts require two-factor authentication. Please complete setup to continue.</div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <div class="step-label">Step 1 — Scan this code</div>
  <div class="qr-wrap" id="qrcode"></div>

  <div class="secret-fallback">
    Can't scan? Enter this key manually in your app:
    <code><?php echo htmlspecialchars($secret); ?></code>
  </div>

  <div class="step-label">Step 2 — Enter the 6-digit code shown in your app</div>
  <form method="POST">
    <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus required>
    <button type="submit">Confirm & Enable 2FA</button>
  </form>
</div>

<script>
new QRCode(document.getElementById("qrcode"), {
  text: <?php echo json_encode($provisioningUri); ?>,
  width: 200,
  height: 200,
  colorDark: "#0B3D1F",
  colorLight: "#ffffff"
});
</script>

</body>
</html>
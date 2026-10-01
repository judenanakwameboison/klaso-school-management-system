<?php
session_start();

if (!isset($_SESSION['show_backup_codes'])) {
    header("Location: login.php");
    exit();
}

$codes = $_SESSION['show_backup_codes'];
unset($_SESSION['show_backup_codes']);

/* Complete the login now that 2FA setup is confirmed */
if (isset($_SESSION['pending_2fa_user_id'])) {

    require_once "../config/db.php";
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['pending_2fa_user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    unset($_SESSION['pending_2fa_user_id']);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['school_id'] = $user['school_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Your Backup Codes - Klaso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --g-900:#06210F; --g-800:#0B3D1F; --g-700:#12592C; --g-600:#1B7A3E;
    --g-500:#1FA24C; --g-100:#E9F8EE;
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

  .warning{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    padding:12px 14px; border-radius:8px; font-size:12.5px; font-weight:600;
    margin-bottom:20px; text-align:center; line-height:1.5;
  }

  .codes-grid{
    display:grid; grid-template-columns:1fr 1fr; gap:10px;
    background:var(--g-100); border:1.5px solid var(--line); border-radius:10px;
    padding:20px; margin-bottom:22px;
  }
  .code-item{
    font-family:'IBM Plex Mono', monospace; font-weight:700; font-size:14px;
    color:var(--g-800); text-align:center; background:#fff; padding:10px;
    border-radius:6px; border:1px solid var(--line); letter-spacing:1px;
  }

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
  <h2>Save Your Backup Codes</h2>
  <div class="sub">Two-factor authentication is now active on your account.</div>

  <div class="warning">
    Each code works once. Save them somewhere safe &mdash; if you lose your phone and don't have these, you could be locked out of your admin account.
  </div>

  <div class="codes-grid">
    <?php foreach ($codes as $code) { ?>
      <div class="code-item"><?php echo htmlspecialchars($code); ?></div>
    <?php } ?>
  </div>

  <form method="GET" action="../admin/dashboard.php">
    <button type="submit">I've Saved These &mdash; Continue to Dashboard</button>
  </form>
</div>

</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Klaso Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --g-900:#06210F; --g-800:#0B3D1F; --g-700:#12592C; --g-600:#1B7A3E;
    --g-500:#1FA24C; --g-400:#34C566; --g-100:#E9F8EE;
    --white:#FFFFFF; --ink:#0C1B12; --slate:#5B6B60; --slate-light:#8FA096; --line:#DCEFE1;
  }
  *{ box-sizing:border-box; margin:0; padding:0; font-family:'Inter', sans-serif; }
  body{
    background:
      radial-gradient(circle at 20% 15%, rgba(52,197,102,0.18), transparent 45%),
      radial-gradient(circle at 85% 85%, rgba(31,162,76,0.12), transparent 50%),
      linear-gradient(150deg, var(--g-900) 0%, var(--g-800) 55%, var(--g-700) 100%);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
    -webkit-font-smoothing:antialiased;
  }

  .login-box{
    width:100%;
    max-width:400px;
    background:var(--white);
    border-radius:16px;
    padding:40px 36px 32px;
    box-shadow:0 24px 60px rgba(0,0,0,0.35);
    animation:fadeIn .4s ease-out;
  }

  @keyframes fadeIn{
    from{ opacity:0; transform:translateY(16px); }
    to{ opacity:1; transform:translateY(0); }
  }

  .crest{
    width:56px; height:56px; border-radius:50%; margin:0 auto 18px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    display:flex; align-items:center; justify-content:center;
    font-family:'Fraunces', serif; font-weight:700; font-size:22px; color:#fff;
    box-shadow:0 0 0 4px rgba(52,197,102,0.15), 0 6px 18px rgba(31,162,76,0.4);
  }

  .school-name{
    text-align:center; font-family:'Fraunces', serif; font-weight:700; font-size:19px;
    color:var(--g-800); margin-bottom:4px;
  }
  .school-sub{
    text-align:center; font-size:10.5px; letter-spacing:1.8px; text-transform:uppercase;
    color:var(--g-500); font-weight:700; margin-bottom:28px;
  }

  .error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:12px 14px;
    border-radius:8px; margin-bottom:20px;
    display:flex; align-items:center; gap:8px;
  }
  .error::before{
    content:'!'; width:18px; height:18px; border-radius:50%; background:#B42318; color:#fff;
    display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; flex-shrink:0;
  }

  .success{
    background:var(--g-100); border:1.5px solid #BFE7CC; color:var(--g-700);
    font-size:13px; font-weight:600; padding:12px 14px;
    border-radius:8px; margin-bottom:20px;
    display:flex; align-items:center; gap:8px;
  }

  .field{ margin-bottom:16px; }
  .field label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field input{
    width:100%;
    padding:13px 14px;
    font-size:14px;
    font-family:'Inter', sans-serif;
    color:var(--ink);
    background:var(--g-100);
    border:1.5px solid var(--line);
    border-radius:8px;
    transition:.15s ease;
  }
  .field input:focus{
    outline:none;
    border-color:var(--g-500);
    background:#fff;
    box-shadow:0 0 0 3px rgba(31,162,76,0.15);
  }

  .toggle-row{
    display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--slate);
    margin:-6px 0 20px;
  }
  .toggle-row input{ width:15px; height:15px; accent-color:var(--g-500); cursor:pointer; }
  .toggle-row label{ cursor:pointer; }

  button{
    width:100%;
    padding:14px;
    font-size:14px; font-weight:700; color:#fff;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    border:none; border-radius:8px; cursor:pointer;
    transition:.15s ease;
    box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  button:hover{ filter:brightness(1.08); transform:translateY(-1px); }

  .forgot-link{
    display:block; text-align:center; margin-top:18px;
    font-size:12.5px; color:var(--g-700); font-weight:600; text-decoration:none;
  }
  .forgot-link:hover{ text-decoration:underline; }

  .apply-link{
    display:block; text-align:center; margin-top:10px;
    font-size:12px; color:var(--slate); text-decoration:none;
  }
  .apply-link:hover{ text-decoration:underline; }

  .footer-note{
    text-align:center; font-size:11px; color:var(--slate-light); margin-top:24px;
  }
</style>
</head>
<body>

<div class="login-box">

  <div class="crest">K</div>
  <div class="school-name">Klaso</div>
  <div class="school-sub">School Management Platform</div>

  <?php if (isset($_GET['error'])) { ?>
    <div class="error">Invalid email or password.</div>
  <?php } ?>

  <?php if (isset($_GET['suspended'])) { ?>
    <div class="error">This school's account has been suspended. Please contact Klaso support.</div>
<?php } ?>

  <?php if (isset($_GET['loggedout'])) { ?>
    <div class="success">You've been logged out successfully.</div>
  <?php } ?>

  <form action="login_process.php" method="POST">

    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" required>
    </div>

    <div class="toggle-row">
      <input type="checkbox" id="showPassword" onclick="togglePassword()">
      <label for="showPassword">Show password</label>
    </div>

    <button type="submit">Log In</button>

  </form>

  <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
  <a href="apply.php" class="apply-link">Is your school not on Klaso yet? Apply here</a>

  <div class="footer-note">Admin accounts require two-factor authentication.</div>

</div>

<script>
function togglePassword(){
  const pass = document.getElementById("password");
  pass.type = pass.type === "password" ? "text" : "password";
}
</script>

</body>
</html>
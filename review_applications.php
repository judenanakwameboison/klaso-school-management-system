<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_superadmin.php";
require_once "../config/db.php";

$generatedPassword = "";
$approvedSchoolName = "";

/* Generate a school_code from the school name: uppercase, alphanumeric only */
function generateSchoolCode($conn, $schoolName) {
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $schoolName));
    $base = substr($base, 0, 10);
    if ($base == "") { $base = "SCHOOL"; }

    $code = $base;
    $suffix = 1;

    while (true) {
        $check = $conn->prepare("SELECT id FROM schools WHERE school_code = ?");
        $check->execute([$code]);
        if ($check->rowCount() == 0) break;
        $suffix++;
        $code = $base . $suffix;
    }

    return $code;
}

/* Generate a strong random temporary password */
function generateTempPassword() {
    return bin2hex(random_bytes(5));
}

/* APPROVE an application */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_id'])) {

    $app_id = (int)$_POST['approve_id'];

    $stmt = $conn->prepare("SELECT * FROM school_applications WHERE id = ? AND status = 'Pending'");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($app) {

        $schoolCode = generateSchoolCode($conn, $app['school_name']);

        /* Create the school — logo_path carried over from the application */
        $insertSchool = $conn->prepare("
            INSERT INTO schools (school_name, school_code, contact_email, contact_phone, logo_path, status, approved_at, approved_by)
            VALUES (?, ?, ?, ?, ?, 'Active', NOW(), ?)
        ");
        $insertSchool->execute([
            $app['school_name'],
            $schoolCode,
            $app['contact_email'],
            $app['contact_phone'],
            $app['logo_path'],
            $_SESSION['user_id']
        ]);

        $newSchoolId = $conn->lastInsertId();

        /* Create the school's first Admin account */
        $tempPassword = generateTempPassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        $insertAdmin = $conn->prepare("
            INSERT INTO users (school_id, full_name, email, password_hash, role_id, force_password_change)
            VALUES (?, ?, ?, ?, 1, 1)
        ");
        $insertAdmin->execute([
            $newSchoolId,
            $app['contact_name'],
            $app['contact_email'],
            $hashedPassword
        ]);

        /* Mark the application as approved */
        $updateApp = $conn->prepare("
            UPDATE school_applications
            SET status = 'Approved', reviewed_at = NOW(), reviewed_by = ?
            WHERE id = ?
        ");
        $updateApp->execute([$_SESSION['user_id'], $app_id]);

        $generatedPassword = $tempPassword;
        $approvedSchoolName = $app['school_name'];
    }
}

/* REJECT an application */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reject_id'])) {

    $app_id = (int)$_POST['reject_id'];

    $stmt = $conn->prepare("
        UPDATE school_applications
        SET status = 'Rejected', reviewed_at = NOW(), reviewed_by = ?
        WHERE id = ? AND status = 'Pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $app_id]);

    header("Location: review_applications.php?rejected=1");
    exit();
}

/* Load pending applications */
$applications = $conn->query("
    SELECT * FROM school_applications
    WHERE status = 'Pending'
    ORDER BY submitted_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingApplications = count($applications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Review Applications - Klaso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{--g-900:#06210F; --g-800:#0B3D1F; --g-700:#12592C; --g-600:#1B7A3E;
    --g-500:#1FA24C; --g-400:#34C566; --g-100:#E9F8EE;
    --white:#FFFFFF; --ink:#0C1B12; --slate:#5B6B60; --slate-light:#8FA096; --line:#DCEFE1;
  }
  *{ box-sizing:border-box; margin:0; padding:0; font-family:'Inter', sans-serif; }
  body{ background:var(--white); color:var(--ink); -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }

  .sidebar{
    position:fixed; left:0; top:0; width:264px; height:100vh;
    background:
      radial-gradient(circle at 0% 0%, rgba(52,197,102,0.18), transparent 45%),
      linear-gradient(180deg, var(--g-900) 0%, var(--g-800) 55%, var(--g-900) 100%);
    color:#EAF8EE; padding:32px 22px; display:flex; flex-direction:column;
    overflow-y:auto;
    box-shadow: 4px 0 24px rgba(6,33,15,0.25);
  }
  .brand{
    display:flex; align-items:center; gap:12px;
    padding-bottom:26px; margin-bottom:20px;
    border-bottom:1px solid rgba(52,197,102,0.25);
  }
  .crest{
    width:42px; height:42px; border-radius:50%;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    display:flex; align-items:center; justify-content:center;
    font-family:'Fraunces', serif; font-weight:700; font-size:16px; color:#fff;
    flex-shrink:0; box-shadow:0 0 0 3px rgba(52,197,102,0.2), 0 4px 14px rgba(31,162,76,0.5);
  }
  .brand-text .name{ font-family:'Fraunces', serif; font-size:17px; font-weight:700; line-height:1.15; }
  .brand-text .sub{ font-size:10.5px; letter-spacing:1.8px; text-transform:uppercase; color:var(--g-400); margin-top:3px; font-weight:600; }
  .nav-group-label{
    font-size:10px; letter-spacing:1.8px; text-transform:uppercase;
    color:#5C7A67; margin:18px 10px 8px; font-weight:700;
  }
  .nav-group-label:first-of-type{ margin-top:2px; }
  .sidebar nav a{
    display:flex; align-items:center; gap:11px;
    padding:10px 12px; border-radius:8px; font-size:13.5px; font-weight:500;
    color:#CFE9D8; margin-bottom:2px; transition:.15s ease;
  }
  .sidebar nav a .dot{ width:5px; height:5px; border-radius:50%; background:#3E6B4F; flex-shrink:0; }
  .sidebar nav a:hover{ background:rgba(52,197,102,0.12); color:#fff; }
  .sidebar nav a.active{
    background: linear-gradient(90deg, rgba(52,197,102,0.28), rgba(52,197,102,0.05));
    color:#fff; font-weight:700;
    box-shadow: inset 3px 0 0 var(--g-400);
  }
  .sidebar nav a.active .dot{ background:var(--g-400); box-shadow:0 0 8px var(--g-400); }
  .badge-count{
    margin-left:auto; background:var(--g-500); color:#fff; font-size:10.5px; font-weight:800;
    padding:2px 7px; border-radius:20px; font-family:'IBM Plex Mono', monospace;
  }
  .sidebar-foot{
    margin-top:auto; padding-top:20px;
    border-top:1px solid rgba(52,197,102,0.25); font-size:12px;
  }
  .sidebar-foot a{ display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:7px; color:#9FC3AC; font-weight:500; }
  .sidebar-foot a:hover{ background:rgba(255,255,255,0.06); color:#fff; }

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:1200px; }

  .page-head{ margin-bottom:22px; }
  .eyebrow{
    font-size:11px; letter-spacing:2.4px; text-transform:uppercase;
    color:var(--g-500); font-weight:700; margin-bottom:10px;
  }
  .page-head h1{
    font-family:'Fraunces', serif; font-weight:700; font-size:30px;
    color:var(--g-800); letter-spacing:-0.3px;
    padding-bottom:20px; border-bottom:2px solid var(--g-800);
  }

  .credential-banner{
    background: linear-gradient(150deg, var(--g-900), var(--g-700));
    color:#EAF8EE; padding:26px 28px; border-radius:12px; margin:22px 0;
    box-shadow:0 10px 28px rgba(11,61,31,0.25);
  }
  .credential-banner h3{ font-family:'Fraunces', serif; font-size:18px; color:#fff; margin-bottom:6px; }
  .credential-banner p{ font-size:12.5px; color:#9FC3AC; margin-bottom:16px; }
  .credential-row{
    display:flex; align-items:center; gap:14px; background:rgba(255,255,255,0.08);
    padding:14px 18px; border-radius:8px; margin-bottom:10px;
  }
  .credential-row .k{ font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color:#9FC3AC; font-weight:700; width:90px; flex-shrink:0; }
  .credential-row .v{ font-family:'IBM Plex Mono', monospace; font-size:15px; font-weight:700; color:#fff; letter-spacing:0.5px; }
  .credential-warn{
    font-size:11.5px; color:#F3C744; margin-top:12px; line-height:1.5;
  }

  .banner-success{
    background:var(--g-100); border:1.5px solid #BFE7CC; color:var(--g-700);
    padding:14px 18px; border-radius:10px; margin:20px 0;
    display:flex; align-items:center; gap:10px; font-size:13px; font-weight:600;
  }

  .app-card{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:22px 26px; margin-bottom:16px;
    box-shadow:0 4px 16px rgba(11,61,31,0.06);
    display:flex; gap:20px;
  }
  .app-logo{
    width:64px; height:64px; border-radius:10px; flex-shrink:0;
    background:var(--g-100); border:1.5px solid var(--line);
    display:flex; align-items:center; justify-content:center;
    overflow:hidden;
  }
  .app-logo img{ width:100%; height:100%; object-fit:cover; }
  .app-logo .placeholder{ font-family:'Fraunces', serif; font-size:22px; font-weight:700; color:var(--g-500); }

  .app-body{ flex:1; min-width:0; }
  .app-head{ display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:10px; }
  .app-name{ font-family:'Fraunces', serif; font-size:18px; font-weight:700; color:var(--g-800); }
  .app-date{ font-size:11.5px; color:var(--slate-light); font-family:'IBM Plex Mono', monospace; }

  .app-details{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
  .detail-item .k{ font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:4px; }
  .detail-item .v{ font-size:13px; color:var(--ink); }
  .app-message{ font-size:12.5px; color:var(--slate); background:var(--g-100); padding:12px 14px; border-radius:8px; margin-bottom:16px; line-height:1.5; }

  .app-actions{ display:flex; gap:10px; }
  .btn{
    padding:11px 22px; font-size:13px; font-weight:700; border-radius:8px;
    border:none; cursor:pointer; transition:.15s ease; font-family:'Inter', sans-serif;
  }
  .btn-approve{
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    color:#fff; box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  .btn-approve:hover{ filter:brightness(1.08); }
  .btn-reject{
    background:#FDEDED; color:#B42318; border:1.5px solid #F3B8B8;
  }
  .btn-reject:hover{ background:#DC3545; color:#fff; border-color:#DC3545; }

  .empty-state{
    text-align:center; padding:60px 20px; background:var(--white);
    border:1.5px solid var(--line); border-radius:12px;
  }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:17px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .app-details{ grid-template-columns:1fr; }
    .app-card{ flex-direction:column; }
  }
</style>
</head>
<body>

<div class="sidebar">
  <div class="brand">
    <div class="crest">K</div>
    <div class="brand-text">
      <div class="name">Klaso</div>
      <div class="sub">Super Admin</div>
    </div>
  </div>

  <div class="nav-group-label">Platform</div>
  <nav>
    <a href="dashboard.php"><span class="dot"></span>Dashboard</a>
    <a href="review_applications.php" class="active">
      <span class="dot"></span>Applications
      <?php if($pendingApplications > 0){ ?><span class="badge-count"><?php echo $pendingApplications; ?></span><?php } ?>
    </a>
    <a href="schools.php"><span class="dot"></span>Schools</a>
  </nav>

  <div class="sidebar-foot">
    <a href="../auth/logout.php">&#8617; Logout</a>
  </div>
</div>

<div class="main">

  <div class="page-head">
    <div class="eyebrow">Klaso Platform</div>
    <h1>Review Applications</h1>
  </div>

  <?php if($generatedPassword != ""){ ?>
  <div class="credential-banner">
      <h3>&#10003; <?php echo htmlspecialchars($approvedSchoolName); ?> approved</h3>
      <p>Share these login credentials with the school &mdash; this password will not be shown again.</p>
      <div class="credential-row">
        <span class="k">Login URL</span>
        <span class="v">klaso.com/auth/login.php</span>
      </div>
      <div class="credential-row">
        <span class="k">Temp Password</span>
        <span class="v"><?php echo htmlspecialchars($generatedPassword); ?></span>
      </div>
      <div class="credential-warn">&#9888; The admin will be required to set a new password on their first login.</div>
    </div>
  <?php } ?>

  <?php if(isset($_GET['rejected'])){ ?>
    <div class="banner-success">Application rejected.</div>
  <?php } ?>

  <?php if(count($applications) > 0){ ?>
    <?php foreach($applications as $app){ ?>
      <div class="app-card">
        <div class="app-logo">
          <?php if(!empty($app['logo_path'])){ ?>
            <img src="../<?php echo htmlspecialchars($app['logo_path']); ?>" alt="Logo">
          <?php }else{ ?>
            <div class="placeholder"><?php echo strtoupper(substr($app['school_name'],0,1)); ?></div>
          <?php } ?>
        </div>

        <div class="app-body">
          <div class="app-head">
            <div class="app-name"><?php echo htmlspecialchars($app['school_name']); ?></div>
            <div class="app-date">Submitted <?php echo htmlspecialchars($app['submitted_at']); ?></div>
          </div>

          <div class="app-details">
            <div class="detail-item">
              <div class="k">Contact Person</div>
              <div class="v"><?php echo htmlspecialchars($app['contact_name']); ?></div>
            </div>
            <div class="detail-item">
              <div class="k">Email</div>
              <div class="v"><?php echo htmlspecialchars($app['contact_email']); ?></div>
            </div>
            <div class="detail-item">
              <div class="k">Phone</div>
              <div class="v"><?php echo htmlspecialchars($app['contact_phone'] ?: 'Not provided'); ?></div>
            </div>
          </div>

          <?php if(!empty($app['message'])){ ?>
            <div class="app-message"><?php echo htmlspecialchars($app['message']); ?></div>
          <?php } ?>

          <div class="app-actions">
            <form method="POST" onsubmit="return confirm('Approve this school and create their admin account?');">
              <input type="hidden" name="approve_id" value="<?php echo $app['id']; ?>">
              <button type="submit" class="btn btn-approve">Approve</button>
            </form>
            <form method="POST" onsubmit="return confirm('Reject this application?');">
              <input type="hidden" name="reject_id" value="<?php echo $app['id']; ?>">
              <button type="submit" class="btn btn-reject">Reject</button>
            </form>
          </div>
        </div>
      </div>
    <?php } ?>
  <?php }else{ ?>
    <div class="empty-state">
      <h3>No pending applications</h3>
      <p>New school applications will appear here for review.</p>
    </div>
  <?php } ?>

</div>

</body>
</html>
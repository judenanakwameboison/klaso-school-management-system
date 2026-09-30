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

$stmt = $conn->prepare("
    SELECT s.*, c.class_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.id = ? AND s.school_id = ?
");
$stmt->execute([$id, $school_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: students.php");
    exit();
}

/* Fee summary for this student, scoped */
$feeStmt = $conn->prepare("
    SELECT IFNULL(SUM(amount_due),0) AS total_due, IFNULL(SUM(amount_paid),0) AS total_paid
    FROM fees
    WHERE student_id = ? AND school_id = ?
");
$feeStmt->execute([$id, $school_id]);
$fees = $feeStmt->fetch(PDO::FETCH_ASSOC);
$balance = $fees['total_due'] - $fees['total_paid'];

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Student - Klaso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --g-900:#06210F; --g-800:#0B3D1F; --g-700:#12592C; --g-600:#1B7A3E;
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
  .brand-text .name{ font-family:'Fraunces', serif; font-size:16px; font-weight:700; line-height:1.2; }
  .brand-text .sub{ font-size:10px; letter-spacing:1.4px; text-transform:uppercase; color:var(--g-400); margin-top:3px; font-weight:600; }
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
  .sidebar-foot{margin-top:auto; padding-top:20px;
    border-top:1px solid rgba(52,197,102,0.25); font-size:12px;
  }
  .sidebar-foot a{ display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:7px; color:#9FC3AC; font-weight:500; }
  .sidebar-foot a:hover{ background:rgba(255,255,255,0.06); color:#fff; }

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:820px; }

  .page-head{ margin-bottom:22px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; }
  .eyebrow{
    font-size:11px; letter-spacing:2.4px; text-transform:uppercase;
    color:var(--g-500); font-weight:700; margin-bottom:10px;
  }
  .page-head h1{
    font-family:'Fraunces', serif; font-weight:700; font-size:30px;
    color:var(--g-800); letter-spacing:-0.3px;
  }
  .head-row{ border-bottom:2px solid var(--g-800); padding-bottom:20px; margin-bottom:0; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; width:100%; }

  .back-link{
    font-size:12.5px; color:var(--g-700); font-weight:700; display:inline-flex; align-items:center; gap:6px; margin-bottom:18px;
  }
  .back-link:hover{ text-decoration:underline; }

  .profile-card{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:30px 32px; margin:22px 0;
    box-shadow:0 8px 24px rgba(11,61,31,0.08);
  }
  .profile-head{ display:flex; align-items:center; gap:16px; margin-bottom:26px; padding-bottom:22px; border-bottom:1px solid var(--line); }
  .avatar{
    width:56px; height:56px; border-radius:50%; flex-shrink:0;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    display:flex; align-items:center; justify-content:center;
    font-family:'Fraunces', serif; font-weight:700; font-size:20px; color:#fff;
  }
  .profile-name{ font-family:'Fraunces', serif; font-size:20px; font-weight:700; color:var(--g-800); }
  .profile-sub{ font-size:12.5px; color:var(--slate); margin-top:3px; font-family:'IBM Plex Mono', monospace; }

  .detail-grid{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:26px; }
  .detail-item .k{ font-size:10.5px; letter-spacing:1px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:6px; }
  .detail-item .v{ font-size:14px; color:var(--ink); font-weight:600; }

  .fee-summary{
    background:var(--g-100); border:1.5px solid var(--line); border-radius:10px;
    padding:18px 20px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;
  }
  .fee-item .k{ font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:6px; }
  .fee-item .v{ font-family:'IBM Plex Mono', monospace; font-size:16px; font-weight:700; color:var(--g-800); }
  .fee-item.balance .v{ color:#B42318; }
  .fee-item.balance.clear .v{ color:var(--g-700); }

  .actions-row{ display:flex; gap:12px; margin-top:24px; }
  .btn{
    padding:12px 22px; font-size:13px; font-weight:700; border-radius:8px;
    border:none; cursor:pointer; transition:.15s ease; font-family:'Inter', sans-serif;
  }
  .btn-edit{ background:#FFF7E0; color:#8A6300; border:1.5px solid #F3E3AC; }
  .btn-edit:hover{ background:#F3C744; color:#3D2E00; }
  .btn-secondary{ background:var(--g-100); color:var(--g-800); border:1.5px solid var(--line); }
  .btn-secondary:hover{ background:var(--line); }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .detail-grid{ grid-template-columns:1fr; }
    .fee-summary{ grid-template-columns:1fr; }
  }
</style>
</head>
<body>

<div class="sidebar">
  <div class="brand">
    <div class="crest">K</div>
    <div class="brand-text">
      <div class="name"><?php echo htmlspecialchars($school['school_name']); ?></div>
      <div class="sub">Admin Console</div>
    </div>
  </div>

  <div class="nav-group-label">Overview</div>
  <nav><a href="dashboard.php"><span class="dot"></span>Dashboard</a></nav>

  <div class="nav-group-label">Academics</div>
  <nav>
    <a href="students.php" class="active"><span class="dot"></span>Students</a>
    <a href="teachers.php"><span class="dot"></span>Teachers</a>
    <a href="classes.php"><span class="dot"></span>Classes</a>
    <a href="subjects.php"><span class="dot"></span>Subjects</a>
  </nav>

  <div class="nav-group-label">Records</div>
  <nav>
    <a href="fees.php"><span class="dot"></span>Fees</a>
  </nav>
  <div class="nav-group-label">Library</div>
  <nav>
    <a href="library_books.php" class="active"><span class="dot"></span>Books Available</a>
    <a href="issue_book.php"><span class="dot"></span>Issue Book</a>
    <a href="return_book.php"><span class="dot"></span>Return Book</a>
  </nav>

  <div class="sidebar-foot">
    <a href="../auth/logout.php">&#8617; Logout</a>
  </div>
</div>\++

<div class="main">

  <a href="students.php" class="back-link">&larr; Back to Students</a>

  <div class="head-row">
    <div>
      <div class="eyebrow">Academics &middot; Students</div>
      <h1>Student Profile</h1>
    </div>
  </div>

  <div class="profile-card">
    <div class="profile-head">
      <div class="avatar"><?php echo strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1)); ?></div>
      <div>
        <div class="profile-name"><?php echo htmlspecialchars($student['first_name'].($student['middle_name'] ? ' '.$student['middle_name'] : '').' '.$student['last_name']); ?></div>
        <div class="profile-sub"><?php echo htmlspecialchars($student['admission_number']); ?></div>
      </div>
    </div>

    <div class="detail-grid">
      <div class="detail-item">
        <div class="k">Class</div>
        <div class="v"><?php echo $student['class_name'] ? htmlspecialchars($student['class_name']) : 'Not assigned'; ?></div>
      </div>
      <div class="detail-item">
        <div class="k">Status</div>
        <div class="v"><?php echo htmlspecialchars($student['status']); ?></div>
      </div>
      <div class="detail-item">
        <div class="k">Promotion Status</div>
        <div class="v"><?php echo htmlspecialchars($student['promotion_status']); ?></div>
      </div>
      <div class="detail-item">
        <div class="k">Enrolled</div>
        <div class="v"><?php echo htmlspecialchars($student['created_at']); ?></div>
      </div>
    </div>

    <div class="fee-summary">
      <div class="fee-item">
        <div class="k">Total Due</div>
        <div class="v">GHS <?php echo number_format($fees['total_due'], 2); ?></div>
      </div>
      <div class="fee-item">
        <div class="k">Total Paid</div>
        <div class="v">GHS <?php echo number_format($fees['total_paid'], 2); ?></div>
      </div>
      <div class="fee-item balance <?php echo $balance <= 0 ? 'clear' : ''; ?>">
        <div class="k">Balance</div>
        <div class="v">GHS <?php echo number_format($balance, 2); ?></div>
      </div>
    </div>

    <div class="actions-row">
      <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-edit">Edit Student</a>
      <a href="students.php" class="btn btn-secondary">Back to List</a>
    </div>
  </div>

</div>

</body>
</html>
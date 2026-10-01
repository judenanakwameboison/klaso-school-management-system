<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];
$admin_name = $_SESSION['full_name'];

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

/* Core stats */
$totalStudents = $conn->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
$totalStudents->execute([$school_id]);
$totalStudents = $totalStudents->fetchColumn();

$totalTeachers = $conn->prepare("SELECT COUNT(*) FROM teachers WHERE school_id = ?");
$totalTeachers->execute([$school_id]);
$totalTeachers = $totalTeachers->fetchColumn();

$totalClasses = $conn->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$totalClasses->execute([$school_id]);
$totalClasses = $totalClasses->fetchColumn();

$totalCollected = $conn->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM fees WHERE school_id = ?");
$totalCollected->execute([$school_id]);
$totalCollected = $totalCollected->fetchColumn();

$totalDue = $conn->prepare("SELECT IFNULL(SUM(amount_due),0) FROM fees WHERE school_id = ?");
$totalDue->execute([$school_id]);
$totalDue = $totalDue->fetchColumn();

$totalOutstanding = $totalDue - $totalCollected;
if ($totalOutstanding < 0) { $totalOutstanding = 0; }

/* Enrollment growth: this month vs last month */
$thisMonthCount = $conn->prepare("
    SELECT COUNT(*) FROM students
    WHERE school_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
");
$thisMonthCount->execute([$school_id]);
$thisMonthCount = $thisMonthCount->fetchColumn();

$lastMonthCount = $conn->prepare("
    SELECT COUNT(*) FROM students
    WHERE school_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m')
");
$lastMonthCount->execute([$school_id]);
$lastMonthCount = $lastMonthCount->fetchColumn();

if ($lastMonthCount > 0) {
    $growthPercent = round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100);
} elseif ($thisMonthCount > 0) {
    $growthPercent = 100;
} else {
    $growthPercent = 0;
}

/* Class distribution */
$classDist = $conn->prepare("
    SELECT c.class_name, COUNT(s.id) AS total
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id AND s.school_id = c.school_id
    WHERE c.school_id = ?
    GROUP BY c.id
    ORDER BY c.class_name
");
$classDist->execute([$school_id]);
$classDist = $classDist->fetchAll(PDO::FETCH_ASSOC);

/* Fee collection trend — last 6 months */
$monthLabels = [];
$monthTotals = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('M', strtotime("-$i months"));
    $ym = date('Y-m', strtotime("-$i months"));
    $monthLabels[] = $label;

    $stmt = $conn->prepare("
        SELECT IFNULL(SUM(amount_paid),0)
        FROM fees
        WHERE school_id = ? AND DATE_FORMAT(payment_date, '%Y-%m') = ?
    ");
    $stmt->execute([$school_id, $ym]);
    $monthTotals[] = (float)$stmt->fetchColumn();
}

/* Recent activity feed */
$recentStudentsRaw = $conn->prepare("
    SELECT 'student' AS type, first_name, last_name, admission_number AS ref, created_at AS event_time
    FROM students
    WHERE school_id = ?
    ORDER BY id DESC LIMIT 5
");
$recentStudentsRaw->execute([$school_id]);
$recentStudentsRaw = $recentStudentsRaw->fetchAll(PDO::FETCH_ASSOC);

$recentPaymentsRaw = $conn->prepare("
    SELECT 'payment' AS type, s.first_name, s.last_name, f.receipt_number AS ref, f.amount_paid, f.created_at AS event_time
    FROM fees f
    INNER JOIN students s ON s.id = f.student_id
    WHERE f.school_id = ? AND f.amount_paid > 0
    ORDER BY f.id DESC LIMIT 5
");
$recentPaymentsRaw->execute([$school_id]);
$recentPaymentsRaw = $recentPaymentsRaw->fetchAll(PDO::FETCH_ASSOC);

$activity = array_merge($recentStudentsRaw, $recentPaymentsRaw);
usort($activity, function($a, $b) {
    return strtotime($b['event_time']) - strtotime($a['event_time']);
});
$activity = array_slice($activity, 0, 6);

/* Outstanding fees leaderboard */$topDebtors = $conn->prepare("
    SELECT s.id, s.first_name, s.last_name, s.admission_number,
           IFNULL(SUM(f.amount_due),0) - IFNULL(SUM(f.amount_paid),0) AS balance
    FROM students s
    LEFT JOIN fees f ON f.student_id = s.id AND f.school_id = s.school_id
    WHERE s.school_id = ?
    GROUP BY s.id
    HAVING balance > 0
    ORDER BY balance DESC
    LIMIT 5
");
$topDebtors->execute([$school_id]);
$topDebtors = $topDebtors->fetchAll(PDO::FETCH_ASSOC);

/* Unassigned students */
$unassigned = $conn->prepare("
    SELECT id, first_name, last_name, admission_number
    FROM students
    WHERE school_id = ? AND class_id IS NULL
    ORDER BY id DESC
    LIMIT 5
");
$unassigned->execute([$school_id]);
$unassigned = $unassigned->fetchAll(PDO::FETCH_ASSOC);

$unassignedCount = $conn->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND class_id IS NULL");
$unassignedCount->execute([$school_id]);
$unassignedCount = $unassignedCount->fetchColumn();

/* Greeting */
$hour = (int)date('G');
if ($hour < 12) { $greeting = "Good morning"; }
elseif ($hour < 17) { $greeting = "Good afternoon"; }
else { $greeting = "Good evening"; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - Klaso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
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
  .sidebar-foot{
    margin-top:auto; padding-top:20px;
    border-top:1px solid rgba(52,197,102,0.25); font-size:12px;
  }
  .sidebar-foot a{ display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:7px; color:#9FC3AC; font-weight:500; }
  .sidebar-foot a:hover{ background:rgba(255,255,255,0.06); color:#fff; }

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:1440px; }

  .hero{
    background: linear-gradient(135deg, var(--g-900), var(--g-700));
    border-radius:16px; padding:32px 36px; margin-bottom:28px;
    position:relative; overflow:hidden;
    box-shadow:0 16px 40px rgba(11,61,31,0.25);
    display:flex; justify-content:space-between; align-items:center; gap:24px; flex-wrap:wrap;
  }
  .hero::before{
    content:''; position:absolute; top:-40%; right:-10%; width:320px; height:320px;
    background: radial-gradient(circle, rgba(52,197,102,0.35), transparent 70%);
  }
  .hero-content{ position:relative; z-index:1; }
  .hero-eyebrow{ font-size:11px; letter-spacing:2.2px; text-transform:uppercase; color:var(--g-400); font-weight:700; margin-bottom:8px; }
  .hero h1{ font-family:'Fraunces', serif; font-size:28px; font-weight:700; color:#fff; margin-bottom:6px; }
  .hero p{ font-size:13.5px; color:#B8D9C2; }

  .live-clock{
    position:relative; z-index:1; text-align:right;
  }
  .live-time{
    font-family:'IBM Plex Mono', monospace; font-size:26px; font-weight:700; color:#fff;
    letter-spacing:1px; line-height:1;
  }
  .live-date{
    font-size:11.5px; color:#B8D9C2; margin-top:6px; font-weight:600;
    letter-spacing:0.3px;
  }

  .search-form{ position:relative; z-index:1; width:100%; }
  .search-form input{
    width:280px; padding:13px 16px; font-size:13.5px; border-radius:10px;
    border:1.5px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.1);
    color:#fff; font-family:'Inter', sans-serif;
  }
  .search-form input::placeholder{ color:#B8D9C2; }
  .search-form input:focus{ outline:none; background:rgba(255,255,255,0.16); border-color:var(--g-400); }

  .stat-grid{ display:grid; grid-template-columns:repeat(5, 1fr); gap:16px; margin-bottom:28px; }
  .stat{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:20px 20px 18px; box-shadow:0 4px 16px rgba(11,61,31,0.06);
    transition:.2s ease;
  }
  .stat:hover{ transform:translateY(-3px); box-shadow:0 14px 28px rgba(11,61,31,0.14); }
  .stat .label{ font-size:10.5px; letter-spacing:1.1px; text-transform:uppercase; color:var(--slate); margin-bottom:12px; font-weight:700; }
  .stat .value{ font-family:'IBM Plex Mono', monospace; font-size:24px; font-weight:700; color:var(--g-800); line-height:1.1; }
  .stat .delta{ font-size:11px; font-weight:700; margin-top:8px; display:flex; align-items:center; gap:4px; }
  .delta.up{ color:var(--g-600); }
  .delta.down{ color:#B42318; }
  .delta.flat{ color:var(--slate-light); }
  .stat.money{ background: linear-gradient(150deg, var(--g-800), var(--g-700)); border-top-color:var(--g-400); }
  .stat.money .label{ color:#9FC3AC; }
  .stat.money .value{ color:#fff; }
  .stat.warn{ border-top-color:#DC3545; }
  .stat.warn .value{ color:#B42318; }

  .grid-2col{ display:grid; grid-template-columns:1.4fr 1fr; gap:20px; margin-bottom:20px; }
  .grid-2col-even{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }

  .panel{
    background:var(--white); border:1.5px solid var(--line); border-radius:12px;
    padding:24px 26px; box-shadow:0 6px 20px rgba(11,61,31,0.07);
  }
  .panel-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
  .panel-title{ font-family:'Fraunces', serif; font-size:16px; font-weight:700; color:var(--g-800); }
  .panel-link{ font-size:11.5px; font-weight:700; color:var(--g-600); }
  .panel-link:hover{ text-decoration:underline; }

  .quick-actions{ display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; margin-bottom:28px; }
  .qa-card{
    background:var(--white); border:1.5px solid var(--line); border-radius:12px;
    padding:18px 20px; display:flex; align-items:center; gap:14px;
    transition:.15s ease; box-shadow:0 4px 12px rgba(11,61,31,0.05);
  }
  .qa-card:hover{ border-color:var(--g-500); background:var(--g-100); transform:translateY(-2px); }
  .qa-icon{
    width:38px; height:38px; border-radius:10px; flex-shrink:0;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; font-weight:700;
  }
  .qa-label{ font-size:13px; font-weight:700; color:var(--g-800); }
  .qa-sub{ font-size:11px; color:var(--slate); margin-top:2px; }

  .activity-item{
    display:flex; align-items:center; gap:12px; padding:12px 0;
    border-bottom:1px solid var(--line);
  }
  .activity-item:last-child{ border-bottom:none; padding-bottom:0; }
  .activity-icon{
    width:34px; height:34px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; color:#fff;
  }
  .activity-icon.student{ background: linear-gradient(135deg, var(--g-500), var(--g-700)); }
  .activity-icon.payment{ background: linear-gradient(135deg, #F3C744, #C9971E); }
  .activity-text{ font-size:13px; color:var(--ink); font-weight:600; }
  .activity-sub{ font-size:11.5px; color:var(--slate-light); font-family:'IBM Plex Mono', monospace; margin-top:2px; }
  .activity-time{ margin-left:auto; font-size:10.5px; color:var(--slate-light); white-space:nowrap; }

  .debtor-row{
    display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid var(--line);
  }
  .debtor-row:last-child{ border-bottom:none; }
  .debtor-avatar{
    width:32px; height:32px; border-radius:50%; flex-shrink:0;
    background:var(--g-100); color:var(--g-700); font-size:11px; font-weight:800;
    display:flex; align-items:center; justify-content:center; font-family:'Fraunces', serif;
  }
  .debtor-name{ font-size:13px; font-weight:700; color:var(--g-800); }
  .debtor-admission{ font-size:10.5px; color:var(--slate-light); font-family:'IBM Plex Mono', monospace; }
  .debtor-balance{ margin-left:auto; font-family:'IBM Plex Mono', monospace; font-size:13px; font-weight:700; color:#B42318; }

  .alert-row{
    display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid var(--line);
  }
  .alert-row:last-child{ border-bottom:none; }
  .alert-name{ font-size:13px; font-weight:700; color:var(--g-800); }
  .alert-admission{ font-size:10.5px; color:var(--slate-light); font-family:'IBM Plex Mono', monospace; }
  .fix-link{
    margin-left:auto; font-size:11px; font-weight:700; color:var(--g-600);
    background:var(--g-100); padding:5px 12px; border-radius:20px; border:1px solid #BFE7CC;
  }
  .fix-link:hover{ background:var(--g-600); color:#fff; }

  .banner-warning{
    background:#FFF7E0; border:1.5px solid #F3E3AC; color:#8A6300;
    padding:14px 18px; border-radius:10px; margin-bottom:20px;
    font-size:13px; font-weight:600; display:flex; align-items:center; gap:10px;
  }

  .chart-wrap{ position:relative; height:220px; }

  .empty-state{ text-align:center; padding:30px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:14px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:11.5px; color:var(--slate-light); margin-top:4px; }

  @media (max-width:1200px){
    .stat-grid{ grid-template-columns:repeat(2, 1fr); }
    .grid-2col, .grid-2col-even{ grid-template-columns:1fr; }
    .quick-actions{ grid-template-columns:1fr 1fr; }
  }
  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .hero{ flex-direction:column; align-items:flex-start; }
    .live-clock{ text-align:left; }
    .search-form input{ width:100%; }
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
  <nav><a href="dashboard.php" class="active"><span class="dot"></span>Dashboard</a></nav>

  <div class="nav-group-label">Academics</div>
  <nav>
    <a href="students.php"><span class="dot"></span>Students</a>
    <a href="teachers.php"><span class="dot"></span>Teachers</a>
    <a href="classes.php"><span class="dot"></span>Classes</a>
    <a href="subjects.php"><span class="dot"></span>Subjects</a>
    <a href="attendance_report.php" class="active"><span class="dot"></span>Attendance Report</a>
        <a href="exams.php" class="active"><span class="dot"></span>Exams</a>
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
</div>

<div class="main">

  <div class="hero">
    <div class="hero-content">
      <div class="hero-eyebrow"><?php echo date('l, F j, Y'); ?></div>
      <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></h1>
      <p>Here's what's happening at <?php echo htmlspecialchars($school['school_name']); ?> today.</p>
    </div>
    <div class="live-clock">
      <div class="live-time" id="liveTime">--:--:--</div>
      <div class="live-date" id="liveDate">Loading...</div>
    </div>
    <form action="students.php" method="GET" class="search-form">
      <input type="text" name="search" placeholder="Quick search a student...">
    </form>
  </div>

  <?php if($unassignedCount > 0){ ?>
    <div class="banner-warning">
      &#9888; <?php echo $unassignedCount; ?> student<?php echo $unassignedCount != 1 ? 's are' : ' is'; ?> not assigned to any class yet — see the alert panel below.
    </div>
  <?php } ?>

  <div class="stat-grid">
    <div class="stat">
      <div class="label">Students</div>
      <div class="value"><?php echo $totalStudents; ?></div>
      <div class="delta <?php echo $growthPercent > 0 ? 'up' : ($growthPercent < 0 ? 'down' : 'flat'); ?>">
        <?php echo $growthPercent > 0 ? '&#8593;' : ($growthPercent < 0 ? '&#8595;' : '&mdash;'); ?>
        <?php echo abs($growthPercent); ?>% this month
      </div>
    </div>
    <div class="stat">
      <div class="label">Teachers</div>
      <div class="value"><?php echo $totalTeachers; ?></div>
    </div>
    <div class="stat">
      <div class="label">Classes</div>
      <div class="value"><?php echo $totalClasses; ?></div>
    </div>
    <div class="stat money">
      <div class="label">Fees Collected</div>
      <div class="value">GHS <?php echo number_format($totalCollected, 2); ?></div>
    </div>
    <div class="stat warn">
      <div class="label">Outstanding</div>
      <div class="value">GHS <?php echo number_format($totalOutstanding, 2); ?></div>
    </div>
  </div>

  <div class="quick-actions">
    <a href="students.php" class="qa-card">
      <div class="qa-icon">+</div>
      <div>
        <div class="qa-label">Add Student</div>
        <div class="qa-sub">Enroll a new student</div>
      </div>
    </a>
    <a href="teachers.php" class="qa-card">
      <div class="qa-icon">+</div>
      <div>
        <div class="qa-label">Add Teacher</div>
        <div class="qa-sub">Register a staff member</div>
      </div>
    </a>
    <a href="fees.php" class="qa-card">
      <div class="qa-icon">GHS</div>
      <div>
        <div class="qa-label">Record Payment</div>
        <div class="qa-sub">Log a fee payment</div>
      </div>
    </a>
    <a href="classes.php" class="qa-card">
      <div class="qa-icon">+</div>
      <div>
        <div class="qa-label">Add Class</div>
        <div class="qa-sub">Create a new class</div>
      </div>
    </a>
  </div>

  <div class="grid-2col">
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title">Fee Collection &mdash; Last 6 Months</div>
      </div>
      <div class="chart-wrap">
        <canvas id="feeChart"></canvas>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Recent Activity</div>
      <?php if(count($activity) > 0){ ?>
        <?php foreach($activity as $a){ ?>
          <div class="activity-item">
            <div class="activity-icon <?php echo $a['type']; ?>">
              <?php echo $a['type'] == 'student' ? 'NEW' : 'GHS'; ?>
            </div>
            <div>
              <?php if($a['type'] == 'student'){ ?>
                <div class="activity-text"><?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?> enrolled</div>
                <div class="activity-sub"><?php echo htmlspecialchars($a['ref']); ?></div>
              <?php }else{ ?>
                <div class="activity-text"><?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?> paid GHS <?php echo number_format($a['amount_paid'],2); ?></div>
                <div class="activity-sub"><?php echo htmlspecialchars($a['ref']); ?></div>
              <?php } ?>
            </div>
            <div class="activity-time"><?php echo date('M j', strtotime($a['event_time'])); ?></div>
          </div>
        <?php } ?>
      <?php }else{ ?>
        <div class="empty-state">
          <h3>No recent activity</h3>
          <p>New students and payments will show up here.</p>
        </div>
      <?php } ?>
    </div>
  </div>

  <div class="grid-2col-even">
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title">Outstanding Fees Leaderboard</div>
        <a href="fees.php" class="panel-link">View all &rarr;</a>
      </div>
      <?php if(count($topDebtors) > 0){ ?>
        <?php foreach($topDebtors as $d){ ?>
          <div class="debtor-row">
            <div class="debtor-avatar"><?php echo strtoupper(substr($d['first_name'],0,1).substr($d['last_name'],0,1)); ?></div>
            <div>
              <div class="debtor-name"><?php echo htmlspecialchars($d['first_name'].' '.$d['last_name']); ?></div>
              <div class="debtor-admission"><?php echo htmlspecialchars($d['admission_number']); ?></div>
            </div>
            <div class="debtor-balance">GHS <?php echo number_format($d['balance'], 2); ?></div>
          </div>
        <?php } ?>
      <?php }else{ ?>
        <div class="empty-state">
          <h3>No outstanding balances</h3>
          <p>Every student is fully paid up.</p>
        </div>
      <?php } ?>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title">Unassigned Students</div>
        <a href="students.php" class="panel-link">View all &rarr;</a>
      </div>
      <?php if(count($unassigned) > 0){ ?>
        <?php foreach($unassigned as $u){ ?>
          <div class="alert-row">
            <div>
              <div class="alert-name"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></div>
              <div class="alert-admission"><?php echo htmlspecialchars($u['admission_number']); ?></div>
            </div>
            <a href="edit_student.php?id=<?php echo $u['id']; ?>" class="fix-link">Assign Class</a>
          </div>
        <?php } ?>
      <?php }else{ ?>
        <div class="empty-state">
          <h3>All students assigned</h3>
          <p>Every student belongs to a class.</p>
        </div>
      <?php } ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title">Students Per Class</div>
    <div class="chart-wrap">
      <canvas id="classChart"></canvas>
    </div>
  </div>

</div>

<script>
const feeCtx = document.getElementById('feeChart');
new Chart(feeCtx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode($monthLabels); ?>,
    datasets: [{
      label: 'GHS Collected',
      data: <?php echo json_encode($monthTotals); ?>,
      borderColor: '#1FA24C',
      backgroundColor: 'rgba(31,162,76,0.1)',
      fill: true,
      tension: 0.35,
      pointBackgroundColor: '#12592C',
      pointRadius: 4
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#DCEFE1' } },
      x: { grid: { display: false } }
    }
  }
});

const classCtx = document.getElementById('classChart');
new Chart(classCtx, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode(array_column($classDist, 'class_name')); ?>,
    datasets: [{
      label: 'Students',
      data: <?php echo json_encode(array_column($classDist, 'total')); ?>,
      backgroundColor: '#1FA24C',
      borderRadius: 6,
      maxBarThickness: 42
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#DCEFE1' } },
      x: { grid: { display: false } }
    }
  }
});

function updateLiveClock() {
  const now = new Date();
  const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
  const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

  document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-US', timeOptions);
  document.getElementById('liveDate').textContent = now.toLocaleDateString('en-US', dateOptions);
}

updateLiveClock();
setInterval(updateLiveClock, 1000);
</script>

</body>
</html>
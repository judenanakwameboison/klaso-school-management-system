<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_superadmin.php";
require_once "../config/db.php";

/* Platform-wide stats */
$totalSchools = $conn->query("SELECT COUNT(*) FROM schools WHERE status = 'Active'")->fetchColumn();
$pendingApplications = $conn->query("SELECT COUNT(*) FROM school_applications WHERE status = 'Pending'")->fetchColumn();
$suspendedSchools = $conn->query("SELECT COUNT(*) FROM schools WHERE status = 'Suspended'")->fetchColumn();

$totalStudents = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = $conn->query("SELECT COUNT(*) FROM teachers")->fetchColumn();

/* Recently approved schools */
$recentSchools = $conn->query("
    SELECT school_name, school_code, status, approved_at
    FROM schools
    ORDER BY id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Super Admin Dashboard - Klaso</title>
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

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:1360px; }

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

  .stat-grid{ display:grid; grid-template-columns:repeat(5, 1fr); gap:16px; margin:24px 0 32px; }
  .stat{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:20px 20px 18px; box-shadow:0 4px 16px rgba(11,61,31,0.06);
    transition:.2s ease;
  }
  .stat:hover{ transform:translateY(-3px); box-shadow:0 14px 28px rgba(11,61,31,0.14); }
  .stat .label{ font-size:10.5px; letter-spacing:1.1px; text-transform:uppercase; color:var(--slate); margin-bottom:12px; font-weight:700; }
  .stat .value{ font-family:'IBM Plex Mono', monospace; font-size:26px; font-weight:700; color:var(--g-800); line-height:1.1; }
  .stat.attention{ border-top-color:#B42318; }
  .stat.attention .value{ color:#B42318; }

  .section-title{ display:flex; align-items:baseline; justify-content:space-between; margin-bottom:16px; }
  .section-title h2{ font-family:'Fraunces', serif; font-size:18px; font-weight:700; color:var(--g-800); }

  .table-card{
    background:var(--white); border:1.5px solid var(--line); border-radius:12px;
    overflow:hidden; box-shadow:0 6px 20px rgba(11,61,31,0.07);
  }
  table{ width:100%; border-collapse:collapse; }
  thead tr{ background: linear-gradient(90deg, var(--g-800), var(--g-700)); }
  th{
    color:#fff; padding:14px 18px; font-size:10.5px; letter-spacing:1.1px;
    text-transform:uppercase; font-weight:700; text-align:left;
  }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .school-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }

  .status-pill{
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 12px 4px 9px; border-radius:20px; font-size:11px; font-weight:700;
  }
  .status-active{ background:var(--g-100); color:var(--g-700); border:1px solid #BFE7CC; }
  .status-active::before{ content:''; width:6px; height:6px; border-radius:50%; background:var(--g-500); }
  .status-suspended{ background:#FDEDED; color:#B42318; border:1px solid #F3B8B8; }
  .status-suspended::before{ content:''; width:6px; height:6px; border-radius:50%; background:#B42318; }
  .status-pending{ background:#FFF7E0; color:#8A6300; border:1px solid #F3E3AC; }
  .status-pending::before{ content:''; width:6px; height:6px; border-radius:50%; background:#F3C744; }

  .empty-state{ text-align:center; padding:50px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:16px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:1100px){
    .stat-grid{ grid-template-columns:repeat(3, 1fr); }
  }
  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .stat-grid{ grid-template-columns:repeat(2, 1fr); }
    .table-card{ overflow-x:auto; }
    table{ min-width:600px; }
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
    <a href="dashboard.php" class="active"><span class="dot"></span>Dashboard</a>
    <a href="review_applications.php">
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
    <h1>Super Admin Dashboard</h1>
  </div>

  <div class="stat-grid">
    <div class="stat">
      <div class="label">Active Schools</div>
      <div class="value"><?php echo $totalSchools; ?></div>
    </div>
    <div class="stat <?php echo $pendingApplications > 0 ? 'attention' : ''; ?>">
      <div class="label">Pending Applications</div>
      <div class="value"><?php echo $pendingApplications; ?></div>
    </div>
    <div class="stat">
      <div class="label">Suspended Schools</div>
      <div class="value"><?php echo $suspendedSchools; ?></div>
    </div>
    <div class="stat">
      <div class="label">Total Students</div>
      <div class="value"><?php echo $totalStudents; ?></div>
    </div>
    <div class="stat">
      <div class="label">Total Teachers</div>
      <div class="value"><?php echo $totalTeachers; ?></div>
    </div>
  </div>

  <div class="section-title">
    <h2>Recently Onboarded Schools</h2>
  </div>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>School Name</th>
          <th>Code</th>
          <th>Status</th>
          <th>Approved</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($recentSchools) > 0){ ?>
          <?php foreach($recentSchools as $s){ ?>
            <tr>
              <td><span class="school-name"><?php echo htmlspecialchars($s['school_name']); ?></span></td>
              <td class="mono"><?php echo htmlspecialchars($s['school_code']); ?></td>
              <td>
                <?php if($s['status'] == 'Active'){ ?>
                  <span class="status-pill status-active">Active</span>
                <?php }elseif($s['status'] == 'Suspended'){ ?>
                  <span class="status-pill status-suspended">Suspended</span>
                <?php }else{ ?>
                  <span class="status-pill status-pending">Pending</span>
                <?php } ?>
              </td>
              <td><?php echo $s['approved_at'] ? htmlspecialchars($s['approved_at']) : '&mdash;'; ?></td>
            </tr>
          <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="4">
              <div class="empty-state">
                <h3>No schools onboarded yet</h3>
                <p>Approved schools will appear here.</p>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

</div>

</body>
</html>
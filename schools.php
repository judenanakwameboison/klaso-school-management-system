<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_superadmin.php";
require_once "../config/db.php";

/* Suspend or Reactivate a school */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_id'])) {

    $school_id = (int)$_POST['toggle_id'];
    $newStatus = $_POST['new_status'] == 'Active' ? 'Active' : 'Suspended';

    $stmt = $conn->prepare("UPDATE schools SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $school_id]);

    header("Location: schools.php?updated=1");
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM schools WHERE 1";
$params = [];

if ($search != "") {
    $sql .= " AND (school_name LIKE ? OR school_code LIKE ?)";
    $keyword = "%$search%";
    $params = [$keyword, $keyword];
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalResults = count($schools);

$pendingApplications = $conn->query("SELECT COUNT(*) FROM school_applications WHERE status = 'Pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Schools - Klaso</title>
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
    margin-left:auto; background:var(--g-500); color:#fff; font-size:10.
    5px; font-weight:800;
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

  .banner-success{
    background:var(--g-100); border:1.5px solid #BFE7CC; color:var(--g-700);
    padding:14px 18px; border-radius:10px; margin:20px 0;
    display:flex; align-items:center; gap:10px; font-size:13px; font-weight:600;
  }

  .toolbar{
    display:flex; justify-content:space-between; align-items:center;
    margin:22px 0; flex-wrap:wrap; gap:14px;
  }
  .search-box{ display:flex; gap:10px; }
  .search-box input{
    width:320px; padding:11px 14px; font-size:13.5px;
    background:var(--g-100); border:1.5px solid var(--line); border-radius:8px;
    font-family:'Inter', sans-serif; color:var(--ink);
  }
  .search-box input:focus{
    outline:none; border-color:var(--g-500); background:#fff;
    box-shadow:0 0 0 3px rgba(31,162,76,0.15);
  }
  .search-box button{
    padding:11px 18px; font-size:13px; font-weight:700;
    background:var(--g-700); color:#fff; border:none; border-radius:8px; cursor:pointer;
    transition:.15s ease;
  }
  .search-box button:hover{ background:var(--g-800); }
  .reset-link{
    padding:11px 18px; font-size:13px; font-weight:700;
    background:var(--g-100); color:var(--g-800); border:1.5px solid var(--line); border-radius:8px;
  }
  .reset-link:hover{ background:var(--line); }
  .result-count{ font-size:12px; color:var(--slate); font-family:'IBM Plex Mono', monospace; }
  .result-count strong{ color:var(--g-700); }

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
  th:last-child{ text-align:center; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:last-child{ text-align:center; }
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

  .row-btn{
    padding:7px 14px; font-size:11.5px; font-weight:700; border-radius:6px;
    border:none; cursor:pointer; transition:.15s ease; font-family:'Inter', sans-serif;
  }
  .btn-suspend{ background:#FDEDED; color:#B42318; border:1px solid #F3B8B8; }
  .btn-suspend:hover{ background:#DC3545; color:#fff; border-color:#DC3545; }
  .btn-activate{ background:var(--g-100); color:var(--g-700); border:1px solid #BFE7CC; }
  .btn-activate:hover{ background:var(--g-600); color:#fff; border-color:var(--g-600); }

  .empty-state{ text-align:center; padding:60px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:17px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .table-card{ overflow-x:auto; }
    table{ min-width:700px; }
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
    <a href="review_applications.php">
      <span class="dot"></span>Applications
      <?php if($pendingApplications > 0){ ?><span class="badge-count"><?php echo $pendingApplications; ?></span><?php } ?>
    </a>
    <a href="schools.php" class="active"><span class="dot"></span>Schools</a>
  </nav>

  <div class="sidebar-foot">
    <a href="../auth/logout.php">&#8617; Logout</a>
  </div>
</div>

<div class="main">

  <div class="page-head">
    <div class="eyebrow">Klaso Platform</div>
    <h1>Schools</h1>
  </div>

  <?php if(isset($_GET['updated'])){ ?>
    <div class="banner-success">School status updated.</div>
  <?php } ?>

  <div class="toolbar">
    <form method="GET" class="search-box">
      <input type="text" name="search" placeholder="Search by school name or code..." value="<?php echo htmlspecialchars($search); ?>">
      <button type="submit">Search</button>
      <a href="schools.php" class="reset-link">Reset</a>
    </form>
    <div class="result-count"><strong><?php echo $totalResults; ?></strong> school<?php echo $totalResults != 1 ? 's' : ''; ?> found</div>
  </div>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>School Name</th>
          <th>Code</th>
          <th>Contact Email</th>
          <th>Status</th>
          <th>Approved</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($schools) > 0){ ?>
          <?php foreach($schools as $s){ ?>
            <tr>
              <td><span class="school-name"><?php echo htmlspecialchars($s['school_name']); ?></span></td>
              <td class="mono"><?php echo htmlspecialchars($s['school_code']); ?></td>
              <td><?php echo htmlspecialchars($s['contact_email']); ?></td>
              <td>
                <?php if($s['status'] == 'Active'){ ?>
                  <span class="status-pill status-active">Active</span>
                <?php }else{ ?>
                  <span class="status-pill status-suspended">Suspended</span>
                <?php } ?>
              </td>
              <td><?php echo $s['approved_at'] ? htmlspecialchars($s['approved_at']) : '&mdash;'; ?></td>
              <td>
                <?php if($s['status'] == 'Active'){ ?>
                  <form method="POST" onsubmit="return confirm('Suspend this school? Their admin, teachers, and students will be unable to log in.');">
                    <input type="hidden" name="toggle_id" value="<?php echo $s['id']; ?>">
                    <input type="hidden" name="new_status" value="Suspended">
                    <button type="submit" class="row-btn btn-suspend">Suspend</button>
                  </form>
                <?php }else{ ?>
                  <form method="POST" onsubmit="return confirm('Reactivate this school?');">
                    <input type="hidden" name="toggle_id" value="<?php echo $s['id']; ?>">
                    <input type="hidden" name="new_status" value="Active">
                    <button type="submit" class="row-btn btn-activate">Reactivate</button>
                  </form>
                <?php } ?>
              </td>
            </tr>
            <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <h3>No schools yet</h3>
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
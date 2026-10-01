<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

$error = "";

/* ADD CLASS */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $class_name = trim($_POST['class_name']);
    $class_code = trim($_POST['class_code']);
    $academic_year = trim($_POST['academic_year']);

    if ($class_name == "") {
        $error = "Class name is required.";
    } else {

        /* Prevent duplicate class names within the same school */
        $check = $conn->prepare("SELECT id FROM classes WHERE class_name = ? AND school_id = ?");
        $check->execute([$class_name, $school_id]);

        if ($check->rowCount() > 0) {
            $error = "A class with this name already exists.";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO classes (school_id, class_name, class_code, academic_year)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $school_id,
                $class_name,
                $class_code,
                $academic_year
            ]);

            header("Location: classes.php?added=1");
            exit();
        }
    }
}

/* LOAD EXISTING CLASSES with live student counts, scoped */
$classes = $conn->prepare("
    SELECT
    c.*,
    COUNT(s.id) AS total_students
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id AND s.school_id = c.school_id
    WHERE c.school_id = ?
    GROUP BY c.id
    ORDER BY c.class_name
");
$classes->execute([$school_id]);
$classes = $classes->fetchAll(PDO::FETCH_ASSOC);

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Classes - Klaso</title>
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
  .sidebar-foot{
    margin-top:auto; padding-top:20px;
    border-top:1px solid rgba(52,197,102,0.25); font-size:12px;
  }
  .sidebar-foot a{ display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:7px; color:#9FC3AC; font-weight:500; }
  .sidebar-foot a:hover{ background:rgba(255,255,255,0.06); color:#fff; }

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:1100px; }

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
  .banner-success .icon{
    width:20px; height:20px; border-radius:50%; background:var(--g-500); color:#fff;
    display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0;
  }

  .alert-error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:13px 16px;
    border-radius:8px; margin:20px 0;
    display:flex; align-items:center; gap:8px;
  }
  .alert-error::before{ content:'!'; width:18px; height:18px; border-radius:50%; background:#B42318; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; flex-shrink:0; }

  .form-card{
    background:var(--white);
    border:1.5px solid var(--line);
    border-top:4px solid var(--g-500);
    border-radius:12px;
    padding:28px 30px 26px;
    margin:22px 0 36px;
    box-shadow:0 8px 24px rgba(11,61,31,0.08);
  }
  .form-card h3{
    font-size:11px; letter-spacing:1.6px; text-transform:uppercase; color:var(--g-600);
    font-weight:800; margin-bottom:18px;
    display:flex; align-items:center; gap:8px;
  }
  .form-card h3::before{ content:''; width:8px; height:8px; border-radius:2px; background:var(--g-500); display:inline-block; }

  .form-grid{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; align-items:end; }
  .field label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field input{
    width:100%;
    padding:12px 14px;
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
  .btn{
    padding:12px 20px;
    font-size:13.5px; font-weight:700;
    border:none; border-radius:8px;
    cursor:pointer;
    transition:.15s ease;
    font-family:'Inter', sans-serif;
    white-space:nowrap;
  }
  .btn-primary{
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    color:#fff;
    box-shadow:0 8px 20px rgba(31,162,76,0.3);
    width:100%;
  }
  .btn-primary:hover{ filter:brightness(1.08); transform:translateY(-1px); }

  .section-title{display:flex; align-items:baseline; justify-content:space-between; margin-bottom:16px;
  }
  .section-title h2{ font-family:'Fraunces', serif; font-size:18px; font-weight:700; color:var(--g-800); }
  .count-tag{
    font-size:11.5px; color:#fff; background:var(--g-500); padding:3px 10px;
    border-radius:20px; font-weight:700; font-family:'IBM Plex Mono', monospace;
  }

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
  th:nth-child(4){ text-align:center; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:nth-child(4){ text-align:center; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .class-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }
  .student-count{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:28px; padding:4px 10px; border-radius:20px;
    background:var(--g-100); color:var(--g-700); font-weight:700; font-size:12.5px;
    border:1px solid #BFE7CC;
  }

  .empty-state{ text-align:center; padding:50px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:16px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .form-grid{ grid-template-columns:1fr; }
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
      <div class="name"><?php echo htmlspecialchars($school['school_name']); ?></div>
      <div class="sub">Admin Console</div>
    </div>
  </div>

  <div class="nav-group-label">Overview</div>
  <nav><a href="dashboard.php"><span class="dot"></span>Dashboard</a></nav>

  <div class="nav-group-label">Academics</div>
  <nav>
    <a href="students.php"><span class="dot"></span>Students</a>
    <a href="teachers.php"><span class="dot"></span>Teachers</a>
    <a href="classes.php" class="active"><span class="dot"></span>Classes</a>
    <a href="subjects.php"><span class="dot"></span>Subjects</a>
    <a href="attendance_report.php"><span class="dot"></span>Attendance Report</a>
    <a href="exams.php"><span class="dot"></span>Exams</a>
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

  <div class="page-head">
    <div class="eyebrow">Academics &middot; Classes</div>
    <h1>Add New Class</h1>
  </div>

  <?php if(isset($_GET['added'])){ ?>
    <div class="banner-success">
      <div class="icon">&#10003;</div>
      Class added successfully.
    </div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <div class="form-card">
    <h3>New Class</h3>
    <form method="POST">
      <div class="form-grid">
        <div class="field">
          <label for="class_name">Class Name</label>
          <input type="text" id="class_name" name="class_name" placeholder="e.g. Form 1 Science" required>
        </div>
        <div class="field">
          <label for="class_code">Class Code</label>
          <input type="text" id="class_code" name="class_code" placeholder="e.g. F1SCI">
        </div>
        <div class="field">
          <label for="academic_year">Academic Year</label>
          <input type="text" id="academic_year" name="academic_year" placeholder="e.g. 2026/2027">
        </div>
      </div>
      <br>
      <button type="submit" class="btn btn-primary" style="max-width:220px;">Save Class</button>
    </form>
  </div>

  <div class="section-title">
    <h2>Existing Classes</h2>
    <span class="count-tag"><?php echo count($classes); ?> total</span>
  </div>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Class</th>
          <th>Code</th>
          <th>Academic Year</th>
          <th>Students</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($classes) > 0){ ?>
          <?php foreach($classes as $c){ ?>
            <tr>
              <td><span class="class-name"><?php echo htmlspecialchars($c['class_name']); ?></span></td>
              <td class="mono"><?php echo htmlspecialchars($c['class_code']); ?></td>
              <td><?php echo htmlspecialchars($c['academic_year']); ?></td>
              <td><span class="student-count"><?php echo $c['total_students']; ?></span></td>
            </tr>
          <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="4">
              <div class="empty-state">
                <h3>No classes yet</h3>
                <p>Add your first class using the form above.</p>
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
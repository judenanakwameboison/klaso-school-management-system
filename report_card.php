<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

$classes = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classes->execute([$school_id]);
$classes = $classes->fetchAll(PDO::FETCH_ASSOC);

$exams = $conn->prepare("SELECT id, exam_name FROM exams WHERE school_id = ? ORDER BY exam_name");
$exams->execute([$school_id]);
$exams = $exams->fetchAll(PDO::FETCH_ASSOC);

$selectedClass = $_GET['class_id'] ?? '';

$students = [];
if ($selectedClass != "") {
    $stmt = $conn->prepare("SELECT id, admission_number, first_name, last_name FROM students WHERE class_id = ? AND school_id = ? ORDER BY first_name, last_name");
    $stmt->execute([$selectedClass, $school_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Report Card - Klaso</title>
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

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:680px; }

  .page-head{ margin-bottom:28px; }
  .eyebrow{
    font-size:11px; letter-spacing:2.4px; text-transform:uppercase;
    color:var(--g-500); font-weight:700; margin-bottom:10px;
  }
  .page-head h1{
    font-family:'Fraunces', serif; font-weight:700; font-size:30px;
    color:var(--g-800); letter-spacing:-0.3px;
    padding-bottom:20px; border-bottom:2px solid var(--g-800);
  }

  .form-card{
    background:var(--white);
    border:1.5px solid var(--line);
    border-top:4px solid var(--g-500);
    border-radius:12px;
    padding:32px 32px 28px;
    box-shadow:0 8px 24px rgba(11,61,31,0.08);
  }
  .form-card .desc{ font-size:12.5px; color:var(--slate); margin-bottom:24px; line-height:1.5; }

  .field{ margin-bottom:20px; }
  .field label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field select{
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
  .field select:focus{
    outline:none;
    border-color:var(--g-500);
    background:#fff;
    box-shadow:0 0 0 3px rgba(31,162,76,0.15);
  }

  .btn{
    width:100%;
    padding:14px;
    font-size:13.5px; font-weight:700;
    border:none; border-radius:8px;
    cursor:pointer;
    transition:.15s ease;
    font-family:'Inter', sans-serif;
    margin-top:6px;
  }
  .btn-primary{
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    color:#fff;
    box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  .btn-primary:hover{ filter:brightness(1.08); transform:translateY(-1px); }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
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
    <a href="classes.php"><span class="dot"></span>Classes</a>
    <a href="subjects.php"><span class="dot"></span>Subjects</a>
    <a href="mark_attendance.php"><span class="dot"></span>Mark Attendance</a>
    <a href="attendance_report.php"><span class="dot"></span>Attendance Report</a>
    <a href="exams.php"><span class="dot"></span>Exams</a>
    <a href="enter_results.php"><span class="dot"></span>Enter Results</a>
    <a href="view_results.php"><span class="dot"></span>View Results</a>
    <a href="report_card.php" class="active"><span class="dot"></span>Report Card</a>
  </nav>

  <div class="nav-group-label">Records</div>
  <nav>
    <a href="fees.php"><span class="dot"></span>Fees</a>
    <a href="assign_fees.php"><span class="dot"></span>Assign Fees</a>
  </nav>

  <div class="nav-group-label">Library</div>
  <nav>
    <a href="library_books.php"><span class="dot"></span>Books Available</a>
    <a href="issue_book.php"><span class="dot"></span>Issue Book</a>
    <a href="return_book.php"><span class="dot"></span>Return Book</a>
  </nav>

  <div class="sidebar-foot">
    <a href="../auth/logout.php">&#8617; Logout</a>
  </div>
</div>

<div class="main">

  <div class="page-head">
    <div class="eyebrow">Academics &middot; Results</div>
    <h1>Generate Report Card</h1>
  </div>
  <div class="form-card">
    <div class="desc">Select a class first, then a student and exam, to generate a printable report card.</div>
    <form method="GET">

      <div class="field">
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id" onchange="this.form.submit()" required>
          <option value="">Select Class</option>
          <?php foreach($classes as $c){ ?>
            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
          <?php } ?>
        </select>
      </div>

      <?php if($selectedClass != ""){ ?>
      <div class="field">
        <label for="student_id">Student</label>
        <select id="student_id" name="student_id" required>
          <option value="">Select Student</option>
          <?php foreach($students as $s){ ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['admission_number']." - ".$s['first_name']." ".$s['last_name']); ?></option>
          <?php } ?>
        </select>
      </div>

      <div class="field">
        <label for="exam_id">Examination</label>
        <select id="exam_id" name="exam_id" required>
          <option value="">Select Exam</option>
          <?php foreach($exams as $e){ ?>
            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['exam_name']); ?></option>
          <?php } ?>
        </select>
      </div>

      <button type="submit" formaction="student_report.php" class="btn btn-primary">Generate Report Card</button>
      <?php } ?>

    </form>
  </div>

</div>

</body>
</html>
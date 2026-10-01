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

$subjects = $conn->prepare("SELECT id, subject_name FROM subjects WHERE school_id = ? ORDER BY subject_name");
$subjects->execute([$school_id]);
$subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);

$exams = $conn->prepare("SELECT id, exam_name FROM exams WHERE school_id = ? ORDER BY exam_name");
$exams->execute([$school_id]);
$exams = $exams->fetchAll(PDO::FETCH_ASSOC);

$selectedClass = $_GET['class_id'] ?? '';
$selectedSubject = $_GET['subject_id'] ?? '';
$selectedExam = $_GET['exam_id'] ?? '';
$hasSearched = isset($_GET['view']);

$results = [];
$classAverage = 0;
$highestScore = 0;
$lowestScore = 0;
$passCount = 0;

if ($hasSearched && $selectedClass != "" && $selectedSubject != "" && $selectedExam != "") {

    $stmt = $conn->prepare("
        SELECT
            s.admission_number, s.first_name, s.last_name,
            r.id AS result_id, r.class_score, r.exam_score, r.total_score, r.grade, r.remarks
        FROM students s
        INNER JOIN results r ON r.student_id = s.id
        WHERE s.class_id = ? AND r.subject_id = ? AND r.exam_id = ? AND s.school_id = ? AND r.school_id = ?
        ORDER BY r.total_score DESC
    ");
    $stmt->execute([$selectedClass, $selectedSubject, $selectedExam, $school_id, $school_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) > 0) {
        $totals = array_column($results, 'total_score');
        $classAverage = array_sum($totals) / count($totals);
        $highestScore = max($totals);
        $lowestScore = min($totals);
        $passCount = count(array_filter($results, function($r) { return $r['grade'] != 'F'; }));
    }
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
<title>View Results - Klaso</title>
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

  .selector-card{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:24px 26px; margin:22px 0;
    box-shadow:0 6px 18px rgba(11,61,31,0.06);
    display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;
  }
  .field-inline{ flex:1; min-width:180px; }
  .field-inline label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field-inline select{
    width:100%; padding:11px 14px; font-size:13.5px;
    background:var(--g-100); border:1.5px solid var(--line); border-radius:8px;
    font-family:'Inter', sans-serif; color:var(--ink);
  }
  .field-inline select:focus{
    outline:none; border-color:var(--g-500); background:#fff;
    box-shadow:0 0 0 3px rgba(31,162,76,0.15);
  }
  .btn{
    padding:12px 24px; font-size:13.5px; font-weight:700;
    border:none; border-radius:8px; cursor:pointer; transition:.15s ease;
    font-family:'Inter', sans-serif; white-space:nowrap;
  }
  .btn-primary{
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    color:#fff; box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  .btn-primary:hover{ filter:brightness(1.08); transform:translateY(-1px); }

  .stat-grid{ display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin:22px 0; }
  .stat{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:18px 20px 16px; box-shadow:0 4px 16px rgba(11,61,31,0.06);
  }
  .stat .label{ font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--slate); margin-bottom:10px; font-weight:700; }
  .stat .value{ font-family:'IBM Plex Mono', monospace; font-size:22px; font-weight:700; color:var(--g-800); }

  .results-head{
    display:flex; justify-content:space-between; align-items:center;
    margin:20px 0 16px; flex-wrap:wrap; gap:12px;
  }
  .results-head h2{ font-family:'Fraunces', serif; font-size:18px; color:var(--g-800); font-weight:700; }
  .count-tag{
    font-size:11.5px; color:#fff; background:var(--g-500); padding:3px 10px;
    border-radius:20px; font-weight:700; font-family:'IBM Plex Mono', monospace;
  }

  .table-card{
    background:var(--white); border:1.5px solid var(--line); border-radius:12px;
    overflow:hidden; box-shadow:0 6px 20px rgba(11,61,31,0.07); margin-bottom:20px;
  }
  table{ width:100%; border-collapse:collapse; }
  thead tr{ background: linear-gradient(90deg, var(--g-800), var(--g-700)); }
  th{
    color:#fff; padding:14px 18px; font-size:10.5px; letter-spacing:1.1px;
    text-transform:uppercase; font-weight:700; text-align:left;
  }
  th:nth-child(1), th:nth-child(4), th:nth-child(5), th:nth-child(6), th:nth-child(7){ text-align:center; }
  th:last-child{ text-align:right; width:100px; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:nth-child(1), td:nth-child(4), td:nth-child(5), td:nth-child(6), td:nth-child(7){ text-align:center; }
  td:last-child{ text-align:right; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .rank{ font-family:'IBM Plex Mono', monospace; font-weight:800; color:var(--slate); }
  .rank.top3{ color:var(--g-600); }
  .student-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }
  .total-score{ font-family:'IBM Plex Mono', monospace; font-weight:700; color:var(--g-700); }

  .grade-pill{
    display:inline-block; font-size:11.5px; padding:4px 12px; border-radius:20px;
    background:var(--g-100); color:var(--g-700); font-weight:800; border:1px solid #BFE7CC;
    font-family:'IBM Plex Mono', monospace;
  }
  .grade-pill.grade-f{ background:#FDEDED; color:#B42318; border-color:#F3B8B8; }
  .grade-pill.grade-e{ background:#FFF7E0; color:#8A6300; border-color:#F3E3AC; }

  .edit-btn{
    padding:7px 14px; font-size:11.5px; font-weight:700; border-radius:6px;
    background:#FFF7E0; color:#8A6300; border:1px solid #F3E3AC; transition:.15s ease;
  }
  .edit-btn:hover{ background:#F3C744; color:#3D2E00; }

  .empty-state{ text-align:center; padding:60px 20px; background:var(--white); border:1.5px solid var(--line); border-radius:12px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:17px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:1100px){
    .stat-grid{ grid-template-columns:repeat(2, 1fr); }
  }
  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .table-card{ overflow-x:auto; }
    table{ min-width:760px; }
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
    <a href="view_results.php" class="active"><span class="dot"></span>View Results</a>
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
    <h1>View Examination Results</h1>
  </div>

  <form method="GET" class="selector-card">
    <div class="field-inline">
      <label for="class_id">Class</label>
      <select id="class_id" name="class_id" required>
        <option value="">Select Class</option>
        <?php foreach($classes as $c){ ?>
          <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
        <?php } ?>
      </select>
    </div>
    <div class="field-inline">
      <label for="subject_id">Subject</label>
      <select id="subject_id" name="subject_id" required>
        <option value="">Select Subject</option>
        <?php foreach($subjects as $s){ ?>
          <option value="<?php echo $s['id']; ?>" <?php echo $selectedSubject == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['subject_name']); ?></option>
        <?php } ?>
      </select>
    </div>
    <div class="field-inline">
      <label for="exam_id">Examination</label>
      <select id="exam_id" name="exam_id" required>
        <option value="">Select Exam</option>
        <?php foreach($exams as $e){ ?>
          <option value="<?php echo $e['id']; ?>" <?php echo $selectedExam == $e['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['exam_name']); ?></option>
        <?php } ?>
      </select>
    </div>
    <button type="submit" name="view" value="1" class="btn btn-primary">View Results</button>
  </form>

  <?php if($hasSearched){ ?>

    <?php if(count($results) > 0){ ?>

      <div class="stat-grid">
        <div class="stat">
          <div class="label">Class Average</div>
          <div class="value"><?php echo number_format($classAverage, 1); ?></div>
        </div>
        <div class="stat">
          <div class="label">Highest Score</div>
          <div class="value"><?php echo number_format($highestScore, 1); ?></div>
        </div>
        <div class="stat">
          <div class="label">Lowest Score</div>
          <div class="value"><?php echo number_format($lowestScore, 1); ?></div>
        </div>
        <div class="stat">
          <div class="label">Pass Rate</div>
          <div class="value"><?php echo round(($passCount / count($results)) * 100); ?>%</div>
        </div>
      </div>

      <div class="results-head">
        <h2>Results</h2>
        <span class="count-tag"><?php echo count($results); ?> student<?php echo count($results) != 1 ? 's' : ''; ?></span>
      </div>

      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Admission No.</th>
              <th>Student Name</th>
              <th>Class Score</th>
              <th>Exam Score</th>
              <th>Total</th>
              <th>Grade</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $rank = 1; foreach($results as $r){
              $gradeClass = '';
              if($r['grade']=='F'){ $gradeClass='grade-f'; }
              elseif($r['grade']=='E'){ $gradeClass='grade-e'; }
            ?>
              <tr>
                <td><span class="rank <?php echo $rank <= 3 ? 'top3' : ''; ?>">#<?php echo $rank; ?></span></td>
                <td class="mono"><?php echo htmlspecialchars($r['admission_number']); ?></td>
                <td><span class="student-name"><?php echo htmlspecialchars($r['first_name']." ".$r['last_name']); ?></span></td>
                <td><?php echo number_format($r['class_score'],1); ?></td>
                <td><?php echo number_format($r['exam_score'],1); ?></td>
                <td><span class="total-score"><?php echo number_format($r['total_score'],1); ?></span></td>
                <td><span class="grade-pill <?php echo $gradeClass; ?>"><?php echo htmlspecialchars($r['grade']); ?></span></td>
                <td>
                  <a href="enter_results.php?class_id=<?php echo $selectedClass; ?>&subject_id=<?php echo $selectedSubject; ?>&exam_id=<?php echo $selectedExam; ?>" class="edit-btn">Edit</a>
                </td>
              </tr>
            <?php $rank++; } ?>
          </tbody>
        </table>
      </div>

    <?php }else{ ?>
      <div class="empty-state">
        <h3>No results found</h3>
        <p>No scores have been entered for this class, subject, and exam combination yet.</p>
      </div>
    <?php } ?>

  <?php }else{ ?>
    <div class="empty-state">
      <h3>Select filters to view results</h3>
      <p>Choose a class, subject and examination above, then click "View Results."</p>
    </div>
  <?php } ?>

</div>

</body>
</html>
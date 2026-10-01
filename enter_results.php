<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];
$error = "";
$saved = false;

$classes = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classes->execute([$school_id]);
$classes = $classes->fetchAll(PDO::FETCH_ASSOC);

$subjects = $conn->prepare("SELECT id, subject_name FROM subjects WHERE school_id = ? ORDER BY subject_name");
$subjects->execute([$school_id]);
$subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);

$exams = $conn->prepare("SELECT id, exam_name FROM exams WHERE school_id = ? ORDER BY exam_name");
$exams->execute([$school_id]);
$exams = $exams->fetchAll(PDO::FETCH_ASSOC);

$selectedClass = $_GET['class_id'] ?? ($_POST['class_id'] ?? '');
$selectedSubject = $_GET['subject_id'] ?? ($_POST['subject_id'] ?? '');
$selectedExam = $_GET['exam_id'] ?? ($_POST['exam_id'] ?? '');

/* Grade calculation, standard WAEC-style bands */
function calculateGrade($total) {
    if ($total >= 80) return ['A', 'Excellent'];
    if ($total >= 70) return ['B', 'Very Good'];
    if ($total >= 60) return ['C', 'Good'];
    if ($total >= 50) return ['D', 'Credit'];
    if ($total >= 40) return ['E', 'Pass'];
    return ['F', 'Fail'];
}

/* SAVE RESULTS */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_results'])) {

    if ($selectedClass == "" || $selectedSubject == "" || $selectedExam == "") {
        $error = "Select class, subject, and exam first.";
    } else {

        $classScores = $_POST['class_score'] ?? [];
        $examScores = $_POST['exam_score'] ?? [];

        foreach ($classScores as $student_id => $classScore) {

            $examScore = $examScores[$student_id] ?? 0;

            if ($classScore === "" && $examScore === "") {
                continue;
            }

            $classScore = is_numeric($classScore) ? $classScore : 0;
            $examScore = is_numeric($examScore) ? $examScore : 0;
            $total = $classScore + $examScore;

            list($grade, $remarks) = calculateGrade($total);

            $check = $conn->prepare("
                SELECT id FROM results
                WHERE student_id = ? AND subject_id = ? AND exam_id = ? AND school_id = ?
            ");
            $check->execute([$student_id, $selectedSubject, $selectedExam, $school_id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $update = $conn->prepare("
                    UPDATE results
                    SET class_score = ?, exam_score = ?, total_score = ?, grade = ?, remarks = ?
                    WHERE id = ?
                ");
                $update->execute([$classScore, $examScore, $total, $grade, $remarks, $existing['id']]);
            } else {
                $insert = $conn->prepare("
                    INSERT INTO results (school_id, student_id, subject_id, exam_id, class_id, class_score, exam_score, total_score, grade, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$school_id, $student_id, $selectedSubject, $selectedExam, $selectedClass, $classScore, $examScore, $total, $grade, $remarks]);
            }
        }

        header("Location: enter_results.php?class_id=$selectedClass&subject_id=$selectedSubject&exam_id=$selectedExam&saved=1");
        exit();
    }
}

/* Load students with existing scores, if any, for this class/subject/exam combo */
$students = [];
if ($selectedClass != "" && $selectedSubject != "" && $selectedExam != "") {
    $stmt = $conn->prepare("
        SELECT s.id, s.admission_number, s.first_name, s.last_name,
               r.class_score, r.exam_score, r.total_score, r.grade
        FROM students s
        LEFT JOIN results r ON r.student_id = s.id AND r.subject_id = ? AND r.exam_id = ? AND r.school_id = s.school_id
        WHERE s.class_id = ? AND s.school_id = ?
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute([$selectedSubject, $selectedExam, $selectedClass, $school_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$saved = isset($_GET['saved']);

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enter Results - Klaso</title>
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

  .banner-success{background:var(--g-100); border:1.5px solid #BFE7CC; color:var(--g-700);
    padding:14px 18px; border-radius:10px; margin:20px 0;
    font-size:13px; font-weight:600;
  }
  .alert-error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:13px 16px;
    border-radius:8px; margin:20px 0;
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

  .table-card{
    background:var(--white); border:1.5px solid var(--line); border-radius:12px;
    overflow:hidden; box-shadow:0 6px 20px rgba(11,61,31,0.07); margin-top:22px;
  }
  table{ width:100%; border-collapse:collapse; }
  thead tr{ background: linear-gradient(90deg, var(--g-800), var(--g-700)); }
  th{
    color:#fff; padding:14px 18px; font-size:10.5px; letter-spacing:1.1px;
    text-transform:uppercase; font-weight:700; text-align:left;
  }
  th:nth-child(3), th:nth-child(4), th:nth-child(5), th:nth-child(6){ text-align:center; }
  td{
    padding:10px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:nth-child(3), td:nth-child(4), td:nth-child(5), td:nth-child(6){ text-align:center; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .student-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }

  .score-input{
    width:70px; padding:8px 10px; text-align:center; font-size:13px;
    font-family:'IBM Plex Mono', monospace; background:var(--g-100);
    border:1.5px solid var(--line); border-radius:6px; color:var(--ink);
  }
  .score-input:focus{ outline:none; border-color:var(--g-500); background:#fff; }

  .total-display{ font-family:'IBM Plex Mono', monospace; font-weight:700; color:var(--g-800); }

  .grade-pill{
    display:inline-block; font-size:11.5px; padding:4px 12px; border-radius:20px;
    background:var(--g-100); color:var(--g-700); font-weight:800; border:1px solid #BFE7CC;
    font-family:'IBM Plex Mono', monospace; min-width:24px;
  }
  .grade-pill.grade-f{ background:#FDEDED; color:#B42318; border-color:#F3B8B8; }
  .grade-pill.grade-e{ background:#FFF7E0; color:#8A6300; border-color:#F3E3AC; }

  .save-bar{ margin-top:24px; text-align:right; }

  .empty-state{ text-align:center; padding:50px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:16px; color:var(--slate); font-weight:600; }
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
    <a href="enter_results.php" class="active"><span class="dot"></span>Enter Results</a>
    <a href="view_results.php"><span class="dot"></span>View Results</a>
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
    <h1>Enter Results</h1>
  </div>

  <?php if($saved){ ?>
    <div class="banner-success">&#10003; Results saved successfully.</div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

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
      <label for="exam_id">Exam</label>
      <select id="exam_id" name="exam_id" required>
        <option value="">Select Exam</option>
        <?php foreach($exams as $e){ ?>
          <option value="<?php echo $e['id']; ?>" <?php echo $selectedExam == $e['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['exam_name']); ?></option>
        <?php } ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Load Students</button>
  </form>

  <?php if($selectedClass != "" && $selectedSubject != "" && $selectedExam != ""){ ?>

    <?php if(count($students) > 0){ ?>
      <form method="POST" id="resultsForm">
        <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($selectedClass); ?>">
        <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selectedSubject); ?>">
        <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($selectedExam); ?>">

        <div class="table-card">
          <table>
            <thead>
              <tr>
                <th>Admission No.</th>
                <th>Name</th>
                <th>Class Score</th>
                <th>Exam Score</th>
                <th>Total</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($students as $s){
                $gradeClass = '';
                if($s['grade']=='F'){ $gradeClass='grade-f'; }
                elseif($s['grade']=='E'){ $gradeClass='grade-e'; }
              ?>
                <tr>
                  <td class="mono"><?php echo htmlspecialchars($s['admission_number']); ?></td>
                  <td><span class="student-name"><?php echo htmlspecialchars($s['first_name']." ".$s['last_name']); ?></span></td>
                  <td>
                    <input type="number" step="0.01" min="0" max="100" class="score-input class-score" name="class_score[<?php echo $s['id']; ?>]" value="<?php echo $s['class_score'] !== null ? $s['class_score'] : ''; ?>" data-student="<?php echo $s['id']; ?>">
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" max="100" class="score-input exam-score" name="exam_score[<?php echo $s['id']; ?>]" value="<?php echo $s['exam_score'] !== null ? $s['exam_score'] : ''; ?>" data-student="<?php echo $s['id']; ?>">
                  </td>
                  <td><span class="total-display" id="total_<?php echo $s['id']; ?>"><?php echo $s['total_score'] !== null ? number_format($s['total_score'],1) : '&mdash;'; ?></span></td>
                  <td><span class="grade-pill <?php echo $gradeClass; ?>" id="grade_<?php echo $s['id']; ?>"><?php echo $s['grade'] ?? '&mdash;'; ?></span></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>

        <div class="save-bar">
          <button type="submit" name="save_results" value="1" class="btn btn-primary">Save Results</button>
        </div>
      </form>
    <?php }else{ ?>
      <div class="empty-state">
        <h3>No students in this class</h3>
        <p>Add students to this class first.</p>
      </div>
    <?php } ?>

  <?php }else{ ?>
    <div class="empty-state">
      <h3>Select class, subject, and exam</h3>
      <p>Choose all three above, then click "Load Students" to begin entering scores.</p>
    </div>
  <?php } ?>

</div>

<script>
function calculateLive(studentId) {
  const classScore = parseFloat(document.querySelector(`input.class-score[data-student="${studentId}"]`).value) || 0;
  const examScore = parseFloat(document.querySelector(`input.exam-score[data-student="${studentId}"]`).value) || 0;
  const total = classScore + examScore;

  let grade = 'F', gradeClass = 'grade-f';
  if (total >= 80) { grade = 'A'; gradeClass = ''; }
  else if (total >= 70) { grade = 'B'; gradeClass = ''; }
  else if (total >= 60) { grade = 'C'; gradeClass = ''; }
  else if (total >= 50) { grade = 'D'; gradeClass = ''; }
  else if (total >= 40) { grade = 'E'; gradeClass = 'grade-e'; }

  document.getElementById('total_' + studentId).textContent = total.toFixed(1);
  const gradeEl = document.getElementById('grade_' + studentId);
  gradeEl.textContent = grade;
  gradeEl.className = 'grade-pill ' + gradeClass;
}

document.querySelectorAll('.score-input').forEach(function(input) {
  input.addEventListener('input', function() {
    calculateLive(this.dataset.student);
  });
});
</script>

</body>
</html>
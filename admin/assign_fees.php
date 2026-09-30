<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $mode = $_POST['mode'];
    $amount_due = trim($_POST['amount_due']);
    $academic_year = trim($_POST['academic_year']);
    $term = trim($_POST['term']);

    if ($amount_due == "" || !is_numeric($amount_due) ||  $amount_due <= 0) {
        $error = "Enter a valid amount.";
    } elseif ($academic_year == "" || $term == "") {
        $error = "Academic year and term are required.";
    } else {

        if ($mode == "class") {

            $class_id = $_POST['class_id'];

            if ($class_id == "") {
                $error = "Select a class.";
            } else {

                $dupCheck = $conn->prepare("
                    SELECT f.id FROM fees f
                    INNER JOIN students s ON s.id = f.student_id
                    WHERE s.class_id = ? AND f.academic_year = ? AND f.term = ? AND f.school_id = ? AND f.amount_due > 0
                    LIMIT 1
                ");
                $dupCheck->execute([$class_id, $academic_year, $term, $school_id]);

                if ($dupCheck->rowCount() > 0) {
                    $error = "Fees for this class, year, and term have already been assigned. Edit individual student fees instead if you need to adjust.";
                } else {

                    $students = $conn->prepare("SELECT id FROM students WHERE class_id = ? AND school_id = ?");
                    $students->execute([$class_id, $school_id]);
                    $students = $students->fetchAll(PDO::FETCH_ASSOC);

                    if (count($students) == 0) {
                        $error = "This class has no students.";
                    } else {

                        $insert = $conn->prepare("
                            INSERT INTO fees (school_id, student_id, academic_year, term, amount_due, amount_paid)
                            VALUES (?, ?, ?, ?, ?, 0)
                        ");

                        foreach ($students as $s) {
                            $insert->execute([$school_id, $s['id'], $academic_year, $term, $amount_due]);
                        }

                        header("Location: assign_fees.php?assigned=1&count=" . count($students));
                        exit();
                    }
                }
            }

        } else {

            $student_id = $_POST['student_id'];

            if ($student_id == "") {
                $error = "Select a student.";
            } else {

                $dupCheck = $conn->prepare("
                    SELECT id FROM fees
                    WHERE student_id = ? AND academic_year = ? AND term = ? AND school_id = ? AND amount_due > 0
                    LIMIT 1
                ");
                $dupCheck->execute([$student_id, $academic_year, $term, $school_id]);

                if ($dupCheck->rowCount() > 0) {
                    $error = "Fees for this student, year, and term have already been assigned.";
                } else {

                    $insert = $conn->prepare("
                        INSERT INTO fees (school_id, student_id, academic_year, term, amount_due, amount_paid)
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    $insert->execute([$school_id, $student_id, $academic_year, $term, $amount_due]);

                    header("Location: assign_fees.php?assigned=1&count=1");
                    exit();
                }
            }
        }
    }
}

$classes = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classes->execute([$school_id]);
$classes = $classes->fetchAll(PDO::FETCH_ASSOC);

$students = $conn->prepare("SELECT id, admission_number, first_name, last_name FROM students WHERE school_id = ? ORDER BY first_name, last_name");
$students->execute([$school_id]);
$students = $students->fetchAll(PDO::FETCH_ASSOC);
/* Live overview: current fee status for every student, so the admin sees who already owes what before assigning more */
$overview = $conn->prepare("
    SELECT
        s.id, s.admission_number, s.first_name, s.last_name, c.class_name,
        IFNULL(SUM(f.amount_due),0) AS total_due,
        IFNULL(SUM(f.amount_paid),0) AS total_paid
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN fees f ON f.student_id = s.id AND f.school_id = s.school_id
    WHERE s.school_id = ?
    GROUP BY s.id
    ORDER BY s.first_name, s.last_name
");
$overview->execute([$school_id]);
$overview = $overview->fetchAll(PDO::FETCH_ASSOC);

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assign Fees - Klaso</title>
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

  .banner-success{
    background:var(--g-100); border:1.5px solid #BFE7CC; color:var(--g-700);
    padding:14px 18px; border-radius:10px; margin:20px 0;
    font-size:13px; font-weight:600;
  }
  .alert-error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:13px 16px;
    border-radius:8px; margin:20px 0;
  }

  .mode-toggle{ display:flex; gap:10px; margin-bottom:24px; max-width:760px; }
  .mode-btn{
    flex:1; padding:14px; text-align:center; border-radius:10px; cursor:pointer;
    border:1.5px solid var(--line); background:var(--white); font-size:13px; font-weight:700;
    color:var(--slate); transition:.15s ease;
  }
  .mode-btn.active{ border-color:var(--g-500); background:var(--g-100); color:var(--g-700); }

  .form-card{
    background:var(--white);
    border:1.5px solid var(--line);
    border-top:4px solid var(--g-500);
    border-radius:12px;
    padding:30px 32px 28px;
    box-shadow:0 8px 24px rgba(11,61,31,0.08);
    max-width:760px;
    margin-bottom:36px;
  }

  .field{ margin-bottom:20px; }
  .field label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field input, .field select{
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
  .field input:focus, .field select:focus{
    outline:none;
    border-color:var(--g-500);
    background:#fff;
    box-shadow:0 0 0 3px rgba(31,162,76,0.15);
  }

  .row-2{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }

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

  .section-title{ font-family:'Fraunces', serif; font-size:18px; font-weight:700; color:var(--g-800); margin-bottom:16px; }

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
  th:nth-child(3), th:nth-child(4), th:nth-child(5){ text-align:right; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:nth-child(3), td:nth-child(4), td:nth-child(5){ text-align:right; font-family:'IBM Plex Mono', monospace; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .student-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }
  .balance-clear{ color:var(--g-700); font-weight:700; }
  .balance-owing{ color:#B42318; font-weight:700; }
  .balance-none{ color:var(--slate-light); }

  .empty-state{ text-align:center; padding:50px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:16px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .row-2{ grid-template-columns:1fr; }
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
    <a href="classes.php"><span class="dot"></span>Classes</a>
    <a href="subjects.php"><span class="dot"></span>Subjects</a>
  </nav>

  <div class="nav-group-label">Records</div>
  <nav>
    <a href="fees.php"><span class="dot"></span>Fees</a>
    <a href="assign_fees.php" class="active"><span class="dot"></span>Assign Fees</a>
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
    <div class="eyebrow">Records &middot; Fees</div>
    <h1>Assign Fees</h1>
  </div>

  <?php if(isset($_GET['assigned'])){ ?>
    <div class="banner-success">
      &#10003; Fees assigned to <?php echo (int)($_GET['count'] ?? 0); ?> student(s) successfully.
    </div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <div class="mode-toggle">
    <div class="mode-btn active" id="btnClass" onclick="switchMode('class')">Assign to Whole Class</div>
    <div class="mode-btn" id="btnStudent" onclick="switchMode('student')">Assign to One Student</div>
  </div>

  <div class="form-card">
    <form method="POST">
      <input type="hidden" name="mode" id="modeInput" value="class">

      <div id="classField" class="field">
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id">
          <option value="">Select Class</option>
          <?php foreach($classes as $c){ ?>
            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
          <?php } ?>
        </select>
      </div>

      <div id="studentField" class="field" style="display:none;">
        <label for="student_id">Student</label>
        <select id="student_id" name="student_id">
          <option value="">Select Student</option>
          <?php foreach($students as $s){ ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['admission_number']." - ".$s['first_name']." ".$s['last_name']); ?></option>
          <?php } ?>
        </select>
      </div>

      <div class="row-2">
        <div class="field">
          <label for="academic_year">Academic Year</label>
          <input type="text" id="academic_year" name="academic_year" value="2026/2027" required>
        </div>
        <div class="field">
          <label for="term">Term</label>
          <select id="term" name="term" required>
            <option value="">Select Term</option>
            <option>First Term</option>
            <option>Second Term</option>
            <option>Third Term</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="amount_due">Amount Due (GHS)</label>
        <input type="number" step="0.01" id="amount_due" name="amount_due" placeholder="e.g. 1000.00" required>
      </div>

      <button type="submit" class="btn btn-primary">Assign Fees</button>
    </form>
  </div>

  <div class="section-title">Current Fee Status &mdash; All Students</div>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Class</th>
          <th>Total Due</th>
          <th>Total Paid</th>
          <th>Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($overview) > 0){ ?>
          <?php foreach($overview as $o){
            $bal = $o['total_due'] - $o['total_paid'];
          ?>
            <tr>
              <td>
                <span class="student-name"><?php echo htmlspecialchars($o['first_name']." ".$o['last_name']); ?></span><br>
                <span class="mono"><?php echo htmlspecialchars($o['admission_number']); ?></span>
              </td>
              <td><?php echo $o['class_name'] ? htmlspecialchars($o['class_name']) : '&mdash;'; ?></td>
              <td>GHS <?php echo number_format($o['total_due'], 2); ?></td>
              <td>GHS <?php echo number_format($o['total_paid'], 2); ?></td>
              <td class="<?php echo $o['total_due']==0 ? 'balance-none' : ($bal <= 0 ? 'balance-clear' : 'balance-owing'); ?>">
                <?php echo $o['total_due']==0 ? 'No fees assigned' : 'GHS '.number_format(max($bal,0), 2); ?>
              </td>
            </tr>
          <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="5">
              <div class="empty-state">
                <h3>No students yet</h3>
                <p>Add students first before assigning fees.</p>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

</div>

<script>
function switchMode(mode) {
  document.getElementById('modeInput').value = mode;

  if (mode === 'class') {
    document.getElementById('classField').style.display = 'block';
    document.getElementById('studentField').style.display = 'none';
    document.getElementById('class_id').required = true;
    document.getElementById('student_id').required = false;
    document.getElementById('btnClass').classList.add('active');
    document.getElementById('btnStudent').classList.remove('active');
  } else {
    document.getElementById('classField').style.display = 'none';
    document.getElementById('studentField').style.display = 'block';
    document.getElementById('class_id').required = false;
    document.getElementById('student_id').required = true;
    document.getElementById('btnStudent').classList.add('active');
    document.getElementById('btnClass').classList.remove('active');
  }
}

switchMode('class');
</script>

</body>
</html>
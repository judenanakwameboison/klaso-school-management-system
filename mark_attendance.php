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

$selectedClass = isset($_GET['class_id']) ? $_GET['class_id'] : (isset($_POST['class_id']) ? $_POST['class_id'] : '');
$selectedDate = isset($_GET['attendance_date']) ? $_GET['attendance_date'] : (isset($_POST['attendance_date']) ? $_POST['attendance_date'] : date('Y-m-d'));

/* SAVE ATTENDANCE */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_attendance'])) {

    if ($selectedClass == "" || $selectedDate == "") {
        $error = "Select a class and date first.";
    } else {

        $statuses = $_POST['status'] ?? [];

        foreach ($statuses as $student_id => $status) {

            /* One record per student per day — update if it exists, insert if not */
            $check = $conn->prepare("
                SELECT id FROM attendance
                WHERE student_id = ? AND class_id = ? AND attendance_date = ? AND school_id = ?
            ");
            $check->execute([$student_id, $selectedClass, $selectedDate, $school_id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $update = $conn->prepare("UPDATE attendance SET status = ?, marked_by = ? WHERE id = ?");
                $update->execute([$status, $_SESSION['user_id'], $existing['id']]);
            } else {
                $insert = $conn->prepare("
                    INSERT INTO attendance (school_id, student_id, class_id, attendance_date, status, marked_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$school_id, $student_id, $selectedClass, $selectedDate, $status, $_SESSION['user_id']]);
            }
        }

        header("Location: mark_attendance.php?class_id=$selectedClass&attendance_date=$selectedDate&saved=1");
        exit();
    }
}

/* Load students in the selected class, with any existing attendance for the selected date */
$students = [];
if ($selectedClass != "") {
    $stmt = $conn->prepare("
        SELECT s.id, s.admission_number, s.first_name, s.last_name, a.status
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ? AND a.school_id = s.school_id
        WHERE s.class_id = ? AND s.school_id = ?
        ORDER BY s.first_name, s.last_name
    ");
    $stmt->execute([$selectedDate, $selectedClass, $school_id]);
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
<title>Mark Attendance - Klaso</title>
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

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:1000px; }

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

  .selector-card{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:24px 26px; margin:22px 0;
    box-shadow:0 6px 18px rgba(11,61,31,0.06);
    display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;
  }
  .field-inline{ flex:1; min-width:200px; }
  .field-inline label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field-inline select, .field-inline input{
    width:100%; padding:11px 14px; font-size:13.5px;
    background:var(--g-100); border:1.5px solid var(--line); border-radius:8px;
    font-family:'Inter', sans-serif; color:var(--ink);
  }
  .field-inline select:focus, .field-inline input:focus{
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

  .bulk-actions{ display:flex; gap:10px; margin:18px 0; }
  .bulk-btn{
    padding:9px 18px; font-size:12px; font-weight:700; border-radius:6px;
    border:1.5px solid var(--line); background:var(--white); color:var(--slate); cursor:pointer;
    transition:.15s ease;
  }
  .bulk-btn:hover{ background:var(--g-100); border-color:var(--g-500); color:var(--g-700); }

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

  .student-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }

  .status-options{ display:flex; gap:6px; justify-content:center; }
  .status-radio{ display:none; }
  .status-label{
    padding:7px 14px; font-size:11.5px; font-weight:700; border-radius:6px;
    cursor:pointer; border:1.5px solid var(--line); color:var(--slate); transition:.15s ease;
  }
  .status-radio:checked + .status-label.present{ background:var(--g-500); color:#fff; border-color:var(--g-500); }
  .status-radio:checked + .status-label.absent{ background:#DC3545; color:#fff; border-color:#DC3545; }
  .status-radio:checked + .status-label.late{ background:#F3C744; color:#3D2E00; border-color:#F3C744; }

  .save-bar{ margin-top:24px; text-align:right; }

  .empty-state{ text-align:center; padding:50px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:16px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .table-card{ overflow-x:auto; }
    table{ min-width:560px; }
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
    <a href="mark_attendance.php" class="active"><span class="dot"></span>Attendance</a>
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
    <div class="eyebrow">Academics &middot; Attendance</div>
    <h1>Mark Attendance</h1>
  </div>

  <?php if($saved){ ?>
    <div class="banner-success">&#10003; Attendance saved successfully.</div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <form method="GET" class="selector-card">
    <div class="field-inline">
      <label for="class_id">Class</label>
      <select id="class_id" name="class_id" onchange="this.form.submit()">
        <option value="">Select Class</option>
        <?php foreach($classes as $c){ ?>
          <option value="<?php echo $c['id']; ?>" <?php echo $selectedClass == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
        <?php } ?>
      </select>
    </div>
    <div class="field-inline">
      <label for="attendance_date">Date</label>
      <input type="date" id="attendance_date" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="this.form.submit()">
    </div>
  </form>

  <?php if($selectedClass != "" && count($students) > 0){ ?>

    <form method="POST" id="attendanceForm">
      <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($selectedClass); ?>">
      <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>">

      <div class="bulk-actions">
        <button type="button" class="bulk-btn" onclick="markAll('Present')">Mark All Present</button>
        <button type="button" class="bulk-btn" onclick="markAll('Absent')">Mark All Absent</button>
      </div>

      <div class="table-card">
        <table>
          <thead>
            <tr>
              <th>Admission No.</th>
              <th>Name</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($students as $s){
              $currentStatus = $s['status'] ?? 'Present';
            ?>
              <tr>
                <td class="mono"><?php echo htmlspecialchars($s['admission_number']); ?></td>
                <td><span class="student-name"><?php echo htmlspecialchars($s['first_name']." ".$s['last_name']); ?></span></td>
                <td>
                  <div class="status-options">
                    <input type="radio" class="status-radio" name="status[<?php echo $s['id']; ?>]" value="Present" id="present_<?php echo $s['id']; ?>" <?php echo $currentStatus=='Present'?'checked':''; ?>>
                    <label class="status-label present" for="present_<?php echo $s['id']; ?>">Present</label>

                    <input type="radio" class="status-radio" name="status[<?php echo $s['id']; ?>]" value="Absent" id="absent_<?php echo $s['id']; ?>" <?php echo $currentStatus=='Absent'?'checked':''; ?>>
                    <label class="status-label absent" for="absent_<?php echo $s['id']; ?>">Absent</label>

                    <input type="radio" class="status-radio" name="status[<?php echo $s['id']; ?>]" value="Late" id="late_<?php echo $s['id']; ?>" <?php echo $currentStatus=='Late'?'checked':''; ?>>
                    <label class="status-label late" for="late_<?php echo $s['id']; ?>">Late</label>
                  </div>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>

      <div class="save-bar">
        <button type="submit" name="save_attendance" value="1" class="btn btn-primary">Save Attendance</button>
      </div>
    </form>

  <?php }elseif($selectedClass != ""){ ?>
    <div class="empty-state">
      <h3>No students in this class</h3>
      <p>Add students to this class first.</p>
    </div>
  <?php }else{ ?>
    <div class="empty-state">
      <h3>Select a class to begin</h3>
      <p>Choose a class and date above to mark attendance.</p>
    </div>
  <?php } ?>

</div>

<script>
function markAll(status) {
  document.querySelectorAll('.status-radio[value="' + status + '"]').forEach(function(radio) {
    radio.checked = true;
  });
}
</script>

</body>
</html>
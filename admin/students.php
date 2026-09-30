<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

$error = "";

/* ADD STUDENT */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $admission_number = trim($_POST['admission_number']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $class_id = $_POST['class_id'] !== "" ? $_POST['class_id'] : null;

    if ($admission_number == "" || $first_name == "" || $last_name == "") {
        $error = "Admission number, first name, and last name are required.";
    } else {

        /* Prevent duplicate admission numbers within the same school */
        $check = $conn->prepare("SELECT id FROM students WHERE admission_number = ? AND school_id = ?");
        $check->execute([$admission_number, $school_id]);

        if ($check->rowCount() > 0) {
            $error = "A student with this admission number already exists.";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO students (school_id, admission_number, first_name, middle_name, last_name, class_id, status, promotion_status)
                VALUES (?, ?, ?, ?, ?, ?, 'Active', 'Active')
            ");
            $stmt->execute([
                $school_id,
                $admission_number,
                $first_name,
                $middle_name,
                $last_name,
                $class_id
            ]);

            header("Location: students.php?added=1");
            exit();
        }
    }
}

/* LOAD CLASSES for the dropdown, scoped */
$classes = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classes->execute([$school_id]);
$classes = $classes->fetchAll(PDO::FETCH_ASSOC);

/* SEARCH */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT s.*, c.class_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.school_id = ?
";
$params = [$school_id];

if ($search != "") {
    $sql .= " AND (s.first_name LIKE ? OR s.middle_name LIKE ? OR s.last_name LIKE ? OR s.admission_number LIKE ?)";
    $keyword = "%$search%";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

$sql .= " ORDER BY s.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalResults = count($students);

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

$deleted = isset($_GET['deleted']);
$updated = isset($_GET['updated']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Students - Klaso</title>
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

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:1280px; }

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

  .form-grid{ display:grid; grid-template-columns:1fr 1fr 1fr 1fr 1fr; gap:16px; align-items:end; }
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
  }
  .btn-primary:hover{ filter:brightness(1.08); transform:translateY(-1px); }

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
  th:last-child{ text-align:right; width:240px; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:last-child{ text-align:right; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .student-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }
  .class-tag{
    display:inline-block; font-size:11.5px; padding:4px 12px; border-radius:20px;
    background:var(--g-100); color:var(--g-700); font-weight:700; border:1px solid #BFE7CC;
  }
  .unassigned{ color:var(--slate-light); font-style:italic; }

  .action-group{ display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap; }
  .row-btn{
    padding:7px 12px; font-size:11.5px; font-weight:700; border-radius:6px;
    transition:.15s ease; white-space:nowrap; border:none; cursor:pointer;
    font-family:'Inter', sans-serif;
  }
  .view{ background:var(--g-100); color:var(--g-700); border:1px solid #BFE7CC; }
  .view:hover{ background:var(--g-600); color:#fff; border-color:var(--g-600); }
  .edit{ background:#FFF7E0; color:#8A6300; border:1px solid #F3E3AC; }
  .edit:hover{ background:#F3C744; color:#3D2E00; border-color:#F3C744; }
  .delete{ background:#FDEDED; color:#B42318; border:1px solid #F3B8B8; }
  .delete:hover{ background:#DC3545; color:#fff; border-color:#DC3545; }

  .empty-state{ text-align:center; padding:60px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:17px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .form-grid{ grid-template-columns:1fr; }
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
    <a href="students.php" class="active"><span class="dot"></span>Students</a>
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

  <div class="page-head">
    <div class="eyebrow">Academics &middot; Students</div>
    <h1>Students</h1>
  </div>

  <?php if(isset($_GET['added'])){ ?>
    <div class="banner-success">
      <div class="icon">&#10003;</div>
      Student added successfully.
    </div>
  <?php } ?>

  <?php if($updated){ ?>
    <div class="banner-success">
      <div class="icon">&#10003;</div>
      Student updated successfully.
    </div>
  <?php } ?>

  <?php if($deleted){ ?>
    <div class="banner-success">
      <div class="icon">&#10003;</div>
      Student deleted successfully.
    </div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <div class="form-card">
    <h3>Add Student</h3>
    <form method="POST">
      <div class="form-grid">
        <div class="field">
          <label for="admission_number">Admission Number</label>
          <input type="text" id="admission_number" name="admission_number" placeholder="e.g. 2026/001" required>
        </div>
        <div class="field">
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name" required>
        </div>
        <div class="field">
          <label for="middle_name">Middle Name</label>
          <input type="text" id="middle_name" name="middle_name" placeholder="Optional">
        </div>
        <div class="field">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name" required>
        </div>
        <div class="field">
          <label for="class_id">Class</label>
          <select id="class_id" name="class_id">
            <option value="">Not Assigned</option>
            <?php foreach($classes as $class){ ?>
              <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
      <br>
      <button type="submit" class="btn btn-primary" style="max-width:200px;">Save Student</button>
    </form>
  </div>

  <div class="toolbar">
    <form method="GET" class="search-box">
      <input type="text" name="search" placeholder="Search by name or admission number..." value="<?php echo htmlspecialchars($search); ?>">
      <button type="submit">Search</button>
      <a href="students.php" class="reset-link">Reset</a>
    </form>
    <div class="result-count"><strong><?php echo $totalResults; ?></strong> student<?php echo $totalResults != 1 ? 's' : ''; ?> found</div>
  </div>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Admission No.</th>
          <th>Name</th>
          <th>Class</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($students) > 0){ ?>
          <?php foreach($students as $s){ ?>
            <tr>
              <td class="mono"><?php echo htmlspecialchars($s['admission_number']); ?></td>
              <td><span class="student-name"><?php echo htmlspecialchars($s['first_name'].($s['middle_name'] ? ' '.$s['middle_name'] : '').' '.$s['last_name']); ?></span></td>
              <td>
                <?php if(!empty($s['class_name'])){ ?>
                  <span class="class-tag"><?php echo htmlspecialchars($s['class_name']); ?></span>
                <?php }else{ ?>
                  <span class="unassigned">Not assigned</span>
                <?php } ?>
              </td>
              <td>
                <div class="action-group">
                  <a class="row-btn view" href="view_student.php?id=<?php echo $s['id']; ?>">View</a>
                  <a class="row-btn edit" href="edit_student.php?id=<?php echo $s['id']; ?>">Edit</a>
                  <a class="row-btn delete" href="delete_student.php?id=<?php echo $s['id']; ?>" onclick="return confirm('Delete this student? This cannot be undone.');">Delete</a>
                </div>
              </td>
            </tr>
          <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="4">
              <div class="empty-state">
                <h3>No students found</h3>
                <p><?php echo $search != "" ? "Try a different search term or reset the filter." : "Add your first student using the form above."; ?></p>
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
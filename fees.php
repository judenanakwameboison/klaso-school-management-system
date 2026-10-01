<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];
$error = "";

/* RECORD PAYMENT */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_id = $_POST['student_id'];
    $amount = trim($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];

    if (!is_numeric($amount) || $amount <= 0) {
        $error = "Enter a valid payment amount.";
    } else {

        /* Confirm student belongs to this school */
        $studentCheck = $conn->prepare("SELECT id FROM students WHERE id = ? AND school_id = ?");
        $studentCheck->execute([$student_id, $school_id]);

        if ($studentCheck->rowCount() == 0) {
            $error = "Invalid student.";
        } else {

            /* Generate a unique receipt number */
            do {
                $receipt_number = "RCT-" . date('Y') . "-" . strtoupper(bin2hex(random_bytes(4)));
                $recCheck = $conn->prepare("SELECT id FROM fees WHERE receipt_number = ?");
                $recCheck->execute([$receipt_number]);
            } while ($recCheck->rowCount() > 0);

            $insert = $conn->prepare("
                INSERT INTO fees (school_id, student_id, amount_due, amount_paid, payment_date, payment_method, receipt_number)
                VALUES (?, ?, 0, ?, ?, ?, ?)
            ");
            $insert->execute([
                $school_id,
                $student_id,
                $amount,
                $payment_date,
                $payment_method,
                $receipt_number
            ]);

            header("Location: fees.php?paid=1&receipt=" . urlencode($receipt_number));
            exit();
        }
    }
}

/* FILTERS */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$classFilter = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

$classes = $conn->prepare("SELECT id, class_name FROM classes WHERE school_id = ? ORDER BY class_name");
$classes->execute([$school_id]);
$classes = $classes->fetchAll(PDO::FETCH_ASSOC);

/* Build the student + fee summary query */
$sql = "
    SELECT
        s.id, s.admission_number, s.first_name, s.last_name,
        c.class_name,
        IFNULL(SUM(f.amount_due),0) AS total_due,
        IFNULL(SUM(f.amount_paid),0) AS total_paid
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN fees f ON f.student_id = s.id AND f.school_id = s.school_id
    WHERE s.school_id = ?
";
$params = [$school_id];

if ($search != "") {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_number LIKE ?)";
    $keyword = "%$search%";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

if ($classFilter != "") {
    $sql .= " AND s.class_id = ?";
    $params[] = $classFilter;
}

$sql .= " GROUP BY s.id ORDER BY s.first_name, s.last_name";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Apply status filter in PHP (needs computed balance) */
$filtered = [];
foreach ($rows as $r) {
    $balance = $r['total_due'] - $r['total_paid'];

    if ($r['total_due'] == 0) {
        $status = 'unassigned';
    } elseif ($balance <= 0) {
        $status = 'paid';
    } elseif ($r['total_paid'] > 0) {
        $status = 'partial';
    } else {
        $status = 'unpaid';
    }

    if ($statusFilter != "" && $statusFilter != $status) {
        continue;
    }

    $r['balance'] = $balance;
    $r['status'] = $status;
    $filtered[] = $r;
}

/* Summary stats — school-wide, not affected by filters */
$summary = $conn->prepare("
    SELECT IFNULL(SUM(amount_due),0) AS total_due, IFNULL(SUM(amount_paid),0) AS total_paid
    FROM fees WHERE school_id = ?
");
$summary->execute([$school_id]);
$summary = $summary->fetch(PDO::FETCH_ASSOC);
$totalOutstanding = $summary['total_due'] - $summary['total_paid'];
if ($totalOutstanding < 0) { $totalOutstanding = 0; }
$fullyPaidCount = $conn->prepare("
    SELECT COUNT(*) FROM (
        SELECT student_id, SUM(amount_due) AS due, SUM(amount_paid) AS paid
        FROM fees WHERE school_id = ?
        GROUP BY student_id
        HAVING due > 0 AND paid >= due
    ) x
");
$fullyPaidCount->execute([$school_id]);
$fullyPaidCount = $fullyPaidCount->fetchColumn();

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fees - Klaso</title>
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

  .main{ margin-left:264px; padding:40px 48px 70px; max-width:1360px; }

  .page-head{ margin-bottom:22px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:14px; }
  .eyebrow{
    font-size:11px; letter-spacing:2.4px; text-transform:uppercase;
    color:var(--g-500); font-weight:700; margin-bottom:10px;
  }
  .page-head h1{
    font-family:'Fraunces', serif; font-weight:700; font-size:30px;
    color:var(--g-800); letter-spacing:-0.3px;
  }
  .head-row{ border-bottom:2px solid var(--g-800); padding-bottom:20px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:14px; width:100%; }
  .assign-link{
    padding:12px 22px; font-size:13px; font-weight:700; border-radius:8px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700)); color:#fff;
    box-shadow:0 8px 20px rgba(31,162,76,0.3); transition:.15s ease;
  }
  .assign-link:hover{ filter:brightness(1.08); }

  .banner-success{
    background:var(--g-100); border:1.5px solid #BFE7CC; color:var(--g-700);
    padding:14px 18px; border-radius:10px; margin:20px 0;
    font-size:13px; font-weight:600; display:flex; align-items:center; gap:10px;
  }
  .receipt-link{ margin-left:auto; font-weight:700; color:var(--g-800); text-decoration:underline; }

  .alert-error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:13px 16px;
    border-radius:8px; margin:20px 0;
  }

  .stat-grid{ display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin:22px 0 28px; }
  .stat{
    background:var(--white); border:1.5px solid var(--line); border-top:4px solid var(--g-500);
    border-radius:12px; padding:20px 20px 18px; box-shadow:0 4px 16px rgba(11,61,31,0.06);
  }
  .stat .label{ font-size:10.5px; letter-spacing:1.1px; text-transform:uppercase; color:var(--slate); margin-bottom:12px; font-weight:700; }
  .stat .value{ font-family:'IBM Plex Mono', monospace; font-size:22px; font-weight:700; color:var(--g-800); line-height:1.1; }
  .stat.money{ background: linear-gradient(150deg, var(--g-800), var(--g-700)); border-top-color:var(--g-400); }
  .stat.money .label{ color:#9FC3AC; }
  .stat.money .value{ color:#fff; }
  .stat.warn{ border-top-color:#DC3545; }
  .stat.warn .value{ color:#B42318; }
  .stat.good .value{ color:var(--g-600); }

  .toolbar{
    background:var(--white); border:1.5px solid var(--line); border-radius:12px;
    padding:18px 20px; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;
  }
  .toolbar input, .toolbar select{
    padding:11px 14px; font-size:13px; background:var(--g-100);
    border:1.5px solid var(--line); border-radius:8px; font-family:'Inter', sans-serif; color:var(--ink);
  }
  .toolbar input{ width:240px; }
  .toolbar input:focus, .toolbar select:focus{ outline:none; border-color:var(--g-500); background:#fff; }
  .toolbar button{
    padding:11px 20px; font-size:13px; font-weight:700; background:var(--g-700); color:#fff;
    border:none; border-radius:8px; cursor:pointer;
  }
  .toolbar button:hover{ background:var(--g-800); }
  .toolbar .reset{ background:var(--g-100); color:var(--g-800); border:1.5px solid var(--line); padding:11px 18px; border-radius:8px; font-size:13px; font-weight:700; }
  .toolbar .reset:hover{ background:var(--line); }

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
  th:nth-child(3), th:nth-child(4), th:nth-child(5), th:nth-child(6){ text-align:right; }
  th:last-child{ text-align:center; width:160px; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:nth-child(3), td:nth-child(4), td:nth-child(5), td:nth-child(6){ text-align:right; font-family:'IBM Plex Mono', monospace; }
  td:last-child{ text-align:center; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .student-name{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }
  .class-tag{
    display:inline-block; font-size:11px; padding:3px 10px; border-radius:20px;
    background:var(--g-100); color:var(--g-700); font-weight:700; border:1px solid #BFE7CC;
  }

  .status-pill{
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 12px 4px 9px; border-radius:20px; font-size:11px; font-weight:700;
  }
  .status-paid{ background:var(--g-100); color:var(--g-700); border:1px solid #BFE7CC; }
  .status-paid::before{ content:''; width:6px; height:6px; border-radius:50%; background:var(--g-500); }
  .status-partial{ background:#FFF7E0; color:#8A6300; border:1px solid #F3E3AC; }
  .status-partial::before{ content:''; width:6px; height:6px; border-radius:50%; background:#F3C744; }
  .status-unpaid{ background:#FDEDED; color:#B42318; border:1px solid #F3B8B8; }
  .status-unpaid::before{ content:''; width:6px; height:6px; border-radius:50%; background:#B42318; }
  .status-unassigned{ background:var(--g-100); color:var(--slate-light); border:1px solid var(--line); }
  .status-unassigned::before{ content:''; width:6px; height:6px; border-radius:50%; background:var(--slate-light); }

  .balance-clear{ color:var(--g-700); font-weight:700; }
  .balance-owing{ color:#B42318; font-weight:700; }

  .pay-btn{
    padding:8px 16px; font-size:11.5px; font-weight:700; border-radius:6px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700)); color:#fff;
    border:none; cursor:pointer; transition:.15s ease;
  }
  .pay-btn:hover{ filter:brightness(1.08); }
  .pay-btn:disabled{ background:var(--slate-light); cursor:not-allowed; }

  .empty-state{ text-align:center; padding:60px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:17px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  /* Modal */
  .modal-overlay{
    display:none; position:fixed; inset:0; background:rgba(6,33,15,0.55);
    z-index:1000; align-items:center; justify-content:center; padding:20px;
  }
  .modal-overlay.open{ display:flex; }
  .modal-box{
    background:var(--white); border-radius:16px; padding:32px 30px 28px;
    width:100%; max-width:420px; box-shadow:0 24px 60px rgba(0,0,0,0.3);
  }
  .modal-title{ font-family:'Fraunces', serif; font-size:19px; font-weight:700; color:var(--g-800); margin-bottom:4px; }
  .modal-sub{ font-size:12.5px; color:var(--slate); margin-bottom:22px; }
  .modal-field{ margin-bottom:16px; }
  .modal-field label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .modal-field input, .modal-field select{
    width:100%; padding:12px 14px; font-size:14px; background:var(--g-100);
    border:1.5px solid var(--line); border-radius:8px; font-family:'Inter', sans-serif; color:var(--ink);
  }
  .modal-field input:focus, .modal-field select:focus{ outline:none; border-color:var(--g-500); background:#fff; }
  .modal-actions{ display:flex; gap:10px; margin-top:22px; }
  .modal-btn{
    flex:1; padding:13px; font-size:13.5px; font-weight:700; border-radius:8px;
    border:none; cursor:pointer; transition:.15s ease;
  }
  .modal-btn.save{ background: linear-gradient(135deg, var(--g-500), var(--g-700)); color:#fff; }
  .modal-btn.save:hover{ filter:brightness(1.08); }
  .modal-btn.cancel{ background:var(--g-100); color:var(--g-800); border:1.5px solid var(--line); }
  .modal-btn.cancel:hover{ background:var(--line); }

  @media (max-width:1100px){
    .stat-grid{ grid-template-columns:repeat(2, 1fr); }
  }
  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .table-card{ overflow-x:auto; }
    table{ min-width:820px; }
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
    <a href="fees.php" class="active"><span class="dot"></span>Fees</a>
    <a href="assign_fees.php"><span class="dot"></span>Assign Fees</a>
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

  <div class="head-row">
    <div>
      <div class="eyebrow">Records &middot; Fees</div>
      <h1>Fees</h1>
    </div>
    <a href="assign_fees.php" class="assign-link">+ Assign Fees</a>
  </div>

  <?php if(isset($_GET['paid'])){ ?>
    <div class="banner-success">
      &#10003; Payment recorded successfully.
      <a href="receipt.php?receipt=<?php echo urlencode($_GET['receipt']); ?>" class="receipt-link" target="_blank">View Receipt &rarr;</a>
    </div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <div class="stat-grid">
    <div class="stat money">
      <div class="label">Total Expected</div>
      <div class="value">GHS <?php echo number_format($summary['total_due'], 2); ?></div>
    </div>
    <div class="stat good">
      <div class="label">Total Collected</div>
      <div class="value">GHS <?php echo number_format($summary['total_paid'], 2); ?></div>
    </div>
    <div class="stat warn">
      <div class="label">Outstanding</div>
      <div class="value">GHS <?php echo number_format($totalOutstanding, 2); ?></div>
    </div>
    <div class="stat">
      <div class="label">Fully Paid Students</div>
      <div class="value"><?php echo $fullyPaidCount; ?></div>
    </div>
  </div>

  <form method="GET" class="toolbar">
    <input type="text" name="search" placeholder="Search by name or admission number..." value="<?php echo htmlspecialchars($search); ?>">
    <select name="class_id">
      <option value="">All Classes</option>
      <?php foreach($classes as $c){ ?>
        <option value="<?php echo $c['id']; ?>" <?php echo $classFilter == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
      <?php } ?>
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      <option value="paid" <?php echo $statusFilter=='paid'?'selected':''; ?>>Fully Paid</option>
      <option value="partial" <?php echo $statusFilter=='partial'?'selected':''; ?>>Partial</option>
      <option value="unpaid" <?php echo $statusFilter=='unpaid'?'selected':''; ?>>Unpaid</option>
      <option value="unassigned" <?php echo $statusFilter=='unassigned'?'selected':''; ?>>No Fees Assigned</option>
    </select>
    <button type="submit">Filter</button>
    <a href="fees.php" class="reset">Reset</a>
  </form>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Class</th>
          <th>Due</th>
          <th>Paid</th>
          <th>Balance</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($filtered) > 0){ ?>
          <?php foreach($filtered as $r){ ?>
            <tr>
              <td>
                <span class="student-name"><?php echo htmlspecialchars($r['first_name']." ".$r['last_name']); ?></span><br>
                <span class="mono"><?php echo htmlspecialchars($r['admission_number']); ?></span>
              </td>
              <td>
                <?php if($r['class_name']){ ?>
                <span class="class-tag"><?php echo htmlspecialchars($r['class_name']); ?></span>
                <?php }else{ ?>
                  &mdash;
                <?php } ?>
              </td>
              <td>GHS <?php echo number_format($r['total_due'], 2); ?></td>
              <td>GHS <?php echo number_format($r['total_paid'], 2); ?></td>
              <td class="<?php echo $r['balance'] <= 0 ? 'balance-clear' : 'balance-owing'; ?>">
                GHS <?php echo number_format(max($r['balance'],0), 2); ?>
              </td>
              <td>
                <?php
                $labels = ['paid'=>'Fully Paid','partial'=>'Partial','unpaid'=>'Unpaid','unassigned'=>'Not Assigned'];
                ?>
                <span class="status-pill status-<?php echo $r['status']; ?>"><?php echo $labels[$r['status']]; ?></span>
              </td>
              <td>
                <button
                  class="pay-btn"
                  <?php echo $r['status']=='unassigned' ? 'disabled title="Assign fees first"' : ''; ?>
                  onclick="openModal(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['first_name']." ".$r['last_name'])); ?>')"
                >Record Payment</button>
              </td>
            </tr>
          <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="7">
              <div class="empty-state">
                <h3>No students found</h3>
                <p>Try a different search or filter, or add students first.</p>
              </div>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

</div>

<div class="modal-overlay" id="payModal">
  <div class="modal-box">
    <div class="modal-title">Record Payment</div>
    <div class="modal-sub" id="modalStudentName">&nbsp;</div>

    <form method="POST">
      <input type="hidden" name="student_id" id="modalStudentId">

      <div class="modal-field">
        <label for="amount">Amount (GHS)</label>
        <input type="number" step="0.01" id="amount" name="amount" placeholder="e.g. 500.00" required autofocus>
      </div>

      <div class="modal-field">
        <label for="payment_method">Payment Method</label>
        <select id="payment_method" name="payment_method" required>
          <option value="Cash">Cash</option>
          <option value="Mobile Money">Mobile Money</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="Cheque">Cheque</option>
        </select>
      </div>

      <div class="modal-field">
        <label for="payment_date">Payment Date</label>
        <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
      </div>

      <div class="modal-actions">
        <button type="button" class="modal-btn cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="modal-btn save">Save Payment</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(studentId, studentName) {
  document.getElementById('modalStudentId').value = studentId;
  document.getElementById('modalStudentName').textContent = studentName;
  document.getElementById('payModal').classList.add('open');
}
function closeModal() {
  document.getElementById('payModal').classList.remove('open');
}
</script>

</body>
</html>
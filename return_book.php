<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

/* RETURN A BOOK */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['issue_id'])) {

    $issue_id = (int)$_POST['issue_id'];

    $check = $conn->prepare("
        SELECT * FROM library_issues
        WHERE id = ? AND school_id = ? AND status = 'Issued'
    ");
    $check->execute([$issue_id, $school_id]);
    $record = $check->fetch(PDO::FETCH_ASSOC);

    if ($record) {

        $update = $conn->prepare("
            UPDATE library_issues
            SET status = 'Returned', return_date = ?
            WHERE id = ? AND school_id = ?
        ");
        $update->execute([date('Y-m-d'), $issue_id, $school_id]);

        $updateBook = $conn->prepare("
            UPDATE library_books
            SET available_copies = available_copies + 1
            WHERE id = ? AND school_id = ?
        ");
        $updateBook->execute([$record['book_id'], $school_id]);

        header("Location: return_book.php?returned=1");
        exit();

    } else {
        header("Location: return_book.php?invalid=1");
        exit();
    }
}

/* Books currently out */
$booksOut = $conn->prepare("
    SELECT li.id, li.issue_date, li.due_date, lb.title, lb.book_code, s.admission_number, s.first_name, s.last_name
    FROM library_issues li
    INNER JOIN library_books lb ON lb.id = li.book_id
    INNER JOIN students s ON s.id = li.student_id
    WHERE li.school_id = ? AND li.status = 'Issued'
    ORDER BY li.due_date ASC
");
$booksOut->execute([$school_id]);
$booksOut = $booksOut->fetchAll(PDO::FETCH_ASSOC);

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Return Book - Klaso</title>
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
    font-size:13px; font-weight:600;
  }
  .alert-error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:13px 16px;
    border-radius:8px; margin:20px 0;
  }

  .table-card{
    background:var(--white); border:1.5px solid var(--line); border-radius:12px;
    overflow:hidden; box-shadow:0 6px 20px rgba(11,61,31,0.07);
    margin-top:22px;
  }
  table{ width:100%; border-collapse:collapse; }
  thead tr{ background: linear-gradient(90deg, var(--g-800), var(--g-700)); }
  th{
    color:#fff; padding:14px 18px; font-size:10.5px; letter-spacing:1.1px;
    text-transform:uppercase; font-weight:700; text-align:left;
  }
  th:last-child{ text-align:right; width:140px; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:last-child{ text-align:right; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }

  .book-title{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }

  .due-pill{
    display:inline-block; font-size:11.5px; padding:4px 12px; border-radius:20px; font-weight:700;
    background:var(--g-100); color:var(--g-700); border:1px solid #BFE7CC;
  }
  .due-pill.overdue{ background:#FDEDED; color:#B42318; border:1px solid #F3B8B8; }

  .return-btn{
    padding:9px 18px; font-size:12px; font-weight:700; border-radius:6px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700)); color:#fff;
    border:none; cursor:pointer; transition:.15s ease;
  }
  .return-btn:hover{ filter:brightness(1.08); }

  .empty-state{ text-align:center; padding:60px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:17px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .table-card{ overflow-x:auto; }
    table{ min-width:640px; }
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
    <a href="assign_fees.php"><span class="dot"></span>Assign Fees</a>
  </nav>

  <div class="nav-group-label">Library</div>
  <nav>
    <a href="library_books.php"><span class="dot"></span>Books Available</a>
    <a href="issue_book.php"><span class="dot"></span>Issue Book</a>
    <a href="return_book.php" class="active"><span class="dot"></span>Return Book</a>
  </nav>

  <div class="sidebar-foot">
    <a href="../auth/logout.php">&#8617; Logout</a>
  </div>
</div>

<div class="main">

  <div class="page-head">
    <div class="eyebrow">Library</div>
    <h1>Return Book</h1>
  </div>

  <?php if(isset($_GET['returned'])){ ?>
    <div class="banner-success">&#10003; Book returned successfully.</div>
  <?php } ?>

  <?php if(isset($_GET['invalid'])){ ?>
    <div class="alert-error">That book issue record could not be found or was already returned.</div>
  <?php } ?>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Book</th>
          <th>Student</th>
          <th>Issue Date</th>
          <th>Due Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($booksOut) > 0){ ?>
          <?php foreach($booksOut as $b){
            $isOverdue = strtotime($b['due_date']) < strtotime(date('Y-m-d'));
          ?>
            <tr>
              <td>
                <span class="book-title"><?php echo htmlspecialchars($b['title']); ?></span><br>
                <span class="mono"><?php echo htmlspecialchars($b['book_code']); ?></span>
              </td>
              <td>
                <?php echo htmlspecialchars($b['first_name']." ".$b['last_name']); ?><br>
                <span class="mono"><?php echo htmlspecialchars($b['admission_number']); ?></span>
              </td>
              <td><?php echo date('d M Y', strtotime($b['issue_date'])); ?></td>
              <td>
                <span class="due-pill <?php echo $isOverdue ? 'overdue' : ''; ?>">
                  <?php echo date('d M Y', strtotime($b['due_date'])); ?><?php echo $isOverdue ? ' (Overdue)' : ''; ?>
                </span>
              </td>
              <td>
                <form method="POST" onsubmit="return confirm('Mark this book as returned?');">
                  <input type="hidden" name="issue_id" value="<?php echo $b['id']; ?>">
                  <button type="submit" class="return-btn">Mark Returned</button>
                </form>
              </td>
            </tr>
          <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="5">
              <div class="empty-state">
                <h3>No books currently out</h3>
                <p>All issued books have been returned.</p>
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
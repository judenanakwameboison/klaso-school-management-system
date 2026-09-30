<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $book_code = trim($_POST['book_code']);
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $total_copies = trim($_POST['total_copies']);

    if ($book_code == "" || $title == "" || !is_numeric($total_copies) || $total_copies < 1) {
        $error = "Book code, title, and a valid copy count are required.";
    } else {

        $check = $conn->prepare("SELECT id FROM library_books WHERE book_code = ? AND school_id = ?");
        $check->execute([$book_code, $school_id]);

        if ($check->rowCount() > 0) {
            $error = "A book with this code already exists.";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO library_books (school_id, book_code, title, author, total_copies, available_copies, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Available')
            ");
            $stmt->execute([$school_id, $book_code, $title, $author, $total_copies, $total_copies]);

            header("Location: library_books.php?added=1");
            exit();
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$showRetired = isset($_GET['show_retired']);

$sql = "SELECT * FROM library_books WHERE school_id = ?";
$params = [$school_id];

if (!$showRetired) {
    $sql .= " AND status != 'Retired'";
}

if ($search != "") {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR book_code LIKE ?)";
    $keyword = "%$search%";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

$sql .= " ORDER BY status ASC, title ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalResults = count($books);

$schoolStmt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

$deleted = isset($_GET['deleted']);
$blocked = isset($_GET['blocked']);
$retired = isset($_GET['retired']);
$reactivated = isset($_GET['reactivated']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Library - Klaso</title>
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
    display:flex; align-items:center; gap:10px; font-size:13px; font-weight:600;
  }
  .alert-error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:13px 16px;
    border-radius:8px; margin:20px 0;
  }

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

  .form-grid{ display:grid; grid-template-columns:1fr 2fr 1.5fr 1fr; gap:16px; align-items:end; }
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

  .toolbar{
    display:flex; justify-content:space-between; align-items:center;
    margin:22px 0; flex-wrap:wrap; gap:14px;
  }
  .search-box{ display:flex; gap:10px; align-items:center; }
  .search-box input{
    width:280px; padding:11px 14px; font-size:13.5px;
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
  }
  .search-box button:hover{ background:var(--g-800); }
  .reset-link{
    padding:11px 18px; font-size:13px; font-weight:700;
    background:var(--g-100); color:var(--g-800); border:1.5px solid var(--line); border-radius:8px;
  }
  .reset-link:hover{ background:var(--line); }
  .toggle-label{
    display:flex; align-items:center; gap:6px; font-size:12.5px; color:var(--slate); font-weight:600; cursor:pointer;
  }
  .toggle-label input{ width:15px; height:15px; accent-color:var(--g-500); cursor:pointer; }
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
  th:nth-child(4), th:nth-child(5){ text-align:center; }
  th:last-child{ text-align:right; width:200px; }
  td{
    padding:12px 18px; border-bottom:1px solid var(--line); font-size:13px;
    vertical-align:middle; color:var(--ink);
  }
  td:nth-child(4), td:nth-child(5){ text-align:center; }
  td:last-child{ text-align:right; }
  tbody tr:hover{ background:var(--g-100); }
  tbody tr:last-child td{ border-bottom:none; }
  tbody tr.retired-row{ opacity:0.55; }

  .book-title{ font-weight:700; color:var(--g-800); }
  .mono{ font-family:'IBM Plex Mono', monospace; font-size:12px; color:var(--slate); }
  .author{ color:var(--slate); }

  .avail-pill{
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 12px; border-radius:20px; font-size:11.5px; font-weight:700;
    font-family:'IBM Plex Mono', monospace;
  }
  .avail-yes{ background:var(--g-100); color:var(--g-700); border:1px solid #BFE7CC; }
  .avail-no{ background:#FDEDED; color:#B42318; border:1px solid #F3B8B8; }
  .avail-retired{ background:var(--g-100); color:var(--slate-light); border:1px solid var(--line); }

  .action-group{ display:flex; gap:6px; justify-content:flex-end; }
  .row-btn{
    padding:7px 12px; font-size:11.5px; font-weight:700; border-radius:6px;
    transition:.15s ease; white-space:nowrap; border:none; cursor:pointer;
    font-family:'Inter', sans-serif;
  }
  .retire-btn{ background:#FFF7E0; color:#8A6300; border:1px solid #F3E3AC; }
  .retire-btn:hover{ background:#F3C744; color:#3D2E00; }
  .reactivate-btn{ background:var(--g-100); color:var(--g-700); border:1px solid #BFE7CC; }
  .reactivate-btn:hover{ background:var(--g-600); color:#fff; }
  .delete-btn{ background:#FDEDED; color:#B42318; border:1px solid #F3B8B8; }
  .delete-btn:hover{ background:#DC3545; color:#fff; }

  .empty-state{ text-align:center; padding:60px 20px; }
  .empty-state h3{ font-family:'Fraunces', serif; font-size:17px; color:var(--slate); font-weight:600; }
  .empty-state p{ font-size:12.5px; color:var(--slate-light); margin-top:6px; }

  @media (max-width:980px){
    .sidebar{ display:none; }
    .main{ margin-left:0; padding:24px; }
    .form-grid{ grid-template-columns:1fr; }
    .table-card{ overflow-x:auto; }
    table{ min-width:720px; }
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
    <div class="eyebrow">Library</div>
    <h1>Books</h1>
  </div>

  <?php if(isset($_GET['added'])){ ?>
    <div class="banner-success">&#10003; Book added successfully.</div>
  <?php } ?>

  <?php if($deleted){ ?>
    <div class="banner-success">&#10003; Book removed successfully.</div>
  <?php } ?>

  <?php if($retired){ ?>
    <div class="banner-success">&#10003; Book retired. It's hidden from active circulation but its history is preserved.</div>
  <?php } ?>

  <?php if($reactivated){ ?>
    <div class="banner-success">&#10003; Book reactivated and available again.</div>
  <?php } ?>

  <?php if($blocked){ ?>
    <div class="alert-error">Cannot permanently delete this book — it has borrowing history. Use "Retire" instead to remove it from circulation.</div>
  <?php } ?>

  <?php if($error != ""){ ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php } ?>

  <div class="form-card">
    <h3>Add Book</h3>
    <form method="POST">
      <div class="form-grid">
        <div class="field">
          <label for="book_code">Book Code</label>
          <input type="text" id="book_code" name="book_code" placeholder="e.g. BK-001" required>
        </div>
        <div class="field">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" required>
        </div>
        <div class="field">
          <label for="author">Author</label>
          <input type="text" id="author" name="author" placeholder="Optional">
        </div>
        <div class="field">
          <label for="total_copies">Copies</label>
          <input type="number" id="total_copies" name="total_copies" min="1" value="1" required>
        </div>
      </div>
      <br>
      <button type="submit" class="btn btn-primary" style="max-width:200px;">Save Book</button>
    </form>
  </div>

  <form method="GET" class="toolbar">
    <div class="search-box">
      <input type="text" name="search" placeholder="Search title, author, or code..." value="<?php echo htmlspecialchars($search); ?>">
      <button type="submit">Search</button>
      <a href="library_books.php" class="reset-link">Reset</a>
    </div>
    <label class="toggle-label">
      <input type="checkbox" name="show_retired" value="1" onchange="this.form.submit()" <?php echo $showRetired ? 'checked' : ''; ?>>
      Show retired books
    </label>
    <div class="result-count"><strong><?php echo $totalResults; ?></strong> book<?php echo $totalResults != 1 ? 's' : ''; ?> found</div>
  </form>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Title</th>
          <th>Author</th>
          <th>Copies</th>
          <th>Available</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(count($books) > 0){ ?>
        <?php foreach($books as $b){ ?>
            <tr class="<?php echo $b['status']=='Retired' ? 'retired-row' : ''; ?>">
              <td class="mono"><?php echo htmlspecialchars($b['book_code']); ?></td>
              <td><span class="book-title"><?php echo htmlspecialchars($b['title']); ?></span></td>
              <td class="author"><?php echo $b['author'] ? htmlspecialchars($b['author']) : '&mdash;'; ?></td>
              <td class="mono"><?php echo $b['total_copies']; ?></td>
              <td>
                <?php if($b['status']=='Retired'){ ?>
                  <span class="avail-pill avail-retired">Retired</span>
                <?php }elseif($b['available_copies'] > 0){ ?>
                  <span class="avail-pill avail-yes"><?php echo $b['available_copies']; ?> available</span>
                <?php }else{ ?>
                  <span class="avail-pill avail-no">All issued</span>
                <?php } ?>
              </td>
              <td>
                <div class="action-group">
                  <?php if($b['status']!='Retired'){ ?>
                    <a class="row-btn retire-btn" href="retire_book.php?id=<?php echo $b['id']; ?>" onclick="return confirm('Retire this book from circulation?');">Retire</a>
                  <?php }else{ ?>
                    <a class="row-btn reactivate-btn" href="reactivate_book.php?id=<?php echo $b['id']; ?>" onclick="return confirm('Reactivate this book?');">Reactivate</a>
                  <?php } ?>
                  <a class="row-btn delete-btn" href="delete_book.php?id=<?php echo $b['id']; ?>" onclick="return confirm('Permanently delete this book? This only works if it has no borrowing history.');">Delete</a>
                </div>
              </td>
            </tr>
          <?php } ?>
        <?php }else{ ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <h3>No books found</h3>
                <p><?php echo $search != "" ? "Try a different search term." : "Add your first book using the form above."; ?></p>
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
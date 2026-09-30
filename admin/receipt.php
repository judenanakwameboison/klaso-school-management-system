<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

if (!isset($_GET['receipt'])) {
    header("Location: fees.php");
    exit();
}

$receipt_number = $_GET['receipt'];

/* Load the specific payment, scoped to this school */
$stmt = $conn->prepare("
    SELECT f.*, s.admission_number, s.first_name, s.last_name, c.class_name
    FROM fees f
    INNER JOIN students s ON s.id = f.student_id
    LEFT JOIN classes c ON c.id = s.class_id
    WHERE f.receipt_number = ? AND f.school_id = ?
");
$stmt->execute([$receipt_number, $school_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header("Location: fees.php");
    exit();
}

/* Pull this student's overall totals, not just this one transaction */
$totalsStmt = $conn->prepare("
    SELECT IFNULL(SUM(amount_due),0) AS total_due, IFNULL(SUM(amount_paid),0) AS total_paid
    FROM fees
    WHERE student_id = ? AND school_id = ?
");
$totalsStmt->execute([$payment['student_id'], $school_id]);
$totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);
$overallBalance = $totals['total_due'] - $totals['total_paid'];
if ($overallBalance < 0) { $overallBalance = 0; }

$schoolStmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt <?php echo htmlspecialchars($receipt_number); ?> - Klaso</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --g-900:#06210F; --g-800:#0B3D1F; --g-700:#12592C; --g-600:#1B7A3E;
    --g-500:#1FA24C; --g-400:#34C566; --g-100:#E9F8EE;
    --white:#FFFFFF; --ink:#0C1B12; --slate:#5B6B60; --slate-light:#8FA096; --line:#DCEFE1;
  }
  *{ box-sizing:border-box; margin:0; padding:0; font-family:'Inter', sans-serif; }
  body{
    background:#EEF3EF; color:var(--ink); -webkit-font-smoothing:antialiased;
    display:flex; justify-content:center; padding:40px 20px;
  }

  .receipt{
    width:100%; max-width:620px; background:var(--white);
    border-radius:16px; box-shadow:0 16px 48px rgba(11,61,31,0.14);
    overflow:hidden;
  }

  .receipt-head{
    background: linear-gradient(135deg, var(--g-900), var(--g-700));
    padding:32px 36px; color:#fff; display:flex; align-items:center; gap:16px;
  }
  .school-logo{
    width:58px; height:58px; border-radius:12px; flex-shrink:0; overflow:hidden;
    background:rgba(255,255,255,0.12); display:flex; align-items:center; justify-content:center;
    border:1.5px solid rgba(255,255,255,0.2);
  }
  .school-logo img{ width:100%; height:100%; object-fit:cover; }
  .school-logo .placeholder{ font-family:'Fraunces', serif; font-size:22px; font-weight:700; color:#fff; }
  .school-name{ font-family:'Fraunces', serif; font-size:20px; font-weight:700; }
  .receipt-label{ font-size:11px; letter-spacing:1.8px; text-transform:uppercase; color:var(--g-400); margin-top:4px; font-weight:700; }

  .receipt-body{ padding:32px 36px; }

  .receipt-meta{
    display:flex; justify-content:space-between; padding-bottom:20px;
    border-bottom:1.5px dashed var(--line); margin-bottom:22px;
  }
  .meta-item .k{ font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:4px; }
  .meta-item .v{ font-size:13.5px; font-weight:700; color:var(--g-800); font-family:'IBM Plex Mono', monospace; }
  .meta-item.right{ text-align:right; }

  .student-block{ margin-bottom:24px; }
  .student-block .label{ font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:8px; }
  .student-name{ font-family:'Fraunces', serif; font-size:19px; font-weight:700; color:var(--g-800); }
  .student-sub{ font-size:12.5px; color:var(--slate); margin-top:4px; }

  .payment-box{
    background:var(--g-100); border:1.5px solid var(--line); border-radius:12px;
    padding:22px 24px; margin-bottom:24px;
  }
  .payment-row{ display:flex; justify-content:space-between; align-items:center; }
  .payment-label{ font-size:13px; color:var(--slate); font-weight:600; }
  .payment-amount{ font-family:'IBM Plex Mono', monospace; font-size:26px; font-weight:700; color:var(--g-700); }
  .payment-method-tag{
    display:inline-block; margin-top:10px; font-size:11.5px; font-weight:700;
    background:#fff; color:var(--g-700); padding:5px 12px; border-radius:20px; border:1px solid #BFE7CC;
  }

  .summary-grid{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:8px; }
  .summary-item{ text-align:center; padding:14px 10px; background:var(--white); border:1.5px solid var(--line); border-radius:10px; }
  .summary-item .k{ font-size:9.5px; letter-spacing:0.8px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:6px; }
  .summary-item .v{ font-family:'IBM Plex Mono', monospace; font-size:14px; font-weight:700; color:var(--g-800); }
  .summary-item.balance .v{ color:#B42318; }
  .summary-item.balance.clear .v{ color:var(--g-700); }

  .receipt-footer{
    text-align:center; padding:20px 36px 28px; border-top:1.5px dashed var(--line);
    font-size:11px; color:var(--slate-light); line-height:1.6;
  }

  .print-bar{
    max-width:620px; margin:0 auto 16px; display:flex; justify-content:flex-end; gap:10px;
  }
  .print-btn{
    padding:11px 22px; font-size:13px; font-weight:700; border-radius:8px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700)); color:#fff;
    border:none; cursor:pointer; box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  .back-btn{
    padding:11px 22px; font-size:13px; font-weight:700; border-radius:8px;
    background:var(--white); color:var(--g-800); border:1.5px solid var(--line); text-decoration:none;
  }

  @media print{
    body{ background:#fff; padding:0; }
    .print-bar{ display:none; }
    .receipt{ box-shadow:none; border-radius:0; }
  }
</style>
</head>
<body>

<div style="width:100%; max-width:620px; margin:0 auto;">
  <div class="print-bar">
    <a href="fees.php" class="back-btn">&larr; Back to Fees</a>
    <button class="print-btn" onclick="window.print()">Print Receipt</button>
  </div>

  <div class="receipt">

    <div class="receipt-head">
      <div class="school-logo">
        <?php if(!empty($school['logo_path'])){ ?>
          <img src="../<?php echo htmlspecialchars($school['logo_path']); ?>" alt="Logo">
        <?php }else{ ?>
          <div class="placeholder"><?php echo strtoupper(substr($school['school_name'],0,1)); ?></div>
        <?php } ?>
      </div>
      <div>
        <div class="school-name"><?php echo htmlspecialchars($school['school_name']); ?></div>
        <div class="receipt-label">Official Payment Receipt</div>
      </div>
    </div>

    <div class="receipt-body">

      <div class="receipt-meta">
        <div class="meta-item">
          <div class="k">Receipt No.</div>
          <div class="v"><?php echo htmlspecialchars($payment['receipt_number']); ?></div>
        </div>
        <div class="meta-item right">
          <div class="k">Date</div>
          <div class="v"><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></div>
        </div>
      </div>

      <div class="student-block">
        <div class="label">Received From</div>
        <div class="student-name"><?php echo htmlspecialchars($payment['first_name']." ".$payment['last_name']); ?></div>
        <div class="student-sub">
          <?php echo htmlspecialchars($payment['admission_number']); ?>
          <?php if($payment['class_name']){ ?> &middot; <?php echo htmlspecialchars($payment['class_name']); ?><?php } ?>
        </div>
      </div>

      <div class="payment-box">
        <div class="payment-row">

        <div class="payment-label">Amount Paid (This Payment)</div>
          <div class="payment-amount">GHS <?php echo number_format($payment['amount_paid'], 2); ?></div>
        </div>
        <span class="payment-method-tag"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
      </div>

      <div class="summary-grid">
        <div class="summary-item">
          <div class="k">Total Due</div>
          <div class="v">GHS <?php echo number_format($totals['total_due'], 2); ?></div>
        </div>
        <div class="summary-item">
          <div class="k">Total Paid</div>
          <div class="v">GHS <?php echo number_format($totals['total_paid'], 2); ?></div>
        </div>
        <div class="summary-item balance <?php echo $overallBalance <= 0 ? 'clear' : ''; ?>">
          <div class="k">Balance</div>
          <div class="v">GHS <?php echo number_format($overallBalance, 2); ?></div>
        </div>
      </div>

    </div>

    <div class="receipt-footer">
      This is an official receipt generated by Klaso on behalf of <?php echo htmlspecialchars($school['school_name']); ?>.<br>
      Please retain this receipt for your records.
    </div>

  </div>
</div>

</body>
</html>
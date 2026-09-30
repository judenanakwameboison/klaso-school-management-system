<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../config/auth_admin.php";
require_once "../config/db.php";

$school_id = $_SESSION['school_id'];

if (!isset($_GET['student_id']) || !isset($_GET['exam_id'])) {
    header("Location: report_card.php");
    exit();
}

$student_id = $_GET['student_id'];
$exam_id = $_GET['exam_id'];

/* Student + class info */
$studentStmt = $conn->prepare("
    SELECT s.*, c.class_name
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    WHERE s.id = ? AND s.school_id = ?
");
$studentStmt->execute([$student_id, $school_id]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: report_card.php");
    exit();
}

/* Exam info */
$examStmt = $conn->prepare("SELECT * FROM exams WHERE id = ? AND school_id = ?");
$examStmt->execute([$exam_id, $school_id]);
$exam = $examStmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header("Location: report_card.php");
    exit();
}

/* All subject results for this student + exam */
$resultsStmt = $conn->prepare("
    SELECT r.*, sub.subject_name
    FROM results r
    INNER JOIN subjects sub ON sub.id = r.subject_id
    WHERE r.student_id = ? AND r.exam_id = ? AND r.school_id = ?
    ORDER BY sub.subject_name
");
$resultsStmt->execute([$student_id, $exam_id, $school_id]);
$results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

/* Overall performance summary */
$totalSubjects = count($results);
$overallAverage = $totalSubjects > 0 ? array_sum(array_column($results, 'total_score')) / $totalSubjects : 0;
$overallGrade = 'N/A';
if ($overallAverage >= 80) $overallGrade = 'A';
elseif ($overallAverage >= 70) $overallGrade = 'B';
elseif ($overallAverage >= 60) $overallGrade = 'C';
elseif ($overallAverage >= 50) $overallGrade = 'D';
elseif ($overallAverage >= 40) $overallGrade = 'E';
elseif ($totalSubjects > 0) $overallGrade = 'F';

/* Class position: rank this student against classmates by average total score for this exam */
$position = "N/A";
if ($totalSubjects > 0 && $student['class_id']) {

    $rankStmt = $conn->prepare("
        SELECT s.id, AVG(r.total_score) AS avg_score
        FROM students s
        INNER JOIN results r ON r.student_id = s.id AND r.exam_id = ? AND r.school_id = s.school_id
        WHERE s.class_id = ? AND s.school_id = ?
        GROUP BY s.id
        ORDER BY avg_score DESC
    ");
    $rankStmt->execute([$exam_id, $student['class_id'], $school_id]);
    $rankings = $rankStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rankings as $index => $r) {
        if ($r['id'] == $student_id) {
            $position = ($index + 1) . " of " . count($rankings);
            break;
        }
    }
}

/* Attendance summary for the exam's term/year */
$attendanceStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_marked,
        SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) AS attended
    FROM attendance
    WHERE student_id = ? AND school_id = ?
");
$attendanceStmt->execute([$student_id, $school_id]);
$attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
$attendancePercent = $attendance['total_marked'] > 0 ? round(($attendance['attended'] / $attendance['total_marked']) * 100) : null;

$schoolStmt = $conn->prepare("SELECT * FROM schools WHERE id = ?");
$schoolStmt->execute([$school_id]);
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Report Card - <?php echo htmlspecialchars($student['first_name']." ".$student['last_name']); ?></title>
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

  .sheet{
    width:100%; max-width:760px; background:var(--white);
    border-radius:16px; box-shadow:0 16px 48px rgba(11,61,31,0.14);
    overflow:hidden;
  }

  .sheet-head{
    background: linear-gradient(135deg, var(--g-900), var(--g-700));
    padding:32px 40px; color:#fff; display:flex; align-items:center; gap:18px;
  }
  .school-logo{
    width:64px; height:64px; border-radius:14px; flex-shrink:0; overflow:hidden;
    background:rgba(255,255,255,0.12); display:flex; align-items:center; justify-content:center;
    border:1.5px solid rgba(255,255,255,0.2);
  }
  .school-logo img{ width:100%; height:100%; object-fit:cover; }
  .school-logo .placeholder{ font-family:'Fraunces', serif; font-size:24px; font-weight:700; color:#fff; }
  .school-name{ font-family:'Fraunces', serif; font-size:22px; font-weight:700; }
  .report-label{ font-size:11px; letter-spacing:1.8px; text-transform:uppercase; color:var(--g-400); margin-top:4px; font-weight:700; }

  .sheet-body{ padding:36px 40px; }

  .student-info{
    display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;
    background:var(--g-100); border:1.5px solid var(--line); border-radius:12px;
    padding:20px 22px; margin-bottom:28px;
  }
  .info-item .k{ font-size:9.5px; letter-spacing:1px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:5px; }
  .info-item .v{ font-size:14px; font-weight:700; color:var(--g-800); }

  .section-label{
    font-size:11px; letter-spacing:1.6px; text-transform:uppercase; color:var(--g-600);
    font-weight:800; margin-bottom:14px;
  }

  table{ width:100%; border-collapse:collapse; margin-bottom:28px; }
  thead tr{ background: linear-gradient(90deg, var(--g-800), var(--g-700)); }
  th{
    color:#fff; padding:11px 16px; font-size:10px; letter-spacing:1px;
    text-transform:uppercase; font-weight:700; text-align:left;
  }
  th:nth-child(2), th:nth-child(3), th:nth-child(4), th:nth-child(5){ text-align:center; }
  td{
    padding:10px 16px; border-bottom:1px solid var(--line); font-size:13px; color:var(--ink);
  }
  td:nth-child(2), td:nth-child(3), td:nth-child(4), td:nth-child(5){ text-align:center; }
  tbody tr:last-child td{ border-bottom:none; }

  .subject-name{ font-weight:700; color:var(--g-800); }
  .total-score{ font-family:'IBM Plex Mono', monospace; font-weight:700; color:var(--g-700); }
  .grade-cell{ font-family:'IBM Plex Mono', monospace; font-weight:800; }
  .grade-f{ color:#B42318; }
  .grade-e{ color:#8A6300; }

  .summary-grid{ display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:8px; }
  .summary-item{ text-align:center; padding:16px 10px; background:var(--g-100); border:1.5px solid var(--line); border-radius:10px; }
  .summary-item .k{ font-size:9.5px; letter-spacing:0.8px; text-transform:uppercase; color:var(--slate); font-weight:700; margin-bottom:6px; }
  .summary-item .v{ font-family:'IBM Plex Mono', monospace; font-size:17px; font-weight:800; color:var(--g-800); }

  .remarks-box{
    margin-top:24px; padding:18px 20px; background:var(--g-100); border:1.5px solid var(--line);
    border-radius:10px; font-size:12.5px; color:var(--slate); line-height:1.6;
  }
  .remarks-box .k{ font-size:10px; letter-spacing:1px; text-transform:uppercase; color:var(--g-700); font-weight:800; margin-bottom:6px; }

  .signature-row{ display:flex; justify-content:space-between; margin-top:40px; padding-top:20px; }
  .signature-block{ text-align:center; width:180px; }
  .signature-line{ border-top:1.5px solid var(--ink); margin-bottom:6px; }
  .signature-label{ font-size:10.5px; color:var(--slate); }

  .sheet-footer{
    text-align:center; padding:20px 40px 28px; border-top:1.5px dashed var(--line);
    font-size:11px; color:var(--slate-light); line-height:1.6;
  }

  .print-bar{ max-width:760px; margin:0 auto 16px; display:flex; justify-content:flex-end; gap:10px; }
  .print-btn{
    padding:11px 22px; font-size:13px; font-weight:700; border-radius:8px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700)); color:#fff;
    border:none; cursor:pointer; box-shadow:0 8px 20px rgba(31,162,76,0.3);
  }
  .back-btn{
    padding:11px 22px; font-size:13px; font-weight:700; border-radius:8px;
    background:var(--white); color:var(--g-800); border:1.5px solid var(--line); text-decoration:none;
  }

  .empty-note{ text-align:center; padding:40px 20px; color:var(--slate-light); font-size:13px; }

  @media print{
    body{ background:#fff; padding:0; }
    .print-bar{ display:none; }
    .sheet{ box-shadow:none; border-radius:0; }
  }
</style>
</head>
<body>

<div style="width:100%; max-width:760px; margin:0 auto;">
  <div class="print-bar">
    <a href="report_card.php" class="back-btn">&larr; Back</a>
    <button class="print-btn" onclick="window.print()">Print Report Card</button>
  </div>

  <div class="sheet">

    <div class="sheet-head">
      <div class="school-logo">
        <?php if(!empty($school['logo_path'])){ ?>
          <img src="../<?php echo htmlspecialchars($school['logo_path']); ?>" alt="Logo">
        <?php }else{ ?>
          <div class="placeholder"><?php echo strtoupper(substr($school['school_name'],0,1)); ?></div>
        <?php } ?>
      </div>
      <div>
        <div class="school-name"><?php echo htmlspecialchars($school['school_name']); ?></div>
        <div class="report-label">Student Report Card</div>
      </div>
    </div>

    <div class="sheet-body">

      <div class="student-info">
        <div class="info-item">
          <div class="k">Student Name</div>
          <div class="v"><?php echo htmlspecialchars($student['first_name'].($student['middle_name'] ? ' '.$student['middle_name'] : '').' '.$student['last_name']); ?></div>
        </div>
        <div class="info-item">
          <div class="k">Admission No.</div>
          <div class="v"><?php echo htmlspecialchars($student['admission_number']); ?></div>
        </div>
        <div class="info-item">
          <div class="k">Class</div>
          <div class="v"><?php echo $student['class_name'] ? htmlspecialchars($student['class_name']) : 'Not Assigned'; ?></div>
        </div>
        <div class="info-item">
          <div class="k">Examination</div>
          <div class="v"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
        </div>
        <div class="info-item">
          <div class="k">Term</div>
          <div class="v"><?php echo htmlspecialchars($exam['term']); ?></div>
        </div>
        <div class="info-item">
          <div class="k">Academic Year</div>
          <div class="v"><?php echo htmlspecialchars($exam['academic_year']); ?></div>
        </div>
      </div>

      <?php if(count($results) > 0){ ?>

        <div class="section-label">Subject Performance</div>

        <table>
          <thead>
            <tr>
              <th>Subject</th>
              <th>Class Score</th>
              <th>Exam Score</th>
              <th>Total</th>
              <th>Grade</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($results as $r){
              $gClass = '';
              if($r['grade']=='F'){ $gClass='grade-f'; }
              elseif($r['grade']=='E'){ $gClass='grade-e'; }
            ?>
              <tr>
                <td><span class="subject-name"><?php echo htmlspecialchars($r['subject_name']); ?></span></td>
                <td><?php echo number_format($r['class_score'],1); ?></td>
                <td><?php echo number_format($r['exam_score'],1); ?></td>
                <td><span class="total-score"><?php echo number_format($r['total_score'],1); ?></span></td>
                <td><span class="grade-cell <?php echo $gClass; ?>"><?php echo htmlspecialchars($r['grade']); ?></span></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>

        <div class="summary-grid">
            <div class="summary-item">
            <div class="k">Overall Average</div>
            <div class="v"><?php echo number_format($overallAverage, 1); ?></div>
          </div>
          <div class="summary-item">
            <div class="k">Overall Grade</div>
            <div class="v"><?php echo $overallGrade; ?></div>
          </div>
          <div class="summary-item">
            <div class="k">Class Position</div>
            <div class="v"><?php echo $position; ?></div>
          </div>
          <div class="summary-item">
            <div class="k">Attendance</div>
            <div class="v"><?php echo $attendancePercent !== null ? $attendancePercent."%" : "N/A"; ?></div>
          </div>
        </div>

        <div class="remarks-box">
          <div class="k">Teacher's Remarks</div>
          <?php
          if ($overallGrade == 'A') echo "Excellent performance. Keep up the outstanding work.";
          elseif ($overallGrade == 'B') echo "Very good performance this term.";
          elseif ($overallGrade == 'C') echo "Good performance, with room for improvement in some areas.";
          elseif ($overallGrade == 'D') echo "Satisfactory performance. More effort is encouraged.";
          elseif ($overallGrade == 'E') echo "Below average performance. Requires additional support and attention.";
          elseif ($overallGrade == 'F') echo "Performance requires urgent attention and improvement.";
          else echo "No results available for this exam yet.";
          ?>
        </div>

        <div class="signature-row">
          <div class="signature-block">
            <div class="signature-line"></div>
            <div class="signature-label">Class Teacher</div>
          </div>
          <div class="signature-block">
            <div class="signature-line"></div>
            <div class="signature-label">Head of School</div>
          </div>
        </div>

      <?php }else{ ?>
        <div class="empty-note">No results have been recorded for this student and examination yet.</div>
      <?php } ?>

    </div>

    <div class="sheet-footer">
      This report card was generated by Klaso on behalf of <?php echo htmlspecialchars($school['school_name']); ?>.
    </div>

  </div>
</div>

</body>
</html>
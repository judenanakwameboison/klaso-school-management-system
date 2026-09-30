<?php
require_once "../config/db.php";

$message = "";
$isError = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $school_name = trim($_POST['school_name']);
    $contact_name = trim($_POST['contact_name']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);
    $msg = trim($_POST['message']);

    if ($school_name == "" || $contact_name == "" || $contact_email == "") {
        $message = "School name, contact name and email are required.";
        $isError = true;
    } else {

        $check = $conn->prepare("
            SELECT id FROM school_applications
            WHERE contact_email = ? AND status = 'Pending'
        ");
        $check->execute([$contact_email]);

        if ($check->rowCount() > 0) {
            $message = "An application from this email is already pending review.";
            $isError = true;
        } else {

            $logo_path = null;

            /* Handle logo upload, if one was provided */
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {

                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB

                $fileType = $_FILES['logo']['type'];
                $fileSize = $_FILES['logo']['size'];
                $fileTmp = $_FILES['logo']['tmp_name'];
                $fileExt = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

                if (!in_array($fileType, $allowedTypes)) {
                    $message = "Logo must be a JPG, PNG, or WEBP image.";
                    $isError = true;
                } elseif ($fileSize > $maxSize) {
                    $message = "Logo file must be under 2MB.";
                    $isError = true;
                } else {

                    $newFileName = "logo_" . bin2hex(random_bytes(8)) . "." . $fileExt;
                    $destination = "../assets/school_logos/" . $newFileName;

                    if (move_uploaded_file($fileTmp, $destination)) {
                        $logo_path = "assets/school_logos/" . $newFileName;
                    } else {
                        $message = "Failed to upload logo. Please try again.";
                        $isError = true;
                    }
                }
            }

            if (!$isError) {

                $stmt = $conn->prepare("
                    INSERT INTO school_applications
                    (school_name, contact_name, contact_email, contact_phone, message, logo_path)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $school_name,
                    $contact_name,
                    $contact_email,
                    $contact_phone,
                    $msg,
                    $logo_path
                ]);

                header("Location: apply.php?submitted=1");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apply to Join Klaso</title>
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
    background:
      radial-gradient(circle at 20% 15%, rgba(52,197,102,0.18), transparent 45%),
      radial-gradient(circle at 85% 85%, rgba(31,162,76,0.12), transparent 50%),
      linear-gradient(150deg, var(--g-900) 0%, var(--g-800) 55%, var(--g-700) 100%);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:30px 20px;
    -webkit-font-smoothing:antialiased;
  }

  .box{
    width:100%;
    max-width:520px;
    background:var(--white);
    border-radius:16px;
    padding:40px 36px 36px;
    box-shadow:0 24px 60px rgba(0,0,0,0.35);
  }

  .crest{
    width:56px; height:56px; border-radius:50%; margin:0 auto 18px;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    display:flex; align-items:center; justify-content:center;
    font-family:'Fraunces', serif; font-weight:700; font-size:22px; color:#fff;
    box-shadow:0 0 0 4px rgba(52,197,102,0.15), 0 6px 18px rgba(31,162,76,0.4);
  }

  h2{
    text-align:center; font-family:'Fraunces', serif; font-weight:700; font-size:22px;
    color:var(--g-800); margin-bottom:6px;
  }
  .sub{
    text-align:center; font-size:12.5px; color:var(--slate); margin-bottom:28px; line-height:1.5;
  }

  .success{
    background:var(--g-100); border:1.5px solid #BFE7CC; color:var(--g-700);
    padding:16px 18px; border-radius:10px; margin-bottom:22px;
    font-size:13px; font-weight:600; text-align:center; line-height:1.6;
  }

  .error{
    background:#FDEDED; border:1.5px solid #F3B8B8; color:#B42318;
    font-size:13px; font-weight:600; padding:12px 14px;
    border-radius:8px; margin-bottom:20px; text-align:center;
  }

  .field{ margin-bottom:18px; }
  .field label{
    display:block; font-size:11px; letter-spacing:1px; text-transform:uppercase;
    color:var(--slate); font-weight:700; margin-bottom:8px;
  }
  .field input, .field textarea{
    width:100%;
    padding:13px 14px;
    font-size:14px;
    font-family:'Inter', sans-serif;
    color:var(--ink);
    background:var(--g-100);
    border:1.5px solid var(--line);
    border-radius:8px;
    transition:.15s ease;
  }
  .field textarea{ height:80px; resize:vertical; }
  .field input:focus, .field textarea:focus{
    outline:none;
    border-color:var(--g-500);
    background:#fff;
    box-shadow:0 0 0 3px rgba(31,162,76,0.15);
  }

  .field input[type="file"]{
    padding:10px 14px;
    cursor:pointer;
  }
  .file-hint{ font-size:11px; color:var(--slate-light); margin-top:6px; }

  .row-2{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }

  button{
    width:100%;
    padding:14px;
    font-size:14px; font-weight:700; color:#fff;
    background: linear-gradient(135deg, var(--g-500), var(--g-700));
    border:none; border-radius:8px; cursor:pointer;
    transition:.15s ease;
    box-shadow:0 8px 20px rgba(31,162,76,0.3);
    margin-top:6px;
  }
  button:hover{ filter:brightness(1.08); transform:translateY(-1px); }

  .back-link{
    display:block; text-align:center; margin-top:20px;
    font-size:12.5px; color:var(--g-700); font-weight:600; text-decoration:none;
  }
  .back-link:hover{ text-decoration:underline; }

  @media (max-width:520px){
    .row-2{ grid-template-columns:1fr; }
  }
</style>
</head>
<body>

<div class="box">

  <div class="crest">K</div>
  <h2>Apply to Join Klaso</h2>
  <div class="sub">Tell us about your school and we'll review your application shortly.</div>

  <?php if(isset($_GET['submitted'])){ ?>
    <div class="success">
      &#10003; Application submitted successfully. We'll review it and reach out to the contact email provided.
    </div>
  <?php } ?>

  <?php if($message != "" && $isError){ ?>
    <div class="error"><?php echo htmlspecialchars($message); ?></div>
  <?php } ?>

  <form method="POST" enctype="multipart/form-data">

    <div class="field">
      <label for="school_name">School Name</label>
      <input type="text" id="school_name" name="school_name" placeholder="e.g. Mesec Senior High School" required>
    </div>

    <div class="field">
      <label for="contact_name">Contact Person (will be the first Admin)</label>
      <input type="text" id="contact_name" name="contact_name" placeholder="Full name" required>
    </div>

    <div class="row-2">
      <div class="field">
        <label for="contact_email">Email</label>
        <input type="email" id="contact_email" name="contact_email" placeholder="you@school.edu" required>
        </div>
      <div class="field">
        <label for="contact_phone">Phone</label>
        <input type="text" id="contact_phone" name="contact_phone" placeholder="Optional">
      </div>
    </div>

    <div class="field">
      <label for="logo">School Logo</label>
      <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
      <div class="file-hint">Optional &mdash; JPG, PNG or WEBP, max 2MB. Appears on receipts and your dashboard once approved.</div>
    </div>

    <div class="field">
      <label for="message">Anything else we should know?</label>
      <textarea id="message" name="message" placeholder="Optional — number of students, current system, etc."></textarea>
    </div>

    <button type="submit">Submit Application</button>

  </form>

  <a href="login.php" class="back-link">&larr; Back to Login</a>

</div>

</body>
</html>
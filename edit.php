<?php
$start_time = microtime(true);
// ═══════════════════════════════════════════════════════════════════
//  ENTERPRISE EDIT FORM  —  แก้ไขนัดหมายงาน
//  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
//  Enterprise-Level Edition — Navy/Gold Theme — Dark/Light Mode
// ═══════════════════════════════════════════════════════════════════
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

include 'config.php';

// ตรวจสอบว่ามีการส่ง ID มาใน URL หรือไม่
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = "SELECT * FROM events WHERE id=$id";
    $result = mysqli_query($connect, $query);
    $event = mysqli_fetch_assoc($result);

    // แยกโทนสีจาก Dress (ถ้ามี)
    $dress = $event['Dress'];
    $selectedDress = $dress;
    $colorTone = "";
    
    if (preg_match('/(.*)\s\((.*)\)/', $dress, $matches)) {
        $selectedDress = $matches[1];
        $colorTone = $matches[2];
    }
} else {
    if (isset($_GET['device'])) {
        $device = $_GET['device'];
        header("Location: " . ($device === 'mobile' ? 'm_manage' : 'manage'));
    } else {
        header("Location: manage");
    }
    exit();
}

// ─── Option lists ──────────────────────────────────────────────────
$dress_query = "SELECT * FROM dress_options";
$dress_result = mysqli_query($connect, $dress_query);
$dress_options = [];
$dress_names = [];
while ($row = mysqli_fetch_assoc($dress_result)) {
    $dress_options[] = $row;
    $dress_names[] = $row['name'];
}

$isDressInOptions = in_array($selectedDress, $dress_names);

$category_query = "SELECT * FROM event_categories";
$category_result = mysqli_query($connect, $category_query);
$categories = [];
while ($row = mysqli_fetch_assoc($category_result)) {
    $categories[] = $row;
}

$department_query = "SELECT * FROM department_options";
$department_result = mysqli_query($connect, $department_query);
$departmentOptions = [];
while ($row = mysqli_fetch_assoc($department_result)) {
    $departmentOptions[] = $row['name'];
}

$chairman_query = "SELECT * FROM chairman_options";
$chairman_result = mysqli_query($connect, $chairman_query);
$chairmanOptions = [];
while ($row = mysqli_fetch_assoc($chairman_result)) {
    $chairmanOptions[] = $row['name'];
}

$eventDepartment = $event['department'];
$eventChairman   = $event['chairman'];

$isDepartmentInOptions = in_array($eventDepartment, $departmentOptions);
$isChairmanInOptions   = in_array($eventChairman, $chairmanOptions);

if (!$isDepartmentInOptions) { $event['department'] = 'อื่นๆ'; $customDepartment = $eventDepartment; }
else { $customDepartment = ''; }

if (!$isChairmanInOptions) { $event['chairman'] = 'อื่นๆ'; $customChairman = $eventChairman; }
else { $customChairman = ''; }

$responsible_persons_query = "SELECT name, email FROM responsible_persons";
$responsible_persons_result = mysqli_query($connect, $responsible_persons_query);
$responsible_persons = [];
while ($row = mysqli_fetch_assoc($responsible_persons_result)) {
    $responsible_persons[$row['name']] = $row['email'];
}

$alert_message = "";
$alert_type = "";

// ─── Form submission ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ดึง updated_by ก่อนใช้ทุก branch
    $username = $_SESSION['username'];
    $user_query = "SELECT first_name, last_name FROM users WHERE username='$username'";
    $user_result = mysqli_query($connect, $user_query);
    $user = mysqli_fetch_assoc($user_result);
    $updated_by = $user['first_name'] . ' ' . $user['last_name'];

    if (isset($_POST['submit'])) {
        $title            = mysqli_real_escape_string($connect, $_POST['title']);
        $location         = mysqli_real_escape_string($connect, $_POST['location']);
        $description      = mysqli_real_escape_string($connect, $_POST['description']);
        $dress            = mysqli_real_escape_string($connect, $_POST['dress']);
        $date             = mysqli_real_escape_string($connect, $_POST['date']);
        $time_from        = mysqli_real_escape_string($connect, $_POST['time_from']);
        $time_to          = mysqli_real_escape_string($connect, $_POST['time_to']);
        $department       = $_POST['department'] === 'อื่นๆ' ? mysqli_real_escape_string($connect, $_POST['custom_department']) : mysqli_real_escape_string($connect, $_POST['department']);
        $chairman         = $_POST['chairman'] === 'อื่นๆ'   ? mysqli_real_escape_string($connect, $_POST['custom_chairman'])   : mysqli_real_escape_string($connect, $_POST['chairman']);
        $coordinator_name = mysqli_real_escape_string($connect, $_POST['coordinator_name']);
        $coordinator_phone= mysqli_real_escape_string($connect, $_POST['coordinator_phone']);
        $category_id      = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;

        $file_name = $event['file_name'];
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $file      = $_FILES['file'];
            $file_size = $file['size'];
            $file_tmp  = $file['tmp_name'];
            $file_ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed   = ['pdf','jpg','jpeg','png','webp'];
            if (!in_array($file_ext, $allowed)) {
                $alert_message = "อนุญาตเฉพาะไฟล์ PDF, JPG, PNG, และ WEBP เท่านั้น";
                $alert_type = "danger";
            } elseif ($file_size > 15*1024*1024) {
                $alert_message = "ขนาดไฟล์ต้องไม่เกิน 15 MB";
                $alert_type = "danger";
            } else {
                $new_file_name = uniqid().".".$file_ext;
                if (!empty($event['file_name']) && file_exists("uploads/".$event['file_name']))
                    unlink("uploads/".$event['file_name']);
                if (move_uploaded_file($file_tmp, "uploads/".$new_file_name))
                    $file_name = $new_file_name;
                else { $alert_message = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์"; $alert_type = "danger"; }
            }
        }

        $attendees = [];
        if (isset($_POST['responsible_persons'])) {
            foreach ($_POST['responsible_persons'] as $name)
                if (isset($responsible_persons[$name]))
                    $attendees[] = $responsible_persons[$name];
        }
        $attendees_str = implode(" ", $attendees);

        $update_query = "UPDATE events SET 
            title='$title', location='$location', description='$description', Dress='$dress', 
            date='$date', time_from='$time_from', time_to='$time_to', file_name='$file_name', 
            department='$department', chairman='$chairman', coordinator_name='$coordinator_name', 
            coordinator_phone='$coordinator_phone', attendees='$attendees_str', 
            category_id=".($category_id ? $category_id : 'NULL').", 
            updated_by='$updated_by', updated=CURRENT_TIMESTAMP WHERE id=$id";

        if (mysqli_query($connect, $update_query)) {
            $alert_message = "ดำเนินการแก้ไขเรียบร้อยแล้ว";
            $alert_type = "success";
            $query = "SELECT * FROM events WHERE id=$id";
            $result = mysqli_query($connect, $query);
            $event = mysqli_fetch_assoc($result);
            $dress = $event['Dress']; $selectedDress = $dress; $colorTone = "";
            if (preg_match('/(.*)\s\((.*)\)/', $dress, $matches)) { $selectedDress = $matches[1]; $colorTone = $matches[2]; }
        } else {
            $alert_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: ".mysqli_error($connect);
            $alert_type = "danger";
        }

    } elseif (isset($_POST['postpone'])) {
        $new_date      = mysqli_real_escape_string($connect, $_POST['new_date']);
        $new_time_from = mysqli_real_escape_string($connect, $_POST['new_time_from']);
        $new_time_to   = mysqli_real_escape_string($connect, $_POST['new_time_to']);
        $no_date       = isset($_POST['no_date']) ? 1 : 0;

        $postpone_file_name = null;
        if (isset($_FILES['postpone_file']) && $_FILES['postpone_file']['error'] == 0) {
            $file = $_FILES['postpone_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['pdf','jpg','jpeg','png','webp'];
            if (!in_array($file_ext, $allowed)) { $alert_message = "อนุญาตเฉพาะไฟล์ PDF, JPG, PNG, และ WEBP เท่านั้น"; $alert_type = "danger"; }
            elseif ($file['size'] > 15*1024*1024) { $alert_message = "ขนาดไฟล์ต้องไม่เกิน 15 MB"; $alert_type = "danger"; }
            else { $postpone_file_name = uniqid().".".$file_ext; if (!move_uploaded_file($file['tmp_name'], "uploads/".$postpone_file_name)) $postpone_file_name = null; }
        }

        $update_query = "UPDATE events SET status='เลื่อน', 
            postpone_date=".($no_date?"NULL":"'$new_date'").", 
            postpone_time_from=".($no_date?"NULL":"'$new_time_from'").", 
            postpone_time_to=".($no_date?"NULL":"'$new_time_to'").", 
            postpone_file_name=".($postpone_file_name?"'$postpone_file_name'":"NULL").", 
            updated_by='$updated_by', updated=CURRENT_TIMESTAMP WHERE id=$id";

        if (mysqli_query($connect, $update_query)) {
            if (!$no_date) {
                $copy_query = "INSERT INTO events (title,location,description,Dress,date,time_from,time_to,file_name,department,chairman,coordinator_name,coordinator_phone,attendees,category_id,status,created_by,updated_by)
                    SELECT title,location,description,Dress,'$new_date','$new_time_from','$new_time_to',file_name,department,chairman,coordinator_name,coordinator_phone,attendees,category_id,'ปกติ',created_by,'$updated_by' FROM events WHERE id=$id";
                mysqli_query($connect, $copy_query);
            }
            header("Location: editEvent?id=$id"); exit();
        } else { $alert_message = "เกิดข้อผิดพลาดในการเลื่อนงาน: ".mysqli_error($connect); $alert_type = "danger"; }

    } elseif (isset($_POST['cancel'])) {
        $cancel_reason = mysqli_real_escape_string($connect, $_POST['cancel_reason']);
        $cancel_file_name = null;
        if (isset($_FILES['cancel_file']) && $_FILES['cancel_file']['error'] == 0) {
            $file = $_FILES['cancel_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['pdf','jpg','jpeg','png','webp'];
            if (!in_array($file_ext, $allowed)) { $alert_message = "อนุญาตเฉพาะไฟล์ PDF, JPG, PNG, และ WEBP เท่านั้น"; $alert_type = "danger"; }
            elseif ($file['size'] > 15*1024*1024) { $alert_message = "ขนาดไฟล์ต้องไม่เกิน 15 MB"; $alert_type = "danger"; }
            else { $cancel_file_name = uniqid().".".$file_ext; if (!move_uploaded_file($file['tmp_name'], "uploads/".$cancel_file_name)) $cancel_file_name = null; }
        }

        $update_query = "UPDATE events SET status='ยกเลิก', cancel_reason='$cancel_reason', 
            cancel_file_name=".($cancel_file_name?"'$cancel_file_name'":"NULL").", 
            updated_by='$updated_by', updated=CURRENT_TIMESTAMP WHERE id=$id";

        if (mysqli_query($connect, $update_query)) { header("Location: editEvent?id=$id"); exit(); }
        else { $alert_message = "เกิดข้อผิดพลาดในการยกเลิกงาน: ".mysqli_error($connect); $alert_type = "danger"; }

    } elseif (isset($_POST['restore'])) {
        $update_query = "UPDATE events SET status='ปกติ', cancel_reason=NULL, cancel_file_name=NULL, 
            postpone_date=NULL, postpone_time_from=NULL, postpone_time_to=NULL, postpone_file_name=NULL, 
            updated_by='$updated_by', updated=CURRENT_TIMESTAMP WHERE id=$id";

        if (mysqli_query($connect, $update_query)) {
            $delete_query = "DELETE FROM events WHERE original_event_id=$id AND status='ปกติ'";
            mysqli_query($connect, $delete_query);
            header("Location: editEvent?id=$id"); exit();
        } else { $alert_message = "เกิดข้อผิดพลาดในการคืนสถานะ: ".mysqli_error($connect); $alert_type = "danger"; }
    }
}

function thaiDate($date) {
    if (empty($date)) return '—';
    $thai_months = [1=>"มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    $p = explode("-", $date);
    return intval($p[2])." ".$thai_months[intval($p[1])]." ".($p[0]+543);
}

$isDisabled = $event['status'] === 'ยกเลิก';
$disAttr    = $isDisabled ? 'disabled' : '';
$ptrNone    = $isDisabled ? 'style="pointer-events:none;opacity:0.6;"' : '';
$generation_time = microtime(true) - $start_time;
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>แก้ไขนัดหมายงาน — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</title>
<meta name="theme-color" content="#0f4c75">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap & Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
/* ══════════════════════════════════════════════════════
   DESIGN TOKENS — Light & Dark  (Navy/Gold theme)
   Font: Anuphan throughout
══════════════════════════════════════════════════════ */
:root {
  --brand-50:  #e8f0fd;
  --brand-100: #c1d5f8;
  --brand-200: #93b8f4;
  --brand-300: #5e98ef;
  --brand-400: #3a80e8;
  --brand-500: #1e6fd9;
  --brand-600: #1760c2;
  --brand-700: #0f4fa3;
  --brand-800: #0a3a7a;
  --brand-900: #0a1628;

  --accent-gold:   #e8a020;
  --accent-red:    #e05252;
  --accent-green:  #3ec97e;
  --accent-orange: #f97316;

  --bg-base:     #e8f0fd;
  --bg-surface:  rgba(255,255,255,0.88);
  --bg-elevated: rgba(255,255,255,0.97);
  --bg-glass:    rgba(255,255,255,0.65);
  --border:      rgba(30,111,217,0.10);
  --border-strong: rgba(30,111,217,0.22);
  --border-input: rgba(30,111,217,0.28);

  --text-primary:   #0a1628;
  --text-secondary: #1e6fd9;
  --text-muted:     #5e98ef;
  --text-inverse:   #ffffff;
  --text-label:     #0f4fa3;

  --shadow-sm:  0 1px 4px rgba(30,111,217,.07),0 1px 2px rgba(0,0,0,.04);
  --shadow-md:  0 4px 16px rgba(30,111,217,.14),0 2px 6px rgba(0,0,0,.06);
  --shadow-lg:  0 10px 36px rgba(30,111,217,.18),0 4px 12px rgba(0,0,0,.08);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.35);

  --r-sm:4px; --r-md:14px; --r-lg:20px; --r-xl:28px; --r-pill:999px;
  --ease-out: cubic-bezier(0.16,1,0.3,1);
  --ease-spring: cubic-bezier(0.34,1.56,0.64,1);

  --header-bg: linear-gradient(135deg,#0a1628 0%,#0d2340 50%,#0f2e54 100%);
  --page-bg: linear-gradient(145deg,#c8daf5 0%,#dce9fc 40%,#e8f0fd 100%);
  --input-bg: rgba(255,255,255,0.95);
  --card-bg: rgba(255,255,255,0.85);
  --section-header-bg: linear-gradient(90deg,rgba(30,111,217,0.08) 0%,transparent 100%);
}

[data-theme="dark"] {
  --bg-base:     #0a1628;
  --bg-surface:  rgba(13,35,64,0.90);
  --bg-elevated: rgba(15,46,84,0.88);
  --bg-glass:    rgba(10,22,40,0.80);
  --border:      rgba(255,255,255,0.09);
  --border-strong: rgba(255,255,255,0.16);
  --border-input: rgba(56,194,255,0.28);

  --text-primary:   #f0f4ff;
  --text-secondary: #38c2ff;
  --text-muted:     rgba(240,244,255,0.55);
  --text-inverse:   #ffffff;
  --text-label:     #93b8f4;

  --shadow-sm:  0 1px 4px rgba(0,0,0,.40);
  --shadow-md:  0 4px 16px rgba(0,0,0,.55);
  --shadow-lg:  0 10px 36px rgba(0,0,0,.65);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.45);

  --header-bg: linear-gradient(135deg,#050d1a 0%,#08192e 50%,#0a2040 100%);
  --page-bg: radial-gradient(ellipse 80% 60% at 20% 0%,rgba(30,111,217,0.22) 0%,transparent 65%),
             radial-gradient(ellipse 60% 50% at 80% 100%,rgba(232,160,32,0.14) 0%,transparent 60%),
             linear-gradient(160deg,#0a1628 0%,#0d1e3a 100%);

  --input-bg: rgba(10,22,40,0.80);
  --card-bg: rgba(13,35,64,0.80);
  --section-header-bg: linear-gradient(90deg,rgba(56,194,255,0.08) 0%,transparent 100%);
}

/* ══ RESET ══ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body {
  font-family: 'Anuphan', sans-serif;
  font-size: 15px; line-height: 1.6;
  background: var(--page-bg); background-attachment: fixed;
  color: var(--text-primary); min-height: 100vh;
  display: flex; flex-direction: column;
  transition: background 0.4s var(--ease-out), color 0.3s;
}

/* ══ HEADER ══ */
.app-header {
  background: var(--header-bg); color:#fff;
  position: sticky; top:0; z-index:200;
  box-shadow: 0 4px 24px rgba(0,0,0,.28);
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.header-inner {
  display: grid; grid-template-columns: 1fr auto 1fr;
  align-items: center; gap: 1rem;
  padding: 0.875rem 2rem;
}
.header-brand { display:flex; align-items:center; gap:1rem; }
.header-logo {
  width:52px; height:52px; object-fit:contain;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.3));
  transition: transform 0.3s var(--ease-spring); flex-shrink:0;
}
.header-logo:hover { transform:scale(1.08) rotate(-3deg); }
.header-text h1 { font-size:1.15rem; font-weight:600; margin:0; line-height:1.2; letter-spacing:.01em; font-family:'Anuphan',sans-serif; }
.header-text p  { font-size:0.78rem; opacity:0.78; margin:0; letter-spacing:.02em; }
.header-center  { text-align:center; }
.header-page-title {
  font-size:0.95rem; font-weight:600; opacity:0.95;
  display:flex; align-items:center; gap:0.5rem; justify-content:center;
}
.header-page-title .bi { font-size:1.1rem; color:var(--accent-gold); }
.header-actions { display:flex; align-items:center; justify-content:flex-end; gap:0.75rem; }
.theme-toggle {
  width:44px; height:44px; border-radius:var(--r-pill);
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
  color:#fff; display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:1.15rem; transition:all 0.3s var(--ease-out); backdrop-filter:blur(8px);
}
.theme-toggle:hover { background:rgba(255,255,255,.22); transform:scale(1.08); }
.btn-back-header {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 16px; border-radius:var(--r-sm);
  font-size:0.82rem; font-weight:500; font-family:'Anuphan',sans-serif;
  cursor:pointer; border:1px solid rgba(255,255,255,.25);
  background:rgba(255,255,255,.1); color:#fff;
  transition:all 0.25s var(--ease-out); text-decoration:none; backdrop-filter:blur(4px);
}
.btn-back-header:hover { background:rgba(255,255,255,.2); color:#fff; transform:translateY(-1px); }

/* ══ BREADCRUMB ══ */
.breadcrumb-bar {
  background:var(--bg-surface); backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border-strong);
  padding:0.6rem 2rem; transition:background 0.4s;
}
.breadcrumb-inner {
  max-width:900px; margin:0 auto;
  display:flex; align-items:center; gap:0.5rem;
  font-size:0.8rem; color:var(--text-muted);
}
.breadcrumb-inner a { color:var(--text-secondary); text-decoration:none; }
.breadcrumb-inner a:hover { text-decoration:underline; }
.breadcrumb-sep { opacity:0.45; }
.breadcrumb-current { color:var(--text-primary); font-weight:500; }

/* ══ MAIN CONTENT ══ */
.app-main {
  flex:1; padding:2rem 1.5rem; width:100%;
  display:flex; flex-direction:column; align-items:center;
}
.form-wrapper { width:100%; max-width:860px; }

/* Page title block */
.page-title-block {
  display:flex; align-items:center; gap:1rem;
  margin-bottom:1.75rem; padding-bottom:1.25rem;
  border-bottom:1px solid var(--border-strong);
}
.page-title-icon {
  width:52px; height:52px; border-radius:var(--r-md);
  background:linear-gradient(135deg,var(--brand-500),var(--brand-700));
  display:flex; align-items:center; justify-content:center;
  font-size:1.4rem; color:#fff; flex-shrink:0; box-shadow:var(--shadow-brand);
}
.page-title-text h2 { font-size:1.35rem; font-weight:600; margin:0; color:var(--text-primary); font-family:'Anuphan',sans-serif; }
.page-title-text p  { font-size:0.82rem; color:var(--text-muted); margin:0; }
.form-meta-chip {
  margin-left:auto; display:flex; align-items:center; gap:0.5rem;
  background:var(--bg-elevated); border:1px solid var(--border-strong);
  border-radius:var(--r-pill); padding:6px 14px;
  font-size:0.78rem; color:var(--text-secondary);
}

/* ══ ALERT ══ */
.enterprise-alert {
  display:flex; align-items:flex-start; gap:0.75rem;
  padding:1rem 1.25rem; border-radius:var(--r-md);
  margin-bottom:1.5rem; border:1px solid transparent;
  animation:alertIn 0.4s var(--ease-out) both; font-family:'Anuphan',sans-serif;
}
@keyframes alertIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.enterprise-alert.success { background:rgba(62,201,126,0.12); border-color:rgba(62,201,126,0.3); color:var(--text-primary); }
.enterprise-alert.danger  { background:rgba(224,82,82,0.12);  border-color:rgba(224,82,82,0.3);  color:var(--text-primary); }
.enterprise-alert.warning { background:rgba(232,160,32,0.12); border-color:rgba(232,160,32,0.3); color:var(--text-primary); }
.enterprise-alert.info    { background:rgba(30,111,217,0.10); border-color:rgba(30,111,217,0.25); color:var(--text-primary); }
.alert-icon { font-size:1.2rem; flex-shrink:0; margin-top:1px; }
.enterprise-alert.success .alert-icon { color:var(--accent-green); }
.enterprise-alert.danger  .alert-icon { color:var(--accent-red); }
.enterprise-alert.warning .alert-icon { color:var(--accent-gold); }
.enterprise-alert.info    .alert-icon { color:var(--brand-500); }
.alert-dismiss {
  margin-left:auto; background:none; border:none; color:var(--text-muted);
  cursor:pointer; font-size:1rem; padding:0; line-height:1; transition:color 0.2s;
}
.alert-dismiss:hover { color:var(--text-primary); }

/* ══ FORM CARD ══ */
.form-card {
  background:var(--bg-surface); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
  border:1px solid var(--border-strong); border-radius:var(--r-lg); overflow:hidden;
  box-shadow:var(--shadow-md); margin-bottom:1.5rem;
  transition:box-shadow 0.3s var(--ease-out);
  animation:cardReveal 0.5s var(--ease-out) both;
}
.form-card:hover { box-shadow:var(--shadow-lg); }
@keyframes cardReveal { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
.form-card:nth-child(1){animation-delay:0.05s} .form-card:nth-child(2){animation-delay:0.10s}
.form-card:nth-child(3){animation-delay:0.15s} .form-card:nth-child(4){animation-delay:0.20s}
.form-card:nth-child(5){animation-delay:0.25s} .form-card:nth-child(6){animation-delay:0.30s}
.form-card:nth-child(7){animation-delay:0.35s}

.section-header {
  padding:1rem 1.5rem; background:var(--section-header-bg);
  border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:0.75rem;
}
.section-icon {
  width:34px; height:34px; border-radius:var(--r-sm);
  background:var(--brand-500); color:#fff;
  display:flex; align-items:center; justify-content:center;
  font-size:0.9rem; flex-shrink:0;
}
.section-icon.gold   { background:linear-gradient(135deg,var(--accent-gold),#c07010); }
.section-icon.green  { background:linear-gradient(135deg,var(--accent-green),#1a9955); }
.section-icon.red    { background:linear-gradient(135deg,var(--accent-red),#b03030); }
.section-icon.orange { background:linear-gradient(135deg,var(--accent-orange),#c05010); }
.section-icon.purple { background:linear-gradient(135deg,#7c3aed,#5b21b6); }
.section-title { font-size:0.95rem; font-weight:600; color:var(--text-primary); font-family:'Anuphan',sans-serif; }
.section-subtitle { font-size:0.76rem; color:var(--text-muted); margin-top:1px; }
.section-body { padding:1.5rem; }

/* ══ FORM CONTROLS ══ */
.form-label {
  font-family:'Anuphan',sans-serif; font-size:0.83rem; font-weight:600;
  color:var(--text-label); margin-bottom:0.45rem;
  display:flex; align-items:center; gap:0.35rem;
}
.req-star { color:var(--accent-red); font-size:0.9rem; }

.form-control,.form-select {
  font-family:'Anuphan',sans-serif; font-size:0.9rem;
  background:var(--input-bg); color:var(--text-primary);
  border:1.5px solid var(--border-input); border-radius:var(--r-sm);
  padding:0.55rem 0.9rem; transition:all 0.25s var(--ease-out);
}
.form-control::placeholder { color:var(--text-muted); opacity:0.7; }
.form-control:focus,.form-select:focus {
  border-color:var(--brand-500); box-shadow:0 0 0 3px rgba(30,111,217,0.18);
  background:var(--bg-elevated); color:var(--text-primary); outline:none;
}
.form-select { cursor:pointer; }
[data-theme="dark"] .form-control,[data-theme="dark"] .form-select { color-scheme:dark; }
[data-theme="dark"] .form-select option { background:#0d2340; color:#f0f4ff; }

/* ══ CATEGORY PILLS ══ */
.pill-grid { display:flex; flex-wrap:wrap; gap:0.6rem; }
.pill-option {
  display:inline-flex; align-items:center; gap:0.4rem;
  padding:7px 16px; border-radius:var(--r-pill);
  border:1.5px solid var(--border-strong); background:var(--bg-elevated);
  color:var(--text-secondary); font-size:0.83rem; font-weight:500;
  cursor:pointer; transition:all 0.22s var(--ease-out);
  font-family:'Anuphan',sans-serif; user-select:none;
}
.pill-option:hover { border-color:var(--brand-500); color:var(--brand-500); transform:translateY(-2px); box-shadow:var(--shadow-sm); }
.pill-option.selected { background:var(--brand-500); border-color:var(--brand-500); color:#fff; box-shadow:var(--shadow-brand); }

/* ══ DRESS CARDS ══ */
.dress-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:0.75rem; }
.dress-card {
  background:var(--bg-elevated); border:1.5px solid var(--border-strong);
  border-radius:var(--r-md); padding:1rem 0.75rem;
  text-align:center; cursor:pointer; transition:all 0.22s var(--ease-out);
  font-size:0.85rem; font-weight:500; color:var(--text-primary);
  font-family:'Anuphan',sans-serif; user-select:none;
  display:flex; flex-direction:column; align-items:center; gap:0.4rem;
}
.dress-card .dress-icon { font-size:1.5rem; }
.dress-card:hover { border-color:var(--brand-500); transform:translateY(-3px); box-shadow:var(--shadow-md); }
.dress-card.selected {
  background:linear-gradient(135deg,var(--brand-500),var(--brand-700));
  border-color:var(--brand-500); color:#fff; box-shadow:var(--shadow-brand);
}

/* ══ PERSON GRID ══ */
.person-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:0.65rem; }
.person-card {
  display:flex; align-items:center; gap:0.6rem;
  background:var(--bg-elevated); border:1.5px solid var(--border-strong);
  border-radius:var(--r-sm); padding:0.7rem 1rem;
  cursor:pointer; transition:all 0.22s var(--ease-out); user-select:none;
}
.person-card:hover { border-color:var(--brand-400); transform:translateY(-1px); box-shadow:var(--shadow-sm); }
.person-card input[type="checkbox"] { display:none; }
.person-check {
  width:20px; height:20px; border-radius:5px; flex-shrink:0;
  border:2px solid var(--border-strong); background:var(--input-bg);
  display:flex; align-items:center; justify-content:center;
  transition:all 0.2s; font-size:0.75rem; color:transparent;
}
.person-card input:checked ~ .person-check { background:var(--brand-500); border-color:var(--brand-500); color:#fff; }
.person-card input:checked ~ .person-label { color:var(--brand-500); font-weight:600; }
.person-card input:disabled ~ .person-check { opacity:0.45; }
.person-card input:disabled ~ .person-label { opacity:0.45; }
.person-label { font-size:0.85rem; color:var(--text-primary); font-family:'Anuphan',sans-serif; line-height:1.3; }

/* ══ FILE UPLOAD ══ */
.file-drop {
  border:2px dashed var(--border-strong); border-radius:var(--r-md);
  padding:2rem 1.5rem; text-align:center; cursor:pointer;
  transition:all 0.25s var(--ease-out); background:transparent;
  position:relative; overflow:hidden;
}
.file-drop:hover,.file-drop.dragover { border-color:var(--brand-500); background:rgba(30,111,217,0.05); }
.file-drop input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.file-drop-icon { font-size:2.2rem; color:var(--brand-400); margin-bottom:0.6rem; display:block; }
.file-drop-text { font-size:0.9rem; color:var(--text-secondary); font-weight:500; display:block; }
.file-drop-hint { font-size:0.78rem; color:var(--text-muted); margin-top:0.3rem; display:block; }
.file-selected-badge {
  display:none; align-items:center; gap:0.6rem;
  background:rgba(30,111,217,0.1); border:1px solid var(--border-strong);
  border-radius:var(--r-sm); padding:0.6rem 1rem; margin-top:0.75rem;
  font-size:0.85rem; color:var(--text-secondary);
}
.file-selected-badge.show { display:flex; }
.current-file-box {
  display:flex; align-items:center; gap:0.75rem;
  background:rgba(30,111,217,0.08); border:1px solid var(--border-strong);
  border-radius:var(--r-sm); padding:0.75rem 1rem; margin-bottom:1rem;
}
.current-file-box .file-icon { font-size:1.5rem; color:var(--brand-400); }
.current-file-box a { color:var(--text-secondary); font-size:0.85rem; text-decoration:none; font-weight:500; }
.current-file-box a:hover { text-decoration:underline; }

/* ══ TIME GROUP ══ */
.time-row { display:flex; align-items:center; gap:0.75rem; }
.time-sep { font-size:0.85rem; color:var(--text-muted); flex-shrink:0; }

/* ══ CUSTOM INPUT REVEAL ══ */
.custom-input-reveal { display:none; margin-top:0.6rem; }
.custom-input-reveal.visible { display:block; animation:revealIn 0.25s var(--ease-out); }
@keyframes revealIn { from{opacity:0;transform:translateY(-5px)} to{opacity:1;transform:translateY(0)} }

/* ══ STATUS BADGE ══ */
.status-badge {
  display:inline-flex; align-items:center; gap:0.4rem;
  padding:4px 12px; border-radius:var(--r-pill); font-size:0.78rem; font-weight:600;
}
.status-badge.normal  { background:rgba(62,201,126,0.15); color:var(--accent-green); border:1px solid rgba(62,201,126,0.3); }
.status-badge.postpone{ background:rgba(232,160,32,0.15); color:var(--accent-gold);  border:1px solid rgba(232,160,32,0.3); }
.status-badge.cancel  { background:rgba(224,82,82,0.15);  color:var(--accent-red);   border:1px solid rgba(224,82,82,0.3); }

/* ══ STATUS BUTTONS ══ */
.status-actions { display:flex; gap:0.65rem; flex-wrap:wrap; }
.btn-status {
  display:inline-flex; align-items:center; gap:6px;
  padding:0.55rem 1.1rem; border-radius:var(--r-sm);
  font-size:0.85rem; font-weight:500; font-family:'Anuphan',sans-serif;
  cursor:pointer; border:1.5px solid; transition:all 0.22s var(--ease-out);
  background:transparent;
}
.btn-status-postpone { color:var(--accent-gold); border-color:rgba(232,160,32,0.4); }
.btn-status-postpone:hover { background:rgba(232,160,32,0.1); transform:translateY(-1px); }
.btn-status-cancel   { color:var(--accent-red);  border-color:rgba(224,82,82,0.4); }
.btn-status-cancel:hover   { background:rgba(224,82,82,0.1);  transform:translateY(-1px); }
.btn-status-restore  { color:var(--text-secondary); border-color:var(--border-strong); }
.btn-status-restore:hover  { background:var(--bg-elevated); transform:translateY(-1px); }

/* ══ FORM ACTIONS ══ */
.form-actions {
  display:flex; align-items:center; justify-content:space-between;
  gap:0.75rem; padding:1.5rem; flex-wrap:wrap;
  background:var(--bg-elevated); border:1px solid var(--border-strong);
  border-radius:var(--r-lg); box-shadow:var(--shadow-md); margin-bottom:2rem;
}
.actions-left,.actions-right { display:flex; gap:0.65rem; align-items:center; }

.btn-ent {
  display:inline-flex; align-items:center; gap:7px;
  padding:0.6rem 1.4rem; border-radius:var(--r-sm);
  font-size:0.88rem; font-weight:600;
  cursor:pointer; border:none; font-family:'Anuphan',sans-serif;
  transition:all 0.25s var(--ease-out); text-decoration:none;
}
.btn-ent-primary { background:var(--brand-500); color:#fff; box-shadow:var(--shadow-brand); min-width:130px; justify-content:center; }
.btn-ent-primary:hover { background:var(--brand-600); transform:translateY(-2px); box-shadow:0 12px 28px rgba(30,111,217,.4); }
.btn-ent-secondary { background:transparent; color:var(--text-secondary); border:1.5px solid var(--border-strong); }
.btn-ent-secondary:hover { background:var(--bg-elevated); transform:translateY(-1px); color:var(--text-primary); }
.btn-ent[disabled] { opacity:0.5; pointer-events:none; }

/* ══ MODAL (Bootstrap override + custom) ══ */
.modal-content { border-radius:var(--r-lg); border:1px solid var(--border-strong); background:var(--bg-elevated); }
.modal-header { background:var(--header-bg); color:#fff; border-bottom:none; border-radius:var(--r-lg) var(--r-lg) 0 0; padding:1.1rem 1.5rem; }
.modal-header .btn-close { filter:invert(1) brightness(2); }
.modal-title { font-weight:600; font-family:'Anuphan',sans-serif; font-size:1rem; }
.modal-body { padding:1.5rem; }
.modal-footer { border-top:1px solid var(--border); padding:1rem 1.5rem; background:var(--bg-surface); border-radius:0 0 var(--r-lg) var(--r-lg); }

/* ══ TOAST ══ */
.toast-container { position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; display:flex; flex-direction:column; gap:0.5rem; }
.toast-item {
  background:var(--bg-elevated); border:1px solid var(--border-strong);
  border-radius:var(--r-md); padding:0.85rem 1.2rem;
  box-shadow:var(--shadow-lg); min-width:280px; max-width:380px;
  display:flex; align-items:center; gap:0.75rem;
  animation:toastIn 0.35s var(--ease-out); font-size:0.88rem;
  font-family:'Anuphan',sans-serif; color:var(--text-primary);
}
@keyframes toastIn { from{opacity:0;transform:translateX(30px)} to{opacity:1;transform:translateX(0)} }
.toast-item.toast-out { animation:toastOut 0.3s forwards; }
@keyframes toastOut { to{opacity:0;transform:translateX(30px)} }
.toast-icon { font-size:1.2rem; flex-shrink:0; }
.toast-success .toast-icon { color:var(--accent-green); }
.toast-warning .toast-icon { color:var(--accent-gold); }
.toast-danger  .toast-icon { color:var(--accent-red); }

/* ══ FLATPICKR DARK ══ */
[data-theme="dark"] .flatpickr-calendar { background:#0d2340; border-color:rgba(255,255,255,0.12); color:#f0f4ff; }
[data-theme="dark"] .flatpickr-day { color:#f0f4ff; }
[data-theme="dark"] .flatpickr-day.selected { background:var(--brand-500); border-color:var(--brand-500); }
[data-theme="dark"] .flatpickr-day:hover { background:rgba(30,111,217,0.3); }
[data-theme="dark"] .flatpickr-months { color:#f0f4ff; }
[data-theme="dark"] .flatpickr-months .flatpickr-month { color:#f0f4ff; fill:#f0f4ff; }
[data-theme="dark"] .flatpickr-current-month .flatpickr-monthDropdown-months,
[data-theme="dark"] .flatpickr-current-month input.cur-year { color:#f0f4ff; background:transparent; }
[data-theme="dark"] .flatpickr-weekday { color:rgba(240,244,255,0.6); }
[data-theme="dark"] .flatpickr-day.flatpickr-disabled { color:rgba(240,244,255,0.25); }
[data-theme="dark"] .flatpickr-time input { background:#0d2340; color:#f0f4ff; border-color:rgba(255,255,255,0.12); }
[data-theme="dark"] .flatpickr-time .flatpickr-time-separator { color:#f0f4ff; }

/* ══ FOOTER ══ */
.app-footer {
  background: var(--header-bg);
  color: rgba(255,255,255,.8);
  text-align: center;
  padding: 1.25rem 2rem;
  font-size: 0.82rem;
  border-top: 1px solid rgba(255,255,255,.08);
}
.app-footer strong { color: #fff; }
.app-footer .perf {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 5px;
  opacity: 0.65;
  font-size: 0.72rem;
  font-family: 'Anuphan', monospace;
}

/* ══ CANCELED OVERLAY ══ */
.form-card.canceled-overlay { position:relative; }
.form-card.canceled-overlay::after { content:''; position:absolute; inset:0; background:rgba(224,82,82,0.04); border-radius:var(--r-lg); pointer-events:none; }

/* ══ RESPONSIVE ══ */
@media (max-width:768px) {
  .header-inner { grid-template-columns:1fr auto; padding:0.75rem 1rem; }
  .header-center { display:none; }
  .header-text h1 { font-size:0.92rem; }
  .header-text p { font-size:0.7rem; }
  .header-logo { width:40px; height:40px; }
  .breadcrumb-bar { padding:0.5rem 1rem; }
  .app-main { padding:1rem; }
  .section-body { padding:1rem; }
  .dress-grid { grid-template-columns:repeat(auto-fill,minmax(105px,1fr)); }
  .person-grid { grid-template-columns:1fr; }
  .form-actions { flex-direction:column-reverse; }
  .actions-left,.actions-right { width:100%; justify-content:stretch; }
  .actions-right { flex-direction:column; }
  .btn-ent { width:100%; justify-content:center; }
  .page-title-block { flex-wrap:wrap; }
  .form-meta-chip { display:none; }
  .time-row { flex-wrap:wrap; }
  .status-actions { flex-direction:column; }
  .btn-status { width:100%; justify-content:center; }
}
</style>
<script src="/theme.js"></script>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header class="app-header">
  <div class="header-inner">
    <!-- Brand -->
    <div class="header-brand">
      <img src="/images/logo.png" alt="โลโก้" class="header-logo">
      <div class="header-text">
        <h1>ระบบปฏิทินและนัดหมาย</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
      </div>
    </div>

    <!-- Center: page context -->
    <div class="header-center">
      <div class="header-page-title">
        <i class="bi bi-pencil-square"></i>
        แก้ไขนัดหมายงาน
      </div>
    </div>

    <!-- Actions -->
    <div class="header-actions">
      <button class="theme-toggle" id="themeToggle" title="สลับธีม">
        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
      </button>
      <span id="autoModeBadge">
        <i class="bi bi-brightness-auto"></i> Auto
      </span>
      <button class="btn-back-header" onclick="goBack()">
        <i class="bi bi-arrow-left"></i>
        <span>ย้อนกลับ</span>
      </button>
    </div>
  </div>
</header>

<!-- ═══ BREADCRUMB ═══ -->
<nav class="breadcrumb-bar">
  <div class="breadcrumb-inner">
    <a href="dashboard"><i class="bi bi-house me-1"></i>หน้าหลัก</a>
    <span class="breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
    <a href="manage">จัดการนัดหมาย</a>
    <span class="breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
    <span class="breadcrumb-current">แก้ไขนัดหมาย</span>
  </div>
</nav>

<!-- ═══ MAIN ═══ -->
<main class="app-main">
  <div class="form-wrapper">

    <!-- Page Title -->
    <div class="page-title-block">
      <div class="page-title-icon"><i class="bi bi-pencil-fill"></i></div>
      <div class="page-title-text">
        <h2>แก้ไขข้อมูลการนัดหมายงาน</h2>
        <p>ID #<?php echo $id; ?> — <?php echo htmlspecialchars($event['title']); ?></p>
      </div>
      <div class="form-meta-chip">
        <?php
          $statusMap = ['ปกติ'=>['normal','bi-check-circle-fill'],'เลื่อน'=>['postpone','bi-clock-fill'],'ยกเลิก'=>['cancel','bi-x-circle-fill']];
          [$sc,$si] = $statusMap[$event['status']] ?? ['normal','bi-circle'];
        ?>
        <span class="status-badge <?php echo $sc; ?>">
          <i class="bi <?php echo $si; ?>"></i> <?php echo $event['status']; ?>
        </span>
      </div>
    </div>

    <!-- Status Alert -->
    <?php if (!empty($alert_message)): ?>
    <div class="enterprise-alert <?php echo $alert_type; ?>" id="statusAlert">
      <i class="bi <?php echo $alert_type==='success'?'bi-check-circle-fill':($alert_type==='danger'?'bi-x-circle-fill':'bi-exclamation-triangle-fill'); ?> alert-icon"></i>
      <div><?php echo htmlspecialchars($alert_message); ?></div>
      <button class="alert-dismiss" onclick="this.parentElement.remove()"><i class="bi bi-x-lg"></i></button>
    </div>
    <?php endif; ?>

    <!-- Canceled notice -->
    <?php if ($isDisabled): ?>
    <div class="enterprise-alert danger">
      <i class="bi bi-x-octagon-fill alert-icon"></i>
      <div>
        <strong>งานนี้ถูกยกเลิกแล้ว</strong> — ไม่สามารถแก้ไขข้อมูลได้
        <?php if (!empty($event['cancel_reason'])): ?>
          <div style="margin-top:4px;font-size:0.85rem;">เหตุผล: <?php echo htmlspecialchars($event['cancel_reason']); ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Postponed notice -->
    <?php if ($event['status'] === 'เลื่อน'): ?>
    <div class="enterprise-alert warning">
      <i class="bi bi-clock-history alert-icon"></i>
      <div>
        <strong>งานนี้ถูกเลื่อนแล้ว</strong>
        <?php if (!empty($event['postpone_date'])): ?>
          — วันที่ใหม่: <strong><?php echo thaiDate($event['postpone_date']); ?></strong>
        <?php else: ?>
          — ยังไม่มีกำหนดการแน่นอน
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── FORM ── -->
    <form method="post" action="editEvent?id=<?php echo $id; ?>" enctype="multipart/form-data" id="mainForm" onsubmit="return validateForm(event)">

      <!-- ① สถานะงาน -->
      <div class="form-card">
        <div class="section-header">
          <div class="section-icon orange"><i class="bi bi-arrow-left-right"></i></div>
          <div>
            <div class="section-title">เปลี่ยนสถานะงาน</div>
            <div class="section-subtitle">เลื่อน ยกเลิก หรือคืนสถานะปกติ</div>
          </div>
        </div>
        <div class="section-body">
          <div class="status-actions">
            <button type="button" class="btn-status btn-status-postpone" data-bs-toggle="modal" data-bs-target="#postponeModal" <?php echo $isDisabled?'disabled':''; ?>>
              <i class="bi bi-calendar-x"></i> เลื่อนงาน
            </button>
            <button type="button" class="btn-status btn-status-cancel" data-bs-toggle="modal" data-bs-target="#cancelModal" <?php echo $isDisabled?'disabled':''; ?>>
              <i class="bi bi-x-circle"></i> ยกเลิกงาน
            </button>
            <button type="button" class="btn-status btn-status-restore" data-bs-toggle="modal" data-bs-target="#restoreModal" <?php echo ($event['status']==='ปกติ'?'disabled':''); ?>>
              <i class="bi bi-arrow-counterclockwise"></i> คืนสถานะปกติ
            </button>
          </div>
        </div>
      </div>

      <!-- ② ข้อมูลพื้นฐาน -->
      <div class="form-card <?php echo $isDisabled?'canceled-overlay':''; ?>" id="section-basic">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-info-circle-fill"></i></div>
          <div>
            <div class="section-title">ข้อมูลพื้นฐาน</div>
            <div class="section-subtitle">ชื่องาน ประเภท วันที่ เวลา และสถานที่</div>
          </div>
        </div>
        <div class="section-body">

          <!-- ชื่อภารกิจ -->
          <div class="mb-3">
            <label class="form-label" for="title">
              <i class="bi bi-card-heading"></i> ภารกิจ <span class="req-star">*</span>
            </label>
            <input type="text" class="form-control" id="title" name="title"
                   value="<?php echo htmlspecialchars($event['title']); ?>"
                   placeholder="ระบุชื่อภารกิจหรือชื่องาน" required <?php echo $disAttr; ?>>
          </div>

          <!-- ประเภท -->
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-tag-fill"></i> ประเภทงาน <span class="req-star">*</span></label>
            <div class="pill-grid" id="categoryPills" <?php echo $ptrNone; ?>>
              <?php foreach ($categories as $cat): ?>
                <div class="pill-option category-option <?php echo ($event['category_id']==$cat['id'])?'selected':''; ?>"
                     data-value="<?php echo $cat['id']; ?>">
                  <?php echo htmlspecialchars($cat['name']); ?>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="category_id" id="category_id" value="<?php echo $event['category_id']; ?>" required>
          </div>

          <!-- วันที่ + เวลา -->
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label" for="date">
                <i class="bi bi-calendar3"></i> วันที่ <span class="req-star">*</span>
                <span id="thai-date-display" style="font-weight:400;color:var(--text-muted);margin-left:4px;font-size:0.78rem;">
                  <?php echo thaiDate($event['date']); ?>
                </span>
              </label>
              <input type="date" name="date" id="date" class="form-control"
                     value="<?php echo $event['date']; ?>" required <?php echo $disAttr; ?>>
            </div>
            <div class="col-md-7">
              <label class="form-label"><i class="bi bi-clock"></i> ช่วงเวลา <span class="req-star">*</span></label>
              <div class="time-row">
                <input type="time" name="time_from" class="form-control" value="<?php echo $event['time_from']; ?>" required <?php echo $disAttr; ?>>
                <span class="time-sep">ถึง</span>
                <input type="time" name="time_to" class="form-control" value="<?php echo $event['time_to']; ?>" required <?php echo $disAttr; ?>>
              </div>
            </div>
          </div>

          <!-- สถานที่ -->
          <div class="mb-0">
            <label class="form-label" for="location">
              <i class="bi bi-geo-alt-fill"></i> สถานที่ <span class="req-star">*</span>
            </label>
            <input type="text" class="form-control" id="location" name="location"
                   value="<?php echo htmlspecialchars($event['location']); ?>"
                   placeholder="ระบุสถานที่จัดงาน" required <?php echo $disAttr; ?>>
          </div>
        </div>
      </div>

      <!-- ③ ผู้รับผิดชอบ -->
      <div class="form-card <?php echo $isDisabled?'canceled-overlay':''; ?>" id="section-persons">
        <div class="section-header">
          <div class="section-icon green"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="section-title">ผู้รับผิดชอบ</div>
            <div class="section-subtitle">เลือกบุคลากรที่เกี่ยวข้อง</div>
          </div>
        </div>
        <div class="section-body">
          <div class="person-grid">
            <?php foreach ($responsible_persons as $name => $email): ?>
              <label class="person-card" id="pc_<?php echo htmlspecialchars($name); ?>" <?php echo $isDisabled?'style="pointer-events:none;"':''; ?>>
                <input type="checkbox" name="responsible_persons[]"
                       value="<?php echo htmlspecialchars($name); ?>"
                       id="cb_<?php echo htmlspecialchars($name); ?>"
                       <?php echo (strpos($event['description'], $name) !== false ? 'checked' : ''); ?>
                       <?php echo $disAttr; ?>>
                <div class="person-check"><i class="bi bi-check2"></i></div>
                <span class="person-label"><?php echo htmlspecialchars($name); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="description" id="description" value="<?php echo htmlspecialchars($event['description']); ?>">
        </div>
      </div>

      <!-- ④ การแต่งกาย -->
      <div class="form-card <?php echo $isDisabled?'canceled-overlay':''; ?>" id="section-dress">
        <div class="section-header">
          <div class="section-icon red"><i class="bi bi-person-badge-fill"></i></div>
          <div>
            <div class="section-title">การแต่งกาย</div>
            <div class="section-subtitle">เลือกชุดที่กำหนด</div>
          </div>
        </div>
        <div class="section-body">
          <div class="dress-grid" <?php echo $ptrNone; ?>>
            <?php
            $dressIcons = ['ชุดปกติขาว'=>'👔','ชุดสุภาพ'=>'👗','ชุดกีฬา'=>'👟','ผ้าไทย'=>'🪡','ชุดดำ'=>'🖤','ชุดเครื่องแบบ'=>'🎖️','ชุดสากล'=>'🤵'];
            foreach ($dress_options as $dopt):
              $dname = $dopt['name'];
              $sel   = ($selectedDress === $dname) ? 'selected' : '';
              $icon  = $dressIcons[$dname] ?? '👔';
            ?>
              <div class="dress-card <?php echo $sel; ?>" data-value="<?php echo htmlspecialchars($dname); ?>">
                <span class="dress-icon"><?php echo $icon; ?></span>
                <?php echo htmlspecialchars($dname); ?>
              </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="dress" id="dress" value="<?php echo htmlspecialchars($event['Dress']); ?>" required>

          <div id="additionalDressInfo" class="custom-input-reveal <?php echo ($selectedDress==='ผ้าไทย')?'visible':''; ?>">
            <label class="form-label mt-2"><i class="bi bi-palette"></i> ระบุโทนสี เช่น โทนสีม่วง</label>
            <input type="text" class="form-control" id="additionalDressInput" name="additionalDressInfo"
                   placeholder="โทนสี..." value="<?php echo htmlspecialchars($colorTone); ?>"
                   <?php echo $disAttr; ?>>
          </div>
        </div>
      </div>

      <!-- ⑤ หน่วยงานและประธาน -->
      <div class="form-card <?php echo $isDisabled?'canceled-overlay':''; ?>" id="section-dept">
        <div class="section-header">
          <div class="section-icon purple"><i class="bi bi-building-fill"></i></div>
          <div>
            <div class="section-title">หน่วยงานและประธาน</div>
            <div class="section-subtitle">หน่วยงานที่จัดงานและประธานในพิธี</div>
          </div>
        </div>
        <div class="section-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="department">
                <i class="bi bi-buildings"></i> หน่วยงาน <span class="req-star">*</span>
              </label>
              <select class="form-select" id="department" name="department" required <?php echo $disAttr; ?>>
                <option value="">— เลือกหน่วยงาน —</option>
                <?php foreach ($departmentOptions as $opt): ?>
                  <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($event['department']===$opt)?'selected':''; ?>>
                    <?php echo htmlspecialchars($opt); ?>
                  </option>
                <?php endforeach; ?>
                <option value="อื่นๆ" <?php echo ($event['department']==='อื่นๆ')?'selected':''; ?>>อื่นๆ (ระบุเอง)</option>
              </select>
              <div id="customDeptWrap" class="custom-input-reveal <?php echo ($event['department']==='อื่นๆ')?'visible':''; ?>">
                <input type="text" class="form-control" id="custom_department" name="custom_department"
                       placeholder="กรอกชื่อหน่วยงาน" value="<?php echo htmlspecialchars($customDepartment); ?>"
                       <?php echo $disAttr; ?>>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="chairman">
                <i class="bi bi-person-workspace"></i> ประธาน
              </label>
              <select class="form-select" id="chairman" name="chairman" <?php echo $disAttr; ?>>
                <option value="">— เลือกประธาน —</option>
                <?php foreach ($chairmanOptions as $opt): ?>
                  <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($event['chairman']===$opt)?'selected':''; ?>>
                    <?php echo htmlspecialchars($opt); ?>
                  </option>
                <?php endforeach; ?>
                <option value="อื่นๆ" <?php echo ($event['chairman']==='อื่นๆ')?'selected':''; ?>>อื่นๆ (ระบุเอง)</option>
              </select>
              <div id="customChairmanWrap" class="custom-input-reveal <?php echo ($event['chairman']==='อื่นๆ')?'visible':''; ?>">
                <input type="text" class="form-control" id="custom_chairman" name="custom_chairman"
                       placeholder="กรอกชื่อประธาน" value="<?php echo htmlspecialchars($customChairman); ?>"
                       <?php echo $disAttr; ?>>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ⑥ ผู้ประสานงาน -->
      <div class="form-card <?php echo $isDisabled?'canceled-overlay':''; ?>" id="section-coord">
        <div class="section-header">
          <div class="section-icon"><i class="bi bi-person-lines-fill"></i></div>
          <div>
            <div class="section-title">ผู้ประสานงาน</div>
            <div class="section-subtitle">ชื่อและเบอร์ติดต่อผู้รับผิดชอบงาน</div>
          </div>
        </div>
        <div class="section-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="coordinator_name">
                <i class="bi bi-person-fill"></i> ชื่อผู้ประสานงาน
              </label>
              <input type="text" class="form-control" id="coordinator_name" name="coordinator_name"
                     value="<?php echo htmlspecialchars($event['coordinator_name']); ?>"
                     placeholder="ชื่อ-นามสกุล" <?php echo $disAttr; ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="coordinator_phone">
                <i class="bi bi-telephone-fill"></i> เบอร์โทรศัพท์
              </label>
              <input type="tel" class="form-control" id="coordinator_phone" name="coordinator_phone"
                     value="<?php echo htmlspecialchars($event['coordinator_phone']); ?>"
                     placeholder="0xx-xxx-xxxx" <?php echo $disAttr; ?>>
            </div>
          </div>
        </div>
      </div>

      <!-- ⑦ เอกสารแนบ -->
      <div class="form-card <?php echo $isDisabled?'canceled-overlay':''; ?>" id="section-file">
        <div class="section-header">
          <div class="section-icon gold"><i class="bi bi-paperclip"></i></div>
          <div>
            <div class="section-title">เอกสารแนบ</div>
            <div class="section-subtitle">PDF, JPG, PNG, WEBP — ขนาดไม่เกิน 15 MB</div>
          </div>
        </div>
        <div class="section-body">
          <?php if (!empty($event['file_name'])): ?>
            <?php $fext = strtolower(pathinfo($event['file_name'], PATHINFO_EXTENSION)); ?>
            <div class="current-file-box">
              <?php if (in_array($fext,['jpg','jpeg','png','webp'])): ?>
                <i class="bi bi-file-earmark-image file-icon" style="color:var(--accent-green)"></i>
              <?php elseif ($fext==='pdf'): ?>
                <i class="bi bi-file-earmark-pdf file-icon" style="color:var(--accent-red)"></i>
              <?php else: ?>
                <i class="bi bi-file-earmark file-icon"></i>
              <?php endif; ?>
              <div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:2px;">ไฟล์ปัจจุบัน</div>
                <a href="uploads/<?php echo $event['file_name']; ?>" target="_blank">
                  <i class="bi bi-download me-1"></i><?php echo htmlspecialchars($event['file_name']); ?>
                </a>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$isDisabled): ?>
          <div class="file-drop" id="fileDrop">
            <input type="file" name="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.webp">
            <i class="bi bi-cloud-arrow-up file-drop-icon"></i>
            <span class="file-drop-text">ลากวางไฟล์ที่นี่ หรือคลิกเพื่อเลือกไฟล์ใหม่</span>
            <span class="file-drop-hint">รองรับ PDF, JPG, JPEG, PNG, WEBP (สูงสุด 15 MB)</span>
          </div>
          <div class="file-selected-badge" id="fileSelectedBadge">
            <i class="bi bi-file-earmark-check-fill" style="color:var(--accent-green);font-size:1.1rem"></i>
            <span id="fileSelectedName">—</span>
            <span id="fileSelectedSize" class="ms-auto" style="font-size:0.78rem;color:var(--text-muted)"></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ─── FORM ACTIONS ─── -->
      <div class="form-actions">
        <div class="actions-left">
          <button type="button" class="btn-ent btn-ent-secondary" onclick="goBack()">
            <i class="bi bi-arrow-left"></i> ย้อนกลับ
          </button>
        </div>
        <div class="actions-right">
          <button type="submit" name="submit" class="btn-ent btn-ent-primary" id="submitBtn" <?php echo $isDisabled?'disabled':''; ?>>
            <i class="bi bi-floppy-fill"></i> บันทึกข้อมูล
          </button>
        </div>
      </div>

    </form><!-- /form -->
  </div><!-- /form-wrapper -->
</main>

<!-- ═══ POSTPONE MODAL ═══ -->
<div class="modal fade" id="postponeModal" tabindex="-1" aria-labelledby="postponeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="postponeModalLabel"><i class="bi bi-calendar-x me-2"></i>เลื่อนงาน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="editEvent?id=<?php echo $id; ?>" enctype="multipart/form-data">
        <input type="hidden" name="postpone" value="1">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-calendar3"></i> วันที่ใหม่</label>
            <input type="date" name="new_date" class="form-control" id="newDate">
          </div>
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-clock"></i> เวลาใหม่</label>
            <div class="time-row">
              <input type="time" name="new_time_from" class="form-control" value="<?php echo $event['time_from']; ?>">
              <span class="time-sep">ถึง</span>
              <input type="time" name="new_time_to" class="form-control" value="<?php echo $event['time_to']; ?>">
            </div>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="no_date" id="noDate">
              <label class="form-check-label" for="noDate" style="font-family:'Anuphan',sans-serif;">ยังไม่มีกำหนดการแน่นอน</label>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label"><i class="bi bi-paperclip"></i> เอกสารแนบ (ถ้ามี)</label>
            <div class="file-drop" style="padding:1rem;">
              <input type="file" name="postpone_file" accept=".pdf,.jpg,.jpeg,.png,.webp">
              <i class="bi bi-cloud-arrow-up file-drop-icon" style="font-size:1.5rem;"></i>
              <span class="file-drop-text" style="font-size:0.85rem;">คลิกเพื่อเลือกไฟล์</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ent btn-ent-secondary" data-bs-dismiss="modal">ปิด</button>
          <button type="submit" class="btn-ent btn-ent-primary"><i class="bi bi-check-lg"></i> บันทึกการเลื่อน</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══ CANCEL MODAL ═══ -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#7a1010,#b03030);">
        <h5 class="modal-title" id="cancelModalLabel"><i class="bi bi-x-circle me-2"></i>ยกเลิกงาน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="editEvent?id=<?php echo $id; ?>" enctype="multipart/form-data">
        <input type="hidden" name="cancel" value="1">
        <div class="modal-body">
          <div class="enterprise-alert danger" style="margin-bottom:1rem;">
            <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
            <div>การยกเลิกงานจะไม่สามารถกลับมาแก้ไขได้จนกว่าจะคืนสถานะ</div>
          </div>
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-chat-text"></i> เหตุผลการยกเลิก <span class="req-star">*</span></label>
            <textarea class="form-control" name="cancel_reason" rows="3" required placeholder="ระบุเหตุผลการยกเลิก..."></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label"><i class="bi bi-paperclip"></i> เอกสารแนบ (ถ้ามี)</label>
            <div class="file-drop" style="padding:1rem;">
              <input type="file" name="cancel_file" accept=".pdf,.jpg,.jpeg,.png,.webp">
              <i class="bi bi-cloud-arrow-up file-drop-icon" style="font-size:1.5rem;"></i>
              <span class="file-drop-text" style="font-size:0.85rem;">คลิกเพื่อเลือกไฟล์</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ent btn-ent-secondary" data-bs-dismiss="modal">ปิด</button>
          <button type="submit" class="btn-ent" style="background:var(--accent-red);color:#fff;box-shadow:0 4px 16px rgba(224,82,82,.4);">
            <i class="bi bi-x-circle-fill"></i> ยืนยันยกเลิก
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══ RESTORE MODAL ═══ -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#1a5c1a,#2a8a2a);">
        <h5 class="modal-title" id="restoreModalLabel"><i class="bi bi-arrow-counterclockwise me-2"></i>คืนสถานะปกติ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="editEvent?id=<?php echo $id; ?>">
        <input type="hidden" name="restore" value="1">
        <div class="modal-body">
          <p style="font-family:'Anuphan',sans-serif;">คุณแน่ใจหรือไม่ว่าต้องการคืนสถานะงานนี้เป็นปกติ?</p>
          <?php if ($event['status']==='เลื่อน'): ?>
          <div class="enterprise-alert warning" style="margin-top:0.75rem;">
            <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
            <div>งานใหม่ที่ถูกสร้างจากการเลื่อนจะถูกลบออก</div>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ent btn-ent-secondary" data-bs-dismiss="modal">ปิด</button>
          <button type="submit" class="btn-ent btn-ent-primary"><i class="bi bi-check-circle-fill"></i> ยืนยันคืนสถานะ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ FOOTER ══ -->
<footer class="app-footer">
  <strong>© <?= date('Y') ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</strong><br>
  <span class="perf">
    <i class="bi bi-lightning-charge-fill"></i>
    สร้างหน้าในเวลา <?= number_format($generation_time, 4) ?> วินาที
    &nbsp;|&nbsp; PHP <?= phpversion() ?>
  </span>
</footer>

<!-- ═══ TOAST CONTAINER ═══ -->
<div class="toast-container" id="toastContainer"></div>

<!-- ═══ SCRIPTS ═══ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
/* ═══════════════════════════════════════════════════════
   THEME MANAGEMENT — handled by /theme.js
═══════════════════════════════════════════════════════ */
// ThemeManager global available from theme.js

/* ═══════════════════════════════════════════════════════
   TOAST
═══════════════════════════════════════════════════════ */
function showToast(msg, type = 'success') {
  const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill' };
  const t = document.createElement('div');
  t.className = `toast-item toast-${type}`;
  t.innerHTML = `<i class="bi ${icons[type] || icons.success} toast-icon"></i><span>${msg}</span>`;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(() => { t.classList.add('toast-out'); setTimeout(() => t.remove(), 300); }, 3500);
}

/* ═══════════════════════════════════════════════════════
   CATEGORY PILLS
═══════════════════════════════════════════════════════ */
document.querySelectorAll('.category-option').forEach(o => {
  o.addEventListener('click', () => {
    document.querySelectorAll('.category-option').forEach(x => x.classList.remove('selected'));
    o.classList.add('selected');
    document.getElementById('category_id').value = o.dataset.value;
  });
});

/* ═══════════════════════════════════════════════════════
   DRESS CARDS
═══════════════════════════════════════════════════════ */
document.querySelectorAll('.dress-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.dress-card').forEach(x => x.classList.remove('selected'));
    card.classList.add('selected');
    const val = card.dataset.value;
    const addInfo = document.getElementById('additionalDressInput').value;
    document.getElementById('dress').value = (val === 'ผ้าไทย' && addInfo) ? `ผ้าไทย (${addInfo})` : val;
    const extra = document.getElementById('additionalDressInfo');
    if (val === 'ผ้าไทย') extra.classList.add('visible');
    else { extra.classList.remove('visible'); document.getElementById('additionalDressInput').value = ''; }
  });
});

document.getElementById('additionalDressInput')?.addEventListener('input', function() {
  const selected = document.querySelector('.dress-card.selected');
  if (selected && selected.dataset.value === 'ผ้าไทย') {
    document.getElementById('dress').value = this.value ? `ผ้าไทย (${this.value})` : 'ผ้าไทย';
  }
});

/* ═══════════════════════════════════════════════════════
   PERSON CHECKBOXES
═══════════════════════════════════════════════════════ */
function syncDescription() {
  const selected = [];
  document.querySelectorAll('.person-card input[type="checkbox"]:checked').forEach(cb => {
    selected.push(cb.value);
  });
  document.getElementById('description').value = selected.join(' ');
}

document.querySelectorAll('.person-card').forEach(card => {
  card.addEventListener('click', function() {
    const cb = this.querySelector('input[type="checkbox"]');
    if (cb.disabled) return;

    // Handle "ทุกท่าน" logic
    const allCbs = document.querySelectorAll('.person-card input[type="checkbox"]');
    if (cb.value === 'จนท.สนง.ปภ.รย(ทุกท่าน)') {
      cb.checked = !cb.checked;
      if (cb.checked) {
        allCbs.forEach(c => {
          if (c.value !== 'จนท.สนง.ปภ.รย(ทุกท่าน)' && c.value !== 'หน.ปภ') { c.checked = false; c.disabled = true; }
        });
        const head = document.querySelector('.person-card input[value="หน.ปภ"]');
        if (head) { head.checked = true; head.disabled = true; }
      } else {
        allCbs.forEach(c => { c.disabled = false; });
        const head = document.querySelector('.person-card input[value="หน.ปภ"]');
        if (head) { head.checked = false; }
      }
    } else {
      cb.checked = !cb.checked;
    }
    syncDescription();
  });
});

/* ═══════════════════════════════════════════════════════
   DEPARTMENT / CHAIRMAN SELECT
═══════════════════════════════════════════════════════ */
document.getElementById('department')?.addEventListener('change', function() {
  const wrap = document.getElementById('customDeptWrap');
  if (this.value === 'อื่นๆ') wrap.classList.add('visible');
  else { wrap.classList.remove('visible'); document.getElementById('custom_department').value = ''; }
});

document.getElementById('chairman')?.addEventListener('change', function() {
  const wrap = document.getElementById('customChairmanWrap');
  if (this.value === 'อื่นๆ') wrap.classList.add('visible');
  else { wrap.classList.remove('visible'); document.getElementById('custom_chairman').value = ''; }
});

/* ═══════════════════════════════════════════════════════
   DATE — Thai display
═══════════════════════════════════════════════════════ */
const dateInput = document.getElementById('date');
const thaiDisp  = document.getElementById('thai-date-display');
if (dateInput) {
  dateInput.addEventListener('change', function() {
    const d = new Date(this.value);
    if (!isNaN(d)) {
      thaiDisp.textContent = d.toLocaleDateString('th-TH', { year:'numeric', month:'long', day:'numeric' });
    }
  });
}

/* ═══════════════════════════════════════════════════════
   FILE UPLOAD — drag & drop
═══════════════════════════════════════════════════════ */
const fileDrop  = document.getElementById('fileDrop');
const fileInput = document.getElementById('fileInput');
const fileBadge = document.getElementById('fileSelectedBadge');

if (fileDrop && fileInput) {
  fileDrop.addEventListener('dragover', e => { e.preventDefault(); fileDrop.classList.add('dragover'); });
  fileDrop.addEventListener('dragleave', () => fileDrop.classList.remove('dragover'));
  fileDrop.addEventListener('drop', e => {
    e.preventDefault(); fileDrop.classList.remove('dragover');
    fileInput.files = e.dataTransfer.files;
    handleFile(fileInput.files[0]);
  });
  fileInput.addEventListener('change', e => handleFile(e.target.files[0]));
}

function handleFile(file) {
  if (!file || !fileBadge) return;
  const mb = (file.size / 1024 / 1024).toFixed(2);
  if (mb > 15) { showToast('ขนาดไฟล์เกิน 15 MB', 'danger'); fileInput.value = ''; return; }
  document.getElementById('fileSelectedName').textContent = file.name;
  document.getElementById('fileSelectedSize').textContent = mb + ' MB';
  fileBadge.classList.add('show');
}

/* ═══════════════════════════════════════════════════════
   POSTPONE MODAL — no date checkbox
═══════════════════════════════════════════════════════ */
document.getElementById('noDate')?.addEventListener('change', function() {
  const nd = document.getElementById('newDate');
  const timeInputs = document.querySelectorAll('input[name="new_time_from"], input[name="new_time_to"]');
  if (this.checked) {
    nd.value = ''; nd.disabled = true;
    timeInputs.forEach(t => { t.disabled = true; t.value = ''; });
  } else {
    nd.disabled = false;
    timeInputs.forEach(t => { t.disabled = false; });
  }
});

/* ═══════════════════════════════════════════════════════
   FORM VALIDATION
═══════════════════════════════════════════════════════ */
function validateForm(e) {
  const catId  = document.getElementById('category_id').value;
  const dressV = document.getElementById('dress').value;
  const dept   = document.getElementById('department')?.value;
  const tf     = document.querySelector('input[name="time_from"]').value;
  const tt     = document.querySelector('input[name="time_to"]').value;

  if (!catId) { showToast('กรุณาเลือกประเภทงาน!', 'warning'); return false; }
  if (!dressV) { showToast('กรุณาเลือกการแต่งกาย!', 'warning'); return false; }
  if (!dept) { showToast('กรุณาเลือกหน่วยงาน!', 'warning'); return false; }
  if (dept === 'อื่นๆ' && !document.getElementById('custom_department').value.trim()) {
    showToast('กรุณากรอกชื่อหน่วยงาน!', 'warning'); return false;
  }
  if (tt <= tf) { showToast('เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่มต้น!', 'warning'); return false; }
  return true;
}

/* ═══════════════════════════════════════════════════════
   GO BACK
═══════════════════════════════════════════════════════ */
function goBack() {
  if (document.referrer && document.referrer.indexOf(window.location.hostname) !== -1)
    window.history.back();
  else window.location.href = 'manage';
}

/* ═══════════════════════════════════════════════════════
   AUTO DISMISS ALERT
═══════════════════════════════════════════════════════ */
const statusAlert = document.getElementById('statusAlert');
if (statusAlert) setTimeout(() => statusAlert.remove(), 5000);
</script>
</body>
</html>
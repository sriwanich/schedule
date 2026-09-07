<?php
$start_time = microtime(true);
// ═══════════════════════════════════════════════════════════════════
//  ENTERPRISE FORM  —  เพิ่มนัดหมายงาน
//  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
//  Enterprise-Level Edition — Navy/Gold Theme — Dark/Light Mode
// ═══════════════════════════════════════════════════════════════════
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'config.php';

function sanitizeInput($data, $connect) {
    return mysqli_real_escape_string($connect, trim($data));
}

// ─── User ──────────────────────────────────────────────────────────
$username = $_SESSION['username'];
$query    = "SELECT first_name, last_name, role FROM users WHERE username = '$username'";
$result   = mysqli_query($connect, $query);
$user_row = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : [];
$created_by = !empty($user_row) ? $user_row['first_name'].' '.$user_row['last_name'] : 'Unknown';
$first_name = $user_row['first_name'] ?? '';
$last_name  = $user_row['last_name']  ?? '';
$role       = $user_row['role']       ?? 'user';

// ─── Thai date for header ───────────────────────────────────────────
$thai_months_full = [
    'Jan'=>'มกราคม','Feb'=>'กุมภาพันธ์','Mar'=>'มีนาคม','Apr'=>'เมษายน',
    'May'=>'พฤษภาคม','Jun'=>'มิถุนายน','Jul'=>'กรกฎาคม','Aug'=>'สิงหาคม',
    'Sep'=>'กันยายน','Oct'=>'ตุลาคม','Nov'=>'พฤศจิกายน','Dec'=>'ธันวาคม',
];
$thai_days_full = ['Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร',
    'Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์'];
$current_date_thai = 'วัน'.$thai_days_full[date('l')].'ที่ '.date('j').' '.$thai_months_full[date('M')].' พ.ศ. '.(date('Y')+543);

// ─── Option lists ──────────────────────────────────────────────────
$departmentOptions = $chairmanOptions = $dressOptions = $responsiblePersons = $eventCategories = [];

foreach ([
    ['department_options', 'name', &$departmentOptions],
    ['chairman_options',   'name', &$chairmanOptions],
    ['dress_options',      'name', &$dressOptions],
] as [$table, $col, &$arr]) {
    $r = mysqli_query($connect, "SELECT $col FROM $table");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $arr[] = $row[$col];
}

$r = mysqli_query($connect, "SELECT name, email FROM responsible_persons");
if ($r) while ($row = mysqli_fetch_assoc($r)) $responsiblePersons[] = $row;

$r = mysqli_query($connect, "SELECT id, name FROM event_categories");
if ($r) while ($row = mysqli_fetch_assoc($r)) $eventCategories[] = $row;

// ─── Session flash ─────────────────────────────────────────────────
$postData = [];
if (!empty($_SESSION['postData'])) { $postData = $_SESSION['postData']; unset($_SESSION['postData']); }

$status = $statusMsg = '';
if (!empty($_SESSION['status_response'])) {
    $sr = $_SESSION['status_response'];
    $status = $sr['status']; $statusMsg = $sr['status_msg'];
    unset($_SESSION['status_response']);
}

// ─── Form submission ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $title            = sanitizeInput($_POST['title'], $connect);
    $date             = sanitizeInput($_POST['date'], $connect);
    $time_from        = sanitizeInput($_POST['time_from'], $connect);
    $time_to          = sanitizeInput($_POST['time_to'], $connect);
    $location         = sanitizeInput($_POST['location'], $connect);
    $description      = sanitizeInput($_POST['description'], $connect);
    $dress            = sanitizeInput($_POST['dress'], $connect);
    $coordinator_name = sanitizeInput($_POST['coordinator_name'], $connect);
    $coordinator_phone= sanitizeInput($_POST['coordinator_phone'], $connect);
    $file_name        = sanitizeInput($_POST['file_name'], $connect);
    $category_id      = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

    $department = '';
    if (isset($_POST['department'])) {
        $department = sanitizeInput($_POST['department'], $connect);
        if ($department === 'อื่นๆ' && !empty($_POST['custom_department'])) {
            $department = sanitizeInput($_POST['custom_department'], $connect);
            $chk = "SELECT name FROM department_options WHERE name = '$department'";
            if (mysqli_num_rows(mysqli_query($connect, $chk)) == 0)
                mysqli_query($connect, "INSERT INTO department_options (name) VALUES ('$department')");
        }
    }

    $chairman = '';
    if (isset($_POST['chairman'])) {
        $chairman = sanitizeInput($_POST['chairman'], $connect);
        if ($chairman === 'อื่นๆ' && !empty($_POST['custom_chairman'])) {
            $chairman = sanitizeInput($_POST['custom_chairman'], $connect);
            $chk = "SELECT name FROM chairman_options WHERE name = '$chairman'";
            if (mysqli_num_rows(mysqli_query($connect, $chk)) == 0)
                mysqli_query($connect, "INSERT INTO chairman_options (name) VALUES ('$chairman')");
        }
    }

    if ($dress === "ผ้าไทย" && !empty($_POST['additionalDressInfo']))
        $dress .= " (" . sanitizeInput($_POST['additionalDressInfo'], $connect) . ")";

    $attendees = [];
    if (!empty($_POST['responsible_persons'])) {
        foreach ($_POST['responsible_persons'] as $sel)
            foreach ($responsiblePersons as $p)
                if ($p['name'] === $sel) $attendees[] = $p['email'];
    }
    $attendees = implode(' ', $attendees);

    [$day, $month, $year] = explode('/', $date);
    $date      = DateTime::createFromFormat('d/m/Y', "$day/$month/$year")->format('Y-m-d');
    $time_from = DateTime::createFromFormat('H:i', $time_from)->format('H:i:s');
    $time_to   = DateTime::createFromFormat('H:i', $time_to)->format('H:i:s');

    if (!empty($_FILES['file']['name'])) {
        $uploadDir  = 'uploads/';
        $fileExt    = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $file_name;

        if ($_FILES['file']['size'] > 15 * 1024 * 1024) {
            $_SESSION['status_response'] = ['status'=>'danger','status_msg'=>'ไฟล์ต้องมีขนาดไม่เกิน 15 MB'];
            header('Location: addEvent'); exit();
        }
        if (!in_array(strtolower($fileExt), ['pdf','jpg','jpeg','png'])) {
            $_SESSION['status_response'] = ['status'=>'danger','status_msg'=>'อนุญาตเฉพาะไฟล์ PDF, JPG, JPEG, PNG'];
            header('Location: addEvent'); exit();
        }
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
            $_SESSION['status_response'] = ['status'=>'danger','status_msg'=>'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'];
            header('Location: addEvent'); exit();
        }
    }

    $q = "INSERT INTO events (title,date,time_from,time_to,location,description,Dress,department,file_name,chairman,coordinator_name,coordinator_phone,attendees,created_by,category_id)
          VALUES ('$title','$date','$time_from','$time_to','$location','$description','$dress','$department','$file_name','$chairman','$coordinator_name','$coordinator_phone','$attendees','$created_by','$category_id')";
    $_SESSION['status_response'] = mysqli_query($connect, $q)
        ? ['status'=>'success','status_msg'=>'บันทึกข้อมูลเรียบร้อยแล้ว!']
        : ['status'=>'danger', 'status_msg'=>'เกิดข้อผิดพลาด: '.mysqli_error($connect)];
    header('Location: addEvent'); exit();
}
$generation_time = microtime(true) - $start_time;
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เพิ่มนัดหมายงาน — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</title>
<meta name="theme-color" content="#0f4c75">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap & Icons -->
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

  --shadow-sm:  0 1px 4px rgba(30,111,217,.07), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md:  0 4px 16px rgba(30,111,217,.14), 0 2px 6px rgba(0,0,0,.06);
  --shadow-lg:  0 10px 36px rgba(30,111,217,.18), 0 4px 12px rgba(0,0,0,.08);
  --shadow-xl:  0 25px 60px rgba(30,111,217,.24);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.35);

  --r-sm: 8px;  --r-md: 14px;  --r-lg: 20px;  --r-xl: 28px;  --r-pill: 999px;

  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);

  --header-bg: linear-gradient(135deg, #0a1628 0%, #0d2340 50%, #0f2e54 100%);
  --page-bg: linear-gradient(145deg, #c8daf5 0%, #dce9fc 40%, #e8f0fd 100%);

  --input-bg: rgba(255,255,255,0.95);
  --card-bg: rgba(255,255,255,0.85);
  --section-header-bg: linear-gradient(90deg, rgba(30,111,217,0.08) 0%, transparent 100%);
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
  --shadow-xl:  0 25px 60px rgba(0,0,0,.80);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.45);

  --header-bg: linear-gradient(135deg, #050d1a 0%, #08192e 50%, #0a2040 100%);
  --page-bg: radial-gradient(ellipse 80% 60% at 20% 0%, rgba(30,111,217,0.22) 0%, transparent 65%),
             radial-gradient(ellipse 60% 50% at 80% 100%, rgba(232,160,32,0.14) 0%, transparent 60%),
             linear-gradient(160deg, #0a1628 0%, #0d1e3a 100%);

  --input-bg: rgba(10,22,40,0.80);
  --card-bg: rgba(13,35,64,0.80);
  --section-header-bg: linear-gradient(90deg, rgba(56,194,255,0.08) 0%, transparent 100%);
}

/* ══════════════════════════════════════════════════════ RESET */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Anuphan', sans-serif;
  font-size: 15px;
  line-height: 1.6;
  background: var(--page-bg);
  background-attachment: fixed;
  color: var(--text-primary);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: background 0.4s var(--ease-out), color 0.3s;
}

/* ══════════════════════════════════════════════════════ HEADER */
.app-header {
  background: var(--header-bg);
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 200;
  box-shadow: 0 4px 24px rgba(0,0,0,.28);
  border-bottom: 1px solid rgba(255,255,255,.08);
}

/* ── PC layout: 3-column grid ── */
.header-inner {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 1rem;
  padding: 0.875rem 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* ── Brand (left) ── */
.header-brand { display: flex; align-items: center; gap: 0.85rem; min-width: 0; }
.header-logo {
  width: 52px; height: 52px; object-fit: contain; flex-shrink: 0;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.35));
  transition: transform 0.35s var(--ease-spring);
}
.header-logo:hover { transform: scale(1.1) rotate(-4deg); }
.header-text { min-width: 0; }
.header-text h1 {
  font-size: 1.05rem; font-weight: 700; margin: 0; line-height: 1.25;
  letter-spacing: .01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.header-text p {
  font-size: 0.73rem; opacity: 0.72; margin: 0;
  letter-spacing: .02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── Center: clock ── */
.header-center { text-align: center; }
.header-clock {
  font-size: 1.9rem; font-weight: 300; letter-spacing: .07em;
  line-height: 1; font-variant-numeric: tabular-nums;
}
.header-date { font-size: 0.75rem; opacity: .8; white-space: nowrap; margin-top: 2px; }

/* ── Actions (right) ── */
.header-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.6rem; }

/* User chip */
.user-chip {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 6px 13px; border-radius: var(--r-pill);
  background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
  color: rgba(255,255,255,.92); font-size: .8rem; font-weight: 500;
  cursor: pointer; transition: all .25s var(--ease-out);
  backdrop-filter: blur(6px); text-decoration: none; white-space: nowrap;
}
.user-chip:hover { background: rgba(255,255,255,.22); color: #fff; transform: translateY(-1px); }
.user-chip .user-name { max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Role badge */
.role-badge {
  font-size: .62rem; padding: 2px 7px; border-radius: var(--r-pill);
  font-weight: 700; letter-spacing: .04em; flex-shrink: 0;
}
.role-badge.admin { background: var(--accent-red); color: #fff; }
.role-badge.user  { background: rgba(255,255,255,.22); color: #fff; }

/* Theme toggle */
.theme-toggle {
  width: 40px; height: 40px; border-radius: var(--r-pill); flex-shrink: 0;
  background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
  color: #fff; display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 1.05rem; transition: all 0.3s var(--ease-out);
  backdrop-filter: blur(8px);
}
.theme-toggle:hover { background: rgba(255,255,255,.24); transform: scale(1.1) rotate(15deg); }

/* Auto-mode badge */
#autoModeBadge {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 0.72rem; opacity: 0.7; white-space: nowrap;
  padding: 4px 8px; border-radius: var(--r-pill);
  border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.07);
}

/* Back button */
.btn-back-header {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 15px; border-radius: var(--r-sm);
  font-size: 0.82rem; font-weight: 600; font-family: 'Anuphan', sans-serif;
  cursor: pointer; border: 1px solid rgba(255,255,255,.28);
  background: rgba(255,255,255,.12); color: #fff;
  transition: all 0.25s var(--ease-out); text-decoration: none;
  backdrop-filter: blur(4px); white-space: nowrap; flex-shrink: 0;
}
.btn-back-header:hover { background: rgba(255,255,255,.22); color: #fff; transform: translateY(-1px); }

/* ── Page-context strip (shows on mobile instead of clock) ── */
.header-page-strip {
  display: none;
  background: rgba(255,255,255,.07);
  border-top: 1px solid rgba(255,255,255,.08);
  padding: 5px 1rem;
  font-size: 0.75rem; opacity: 0.85;
  align-items: center; gap: 0.4rem;
}
.header-page-strip .bi { color: var(--accent-gold); font-size: 0.8rem; }

/* ══════════════════════════════════════════════════════ BREADCRUMB */
.breadcrumb-bar {
  background: var(--bg-surface);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-strong);
  padding: 0.6rem 2rem;
  transition: background 0.4s;
}
.breadcrumb-inner {
  max-width: 900px; margin: 0 auto;
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 0.8rem; color: var(--text-muted);
}
.breadcrumb-inner a { color: var(--text-secondary); text-decoration: none; }
.breadcrumb-inner a:hover { text-decoration: underline; }
.breadcrumb-sep { opacity: 0.45; }
.breadcrumb-current { color: var(--text-primary); font-weight: 500; }

/* ══════════════════════════════════════════════════════ MAIN CONTENT */
.app-main {
  flex: 1; padding: 2rem 1.5rem; width: 100%;
  display: flex; flex-direction: column; align-items: center;
}
.form-wrapper { width: 100%; max-width: 860px; }

/* Page title block */
.page-title-block {
  display: flex; align-items: center; gap: 1rem;
  margin-bottom: 1.75rem; padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--border-strong);
}
.page-title-icon {
  width: 52px; height: 52px; border-radius: var(--r-md);
  background: linear-gradient(135deg, var(--brand-500), var(--brand-700));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; color: #fff; flex-shrink: 0;
  box-shadow: var(--shadow-brand);
}
.page-title-text h2 { font-size: 1.35rem; font-weight: 600; margin: 0; color: var(--text-primary); }
.page-title-text p  { font-size: 0.82rem; color: var(--text-muted); margin: 0; }
.form-meta-chip {
  margin-left: auto; display: flex; align-items: center; gap: 0.5rem;
  background: var(--bg-elevated); border: 1px solid var(--border-strong);
  border-radius: var(--r-pill); padding: 6px 14px;
  font-size: 0.78rem; color: var(--text-secondary);
}

/* ══════════════════════════════════════════════════════ ALERT */
.enterprise-alert {
  display: flex; align-items: flex-start; gap: 0.75rem;
  padding: 1rem 1.25rem; border-radius: var(--r-md);
  margin-bottom: 1.5rem; border: 1px solid transparent;
  animation: alertIn 0.4s var(--ease-out) both;
  font-family: 'Anuphan', sans-serif;
}
@keyframes alertIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
.enterprise-alert.success { background: rgba(62,201,126,0.12); border-color: rgba(62,201,126,0.3); color: var(--text-primary); }
.enterprise-alert.danger  { background: rgba(224,82,82,0.12);  border-color: rgba(224,82,82,0.3);  color: var(--text-primary); }
.enterprise-alert.warning { background: rgba(232,160,32,0.12); border-color: rgba(232,160,32,0.3); color: var(--text-primary); }
.alert-icon { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }
.enterprise-alert.success .alert-icon { color: var(--accent-green); }
.enterprise-alert.danger  .alert-icon { color: var(--accent-red); }
.enterprise-alert.warning .alert-icon { color: var(--accent-gold); }
.alert-dismiss {
  margin-left: auto; background: none; border: none; color: var(--text-muted);
  cursor: pointer; font-size: 1rem; padding: 0; line-height: 1;
  transition: color 0.2s;
}
.alert-dismiss:hover { color: var(--text-primary); }

/* ══════════════════════════════════════════════════════ FORM CARD */
.form-card {
  background: var(--bg-surface);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid var(--border-strong);
  border-radius: var(--r-lg);
  overflow: hidden;
  box-shadow: var(--shadow-md);
  margin-bottom: 1.5rem;
  transition: box-shadow 0.3s var(--ease-out);
  animation: cardReveal 0.5s var(--ease-out) both;
}
.form-card:hover { box-shadow: var(--shadow-lg); }
@keyframes cardReveal { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
.form-card:nth-child(1) { animation-delay: 0.05s; }
.form-card:nth-child(2) { animation-delay: 0.10s; }
.form-card:nth-child(3) { animation-delay: 0.15s; }
.form-card:nth-child(4) { animation-delay: 0.20s; }
.form-card:nth-child(5) { animation-delay: 0.25s; }
.form-card:nth-child(6) { animation-delay: 0.30s; }
.form-card:nth-child(7) { animation-delay: 0.35s; }

.section-header {
  padding: 1rem 1.5rem;
  background: var(--section-header-bg);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 0.75rem;
}
.section-icon {
  width: 34px; height: 34px; border-radius: var(--r-sm);
  background: var(--brand-500); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.9rem; flex-shrink: 0;
}
.section-icon.gold   { background: linear-gradient(135deg, var(--accent-gold), #c07010); }
.section-icon.green  { background: linear-gradient(135deg, var(--accent-green), #1a9955); }
.section-icon.red    { background: linear-gradient(135deg, var(--accent-red), #b03030); }
.section-icon.purple { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
.section-title {
  font-size: 0.95rem; font-weight: 600; color: var(--text-primary);
  font-family: 'Anuphan', sans-serif;
}
.section-subtitle { font-size: 0.76rem; color: var(--text-muted); margin-top: 1px; }

.section-body { padding: 1.5rem; }

/* ══════════════════════════════════════════════════════ FORM CONTROLS */
.form-label {
  font-family: 'Anuphan', sans-serif;
  font-size: 0.83rem; font-weight: 600;
  color: var(--text-label); margin-bottom: 0.45rem;
  display: flex; align-items: center; gap: 0.35rem;
}
.req-star { color: var(--accent-red); font-size: 0.9rem; }

.form-control, .form-select {
  font-family: 'Anuphan', sans-serif; font-size: 0.9rem;
  background: var(--input-bg); color: var(--text-primary);
  border: 1.5px solid var(--border-input); border-radius: var(--r-sm);
  padding: 0.55rem 0.9rem;
  transition: all 0.25s var(--ease-out);
}
.form-control::placeholder { color: var(--text-muted); opacity: 0.7; }
.form-control:focus, .form-select:focus {
  border-color: var(--brand-500);
  box-shadow: 0 0 0 3px rgba(30,111,217,0.18);
  background: var(--bg-elevated); color: var(--text-primary); outline: none;
}
.form-control.is-invalid { border-color: var(--accent-red); }
.form-select { cursor: pointer; }

[data-theme="dark"] .form-control,
[data-theme="dark"] .form-select {
  color-scheme: dark;
}
[data-theme="dark"] .form-select option {
  background: #0d2340; color: #f0f4ff;
}

/* ══════════════════════════════════════════════════════ CATEGORY PILLS */
.pill-grid {
  display: flex; flex-wrap: wrap; gap: 0.6rem;
}
.pill-option {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 7px 16px; border-radius: var(--r-pill);
  border: 1.5px solid var(--border-strong);
  background: var(--bg-elevated);
  color: var(--text-secondary);
  font-size: 0.83rem; font-weight: 500;
  cursor: pointer; transition: all 0.22s var(--ease-out);
  font-family: 'Anuphan', sans-serif;
  user-select: none;
}
.pill-option:hover {
  border-color: var(--brand-500); color: var(--brand-500);
  transform: translateY(-2px); box-shadow: var(--shadow-sm);
}
.pill-option.selected {
  background: var(--brand-500); border-color: var(--brand-500);
  color: #fff; box-shadow: var(--shadow-brand);
}

/* ══════════════════════════════════════════════════════ DRESS CARDS */
.dress-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(130px,1fr)); gap: 0.75rem;
}
.dress-card {
  background: var(--bg-elevated); border: 1.5px solid var(--border-strong);
  border-radius: var(--r-md); padding: 1rem 0.75rem;
  text-align: center; cursor: pointer;
  transition: all 0.22s var(--ease-out);
  font-size: 0.85rem; font-weight: 500; color: var(--text-primary);
  font-family: 'Anuphan', sans-serif; user-select: none;
  display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
}
.dress-card .dress-icon { font-size: 1.5rem; }
.dress-card:hover { border-color: var(--brand-500); transform: translateY(-3px); box-shadow: var(--shadow-md); }
.dress-card.selected {
  background: linear-gradient(135deg, var(--brand-500), var(--brand-700));
  border-color: var(--brand-500); color: #fff;
  box-shadow: var(--shadow-brand);
}

/* ══════════════════════════════════════════════════════ PERSON GRID */
.person-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 0.65rem;
}
.person-card {
  display: flex; align-items: center; gap: 0.6rem;
  background: var(--bg-elevated); border: 1.5px solid var(--border-strong);
  border-radius: var(--r-sm); padding: 0.7rem 1rem;
  cursor: pointer; transition: all 0.22s var(--ease-out);
  user-select: none;
}
.person-card:hover { border-color: var(--brand-400); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
.person-card input[type="checkbox"] { display: none; }
.person-check {
  width: 20px; height: 20px; border-radius: 5px; flex-shrink: 0;
  border: 2px solid var(--border-strong); background: var(--input-bg);
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s; font-size: 0.75rem; color: transparent;
}
.person-card input:checked ~ .person-check,
.person-card.checked .person-check {
  background: var(--brand-500); border-color: var(--brand-500); color: #fff;
}
.person-card input:checked ~ .person-label { color: var(--brand-500); font-weight: 600; }
.person-card input:disabled ~ .person-check  { opacity: 0.45; }
.person-card input:disabled ~ .person-label  { opacity: 0.45; }
.person-label { font-size: 0.85rem; color: var(--text-primary); font-family: 'Anuphan', sans-serif; line-height: 1.3; }

/* ══════════════════════════════════════════════════════ FILE UPLOAD */
.file-drop {
  border: 2px dashed var(--border-strong); border-radius: var(--r-md);
  padding: 2rem 1.5rem; text-align: center; cursor: pointer;
  transition: all 0.25s var(--ease-out); background: transparent;
  position: relative; overflow: hidden;
}
.file-drop:hover, .file-drop.dragover {
  border-color: var(--brand-500); background: rgba(30,111,217,0.05);
}
.file-drop input[type="file"] {
  position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.file-drop-icon { font-size: 2.2rem; color: var(--brand-400); margin-bottom: 0.6rem; display: block; }
.file-drop-text { font-size: 0.9rem; color: var(--text-secondary); font-weight: 500; display: block; }
.file-drop-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem; display: block; }
.file-selected-badge {
  display: none; align-items: center; gap: 0.6rem;
  background: rgba(30,111,217,0.1); border: 1px solid var(--border-strong);
  border-radius: var(--r-sm); padding: 0.6rem 1rem; margin-top: 0.75rem;
  font-size: 0.85rem; color: var(--text-secondary);
}
.file-selected-badge.show { display: flex; }
.file-size-warn { font-size: 0.78rem; color: var(--accent-red); }

/* ══════════════════════════════════════════════════════ TIME GROUP */
.time-row { display: flex; align-items: center; gap: 0.75rem; }
.time-sep { font-size: 0.85rem; color: var(--text-muted); flex-shrink: 0; }

/* ══════════════════════════════════════════════════════ COPY SECTION */
.copy-select-row {
  display: grid; grid-template-columns: 1fr auto;
  gap: 1rem; align-items: flex-start;
}
@media (max-width: 600px) {
  .copy-select-row { grid-template-columns: 1fr; }
  .btn-back-header span { display: none; }
}

.copy-search-wrap { position: relative; min-width: 0; }

/* Badge count */
.copy-badge-count {
  font-size: 0.72rem; font-weight: 600; color: var(--accent-gold);
  background: rgba(232,160,32,.12); border: 1px solid rgba(232,160,32,.3);
  border-radius: var(--r-pill); padding: 2px 10px;
  white-space: nowrap; align-self: center;
}

/* ── Combobox trigger ── */
.copy-combobox { position: relative; }

.copy-combobox-field {
  display: flex; align-items: center; gap: 0.5rem;
  background: var(--input-bg); border: 1.5px solid var(--border-input);
  border-radius: var(--r-sm); padding: 0.55rem 0.9rem;
  cursor: pointer; transition: all 0.22s var(--ease-out);
  min-height: 42px; user-select: none;
}
.copy-combobox-field:hover,
.copy-combobox-field:focus { border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(30,111,217,.15); outline: none; }
.copy-combobox-field.open { border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(30,111,217,.18); border-bottom-left-radius: 0; border-bottom-right-radius: 0; }

.copy-field-icon { color: var(--accent-gold); font-size: 1rem; flex-shrink: 0; }
.copy-field-text { flex: 1; font-size: 0.9rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.copy-field-text.has-value { color: var(--text-primary); font-weight: 500; }
.copy-field-clear { color: var(--text-muted); cursor: pointer; font-size: 1rem; flex-shrink: 0; transition: color 0.2s; }
.copy-field-clear:hover { color: var(--accent-red); }
.copy-field-arrow { color: var(--text-muted); font-size: 0.8rem; flex-shrink: 0; transition: transform 0.25s var(--ease-out); }
.copy-combobox-field.open .copy-field-arrow { transform: rotate(180deg); }

/* ── Floating dropdown — fixed so it escapes form-card overflow:hidden ── */
.copy-dropdown {
  position: fixed;
  background: var(--bg-elevated);
  border: 1.5px solid var(--brand-500);
  border-radius: var(--r-md);
  box-shadow: 0 20px 60px rgba(30,111,217,.28), 0 8px 24px rgba(0,0,0,.18);
  z-index: 9000;
  display: none;
  flex-direction: column;
  max-height: 360px;
  overflow: hidden;
  animation: dropReveal 0.2s var(--ease-out);
}
.copy-dropdown.open { display: flex; }
@keyframes dropReveal { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

/* Search bar inside dropdown */
.copy-dropdown-search {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.65rem 0.85rem;
  border-bottom: 1px solid var(--border);
  background: var(--bg-surface);
  flex-shrink: 0;
}
.copy-search-icon { color: var(--brand-400); font-size: 0.95rem; flex-shrink: 0; }
.copy-search-input {
  flex: 1; border: none; background: transparent; outline: none;
  font-family: 'Anuphan', sans-serif; font-size: 0.88rem;
  color: var(--text-primary);
}
.copy-search-input::placeholder { color: var(--text-muted); }
.copy-search-count {
  font-size: 0.72rem; color: var(--text-muted);
  white-space: nowrap; flex-shrink: 0;
}

/* Scrollable list */
.copy-dropdown-list {
  overflow-y: auto; flex: 1;
  scrollbar-width: thin; scrollbar-color: var(--border-strong) transparent;
}
.copy-dropdown-list::-webkit-scrollbar { width: 5px; }
.copy-dropdown-list::-webkit-scrollbar-track { background: transparent; }
.copy-dropdown-list::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 9px; }

/* Group header */
.copy-group-header {
  padding: 0.45rem 0.9rem 0.3rem;
  font-size: 0.7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: var(--text-muted); background: var(--bg-surface);
  position: sticky; top: 0; z-index: 1;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 0.4rem;
}

/* Each option row */
.copy-option {
  display: flex; align-items: center; gap: 0.65rem;
  padding: 0.6rem 0.9rem; cursor: pointer;
  transition: background 0.15s, transform 0.12s;
  border-bottom: 1px solid var(--border);
}
.copy-option:last-child { border-bottom: none; }
.copy-option:hover { background: rgba(30,111,217,.08); transform: translateX(3px); }
.copy-option.active { background: rgba(30,111,217,.12); }
.copy-option.focused { background: rgba(30,111,217,.12); }

.copy-opt-icon {
  width: 30px; height: 30px; border-radius: var(--r-sm); flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem;
}
.copy-opt-icon.upcoming { background: rgba(30,111,217,.12); color: var(--brand-500); }
.copy-opt-icon.past     { background: rgba(94,152,239,.1);  color: var(--text-muted); }

.copy-opt-body { flex: 1; min-width: 0; }
.copy-opt-title {
  font-size: 0.87rem; font-weight: 500; color: var(--text-primary);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  line-height: 1.3;
}
.copy-opt-date { font-size: 0.75rem; color: var(--text-muted); margin-top: 1px; }
.copy-opt-title mark {
  background: rgba(232,160,32,.3); color: inherit;
  border-radius: 2px; padding: 0 1px;
}

/* Empty state */
.copy-dropdown-empty {
  padding: 2.5rem 1rem; text-align: center;
  color: var(--text-muted); font-size: 0.85rem;
}

/* Selected card below trigger */
.copy-selected-card {
  display: flex; align-items: center; gap: 0.65rem;
  margin-top: 0.6rem; padding: 0.6rem 1rem;
  background: rgba(30,111,217,.07);
  border: 1px solid rgba(30,111,217,.2);
  border-radius: var(--r-sm);
  animation: revealIn 0.25s var(--ease-out);
}
.copy-sel-icon { font-size: 1.1rem; color: var(--brand-500); flex-shrink: 0; }
.copy-sel-info { flex: 1; min-width: 0; }
.copy-sel-title { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.copy-sel-date  { font-size: 0.76rem; color: var(--text-muted); }

/* Actions column */
.copy-actions-col {
  display: flex; flex-direction: column; gap: 0.5rem;
  padding-top: 1.55rem; /* align with field */
}
.btn-copy-action {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 0.55rem 1.1rem; border-radius: var(--r-sm);
  font-size: 0.83rem; font-weight: 500;
  cursor: pointer; border: none; font-family: 'Anuphan', sans-serif;
  transition: all 0.22s var(--ease-out); white-space: nowrap;
}
.btn-copy-primary { background: var(--brand-500); color: #fff; box-shadow: var(--shadow-brand); }
.btn-copy-primary:hover { background: var(--brand-600); transform: translateY(-2px); }
.btn-copy-ghost  { background: transparent; color: var(--text-secondary); border: 1.5px solid var(--border-strong); }
.btn-copy-ghost:hover  { background: var(--bg-elevated); transform: translateY(-1px); }

/* ══════════════════════════════════════════════════════ MODAL */
.ent-modal {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.6); backdrop-filter: blur(6px);
  z-index: 9999; align-items: center; justify-content: center; padding: 1rem;
}
.ent-modal.open { display: flex; animation: fadeIn 0.25s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-box {
  background: var(--bg-elevated); border: 1px solid var(--border-strong);
  border-radius: var(--r-xl); width: 100%; max-width: 620px; max-height: 85vh;
  overflow: hidden; display: flex; flex-direction: column;
  box-shadow: 0 30px 80px rgba(0,0,0,0.4);
  animation: modalSlide 0.3s var(--ease-out);
}
@keyframes modalSlide { from { transform: translateY(20px) scale(0.97); opacity: 0; } to { transform: none; opacity: 1; } }
.modal-head {
  background: var(--header-bg); color: #fff;
  padding: 1.1rem 1.5rem; display: flex; align-items: center; gap: 0.75rem;
  border-radius: var(--r-xl) var(--r-xl) 0 0;
}
.modal-head-title { font-size: 1rem; font-weight: 600; flex: 1; }
.modal-close { background: rgba(255,255,255,.15); border: none; color: #fff; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
.modal-close:hover { background: rgba(255,255,255,.3); }
.modal-body-content { overflow-y: auto; padding: 1.5rem; flex: 1; }
.modal-foot {
  padding: 1rem 1.5rem; border-top: 1px solid var(--border);
  display: flex; gap: 0.75rem; justify-content: flex-end;
  background: var(--bg-surface);
}

/* Preview table in modal */
.preview-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.preview-table tr { border-bottom: 1px solid var(--border); }
.preview-table tr:last-child { border-bottom: none; }
.preview-table td:first-child { width: 36%; padding: 0.6rem 0.75rem; color: var(--text-muted); font-weight: 500; }
.preview-table td:last-child  { padding: 0.6rem 0.75rem; color: var(--text-primary); }

/* ══════════════════════════════════════════════════════ FORM ACTIONS */
.form-actions {
  display: flex; align-items: center; justify-content: space-between;
  gap: 0.75rem; padding: 1.5rem; flex-wrap: wrap;
  background: var(--bg-elevated); border: 1px solid var(--border-strong);
  border-radius: var(--r-lg); box-shadow: var(--shadow-md);
  margin-bottom: 2rem;
}
.actions-left, .actions-right { display: flex; gap: 0.65rem; align-items: center; }

.btn-ent {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 0.6rem 1.4rem; border-radius: var(--r-sm);
  font-size: 0.88rem; font-weight: 600;
  cursor: pointer; border: none; font-family: 'Anuphan', sans-serif;
  transition: all 0.25s var(--ease-out); text-decoration: none;
}
.btn-ent-primary {
  background: var(--brand-500); color: #fff; box-shadow: var(--shadow-brand);
  min-width: 130px; justify-content: center;
}
.btn-ent-primary:hover { background: var(--brand-600); transform: translateY(-2px); box-shadow: 0 12px 28px rgba(30,111,217,.4); }
.btn-ent-secondary { background: transparent; color: var(--text-secondary); border: 1.5px solid var(--border-strong); }
.btn-ent-secondary:hover { background: var(--bg-elevated); transform: translateY(-1px); color: var(--text-primary); }
.btn-ent-danger { background: transparent; color: var(--accent-red); border: 1.5px solid rgba(224,82,82,.35); }
.btn-ent-danger:hover { background: rgba(224,82,82,.08); transform: translateY(-1px); }
.btn-loading { opacity: 0.7; pointer-events: none; }

/* ══════════════════════════════════════════════════════ CUSTOM INPUT */
.custom-input-reveal { display: none; margin-top: 0.6rem; }
.custom-input-reveal.visible { display: block; animation: revealIn 0.25s var(--ease-out); }
@keyframes revealIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

/* ══════════════════════════════════════════════════════ FOOTER */
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

/* ══════════════════════════════════════════════════════ TOAST */
.toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
.toast-item {
  background: var(--bg-elevated); border: 1px solid var(--border-strong);
  border-radius: var(--r-md); padding: 0.85rem 1.2rem;
  box-shadow: var(--shadow-lg); min-width: 280px; max-width: 380px;
  display: flex; align-items: center; gap: 0.75rem;
  animation: toastIn 0.35s var(--ease-out); font-size: 0.88rem;
  font-family: 'Anuphan', sans-serif; color: var(--text-primary);
}
@keyframes toastIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
.toast-item.toast-out { animation: toastOut 0.3s forwards; }
@keyframes toastOut { to { opacity: 0; transform: translateX(30px); } }
.toast-icon { font-size: 1.2rem; flex-shrink: 0; }
.toast-success .toast-icon { color: var(--accent-green); }
.toast-warning .toast-icon { color: var(--accent-gold); }
.toast-danger  .toast-icon { color: var(--accent-red); }

/* ══════════════════════════════════════════════════════ PROGRESS STEPS */
.form-steps {
  display: flex; align-items: center; gap: 0;
  margin-bottom: 1.75rem; padding: 1rem 1.5rem;
  background: var(--bg-surface); border: 1px solid var(--border-strong);
  border-radius: var(--r-lg); overflow-x: auto; box-shadow: var(--shadow-sm);
}
.step-item {
  display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;
}
.step-num {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--border-strong); color: var(--text-muted);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.78rem; font-weight: 700; transition: all 0.3s;
}
.step-item.active .step-num { background: var(--brand-500); color: #fff; box-shadow: 0 4px 12px rgba(30,111,217,.35); }
.step-item.done .step-num   { background: var(--accent-green); color: #fff; }
.step-label { font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; font-weight: 500; }
.step-item.active .step-label { color: var(--brand-500); font-weight: 600; }
.step-item.done  .step-label  { color: var(--accent-green); }
.step-connector { flex: 1; height: 1px; background: var(--border-strong); min-width: 20px; margin: 0 0.4rem; }

/* ══════════════════════════════════════════════════════ RESPONSIVE */
@media (max-width: 768px) {
  .header-inner { grid-template-columns: 1fr auto; padding: 0.75rem 1rem; }
  .header-center { display: none; }
  .header-text h1 { font-size: 0.92rem; }
  .header-text p  { font-size: 0.7rem; }
  .header-logo { width: 40px; height: 40px; }
  .breadcrumb-bar { padding: 0.5rem 1rem; }
  .app-main { padding: 1rem; }
  .section-body { padding: 1rem; }
  .section-header { padding: 0.85rem 1rem; }
  .dress-grid { grid-template-columns: repeat(auto-fill, minmax(100px,1fr)); gap: 0.55rem; }
  .person-grid { grid-template-columns: 1fr; }
  .form-actions { flex-direction: column-reverse; gap: 0.5rem; }
  .actions-left, .actions-right { width: 100%; justify-content: stretch; }
  .actions-right { flex-direction: column; gap: 0.5rem; }
  .btn-ent { width: 100%; justify-content: center; }
  .form-steps { gap: 0; padding: 0.75rem 0.75rem; overflow-x: auto; }
  .step-label { font-size: 0.7rem; }
  .page-title-block { flex-wrap: wrap; gap: 0.75rem; }
  .page-title-icon { width: 42px; height: 42px; font-size: 1.1rem; }
  .page-title-text h2 { font-size: 1.1rem; }
  .form-meta-chip { display: none; }
  .time-row { flex-wrap: wrap; }
  .time-row .form-control { min-width: 100px; flex: 1; }
  .copy-dropdown { max-width: calc(100vw - 2rem); }
  .form-card { border-radius: var(--r-md); }
  .user-chip .role-badge { display: none; }
  #autoModeBadge { display: none !important; }
}
@media (max-width: 480px) {
  .pill-grid { gap: 0.45rem; }
  .pill-option { font-size: 0.78rem; padding: 5px 12px; }
  .dress-grid { grid-template-columns: repeat(auto-fill, minmax(88px,1fr)); }
  .dress-card { padding: 0.75rem 0.5rem; font-size: 0.8rem; }
  .dress-card .dress-icon { font-size: 1.25rem; }
  .section-header { flex-wrap: nowrap; }
  .section-title { font-size: 0.88rem; }
  .section-subtitle { font-size: 0.72rem; }
  .form-steps { padding: 0.65rem 0.5rem; }
  .step-num { width: 24px; height: 24px; font-size: 0.72rem; }
  .step-connector { min-width: 12px; }
  .step-label { font-size: 0.65rem; }
  .app-main { padding: 0.75rem; }
  .form-wrapper { padding: 0; }
  .toast-item { min-width: unset; max-width: calc(100vw - 2rem); }
  .toast-container { right: 0.75rem; left: 0.75rem; bottom: 1rem; }
  .modal-box { border-radius: var(--r-lg); margin: 0.5rem; }
  .modal-head { padding: 0.85rem 1rem; }
  .modal-body-content { padding: 1rem; }
  .modal-foot { padding: 0.75rem 1rem; flex-wrap: wrap; }
  .modal-foot .btn-ent { flex: 1; }
}

/* ══════════════════════════════════════════════════════ FLATPICKR DARK MODE */
[data-theme="dark"] .flatpickr-calendar {
  background: #0d2340; border-color: rgba(255,255,255,0.12); color: #f0f4ff;
}
[data-theme="dark"] .flatpickr-day { color: #f0f4ff; }
[data-theme="dark"] .flatpickr-day.selected { background: var(--brand-500); border-color: var(--brand-500); }
[data-theme="dark"] .flatpickr-day:hover { background: rgba(30,111,217,0.3); }
[data-theme="dark"] .flatpickr-months { color: #f0f4ff; }
[data-theme="dark"] .flatpickr-months .flatpickr-month { color: #f0f4ff; fill: #f0f4ff; }
[data-theme="dark"] .flatpickr-current-month .flatpickr-monthDropdown-months,
[data-theme="dark"] .flatpickr-current-month input.cur-year { color: #f0f4ff; background: transparent; }
[data-theme="dark"] .flatpickr-weekday { color: rgba(240,244,255,0.6); }
[data-theme="dark"] .flatpickr-day.flatpickr-disabled { color: rgba(240,244,255,0.25); }
[data-theme="dark"] .flatpickr-time input { background: #0d2340; color: #f0f4ff; border-color: rgba(255,255,255,0.12); }
[data-theme="dark"] .flatpickr-time .flatpickr-time-separator { color: #f0f4ff; }
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

    <!-- Center: Live Clock (same as dashboard) -->
    <div class="header-center">
      <div class="header-clock" id="liveClock">--:--:--</div>
      <div class="header-date" id="liveDate"><?= htmlspecialchars($current_date_thai) ?></div>
    </div>

    <!-- Actions -->
    <div class="header-actions">
      <a href="dashboard" class="user-chip">
        <i class="bi bi-person-circle"></i>
        <?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?>
        <span class="role-badge <?= $role === 'admin' ? 'admin' : 'user' ?>"><?= $role === 'admin' ? 'Admin' : 'User' ?></span>
      </a>
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
    <span class="breadcrumb-current">เพิ่มนัดหมายงาน</span>
  </div>
</nav>

<!-- ═══ MAIN ═══ -->
<main class="app-main">
  <div class="form-wrapper">

    <!-- Page Title -->
    <div class="page-title-block">
      <div class="page-title-icon"><i class="bi bi-calendar-plus-fill"></i></div>
      <div class="page-title-text">
        <h2>กรอกข้อมูลการนัดหมายงาน</h2>
        <p>กรุณากรอกข้อมูลให้ครบถ้วนก่อนบันทึก</p>
      </div>
		<span style="display:none;">
      <div class="form-meta-chip">
        <i class="bi bi-person-circle"></i>
		<?php echo htmlspecialchars($created_by); ?>
		</span>
      </div>
    </div>

    <!-- Progress Steps -->
    <div class="form-steps" id="formSteps">
      <div class="step-item active" data-step="0">
        <div class="step-num">1</div>
        <div class="step-label">ข้อมูลพื้นฐาน</div>
      </div>
      <div class="step-connector"></div>
      <div class="step-item" data-step="1">
        <div class="step-num">2</div>
        <div class="step-label">ผู้รับผิดชอบ</div>
      </div>
      <div class="step-connector"></div>
      <div class="step-item" data-step="2">
        <div class="step-num">3</div>
        <div class="step-label">การแต่งกาย</div>
      </div>
      <div class="step-connector"></div>
      <div class="step-item" data-step="3">
        <div class="step-num">4</div>
        <div class="step-label">หน่วยงาน</div>
      </div>
      <div class="step-connector"></div>
      <div class="step-item" data-step="4">
        <div class="step-num">5</div>
        <div class="step-label">เอกสารแนบ</div>
      </div>
    </div>

    <!-- Status Alert -->
    <?php if (!empty($statusMsg)): ?>
    <div class="enterprise-alert <?php echo $status; ?>" id="statusAlert">
      <i class="bi <?php echo $status === 'success' ? 'bi-check-circle-fill' : ($status === 'danger' ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill'); ?> alert-icon"></i>
      <div><?php echo htmlspecialchars($statusMsg); ?></div>
      <button class="alert-dismiss" onclick="this.parentElement.remove()" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
    </div>
    <?php endif; ?>

    <!-- ── FORM ── -->
    <form method="post" action="addEvent" enctype="multipart/form-data" id="mainForm" onsubmit="return validateForm(event)">

      <!-- ①  คัดลอกจากงานที่มีอยู่ -->
      <div class="form-card">
        <div class="section-header">
          <div class="section-icon gold"><i class="bi bi-copy"></i></div>
          <div>
            <div class="section-title">คัดลอกข้อมูลจากงานที่มีอยู่</div>
            <div class="section-subtitle">พิมพ์ค้นหาและเลือกงานเพื่อนำข้อมูลมากรอกอัตโนมัติ</div>
          </div>
          <div class="copy-badge-count ms-auto" id="copyEventCountBadge"></div>
        </div>
        <div class="section-body">
          <div class="copy-select-row">
            <div class="copy-search-wrap">
              <label class="form-label"><i class="bi bi-search"></i> ค้นหางาน</label>

              <!-- Hidden real value -->
              <input type="hidden" id="copy_event" name="copy_event" value="">

              <!-- Trigger input -->
              <div class="copy-combobox" id="copyCombobox">
                <div class="copy-combobox-field" id="copyComboboxField" tabindex="0" role="combobox"
                     aria-expanded="false" aria-haspopup="listbox" aria-controls="copyDropdown">
                  <span class="copy-field-icon"><i class="bi bi-calendar-event"></i></span>
                  <span class="copy-field-text" id="copyFieldText">— เลือกหรือพิมพ์ค้นหางาน —</span>
                  <span class="copy-field-clear" id="copyFieldClear" title="ล้างการเลือก" style="display:none">
                    <i class="bi bi-x-circle-fill"></i>
                  </span>
                  <span class="copy-field-arrow"><i class="bi bi-chevron-down"></i></span>
                </div>
              </div>

              <!-- Selected event mini card -->
              <div class="copy-selected-card" id="copySelectedCard" style="display:none">
                <i class="bi bi-calendar-check-fill copy-sel-icon"></i>
                <div class="copy-sel-info">
                  <div class="copy-sel-title" id="copySelTitle"></div>
                  <div class="copy-sel-date" id="copySelDate"></div>
                </div>
              </div>
            </div>

            <div class="copy-actions-col">
              <button type="button" class="btn-copy-action btn-copy-primary" id="copyDataBtn">
                <i class="bi bi-clipboard-check"></i> คัดลอก
              </button>
              <button type="button" class="btn-copy-action btn-copy-ghost" id="previewEventBtn">
                <i class="bi bi-eye"></i> ดูตัวอย่าง
              </button>
            </div>
          </div>
        </div>
      </div>

      <?php
      /* Build JS event data array for the searchable dropdown */
      $eventsJs = [];
      $fr2 = mysqli_query($connect, "SELECT id,title,date FROM events WHERE date>=CURDATE() ORDER BY date ASC");
      if ($fr2) while ($ev = mysqli_fetch_assoc($fr2))
        $eventsJs[] = ['id'=>(int)$ev['id'],'title'=>$ev['title'],'date'=>$ev['date'],'group'=>'upcoming'];
      $pr2 = mysqli_query($connect, "SELECT id,title,date FROM events WHERE date<CURDATE() ORDER BY date DESC LIMIT 200");
      if ($pr2) while ($ev = mysqli_fetch_assoc($pr2))
        $eventsJs[] = ['id'=>(int)$ev['id'],'title'=>$ev['title'],'date'=>$ev['date'],'group'=>'past'];
      ?>
      <script>
      const COPY_EVENTS = <?php echo json_encode($eventsJs, JSON_UNESCAPED_UNICODE); ?>;
      </script>

      <!-- ②  ข้อมูลพื้นฐาน -->
      <div class="form-card" id="section-basic">
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
                   value="<?php echo htmlspecialchars($postData['title'] ?? ''); ?>"
                   placeholder="ระบุชื่อภารกิจหรือชื่องาน" required>
          </div>

          <!-- ประเภท -->
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-tag-fill"></i> ประเภทงาน <span class="req-star">*</span></label>
            <div class="pill-grid" id="categoryPills">
              <?php foreach ($eventCategories as $cat): ?>
                <div class="pill-option category-option <?php echo (!empty($postData['category_id']) && $postData['category_id'] == $cat['id']) ? 'selected' : ''; ?>"
                     data-value="<?php echo $cat['id']; ?>">
                  <?php echo htmlspecialchars($cat['name']); ?>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="category_id" id="category_id" value="<?php echo htmlspecialchars($postData['category_id'] ?? ''); ?>" required>
          </div>

          <!-- วันที่ + เวลา -->
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label" for="date">
                <i class="bi bi-calendar3"></i> วันที่ <span class="req-star">*</span>
              </label>
              <input type="text" class="form-control date-picker" id="date" name="date"
                     placeholder="เลือกวันที่" required>
            </div>
            <div class="col-md-7">
              <label class="form-label"><i class="bi bi-clock"></i> ช่วงเวลา <span class="req-star">*</span></label>
              <div class="time-row">
                <input type="text" class="form-control time-picker" name="time_from" placeholder="เริ่ม" required>
                <span class="time-sep">ถึง</span>
                <input type="text" class="form-control time-picker" name="time_to" placeholder="สิ้นสุด" required>
              </div>
            </div>
          </div>

          <!-- สถานที่ -->
          <div class="mb-0">
            <label class="form-label" for="location">
              <i class="bi bi-geo-alt-fill"></i> สถานที่ <span class="req-star">*</span>
            </label>
            <input type="text" class="form-control" id="location" name="location"
                   value="<?php echo htmlspecialchars($postData['location'] ?? ''); ?>"
                   placeholder="ระบุสถานที่จัดงาน" required>
          </div>
        </div>
      </div>

      <!-- ③  ผู้รับผิดชอบ -->
      <div class="form-card" id="section-persons">
        <div class="section-header">
          <div class="section-icon green"><i class="bi bi-people-fill"></i></div>
          <div>
            <div class="section-title">ผู้รับผิดชอบ</div>
            <div class="section-subtitle">เลือกบุคลากรที่เกี่ยวข้อง</div>
          </div>
        </div>
        <div class="section-body">
          <div class="person-grid">
            <?php foreach ($responsiblePersons as $person): ?>
              <label class="person-card" id="pc_<?php echo htmlspecialchars($person['name']); ?>">
                <input type="checkbox" name="responsible_persons[]"
                       value="<?php echo htmlspecialchars($person['name']); ?>"
                       id="cb_<?php echo htmlspecialchars($person['name']); ?>">
                <div class="person-check"><i class="bi bi-check2"></i></div>
                <span class="person-label"><?php echo htmlspecialchars($person['name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="description" id="description" value="<?php echo htmlspecialchars($postData['description'] ?? ''); ?>" required>
          <input type="hidden" name="attendees"   id="attendees"   value="<?php echo htmlspecialchars($postData['attendees'] ?? ''); ?>">
        </div>
      </div>

      <!-- ④  การแต่งกาย -->
      <div class="form-card" id="section-dress">
        <div class="section-header">
          <div class="section-icon red"><i class="bi bi-person-badge-fill"></i></div>
          <div>
            <div class="section-title">การแต่งกาย</div>
            <div class="section-subtitle">เลือกชุดที่กำหนด</div>
          </div>
        </div>
        <div class="section-body">
          <div class="dress-grid">
            <?php
            $dressIcons = ['ชุดปกติขาว'=>'👔','ชุดสุภาพ'=>'👗','ชุดกีฬา'=>'👟','ผ้าไทย'=>'🪡','ชุดดำ'=>'🖤','ชุดเครื่องแบบ'=>'🎖️','ชุดสากล'=>'🤵'];
            foreach ($dressOptions as $d):
              $sel = (!empty($postData['dress']) && str_contains($postData['dress'], $d)) ? 'selected' : '';
              $icon = $dressIcons[$d] ?? '👔';
            ?>
              <div class="dress-card <?php echo $sel; ?>" data-value="<?php echo htmlspecialchars($d); ?>">
                <span class="dress-icon"><?php echo $icon; ?></span>
                <?php echo htmlspecialchars($d); ?>
              </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="dress" id="dress" value="<?php echo htmlspecialchars($postData['dress'] ?? ''); ?>" required>

          <!-- ผ้าไทย — extra input -->
          <div id="additionalDressInfo" class="custom-input-reveal <?php echo (str_contains($postData['dress'] ?? '', 'ผ้าไทย')) ? 'visible' : ''; ?>">
            <label class="form-label mt-2"><i class="bi bi-palette"></i> ระบุโทนสี เช่น โทนสีม่วง</label>
            <input type="text" class="form-control" id="additionalDressInput" name="additionalDressInfo"
                   placeholder="โทนสี..."
                   value="<?php
                     if (!empty($postData['dress']) && str_contains($postData['dress'],'ผ้าไทย')) {
                         preg_match('/\((.*?)\)/', $postData['dress'], $m); echo htmlspecialchars($m[1] ?? '');
                     }
                   ?>">
          </div>
        </div>
      </div>

      <!-- ⑤  หน่วยงาน & ประธาน -->
      <div class="form-card" id="section-dept">
        <div class="section-header">
          <div class="section-icon purple"><i class="bi bi-building-fill"></i></div>
          <div>
            <div class="section-title">หน่วยงานและประธาน</div>
            <div class="section-subtitle">หน่วยงานที่จัดงานและประธานในพิธี</div>
          </div>
        </div>
        <div class="section-body">
          <?php
            $deptOptions_js  = array_merge($departmentOptions, ['อื่นๆ (ระบุเอง)']);
            $chairOptions_js = array_merge($chairmanOptions,   ['อื่นๆ (ระบุเอง)']);
          ?>
          <script>
            const DEPT_OPTIONS  = <?php echo json_encode($departmentOptions, JSON_UNESCAPED_UNICODE); ?>;
            const CHAIR_OPTIONS = <?php echo json_encode($chairmanOptions,   JSON_UNESCAPED_UNICODE); ?>;
          </script>

          <div class="row g-3">

            <!-- ── หน่วยงาน combobox ── -->
            <div class="col-md-6">
              <label class="form-label">
                <i class="bi bi-buildings"></i> หน่วยงาน <span class="req-star">*</span>
              </label>
              <input type="hidden" id="department" name="department"
                     value="<?php echo htmlspecialchars($postData['department'] ?? ''); ?>" required>

              <div class="copy-combobox" id="deptCombobox">
                <div class="copy-combobox-field" id="deptComboboxField" tabindex="0" role="combobox"
                     aria-expanded="false" aria-haspopup="listbox" aria-controls="deptDropdown">
                  <span class="copy-field-icon"><i class="bi bi-buildings"></i></span>
                  <span class="copy-field-text" id="deptFieldText">— เลือกหรือพิมพ์ค้นหาหน่วยงาน —</span>
                  <span class="copy-field-clear" id="deptFieldClear" title="ล้างการเลือก" style="display:none">
                    <i class="bi bi-x-circle-fill"></i>
                  </span>
                  <span class="copy-field-arrow"><i class="bi bi-chevron-down"></i></span>
                </div>
              </div>

              <div id="customDeptWrap" class="custom-input-reveal">
                <input type="text" class="form-control" id="custom_department" name="custom_department"
                       placeholder="กรอกชื่อหน่วยงาน (อื่นๆ)">
              </div>
            </div>

            <!-- ── ประธาน combobox ── -->
            <div class="col-md-6">
              <label class="form-label">
                <i class="bi bi-person-workspace"></i> ประธาน
              </label>
              <input type="hidden" id="chairman" name="chairman"
                     value="<?php echo htmlspecialchars($postData['chairman'] ?? ''); ?>">

              <div class="copy-combobox" id="chairCombobox">
                <div class="copy-combobox-field" id="chairComboboxField" tabindex="0" role="combobox"
                     aria-expanded="false" aria-haspopup="listbox" aria-controls="chairDropdown">
                  <span class="copy-field-icon"><i class="bi bi-person-workspace"></i></span>
                  <span class="copy-field-text" id="chairFieldText">— เลือกหรือพิมพ์ค้นหาประธาน —</span>
                  <span class="copy-field-clear" id="chairFieldClear" title="ล้างการเลือก" style="display:none">
                    <i class="bi bi-x-circle-fill"></i>
                  </span>
                  <span class="copy-field-arrow"><i class="bi bi-chevron-down"></i></span>
                </div>
              </div>

              <div id="customChairmanWrap" class="custom-input-reveal">
                <input type="text" class="form-control" id="custom_chairman" name="custom_chairman"
                       placeholder="กรอกชื่อประธาน (อื่นๆ)">
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- ⑥  ผู้ประสานงาน -->
      <div class="form-card" id="section-coord">
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
                     value="<?php echo htmlspecialchars($postData['coordinator_name'] ?? ''); ?>"
                     placeholder="ชื่อ-นามสกุล">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="coordinator_phone">
                <i class="bi bi-telephone-fill"></i> เบอร์โทรศัพท์
              </label>
              <input type="tel" class="form-control" id="coordinator_phone" name="coordinator_phone"
                     value="<?php echo htmlspecialchars($postData['coordinator_phone'] ?? ''); ?>"
                     placeholder="0xx-xxx-xxxx">
            </div>
          </div>
        </div>
      </div>

      <!-- ⑦  เอกสารแนบ -->
      <div class="form-card" id="section-file">
        <div class="section-header">
          <div class="section-icon gold"><i class="bi bi-paperclip"></i></div>
          <div>
            <div class="section-title">เอกสารแนบ</div>
            <div class="section-subtitle">PDF, JPG, PNG — ขนาดไม่เกิน 15 MB</div>
          </div>
        </div>
        <div class="section-body">
          <div class="file-drop" id="fileDrop">
            <input type="file" name="file" id="file" accept=".pdf,.jpg,.jpeg,.png">
            <i class="bi bi-cloud-arrow-up file-drop-icon"></i>
            <span class="file-drop-text">ลากวางไฟล์ที่นี่ หรือคลิกเพื่อเลือกไฟล์</span>
            <span class="file-drop-hint">รองรับ PDF, JPG, JPEG, PNG (สูงสุด 15 MB)</span>
          </div>
          <div class="file-selected-badge" id="fileSelectedBadge">
            <i class="bi bi-file-earmark-check-fill" style="color:var(--accent-green);font-size:1.1rem"></i>
            <span id="fileSelectedName">—</span>
            <span id="fileSelectedSize" class="ms-auto" style="font-size:0.78rem;color:var(--text-muted)"></span>
          </div>
          <input type="hidden" name="file_name" id="file_name">
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
          <button type="button" class="btn-ent btn-ent-danger" onclick="clearForm()">
            <i class="bi bi-eraser-fill"></i> ล้างฟอร์ม
          </button>
          <button type="submit" name="submit" class="btn-ent btn-ent-primary" id="submitBtn">
            <i class="bi bi-floppy-fill"></i> บันทึกข้อมูล
          </button>
        </div>
      </div>

    </form><!-- /form -->
  </div><!-- /form-wrapper -->
</main>

<!-- ═══ EVENT PREVIEW MODAL ═══ -->
<div class="ent-modal" id="previewModal">
  <div class="modal-box">
    <div class="modal-head">
      <i class="bi bi-eye-fill" style="font-size:1.1rem"></i>
      <div class="modal-head-title">ตัวอย่างข้อมูลงาน</div>
      <button class="modal-close" onclick="closePreviewModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body-content" id="previewContent">
      <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted)">
        <i class="bi bi-hourglass-split" style="font-size:2rem;display:block;margin-bottom:0.75rem"></i>
        กำลังโหลดข้อมูล...
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ent btn-ent-secondary" onclick="closePreviewModal()">ปิด</button>
      <button class="btn-ent btn-ent-primary" id="confirmCopyBtn">
        <i class="bi bi-clipboard-check-fill"></i> คัดลอกข้อมูลนี้
      </button>
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

<!-- ═══ COPY DROPDOWN PORTAL (appended to body to escape overflow:hidden) ═══ -->
<!-- ═══ DEPT FLOATING DROPDOWN ═══ -->
<div class="copy-dropdown" id="deptDropdown" role="listbox" aria-label="รายการหน่วยงาน">
  <div class="copy-dropdown-search">
    <i class="bi bi-search copy-search-icon"></i>
    <input type="text" id="deptSearchInput" class="copy-search-input"
           placeholder="พิมพ์ค้นหาหน่วยงาน..." autocomplete="off" spellcheck="false">
    <span class="copy-search-count" id="deptSearchCount"></span>
  </div>
  <div class="copy-dropdown-list" id="deptDropdownList"></div>
  <div class="copy-dropdown-empty" id="deptDropdownEmpty" style="display:none">
    <i class="bi bi-search" style="font-size:1.4rem;display:block;margin-bottom:.5rem;opacity:.4"></i>
    ไม่พบหน่วยงานที่ตรงกัน
  </div>
</div>

<!-- ═══ CHAIR FLOATING DROPDOWN ═══ -->
<div class="copy-dropdown" id="chairDropdown" role="listbox" aria-label="รายการประธาน">
  <div class="copy-dropdown-search">
    <i class="bi bi-search copy-search-icon"></i>
    <input type="text" id="chairSearchInput" class="copy-search-input"
           placeholder="พิมพ์ค้นหาประธาน..." autocomplete="off" spellcheck="false">
    <span class="copy-search-count" id="chairSearchCount"></span>
  </div>
  <div class="copy-dropdown-list" id="chairDropdownList"></div>
  <div class="copy-dropdown-empty" id="chairDropdownEmpty" style="display:none">
    <i class="bi bi-search" style="font-size:1.4rem;display:block;margin-bottom:.5rem;opacity:.4"></i>
    ไม่พบประธานที่ตรงกัน
  </div>
</div>

<div class="copy-dropdown" id="copyDropdown" role="listbox" aria-label="รายการงาน">
  <div class="copy-dropdown-search">
    <i class="bi bi-search copy-search-icon"></i>
    <input type="text" id="copySearchInput" class="copy-search-input"
           placeholder="พิมพ์ชื่องานหรือวันที่..." autocomplete="off" spellcheck="false">
    <span class="copy-search-count" id="copySearchCount"></span>
  </div>
  <div class="copy-dropdown-list" id="copyDropdownList"></div>
  <div class="copy-dropdown-empty" id="copyDropdownEmpty" style="display:none">
    <i class="bi bi-search" style="font-size:1.4rem;display:block;margin-bottom:.5rem;opacity:.4"></i>
    ไม่พบงานที่ตรงกัน
  </div>
</div>

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
   LIVE CLOCK
═══════════════════════════════════════════════════════ */
function updateClock() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2,'0');
  const m = String(now.getMinutes()).padStart(2,'0');
  const s = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('liveClock').textContent = `${h}:${m}:${s}`;
}
updateClock(); setInterval(updateClock, 1000);

/* ═══════════════════════════════════════════════════════
   FLATPICKR
═══════════════════════════════════════════════════════ */
flatpickr('.date-picker', { locale: 'th', dateFormat: 'd/m/Y', minDate: 'today' });
flatpickr('.time-picker', { enableTime: true, noCalendar: true, time_24hr: true, dateFormat: 'H:i' });

/* ═══════════════════════════════════════════════════════
   TOAST
═══════════════════════════════════════════════════════ */
function showToast(msg, type = 'success', dur = 4000) {
  const icons = { success:'bi-check-circle-fill', danger:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill' };
  const t = document.createElement('div');
  t.className = `toast-item toast-${type}`;
  t.innerHTML = `<i class="bi ${icons[type]||icons.success} toast-icon"></i><span>${msg}</span>`;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(() => {
    t.classList.add('toast-out');
    setTimeout(() => t.remove(), 320);
  }, dur);
}

/* ═══════════════════════════════════════════════════════
   STATUS ALERT AUTO DISMISS
═══════════════════════════════════════════════════════ */
const sa = document.getElementById('statusAlert');
if (sa) setTimeout(() => sa.remove(), 5000);

/* ═══════════════════════════════════════════════════════
   CATEGORY SELECTION
═══════════════════════════════════════════════════════ */
document.querySelectorAll('.category-option').forEach(el => {
  el.addEventListener('click', () => {
    document.querySelectorAll('.category-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('category_id').value = el.dataset.value;
    updateStepState();
  });
});

/* ═══════════════════════════════════════════════════════
   DRESS SELECTION
═══════════════════════════════════════════════════════ */
document.querySelectorAll('.dress-card').forEach(el => {
  el.addEventListener('click', () => {
    document.querySelectorAll('.dress-card').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('dress').value = el.dataset.value;
    const addi = document.getElementById('additionalDressInfo');
    el.dataset.value === 'ผ้าไทย'
      ? addi.classList.add('visible')
      : addi.classList.remove('visible');
    updateStepState();
  });
});

/* ═══════════════════════════════════════════════════════
   RESPONSIBLE PERSONS
═══════════════════════════════════════════════════════ */
const RESP_PERSONS = <?php echo json_encode($responsiblePersons); ?>;

function syncPersonHiddens() {
  const names = [], emails = [];
  document.querySelectorAll('.person-card input:checked').forEach(cb => {
    names.push(cb.value);
    const p = RESP_PERSONS.find(x => x.name === cb.value);
    if (p) emails.push(p.email);
  });
  document.getElementById('description').value = names.join(' ');
  document.getElementById('attendees').value   = emails.join(' ');
}

document.querySelectorAll('.person-card').forEach(card => {
  card.addEventListener('click', () => {
    const cb = card.querySelector('input[type="checkbox"]');
    if (cb.disabled) return;
    cb.checked = !cb.checked;

    const everyoneVal = 'จนท.สนง.ปภ.รย(ทุกท่าน)';
    const headVal     = 'หน.ปภ';

    if (cb.value === everyoneVal) {
      document.querySelectorAll('.person-card input[type="checkbox"]').forEach(o => {
        if (o.value !== everyoneVal && o.value !== headVal) {
          o.checked = false; o.disabled = cb.checked;
        }
      });
      const headCb = document.querySelector(`.person-card input[value="${headVal}"]`);
      if (headCb) { headCb.checked = cb.checked; headCb.disabled = cb.checked; }
    }
    syncPersonHiddens();
    updateStepState();
  });
});

/* ═══════════════════════════════════════════════════════
   GENERIC SEARCHABLE COMBOBOX FACTORY
   สำหรับ หน่วยงาน และ ประธาน
═══════════════════════════════════════════════════════ */
function makeSearchCombobox(cfg) {
  /*
    cfg = {
      fieldId, dropdownId, listId, searchId, countId, emptyId,
      clearId, fieldTextId, hiddenId,
      customWrapId, customInputId,       // for อื่นๆ reveal
      items,                             // string[]
      placeholder, customLabel,
      icon,                              // bootstrap-icon class (without "bi-")
      required,                          // bool
    }
  */
  const field      = document.getElementById(cfg.fieldId);
  const dropdown   = document.getElementById(cfg.dropdownId);
  const listEl     = document.getElementById(cfg.listId);
  const searchEl   = document.getElementById(cfg.searchId);
  const emptyEl    = document.getElementById(cfg.emptyId);
  const countEl    = document.getElementById(cfg.countId);
  const clearBtn   = document.getElementById(cfg.clearId);
  const fieldText  = document.getElementById(cfg.fieldTextId);
  const hiddenVal  = document.getElementById(cfg.hiddenId);
  const customWrap = document.getElementById(cfg.customWrapId);
  const customInp  = document.getElementById(cfg.customInputId);

  let focusedIndex   = -1;
  let visibleOptions = [];

  // ── Highlight
  function highlight(text, q) {
    if (!q) return text;
    const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${esc})`, 'gi'), '<mark>$1</mark>');
  }

  // ── Render
  function renderList(query) {
    const q = (query || '').trim().toLowerCase();
    listEl.innerHTML = '';
    visibleOptions   = [];
    emptyEl.style.display = 'none';

    const allItems = [...cfg.items, 'อื่นๆ (ระบุเอง)'];
    const filtered = allItems.filter(v => !q || v.toLowerCase().includes(q));

    if (!filtered.length) { emptyEl.style.display = 'block'; countEl.textContent = ''; return; }

    filtered.forEach(v => {
      const div = document.createElement('div');
      div.className = 'copy-option' + (v === hiddenVal.value ? ' active' : '');
      div.dataset.value = v;
      const isOther = v === 'อื่นๆ (ระบุเอง)';
      div.innerHTML = `
        <div class="copy-opt-icon upcoming">
          <i class="bi bi-${isOther ? 'pencil-square' : cfg.icon}"></i>
        </div>
        <div class="copy-opt-body">
          <div class="copy-opt-title">${highlight(v, q)}</div>
        </div>`;
      div.addEventListener('mousedown', e => { e.preventDefault(); selectOption(v); });
      listEl.appendChild(div);
      visibleOptions.push(div);
    });

    countEl.textContent = q ? `${filtered.length} รายการ` : '';
  }

  // ── Position: align dropdown exactly under its own field
  function positionDropdown() {
    const fieldRect  = field.getBoundingClientRect();
    const spaceBelow = window.innerHeight - fieldRect.bottom;
    const spaceAbove = fieldRect.top;
    if (spaceBelow >= 200 || spaceBelow >= spaceAbove) {
      dropdown.style.top    = (fieldRect.bottom + 4) + 'px';
      dropdown.style.bottom = 'auto';
    } else {
      dropdown.style.bottom = (window.innerHeight - fieldRect.top + 4) + 'px';
      dropdown.style.top    = 'auto';
    }
    dropdown.style.left  = fieldRect.left + 'px';
    dropdown.style.width = fieldRect.width + 'px';
  }

  // ── Open / close
  function openDropdown() {
    // close all other open dropdowns first
    document.querySelectorAll('.copy-dropdown.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.copy-combobox-field.open').forEach(f => {
      f.classList.remove('open'); f.setAttribute('aria-expanded','false');
    });

    field.classList.add('open');
    field.setAttribute('aria-expanded', 'true');
    dropdown.classList.add('open');
    positionDropdown();
    renderList('');
    setTimeout(() => searchEl.focus(), 50);
    focusedIndex = -1;
  }
  function closeDropdown() {
    field.classList.remove('open');
    field.setAttribute('aria-expanded', 'false');
    dropdown.classList.remove('open');
    searchEl.value = '';
    focusedIndex   = -1;
  }

  // ── Select
  function selectOption(val) {
    const isOther = (val === 'อื่นๆ (ระบุเอง)');
    hiddenVal.value = isOther ? 'อื่นๆ' : val;
    fieldText.textContent = val;
    fieldText.classList.add('has-value');
    clearBtn.style.display = '';
    if (isOther) {
      customWrap.classList.add('visible');
      customInp.setAttribute('required','true');
    } else {
      customWrap.classList.remove('visible');
      customInp.removeAttribute('required');
    }
    closeDropdown();
    field.focus();
    updateStepState();
  }

  // ── Clear
  function clearSelection() {
    hiddenVal.value = '';
    fieldText.textContent = cfg.placeholder;
    fieldText.classList.remove('has-value');
    clearBtn.style.display = 'none';
    customWrap.classList.remove('visible');
    customInp.removeAttribute('required');
    updateStepState();
  }

  // ── Keyboard nav
  function moveFocus(dir) {
    if (!visibleOptions.length) return;
    visibleOptions.forEach(o => o.classList.remove('focused'));
    focusedIndex = Math.max(0, Math.min(visibleOptions.length - 1, focusedIndex + dir));
    visibleOptions[focusedIndex].classList.add('focused');
    visibleOptions[focusedIndex].scrollIntoView({ block: 'nearest' });
  }

  // ── Events
  field.addEventListener('click', () =>
    dropdown.classList.contains('open') ? closeDropdown() : openDropdown()
  );
  field.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDropdown(); }
  });
  clearBtn.addEventListener('mousedown', e => { e.preventDefault(); e.stopPropagation(); clearSelection(); });
  searchEl.addEventListener('input', () => { renderList(searchEl.value); focusedIndex = -1; });
  searchEl.addEventListener('keydown', e => {
    if      (e.key === 'ArrowDown') { e.preventDefault(); moveFocus(1); }
    else if (e.key === 'ArrowUp')   { e.preventDefault(); moveFocus(-1); }
    else if (e.key === 'Enter') {
      e.preventDefault();
      if (focusedIndex >= 0 && visibleOptions[focusedIndex])
        selectOption(visibleOptions[focusedIndex].dataset.value);
    }
    else if (e.key === 'Escape') closeDropdown();
  });

  document.addEventListener('mousedown', e => {
    if (!document.getElementById(cfg.comboboxId).contains(e.target) &&
        !dropdown.contains(e.target)) closeDropdown();
  });
  window.addEventListener('scroll', () => { if (dropdown.classList.contains('open')) positionDropdown(); }, true);
  window.addEventListener('resize', () => { if (dropdown.classList.contains('open')) positionDropdown(); });

  // ── Restore value from PHP (postData or fillFormWithData)
  const initVal = hiddenVal.value;
  if (initVal && initVal !== 'อื่นๆ') {
    fieldText.textContent = initVal;
    fieldText.classList.add('has-value');
    clearBtn.style.display = '';
  } else if (initVal === 'อื่นๆ') {
    fieldText.textContent = 'อื่นๆ (ระบุเอง)';
    fieldText.classList.add('has-value');
    clearBtn.style.display = '';
    customWrap.classList.add('visible');
    customInp.setAttribute('required','true');
  }
}

// ── Init หน่วยงาน
makeSearchCombobox({
  comboboxId:   'deptCombobox',
  fieldId:      'deptComboboxField',
  dropdownId:   'deptDropdown',
  listId:       'deptDropdownList',
  searchId:     'deptSearchInput',
  countId:      'deptSearchCount',
  emptyId:      'deptDropdownEmpty',
  clearId:      'deptFieldClear',
  fieldTextId:  'deptFieldText',
  hiddenId:     'department',
  customWrapId: 'customDeptWrap',
  customInputId:'custom_department',
  items:        DEPT_OPTIONS,
  placeholder:  '— เลือกหรือพิมพ์ค้นหาหน่วยงาน —',
  icon:         'buildings',
  required:     true,
});

// ── Init ประธาน
makeSearchCombobox({
  comboboxId:   'chairCombobox',
  fieldId:      'chairComboboxField',
  dropdownId:   'chairDropdown',
  listId:       'chairDropdownList',
  searchId:     'chairSearchInput',
  countId:      'chairSearchCount',
  emptyId:      'chairDropdownEmpty',
  clearId:      'chairFieldClear',
  fieldTextId:  'chairFieldText',
  hiddenId:     'chairman',
  customWrapId: 'customChairmanWrap',
  customInputId:'custom_chairman',
  items:        CHAIR_OPTIONS,
  placeholder:  '— เลือกหรือพิมพ์ค้นหาประธาน —',
  icon:         'person-workspace',
  required:     false,
});

/* ═══════════════════════════════════════════════════════
   FILE UPLOAD
═══════════════════════════════════════════════════════ */
const fileDrop  = document.getElementById('fileDrop');
const fileInput = document.getElementById('file');
const fileBadge = document.getElementById('fileSelectedBadge');
const fileName  = document.getElementById('fileSelectedName');
const fileSize  = document.getElementById('fileSelectedSize');

function handleFileSelect(file) {
  if (!file) return;
  if (file.size > 15 * 1024 * 1024) {
    showToast('ไฟล์มีขนาดเกิน 15 MB', 'danger');
    return;
  }
  fileName.textContent = file.name;
  fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
  fileBadge.classList.add('show');
  updateStepState();
}

fileInput.addEventListener('change', () => handleFileSelect(fileInput.files[0]));

fileDrop.addEventListener('dragover',  e => { e.preventDefault(); fileDrop.classList.add('dragover'); });
fileDrop.addEventListener('dragleave', () => fileDrop.classList.remove('dragover'));
fileDrop.addEventListener('drop', e => {
  e.preventDefault(); fileDrop.classList.remove('dragover');
  if (e.dataTransfer.files[0]) {
    const dt = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    fileInput.files = dt.files;
    handleFileSelect(fileInput.files[0]);
  }
});

/* ═══════════════════════════════════════════════════════
   PROGRESS STEPS VISUAL UPDATE
═══════════════════════════════════════════════════════ */
function updateStepState() {
  const steps = document.querySelectorAll('.step-item');
  const checks = [
    () => document.getElementById('title').value.trim() && document.getElementById('category_id').value,
    () => document.getElementById('description').value.trim(),
    () => document.getElementById('dress').value.trim(),
    () => document.getElementById('department').value,
    () => fileInput.files.length > 0,
  ];
  checks.forEach((fn, i) => {
    steps[i].classList.toggle('done', !!fn());
    steps[i].classList.toggle('active', !fn());
  });
}

/* ═══════════════════════════════════════════════════════
   FORM VALIDATION
═══════════════════════════════════════════════════════ */
function validateForm(e) {
  const checks = [
    { el: document.getElementById('title'),       msg: 'กรุณากรอกชื่อภารกิจ' },
    { el: document.getElementById('category_id'), msg: 'กรุณาเลือกประเภทงาน' },
    { el: document.querySelector('[name="date"]'), msg: 'กรุณาเลือกวันที่' },
    { el: document.getElementById('location'),    msg: 'กรุณากรอกสถานที่' },
    { el: document.getElementById('dress'),       msg: 'กรุณาเลือกการแต่งกาย' },
    { el: document.getElementById('department'),  msg: 'กรุณาเลือกหน่วยงาน' },
  ];

  for (const c of checks) {
    if (!c.el || !c.el.value.trim()) {
      if (e) e.preventDefault();
      showToast(c.msg, 'warning');
      c.el && c.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      c.el && c.el.classList.add('is-invalid');
      setTimeout(() => c.el && c.el.classList.remove('is-invalid'), 3000);
      return false;
    }
  }

  // Check description (persons) required  
  if (!document.getElementById('description').value.trim()) {
    if (e) e.preventDefault();
    showToast('กรุณาเลือกผู้รับผิดชอบอย่างน้อย 1 คน', 'warning');
    document.getElementById('section-persons').scrollIntoView({ behavior:'smooth', block:'center' });
    return false;
  }

  // Generate file name
  if (fileInput.files.length > 0) {
    const ext = fileInput.files[0].name.split('.').pop();
    document.getElementById('file_name').value = `${Date.now()}_${Math.floor(Math.random()*10000)}.${ext}`;
  }

  const btn = document.getElementById('submitBtn');
  btn.classList.add('btn-loading');
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> กำลังบันทึก...';
  return true;
}

/* ═══════════════════════════════════════════════════════
   CLEAR FORM
═══════════════════════════════════════════════════════ */
function clearForm() {
  if (!confirm('ต้องการล้างข้อมูลทั้งหมดในฟอร์มหรือไม่?')) return;
  document.getElementById('mainForm').reset();
  document.querySelectorAll('.dress-card').forEach(o => o.classList.remove('selected'));
  document.querySelectorAll('.category-option').forEach(o => o.classList.remove('selected'));
  document.querySelectorAll('.person-card input').forEach(cb => { cb.checked = false; cb.disabled = false; });
  document.getElementById('additionalDressInfo').classList.remove('visible');
  document.getElementById('customDeptWrap').classList.remove('visible');
  document.getElementById('customChairmanWrap').classList.remove('visible');
  fileBadge.classList.remove('show');
  document.getElementById('description').value = '';
  document.getElementById('attendees').value = '';
  showToast('ล้างฟอร์มเรียบร้อยแล้ว', 'warning');
}

/* ═══════════════════════════════════════════════════════
   GO BACK
═══════════════════════════════════════════════════════ */
function goBack() {
  if (document.referrer && document.referrer.indexOf(window.location.hostname) !== -1) {
    window.history.back();
  } else {
    const mob = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    window.location.href = mob ? 'm_dashboard' : 'dashboard';
  }
}

/* ═══════════════════════════════════════════════════════
   SEARCHABLE FLOATING DROPDOWN — copy from existing event
═══════════════════════════════════════════════════════ */
(function() {
  const field     = document.getElementById('copyComboboxField');
  const dropdown  = document.getElementById('copyDropdown');
  const listEl    = document.getElementById('copyDropdownList');
  const searchEl  = document.getElementById('copySearchInput');
  const emptyEl   = document.getElementById('copyDropdownEmpty');
  const countEl   = document.getElementById('copySearchCount');
  const clearBtn  = document.getElementById('copyFieldClear');
  const fieldText = document.getElementById('copyFieldText');
  const hiddenVal = document.getElementById('copy_event');
  const selCard   = document.getElementById('copySelectedCard');
  const selTitle  = document.getElementById('copySelTitle');
  const selDate   = document.getElementById('copySelDate');
  const badge     = document.getElementById('copyEventCountBadge');

  let currentId = null;
  let focusedIndex = -1;
  let visibleOptions = [];

  // ── Badge
  const upcoming = COPY_EVENTS.filter(e => e.group === 'upcoming');
  const past      = COPY_EVENTS.filter(e => e.group === 'past');
  if (badge) badge.textContent = `จากทั้งหมด ${COPY_EVENTS.length} งาน`;

  // ── Date formatter
  function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('th-TH', {year:'numeric', month:'short', day:'numeric'});
  }

  // ── Highlight match
  function highlight(text, q) {
    if (!q) return text;
    const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${esc})`, 'gi'), '<mark>$1</mark>');
  }

  // ── Render list
  function renderList(query) {
    const q = (query || '').trim().toLowerCase();
    listEl.innerHTML = '';
    visibleOptions = [];
    emptyEl.style.display = 'none';

    function renderGroup(items, groupLabel, groupKey, icon) {
      const filtered = items.filter(ev =>
        !q || ev.title.toLowerCase().includes(q) || ev.date.includes(q)
      );
      if (!filtered.length) return;
      const gh = document.createElement('div');
      gh.className = 'copy-group-header';
      gh.innerHTML = `<i class="bi bi-${icon}"></i> ${groupLabel} (${filtered.length})`;
      listEl.appendChild(gh);
      filtered.forEach(ev => {
        const div = document.createElement('div');
        div.className = 'copy-option' + (ev.id === currentId ? ' active' : '');
        div.dataset.id = ev.id;
        div.dataset.title = ev.title;
        div.dataset.date  = ev.date;
        div.dataset.group = groupKey;
        div.innerHTML = `
          <div class="copy-opt-icon ${groupKey}">
            <i class="bi bi-${groupKey === 'upcoming' ? 'calendar-event' : 'calendar-check'}"></i>
          </div>
          <div class="copy-opt-body">
            <div class="copy-opt-title">${highlight(ev.title, q)}</div>
            <div class="copy-opt-date">${fmtDate(ev.date)}</div>
          </div>`;
        div.addEventListener('mousedown', e => { e.preventDefault(); selectOption(ev.id, ev.title, ev.date); });
        listEl.appendChild(div);
        visibleOptions.push(div);
      });
    }

    renderGroup(upcoming, 'งานที่กำลังจะมาถึง', 'upcoming', 'calendar-event-fill');
    renderGroup(past,     'งานที่ผ่านมาแล้ว',    'past',     'clock-history');

    const total = visibleOptions.length;
    countEl.textContent = q ? `${total} รายการ` : '';
    if (!total) emptyEl.style.display = 'block';
  }

  // ── Position dropdown using fixed coords from field rect
  function positionDropdown() {
    const fieldRect = field.getBoundingClientRect();
    const card      = field.closest('.form-card');
    const cardRect  = card ? card.getBoundingClientRect() : fieldRect;
    const spaceBelow = window.innerHeight - fieldRect.bottom;
    const spaceAbove = fieldRect.top;
    if (spaceBelow >= 200 || spaceBelow >= spaceAbove) {
      dropdown.style.top    = (fieldRect.bottom + 6) + 'px';
      dropdown.style.bottom = 'auto';
    } else {
      dropdown.style.bottom = (window.innerHeight - fieldRect.top + 6) + 'px';
      dropdown.style.top    = 'auto';
    }
    dropdown.style.left  = cardRect.left + 'px';
    dropdown.style.width = cardRect.width + 'px';
  }

  // ── Open/close
  function openDropdown() {
    field.classList.add('open');
    field.setAttribute('aria-expanded', 'true');
    dropdown.classList.add('open');
    positionDropdown();
    renderList('');
    setTimeout(() => searchEl.focus(), 50);
    focusedIndex = -1;
  }
  function closeDropdown() {
    field.classList.remove('open');
    field.setAttribute('aria-expanded', 'false');
    dropdown.classList.remove('open');
    searchEl.value = '';
    focusedIndex = -1;
  }

  // ── Select
  function selectOption(id, title, date) {
    currentId = id;
    hiddenVal.value = id;
    fieldText.textContent = title;
    fieldText.classList.add('has-value');
    clearBtn.style.display = '';
    selTitle.textContent = title;
    selDate.textContent   = fmtDate(date);
    selCard.style.display = 'flex';
    closeDropdown();
    field.focus();
  }

  // ── Clear
  function clearSelection() {
    currentId = null;
    hiddenVal.value = '';
    fieldText.textContent = '— เลือกหรือพิมพ์ค้นหางาน —';
    fieldText.classList.remove('has-value');
    clearBtn.style.display = 'none';
    selCard.style.display = 'none';
  }

  // ── Keyboard navigation
  function moveFocus(dir) {
    if (!visibleOptions.length) return;
    visibleOptions.forEach(o => o.classList.remove('focused'));
    focusedIndex = Math.max(0, Math.min(visibleOptions.length - 1, focusedIndex + dir));
    const el = visibleOptions[focusedIndex];
    el.classList.add('focused');
    el.scrollIntoView({ block: 'nearest' });
  }

  // Events
  field.addEventListener('click', () => {
    dropdown.classList.contains('open') ? closeDropdown() : openDropdown();
  });
  field.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openDropdown(); }
  });
  clearBtn.addEventListener('mousedown', e => { e.preventDefault(); e.stopPropagation(); clearSelection(); });
  searchEl.addEventListener('input', () => { renderList(searchEl.value); focusedIndex = -1; });
  searchEl.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); moveFocus(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); moveFocus(-1); }
    else if (e.key === 'Enter') {
      e.preventDefault();
      if (focusedIndex >= 0 && visibleOptions[focusedIndex]) {
        const el = visibleOptions[focusedIndex];
        selectOption(+el.dataset.id, el.dataset.title, el.dataset.date);
      }
    }
    else if (e.key === 'Escape') closeDropdown();
  });

  // Close on outside click
  document.addEventListener('mousedown', e => {
    if (!document.getElementById('copyCombobox').contains(e.target) &&
        !dropdown.contains(e.target)) closeDropdown();
  });

  // Reposition on scroll / resize while open
  window.addEventListener('scroll', () => { if (dropdown.classList.contains('open')) positionDropdown(); }, true);
  window.addEventListener('resize', () => { if (dropdown.classList.contains('open')) positionDropdown(); });

  // Expose getter for copy/preview buttons
  window.getCopyEventId = () => hiddenVal.value;
})();

/* ═══════════════════════════════════════════════════════
   COPY / PREVIEW EVENT
═══════════════════════════════════════════════════════ */
let selectedEventData = null;

document.getElementById('previewEventBtn').addEventListener('click', () => {
  const id = getCopyEventId();
  if (!id) { showToast('กรุณาเลือกงานที่จะดูตัวอย่าง', 'warning'); return; }

  document.getElementById('previewContent').innerHTML = `
    <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted)">
      <i class="bi bi-hourglass-split" style="font-size:2rem;display:block;margin-bottom:.75rem;animation:spin 1s linear infinite"></i>
      กำลังโหลดข้อมูล...
    </div>
    <style>@keyframes spin{to{transform:rotate(360deg)}}</style>`;
  document.getElementById('previewModal').classList.add('open');

  fetch('getEventData.php?id=' + id)
    .then(r => r.json())
    .then(data => {
      if (data.error) { document.getElementById('previewContent').innerHTML = `<p style="color:var(--accent-red)">${data.error}</p>`; return; }
      selectedEventData = data;
      const fmt = d => d ? new Date(d).toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'}) : '—';
      document.getElementById('previewContent').innerHTML = `
        <table class="preview-table">
          <tr><td>ภารกิจ</td><td><strong>${data.title||'—'}</strong></td></tr>
          <tr><td>วันที่</td><td>${fmt(data.date)}</td></tr>
          <tr><td>เวลา</td><td>${(data.time_from||'').substring(0,5)} – ${(data.time_to||'').substring(0,5)}</td></tr>
          <tr><td>สถานที่</td><td>${data.location||'—'}</td></tr>
          <tr><td>การแต่งกาย</td><td>${data.Dress||'—'}</td></tr>
          <tr><td>หน่วยงาน</td><td>${data.department||'—'}</td></tr>
          <tr><td>ประธาน</td><td>${data.chairman||'—'}</td></tr>
          <tr><td>ผู้ประสานงาน</td><td>${data.coordinator_name||'—'} ${data.coordinator_phone ? '('+data.coordinator_phone+')' : ''}</td></tr>
        </table>`;
    })
    .catch(() => showToast('เกิดข้อผิดพลาดในการดึงข้อมูล', 'danger'));
});

document.getElementById('copyDataBtn').addEventListener('click', () => {
  const id = getCopyEventId();
  if (!id) { showToast('กรุณาเลือกงานที่จะคัดลอก', 'warning'); return; }
  fetch('getEventData.php?id=' + id)
    .then(r => r.json())
    .then(data => { if (data.error) { showToast(data.error, 'danger'); return; } fillFormWithData(data); showToast('คัดลอกข้อมูลเรียบร้อยแล้ว', 'success'); })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'danger'));
});

document.getElementById('confirmCopyBtn').addEventListener('click', () => {
  if (!selectedEventData) { showToast('ไม่มีข้อมูลที่จะคัดลอก', 'danger'); return; }
  fillFormWithData(selectedEventData);
  closePreviewModal();
  showToast('คัดลอกข้อมูลเรียบร้อยแล้ว', 'success');
});

function closePreviewModal() {
  document.getElementById('previewModal').classList.remove('open');
  selectedEventData = null;
}

document.getElementById('previewModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closePreviewModal();
});

/* ═══════════════════════════════════════════════════════
   FILL FORM WITH DATA
═══════════════════════════════════════════════════════ */
function fillFormWithData(data) {
  document.getElementById('title').value = data.title || '';
  document.getElementById('location').value = data.location || '';
  document.getElementById('coordinator_name').value = data.coordinator_name || '';
  document.getElementById('coordinator_phone').value = data.coordinator_phone || '';

  // Category
  if (data.category_id) {
    document.querySelectorAll('.category-option').forEach(o => {
      o.classList.toggle('selected', o.dataset.value == data.category_id);
    });
    document.getElementById('category_id').value = data.category_id;
  }

  // Date
  if (data.date) {
    const d = new Date(data.date);
    const dd = String(d.getDate()).padStart(2,'0');
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const fp = document.querySelector('.date-picker')._flatpickr;
    if (fp) fp.setDate(`${dd}/${mm}/${d.getFullYear()}`, false, 'd/m/Y');
  }

  // Times
  const fpArr = document.querySelectorAll('.time-picker');
  if (data.time_from && fpArr[0]?._flatpickr) fpArr[0]._flatpickr.setDate(data.time_from.substring(0,5), false, 'H:i');
  if (data.time_to   && fpArr[1]?._flatpickr) fpArr[1]._flatpickr.setDate(data.time_to.substring(0,5),   false, 'H:i');

  // Dress
  if (data.Dress) {
    document.querySelectorAll('.dress-card').forEach(o => {
      const match = data.Dress.includes('ผ้าไทย') ? o.dataset.value === 'ผ้าไทย' : o.dataset.value === data.Dress;
      o.classList.toggle('selected', match);
    });
    document.getElementById('dress').value = data.Dress;
    if (data.Dress.includes('ผ้าไทย')) {
      document.getElementById('additionalDressInfo').classList.add('visible');
      const m = data.Dress.match(/\((.*?)\)/);
      if (m) document.getElementById('additionalDressInput').value = m[1];
    }
  }

  // Department — combobox hidden input
  if (data.department) {
    const deptHidden = document.getElementById('department');
    const deptText   = document.getElementById('deptFieldText');
    const deptClear  = document.getElementById('deptFieldClear');
    const customWrapD = document.getElementById('customDeptWrap');
    const customInpD  = document.getElementById('custom_department');
    const knownDept   = DEPT_OPTIONS.includes(data.department);
    if (knownDept) {
      deptHidden.value = data.department;
      deptText.textContent = data.department;
      deptText.classList.add('has-value');
      deptClear.style.display = '';
      customWrapD.classList.remove('visible');
      customInpD.removeAttribute('required');
    } else {
      deptHidden.value = 'อื่นๆ';
      deptText.textContent = 'อื่นๆ (ระบุเอง)';
      deptText.classList.add('has-value');
      deptClear.style.display = '';
      customWrapD.classList.add('visible');
      customInpD.setAttribute('required','true');
      customInpD.value = data.department;
    }
  }

  // Chairman — combobox hidden input
  if (data.chairman) {
    const chrHidden  = document.getElementById('chairman');
    const chrText    = document.getElementById('chairFieldText');
    const chrClear   = document.getElementById('chairFieldClear');
    const customWrapC = document.getElementById('customChairmanWrap');
    const customInpC  = document.getElementById('custom_chairman');
    const knownChr    = CHAIR_OPTIONS.includes(data.chairman);
    if (knownChr) {
      chrHidden.value = data.chairman;
      chrText.textContent = data.chairman;
      chrText.classList.add('has-value');
      chrClear.style.display = '';
      customWrapC.classList.remove('visible');
      customInpC.removeAttribute('required');
    } else {
      chrHidden.value = 'อื่นๆ';
      chrText.textContent = 'อื่นๆ (ระบุเอง)';
      chrText.classList.add('has-value');
      chrClear.style.display = '';
      customWrapC.classList.add('visible');
      customWrapC.setAttribute('required','true');
      customInpC.value = data.chairman;
    }
  }

  // Persons
  if (data.attendees) {
    const emails = data.attendees.split(' ');
    const names  = emails.map(em => (RESP_PERSONS.find(p => p.email === em) || {}).name).filter(Boolean);
    document.querySelectorAll('.person-card input').forEach(cb => {
      cb.checked = names.includes(cb.value);
    });
    syncPersonHiddens();
  }

  updateStepState();
}

/* ═══════════════════════════════════════════════════════
   KEYBOARD SHORTCUTS
═══════════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closePreviewModal();
});

/* ═══════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════ */
updateStepState();
</script>
</body>
</html>
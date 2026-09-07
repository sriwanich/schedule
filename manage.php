<?php
$start_time = microtime(true);
// ═══════════════════════════════════════════════════════════════════
//  ENTERPRISE MANAGE  —  ระบบจัดการตารางนัดหมาย
//  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
//  Enterprise-Level Refactor — Dark/Light Mode Edition
// ═══════════════════════════════════════════════════════════════════
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

include 'config.php';

date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ─── Thai locale helpers ───────────────────────────────────────────
$thai_months_full = [
    'Jan' => 'มกราคม',   'Feb' => 'กุมภาพันธ์', 'Mar' => 'มีนาคม',
    'Apr' => 'เมษายน',   'May' => 'พฤษภาคม',    'Jun' => 'มิถุนายน',
    'Jul' => 'กรกฎาคม',  'Aug' => 'สิงหาคม',    'Sep' => 'กันยายน',
    'Oct' => 'ตุลาคม',   'Nov' => 'พฤศจิกายน',  'Dec' => 'ธันวาคม'
];
$thai_days_full = [
    'Sunday'    => 'อาทิตย์', 'Monday'    => 'จันทร์',
    'Tuesday'   => 'อังคาร',  'Wednesday' => 'พุธ',
    'Thursday'  => 'พฤหัสบดี','Friday'    => 'ศุกร์',
    'Saturday'  => 'เสาร์'
];

// ─── POST: Update profile ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = $_SESSION['username'];
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name']  ?? '');
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $stmt = mysqli_prepare($connect, "SELECT password FROM users WHERE username=?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($user_data && password_verify($old_password, $user_data['password'])) {
        $stmt2 = mysqli_prepare($connect, "UPDATE users SET first_name=?, last_name=? WHERE username=?");
        mysqli_stmt_bind_param($stmt2, 'sss', $first_name, $last_name, $username);
        mysqli_stmt_execute($stmt2);

        if (!empty($new_password) && $new_password === $confirm_password) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt3  = mysqli_prepare($connect, "UPDATE users SET password=? WHERE username=?");
            mysqli_stmt_bind_param($stmt3, 'ss', $hashed, $username);
            mysqli_stmt_execute($stmt3);
            $_SESSION['success'] = "อัปเดตข้อมูลและรหัสผ่านสำเร็จ";
        } else {
            $_SESSION['success'] = "อัปเดตข้อมูลสำเร็จ";
        }
    } else {
        $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่";
    }
    header("Location: manage");
    exit();
}

// ─── DELETE event ──────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $stmt = mysqli_prepare($connect, "DELETE FROM events WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = "ลบการนัดหมายสำเร็จ";
    header("Location: manage");
    exit();
}

// ─── Version ───────────────────────────────────────────────────────
try {
    $v = mysqli_fetch_assoc(mysqli_query($connect, "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1"));
    $current_version = $v['version'] ?? '1.0.0';
} catch (Exception $e) { $current_version = '1.0.0'; }

// ─── Current user ──────────────────────────────────────────────────
$username  = $_SESSION['username'];
$stmt      = mysqli_prepare($connect, "SELECT first_name, last_name, role FROM users WHERE username=?");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$user_data  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$first_name = $user_data['first_name'] ?? '';
$last_name  = $user_data['last_name']  ?? '';
$role       = $user_data['role']        ?? 'user';

// ─── Device detection ──────────────────────────────────────────────
$is_mobile   = (bool) preg_match("/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i", $_SERVER['HTTP_USER_AGENT']);
$previous_page = $_SERVER['HTTP_REFERER'] ?? ($is_mobile ? 'mobile' : 'desktop');

// ─── Date stats ────────────────────────────────────────────────────
$current_date = date('Y-m-d');
$current_day  = date('l');
$thai_day_full = $thai_days_full[$current_day];
$current_date_thai = "วัน{$thai_day_full}ที่ " . date('j') . ' ' .
                     $thai_months_full[date('M')] . ' พ.ศ. ' . (date('Y') + 543);
$current_datetime_for_js = date('Y-m-d H:i:s');

try {
    $s1 = mysqli_prepare($connect,
        "SELECT COUNT(*) as c FROM events WHERE date=? AND (time_to>CURTIME() OR time_to IS NULL) AND status NOT IN ('ยกเลิก','เลื่อน')");
    mysqli_stmt_bind_param($s1, 's', $current_date);
    mysqli_stmt_execute($s1);
    $total_events = (mysqli_fetch_assoc(mysqli_stmt_get_result($s1)))['c'] ?? 0;

    $s2 = mysqli_prepare($connect,
        "SELECT COUNT(*) as c FROM events WHERE date=? AND time_to<=CURTIME() AND status NOT IN ('ยกเลิก','เลื่อน')");
    mysqli_stmt_bind_param($s2, 's', $current_date);
    mysqli_stmt_execute($s2);
    $completed_events = (mysqli_fetch_assoc(mysqli_stmt_get_result($s2)))['c'] ?? 0;
} catch (Exception $e) { $total_events = 0; $completed_events = 0; }

// ─── Events data ───────────────────────────────────────────────────
$dbquery = mysqli_query($connect,
    "SELECT * FROM events
     WHERE (date = '0000-00-00'
         OR (CONCAT(date,' ',time_from) <= NOW() AND CONCAT(date,' ',time_to) >= NOW())
         OR CONCAT(date,' ',time_from) > NOW())
     ORDER BY
       CASE WHEN date = '0000-00-00' THEN 1 ELSE 0 END,
       date ASC, time_from ASC");

$all_events = [];
while ($row = mysqli_fetch_assoc($dbquery)) $all_events[] = $row;
$total_rows = count($all_events);
$generation_time = microtime(true) - $start_time;
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>จัดการตารางนัดหมาย — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</title>
<meta name="author"    content="นายณัฐพันธุ์ ศรีวนิช ฝ่ายป้องกันและปฏิบัติการ">
<meta name="copyright" content="© 2024 นายณัฐพันธุ์ ศรีวนิช">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════════════
   DESIGN TOKENS — Light & Dark  (Navy/Gold — same as index_desktop)
   Font: Anuphan throughout
══════════════════════════════════════════════════════ */
:root {
  --brand-50:  #e8f0fd; --brand-100: #c1d5f8; --brand-200: #93b8f4;
  --brand-300: #5e98ef; --brand-400: #3a80e8; --brand-500: #1e6fd9;
  --brand-600: #1760c2; --brand-700: #0f4fa3; --brand-800: #0a3a7a;
  --brand-900: #0a1628;

  --accent-gold:   #e8a020;
  --accent-red:    #e05252;
  --accent-green:  #3ec97e;
  --accent-orange: #f07030;
  --accent-sky:    #38c2ff;

  --bg-base:     #e8f0fd;
  --bg-surface:  rgba(255,255,255,0.82);
  --bg-elevated: rgba(255,255,255,0.95);
  --bg-glass:    rgba(255,255,255,0.60);
  --border:      rgba(30,111,217,0.10);
  --border-strong: rgba(30,111,217,0.22);

  --text-primary:   #0a1628;
  --text-secondary: #1e6fd9;
  --text-muted:     #5e98ef;
  --text-inverse:   #ffffff;

  --shadow-sm:  0 1px 4px rgba(30,111,217,.07), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md:  0 4px 16px rgba(30,111,217,.14), 0 2px 6px rgba(0,0,0,.06);
  --shadow-lg:  0 10px 36px rgba(30,111,217,.18), 0 4px 12px rgba(0,0,0,.08);
  --shadow-xl:  0 25px 60px rgba(30,111,217,.24);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.35);

  --r-sm: 8px; --r-md: 14px; --r-lg: 20px; --r-xl: 28px; --r-pill: 999px;
  --ease-out: cubic-bezier(0.16,1,0.3,1);
  --ease-spring: cubic-bezier(0.34,1.56,0.64,1);

  --header-bg: linear-gradient(135deg,#0a1628 0%,#0d2340 50%,#0f2e54 100%);
  --page-bg: linear-gradient(145deg,#c8daf5 0%,#dce9fc 40%,#e8f0fd 100%);
}

[data-theme="dark"] {
  --bg-base:     #0a1628;
  --bg-surface:  rgba(13,35,64,0.90);
  --bg-elevated: rgba(15,46,84,0.85);
  --bg-glass:    rgba(10,22,40,0.80);
  --border:      rgba(255,255,255,0.09);
  --border-strong: rgba(255,255,255,0.15);

  --text-primary:   #f0f4ff;
  --text-secondary: #38c2ff;
  --text-muted:     rgba(240,244,255,0.55);
  --text-inverse:   #ffffff;

  --shadow-sm:  0 1px 4px rgba(0,0,0,.40);
  --shadow-md:  0 4px 16px rgba(0,0,0,.55);
  --shadow-lg:  0 10px 36px rgba(0,0,0,.65);
  --shadow-xl:  0 25px 60px rgba(0,0,0,.80);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.45);

  --header-bg: linear-gradient(135deg,#050d1a 0%,#08192e 50%,#0a2040 100%);
  --page-bg: radial-gradient(ellipse 80% 60% at 20% 0%, rgba(30,111,217,0.22) 0%,transparent 65%),
             radial-gradient(ellipse 60% 50% at 80% 100%, rgba(232,160,32,0.14) 0%,transparent 60%),
             radial-gradient(ellipse 50% 80% at 50% 50%, #0a1628 0%,#1a0a2e 100%);
}

/* ── Reset & Base ─────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Anuphan',sans-serif;
  font-size:15px;line-height:1.6;
  background:var(--page-bg);background-attachment:fixed;
  color:var(--text-primary);
  min-height:100vh;display:flex;flex-direction:column;
  transition:background .4s var(--ease-out),color .4s var(--ease-out);
}

/* ── Header ───────────────────────────────────────── */
.app-header{
  background:var(--header-bg);color:#fff;
  position:sticky;top:0;z-index:200;
  box-shadow:0 4px 24px rgba(0,0,0,.25);
  border-bottom:1px solid rgba(255,255,255,.08);
}
.header-inner{
  display:grid;
  grid-template-columns:1fr auto 1fr;
  align-items:center;gap:1rem;padding:.875rem 2rem;
}
.header-brand{display:flex;align-items:center;gap:1rem}
.header-logo{
  width:52px;height:52px;object-fit:contain;
  filter:drop-shadow(0 2px 8px rgba(0,0,0,.3));
  transition:transform .3s var(--ease-spring);flex-shrink:0;
}
.header-logo:hover{transform:scale(1.08) rotate(-3deg)}
.header-text h1{font-size:1.15rem;font-weight:600;letter-spacing:.01em;line-height:1.2;margin:0}
.header-text p{font-size:.78rem;opacity:.78;margin:0;letter-spacing:.02em}
.header-center{text-align:center}
.header-date{font-size:.82rem;opacity:.85;white-space:nowrap}
.header-clock{
  font-size:2rem;font-weight:300;letter-spacing:.06em;
  line-height:1;font-variant-numeric:tabular-nums
}
.header-actions{display:flex;align-items:center;justify-content:flex-end;gap:.75rem}

/* Theme toggle */
.theme-toggle{
  width:44px;height:44px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
  color:#fff;display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1.15rem;
  transition:all .3s var(--ease-out);backdrop-filter:blur(8px);
}
.theme-toggle:hover{background:rgba(255,255,255,.22);transform:scale(1.08)}

/* Version chip */
.version-chip{
  font-size:.7rem;padding:3px 10px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
  color:rgba(255,255,255,.8);cursor:pointer;transition:all .25s;
  white-space:nowrap;backdrop-filter:blur(4px);
}
.version-chip:hover{background:rgba(255,255,255,.22);color:#fff}

/* User pill */
.user-pill{
  display:flex;align-items:center;gap:.5rem;
  padding:6px 14px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);
  cursor:pointer;transition:all .25s var(--ease-out);
  font-size:.82rem;color:#fff;
}
.user-pill:hover{background:rgba(255,255,255,.20);transform:translateY(-1px)}
.user-pill .role-tag{
  padding:2px 8px;border-radius:var(--r-pill);font-size:.68rem;font-weight:600;
}
.role-tag.admin{background:rgba(224,82,82,.35);color:#fca5a5}
.role-tag.user{background:rgba(232,160,32,.30);color:#fde68a}

/* ── Toolbar ──────────────────────────────────────── */
.app-toolbar{
  background:var(--bg-surface);backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border-strong);
  padding:.75rem 2rem;
  display:flex;align-items:center;justify-content:space-between;gap:1rem;
  box-shadow:var(--shadow-sm);transition:background .4s;
  flex-wrap:wrap;
}
.toolbar-left,.toolbar-right{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.toolbar-center{display:flex;flex-direction:column;align-items:center}
.today-label{font-size:.9rem;font-weight:500;color:var(--text-primary)}

/* Status pill */
.status-pill{
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 14px;border-radius:var(--r-pill);
  font-size:.75rem;font-weight:600;letter-spacing:.02em;
}
.status-pill.has-events  {background:#e0e7ff;color:#3730a3}
.status-pill.done-events {background:#d1fae5;color:#065f46}
.status-pill.no-events   {background:#fee2e2;color:#991b1b}
[data-theme="dark"] .status-pill.has-events  {background:rgba(67,56,202,.25);color:#a5b4fc}
[data-theme="dark"] .status-pill.done-events {background:rgba(6,95,70,.25);color:#6ee7b7}
[data-theme="dark"] .status-pill.no-events   {background:rgba(153,27,27,.25);color:#fca5a5}

/* Toolbar buttons */
.tb-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:7px 16px;border-radius:var(--r-sm);
  font-size:.82rem;font-weight:500;font-family:'Anuphan',sans-serif;
  cursor:pointer;border:none;
  transition:all .25s var(--ease-out);text-decoration:none;white-space:nowrap;
}
.tb-btn-primary{background:var(--brand-500);color:#fff;box-shadow:var(--shadow-brand)}
.tb-btn-primary:hover{background:var(--brand-600);color:#fff;transform:translateY(-2px)}
.tb-btn-danger{background:var(--accent-red);color:#fff;box-shadow:0 4px 14px rgba(224,82,82,.35)}
.tb-btn-danger:hover{background:#c83c3c;color:#fff;transform:translateY(-2px)}
.tb-btn-gold{background:var(--accent-gold);color:#fff;box-shadow:0 4px 14px rgba(232,160,32,.35)}
.tb-btn-gold:hover{background:#d08010;color:#fff;transform:translateY(-2px)}
.tb-btn-ghost{
  background:transparent;color:var(--text-secondary);
  border:1px solid var(--border-strong);
}
.tb-btn-ghost:hover{background:var(--bg-elevated);color:var(--text-primary);transform:translateY(-1px)}

/* ── Main ─────────────────────────────────────────── */
.app-main{flex:1;padding:1.75rem 2rem;max-width:100%}

/* ── Page header ──────────────────────────────────── */
.page-header-row{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;
}
.page-title{
  font-size:1.5rem;font-weight:700;color:var(--text-primary);
  display:flex;align-items:center;gap:.6rem;
}
.page-title i{color:var(--brand-500)}

/* ── Stats row ────────────────────────────────────── */
.stats-row{
  display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;
  margin-bottom:1.75rem;
}
.stat-card{
  background:var(--bg-surface);backdrop-filter:blur(16px);
  border-radius:var(--r-lg);border:1px solid var(--border-strong);
  padding:1.25rem 1.5rem;box-shadow:var(--shadow-sm);
  display:flex;align-items:center;gap:1rem;
  transition:all .3s var(--ease-out);animation:statReveal .5s var(--ease-out) both;
}
.stat-card:hover{box-shadow:var(--shadow-md);transform:translateY(-3px)}
@keyframes statReveal{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.10s}
.stat-card:nth-child(3){animation-delay:.15s}
.stat-icon{
  width:52px;height:52px;border-radius:var(--r-md);
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;flex-shrink:0;
}
.stat-icon.blue  {background:rgba(30,111,217,.15);color:var(--brand-500)}
.stat-icon.green {background:rgba(62,201,126,.15);color:var(--accent-green)}
.stat-icon.gold  {background:rgba(232,160,32,.15);color:var(--accent-gold)}
[data-theme="dark"] .stat-icon.blue  {background:rgba(30,111,217,.25)}
[data-theme="dark"] .stat-icon.green {background:rgba(62,201,126,.20)}
[data-theme="dark"] .stat-icon.gold  {background:rgba(232,160,32,.20)}
.stat-info{}
.stat-value{font-size:2rem;font-weight:700;line-height:1;color:var(--text-primary)}
.stat-label{font-size:.8rem;color:var(--text-muted);margin-top:2px}

/* ── Search / filter bar ──────────────────────────── */
.search-bar{
  background:var(--bg-surface);backdrop-filter:blur(16px);
  border-radius:var(--r-md);border:1px solid var(--border-strong);
  padding:1rem 1.25rem;box-shadow:var(--shadow-sm);
  margin-bottom:1.25rem;
  display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;
}
.search-input-wrap{flex:1;min-width:220px;position:relative}
.search-input-wrap i{
  position:absolute;left:.85rem;top:50%;transform:translateY(-50%);
  color:var(--text-muted);font-size:.95rem;
}
.search-input{
  width:100%;padding:.55rem .85rem .55rem 2.4rem;
  border-radius:var(--r-sm);border:1px solid var(--border-strong);
  background:var(--bg-elevated);color:var(--text-primary);
  font-family:'Anuphan',sans-serif;font-size:.88rem;
  transition:all .25s;outline:none;
}
.search-input:focus{border-color:var(--brand-500);box-shadow:0 0 0 3px rgba(30,111,217,.15)}
.filter-select{
  padding:.55rem .85rem;border-radius:var(--r-sm);
  border:1px solid var(--border-strong);
  background:var(--bg-elevated);color:var(--text-primary);
  font-family:'Anuphan',sans-serif;font-size:.88rem;
  cursor:pointer;outline:none;transition:all .25s;
}
.filter-select:focus{border-color:var(--brand-500)}
.filter-label{font-size:.82rem;color:var(--text-muted);white-space:nowrap}
.row-count-badge{
  padding:3px 12px;border-radius:var(--r-pill);
  background:var(--brand-50);color:var(--brand-700);
  font-size:.78rem;font-weight:600;
}
[data-theme="dark"] .row-count-badge{background:rgba(30,111,217,.20);color:var(--brand-200)}

/* ── Table card ───────────────────────────────────── */
.table-card{
  background:var(--bg-surface);backdrop-filter:blur(16px);
  border-radius:var(--r-lg);border:1px solid var(--border-strong);
  overflow:hidden;box-shadow:var(--shadow-md);
  animation:tableReveal .5s var(--ease-out) .2s both;
}
@keyframes tableReveal{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.table-scroll{overflow-x:auto}

/* Table */
.manage-table{
  width:100%;border-collapse:collapse;
  font-family:'Anuphan',sans-serif;font-size:.88rem;
}
.manage-table thead{background:var(--header-bg)}
.manage-table thead th{
  color:#fff;padding:14px 12px;font-weight:600;
  font-size:.82rem;letter-spacing:.04em;white-space:nowrap;
  border:none;text-align:center;
}
.manage-table thead th:nth-child(3){text-align:left}
.manage-table thead th:nth-child(4){text-align:left}
.manage-table tbody tr{
  border-bottom:1px solid var(--border);
  transition:background .15s,transform .15s;
}
.manage-table tbody tr:last-child{border-bottom:none}
.manage-table tbody tr:hover td{
  background:rgba(30,111,217,.05);
}
[data-theme="dark"] .manage-table tbody tr:hover td{
  background:rgba(255,255,255,.04);
}
.manage-table tbody td{
  padding:12px 12px;vertical-align:middle;color:var(--text-primary);
  word-wrap:break-word;white-space:normal;
}
.manage-table tbody td:nth-child(1){ text-align:center;width:13%;white-space:nowrap }
.manage-table tbody td:nth-child(2){ text-align:center;width:8%; white-space:nowrap }
.manage-table tbody td:nth-child(3){ text-align:left;width:35% }
.manage-table tbody td:nth-child(4){ text-align:left;width:18% }
.manage-table tbody td:nth-child(5){ text-align:center;width:12% }
.manage-table tbody td:nth-child(6){ text-align:center;width:14% }

/* Status rows */
.row-cancelled td{
  text-decoration:line-through;
  background:rgba(224,82,82,.06) !important;
  color:var(--text-muted) !important;
}
.row-postponed td{
  text-decoration:line-through;
  background:rgba(232,160,32,.06) !important;
  color:var(--text-muted) !important;
}
[data-theme="dark"] .row-cancelled td{background:rgba(224,82,82,.12) !important}
[data-theme="dark"] .row-postponed td{background:rgba(232,160,32,.12) !important}
.no-date-tag{color:var(--text-muted);font-style:italic;font-size:.82rem}

/* Status badge inline */
.status-inline{
  display:inline-flex;align-items:center;gap:4px;
  padding:2px 8px;border-radius:var(--r-pill);font-size:.72rem;font-weight:600;
  margin-left:.4rem;vertical-align:middle;
}
.status-inline.postponed{background:rgba(232,160,32,.18);color:#a06000}
.status-inline.cancelled{background:rgba(224,82,82,.18);color:#b02020}
[data-theme="dark"] .status-inline.postponed{background:rgba(232,160,32,.28);color:#fde68a}
[data-theme="dark"] .status-inline.cancelled{background:rgba(224,82,82,.28);color:#fca5a5}

/* Creator chip */
.creator-chip{
  display:inline-flex;align-items:center;gap:.35rem;
  padding:2px 10px;border-radius:var(--r-pill);
  background:var(--brand-50);color:var(--brand-700);
  font-size:.78rem;font-weight:500;
}
[data-theme="dark"] .creator-chip{background:rgba(30,111,217,.18);color:var(--brand-200)}

/* Action btns */
.action-group{display:flex;gap:.4rem;justify-content:center;flex-wrap:nowrap}
.act-btn{
  display:inline-flex;align-items:center;gap:4px;
  padding:5px 12px;border-radius:var(--r-sm);
  font-size:.78rem;font-weight:500;font-family:'Anuphan',sans-serif;
  cursor:pointer;border:none;text-decoration:none;
  transition:all .2s var(--ease-out);white-space:nowrap;
}
.act-btn-edit{background:rgba(232,160,32,.18);color:#a06000}
.act-btn-edit:hover{background:var(--accent-gold);color:#fff;transform:translateY(-1px)}
.act-btn-del{background:rgba(224,82,82,.14);color:#b02020}
.act-btn-del:hover{background:var(--accent-red);color:#fff;transform:translateY(-1px)}
[data-theme="dark"] .act-btn-edit{background:rgba(232,160,32,.22);color:#fde68a}
[data-theme="dark"] .act-btn-edit:hover{background:var(--accent-gold);color:#fff}
[data-theme="dark"] .act-btn-del{background:rgba(224,82,82,.22);color:#fca5a5}
[data-theme="dark"] .act-btn-del:hover{background:var(--accent-red);color:#fff}

/* Empty state */
.empty-state{
  padding:3rem;text-align:center;color:var(--text-muted);
}
.empty-state i{font-size:3rem;opacity:.4;display:block;margin-bottom:.75rem}
.empty-state p{font-size:.95rem}

/* ── Footer ───────────────────────────────────────── */
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


/* ── Toast ────────────────────────────────────────── */
.toast-stack{
  position:fixed;top:5rem;left:50%;transform:translateX(-50%);
  z-index:2000;display:flex;flex-direction:column;gap:.5rem;align-items:center;
}
.toast-item{
  padding:.75rem 1.5rem;border-radius:var(--r-md);
  font-family:'Anuphan',sans-serif;font-size:.9rem;font-weight:500;
  box-shadow:var(--shadow-lg);color:#fff;
  animation:toastIn .35s var(--ease-spring) both;
}
.toast-item.success{background:linear-gradient(135deg,#059669,#10b981)}
.toast-item.error  {background:linear-gradient(135deg,#dc2626,#ef4444)}
@keyframes toastIn{from{opacity:0;transform:translateY(-12px) scale(.95)}to{opacity:1;transform:none}}

/* ── Profile Modal (custom, no Bootstrap modal) ───── */
.modal-backdrop-custom{
  position:fixed;inset:0;z-index:500;
  background:rgba(5,10,20,.70);backdrop-filter:blur(8px);
  display:none;align-items:center;justify-content:center;
}
.modal-backdrop-custom.open{display:flex}
.modal-panel{
  background:var(--bg-elevated);border:1px solid var(--border-strong);
  border-radius:var(--r-xl);box-shadow:var(--shadow-xl);
  width:min(480px,96vw);max-height:90vh;overflow-y:auto;
  animation:panelSlideIn .4s var(--ease-spring) both;
}
@keyframes panelSlideIn{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:none}}
.modal-header-custom{
  background:var(--header-bg);color:#fff;
  padding:1.25rem 1.5rem;border-radius:var(--r-xl) var(--r-xl) 0 0;
  display:flex;align-items:center;justify-content:space-between;
}
.modal-header-custom h5{font-size:1rem;font-weight:600;margin:0;display:flex;align-items:center;gap:.5rem}
.modal-close-btn{
  width:34px;height:34px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.15);border:none;color:#fff;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1rem;transition:all .2s;
}
.modal-close-btn:hover{background:rgba(255,255,255,.28)}
.modal-body-custom{padding:1.5rem}
.form-group{margin-bottom:1.1rem}
.form-label-custom{
  display:block;font-size:.82rem;font-weight:600;
  color:var(--text-secondary);margin-bottom:.35rem;letter-spacing:.03em;
}
.form-control-custom{
  width:100%;padding:.6rem 1rem;
  border-radius:var(--r-sm);border:1px solid var(--border-strong);
  background:var(--bg-surface);color:var(--text-primary);
  font-family:'Anuphan',sans-serif;font-size:.9rem;
  transition:all .25s;outline:none;
}
.form-control-custom:focus{
  border-color:var(--brand-500);
  box-shadow:0 0 0 3px rgba(30,111,217,.15);
}
.form-hint{font-size:.75rem;color:var(--text-muted);margin-top:.3rem}
.modal-divider{height:1px;background:var(--border-strong);margin:1rem 0}
.modal-footer-custom{
  padding:1rem 1.5rem;border-top:1px solid var(--border);
  display:flex;gap:.75rem;justify-content:flex-end;
}

/* Password strength indicator */
.pw-strength-bar{height:3px;border-radius:var(--r-pill);background:var(--border-strong);margin-top:.4rem;overflow:hidden}
.pw-strength-fill{height:100%;border-radius:var(--r-pill);transition:width .3s,background .3s}

/* Delete Confirm Modal */
.del-confirm-panel{width:min(400px,96vw)}
.del-confirm-icon{
  width:64px;height:64px;border-radius:var(--r-pill);
  background:rgba(224,82,82,.15);color:var(--accent-red);
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;margin:0 auto 1rem;
}

/* Responsive */
@media(max-width:900px){
  .header-inner{grid-template-columns:1fr 1fr;padding:.75rem 1rem}
  .header-center{display:none}
  .stats-row{grid-template-columns:1fr 1fr}
  .app-main{padding:1rem}
  .app-toolbar{padding:.6rem 1rem}
  .header-text p{display:none}
}
@media(max-width:600px){
  .stats-row{grid-template-columns:1fr}
  .header-inner{grid-template-columns:1fr auto}
  .version-chip{display:none}
  .manage-table tbody td{padding:8px 6px;font-size:.82rem}
}
</style>
<script src="/theme.js"></script>
</head>
<body>

<!-- Toast stack -->
<div class="toast-stack" id="toastStack"></div>

<!-- ══ HEADER ═══════════════════════════════════════════ -->
<header class="app-header">
  <div class="header-inner">
    <!-- Brand -->
    <div class="header-brand">
      <img src="/images/logo.png" alt="Logo" class="header-logo">
      <div class="header-text">
        <h1>ระบบปฏิทินและนัดหมาย</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
      </div>
    </div>
    <!-- Clock -->
    <div class="header-center">
      <div class="header-date" id="liveDate"><?= htmlspecialchars($current_date_thai) ?></div>
      <div class="header-clock" id="liveClock">--:--:--</div>
    </div>
    <!-- Actions -->
    <div class="header-actions">
      <span class="version-chip" onclick="window.location.href='changelogs'" title="ดูบันทึกการเปลี่ยนแปลง">
        v<?= htmlspecialchars($current_version) ?>
      </span>
      <!-- User pill -->
      <button class="user-pill" id="userPillBtn" title="แก้ไขข้อมูลผู้ใช้">
        <i class="bi bi-person-circle"></i>
        <span><?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?></span>
        <span class="role-tag <?= $role === 'admin' ? 'admin' : 'user' ?>">
          <?= $role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน' ?>
        </span>
      </button>
      <!-- Theme toggle -->
      <button class="theme-toggle" id="themeToggle" title="สลับธีม">
        <i class="bi bi-sun-fill" id="themeIcon"></i>
      </button>
      <span id="autoModeBadge">
        <i class="bi bi-brightness-auto"></i> Auto
      </span>
    </div>
  </div>
</header>

<!-- ══ TOOLBAR ═══════════════════════════════════════════ -->
<div class="app-toolbar">
  <div class="toolbar-left">
    <a href="<?= htmlspecialchars($previous_page) ?>" class="tb-btn tb-btn-ghost">
      <i class="bi bi-arrow-left"></i> ย้อนกลับ
    </a>
    <a href="old_events" class="tb-btn tb-btn-gold">
      <i class="bi bi-clock-history"></i> ประวัตินัดหมาย
    </a>
  </div>
  <div class="toolbar-center">
    <div class="today-label">จัดการการนัดหมาย</div>
    <div style="margin-top:3px">
      <?php if ($total_events > 0): ?>
        <span class="status-pill has-events"><i class="bi bi-list-task"></i> มีวาระงาน <?= $total_events ?> รายการ</span>
      <?php elseif ($completed_events > 0): ?>
        <span class="status-pill done-events"><i class="bi bi-check-circle"></i> วาระงานวันนี้เสร็จสิ้นแล้ว</span>
      <?php else: ?>
        <span class="status-pill no-events"><i class="bi bi-calendar-x"></i> ไม่มีวาระงานวันนี้</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="toolbar-right">
    <a href="addEvent" class="tb-btn tb-btn-primary">
      <i class="bi bi-plus-circle-fill"></i> เพิ่มนัดหมาย
    </a>
    <a href="logout" class="tb-btn tb-btn-danger">
      <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
    </a>
  </div>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════ -->
<main class="app-main">

  <!-- Page title -->
  <div class="page-header-row">
    <h2 class="page-title"><i class="bi bi-speedometer2"></i> ตารางการนัดหมายทั้งหมด</h2>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-calendar-event-fill"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $total_rows ?></div>
        <div class="stat-label">รายการนัดหมายทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-list-check"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $total_events ?></div>
        <div class="stat-label">วาระงานวันนี้ที่เหลืออยู่</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon gold"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $completed_events ?></div>
        <div class="stat-label">วาระที่เสร็จสิ้นวันนี้</div>
      </div>
    </div>
  </div>

  <!-- Search bar -->
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="bi bi-search"></i>
      <input type="text" class="search-input" id="searchInput" placeholder="ค้นหาเรื่อง, สถานที่, ผู้บันทึก...">
    </div>
    <span class="filter-label">สถานะ:</span>
    <select class="filter-select" id="statusFilter">
      <option value="">ทั้งหมด</option>
      <option value="active">ปกติ</option>
      <option value="เลื่อน">เลื่อน</option>
      <option value="ยกเลิก">ยกเลิก</option>
    </select>
    <span class="row-count-badge" id="rowCountBadge"><?= $total_rows ?> รายการ</span>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-scroll">
      <table class="manage-table" id="manageTable">
        <thead>
          <tr>
            <th><i class="bi bi-calendar-event"></i> วันที่</th>
            <th><i class="bi bi-clock"></i> เวลา</th>
            <th><i class="bi bi-card-heading"></i> เรื่อง / หัวข้อ</th>
            <th><i class="bi bi-geo-alt"></i> สถานที่</th>
            <th><i class="bi bi-person"></i> ผู้บันทึก</th>
            <th><i class="bi bi-gear-fill"></i> จัดการ</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php if (empty($all_events)): ?>
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <i class="bi bi-calendar2-x"></i>
                  <p>ไม่พบการนัดหมายในขณะนี้</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($all_events as $result):
              // Format date
              if ($result['date'] === '0000-00-00' || empty($result['date'])) {
                $formatted_date = '<span class="no-date-tag"><i class="bi bi-question-circle"></i> ยังไม่มีกำหนด</span>';
                $formatted_time = '—';
                $sort_date = '9999-12-31';
              } else {
                $d = strtotime($result['date']);
                $m = date('M', $d);
                $y = (int)date('Y', $d) + 543;
                $formatted_date = date('j', $d) . ' ' . $thai_months_full[$m] . ' ' . $y;
                $formatted_time = date('H:i', strtotime($result['time_from'])) . ' น.';
                $sort_date = $result['date'];
              }
              // Row class
              $row_class = '';
              if ($result['status'] === 'ยกเลิก') $row_class = 'row-cancelled';
              elseif ($result['status'] === 'เลื่อน')   $row_class = 'row-postponed';
              // Status inline badge
              $status_html = '';
              if ($result['status'] === 'เลื่อน')   $status_html = '<span class="status-inline postponed"><i class="bi bi-arrow-right-circle"></i>เลื่อน</span>';
              elseif ($result['status'] === 'ยกเลิก') $status_html = '<span class="status-inline cancelled"><i class="bi bi-x-circle"></i>ยกเลิก</span>';
            ?>
            <tr class="<?= $row_class ?>"
                data-title="<?= htmlspecialchars(strtolower($result['title'])) ?>"
                data-location="<?= htmlspecialchars(strtolower($result['location'] ?? '')) ?>"
                data-creator="<?= htmlspecialchars(strtolower($result['created_by'] ?? '')) ?>"
                data-status="<?= htmlspecialchars($result['status'] ?? '') ?>"
                data-sort="<?= $sort_date ?>">
              <td><?= $formatted_date ?></td>
              <td><?= $formatted_time ?></td>
              <td>
                <?= htmlspecialchars($result['title']) ?>
                <?= $status_html ?>
              </td>
              <td><?= htmlspecialchars($result['location'] ?? '') ?></td>
              <td>
                <span class="creator-chip">
                  <i class="bi bi-person-fill"></i>
                  <?= htmlspecialchars($result['created_by'] ?? '') ?>
                </span>
              </td>
              <td>
                <div class="action-group">
                  <a href="editEvent?id=<?= (int)$result['id'] ?>" class="act-btn act-btn-edit" title="แก้ไข">
                    <i class="bi bi-pencil-fill"></i> แก้ไข
                  </a>
                  <button class="act-btn act-btn-del" title="ลบ"
                    onclick="confirmDelete(<?= (int)$result['id'] ?>, '<?= addslashes(htmlspecialchars($result['title'])) ?>')">
                    <i class="bi bi-trash3-fill"></i> ลบ
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<!-- ══════════════════════════════════════════════ FOOTER ══ -->
<footer class="app-footer">
  <strong>© <?= date('Y') ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</strong><br>
  <span class="perf">
    <i class="bi bi-lightning-charge-fill"></i>
    สร้างหน้าในเวลา <?= number_format($generation_time, 4) ?> วินาที
    &nbsp;|&nbsp; PHP <?= phpversion() ?>
  </span>
</footer>


<!-- ══ PROFILE MODAL ════════════════════════════════════ -->
<div class="modal-backdrop-custom" id="profileModal">
  <div class="modal-panel">
    <div class="modal-header-custom">
      <h5><i class="bi bi-person-gear"></i> แก้ไขข้อมูลผู้ใช้</h5>
      <button class="modal-close-btn" onclick="closeProfileModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body-custom">
      <form id="profileForm" method="POST" action="">
        <div class="form-group">
          <label class="form-label-custom">ชื่อ</label>
          <input type="text" class="form-control-custom" name="first_name"
                 value="<?= htmlspecialchars($first_name) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label-custom">นามสกุล</label>
          <input type="text" class="form-control-custom" name="last_name"
                 value="<?= htmlspecialchars($last_name) ?>" required>
        </div>
        <div class="modal-divider"></div>
        <div class="form-group">
          <label class="form-label-custom">รหัสผ่านปัจจุบัน <span style="color:var(--accent-red)">*</span></label>
          <input type="password" class="form-control-custom" name="old_password" id="oldPw" required>
          <div class="form-hint">จำเป็นต้องกรอกเพื่อยืนยันตัวตน</div>
        </div>
        <div class="form-group">
          <label class="form-label-custom">รหัสผ่านใหม่</label>
          <input type="password" class="form-control-custom" name="new_password" id="newPw" oninput="checkStrength(this.value)">
          <div class="pw-strength-bar"><div class="pw-strength-fill" id="pwStrengthFill" style="width:0%"></div></div>
          <div class="form-hint">เว้นว่างหากไม่ต้องการเปลี่ยนรหัสผ่าน</div>
        </div>
        <div class="form-group">
          <label class="form-label-custom">ยืนยันรหัสผ่านใหม่</label>
          <input type="password" class="form-control-custom" name="confirm_password" id="confirmPw">
        </div>
      </form>
    </div>
    <div class="modal-footer-custom">
      <button type="button" class="tb-btn tb-btn-ghost" onclick="closeProfileModal()">
        <i class="bi bi-x-circle"></i> ยกเลิก
      </button>
      <button type="submit" form="profileForm" class="tb-btn tb-btn-primary">
        <i class="bi bi-check-circle-fill"></i> บันทึกการเปลี่ยนแปลง
      </button>
    </div>
  </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ════════════════════════════ -->
<div class="modal-backdrop-custom" id="deleteModal">
  <div class="modal-panel del-confirm-panel">
    <div class="modal-header-custom" style="background:linear-gradient(135deg,#7f1d1d,#991b1b)">
      <h5><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันการลบ</h5>
      <button class="modal-close-btn" onclick="closeDeleteModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body-custom" style="text-align:center;padding:2rem 1.5rem">
      <div class="del-confirm-icon"><i class="bi bi-trash3-fill"></i></div>
      <p style="font-weight:600;font-size:1rem;color:var(--text-primary)">คุณแน่ใจหรือไม่?</p>
      <p style="color:var(--text-muted);font-size:.88rem;margin-top:.5rem">
        จะลบนัดหมาย: <strong id="delTitle" style="color:var(--accent-red)"></strong>
      </p>
      <p style="font-size:.8rem;color:var(--text-muted);margin-top:.5rem">การกระทำนี้ไม่สามารถย้อนกลับได้</p>
    </div>
    <div class="modal-footer-custom" style="justify-content:center">
      <button class="tb-btn tb-btn-ghost" onclick="closeDeleteModal()">
        <i class="bi bi-x-circle"></i> ยกเลิก
      </button>
      <a id="delConfirmLink" href="#" class="tb-btn tb-btn-danger">
        <i class="bi bi-trash3-fill"></i> ยืนยันลบ
      </a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════
   CLOCK
══════════════════════════════════════════════════════ */
const SERVER_NOW = new Date('<?= $current_datetime_for_js ?>');
let offset = Date.now() - SERVER_NOW.getTime();

function startClock() {
  function tick() {
    const now = new Date(Date.now() - offset);
    const hh = String(now.getHours()).padStart(2,'0');
    const mm = String(now.getMinutes()).padStart(2,'0');
    const ss = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveClock').textContent = `${hh}:${mm}:${ss}`;
  }
  tick();
  setInterval(tick, 1000);
}
startClock();

/* ══════════════════════════════════════════════════════
   THEME — handled by /theme.js
══════════════════════════════════════════════════════ */
// ThemeManager global available from theme.js

/* ══════════════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════════════ */
function showToast(msg, type='success', dur=3500) {
  const stack = document.getElementById('toastStack');
  const t = document.createElement('div');
  t.className = `toast-item ${type}`;
  t.textContent = msg;
  stack.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateY(-8px)';
    t.style.transition = '.3s';
    setTimeout(() => t.remove(), 300);
  }, dur);
}

<?php if (isset($_SESSION['success'])): ?>
showToast('<?= addslashes($_SESSION['success']) ?>', 'success');
<?php unset($_SESSION['success']); endif; ?>
<?php if (isset($_SESSION['error'])): ?>
showToast('<?= addslashes($_SESSION['error']) ?>', 'error');
<?php unset($_SESSION['error']); endif; ?>

/* ══════════════════════════════════════════════════════
   PROFILE MODAL
══════════════════════════════════════════════════════ */
document.getElementById('userPillBtn').addEventListener('click', () => {
  document.getElementById('profileModal').classList.add('open');
  document.body.style.overflow = 'hidden';
});
function closeProfileModal() {
  document.getElementById('profileModal').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('profileModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeProfileModal();
});
document.getElementById('profileForm').addEventListener('submit', e => {
  const np = document.getElementById('newPw').value;
  const cp = document.getElementById('confirmPw').value;
  if (np && np !== cp) {
    e.preventDefault();
    showToast('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน', 'error');
  }
});

/* Password strength */
function checkStrength(val) {
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const fill = document.getElementById('pwStrengthFill');
  const pct  = (score / 4) * 100;
  const colors = ['','#ef4444','#f59e0b','#3b82f6','#22c55e'];
  fill.style.width  = pct + '%';
  fill.style.background = colors[score] || '#ddd';
}

/* ══════════════════════════════════════════════════════
   DELETE MODAL
══════════════════════════════════════════════════════ */
function confirmDelete(id, title) {
  document.getElementById('delTitle').textContent = title;
  document.getElementById('delConfirmLink').href = `manage?delete=${id}`;
  document.getElementById('deleteModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('deleteModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeDeleteModal();
});

/* ══════════════════════════════════════════════════════
   SEARCH & FILTER
══════════════════════════════════════════════════════ */
const searchInput   = document.getElementById('searchInput');
const statusFilter  = document.getElementById('statusFilter');
const tableBody     = document.getElementById('tableBody');
const rowCountBadge = document.getElementById('rowCountBadge');

function filterTable() {
  const q      = searchInput.value.toLowerCase().trim();
  const status = statusFilter.value;
  const rows   = tableBody.querySelectorAll('tr[data-title]');
  let visible  = 0;

  rows.forEach(row => {
    const title    = row.dataset.title    || '';
    const location = row.dataset.location || '';
    const creator  = row.dataset.creator  || '';
    const rowStatus = row.dataset.status  || '';

    const matchSearch  = !q || title.includes(q) || location.includes(q) || creator.includes(q);
    const matchStatus  = !status
      || (status === 'active' && !['เลื่อน','ยกเลิก'].includes(rowStatus))
      || rowStatus === status;

    const show = matchSearch && matchStatus;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  rowCountBadge.textContent = visible + ' รายการ';
}

searchInput.addEventListener('input', filterTable);
statusFilter.addEventListener('change', filterTable);

/* ══════════════════════════════════════════════════════
   KEYBOARD
══════════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeProfileModal(); closeDeleteModal(); }
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    searchInput.focus();
  }
});
</script>
</body>
</html>

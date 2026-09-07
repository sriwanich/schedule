<?php
// ═══════════════════════════════════════════════════════════════════
//  MOBILE MANAGE  —  ระบบจัดการตารางนัดหมาย (Mobile)
//  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
//  Navy/Gold Theme — Dark/Light Mode Edition
// ═══════════════════════════════════════════════════════════════════
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

include 'config.php';

date_default_timezone_set('Asia/Bangkok');

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
    $username         = $_SESSION['username'];
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name']  ?? '');
    $old_password     = $_POST['old_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
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
    header("Location: m_manage");
    exit();
}

// ─── DELETE event ──────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $stmt = mysqli_prepare($connect, "DELETE FROM events WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = "ลบการนัดหมายสำเร็จ";
    header("Location: m_manage");
    exit();
}

// ─── Current user ──────────────────────────────────────────────────
$username  = $_SESSION['username'];
$stmt      = mysqli_prepare($connect, "SELECT first_name, last_name, role FROM users WHERE username=?");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$user_data  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$first_name = $user_data['first_name'] ?? '';
$last_name  = $user_data['last_name']  ?? '';
$role       = $user_data['role']        ?? 'user';

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

$previous_page = $_SERVER['HTTP_REFERER'] ?? 'mobile';
?>
<!DOCTYPE html>
<html lang="th" data-theme="light" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
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
   DESIGN TOKENS — Light & Dark (Navy/Gold)
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
  --bg-surface:  rgba(255,255,255,0.88);
  --bg-elevated: rgba(255,255,255,0.97);
  --bg-glass:    rgba(255,255,255,0.65);
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
  --bg-surface:  rgba(13,35,64,0.92);
  --bg-elevated: rgba(15,46,84,0.88);
  --bg-glass:    rgba(10,22,40,0.82);
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
  display:flex;flex-direction:column;
  align-items:center;padding:.875rem 1rem .6rem;gap:.4rem;
}
.header-top-row{
  width:100%;display:flex;align-items:center;justify-content:space-between;
}
.header-brand{display:flex;align-items:center;gap:.75rem}
.header-logo{
  width:44px;height:44px;object-fit:contain;
  filter:drop-shadow(0 2px 8px rgba(0,0,0,.3));
  transition:transform .3s var(--ease-spring);flex-shrink:0;
}
.header-logo:hover{transform:scale(1.08) rotate(-3deg)}
.header-text h1{font-size:1rem;font-weight:600;letter-spacing:.01em;line-height:1.2;margin:0}
.header-text p{font-size:.72rem;opacity:.78;margin:0}
.header-actions{display:flex;align-items:center;gap:.5rem}

/* Clock row */
.header-clock-row{
  display:flex;align-items:center;gap:.75rem;
}
.header-date{font-size:.75rem;opacity:.82;white-space:nowrap}
.header-clock{
  font-size:1.6rem;font-weight:300;letter-spacing:.06em;
  line-height:1;font-variant-numeric:tabular-nums
}

/* Theme toggle */
.theme-toggle{
  width:38px;height:38px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
  color:#fff;display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1.05rem;
  transition:all .3s var(--ease-out);backdrop-filter:blur(8px);
  flex-shrink:0;
}
.theme-toggle:hover{background:rgba(255,255,255,.22)}

/* User pill */
.user-pill{
  display:flex;align-items:center;gap:.4rem;
  padding:5px 12px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.16);
  cursor:pointer;transition:all .25s var(--ease-out);
  font-size:.78rem;color:#fff;
}
.user-pill:hover{background:rgba(255,255,255,.20)}
.user-pill .role-tag{
  padding:2px 7px;border-radius:var(--r-pill);font-size:.65rem;font-weight:600;
}
.role-tag.admin{background:rgba(224,82,82,.35);color:#fca5a5}
.role-tag.user{background:rgba(232,160,32,.30);color:#fde68a}

/* ── Toolbar ──────────────────────────────────────── */
.app-toolbar{
  background:var(--bg-surface);backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border-strong);
  padding:.65rem 1rem;
  display:flex;align-items:center;justify-content:space-between;gap:.6rem;
  box-shadow:var(--shadow-sm);transition:background .4s;
  flex-wrap:wrap;
}
.toolbar-section{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.toolbar-center{
  display:flex;flex-direction:column;align-items:center;
  width:100%;order:3;margin-top:.3rem;
}
.today-label{font-size:.82rem;font-weight:500;color:var(--text-primary)}

/* Status pill */
.status-pill{
  display:inline-flex;align-items:center;gap:5px;
  padding:3px 12px;border-radius:var(--r-pill);
  font-size:.72rem;font-weight:600;letter-spacing:.02em;
}
.status-pill.has-events  {background:#e0e7ff;color:#3730a3}
.status-pill.done-events {background:#d1fae5;color:#065f46}
.status-pill.no-events   {background:#fee2e2;color:#991b1b}
[data-theme="dark"] .status-pill.has-events  {background:rgba(67,56,202,.25);color:#a5b4fc}
[data-theme="dark"] .status-pill.done-events {background:rgba(6,95,70,.25);color:#6ee7b7}
[data-theme="dark"] .status-pill.no-events   {background:rgba(153,27,27,.25);color:#fca5a5}

/* Toolbar buttons */
.tb-btn{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 13px;border-radius:var(--r-sm);
  font-size:.78rem;font-weight:500;font-family:'Anuphan',sans-serif;
  cursor:pointer;border:none;
  transition:all .25s var(--ease-out);text-decoration:none;white-space:nowrap;
}
.tb-btn-primary{background:var(--brand-500);color:#fff;box-shadow:var(--shadow-brand)}
.tb-btn-primary:hover{background:var(--brand-600);color:#fff}
.tb-btn-danger{background:var(--accent-red);color:#fff}
.tb-btn-danger:hover{background:#c83c3c;color:#fff}
.tb-btn-gold{background:var(--accent-gold);color:#fff}
.tb-btn-gold:hover{background:#d08010;color:#fff}
.tb-btn-ghost{
  background:transparent;color:var(--text-secondary);
  border:1px solid var(--border-strong);
}
.tb-btn-ghost:hover{background:var(--bg-elevated);color:var(--text-primary)}

/* ── Main ─────────────────────────────────────────── */
.app-main{flex:1;padding:1rem;max-width:100%}

/* ── Event cards ─────────────────────────────────── */
.date-group-card{
  background:var(--bg-surface);backdrop-filter:blur(16px);
  border-radius:var(--r-lg);border:1px solid var(--border-strong);
  margin-bottom:1rem;overflow:hidden;
  box-shadow:var(--shadow-sm);
  animation:cardReveal .4s var(--ease-out) both;
}
@keyframes cardReveal{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

.date-group-header{
  background:var(--header-bg);
  padding:.75rem 1rem;
  display:flex;align-items:center;gap:.6rem;
  position:relative;
}
.date-group-header.is-today{
  background:linear-gradient(135deg,#0f2e54,#1e6fd9 80%,#e8a020);
}
.date-group-header .date-label{
  font-size:.95rem;font-weight:600;color:#fff;
  display:flex;align-items:center;gap:.5rem;flex:1;
}
.today-chip{
  padding:2px 10px;border-radius:var(--r-pill);
  background:rgba(232,160,32,.9);color:#fff;
  font-size:.68rem;font-weight:700;letter-spacing:.04em;
}

.event-item{
  padding:.9rem 1rem;border-bottom:1px solid var(--border);
  position:relative;transition:background .15s;
}
.event-item:last-child{border-bottom:none}
.event-item:hover{background:rgba(30,111,217,.04)}
[data-theme="dark"] .event-item:hover{background:rgba(255,255,255,.03)}

.event-item.is-cancelled{
  background:rgba(224,82,82,.05);
}
.event-item.is-postponed{
  background:rgba(232,160,32,.05);
}
[data-theme="dark"] .event-item.is-cancelled{background:rgba(224,82,82,.10)}
[data-theme="dark"] .event-item.is-postponed{background:rgba(232,160,32,.10)}

/* Event title row */
.event-title-row{
  display:flex;align-items:flex-start;justify-content:space-between;
  gap:.5rem;margin-bottom:.5rem;
}
.event-title{
  font-size:.95rem;font-weight:600;color:var(--text-primary);
  flex:1;line-height:1.4;
}
.event-title.strikethrough{text-decoration:line-through;color:var(--text-muted)}

.status-badge-inline{
  display:inline-flex;align-items:center;gap:3px;
  padding:2px 8px;border-radius:var(--r-pill);font-size:.65rem;font-weight:700;
  flex-shrink:0;margin-top:2px;
}
.status-badge-inline.postponed{background:rgba(232,160,32,.2);color:#a06000}
.status-badge-inline.cancelled{background:rgba(224,82,82,.2);color:#b02020}
[data-theme="dark"] .status-badge-inline.postponed{background:rgba(232,160,32,.3);color:#fde68a}
[data-theme="dark"] .status-badge-inline.cancelled{background:rgba(224,82,82,.3);color:#fca5a5}

/* Event meta rows */
.event-meta{
  display:grid;grid-template-columns:1fr 1fr;gap:.25rem .75rem;
  margin-bottom:.6rem;
}
.meta-item{
  display:flex;align-items:flex-start;gap:.4rem;font-size:.82rem;
  color:var(--text-primary);
}
.meta-item.full-width{grid-column:1/-1}
.meta-item i{color:var(--brand-500);font-size:.82rem;margin-top:2px;flex-shrink:0}
[data-theme="dark"] .meta-item i{color:var(--accent-sky)}
.meta-label{color:var(--text-muted);font-size:.75rem}
.meta-value{font-size:.82rem}

/* Badges */
.tag-chip{
  display:inline-flex;align-items:center;gap:3px;
  padding:2px 8px;border-radius:var(--r-pill);
  background:var(--brand-50);color:var(--brand-700);
  font-size:.72rem;font-weight:500;margin:1px;
}
[data-theme="dark"] .tag-chip{background:rgba(30,111,217,.2);color:var(--brand-200)}
.chairman-chip{
  display:inline-flex;align-items:center;gap:3px;
  padding:2px 8px;border-radius:var(--r-pill);
  background:rgba(232,160,32,.18);color:#8a5c00;
  font-size:.72rem;font-weight:600;
}
[data-theme="dark"] .chairman-chip{background:rgba(232,160,32,.28);color:#fde68a}

/* Action row */
.event-actions{
  display:flex;gap:.5rem;justify-content:flex-end;margin-top:.6rem;
}
.act-btn{
  display:inline-flex;align-items:center;gap:4px;
  padding:5px 12px;border-radius:var(--r-sm);
  font-size:.78rem;font-weight:500;font-family:'Anuphan',sans-serif;
  cursor:pointer;border:none;text-decoration:none;
  transition:all .2s var(--ease-out);
}
.act-btn-edit{background:rgba(232,160,32,.18);color:#a06000}
.act-btn-edit:hover{background:var(--accent-gold);color:#fff}
.act-btn-del{background:rgba(224,82,82,.14);color:#b02020}
.act-btn-del:hover{background:var(--accent-red);color:#fff}
[data-theme="dark"] .act-btn-edit{background:rgba(232,160,32,.22);color:#fde68a}
[data-theme="dark"] .act-btn-edit:hover{background:var(--accent-gold);color:#fff}
[data-theme="dark"] .act-btn-del{background:rgba(224,82,82,.22);color:#fca5a5}
[data-theme="dark"] .act-btn-del:hover{background:var(--accent-red);color:#fff}

/* Rescheduled info */
.reschedule-info{
  margin-top:.5rem;padding:.5rem .75rem;
  border-radius:var(--r-sm);background:rgba(30,111,217,.08);
  border-left:3px solid var(--brand-500);
  font-size:.8rem;color:var(--text-secondary);
}
[data-theme="dark"] .reschedule-info{background:rgba(30,111,217,.15)}

/* File link */
.file-link{
  color:var(--text-secondary);text-decoration:none;
  display:inline-flex;align-items:center;gap:.3rem;
  font-size:.82rem;transition:color .2s;
}
.file-link:hover{color:var(--brand-400)}

/* Phone link */
.phone-link{color:inherit;text-decoration:none}
.phone-link:hover{color:var(--brand-400)}

/* Empty state */
.empty-state{
  padding:3rem 1rem;text-align:center;color:var(--text-muted);
}
.empty-state i{font-size:3rem;opacity:.4;display:block;margin-bottom:.75rem}
.empty-state p{font-size:.95rem}

/* Highlight animation */
.highlight{animation:highlightAnim 2s ease-out}
@keyframes highlightAnim{
  0%{background:rgba(30,111,217,.2)}
  100%{background:transparent}
}

/* ── Footer ───────────────────────────────────────── */
.app-footer{
  background:var(--header-bg);color:rgba(255,255,255,.65);
  text-align:center;padding:.75rem 1rem;font-size:.75rem;
  border-top:1px solid rgba(255,255,255,.08);
}

/* ── Admin FAB ────────────────────────────────────── */
.admin-fab{
  position:fixed;bottom:1.25rem;left:1.25rem;z-index:300;
  width:48px;height:48px;border-radius:var(--r-pill);
  background:var(--brand-500);color:#fff;border:none;
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;cursor:pointer;
  box-shadow:var(--shadow-brand);
  transition:all .3s var(--ease-spring);
}
.admin-fab:hover{background:var(--brand-600);transform:scale(1.1) rotate(15deg)}

/* ── Toast ────────────────────────────────────────── */
.toast-stack{
  position:fixed;top:4.5rem;left:50%;transform:translateX(-50%);
  z-index:2000;display:flex;flex-direction:column;gap:.5rem;align-items:center;
  width:90%;max-width:360px;
}
.toast-item{
  width:100%;padding:.7rem 1.25rem;border-radius:var(--r-md);
  font-family:'Anuphan',sans-serif;font-size:.88rem;font-weight:500;
  box-shadow:var(--shadow-lg);color:#fff;text-align:center;
  animation:toastIn .35s var(--ease-spring) both;
}
.toast-item.success{background:linear-gradient(135deg,#059669,#10b981)}
.toast-item.error  {background:linear-gradient(135deg,#dc2626,#ef4444)}
@keyframes toastIn{from{opacity:0;transform:translateY(-10px) scale(.96)}to{opacity:1;transform:none}}

/* ── Profile Modal ────────────────────────────────── */
.modal-backdrop-custom{
  position:fixed;inset:0;z-index:500;
  background:rgba(5,10,20,.75);backdrop-filter:blur(8px);
  display:none;align-items:flex-end;justify-content:center;
}
.modal-backdrop-custom.open{display:flex}
.modal-panel{
  background:var(--bg-elevated);border:1px solid var(--border-strong);
  border-radius:var(--r-xl) var(--r-xl) 0 0;
  box-shadow:var(--shadow-xl);
  width:100%;max-height:92vh;overflow-y:auto;
  animation:sheetSlideIn .4s var(--ease-spring) both;
}
@keyframes sheetSlideIn{from{transform:translateY(100%)}to{transform:none}}
.modal-header-custom{
  background:var(--header-bg);color:#fff;
  padding:1rem 1.25rem;border-radius:var(--r-xl) var(--r-xl) 0 0;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:10;
}
.modal-header-custom h5{font-size:.95rem;font-weight:600;margin:0;display:flex;align-items:center;gap:.5rem}
.modal-close-btn{
  width:32px;height:32px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.15);border:none;color:#fff;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:.95rem;transition:all .2s;
}
.modal-close-btn:hover{background:rgba(255,255,255,.28)}
.modal-body-custom{padding:1.25rem}
.form-group{margin-bottom:1rem}
.form-label-custom{
  display:block;font-size:.8rem;font-weight:600;
  color:var(--text-secondary);margin-bottom:.3rem;letter-spacing:.03em;
}
.form-control-custom{
  width:100%;padding:.55rem .9rem;
  border-radius:var(--r-sm);border:1px solid var(--border-strong);
  background:var(--bg-surface);color:var(--text-primary);
  font-family:'Anuphan',sans-serif;font-size:.9rem;
  transition:all .25s;outline:none;
}
.form-control-custom:focus{
  border-color:var(--brand-500);
  box-shadow:0 0 0 3px rgba(30,111,217,.15);
}
.form-hint{font-size:.73rem;color:var(--text-muted);margin-top:.25rem}
.modal-divider{height:1px;background:var(--border-strong);margin:.875rem 0}
.modal-footer-custom{
  padding:.875rem 1.25rem;border-top:1px solid var(--border);
  display:flex;gap:.6rem;justify-content:flex-end;
}
.pw-strength-bar{height:3px;border-radius:var(--r-pill);background:var(--border-strong);margin-top:.35rem;overflow:hidden}
.pw-strength-fill{height:100%;border-radius:var(--r-pill);transition:width .3s,background .3s}

/* Delete modal */
.del-panel{border-radius:var(--r-xl) var(--r-xl) 0 0}
.del-confirm-icon{
  width:60px;height:60px;border-radius:var(--r-pill);
  background:rgba(224,82,82,.15);color:var(--accent-red);
  display:flex;align-items:center;justify-content:center;
  font-size:1.8rem;margin:0 auto 1rem;
}
</style>
    <!-- Unified Theme System — apply before first paint -->
    <script>
    (function(){
      var h=document.documentElement;
      function autoT(){var hr=new Date().getHours();return(hr>=6&&hr<18)?'light':'dark';}
      function resolve(p){return p==='auto'?autoT():(p==='dark'||p==='light'?p:autoT());}
      h.setAttribute('data-theme', resolve(localStorage.getItem('theme-pref')||'auto'));
    })();
    </script>
</head>
<body>

<!-- Toast stack -->
<div class="toast-stack" id="toastStack"></div>

<!-- ══ HEADER ═══════════════════════════════════════════ -->
<header class="app-header">
  <div class="header-inner">
    <!-- Top row: brand + actions -->
    <div class="header-top-row">
      <div class="header-brand">
        <img src="/images/logo.png" alt="Logo" class="header-logo">
        <div class="header-text">
          <h1>ระบบปฏิทินและนัดหมาย</h1>
          <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
        </div>
      </div>
      <div class="header-actions">
        <button class="user-pill" id="userPillBtn" title="แก้ไขข้อมูลผู้ใช้">
          <i class="bi bi-person-circle"></i>
          <span class="role-tag <?= $role === 'admin' ? 'admin' : 'user' ?>">
            <?= $role === 'admin' ? 'Admin' : 'User' ?>
          </span>
        </button>
        <button class="theme-toggle" id="themeToggle" onclick="__themeToggleClick()" title="ธีม: Auto">
          <i class="bi bi-circle-half" id="themeIcon"></i>
          <span id="themeModeLabel" style="font-size:.6rem;margin-left:2px">Auto</span>
        </button>
      </div>
    </div>
    <!-- Clock row -->
    <div class="header-clock-row">
      <div class="header-date" id="headerDate"><?= htmlspecialchars($current_date_thai) ?></div>
      <div class="header-clock" id="headerClock">--:--:--</div>
    </div>
  </div>
</header>

<!-- ══ TOOLBAR ═══════════════════════════════════════════ -->
<div class="app-toolbar">
  <div class="toolbar-section">
    <a href="<?= htmlspecialchars($previous_page) ?>" class="tb-btn tb-btn-ghost">
      <i class="bi bi-arrow-left"></i> ย้อนกลับ
    </a>
    <a href="old_events" class="tb-btn tb-btn-gold">
      <i class="bi bi-clock-history"></i> ประวัติ
    </a>
  </div>
  <div class="toolbar-section">
    <a href="addEvent" class="tb-btn tb-btn-primary">
      <i class="bi bi-plus-circle-fill"></i> เพิ่ม
    </a>
    <a href="logout" class="tb-btn tb-btn-danger">
      <i class="bi bi-box-arrow-right"></i> ออก
    </a>
  </div>
  <!-- Status center -->
  <div class="toolbar-center">
    <div class="today-label">จัดการการนัดหมาย</div>
    <div style="margin-top:4px">
      <?php if ($total_events > 0): ?>
        <span class="status-pill has-events"><i class="bi bi-list-task"></i> มีวาระงาน <?= $total_events ?> รายการ</span>
      <?php elseif ($completed_events > 0): ?>
        <span class="status-pill done-events"><i class="bi bi-check-circle"></i> วาระงานวันนี้เสร็จสิ้นแล้ว</span>
      <?php else: ?>
        <span class="status-pill no-events"><i class="bi bi-calendar-x"></i> ไม่มีวาระงานวันนี้</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════ -->
<main class="app-main">
<?php
$query = "SELECT *,
          CASE
            WHEN status = 'เลื่อน' THEN 1
            WHEN status = 'ยกเลิก' THEN 2
            WHEN date = '0000-00-00' THEN 3
            ELSE 0
          END as status_order,
          (SELECT id FROM events e2 WHERE e2.original_event_id = events.id LIMIT 1) as rescheduled_id
          FROM events
          WHERE (date > CURDATE() OR date = '0000-00-00')
             OR (date = CURDATE() AND (time_to > CURTIME() OR time_to IS NULL OR time_from >= CURTIME()))
          ORDER BY
            CASE WHEN date = '0000-00-00' THEN 1 ELSE 0 END,
            date ASC, time_from ASC, status_order ASC";
$dbquery = mysqli_query($connect, $query);

$all_events = [];
while ($row = mysqli_fetch_assoc($dbquery)) $all_events[] = $row;

if (empty($all_events)):
?>
  <div class="date-group-card">
    <div class="empty-state">
      <i class="bi bi-calendar2-x"></i>
      <p>ไม่พบการนัดหมายในขณะนี้</p>
    </div>
  </div>
<?php
else:
  $current_group_date = null;
  $card_open = false;

  foreach ($all_events as $result):
    // ─── Determine date group ───────────────────────────────────────
    if ($result['date'] === '0000-00-00' || empty($result['date'])) {
      $group_key      = 'no-date';
      $formatted_date = 'ยังไม่มีกำหนดการแน่นอน';
      $is_today       = false;
    } else {
      $group_key    = $result['date'];
      $d            = strtotime($result['date']);
      $is_today     = ($result['date'] === $current_date);
      $day_of_week  = date('l', $d);
      $thai_day     = $thai_days_full[$day_of_week];
      $thai_month   = $thai_months_full[date('M', $d)];
      $year         = (int)date('Y', $d) + 543;
      $formatted_date = "วัน{$thai_day}ที่ " . date('j', $d) . " {$thai_month} {$year}";
    }

    // ─── Open new group card if needed ──────────────────────────────
    if ($current_group_date !== $group_key):
      if ($card_open) echo '</div>'; // close previous card
      $card_open = true;
      $current_group_date = $group_key;
      $icon = ($group_key === 'no-date') ? 'bi-question-circle' : 'bi-calendar-event-fill';
?>
    <div class="date-group-card" <?= ($group_key !== 'no-date') ? "id=\"group-{$result['date']}\"" : '' ?>>
      <div class="date-group-header <?= $is_today ? 'is-today' : '' ?>">
        <div class="date-label">
          <i class="bi <?= $icon ?>"></i>
          <?= htmlspecialchars($formatted_date) ?>
        </div>
        <?php if ($is_today): ?>
          <span class="today-chip">วันนี้</span>
        <?php endif; ?>
      </div>
<?php
    endif;

    // ─── Event item ─────────────────────────────────────────────────
    $status       = $result['status'] ?? '';
    $is_cancelled = in_array($status, ['ยกเลิก', 'เลื่อน']);
    $item_class   = '';
    if ($status === 'ยกเลิก') $item_class = 'is-cancelled';
    elseif ($status === 'เลื่อน') $item_class = 'is-postponed';

    $formatted_time = ($result['date'] === '0000-00-00')
      ? 'ยังไม่กำหนดเวลา'
      : date('H:i', strtotime($result['time_from'])) . ' น.';

    $rescheduled_id = $result['rescheduled_id'] ?? null;
    $rescheduled_event = null;
    if ($rescheduled_id) {
      $rs = mysqli_prepare($connect, "SELECT * FROM events WHERE id=?");
      mysqli_stmt_bind_param($rs, 'i', $rescheduled_id);
      mysqli_stmt_execute($rs);
      $rescheduled_event = mysqli_fetch_assoc(mysqli_stmt_get_result($rs));
    }
?>
      <div class="event-item <?= $item_class ?>" id="event-<?= (int)$result['id'] ?>"
        <?php if ($is_cancelled && $rescheduled_id): ?>
          onclick="window.location.href='#event-<?= (int)$rescheduled_id ?>'" style="cursor:pointer"
        <?php endif; ?>>

        <!-- Title row -->
        <div class="event-title-row">
          <div class="event-title <?= $is_cancelled ? 'strikethrough' : '' ?>">
            <?= htmlspecialchars($result['title']) ?>
          </div>
          <?php if ($status === 'เลื่อน'): ?>
            <span class="status-badge-inline postponed"><i class="bi bi-arrow-right-circle"></i>เลื่อน</span>
          <?php elseif ($status === 'ยกเลิก'): ?>
            <span class="status-badge-inline cancelled"><i class="bi bi-x-circle"></i>ยกเลิก</span>
          <?php endif; ?>
        </div>

        <!-- Meta grid -->
        <div class="event-meta">
          <div class="meta-item">
            <i class="bi bi-clock-fill"></i>
            <div><div class="meta-label">เวลา</div><div class="meta-value"><?= $formatted_time ?></div></div>
          </div>

          <?php if (!empty($result['location'])): ?>
          <div class="meta-item">
            <i class="bi bi-geo-alt-fill"></i>
            <div><div class="meta-label">สถานที่</div><div class="meta-value"><?= htmlspecialchars($result['location']) ?></div></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['Dress'])): ?>
          <div class="meta-item">
            <i class="bi bi-person-bounding-box"></i>
            <div><div class="meta-label">การแต่งกาย</div><div class="meta-value"><?= htmlspecialchars($result['Dress']) ?></div></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['department'])): ?>
          <div class="meta-item">
            <i class="bi bi-buildings"></i>
            <div><div class="meta-label">เจ้าของเรื่อง</div><div class="meta-value"><?= htmlspecialchars($result['department']) ?></div></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['chairman'])): ?>
          <div class="meta-item full-width">
            <i class="bi bi-person-fill"></i>
            <div>
              <div class="meta-label">ประธาน</div>
              <div class="meta-value">
                <span class="chairman-chip"><?= htmlspecialchars($result['chairman']) ?></span>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['description'])): ?>
          <div class="meta-item full-width">
            <i class="bi bi-people-fill"></i>
            <div>
              <div class="meta-label">ผู้ปฏิบัติ</div>
              <div class="meta-value">
                <?php foreach (explode(' ', $result['description']) as $tag): ?>
                  <span class="tag-chip"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['coordinator_name']) || !empty($result['coordinator_phone'])): ?>
          <div class="meta-item full-width">
            <i class="bi bi-headset"></i>
            <div>
              <div class="meta-label">ผู้ประสานงาน</div>
              <div class="meta-value">
                <?= htmlspecialchars($result['coordinator_name']) ?>
                <?php if (!empty($result['coordinator_phone'])): ?>
                  <a href="tel:<?= htmlspecialchars($result['coordinator_phone']) ?>" class="phone-link">
                    <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($result['coordinator_phone']) ?>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($result['file_name'])): ?>
          <div class="meta-item">
            <i class="bi bi-paperclip"></i>
            <div>
              <div class="meta-label">ไฟล์แนบ</div>
              <div class="meta-value">
                <a href="uploads/<?= htmlspecialchars($result['file_name']) ?>" download class="file-link">
                  <i class="bi bi-download"></i> ดาวน์โหลด
                </a>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Rescheduled info -->
        <?php if ($is_cancelled && $status === 'เลื่อน' && $rescheduled_event): ?>
        <div class="reschedule-info">
          <i class="bi bi-arrow-right-circle-fill"></i>
          เลื่อนไปวันที่ <?= date('d/m/Y', strtotime($rescheduled_event['date'])) ?>
          เวลา <?= date('H:i', strtotime($rescheduled_event['time_from'])) ?> น.
          <a href="#event-<?= (int)$rescheduled_id ?>" style="color:var(--brand-400);text-decoration:none;margin-left:.4rem">
            <i class="bi bi-arrow-down-circle"></i> ดูวาระที่เลื่อน
          </a>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="event-actions">
          <a href="editEvent?id=<?= (int)$result['id'] ?>" class="act-btn act-btn-edit">
            <i class="bi bi-pencil-fill"></i> แก้ไข
          </a>
          <button class="act-btn act-btn-del"
            onclick="confirmDelete(<?= (int)$result['id'] ?>, '<?= addslashes(htmlspecialchars($result['title'])) ?>')">
            <i class="bi bi-trash3-fill"></i> ลบ
          </button>
        </div>
      </div>
<?php
  endforeach;
  if ($card_open) echo '</div>'; // close last card
endif;
?>
</main>

<!-- ══ FOOTER ════════════════════════════════════════════ -->
<footer class="app-footer">
  <i class="bi bi-shield-check"></i>
  © 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง — พัฒนาโดย นายณัฐพันธุ์ ศรีวนิช
</footer>



<!-- ══ PROFILE MODAL (Bottom Sheet) ════════════════════ -->
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
        <i class="bi bi-check-circle-fill"></i> บันทึก
      </button>
    </div>
  </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ════════════════════════════ -->
<div class="modal-backdrop-custom" id="deleteModal">
  <div class="modal-panel del-panel">
    <div class="modal-header-custom" style="background:linear-gradient(135deg,#7f1d1d,#991b1b);border-radius:var(--r-xl) var(--r-xl) 0 0">
      <h5><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันการลบ</h5>
      <button class="modal-close-btn" onclick="closeDeleteModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body-custom" style="text-align:center;padding:1.75rem 1.25rem">
      <div class="del-confirm-icon"><i class="bi bi-trash3-fill"></i></div>
      <p style="font-weight:600;font-size:1rem;color:var(--text-primary)">คุณแน่ใจหรือไม่?</p>
      <p style="color:var(--text-muted);font-size:.85rem;margin-top:.5rem">
        จะลบนัดหมาย: <strong id="delTitle" style="color:var(--accent-red)"></strong>
      </p>
      <p style="font-size:.78rem;color:var(--text-muted);margin-top:.4rem">การกระทำนี้ไม่สามารถย้อนกลับได้</p>
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
    document.getElementById('headerClock').textContent = `${hh}:${mm}:${ss}`;
  }
  tick();
  setInterval(tick, 1000);
}
startClock();



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
  document.getElementById('delConfirmLink').href = `m_manage?delete=${id}`;
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
   KEYBOARD & SCROLL TO HASH
══════════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeProfileModal(); closeDeleteModal(); }
});

window.addEventListener('load', () => {
  if (window.location.hash) {
    const target = document.querySelector(window.location.hash);
    if (target) {
      setTimeout(() => {
        target.scrollIntoView({ behavior: 'smooth' });
        target.closest('.event-item')?.classList.add('highlight');
        setTimeout(() => target.closest('.event-item')?.classList.remove('highlight'), 2000);
      }, 400);
    }
  }
});

/* ═══════════════════════════════════════════════════════════════
   UNIFIED THEME ENGINE — shared across all pages
   Cycle: สว่าง → มืด → Auto (ตามเวลา 06:00-18:00)
═══════════════════════════════════════════════════════════════ */
(function () {
  var html = document.documentElement;
  function autoTheme() { var hr=new Date().getHours(); return (hr>=6&&hr<18)?'light':'dark'; }
  function resolve(pref) { return pref==='auto'?autoTheme():(pref==='dark'||pref==='light'?pref:autoTheme()); }
  function applyTheme(pref) {
    var resolved = resolve(pref);
    html.setAttribute('data-theme', resolved);
    localStorage.setItem('theme-pref', pref);
    var icon = document.getElementById('themeIcon');
    if (icon) icon.className = pref==='auto' ? 'bi bi-circle-half' : (resolved==='dark'?'bi bi-moon-fill':'bi bi-sun-fill');
    var lbl = document.getElementById('themeModeLabel');
    if (lbl) lbl.textContent = pref==='auto'?'Auto':(resolved==='dark'?'มืด':'สว่าง');
    var btn = document.getElementById('themeToggle');
    if (btn) btn.title = 'ธีม: '+(pref==='auto'?'Auto (ตามเวลา)':(resolved==='dark'?'โหมดมืด':'โหมดสว่าง'))+' — คลิกเพื่อเปลี่ยน';
  }
  var saved = localStorage.getItem('theme-pref') || 'auto';
  applyTheme(saved);
  setInterval(function(){ if((localStorage.getItem('theme-pref')||'auto')==='auto') applyTheme('auto'); }, 60000);
  window.__themeToggleClick = function(){
    var pref = localStorage.getItem('theme-pref')||'auto';
    var next = pref==='light'?'dark':(pref==='dark'?'auto':'light');
    applyTheme(next);
  };
})();

</script>
</body>
</html>
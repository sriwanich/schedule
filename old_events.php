<?php
// ═══════════════════════════════════════════════════════════════════
//  ENTERPRISE — ประวัติการนัดหมาย (old_events.php)
//  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
//  Theme: index_desktop.php Navy/Gold  |  Font: Anuphan
// ═══════════════════════════════════════════════════════════════════

$start_time = microtime(true);

session_start();

// ─── Auth guard ────────────────────────────────────────────────────
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
    'Sunday'    => 'อาทิตย์', 'Monday'  => 'จันทร์',
    'Tuesday'   => 'อังคาร',  'Wednesday' => 'พุธ',
    'Thursday'  => 'พฤหัสบดี','Friday'  => 'ศุกร์',
    'Saturday'  => 'เสาร์'
];

// ─── Helpers ───────────────────────────────────────────────────────
function getFileIcon(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'                      => 'bi-filetype-pdf',
        'jpg','jpeg','png','gif'   => 'bi-file-earmark-image',
        'doc','docx'               => 'bi-file-earmark-word',
        'xls','xlsx'               => 'bi-file-earmark-excel',
        'ppt','pptx'               => 'bi-file-earmark-ppt',
        'zip','rar'                => 'bi-file-earmark-zip',
        default                    => 'bi-file-earmark'
    };
}

// ─── Profile update (POST) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username       = mysqli_real_escape_string($connect, $_SESSION['username']);
    $first_name     = mysqli_real_escape_string($connect, trim($_POST['first_name'] ?? ''));
    $last_name      = mysqli_real_escape_string($connect, trim($_POST['last_name']  ?? ''));
    $old_password   = $_POST['old_password']   ?? '';
    $new_password   = $_POST['new_password']   ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $user_query = mysqli_query($connect, "SELECT password FROM users WHERE username='$username'");
    $user_data  = mysqli_fetch_assoc($user_query);

    if ($user_data && password_verify($old_password, $user_data['password'])) {
        mysqli_query($connect, "UPDATE users SET first_name='$first_name', last_name='$last_name' WHERE username='$username'");
        if (!empty($new_password) && $new_password === $confirm_password) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            mysqli_query($connect, "UPDATE users SET password='$hash' WHERE username='$username'");
            $_SESSION['success'] = "อัปเดตข้อมูลและรหัสผ่านสำเร็จ";
        } else {
            $_SESSION['success'] = "อัปเดตข้อมูลสำเร็จ";
        }
    } else {
        $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
    }
    header("Location: old_events");
    exit();
}

// ─── Current user ──────────────────────────────────────────────────
$username   = mysqli_real_escape_string($connect, $_SESSION['username']);
$user_query = mysqli_query($connect, "SELECT first_name, last_name, role FROM users WHERE username='$username'");
$user_data  = mysqli_fetch_assoc($user_query);
$first_name = $user_data['first_name'] ?? '';
$last_name  = $user_data['last_name']  ?? '';
$role       = $user_data['role']       ?? 'user';

// ─── Device detection ─────────────────────────────────────────────
$is_mobile    = (bool) preg_match("/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i", $_SERVER['HTTP_USER_AGENT']);
$previous_page = $_SERVER['HTTP_REFERER'] ?? ($is_mobile ? 'mobile' : 'desktop');

// ─── Current date (Thai) ───────────────────────────────────────────
$current_date      = date('Y-m-d');
$current_day       = date('l');
$thai_day_full     = $thai_days_full[$current_day];
$current_date_thai = "วัน{$thai_day_full}ที่ " . date('j') . ' ' .
                     $thai_months_full[date('M')] . ' พ.ศ. ' . (date('Y') + 543);

// ─── Version ───────────────────────────────────────────────────────
try {
    $vr = mysqli_query($connect, "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1");
    $current_version = mysqli_fetch_assoc($vr)['version'] ?? '1.0.0';
} catch (Exception $e) { $current_version = '1.0.0'; }

// ─── Delete handler ────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($connect, "DELETE FROM events WHERE id=$id");
    $_SESSION['success'] = "ลบการนัดหมายสำเร็จ";
    header("Location: old_events");
    exit();
}

// ─── Search & filter params ────────────────────────────────────────
$search_keyword = isset($_GET['search']) ? mysqli_real_escape_string($connect, trim($_GET['search'])) : '';
$filter_start   = isset($_GET['start_date']) && !empty($_GET['start_date']) ? mysqli_real_escape_string($connect, $_GET['start_date']) : '';
$filter_end     = isset($_GET['end_date'])   && !empty($_GET['end_date'])   ? mysqli_real_escape_string($connect, $_GET['end_date'])   : '';
$filter_status  = isset($_GET['status'])     && !empty($_GET['status'])     ? mysqli_real_escape_string($connect, $_GET['status'])     : '';

// ─── Pagination ────────────────────────────────────────────────────
$records_per_page = 15;
$current_page     = max(1, intval($_GET['page'] ?? 1));
$offset           = ($current_page - 1) * $records_per_page;

// ─── Build WHERE ───────────────────────────────────────────────────
$conditions = ["(date < CURDATE() OR (date = CURDATE() AND time_to < CURTIME()) OR status IN ('ยกเลิก','เลื่อน'))"];
if ($filter_start)  $conditions[] = "date >= '$filter_start'";
if ($filter_end)    $conditions[] = "date <= '$filter_end'";
if ($filter_status) {
    if ($filter_status === 'เสร็จสิ้น') {
        $conditions[] = "(status IS NULL OR status NOT IN ('ยกเลิก','เลื่อน'))";
    } else {
        $conditions[] = "status = '$filter_status'";
    }
}
if ($search_keyword) $conditions[] = "title LIKE '%$search_keyword%'";

$where = 'WHERE ' . implode(' AND ', $conditions);

// Count
$count_result  = mysqli_query($connect, "SELECT COUNT(*) as total FROM events $where");
$total_records = (int)(mysqli_fetch_assoc($count_result)['total'] ?? 0);
$total_pages   = max(1, (int) ceil($total_records / $records_per_page));
$current_page  = min($current_page, $total_pages);
$offset        = ($current_page - 1) * $records_per_page;

// Data
$dbquery = mysqli_query($connect, "SELECT * FROM events $where ORDER BY date DESC, time_from DESC LIMIT $offset, $records_per_page");

// ─── Stats for header pills ────────────────────────────────────────
$total_cancelled = (int)(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM events WHERE status='ยกเลิก'"))['c'] ?? 0);
$total_postponed = (int)(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM events WHERE status='เลื่อน'"))['c'] ?? 0);

$end_time        = microtime(true);
$generation_time = round($end_time - $start_time, 3);

// ─── Pagination URL helper ─────────────────────────────────────────
function pageUrl(int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ประวัติการนัดหมาย — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</title>
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
   DESIGN TOKENS — Light & Dark  (Navy/Gold)
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
  --shadow-brand: 0 8px 32px rgba(30,111,217,.35);

  --r-sm: 8px; --r-md: 14px; --r-lg: 20px; --r-xl: 28px; --r-pill: 999px;
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);

  --header-bg: linear-gradient(135deg, #0a1628 0%, #0d2340 50%, #0f2e54 100%);
  --page-bg: linear-gradient(145deg, #c8daf5 0%, #dce9fc 40%, #e8f0fd 100%);
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
  --shadow-sm:  0 1px 4px rgba(0,0,0,.40);
  --shadow-md:  0 4px 16px rgba(0,0,0,.55);
  --shadow-lg:  0 10px 36px rgba(0,0,0,.65);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.45);
  --header-bg: linear-gradient(135deg, #050d1a 0%, #08192e 50%, #0a2040 100%);
  --page-bg: radial-gradient(ellipse 80% 60% at 20% 0%, rgba(30,111,217,0.22) 0%, transparent 65%),
             radial-gradient(ellipse 60% 50% at 80% 100%, rgba(232,160,32,0.14) 0%, transparent 60%),
             radial-gradient(ellipse 50% 80% at 50% 50%, #0a1628 0%, #1a0a2e 100%);
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
  transition: background 0.4s var(--ease-out), color 0.4s var(--ease-out);
}

/* ══════════════════════════════════════════════════════ BG ORBS */
.bg-orb {
  position: fixed; border-radius: 50%; filter: blur(80px);
  opacity: 0.35; pointer-events: none; z-index: 0;
  animation: orbFloat 12s ease-in-out infinite;
}
.bg-orb-1 { width:520px;height:520px;background:radial-gradient(circle,#6366f1 0%,transparent 70%);top:-160px;left:-120px;animation-delay:0s; }
.bg-orb-2 { width:400px;height:400px;background:radial-gradient(circle,#3730a3 0%,transparent 70%);bottom:-120px;right:-100px;animation-delay:-5s; }
.bg-orb-3 { width:280px;height:280px;background:radial-gradient(circle,#a5b4fc 0%,transparent 70%);top:45%;left:55%;opacity:.18;animation-delay:-9s; }
[data-theme="dark"] .bg-orb   { opacity: 0.25; }
[data-theme="dark"] .bg-orb-3 { opacity: 0.12; }
@keyframes orbFloat { 0%,100%{transform:translateY(0) scale(1);} 50%{transform:translateY(-30px) scale(1.06);} }

/* ══════════════════════════════════════════════════════ HEADER */
.app-header {
  background: var(--header-bg);
  color: #fff;
  position: sticky; top: 0; z-index: 100;
  box-shadow: 0 4px 24px rgba(0,0,0,.25);
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.header-inner {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center; gap: 1rem;
  padding: 0.875rem 2rem;
}
.header-brand { display:flex;align-items:center;gap:1rem; }
.header-logo {
  width:52px;height:52px;object-fit:contain;
  filter:drop-shadow(0 2px 8px rgba(0,0,0,.3));
  transition:transform 0.3s var(--ease-spring);flex-shrink:0;
}
.header-logo:hover { transform:scale(1.08) rotate(-3deg); }
.header-text h1 {
  font-family:'Anuphan',sans-serif;font-size:1.15rem;
  font-weight:600;letter-spacing:.01em;line-height:1.2;margin:0;
}
.header-text p { font-size:.78rem;opacity:.78;margin:0;letter-spacing:.02em; }
.header-center { text-align:center; }
.header-date { font-size:.82rem;opacity:.85;white-space:nowrap; }
.header-clock {
  font-size:2rem;font-weight:300;letter-spacing:.06em;
  line-height:1;font-variant-numeric:tabular-nums;
}
.header-actions { display:flex;align-items:center;justify-content:flex-end;gap:.75rem; }

.theme-toggle {
  width:44px;height:44px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
  color:#fff;display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1.15rem;transition:all .3s var(--ease-out);
  backdrop-filter:blur(8px);
}
.theme-toggle:hover { background:rgba(255,255,255,.22);transform:scale(1.08); }

.version-chip {
  font-size:.7rem;padding:3px 10px;border-radius:var(--r-pill);
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
  color:rgba(255,255,255,.8);cursor:pointer;transition:all .25s;
  white-space:nowrap;backdrop-filter:blur(4px);
}
.version-chip:hover { background:rgba(255,255,255,.22);color:#fff; }

.user-chip {
  display:inline-flex;align-items:center;gap:.5rem;padding:6px 14px;
  border-radius:var(--r-pill);background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.18);color:#fff;font-size:.82rem;
  cursor:pointer;transition:all .25s var(--ease-out);backdrop-filter:blur(8px);
}
.user-chip:hover { background:rgba(255,255,255,.20);transform:translateY(-1px); }
.user-role-badge {
  font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:var(--r-pill);
  background:var(--accent-gold);color:#1a0a00;letter-spacing:.04em;
}
.user-role-badge.admin { background:var(--accent-red);color:#fff; }

/* ══════════════════════════════════════════════════════ TOOLBAR */
.app-toolbar {
  background:var(--bg-surface);backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border-strong);
  padding:.75rem 2rem;display:flex;align-items:center;
  justify-content:space-between;gap:1rem;
  box-shadow:var(--shadow-sm);transition:background .4s;
}
.toolbar-left,.toolbar-right { display:flex;align-items:center;gap:.6rem; }
.toolbar-title {
  font-size:1.05rem;font-weight:600;color:var(--text-primary);
  display:flex;align-items:center;gap:.5rem;
}
.tb-btn {
  display:inline-flex;align-items:center;gap:6px;padding:7px 16px;
  border-radius:var(--r-sm);font-size:.82rem;font-weight:500;
  font-family:'Anuphan',sans-serif;cursor:pointer;border:none;
  transition:all .25s var(--ease-out);text-decoration:none;white-space:nowrap;
}
.tb-btn-primary { background:var(--brand-500);color:#fff;box-shadow:var(--shadow-brand); }
.tb-btn-primary:hover { background:var(--brand-600);color:#fff;transform:translateY(-2px); }
.tb-btn-ghost {
  background:transparent;color:var(--text-secondary);
  border:1px solid var(--border-strong);
}
.tb-btn-ghost:hover { background:var(--bg-elevated);color:var(--text-primary);transform:translateY(-1px); }
.tb-btn-danger { background:transparent;color:var(--accent-red);border:1px solid rgba(224,82,82,.35); }
.tb-btn-danger:hover { background:var(--accent-red);color:#fff;transform:translateY(-1px); }

/* ══════════════════════════════════════════════════════ MAIN */
.app-main { flex:1;padding:1.75rem 2rem;max-width:100%;position:relative;z-index:1; }

/* ══════════════════════════════════════════════════════ DATE BANNER */
.date-banner {
  display:inline-flex;align-items:center;gap:.6rem;padding:6px 18px;
  background:var(--bg-surface);border:1px solid var(--border-strong);
  border-radius:var(--r-pill);font-size:.88rem;font-weight:500;
  color:var(--text-secondary);backdrop-filter:blur(12px);
  box-shadow:var(--shadow-sm);margin-bottom:1.25rem;
}

/* ══════════════════════════════════════════════════════ STATS STRIP */
.stats-strip {
  display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;
}
.stat-card {
  flex:1;min-width:140px;
  background:var(--bg-surface);backdrop-filter:blur(16px);
  border:1px solid var(--border-strong);border-radius:var(--r-md);
  padding:1rem 1.25rem;display:flex;align-items:center;gap:.875rem;
  box-shadow:var(--shadow-sm);transition:all .25s var(--ease-out);
  animation:statReveal .5s var(--ease-out) both;
}
.stat-card:hover { transform:translateY(-3px);box-shadow:var(--shadow-md); }
@keyframes statReveal { from{opacity:0;transform:translateY(10px);} to{opacity:1;transform:translateY(0);} }
.stat-card:nth-child(1){animation-delay:.05s;} .stat-card:nth-child(2){animation-delay:.1s;} .stat-card:nth-child(3){animation-delay:.15s;}
.stat-icon {
  width:40px;height:40px;border-radius:var(--r-sm);display:flex;
  align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;
}
.stat-icon.total   { background:rgba(30,111,217,.12);color:var(--brand-500); }
.stat-icon.cancel  { background:rgba(224,82,82,.12);color:var(--accent-red); }
.stat-icon.postpone{ background:rgba(232,160,32,.12);color:var(--accent-gold); }
[data-theme="dark"] .stat-icon.total   { background:rgba(30,111,217,.22); }
[data-theme="dark"] .stat-icon.cancel  { background:rgba(224,82,82,.22); }
[data-theme="dark"] .stat-icon.postpone{ background:rgba(232,160,32,.22); }
.stat-num  { font-size:1.6rem;font-weight:700;line-height:1;color:var(--text-primary);font-variant-numeric:tabular-nums; }
.stat-label{ font-size:.75rem;color:var(--text-muted);margin-top:2px; }

/* ══════════════════════════════════════════════════════ FILTER PANEL */
.filter-panel {
  background:var(--bg-surface);backdrop-filter:blur(16px);
  -webkit-backdrop-filter:blur(16px);
  border:1px solid var(--border-strong);border-radius:var(--r-lg);
  padding:1.25rem 1.5rem;margin-bottom:1.5rem;
  box-shadow:var(--shadow-sm);
}
.filter-heading {
  font-size:.8rem;font-weight:700;letter-spacing:.06em;
  text-transform:uppercase;color:var(--text-secondary);
  display:flex;align-items:center;gap:.4rem;margin-bottom:1rem;
}
.filter-grid { display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:.75rem;align-items:end; }
@media(max-width:900px){ .filter-grid{grid-template-columns:1fr 1fr;} }
@media(max-width:600px){ .filter-grid{grid-template-columns:1fr;} }

.form-label-ent {
  display:block;font-size:.72rem;font-weight:600;letter-spacing:.04em;
  text-transform:uppercase;color:var(--text-muted);margin-bottom:.4rem;
}
.form-control-ent, .form-select-ent {
  width:100%;padding:.5rem .875rem;border-radius:var(--r-sm);
  background:var(--bg-elevated);border:1px solid var(--border-strong);
  color:var(--text-primary);font-family:'Anuphan',sans-serif;font-size:.88rem;
  transition:all .2s;outline:none;
}
.form-control-ent:focus, .form-select-ent:focus {
  border-color:var(--brand-500);box-shadow:0 0 0 3px rgba(30,111,217,.15);
}
[data-theme="dark"] .form-control-ent,
[data-theme="dark"] .form-select-ent {
  background:rgba(10,22,40,.8);color:#f0f4ff;
}
.form-select-ent { appearance:auto; }
.filter-actions { display:flex;gap:.5rem;align-items:flex-end; }

/* ══════════════════════════════════════════════════════ TABLE CARD */
.table-card {
  background:var(--bg-surface);backdrop-filter:blur(16px);
  -webkit-backdrop-filter:blur(16px);
  border:1px solid var(--border-strong);border-radius:var(--r-lg);
  overflow:hidden;box-shadow:var(--shadow-md);
  animation:cardReveal .5s var(--ease-out) both .2s;
}
@keyframes cardReveal { from{opacity:0;transform:translateY(16px);} to{opacity:1;transform:translateY(0);} }

.table-card-header {
  padding:.875rem 1.5rem;border-bottom:1px solid var(--border);
  background:var(--bg-elevated);display:flex;align-items:center;
  justify-content:space-between;gap:1rem;
}
.table-card-title {
  font-size:.88rem;font-weight:600;color:var(--text-primary);
  display:flex;align-items:center;gap:.5rem;
}
.record-count-badge {
  font-size:.7rem;font-weight:700;padding:2px 10px;border-radius:var(--r-pill);
  background:rgba(30,111,217,.10);color:var(--brand-600);border:1px solid rgba(30,111,217,.2);
}
[data-theme="dark"] .record-count-badge { background:rgba(30,111,217,.22);color:var(--brand-300); }

.table-scroll { overflow-x:auto; }
.data-table { width:100%;border-collapse:collapse;table-layout:fixed; }
.data-table thead tr {
  background:linear-gradient(90deg,var(--brand-800) 0%,var(--brand-700) 100%);
}
.data-table th {
  padding:.75rem 1.25rem;font-family:'Anuphan',sans-serif;
  font-size:.72rem;font-weight:600;color:rgba(255,255,255,.9);
  letter-spacing:.04em;text-transform:uppercase;text-align:left;
  border:none;white-space:nowrap;user-select:none;
}
.data-table th.center { text-align:center; }
.data-table th:nth-child(1){ width:12%; }
.data-table th:nth-child(2){ width:8%; }
.data-table th:nth-child(3){ width:28%; }
.data-table th:nth-child(4){ width:17%; }
.data-table th:nth-child(5){ width:13%; }
.data-table th:nth-child(6){ width:12%; }
.data-table th:nth-child(7){ width:10%; }

.data-table tbody tr { border-bottom:1px solid var(--border);transition:background .15s; }
.data-table tbody tr:last-child { border-bottom:none; }
.data-table tbody tr:hover { background:var(--bg-elevated); }
.data-table tbody tr:nth-child(even) { background:rgba(0,0,0,.018); }
[data-theme="dark"] .data-table tbody tr:nth-child(even) { background:rgba(255,255,255,.025); }
[data-theme="dark"] .data-table tbody tr:hover { background:rgba(255,255,255,.04); }

.data-table td {
  padding:.75rem 1.25rem;vertical-align:middle;
  color:var(--text-primary);font-size:.88rem;word-break:break-word;
}
.data-table td.center { text-align:center; }

/* Row variants */
.row-cancelled td { text-decoration:line-through;opacity:.75;background:rgba(224,82,82,.05) !important; }
.row-postponed td { text-decoration:line-through;opacity:.75;background:rgba(232,160,32,.05) !important; }
[data-theme="dark"] .row-cancelled td { background:rgba(224,82,82,.08) !important; }
[data-theme="dark"] .row-postponed td { background:rgba(232,160,32,.08) !important; }

/* Date cell */
.date-cell { font-weight:600;font-size:.85rem;color:var(--text-primary); }
.date-cell small { display:block;font-size:.72rem;color:var(--text-muted);font-weight:400; }

/* Time cell */
.time-val { font-variant-numeric:tabular-nums;font-weight:600;color:var(--brand-600); }
[data-theme="dark"] .time-val { color:var(--brand-300); }

/* Status badge */
.status-badge {
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 12px;border-radius:var(--r-pill);
  font-size:.72rem;font-weight:700;letter-spacing:.03em;white-space:nowrap;
}
.status-done      { background:#d1fae5;color:#065f46; }
.status-cancelled { background:#fee2e2;color:#991b1b; }
.status-postponed { background:#fef3c7;color:#92400e; }
[data-theme="dark"] .status-done      { background:rgba(6,95,70,.25);color:#6ee7b7; }
[data-theme="dark"] .status-cancelled { background:rgba(153,27,27,.25);color:#fca5a5; }
[data-theme="dark"] .status-postponed { background:rgba(146,64,14,.25);color:#fcd34d; }

/* File attachment */
.file-link {
  display:inline-flex;align-items:center;gap:5px;padding:4px 12px;
  border-radius:var(--r-sm);background:rgba(30,111,217,.08);
  color:var(--brand-600);font-size:.78rem;font-weight:500;
  text-decoration:none;border:1px solid rgba(30,111,217,.15);
  transition:all .2s;
}
.file-link:hover {
  background:var(--brand-500);color:#fff;
  transform:translateY(-2px);box-shadow:var(--shadow-sm);
}
[data-theme="dark"] .file-link { background:rgba(30,111,217,.15);color:var(--brand-300); }
.no-file { font-size:.78rem;color:var(--text-muted);font-style:italic; }

/* Empty state */
.empty-state {
  text-align:center;padding:4rem 2rem;
}
.empty-icon {
  width:72px;height:72px;border-radius:var(--r-xl);
  background:rgba(30,111,217,.08);
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;color:var(--text-muted);margin:0 auto 1rem;
}
.empty-title { font-size:1rem;font-weight:600;color:var(--text-primary);margin-bottom:.4rem; }
.empty-sub   { font-size:.85rem;color:var(--text-muted); }

/* ══════════════════════════════════════════════════════ PAGINATION */
.pagination-bar {
  display:flex;align-items:center;justify-content:space-between;
  padding:1rem 1.5rem;border-top:1px solid var(--border);
  background:var(--bg-elevated);flex-wrap:wrap;gap:.75rem;
}
.pagination-info { font-size:.82rem;color:var(--text-muted); }
.pagination-nav  { display:flex;gap:.35rem;align-items:center; }
.page-btn {
  display:inline-flex;align-items:center;justify-content:center;
  width:36px;height:36px;border-radius:var(--r-sm);
  background:var(--bg-surface);border:1px solid var(--border-strong);
  color:var(--text-secondary);font-size:.82rem;text-decoration:none;
  transition:all .2s;
}
.page-btn:hover { background:var(--brand-500);color:#fff;border-color:var(--brand-500); }
.page-btn.active { background:var(--brand-500);color:#fff;border-color:var(--brand-500);font-weight:700; }
.page-btn.disabled { opacity:.45;pointer-events:none; }
.page-btn-wide { width:auto;padding:0 12px;gap:5px; }

/* ══════════════════════════════════════════════════════ TOAST */
.toast-stack {
  position:fixed;top:1.25rem;left:50%;transform:translateX(-50%);
  z-index:2000;display:flex;flex-direction:column;gap:.5rem;
  pointer-events:none;min-width:280px;
}
.toast-item {
  display:flex;align-items:center;gap:.75rem;padding:.875rem 1.25rem;
  border-radius:var(--r-md);backdrop-filter:blur(20px);
  box-shadow:0 8px 32px rgba(0,0,0,.2);border:1px solid rgba(255,255,255,.12);
  font-size:.88rem;font-weight:500;pointer-events:all;
  animation:toastIn .4s var(--ease-spring) both;transition:opacity .3s;
}
.toast-success { background:rgba(6,95,70,.9);color:#6ee7b7; }
.toast-error   { background:rgba(153,27,27,.9);color:#fca5a5; }
@keyframes toastIn { from{opacity:0;transform:translateY(-12px) scale(.96);} to{opacity:1;transform:translateY(0) scale(1);} }

/* ══════════════════════════════════════════════════════ MODAL */
.modal-overlay {
  position:fixed;inset:0;background:rgba(5,13,26,.7);
  backdrop-filter:blur(8px);z-index:500;
  display:flex;align-items:center;justify-content:center;padding:1.5rem;
  opacity:0;pointer-events:none;transition:opacity .3s;
}
.modal-overlay.open { opacity:1;pointer-events:all; }
.modal-sheet {
  background:var(--bg-elevated);border:1px solid var(--border-strong);
  border-radius:var(--r-xl);box-shadow:0 24px 80px rgba(0,0,0,.45);
  width:100%;max-width:520px;display:flex;flex-direction:column;
  max-height:90vh;overflow:hidden;
  transform:translateY(20px) scale(.97);transition:transform .35s var(--ease-out),opacity .3s;
  opacity:0;
}
.modal-overlay.open .modal-sheet { transform:translateY(0) scale(1);opacity:1; }
.modal-hdr {
  padding:1.25rem 1.75rem;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  background:var(--bg-surface);
}
.modal-hdr-title { font-size:1rem;font-weight:700;color:var(--text-primary); display:flex;align-items:center;gap:.5rem; }
.modal-close-btn {
  width:36px;height:36px;border-radius:var(--r-pill);border:1px solid var(--border-strong);
  background:transparent;color:var(--text-secondary);display:flex;align-items:center;
  justify-content:center;cursor:pointer;font-size:1rem;transition:all .25s;
}
.modal-close-btn:hover { background:var(--accent-red);color:#fff;border-color:transparent;transform:rotate(90deg); }
.modal-bdy { padding:1.5rem 1.75rem;overflow-y:auto;flex:1; }
.modal-ftr {
  padding:1rem 1.75rem;border-top:1px solid var(--border);
  display:flex;justify-content:flex-end;gap:.75rem;background:var(--bg-elevated);
}
.field-group { margin-bottom:1.1rem; }
.field-group:last-child { margin-bottom:0; }
.field-label { font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--text-muted);margin-bottom:.4rem; }

/* ══════════════════════════════════════════════════════ FAB */
.fab-stack {
  position:fixed;bottom:1.5rem;right:1.5rem;z-index:200;
  display:flex;flex-direction:column-reverse;gap:.6rem;align-items:flex-end;
}
.fab-left { position:fixed;bottom:1.5rem;left:1.5rem;z-index:200; }
.fab {
  display:inline-flex;align-items:center;gap:.5rem;
  padding:10px 18px;border-radius:var(--r-pill);
  font-size:.85rem;font-weight:600;font-family:'Anuphan',sans-serif;
  cursor:pointer;border:none;transition:all .3s var(--ease-spring);
  box-shadow:0 6px 20px rgba(0,0,0,.2);text-decoration:none;
}
.fab-primary { background:var(--brand-500);color:#fff; }
.fab-primary:hover { background:var(--brand-600);color:#fff;transform:translateY(-3px) scale(1.04); }
.fab-settings { background:var(--bg-elevated);color:var(--text-secondary);border:1px solid var(--border-strong); }
.fab-settings:hover { background:var(--brand-500);color:#fff;transform:scale(1.1); }

/* ══════════════════════════════════════════════════════ FOOTER */
.app-footer {
  background:var(--header-bg);color:rgba(255,255,255,.8);
  text-align:center;padding:1.25rem 2rem;font-size:.82rem;
  border-top:1px solid rgba(255,255,255,.08);position:relative;z-index:1;
}
.app-footer strong { color:#fff; }
.perf { font-size:.7rem;opacity:.55;margin-top:.3rem; }

/* ══════════════════════════════════════════════════════ RESPONSIVE */
.app-header,.app-toolbar,.app-main,.app-footer { position:relative;z-index:1; }
::-webkit-scrollbar       { width:7px;height:7px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--border-strong);border-radius:4px; }
::-webkit-scrollbar-thumb:hover { background:var(--text-muted); }

@media(max-width:900px){
  .header-inner { grid-template-columns:1fr;gap:.5rem; }
  .header-center { display:none; }
  .header-actions { justify-content:flex-start;flex-wrap:wrap; }
  .app-toolbar { flex-wrap:wrap; }
  .app-main { padding:1rem; }
  .stats-strip { gap:.75rem; }
  .stat-card { min-width:110px; }
}
@media(max-width:600px){
  .data-table th:nth-child(5),.data-table td:nth-child(5),
  .data-table th:nth-child(6),.data-table td:nth-child(6) { display:none; }
  .pagination-bar { flex-direction:column;gap:.5rem; }
}
</style>
</head>
<body>

<!-- BG Orbs -->
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>

<!-- Toast Stack -->
<div class="toast-stack" id="toastStack"></div>

<!-- ══════════════ HEADER ══════════════ -->
<header class="app-header">
  <div class="header-inner">
    <!-- Brand -->
    <div class="header-brand">
      <img src="/images/logo.png" alt="Logo" class="header-logo">
      <div class="header-text">
        <h1>ระบบปฏิทินและนัดหมาย</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัย จังหวัดระยอง</p>
      </div>
    </div>

    <!-- Clock -->
    <div class="header-center">
      <div class="header-date"><?= htmlspecialchars($current_date_thai) ?></div>
      <div class="header-clock" id="headerClock">--:--:--</div>
    </div>

    <!-- Actions -->
    <div class="header-actions">
      <span class="version-chip" onclick="window.location.href='changelogs'" title="บันทึกการเปลี่ยนแปลง">
        เวอร์ชัน <?= htmlspecialchars($current_version) ?>
      </span>
      <button class="theme-toggle" id="themeToggle" title="สลับโหมด">
        <i class="bi bi-moon-stars" id="themeIcon"></i>
      </button>
      <div class="user-chip" onclick="openProfileModal()" title="แก้ไขโปรไฟล์">
        <i class="bi bi-person-circle"></i>
        <span><?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?></span>
        <span class="user-role-badge <?= $role === 'admin' ? 'admin' : '' ?>">
          <?= $role === 'admin' ? 'Admin' : 'User' ?>
        </span>
      </div>
    </div>
  </div>
</header>

<!-- ══════════════ TOOLBAR ══════════════ -->
<div class="app-toolbar">
  <div class="toolbar-left">
    <a href="<?= htmlspecialchars($previous_page) ?>" class="tb-btn tb-btn-ghost">
      <i class="bi bi-arrow-left"></i> ย้อนกลับ
    </a>
    <span class="toolbar-title">
      <i class="bi bi-clock-history"></i> ประวัติการนัดหมาย
    </span>
  </div>
  <div class="toolbar-right">
    <a href="addEvent" class="tb-btn tb-btn-primary">
      <i class="bi bi-plus-circle"></i> เพิ่มนัดหมาย
    </a>
    <a href="logout" class="tb-btn tb-btn-danger">
      <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
    </a>
  </div>
</div>

<!-- ══════════════ MAIN ══════════════ -->
<main class="app-main">

  <!-- Stats Strip -->
  <div class="stats-strip">
    <div class="stat-card">
      <div class="stat-icon total"><i class="bi bi-archive"></i></div>
      <div>
        <div class="stat-num"><?= number_format($total_records) ?></div>
        <div class="stat-label">รายการทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon cancel"><i class="bi bi-x-circle"></i></div>
      <div>
        <div class="stat-num"><?= number_format($total_cancelled) ?></div>
        <div class="stat-label">ยกเลิกแล้ว</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon postpone"><i class="bi bi-arrow-repeat"></i></div>
      <div>
        <div class="stat-num"><?= number_format($total_postponed) ?></div>
        <div class="stat-label">เลื่อนแล้ว</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon total" style="background:rgba(62,201,126,.12);color:var(--accent-green)">
        <i class="bi bi-check2-circle"></i>
      </div>
      <div>
        <div class="stat-num"><?= number_format($total_records - $total_cancelled - $total_postponed) ?></div>
        <div class="stat-label">เสร็จสิ้น</div>
      </div>
    </div>
  </div>

  <!-- Filter Panel -->
  <div class="filter-panel">
    <div class="filter-heading"><i class="bi bi-funnel"></i> ค้นหาและกรองข้อมูล</div>
    <form method="GET" action="">
      <div class="filter-grid">
        <div>
          <label class="form-label-ent">ค้นหาเรื่อง</label>
          <input type="text" name="search" class="form-control-ent"
                 placeholder="พิมพ์คำค้นหา…"
                 value="<?= htmlspecialchars($search_keyword) ?>">
        </div>
        <div>
          <label class="form-label-ent">จากวันที่</label>
          <input type="date" name="start_date" class="form-control-ent"
                 value="<?= htmlspecialchars($filter_start) ?>">
        </div>
        <div>
          <label class="form-label-ent">ถึงวันที่</label>
          <input type="date" name="end_date" class="form-control-ent"
                 value="<?= htmlspecialchars($filter_end) ?>">
        </div>
        <div>
          <label class="form-label-ent">สถานะ</label>
          <select name="status" class="form-select-ent">
            <option value="">ทั้งหมด</option>
            <option value="เสร็จสิ้น" <?= $filter_status === 'เสร็จสิ้น' ? 'selected' : '' ?>>เสร็จสิ้น</option>
            <option value="ยกเลิก"    <?= $filter_status === 'ยกเลิก'    ? 'selected' : '' ?>>ยกเลิก</option>
            <option value="เลื่อน"    <?= $filter_status === 'เลื่อน'    ? 'selected' : '' ?>>เลื่อน</option>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit" class="tb-btn tb-btn-primary" style="height:38px">
            <i class="bi bi-search"></i> ค้นหา
          </button>
          <a href="old_events" class="tb-btn tb-btn-ghost" style="height:38px">
            <i class="bi bi-x"></i>
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Table Card -->
  <div class="table-card">
    <div class="table-card-header">
      <div class="table-card-title">
        <i class="bi bi-table"></i> รายการประวัติ
        <?php if (!empty($search_keyword)): ?>
          <span style="font-size:.78rem;color:var(--text-muted);font-weight:400">
            — ผลลัพธ์สำหรับ "<em><?= htmlspecialchars($search_keyword) ?></em>"
          </span>
        <?php endif; ?>
      </div>
      <span class="record-count-badge">
        <?= number_format($total_records) ?> รายการ
      </span>
    </div>

    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th><i class="bi bi-calendar-event"></i> วันที่</th>
            <th class="center"><i class="bi bi-clock"></i> เวลา</th>
            <th><i class="bi bi-card-heading"></i> เรื่อง</th>
            <th><i class="bi bi-geo-alt"></i> สถานที่</th>
            <th><i class="bi bi-person"></i> ผู้ปฏิบัติ</th>
            <th class="center"><i class="bi bi-paperclip"></i> ไฟล์</th>
            <th class="center"><i class="bi bi-info-circle"></i> สถานะ</th>
          </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($dbquery) === 0): ?>
          <tr>
            <td colspan="7" style="padding:0">
              <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <div class="empty-title">
                  <?= !empty($search_keyword)
                    ? 'ไม่พบรายการที่ตรงกับ "' . htmlspecialchars($search_keyword) . '"'
                    : 'ไม่พบประวัติการนัดหมาย' ?>
                </div>
                <div class="empty-sub">ลองปรับเงื่อนไขการค้นหาหรือล้างตัวกรอง</div>
              </div>
            </td>
          </tr>
        <?php else:
          while ($row = mysqli_fetch_assoc($dbquery)):
            // Date formatting
            if ($row['date'] === '0000-00-00' || empty($row['date'])) {
                $date_main = '<span style="color:var(--text-muted);font-style:italic;font-size:.8rem">ยังไม่มีกำหนด</span>';
                $date_sub  = '';
                $time_display = '—';
            } else {
                $d    = strtotime($row['date']);
                $mon  = date('M', $d);
                $yr   = date('Y', $d) + 543;
                $day_name = $thai_days_full[date('l', $d)] ?? '';
                $date_main = date('j', $d) . ' ' . $thai_months_full[$mon] . ' ' . $yr;
                $date_sub  = 'วัน' . $day_name;
                $time_display = $row['time_from'] ? date('H:i', strtotime($row['time_from'])) . ' น.' : '—';
            }

            // Status
            $status_text  = 'เสร็จสิ้น';
            $status_class = 'status-done';
            $row_class    = '';
            if ($row['status'] === 'ยกเลิก') {
                $status_text  = 'ยกเลิก';
                $status_class = 'status-cancelled';
                $row_class    = 'row-cancelled';
            } elseif ($row['status'] === 'เลื่อน') {
                $status_text  = 'เลื่อน';
                $status_class = 'status-postponed';
                $row_class    = 'row-postponed';
            }

            // File
            $file_html = '<span class="no-file">—</span>';
            if (!empty($row['file_name'])) {
                $icon      = getFileIcon($row['file_name']);
                $file_path = 'uploads/' . htmlspecialchars($row['file_name']);
                $file_html = '<a href="' . $file_path . '" target="_blank" class="file-link">
                    <i class="bi ' . $icon . '"></i> ดูไฟล์
                </a>';
            }

            $description = !empty($row['description']) ? htmlspecialchars($row['description']) : '—';
        ?>
          <tr class="<?= $row_class ?>">
            <td>
              <div class="date-cell">
                <?= $date_main ?>
                <?php if ($date_sub): ?>
                  <small><?= $date_sub ?></small>
                <?php endif; ?>
              </div>
            </td>
            <td class="center">
              <span class="time-val"><?= htmlspecialchars($time_display) ?></span>
            </td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
            <td><?= $description ?></td>
            <td class="center"><?= $file_html ?></td>
            <td class="center">
              <span class="status-badge <?= $status_class ?>">
                <?= $status_text ?>
              </span>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div><!-- /table-scroll -->

    <!-- Pagination Bar -->
    <?php if ($total_records > 0): ?>
    <div class="pagination-bar">
      <div class="pagination-info">
        <i class="bi bi-info-circle"></i>
        แสดง <?= number_format(min($offset + 1, $total_records)) ?>–<?= number_format(min($offset + $records_per_page, $total_records)) ?>
        จากทั้งหมด <?= number_format($total_records) ?> รายการ
        (หน้า <?= $current_page ?>/<?= $total_pages ?>)
      </div>
      <nav class="pagination-nav" aria-label="Pagination">
        <!-- Prev -->
        <?php if ($current_page <= 1): ?>
          <span class="page-btn disabled"><i class="bi bi-chevron-double-left"></i></span>
        <?php else: ?>
          <a class="page-btn" href="<?= pageUrl(1) ?>"><i class="bi bi-chevron-double-left"></i></a>
          <a class="page-btn" href="<?= pageUrl($current_page - 1) ?>"><i class="bi bi-chevron-left"></i></a>
        <?php endif; ?>

        <!-- Pages -->
        <?php
        $start_p = max(1, $current_page - 2);
        $end_p   = min($total_pages, $current_page + 2);
        if ($start_p > 1) {
            echo '<a class="page-btn" href="' . pageUrl(1) . '">1</a>';
            if ($start_p > 2) echo '<span class="page-btn disabled" style="border:none">…</span>';
        }
        for ($i = $start_p; $i <= $end_p; $i++) {
            $ac = ($i === $current_page) ? ' active' : '';
            echo '<a class="page-btn' . $ac . '" href="' . pageUrl($i) . '">' . $i . '</a>';
        }
        if ($end_p < $total_pages) {
            if ($end_p < $total_pages - 1) echo '<span class="page-btn disabled" style="border:none">…</span>';
            echo '<a class="page-btn" href="' . pageUrl($total_pages) . '">' . $total_pages . '</a>';
        }
        ?>

        <!-- Next -->
        <?php if ($current_page >= $total_pages): ?>
          <span class="page-btn disabled"><i class="bi bi-chevron-double-right"></i></span>
        <?php else: ?>
          <a class="page-btn" href="<?= pageUrl($current_page + 1) ?>"><i class="bi bi-chevron-right"></i></a>
          <a class="page-btn" href="<?= pageUrl($total_pages) ?>"><i class="bi bi-chevron-double-right"></i></a>
        <?php endif; ?>
      </nav>
    </div>
    <?php endif; ?>
  </div><!-- /table-card -->

</main><!-- /app-main -->

<!-- ══════════════ FABs ══════════════ -->
<?php if ($role === 'admin'): ?>
<div class="fab-left">
  <button class="fab fab-settings" onclick="window.location.href='settings'" title="ตั้งค่าระบบ">
    <i class="bi bi-gear"></i>
  </button>
</div>
<?php endif; ?>

<!-- ══════════════ FOOTER ══════════════ -->
<footer class="app-footer">
  <strong>© <?= date('Y') ?> สำนักงานป้องกันและบรรเทาสาธารณภัย จังหวัดระยอง</strong>
  <div class="perf">⚡ สร้างหน้าในเวลา <?= $generation_time ?>วินาที · เวอร์ชัน <?= htmlspecialchars($current_version) ?></div>
</footer>

<!-- ══════════════ PROFILE MODAL ══════════════ -->
<div class="modal-overlay" id="profileModal">
  <div class="modal-sheet">
    <div class="modal-hdr">
      <div class="modal-hdr-title">
        <i class="bi bi-person-gear"></i> แก้ไขข้อมูลผู้ใช้
      </div>
      <button class="modal-close-btn" onclick="closeProfileModal()">
        <i class="bi bi-x"></i>
      </button>
    </div>
    <div class="modal-bdy">
      <form id="profileForm" method="POST" action="">
        <div class="field-group">
          <div class="field-label">ชื่อ</div>
          <input type="text" name="first_name" class="form-control-ent"
                 value="<?= htmlspecialchars($first_name) ?>" required>
        </div>
        <div class="field-group">
          <div class="field-label">นามสกุล</div>
          <input type="text" name="last_name" class="form-control-ent"
                 value="<?= htmlspecialchars($last_name) ?>" required>
        </div>
        <div style="height:1px;background:var(--border);margin:1.25rem 0"></div>
        <div class="field-group">
          <div class="field-label">รหัสผ่านปัจจุบัน <span style="color:var(--accent-red)">*</span></div>
          <input type="password" name="old_password" class="form-control-ent" required>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:.35rem">กรอกเพื่อยืนยันตัวตน</div>
        </div>
        <div class="field-group">
          <div class="field-label">รหัสผ่านใหม่</div>
          <input type="password" name="new_password" id="newPassword" class="form-control-ent">
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:.35rem">เว้นว่างหากไม่ต้องการเปลี่ยน</div>
        </div>
        <div class="field-group">
          <div class="field-label">ยืนยันรหัสผ่านใหม่</div>
          <input type="password" name="confirm_password" id="confirmPassword" class="form-control-ent">
        </div>
    </div>
    <div class="modal-ftr">
      <button type="button" class="tb-btn tb-btn-ghost" onclick="closeProfileModal()">
        <i class="bi bi-x-circle"></i> ยกเลิก
      </button>
      <button type="submit" class="tb-btn tb-btn-primary" form="profileForm">
        <i class="bi bi-check-circle"></i> บันทึก
      </button>
    </div>
      </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ══ CLOCK ══ */
function startClock() {
  function tick() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('headerClock').textContent = h + ':' + m + ':' + s;
  }
  tick(); setInterval(tick, 1000);
}
startClock();

/* ══ THEME TOGGLE ══ */
const html      = document.documentElement;
const themeIcon = document.getElementById('themeIcon');
const saved     = localStorage.getItem('theme') || 'light';
html.setAttribute('data-theme', saved);
themeIcon.className = saved === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';

document.getElementById('themeToggle').addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  themeIcon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
});

/* ══ TOAST ══ */
function showToast(msg, type = 'success', duration = 3500) {
  const stack = document.getElementById('toastStack');
  const el    = document.createElement('div');
  el.className = 'toast-item toast-' + type;
  el.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${msg}`;
  stack.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, duration);
}

<?php if (isset($_SESSION['success'])): ?>
showToast(<?= json_encode($_SESSION['success']) ?>, 'success');
<?php unset($_SESSION['success']); endif; ?>
<?php if (isset($_SESSION['error'])): ?>
showToast(<?= json_encode($_SESSION['error']) ?>, 'error');
<?php unset($_SESSION['error']); endif; ?>

/* ══ PROFILE MODAL ══ */
function openProfileModal()  { document.getElementById('profileModal').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeProfileModal() { document.getElementById('profileModal').classList.remove('open'); document.body.style.overflow = ''; }

document.getElementById('profileModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeProfileModal();
});

document.getElementById('profileForm').addEventListener('submit', function(e) {
  const np = document.getElementById('newPassword').value;
  const cp = document.getElementById('confirmPassword').value;
  if (np && np !== cp) {
    e.preventDefault();
    showToast('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน', 'error');
  }
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeProfileModal();
});

/* ══ INIT: set date inputs from URL ══ */
(function(){
  const p = new URLSearchParams(window.location.search);
  ['start_date','end_date','status','search'].forEach(k => {
    const el = document.querySelector('[name="' + k + '"]');
    if (el && p.get(k)) el.value = p.get(k);
  });
})();
</script>
</body>
</html>

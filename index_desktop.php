<?php
// ═══════════════════════════════════════════════════════════════════
//  ENTERPRISE DASHBOARD  —  ระบบปฏิทินและนัดหมาย
//  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
//  Upgraded by: Enterprise-Level Refactor (Dark/Light Mode Edition)
// ═══════════════════════════════════════════════════════════════════

$start_time = microtime(true);

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

// ─── DB queries ────────────────────────────────────────────────────
try {
    $version_query  = "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1";
    $version_result = mysqli_query($connect, $version_query);
    $version_row    = mysqli_fetch_assoc($version_result);
    $current_version = $version_row['version'] ?? '1.0.0';
} catch (Exception $e) { $current_version = '1.0.0'; }

try {
    $leave_result = mysqli_query($connect,
        "SELECT COUNT(*) as c FROM responsible_persons WHERE leave_type IS NOT NULL AND leave_type != ''");
    $leave_count  = (mysqli_fetch_assoc($leave_result))['c'] ?? 0;
} catch (Exception $e) { $leave_count = 0; }

// ─── File helpers ──────────────────────────────────────────────────
function formatFileSize($bytes) {
    if (!$bytes) return 'ไม่ทราบขนาด';
    if ($bytes < 1024)    return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'                        => 'bi-filetype-pdf',
        'jpg','jpeg','png','gif'     => 'bi-file-earmark-image',
        'doc','docx'                 => 'bi-file-earmark-word',
        'xls','xlsx'                 => 'bi-file-earmark-excel',
        'ppt','pptx'                 => 'bi-file-earmark-ppt',
        'zip','rar'                  => 'bi-file-earmark-zip',
        default                      => 'bi-file-earmark'
    };
}
function getRealFileSize($filename) {
    if (!$filename) return null;
    $path = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $filename;
    return (file_exists($path) && is_file($path)) ? filesize($path) : null;
}

// ─── Upcoming events ───────────────────────────────────────────────
try {
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM events
         WHERE (date > CURDATE() OR (date = CURDATE() AND time_to > CURTIME()))
           AND status NOT IN ('เลื่อน','ยกเลิก')
         ORDER BY date ASC, time_from ASC");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $upcoming_events = [];
    while ($r = mysqli_fetch_assoc($result)) $upcoming_events[] = $r;
} catch (Exception $e) { $upcoming_events = []; }

// ─── Today stats ───────────────────────────────────────────────────
$current_date    = date('Y-m-d');
$current_day     = date('l');
$thai_day_full   = $thai_days_full[$current_day];
$current_date_thai = "วัน{$thai_day_full}ที่ " . date('j') . ' ' .
                     $thai_months_full[date('M')] . ' พ.ศ. ' . (date('Y') + 543);

try {
    $stmt = mysqli_prepare($connect,
        "SELECT COUNT(*) as c FROM events WHERE date=? AND (time_to>CURTIME() OR time_to IS NULL) AND status NOT IN ('เลื่อน','ยกเลิก')");
    mysqli_stmt_bind_param($stmt, 's', $current_date);
    mysqli_stmt_execute($stmt);
    $total_events = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)))['c'] ?? 0;

    $stmt2 = mysqli_prepare($connect,
        "SELECT COUNT(*) as c FROM events WHERE date=? AND time_to<=CURTIME() AND status NOT IN ('เลื่อน','ยกเลิก')");
    mysqli_stmt_bind_param($stmt2, 's', $current_date);
    mysqli_stmt_execute($stmt2);
    $completed_events = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2)))['c'] ?? 0;
} catch (Exception $e) { $total_events = 0; $completed_events = 0; }

// ─── Grouped events ────────────────────────────────────────────────
$dbquery = mysqli_query($connect,
    "SELECT * FROM events
     WHERE ((CONCAT(date,' ',time_from)<=NOW() AND CONCAT(date,' ',time_to)>=NOW()) OR CONCAT(date,' ',time_from)>NOW())
       AND status NOT IN ('เลื่อน','ยกเลิก')
     ORDER BY date ASC, time_from ASC");

$grouped_events = [];
while ($event = mysqli_fetch_assoc($dbquery)) {
    if ($event['file_name']) $event['real_file_size'] = getRealFileSize($event['file_name']);
    $grouped_events[$event['date']][] = $event;
}

$end_time        = microtime(true);
$generation_time = $end_time - $start_time;
$current_datetime_for_js = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ระบบปฏิทินและนัดหมาย — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</title>
<meta name="author"    content="นายณัฐพันธุ์ ศรีวนิช ฝ่ายป้องกันและปฏิบัติการ">
<meta name="copyright" content="© 2024 นายณัฐพันธุ์ ศรีวนิช">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0f4c75">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════════════
   DESIGN TOKENS — Light & Dark  (index.php Navy/Gold theme)
   Font: Anuphan throughout
══════════════════════════════════════════════════════ */
:root {
  /* Brand — navy/blue matching index.php */
  --brand-50:  #e8f0fd;
  --brand-100: #c1d5f8;
  --brand-200: #93b8f4;
  --brand-300: #5e98ef;
  --brand-400: #3a80e8;
  --brand-500: #1e6fd9;   /* Primary — index.php --clr-blue */
  --brand-600: #1760c2;
  --brand-700: #0f4fa3;
  --brand-800: #0a3a7a;
  --brand-900: #0a1628;   /* index.php --clr-bg-start */

  /* Accent — gold matching index.php */
  --accent-gold:   #e8a020;   /* index.php --clr-accent */
  --accent-orange: #e8a020;
  --accent-red:    #e05252;   /* index.php --clr-danger */
  --accent-green:  #3ec97e;   /* index.php --clr-success */
  --accent-purple: #7c3aed;
  --accent-sky:    #38c2ff;

  /* Neutral (Light) — index.php-inspired light mode */
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

  /* Shadows */
  --shadow-sm:  0 1px 4px rgba(30,111,217,.07), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md:  0 4px 16px rgba(30,111,217,.14), 0 2px 6px rgba(0,0,0,.06);
  --shadow-lg:  0 10px 36px rgba(30,111,217,.18), 0 4px 12px rgba(0,0,0,.08);
  --shadow-xl:  0 25px 60px rgba(30,111,217,.24);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.35);

  /* Radius */
  --r-sm: 8px;
  --r-md: 14px;
  --r-lg: 20px;
  --r-xl: 28px;
  --r-pill: 999px;

  /* Motion */
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);

  /* Header — deep navy matching index.php dark bg */
  --header-bg: linear-gradient(135deg, #0a1628 0%, #0d2340 50%, #0f2e54 100%);

  /* Page background — light blue-tinted, index.php inspired */
  --page-bg: linear-gradient(145deg, #c8daf5 0%, #dce9fc 40%, #e8f0fd 100%);
}

[data-theme="dark"] {
  /* Dark — mirrors index.php dark palette exactly */
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

  --header-bg: linear-gradient(135deg, #050d1a 0%, #08192e 50%, #0a2040 100%);

  --page-bg: radial-gradient(ellipse 80% 60% at 20% 0%, rgba(30,111,217,0.22) 0%, transparent 65%),
             radial-gradient(ellipse 60% 50% at 80% 100%, rgba(232,160,32,0.14) 0%, transparent 60%),
             radial-gradient(ellipse 50% 80% at 50% 50%, #0a1628 0%, #1a0a2e 100%);
}

/* ══════════════════════════════════════════════════════
   RESET & BASE
══════════════════════════════════════════════════════ */
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

/* ══════════════════════════════════════════════════════
   HEADER
══════════════════════════════════════════════════════ */
.app-header {
  background: var(--header-bg);
  color: #fff;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 4px 24px rgba(0,0,0,.25);
  border-bottom: 1px solid rgba(255,255,255,.08);
}

.header-inner {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 1rem;
  padding: 0.875rem 2rem;
}

.header-brand {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.header-logo {
  width: 52px;
  height: 52px;
  object-fit: contain;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.3));
  transition: transform 0.3s var(--ease-spring);
  flex-shrink: 0;
}
.header-logo:hover { transform: scale(1.08) rotate(-3deg); }

.header-text h1 {
  font-family: 'Anuphan', sans-serif;
  font-size: 1.15rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  line-height: 1.2;
  margin: 0;
}
.header-text p {
  font-size: 0.78rem;
  opacity: 0.78;
  margin: 0;
  letter-spacing: 0.02em;
}

.header-center {
  text-align: center;
}

.header-date {
  font-family: 'Anuphan', sans-serif;
  font-size: 0.82rem;
  opacity: 0.85;
  white-space: nowrap;
}

.header-clock {
  font-family: 'Anuphan', sans-serif;
  font-size: 2rem;
  font-weight: 300;
  letter-spacing: 0.06em;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.header-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
}

/* Theme toggle */
.theme-toggle {
  width: 44px;
  height: 44px;
  border-radius: var(--r-pill);
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1.15rem;
  transition: all 0.3s var(--ease-out);
  backdrop-filter: blur(8px);
}
.theme-toggle:hover {
  background: rgba(255,255,255,.22);
  transform: scale(1.08);
}

/* Version chip */
.version-chip {
  font-size: 0.7rem;
  padding: 3px 10px;
  border-radius: var(--r-pill);
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  color: rgba(255,255,255,.8);
  cursor: pointer;
  transition: all 0.25s;
  white-space: nowrap;
  backdrop-filter: blur(4px);
  font-family: 'Anuphan', monospace;
}
.version-chip:hover {
  background: rgba(255,255,255,.22);
  color: #fff;
}

/* ══════════════════════════════════════════════════════
   TOOLBAR (below header)
══════════════════════════════════════════════════════ */
.app-toolbar {
  background: var(--bg-surface);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-strong);
  padding: 0.75rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  box-shadow: var(--shadow-sm);
  transition: background 0.4s;
}

.toolbar-left, .toolbar-right {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.toolbar-center {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.today-label {
  font-family: 'Anuphan', sans-serif;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-primary);
}

/* Status pill */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 14px;
  border-radius: var(--r-pill);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}
.status-pill.has-events   { background: #e0e7ff; color: #3730a3; }
.status-pill.done-events  { background: #d1fae5; color: #065f46; }
.status-pill.no-events    { background: #fee2e2; color: #991b1b; }
[data-theme="dark"] .status-pill.has-events  { background: rgba(67,56,202,.25); color: #a5b4fc; }
[data-theme="dark"] .status-pill.done-events { background: rgba(6,95,70,.25); color: #6ee7b7; }
[data-theme="dark"] .status-pill.no-events   { background: rgba(153,27,27,.25); color: #fca5a5; }

/* Toolbar buttons */
.tb-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: var(--r-sm);
  font-size: 0.82rem;
  font-weight: 500;
  font-family: 'Anuphan', sans-serif;
  cursor: pointer;
  border: none;
  transition: all 0.25s var(--ease-out);
  text-decoration: none;
  white-space: nowrap;
}
.tb-btn-primary {
  background: var(--brand-500);
  color: #fff;
  box-shadow: var(--shadow-brand);
}
.tb-btn-primary:hover {
  background: var(--brand-600);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(79,70,229,.38);
}
.tb-btn-ghost {
  background: transparent;
  color: var(--text-secondary);
  border: 1px solid var(--border-strong);
}
.tb-btn-ghost:hover {
  background: var(--bg-elevated);
  color: var(--text-primary);
  transform: translateY(-1px);
}

/* ══════════════════════════════════════════════════════
   MAIN CONTENT
══════════════════════════════════════════════════════ */
.app-main {
  flex: 1;
  padding: 1.75rem 2rem;
  max-width: 100%;
}

/* ══════════════════════════════════════════════════════
   EVENT GROUP CARD
══════════════════════════════════════════════════════ */
.event-group {
  background: var(--bg-surface);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: var(--r-lg);
  margin-bottom: 1.75rem;
  border: 1px solid var(--border-strong);
  overflow: hidden;
  box-shadow: var(--shadow-md);
  transition: box-shadow 0.3s var(--ease-out), transform 0.3s var(--ease-out);
  animation: groupReveal 0.5s var(--ease-out) both;
}
.event-group:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}

@keyframes groupReveal {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Stagger children */
.event-group:nth-child(1) { animation-delay: 0.05s; }
.event-group:nth-child(2) { animation-delay: 0.10s; }
.event-group:nth-child(3) { animation-delay: 0.15s; }
.event-group:nth-child(4) { animation-delay: 0.20s; }
.event-group:nth-child(n+5) { animation-delay: 0.25s; }

.event-group.today {
  border: 1.5px solid rgba(239,68,68,.35);
  box-shadow: 0 0 0 1px rgba(239,68,68,.1), var(--shadow-md);
}

.group-header {
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
  background: var(--bg-elevated);
  gap: 0.75rem;
}

.group-date {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-family: 'Anuphan', sans-serif;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-primary);
}
.group-date-icon {
  width: 32px;
  height: 32px;
  border-radius: var(--r-sm);
  background: var(--brand-500);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  flex-shrink: 0;
}
.event-group.today .group-date-icon {
  background: var(--accent-red);
  box-shadow: 0 4px 12px rgba(239,68,68,.35);
}

.today-ribbon {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 12px;
  background: var(--accent-red);
  color: #fff;
  border-radius: var(--r-pill);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.4); }
  50%       { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}

.group-count {
  font-size: 0.72rem;
  padding: 2px 10px;
  border-radius: var(--r-pill);
  background: var(--brand-50);
  color: var(--brand-700);
  font-weight: 600;
  border: 1px solid rgba(79,70,229,.20);
}
[data-theme="dark"] .group-count {
  background: rgba(79,70,229,.18);
  color: var(--brand-300);
}

/* ══════════════════════════════════════════════════════
   DATA TABLE
══════════════════════════════════════════════════════ */
.data-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.data-table thead {
  position: sticky;
  top: 0;
  z-index: 5;
}

.data-table thead tr {
  background: linear-gradient(90deg, var(--brand-800) 0%, var(--brand-700) 100%);
}

.data-table th {
  padding: 0.75rem 1.25rem;
  font-family: 'Anuphan', sans-serif;
  font-size: 0.78rem;
  font-weight: 600;
  color: rgba(255,255,255,.9);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  text-align: left;
  border: none;
  white-space: nowrap;
  user-select: none;
}
.data-table th.center { text-align: center; }

/* Column widths */
.data-table th:nth-child(1), .data-table td:nth-child(1) { width: 10%; }
.data-table th:nth-child(2), .data-table td:nth-child(2) { width: 40%; }
.data-table th:nth-child(3), .data-table td:nth-child(3) { width: 27%; }
.data-table th:nth-child(4), .data-table td:nth-child(4) { width: 13%; text-align: center; }
.data-table th:nth-child(5), .data-table td:nth-child(5) { width: 10%; text-align: center; }

.data-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background 0.2s;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: var(--bg-elevated); }

.data-table td {
  padding: 0.875rem 1.25rem;
  vertical-align: middle;
  color: var(--text-primary);
  font-size: 0.9rem;
}

/* Striped */
.data-table tbody tr:nth-child(even) {
  background: rgba(0,0,0,.018);
}
[data-theme="dark"] .data-table tbody tr:nth-child(even) {
  background: rgba(255,255,255,.025);
}
[data-theme="dark"] .data-table tbody tr:hover {
  background: rgba(255,255,255,.04);
}

/* Time cell */
.time-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.time-value {
  font-family: 'Anuphan', monospace;
  font-size: 1rem;
  font-weight: 600;
  color: var(--brand-600);
  font-variant-numeric: tabular-nums;
}
[data-theme="dark"] .time-value { color: var(--brand-300); }

/* Countdown badge */
.countdown-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 10px;
  border-radius: var(--r-pill);
  font-size: 0.68rem;
  font-weight: 700;
  white-space: nowrap;
  letter-spacing: 0.02em;
}
.countdown-badge.active {
  background: #d1fae5;
  color: #065f46;
  animation: pulse-green 2s ease-in-out infinite;
}
.countdown-badge.upcoming {
  background: #fef3c7;
  color: #92400e;
}
[data-theme="dark"] .countdown-badge.active  { background: rgba(6,95,70,.3);  color: #6ee7b7; }
[data-theme="dark"] .countdown-badge.upcoming { background: rgba(146,64,14,.3); color: #fcd34d; }

@keyframes pulse-green {
  0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,.4); }
  50%     { box-shadow: 0 0 0 5px rgba(16,185,129,0); }
}

/* Title cell */
.event-title {
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1.4;
  display: block;
  margin-bottom: 4px;
}
.detail-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 9px;
  border-radius: var(--r-pill);
  font-size: 0.68rem;
  font-weight: 600;
  background: var(--brand-50);
  color: var(--brand-600);
  border: 1px solid rgba(0,120,212,.15);
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}
.detail-chip:hover {
  background: var(--brand-500);
  color: #fff;
  border-color: transparent;
  transform: translateY(-1px);
  box-shadow: var(--shadow-brand);
}
[data-theme="dark"] .detail-chip {
  background: rgba(0,120,212,.15);
  color: var(--brand-300);
  border-color: rgba(0,120,212,.25);
}
[data-theme="dark"] .detail-chip:hover { background: var(--brand-500); color: #fff; }

/* Assignee */
.assignee-cell { white-space: pre-line; font-size: 0.85rem; line-height: 1.5; }

/* File attachment */
.file-chip {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 8px 12px;
  border-radius: var(--r-md);
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  color: var(--brand-600);
  text-decoration: none;
  transition: all 0.25s var(--ease-out);
  min-width: 60px;
}
.file-chip:hover {
  background: var(--brand-500);
  color: #fff;
  border-color: transparent;
  transform: translateY(-3px) scale(1.05);
  box-shadow: var(--shadow-brand);
}
.file-chip i { font-size: 1.4rem; }
.file-chip-size {
  font-size: 0.65rem;
  font-family: 'Kanit', monospace;
  opacity: 0.8;
}
.file-chip:hover .file-chip-size { opacity: 1; }
[data-theme="dark"] .file-chip { color: var(--brand-300); }

/* Empty state */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  gap: 1rem;
}
.empty-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--bg-elevated);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: var(--text-muted);
  border: 2px dashed var(--border-strong);
}
.empty-title   { font-family: 'Kanit'; font-size: 1.1rem; color: var(--text-secondary); }
.empty-sub     { font-size: 0.85rem; color: var(--text-muted); }

/* ══════════════════════════════════════════════════════
   FLOATING BUTTONS
══════════════════════════════════════════════════════ */
.fab-stack {
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.75rem;
  z-index: 500;
}

.fab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 22px;
  border-radius: var(--r-pill);
  font-size: 0.88rem;
  font-weight: 600;
  font-family: 'Sarabun';
  color: #fff;
  text-decoration: none;
  border: none;
  cursor: pointer;
  box-shadow: var(--shadow-lg);
  transition: all 0.3s var(--ease-spring);
  backdrop-filter: blur(8px);
}
.fab:hover { transform: translateY(-4px) scale(1.03); color: #fff; }
.fab i { font-size: 1.1rem; }

.fab-primary {
  background: linear-gradient(135deg, var(--brand-500), var(--brand-800));
  box-shadow: var(--shadow-brand);
}
.fab-primary:hover { box-shadow: 0 16px 40px rgba(79,70,229,.45); }

.fab-leave {
  background: linear-gradient(135deg, #ef4444, #b91c1c);
  box-shadow: 0 8px 25px rgba(239,68,68,.35);
}
.fab-leave:hover { box-shadow: 0 16px 40px rgba(239,68,68,.5); }

.fab-left-stack {
  position: fixed;
  bottom: 2rem;
  left: 2rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
  z-index: 500;
}

/* Badge count */
.badge-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  background: #fff;
  color: #ef4444;
  border-radius: var(--r-pill);
  font-size: 0.72rem;
  font-weight: 800;
}

/* ══════════════════════════════════════════════════════
   MODAL / POPUP  
══════════════════════════════════════════════════════ */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15,10,40,.65);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 1.5rem;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s, visibility 0.3s;
}
.modal-overlay.open { opacity: 1; visibility: visible; }

.modal-sheet {
  background: var(--bg-surface);
  border-radius: var(--r-xl);
  width: 100%;
  max-width: 1100px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-xl);
  border: 1px solid var(--border);
  transform: translateY(30px) scale(0.97);
  transition: transform 0.4s var(--ease-spring);
}
.modal-overlay.open .modal-sheet { transform: translateY(0) scale(1); }

.modal-header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  background: var(--bg-elevated);
  flex-shrink: 0;
}

.modal-title {
  font-family: 'Kanit', sans-serif;
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.3;
}
.modal-meta {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-top: 4px;
}

.modal-close {
  width: 40px;
  height: 40px;
  border-radius: var(--r-pill);
  border: 1px solid var(--border-strong);
  background: transparent;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1.1rem;
  flex-shrink: 0;
  transition: all 0.25s;
}
.modal-close:hover {
  background: var(--accent-red);
  color: #fff;
  border-color: transparent;
  transform: rotate(90deg);
}

.modal-body {
  padding: 1.75rem 2rem;
  overflow-y: auto;
  flex: 1;
}

.modal-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.25rem;
}

.detail-card {
  padding: 1.25rem;
  background: var(--bg-elevated);
  border-radius: var(--r-md);
  border: 1px solid var(--border);
  border-left: 4px solid var(--brand-500);
  transition: all 0.25s var(--ease-out);
}
.detail-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
  border-left-color: var(--brand-400);
}
.detail-card-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-muted);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.detail-card-label i { color: var(--brand-500); font-size: 1rem; }
.detail-card-value {
  font-size: 0.92rem;
  color: var(--text-primary);
  line-height: 1.6;
  word-break: break-word;
}

.status-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 14px;
  border-radius: var(--r-pill);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}
.status-normal    { background: #d1fae5; color: #065f46; }
.status-postponed { background: #fef3c7; color: #92400e; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
[data-theme="dark"] .status-normal    { background: rgba(6,95,70,.25);   color: #6ee7b7; }
[data-theme="dark"] .status-postponed { background: rgba(146,64,14,.25); color: #fcd34d; }
[data-theme="dark"] .status-cancelled { background: rgba(153,27,27,.25); color: #fca5a5; }

.file-preview-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--bg-elevated);
  border-radius: var(--r-md);
  border: 1px solid var(--border);
  margin-top: 0.75rem;
  transition: all 0.2s;
  text-decoration: none;
}
.file-preview-row:hover {
  background: var(--brand-50);
  border-color: var(--brand-500);
  transform: translateX(6px);
}
[data-theme="dark"] .file-preview-row:hover { background: rgba(79,70,229,.12); }
.file-preview-row i { font-size: 2rem; color: var(--brand-500); flex-shrink: 0; }
.file-preview-name { font-weight: 600; color: var(--text-primary); font-size: 0.88rem; word-break: break-all; }
.file-preview-size { font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; }
.file-preview-dl   { margin-left: auto; color: var(--brand-500); font-size: 1.2rem; flex-shrink: 0; }

.modal-footer {
  padding: 1.25rem 2rem;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  background: var(--bg-elevated);
  flex-shrink: 0;
}

.modal-btn {
  padding: 9px 22px;
  border-radius: var(--r-sm);
  font-size: 0.88rem;
  font-weight: 600;
  font-family: 'Sarabun';
  cursor: pointer;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.25s;
}
.modal-btn-primary { background: var(--brand-500); color: #fff; box-shadow: var(--shadow-brand); }
.modal-btn-primary:hover { background: var(--brand-600); transform: translateY(-2px); }
.modal-btn-ghost { background: var(--bg-base); color: var(--text-secondary); border: 1px solid var(--border-strong); }
.modal-btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); }

/* ══════════════════════════════════════════════════════
   LEAVE POPUP (narrower)
══════════════════════════════════════════════════════ */
.leave-sheet { max-width: 720px; }

/* Leave card */
.leave-card {
  background: var(--bg-elevated);
  border-radius: var(--r-md);
  padding: 1.25rem;
  margin-bottom: 1rem;
  border: 1px solid var(--border);
  border-left: 4px solid var(--brand-500);
  transition: all 0.25s var(--ease-out);
}
.leave-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.leave-card-name {
  font-family: 'Kanit';
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}
.leave-type-tag {
  display: inline-block;
  padding: 2px 10px;
  border-radius: var(--r-pill);
  font-size: 0.72rem;
  font-weight: 700;
  background: rgba(79,70,229,.12);
  color: var(--brand-700);
  letter-spacing: 0.04em;
}
[data-theme="dark"] .leave-type-tag { background: rgba(79,70,229,.28); color: var(--brand-300); }

.leave-row {
  display: flex;
  gap: 0.75rem;
  margin-top: 0.5rem;
  font-size: 0.85rem;
}
.leave-row-label { color: var(--text-muted); width: 90px; flex-shrink: 0; }
.leave-row-val   { color: var(--text-primary); }

.leave-scroll {
  max-height: 65vh;
  overflow-y: auto;
  padding-right: 4px;
}
.leave-scroll::-webkit-scrollbar { width: 5px; }
.leave-scroll::-webkit-scrollbar-track { background: transparent; }
.leave-scroll::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 3px; }

/* ══════════════════════════════════════════════════════
   FOOTER
══════════════════════════════════════════════════════ */
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
  font-family: 'Kanit', monospace;
}

/* ══════════════════════════════════════════════════════
   BACKGROUND ORBS — login.php style
══════════════════════════════════════════════════════ */
.bg-orb {
  position: fixed;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.35;
  pointer-events: none;
  z-index: 0;
  animation: orbFloat 12s ease-in-out infinite;
}
.bg-orb-1 {
  width: 520px; height: 520px;
  background: radial-gradient(circle, #6366f1 0%, transparent 70%);
  top: -160px; left: -120px;
  animation-delay: 0s;
}
.bg-orb-2 {
  width: 400px; height: 400px;
  background: radial-gradient(circle, #3730a3 0%, transparent 70%);
  bottom: -120px; right: -100px;
  animation-delay: -5s;
}
.bg-orb-3 {
  width: 280px; height: 280px;
  background: radial-gradient(circle, #a5b4fc 0%, transparent 70%);
  top: 45%; left: 55%;
  opacity: 0.18;
  animation-delay: -9s;
}
[data-theme="dark"] .bg-orb { opacity: 0.25; }
[data-theme="dark"] .bg-orb-3 { opacity: 0.12; }

@keyframes orbFloat {
  0%, 100% { transform: translateY(0) scale(1); }
  50%       { transform: translateY(-30px) scale(1.06); }
}

/* ensure content sits above orbs */
.app-header, .app-toolbar, .app-main, .app-footer { position: relative; z-index: 1; }

/* ══════════════════════════════════════════════════════
   SCROLLBAR GLOBAL
══════════════════════════════════════════════════════ */
::-webkit-scrollbar       { width: 7px; height: 7px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

/* ══════════════════════════════════════════════════════
   LOADING SKELETON
══════════════════════════════════════════════════════ */
.skeleton {
  background: linear-gradient(90deg, var(--bg-elevated) 25%, var(--border) 50%, var(--bg-elevated) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: var(--r-sm);
}
@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ══════════════════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════════════════ */
@media (max-width: 900px) {
  .header-inner { grid-template-columns: 1fr; gap: 0.5rem; }
  .header-actions { justify-content: flex-start; }
  .header-center { display: none; }
  .app-toolbar { flex-wrap: wrap; }
  .app-main { padding: 1rem; }
  .data-table th:nth-child(3), .data-table td:nth-child(3) { display: none; }
  .fab-stack, .fab-left-stack { bottom: 1rem; }
  .fab-stack { right: 1rem; }
  .fab-left-stack { left: 1rem; }
  .fab { padding: 10px 16px; font-size: 0.82rem; }
}

@media (max-width: 600px) {
  .data-table th:nth-child(4), .data-table td:nth-child(4) { display: none; }
  .modal-grid { grid-template-columns: 1fr; }
}

/* ══════════════════════════════════════════════════════
   NOTIFICATION PERMISSION TOAST
══════════════════════════════════════════════════════ */
.toast-stack {
  position: fixed;
  top: 1.25rem;
  right: 1.25rem;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  pointer-events: none;
}
.toast {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  padding: 0.9rem 1.25rem;
  box-shadow: var(--shadow-lg);
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.85rem;
  color: var(--text-primary);
  pointer-events: all;
  animation: toastIn 0.4s var(--ease-spring) both;
  max-width: 340px;
}
@keyframes toastIn {
  from { transform: translateX(100%); opacity: 0; }
  to   { transform: translateX(0);    opacity: 1; }
}
.toast.out {
  animation: toastOut 0.3s var(--ease-out) forwards;
}
@keyframes toastOut {
  to { transform: translateX(110%); opacity: 0; }
}
.toast-icon { font-size: 1.3rem; flex-shrink: 0; }
.toast-icon.success { color: var(--accent-green); }
.toast-icon.warning { color: var(--accent-orange); }
.toast-icon.info    { color: var(--brand-500); }
</style>
<script src="/theme.js"></script>
</head>
<body>

<!-- Background orbs (login.php style) -->
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>
<div class="bg-orb bg-orb-3" aria-hidden="true"></div>

<!-- ══════════════════════════════════════════════ HEADER ══ -->
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

    <!-- Clock (center) -->
    <div class="header-center">
      <div class="header-clock" id="liveClock">--:--:--</div>
      <div class="header-date" id="liveDate"><?= htmlspecialchars($current_date_thai) ?></div>
    </div>

    <!-- Actions -->
    <div class="header-actions">
      <span class="version-chip" onclick="window.location.href='/changelogs'" title="ดูบันทึกการเปลี่ยนแปลง">
        v<?= htmlspecialchars($current_version) ?>
      </span>
      <button class="theme-toggle" id="themeToggle" title="สลับธีม">
        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
      </button>
      <span id="autoModeBadge">
        <i class="bi bi-brightness-auto"></i> Auto
      </span>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════════════ TOOLBAR ══ -->
<div class="app-toolbar">
  <div class="toolbar-left">
    <a href="/calendar" id="calendar-view-btn" class="tb-btn tb-btn-primary">
      <i class="bi bi-calendar3"></i> มุมมองปฏิทิน
    </a>
    <button class="tb-btn tb-btn-ghost" onclick="window.location.reload()">
      <i class="bi bi-arrow-clockwise"></i> รีเฟรช
    </button>
  </div>

  <div class="toolbar-center">
    <?php if ($total_events > 0): ?>
      <span class="status-pill has-events">
        <i class="bi bi-calendar-check"></i> มีวาระงาน <?= $total_events ?> รายการ
      </span>
    <?php elseif ($completed_events > 0): ?>
      <span class="status-pill done-events">
        <i class="bi bi-check-circle"></i> วาระงานวันนี้เสร็จสิ้นแล้ว
      </span>
    <?php else: ?>
      <span class="status-pill no-events">
        <i class="bi bi-calendar-x"></i> ไม่มีวาระงานในวันนี้
      </span>
    <?php endif; ?>
  </div>

  <div class="toolbar-right">
    <button class="tb-btn tb-btn-ghost" onclick="window.location.href='/manage'">
      <i class="bi bi-gear"></i> จัดการระบบ
    </button>
    <button class="tb-btn tb-btn-ghost" id="notificationBtn">
      <i class="bi bi-bell"></i> แจ้งเตือน
    </button>
  </div>
</div>

<!-- ══════════════════════════════════════════════ MAIN ══ -->
<main class="app-main">

  <?php if (empty($grouped_events)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
      <div class="empty-title">ไม่มีกิจกรรมที่กำลังจะมาถึง</div>
      <div class="empty-sub">ระบบจะแสดงกิจกรรมที่ยังไม่เลื่อนหรือยกเลิกในที่นี้</div>
    </div>

  <?php else: foreach ($grouped_events as $date => $events):
    $dow      = date('l', strtotime($date));
    $thai_day = $thai_days_full[$dow];
    $month_en = date('M', strtotime($date));
    $day_num  = date('j', strtotime($date));
    $thai_mon = $thai_months_full[$month_en];
    $year_be  = date('Y', strtotime($date)) + 543;
    $fmt_date = "วัน{$thai_day}ที่ {$day_num} {$thai_mon} พ.ศ. {$year_be}";
    $is_today = ($date === $current_date);
  ?>

    <div class="event-group <?= $is_today ? 'today' : '' ?>">
      <!-- Group header -->
      <div class="group-header">
        <div class="group-date">
          <div class="group-date-icon">
            <i class="bi bi-calendar<?= $is_today ? '-heart' : '' ?>"></i>
          </div>
          <span><?= $fmt_date ?></span>
          <?php if ($is_today): ?>
            <span class="today-ribbon"><i class="bi bi-circle-fill" style="font-size:.5rem"></i> วันนี้</span>
          <?php endif; ?>
        </div>
        <span class="group-count"><?= count($events) ?> วาระ</span>
      </div>

      <!-- Table -->
      <div style="overflow-x:auto">
        <table class="data-table">
          <thead>
            <tr>
              <th class="center">เวลา</th>
              <th>เรื่อง</th>
              <th>สถานที่</th>
              <th class="center">ผู้ปฏิบัติ</th>
              <th class="center">เอกสาร</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($events as $ev):
              $time_str  = date('H:i', strtotime($ev['time_from'])) . ' น.';
              $ev_dt     = $ev['date'] . ' ' . $ev['time_from'];
              $is_past   = (strtotime($ev_dt) <= time());
              $file_size = $ev['real_file_size'] ?? $ev['file_size'] ?? null;
              $ev_json   = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');
            ?>
            <tr>
              <!-- Time -->
              <td>
                <div class="time-cell">
                  <span class="time-value"><?= $time_str ?></span>
                  <?php if ($is_today): ?>
                    <span class="countdown-badge <?= $is_past ? 'active' : 'upcoming' ?>"
                          data-event-time="<?= htmlspecialchars($ev_dt) ?>">
                      <i class="bi bi-<?= $is_past ? 'play-circle' : 'hourglass-split' ?>"></i>
                      <?= $is_past ? 'กำลังดำเนินการ' : 'รอเริ่ม' ?>
                    </span>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Title -->
              <td>
                <span class="event-title"><?= htmlspecialchars($ev['title']) ?></span>
                <button class="detail-chip" onclick='showEventDetails(<?= $ev_json ?>)'>
                  <i class="bi bi-info-circle"></i> รายละเอียด
                </button>
              </td>

              <!-- Location -->
              <td><?= htmlspecialchars($ev['location']) ?></td>

              <!-- Assignee -->
              <td><div class="assignee-cell"><?= nl2br(htmlspecialchars($ev['description'])) ?></div></td>

              <!-- File -->
              <td>
                <?php if ($ev['file_name']): ?>
                  <a href="/uploads/<?= htmlspecialchars($ev['file_name']) ?>" target="_blank" class="file-chip">
                    <i class="bi <?= getFileIcon($ev['file_name']) ?>"></i>
                    <span class="file-chip-size"><?= formatFileSize($file_size) ?></span>
                  </a>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:.85rem">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endforeach; endif; ?>

</main>

<!-- ══════════════════════════════════════════════ FLOATING BUTTONS ══ -->
<div class="fab-left-stack">
  <button class="fab fab-leave" id="leaveFab"
          onclick="showLeavePopup()"
          style="display:<?= $leave_count > 0 ? 'flex' : 'none' ?>">
    <i class="bi bi-person-x-fill"></i>
    แจ้งเตือนการลา
    <span class="badge-count" id="leaveCountBadge"><?= $leave_count ?></span>
  </button>
</div>

<!-- ══════════════════════════════════════════════ EVENT DETAIL MODAL ══ -->
<div class="modal-overlay" id="eventModal" role="dialog" aria-modal="true">
  <div class="modal-sheet">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modalTitle">—</div>
        <div class="modal-meta"  id="modalMeta">—</div>
      </div>
      <button class="modal-close" onclick="closeEventModal()" aria-label="ปิด">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-body">
      <div class="modal-grid" id="modalGrid"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn modal-btn-ghost" onclick="closeEventModal()">
        <i class="bi bi-x-circle"></i> ปิด
      </button>
      <button class="modal-btn modal-btn-primary" id="modalEditBtn" style="display:none"
              onclick="editEvent(currentEventId)">
        <i class="bi bi-pencil-square"></i> แก้ไขกิจกรรม
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ LEAVE MODAL ══ -->
<div class="modal-overlay" id="leaveModal" role="dialog" aria-modal="true">
  <div class="modal-sheet leave-sheet">
    <div class="modal-header">
      <div>
        <div class="modal-title">
          <i class="bi bi-person-x-fill me-2" style="color:var(--accent-red)"></i>
          รายการแจ้งเตือนการลา
        </div>
        <div class="modal-meta">พบ <span id="leaveCountModal"><?= $leave_count ?></span> รายการ</div>
      </div>
      <button class="modal-close" onclick="closeLeaveModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <div class="leave-scroll" id="leaveContainer">
        <?php if ($leave_count == 0): ?>
          <div class="empty-state" style="padding:2rem">
            <div class="empty-icon"><i class="bi bi-person-check"></i></div>
            <div class="empty-title">ไม่มีข้อมูลการลาในขณะนี้</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn modal-btn-ghost" onclick="closeLeaveModal()">
        <i class="bi bi-x-circle"></i> ปิด
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════ TOAST STACK ══ -->
<div class="toast-stack" id="toastStack"></div>

<!-- ══════════════════════════════════════════════ FOOTER ══ -->
<footer class="app-footer">
  <strong>© <?= date('Y') ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</strong><br>
  <span class="perf">
    <i class="bi bi-lightning-charge-fill"></i>
    สร้างหน้าในเวลา <?= number_format($generation_time, 4) ?> วินาที
    &nbsp;|&nbsp; PHP <?= phpversion() ?>
  </span>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
'use strict';

/* ══════════════════════════════════════════════════════
   CONSTANTS & STATE
══════════════════════════════════════════════════════ */
const VAPID_PUBLIC_KEY = 'BAYl0GpGYm6ohG-SPMOviCjfYpTRs0CFQW-_okleCSD-tYUYBDbbNjSsCGkKHAsLoy3VP-14Ze-YyQByA8YpsCA';
const UPCOMING_EVENTS  = <?= json_encode($upcoming_events) ?>;
const THAI_MONTHS      = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                          'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
const THAI_DAYS        = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];

let currentEventId   = null;
let notifiedEventIds = new Set(JSON.parse(localStorage.getItem('notifiedEvents') || '[]'));

/* ══════════════════════════════════════════════════════
   THEME MANAGER
══════════════════════════════════════════════════════ */
// ── Theme handled by /theme.js (loaded in <head>) ──
// ThemeManager.toggle() / ThemeManager.setMode('light'|'dark'|'auto') available globally

/* ══════════════════════════════════════════════════════
   LIVE CLOCK
══════════════════════════════════════════════════════ */
function startClock() {
  const clockEl = document.getElementById('liveClock');
  const tick = () => {
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString('th-TH', { hour12: false });
  };
  tick();
  setInterval(tick, 1000);
}

/* ══════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
══════════════════════════════════════════════════════ */
function showToast(message, type = 'info', duration = 4000) {
  const stack = document.getElementById('toastStack');
  const iconMap = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-bell-fill' };
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `
    <i class="bi ${iconMap[type] || iconMap.info} toast-icon ${type}"></i>
    <span>${message}</span>
  `;
  stack.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('out');
    setTimeout(() => toast.remove(), 350);
  }, duration);
}

/* ══════════════════════════════════════════════════════
   COUNTDOWN BADGES
══════════════════════════════════════════════════════ */
function updateCountdowns() {
  document.querySelectorAll('[data-event-time]').forEach(el => {
    const eventTime = new Date(el.dataset.eventTime);
    const now = new Date();
    const diff = eventTime - now;
    const icon = el.querySelector('i');

    if (diff <= 0) {
      el.className = 'countdown-badge active';
      el.innerHTML = '<i class="bi bi-play-circle"></i> กำลังดำเนินการ';
    } else {
      el.className = 'countdown-badge upcoming';
      const h = Math.floor(diff / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      const parts = [];
      if (h > 0) parts.push(`${h} ชม.`);
      if (m > 0 || h > 0) parts.push(`${m} น.`);
      parts.push(`${s} ว.`);
      el.innerHTML = `<i class="bi bi-hourglass-split"></i> อีก ${parts.join(' ')}`;
    }
  });
}

/* ══════════════════════════════════════════════════════
   EVENT DETAIL MODAL
══════════════════════════════════════════════════════ */
function showEventDetails(event) {
  currentEventId = event.id;

  document.getElementById('modalTitle').textContent = event.title || '—';
  document.getElementById('modalMeta').textContent  =
    (event.date ? formatThaiDate(event.date) : '') +
    (event.time_from ? ' · ' + event.time_from.slice(0,5) + ' น.' : '') +
    (event.time_to   ? ' – ' + event.time_to.slice(0,5)   + ' น.' : '');

  const statusMap = {
    'เลื่อน':   { cls: 'status-postponed', icon: 'bi-pause-circle' },
    'ยกเลิก':   { cls: 'status-cancelled', icon: 'bi-x-circle'     },
    'ปกติ':     { cls: 'status-normal',    icon: 'bi-check-circle'  }
  };
  const st  = statusMap[event.status] || statusMap['ปกติ'];
  const stTag = `<span class="status-tag ${st.cls}"><i class="bi ${st.icon}"></i> ${event.status || 'ปกติ'}</span>`;

  // File section
  let fileHtml = '<span style="color:var(--text-muted)">ไม่มีไฟล์แนบ</span>';
  if (event.file_name) {
    const iconCls = getFileIconClass(event.file_name);
    const sizeTxt = formatFileSize(event.real_file_size || event.file_size);
    fileHtml = `
      <a href="/uploads/${event.file_name}" target="_blank" class="file-preview-row">
        <i class="bi ${iconCls}"></i>
        <div>
          <div class="file-preview-name">${event.file_name}</div>
          <div class="file-preview-size">${sizeTxt}</div>
        </div>
        <i class="bi bi-download file-preview-dl"></i>
      </a>`;
  }

  const cards = [
    { icon: 'bi-geo-alt',      label: 'สถานที่',      value: event.location    || '—' },
    { icon: 'bi-person-lines-fill', label: 'ผู้ปฏิบัติ', value: (event.description || '—').replace(/\n/g,'<br>') },
    { icon: 'bi-flag',         label: 'สถานะ',        value: stTag             },
    { icon: 'bi-card-text',    label: 'หมายเหตุ',     value: event.notes       || '—' },
    { icon: 'bi-pencil',       label: 'ผู้บันทึก',    value: event.created_by  || '—' },
    { icon: 'bi-clock-history','label': 'บันทึกเมื่อ', value: formatDateTimeLocal(event.created_at) },
    { icon: 'bi-paperclip',    label: 'ไฟล์แนบ',     value: fileHtml, fullWidth: true },
  ].filter(c => c.value !== '—' || c.icon === 'bi-paperclip');

  document.getElementById('modalGrid').innerHTML = cards.map(c => `
    <div class="detail-card" ${c.fullWidth ? 'style="grid-column:1/-1"' : ''}>
      <div class="detail-card-label"><i class="bi ${c.icon}"></i>${c.label}</div>
      <div class="detail-card-value">${c.value}</div>
    </div>`).join('');

  document.getElementById('modalEditBtn').style.display = event.id ? 'flex' : 'none';
  document.getElementById('eventModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeEventModal() {
  document.getElementById('eventModal').classList.remove('open');
  document.body.style.overflow = '';
  currentEventId = null;
}

function editEvent(id) {
  window.location.href = `/editEvent?id=${id}`;
}

/* ══════════════════════════════════════════════════════
   LEAVE MODAL
══════════════════════════════════════════════════════ */
function showLeavePopup() {
  document.getElementById('leaveModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  fetchLeaveData();
}
function closeLeaveModal() {
  document.getElementById('leaveModal').classList.remove('open');
  document.body.style.overflow = '';
}

async function fetchLeaveData() {
  try {
    const res  = await fetch('get_leaves.php');
    const data = await res.json();
    updateLeaveBadge(data.length);
    renderLeaveCards(data);
  } catch (err) {
    console.error('Leave fetch error:', err);
  }
}

function updateLeaveBadge(count) {
  document.getElementById('leaveCountBadge').textContent = count;
  document.getElementById('leaveCountModal').textContent = count;
  const fab = document.getElementById('leaveFab');
  fab.style.display = count > 0 ? 'flex' : 'none';
}

function renderLeaveCards(leaves) {
  const container = document.getElementById('leaveContainer');
  if (leaves.length === 0) {
    container.innerHTML = `
      <div class="empty-state" style="padding:2rem">
        <div class="empty-icon"><i class="bi bi-person-check"></i></div>
        <div class="empty-title">ไม่มีข้อมูลการลาในขณะนี้</div>
      </div>`;
    return;
  }
  container.innerHTML = leaves.map(l => `
    <div class="leave-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
        <span class="leave-card-name">${l.name}</span>
        <span class="leave-type-tag">${l.leave_type}</span>
      </div>
      ${l.location ? `<div class="leave-row"><span class="leave-row-label">สถานที่</span><span class="leave-row-val">${l.location}</span></div>` : ''}
      <div class="leave-row"><span class="leave-row-label">วันที่เริ่ม</span><span class="leave-row-val">${formatThaiDate(l.start_datetime)}</span></div>
      <div class="leave-row"><span class="leave-row-label">วันที่สิ้นสุด</span><span class="leave-row-val">${formatThaiDate(l.end_datetime)}</span></div>
      ${l.details ? `<div class="leave-row"><span class="leave-row-label">รายละเอียด</span><span class="leave-row-val">${l.details}</span></div>` : ''}
    </div>`).join('');
}

/* ══════════════════════════════════════════════════════
   DATE / TIME UTILITIES
══════════════════════════════════════════════════════ */
function formatThaiDate(str) {
  if (!str) return '—';
  const d = new Date(str);
  return `วัน${THAI_DAYS[d.getDay()]}ที่ ${d.getDate()} ${THAI_MONTHS[d.getMonth()]} พ.ศ. ${d.getFullYear()+543}`;
}

function formatDateTimeLocal(str) {
  if (!str) return '—';
  const d = new Date(str);
  return d.toLocaleString('th-TH', { dateStyle:'medium', timeStyle:'short' });
}

function getFileIconClass(filename) {
  if (!filename) return 'bi-file-earmark';
  const ext = filename.split('.').pop().toLowerCase();
  const map = {
    pdf:'bi-filetype-pdf', jpg:'bi-file-earmark-image', jpeg:'bi-file-earmark-image',
    png:'bi-file-earmark-image', gif:'bi-file-earmark-image',
    doc:'bi-file-earmark-word', docx:'bi-file-earmark-word',
    xls:'bi-file-earmark-excel', xlsx:'bi-file-earmark-excel',
    ppt:'bi-file-earmark-ppt', pptx:'bi-file-earmark-ppt',
    zip:'bi-file-earmark-zip', rar:'bi-file-earmark-zip'
  };
  return map[ext] || 'bi-file-earmark';
}

function formatFileSize(bytes) {
  if (!bytes) return 'ไม่ทราบขนาด';
  if (bytes < 1024)    return bytes + ' B';
  if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1048576).toFixed(1) + ' MB';
}

/* ══════════════════════════════════════════════════════
   PUSH NOTIFICATIONS
══════════════════════════════════════════════════════ */
function urlBase64ToUint8Array(base64) {
  const padding = '='.repeat((4 - base64.length % 4) % 4);
  const b64 = (base64 + padding).replace(/-/g,'+').replace(/_/g,'/');
  const raw = atob(b64);
  return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

async function registerServiceWorker() {
  if (!('serviceWorker' in navigator && 'PushManager' in window)) return null;
  try {
    return await navigator.serviceWorker.register('/service-worker.js');
  } catch(e) { return null; }
}

async function requestNotificationPermission() {
  if (!('Notification' in window)) return false;
  if (Notification.permission === 'granted') return true;
  if (Notification.permission !== 'denied') {
    const p = await Notification.requestPermission();
    return p === 'granted';
  }
  return false;
}

function checkUpcomingEvents(events) {
  if (!events?.length) return;
  const now = new Date();
  events.forEach(ev => {
    if (notifiedEventIds.has(ev.id)) return;
    const evTime = new Date(ev.date + ' ' + ev.time_from);
    const minutesLeft = (evTime - now) / 60000;
    if (minutesLeft > 0 && minutesLeft <= 30) {
      const mins = Math.round(minutesLeft);
      showToast(`📅 "${ev.title}" จะเริ่มในอีก ${mins} นาที`, 'warning', 6000);
      if (Notification.permission === 'granted') {
        new Notification('แจ้งเตือนกิจกรรม', {
          body: `"${ev.title}" จะเริ่มในอีก ${mins} นาที`,
          icon: '/images/logo.jpg'
        });
      }
      notifiedEventIds.add(ev.id);
      localStorage.setItem('notifiedEvents', JSON.stringify([...notifiedEventIds]));
    }
  });
}

/* Notification toggle button */
document.getElementById('notificationBtn').addEventListener('click', async () => {
  const granted = await requestNotificationPermission();
  if (granted) {
    const reg = await registerServiceWorker();
    if (reg) {
      showToast('เปิดการแจ้งเตือนเรียบร้อยแล้ว', 'success');
    }
  } else {
    showToast('ไม่ได้รับอนุญาตการแจ้งเตือน กรุณาตรวจสอบการตั้งค่าเบราว์เซอร์', 'warning');
  }
});

/* ══════════════════════════════════════════════════════
   KEYBOARD / BACKDROP CLOSE
══════════════════════════════════════════════════════ */
document.getElementById('eventModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeEventModal();
});
document.getElementById('leaveModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeLeaveModal();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeEventModal(); closeLeaveModal(); }
});

/* ══════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  startClock();
  updateCountdowns();
  setInterval(updateCountdowns, 1000);
  fetchLeaveData();
  setInterval(fetchLeaveData, 300000);

  requestNotificationPermission().then(granted => {
    if (granted) {
      checkUpcomingEvents(UPCOMING_EVENTS);
      setInterval(() => checkUpcomingEvents(UPCOMING_EVENTS), 60000);
    }
  });

  // Auto-refresh every 5 minutes
  setTimeout(() => window.location.reload(), 300000);
});

/* Calendar view button */
document.getElementById('calendar-view-btn').addEventListener('click', e => {
  e.preventDefault();
  window.location.href = '/calendar';
});
</script>
</body>
</html>
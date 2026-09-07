<?php
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

// เชื่อมต่อฐานข้อมูล config.php
include 'config.php';

// สร้างโฟลเดอร์ Cache ถ้ายังไม่มี
$cache_base_dir = __DIR__ . '/cache';
if (!is_dir($cache_base_dir)) {
    @mkdir($cache_base_dir, 0755, true);
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

function getRamUsage(): array {
    if (PHP_OS_FAMILY === 'Windows') {
        $ram = shell_exec('wmic OS get TotalVisibleMemorySize,FreePhysicalMemory');
        $ram = array_filter(explode("\n", trim($ram)));
        $ram = explode(" ", preg_replace("/\s+/", " ", end($ram)));
        $total = round($ram[0] / 1024 / 1024, 2);
        $free  = round($ram[1] / 1024 / 1024, 2);
    } else {
        $free_out = (string) trim((string) shell_exec('free'));
        $free_arr = explode("\n", $free_out);
        $mem   = explode(" ", preg_replace("/\s+/", " ", $free_arr[1]));
        $total = round($mem[1] / 1024 / 1024, 2);
        $free  = round($mem[6] / 1024 / 1024, 2);
    }
    $usage = $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0;
    return ['total' => $total, 'free' => $free, 'used' => round($total - $free, 2), 'usage' => $usage];
}

function getCpuUsage(): float {
    if (PHP_OS_FAMILY === 'Windows') {
        $load = explode("\n", trim((string) shell_exec('wmic cpu get loadpercentage')));
        return (float)($load[1] ?? 0);
    }
    $load = sys_getloadavg();
    return round(($load[0] / max(1, (int) shell_exec('nproc'))) * 100, 2);
}

function getStorageUsage(): array {
    $total = disk_total_space("/") / (1024 ** 3);
    $free  = disk_free_space("/")  / (1024 ** 3);
    $used  = $total - $free;
    return [
        'total' => round($total, 2), 'free' => round($free, 2),
        'used'  => round($used,  2), 'usage' => round(($used / max($total, 0.001)) * 100, 2)
    ];
}

function getCacheSize(): float {
    $dirs = [__DIR__.'/cache', __DIR__.'/uploads/cache', __DIR__.'/tmp', sys_get_temp_dir().'/app_cache'];
    $size = 0;
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $n = $f->getFilename();
                    if (str_contains($n,'.cache')||str_contains($n,'.tmp')||
                        str_contains($dir,'cache')||str_contains($dir,'tmp')) {
                        $size += $f->getSize();
                    }
                }
            }
        } catch (Exception) {}
    }
    return $size / (1024 * 1024);
}

function clearCache(): bool {
    $dirs = [__DIR__.'/cache', __DIR__.'/uploads/cache', __DIR__.'/tmp', sys_get_temp_dir().'/app_cache'];
    $cleared = false;
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $path = $f->getRealPath();
                if ($f->isFile()) {
                    $n = $f->getFilename();
                    if (str_contains($n,'.cache')||str_contains($n,'.tmp')||str_contains($n,'sess_')) {
                        if (@unlink($path)) $cleared = true;
                    }
                } elseif ($f->isDir()) {
                    @rmdir($path);
                }
            }
            if (function_exists('opcache_reset')) { opcache_reset(); $cleared = true; }
        } catch (Exception) {}
    }
    return $cleared;
}

function getEventStatus(array $event): array {
    $now_time = date('H:i:s');
    $now_date = date('Y-m-d');
    $status   = $event['status'];

    if ($status === 'ยกเลิก') return ['text'=>'ยกเลิก',  'class'=>'badge-cancelled',  'icon'=>'x-circle',       'dot'=>'dot-cancelled'];
    if ($status === 'เลื่อน') return ['text'=>'เลื่อน',   'class'=>'badge-postponed',  'icon'=>'clock-history',  'dot'=>'dot-postponed'];

    $edate = $event['date'];
    $tfrom = $event['time_from'];
    $tto   = $event['time_to'];

    if ($edate === $now_date) {
        if ($tto && $now_time > $tto)
            return ['text'=>'เสร็จสิ้น',       'class'=>'badge-completed', 'icon'=>'check-circle',   'dot'=>'dot-completed'];
        if ($now_time >= $tfrom && (!$tto || $now_time <= $tto))
            return ['text'=>'กำลังดำเนินการ', 'class'=>'badge-ongoing',   'icon'=>'arrow-repeat',   'dot'=>'dot-ongoing'];
        return ['text'=>'รอดำเนินการ', 'class'=>'badge-waiting', 'icon'=>'hourglass-split', 'dot'=>'dot-waiting'];
    }
    if ($edate < $now_date) return ['text'=>'เสร็จสิ้น',    'class'=>'badge-completed', 'icon'=>'check-circle',    'dot'=>'dot-completed'];
    return                         ['text'=>'รอดำเนินการ', 'class'=>'badge-waiting',   'icon'=>'hourglass-split', 'dot'=>'dot-waiting'];
}

// ============================================================
// HANDLE ACTIONS
// ============================================================

if (($_GET['action'] ?? '') === 'clear_cache') {
    $count = 0;
    if (clearCache()) $count++;
    $sp = session_save_path();
    if (!empty($sp) && is_dir($sp)) {
        $lt = (int)(ini_get('session.gc_maxlifetime') ?: 1440);
        foreach (glob($sp.'/sess_*') ?: [] as $f) {
            if (is_file($f) && (time() - filemtime($f)) > $lt) { if (@unlink($f)) $count++; }
        }
    }
    $_SESSION[$count > 0 ? 'success' : 'error'] = $count > 0
        ? "เคลียร์ Cache สำเร็จ ($count รายการ)"
        : "ไม่พบไฟล์ Cache ที่ต้องลบ";
    header("Location: ".basename(__FILE__)); exit();
}

// ============================================================
// PROFILE UPDATE
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname      = $_SESSION['username'];
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $old_pw     = $_POST['old_password']    ?? '';
    $new_pw     = $_POST['new_password']    ?? '';
    $confirm_pw = $_POST['confirm_password']?? '';

    $uq   = mysqli_query($connect, "SELECT password FROM users WHERE username='".mysqli_real_escape_string($connect,$uname)."'");
    $urow = mysqli_fetch_assoc($uq);

    if (password_verify($old_pw, $urow['password'])) {
        $fn = mysqli_real_escape_string($connect, $first_name);
        $ln = mysqli_real_escape_string($connect, $last_name);
        mysqli_query($connect, "UPDATE users SET first_name='$fn', last_name='$ln' WHERE username='".mysqli_real_escape_string($connect,$uname)."'");
        if (!empty($new_pw) && $new_pw === $confirm_pw) {
            $hp = password_hash($new_pw, PASSWORD_BCRYPT, ['cost'=>12]);
            mysqli_query($connect, "UPDATE users SET password='$hp' WHERE username='".mysqli_real_escape_string($connect,$uname)."'");
        }
        $_SESSION['success'] = "อัพเดตข้อมูลสำเร็จ";
    } else {
        $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
    }
    header("Location: dashboard"); exit();
}

// ============================================================
// DATA FETCHING
// ============================================================

try {
    $vq = "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1";
    $vr = mysqli_query($connect, $vq);
    $current_version = mysqli_fetch_assoc($vr)['version'] ?? '1.0.0';
} catch (Exception) { $current_version = '1.0.0'; }

$uname = $_SESSION['username'];
$uq    = mysqli_query($connect, "SELECT first_name, last_name, role FROM users WHERE username='".mysqli_real_escape_string($connect,$uname)."'");
$udata = mysqli_fetch_assoc($uq);
$first_name = $udata['first_name'] ?? '';
$last_name  = $udata['last_name']  ?? '';
$role       = $udata['role']       ?? 'user';

$thai_months = [
    'Jan'=>'มกราคม','Feb'=>'กุมภาพันธ์','Mar'=>'มีนาคม','Apr'=>'เมษายน',
    'May'=>'พฤษภาคม','Jun'=>'มิถุนายน','Jul'=>'กรกฎาคม','Aug'=>'สิงหาคม',
    'Sep'=>'กันยายน','Oct'=>'ตุลาคม','Nov'=>'พฤศจิกายน','Dec'=>'ธันวาคม'
];
$thai_days = [
    'Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร','Wednesday'=>'พุธ',
    'Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์'
];
$current_date_thai = 'วัน'.$thai_days[date('l')].' ที่ '.date('j').' '.$thai_months[date('M')].' '.(date('Y')+543);

$stats_result = mysqli_query($connect, "
    SELECT
        SUM(CASE WHEN date=CURDATE() AND (time_to>CURTIME() OR time_to IS NULL) AND status NOT IN ('ยกเลิก','เลื่อน') THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN (date<CURDATE() OR (date=CURDATE() AND time_to<=CURTIME())) AND status NOT IN ('ยกเลิก','เลื่อน') THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (date>CURDATE() OR (date=CURDATE() AND time_from>=CURTIME())) AND status NOT IN ('ยกเลิก','เลื่อน') THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN status='ยกเลิก' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status='เลื่อน' THEN 1 ELSE 0 END) as postponed
    FROM events");
$stats_data = mysqli_fetch_assoc($stats_result);

$cr = mysqli_query($connect,"SELECT COUNT(*) as c FROM events WHERE date=CURDATE() AND (time_to>CURTIME() OR time_to IS NULL) AND status NOT IN ('ยกเลิก','เลื่อน')");
$total_events = mysqli_fetch_assoc($cr)['c'] ?? 0;

$cr2 = mysqli_query($connect,"SELECT COUNT(*) as c FROM events WHERE date=CURDATE() AND time_to<=CURTIME() AND status NOT IN ('ยกเลิก','เลื่อน')");
$completed_events = mysqli_fetch_assoc($cr2)['c'] ?? 0;

$dsr = mysqli_query($connect,"
    SELECT COUNT(*) as total,
        SUM(CASE WHEN date=CURDATE() AND status NOT IN ('ยกเลิก','เลื่อน') THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE()) AND status NOT IN ('ยกเลิก','เลื่อน') THEN 1 ELSE 0 END) as this_month,
        SUM(CASE WHEN YEAR(date)=YEAR(CURDATE()) AND status NOT IN ('ยกเลิก','เลื่อน') THEN 1 ELSE 0 END) as this_year
    FROM events");
$daily_stats_data = mysqli_fetch_assoc($dsr);

$ter = mysqli_query($connect, "SELECT * FROM events WHERE date=CURDATE() ORDER BY time_from");
$today_events = [];
while ($row = mysqli_fetch_assoc($ter)) $today_events[] = $row;

$ram_usage     = getRamUsage();
$cpu_usage     = getCpuUsage();
$storage_usage = getStorageUsage();
$cache_size    = getCacheSize();

// Color thresholds for system metrics
function metricColor(float $pct): string {
    if ($pct < 60) return '#10b981';
    if ($pct < 80) return '#f59e0b';
    return '#ef4444';
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="light" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0a3d54" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#061f2a" media="(prefers-color-scheme: dark)">
    <title>แดชบอร์ด — ระบบปฏิทินและนัดหมาย</title>
    <meta name="author" content="นายดักษ์พันธุ์ ศรีวนิช">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <!-- Unified Theme System — apply before first paint -->
    <script>
    (function(){
      var h=document.documentElement;
      function autoT(){var hr=new Date().getHours();return(hr>=6&&hr<18)?'light':'dark';}
      function resolve(p){return p==='auto'?autoT():(p==='dark'||p==='light'?p:autoT());}
      h.setAttribute('data-theme', resolve(localStorage.getItem('theme-pref')||'auto'));
    })();
    </script>
    <style>
    /* ═══════════════════════════════════════════════
       DESIGN TOKENS — LIGHT MODE (DEFAULT)
    ═══════════════════════════════════════════════ */
    :root {
        /* Brand colors (invariant) */
        --teal:          #0891b2;
        --teal-dim:      rgba(8,145,178,0.13);
        --teal-glow:     rgba(8,145,178,0.35);
        --blue:          #3b82f6;
        --blue-dim:      rgba(59,130,246,0.13);
        --amber:         #f59e0b;
        --amber-dim:     rgba(245,158,11,0.13);
        --red:           #ef4444;
        --red-dim:       rgba(239,68,68,0.13);
        --green:         #10b981;
        --green-dim:     rgba(16,185,129,0.13);
        --purple:        #8b5cf6;
        --purple-dim:    rgba(139,92,246,0.13);

        /* Surfaces — Light */
        --bg-base:       #f1f5f9;
        --bg-surface:    #ffffff;
        --bg-card:       #ffffff;
        --bg-card-hover: #f8fafc;
        --bg-input:      #f8fafc;

        /* Borders — Light */
        --border:        rgba(0,0,0,0.08);
        --border-strong: rgba(0,0,0,0.14);

        /* Text — Light */
        --text-primary:  #0f172a;
        --text-secondary:#475569;
        --text-muted:    #94a3b8;

        /* Shadows — Light */
        --shadow-card: 0 2px 12px rgba(0,0,0,0.07);
        --shadow-glow: 0 0 32px var(--teal-glow);

        /* Header — Light */
        --header-bg:     rgba(255,255,255,0.88);
        --nav-bg:        rgba(255,255,255,0.92);

        /* Radius / Layout */
        --radius-sm:  8px;
        --radius-md:  14px;
        --radius-lg:  20px;
        --radius-xl:  28px;
        --nav-height: 68px;
        --header-height: 72px;

        --font-main: 'Anuphan', 'Sarabun', sans-serif;
        --font-mono: 'JetBrains Mono', monospace;
    }

    /* ═══════════════════════════════════════════════
       DARK MODE — system preference (no manual override)
    ═══════════════════════════════════════════════ */
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --bg-base:       #0d1b2a;
            --bg-surface:    #112233;
            --bg-card:       #162840;
            --bg-card-hover: #1c3354;
            --bg-input:      #0d1b2a;
            --border:        rgba(255,255,255,0.07);
            --border-strong: rgba(255,255,255,0.14);
            --text-primary:  #e2edf8;
            --text-secondary:#8ba3be;
            --text-muted:    #4d6278;
            --shadow-card:   0 4px 24px rgba(0,0,0,0.35);
            --header-bg:     rgba(13,27,42,0.88);
            --nav-bg:        rgba(13,27,42,0.92);
            --teal:          #0ccfbd;
            --teal-dim:      rgba(12,207,189,0.15);
            --teal-glow:     rgba(12,207,189,0.35);
            --blue-dim:      rgba(59,130,246,0.15);
            --amber-dim:     rgba(245,158,11,0.15);
            --red-dim:       rgba(239,68,68,0.15);
            --green-dim:     rgba(16,185,129,0.15);
            --purple-dim:    rgba(139,92,246,0.15);
        }
    }

    /* ═══════════════════════════════════════════════
       DARK MODE — manual override via data-theme="dark"
    ═══════════════════════════════════════════════ */
    [data-theme="dark"] {
        --bg-base:       #0d1b2a;
        --bg-surface:    #112233;
        --bg-card:       #162840;
        --bg-card-hover: #1c3354;
        --bg-input:      #0d1b2a;
        --border:        rgba(255,255,255,0.07);
        --border-strong: rgba(255,255,255,0.14);
        --text-primary:  #e2edf8;
        --text-secondary:#8ba3be;
        --text-muted:    #4d6278;
        --shadow-card:   0 4px 24px rgba(0,0,0,0.35);
        --header-bg:     rgba(13,27,42,0.88);
        --nav-bg:        rgba(13,27,42,0.92);
        --teal:          #0ccfbd;
        --teal-dim:      rgba(12,207,189,0.15);
        --teal-glow:     rgba(12,207,189,0.35);
        --blue-dim:      rgba(59,130,246,0.15);
        --amber-dim:     rgba(245,158,11,0.15);
        --red-dim:       rgba(239,68,68,0.15);
        --green-dim:     rgba(16,185,129,0.15);
        --purple-dim:    rgba(139,92,246,0.15);
    }

    /* ═══════════════════════════════════════════════
       THEME TOGGLE BUTTON
    ═══════════════════════════════════════════════ */
    .theme-toggle-btn {
        width: 36px; height: 36px; border-radius: 50%;
        background: var(--teal-dim);
        border: 1px solid var(--border-strong);
        color: var(--teal);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: .92rem; flex-shrink: 0;
        transition: background .2s, transform .3s;
    }
    .theme-toggle-btn:hover {
        background: var(--teal-dim);
        transform: rotate(20deg) scale(1.08);
    }
    .theme-toggle-btn .icon-sun,
    .theme-toggle-btn .icon-moon { transition: opacity .2s, transform .3s; }
    [data-theme="dark"] .theme-toggle-btn .icon-sun  { opacity: 1; transform: scale(1); }
    [data-theme="dark"] .theme-toggle-btn .icon-moon { opacity: 0; transform: scale(.5); position: absolute; }
    [data-theme="light"] .theme-toggle-btn .icon-sun  { opacity: 0; transform: scale(.5); position: absolute; }
    [data-theme="light"] .theme-toggle-btn .icon-moon { opacity: 1; transform: scale(1); }

    /* ═══════════════════════════════════════════════
       RESET & BASE
    ═══════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
        font-family: var(--font-main);
        background: var(--bg-base);
        color: var(--text-primary);
        font-size: 15px;
        line-height: 1.65;
        padding-top: var(--header-height);
        padding-bottom: calc(var(--nav-height) + 20px);
        min-height: 100vh;
        overflow-x: hidden;
    }
    body::before {
        content: '';
        position: fixed; inset: 0; z-index: -1;
        background:
            radial-gradient(ellipse 80% 40% at 10% 0%,   rgba(8,145,178,0.06) 0%, transparent 60%),
            radial-gradient(ellipse 60% 50% at 90% 100%, rgba(59,130,246,0.06) 0%, transparent 60%),
            var(--bg-base);
        pointer-events: none;
    }
    a { text-decoration: none !important; color: inherit; }
    h1,h2,h3,h4,h5,h6 { font-weight: 600; line-height: 1.3; }

    /* ═══════════════════════════════════════════════
       KEYFRAMES
    ═══════════════════════════════════════════════ */
    @keyframes fadeUp   { from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none} }
    @keyframes fadeIn   { from{opacity:0}to{opacity:1} }
    @keyframes pulse    { 0%,100%{opacity:1}50%{opacity:.5} }
    @keyframes spin     { to{transform:rotate(360deg)} }
    @keyframes shimmer  { to{background-position:200% center} }
    @keyframes barGrow  { from{width:0}to{width:var(--w)} }

    .fade-up  { animation: fadeUp  0.55s cubic-bezier(.22,1,.36,1) both; }
    .d1       { animation-delay:.07s }
    .d2       { animation-delay:.14s }
    .d3       { animation-delay:.21s }
    .d4       { animation-delay:.28s }
    .d5       { animation-delay:.35s }
    .d6       { animation-delay:.42s }

    /* ═══════════════════════════════════════════════
       HEADER
    ═══════════════════════════════════════════════ */
    .app-header {
        position: fixed; top:0; left:0; right:0;
        height: var(--header-height);
        background: var(--header-bg);
        backdrop-filter: blur(20px) saturate(1.4);
        -webkit-backdrop-filter: blur(20px) saturate(1.4);
        border-bottom: 1px solid var(--border);
        z-index: 1050;
        display: flex; align-items: center;
        padding: 0 16px;
        gap: 12px;
    }
    .header-logo {
        width: 40px; height: 40px; border-radius: 10px;
        object-fit: cover;
        border: 1px solid var(--border-strong);
        flex-shrink: 0;
    }
    .header-titles { flex: 1; min-width: 0; }
    .header-titles h1 {
        font-size: .95rem; font-weight: 600;
        color: var(--text-primary); margin: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .header-titles p {
        font-size: .72rem; color: var(--text-muted);
        margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .header-version {
        font-family: var(--font-mono);
        font-size: .65rem; color: var(--teal);
        background: var(--teal-dim);
        border: 1px solid rgba(12,207,189,.3);
        padding: 3px 8px; border-radius: 20px;
        flex-shrink: 0; cursor: pointer;
        transition: all .2s;
    }
    .header-version:hover { background: rgba(12,207,189,.25); }

    /* ═══════════════════════════════════════════════
       MAIN LAYOUT
    ═══════════════════════════════════════════════ */
    .page-content { padding: 14px 14px 0; max-width: 520px; margin: 0 auto; }

    /* ═══════════════════════════════════════════════
       ALERT SYSTEM
    ═══════════════════════════════════════════════ */
    .ent-alert {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 12px 14px; border-radius: var(--radius-md);
        margin-bottom: 14px; font-size: .875rem;
        border: 1px solid; animation: fadeUp .3s ease;
    }
    .ent-alert-success {
        background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3);
        color: #34d399;
    }
    .ent-alert-danger {
        background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3);
        color: #f87171;
    }
    .ent-alert .btn-close { filter: invert(1) opacity(.6); margin-left: auto; }

    /* ═══════════════════════════════════════════════
       WELCOME CARD
    ═══════════════════════════════════════════════ */
    .welcome-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 18px;
        margin-bottom: 12px;
        position: relative; overflow: hidden;
        box-shadow: var(--shadow-card);
    }
    .welcome-card::before {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(135deg, var(--teal-dim) 0%, transparent 60%);
        pointer-events: none;
    }
    .welcome-top {
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 12px;
    }
    .avatar {
        width: 48px; height: 48px; border-radius: 50%;
        background: linear-gradient(135deg, var(--teal), var(--blue));
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; font-weight: 700; color: #fff;
        flex-shrink: 0; box-shadow: 0 0 0 3px var(--teal-dim);
    }
    .welcome-info { flex: 1; min-width: 0; }
    .welcome-name {
        font-size: 1.05rem; font-weight: 600; color: var(--text-primary);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .welcome-sub { font-size: .78rem; color: var(--text-muted); }
    .role-pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 500;
    }
    .role-admin { background: rgba(239,68,68,.15); color: #f87171; border:1px solid rgba(239,68,68,.3); }
    .role-user  { background: rgba(245,158,11,.15); color: #fbbf24; border:1px solid rgba(245,158,11,.3); }

    .date-strip {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 12px; border-radius: var(--radius-sm);
        background: var(--bg-base); border: 1px solid var(--border);
    }
    .date-text { font-size: .82rem; color: var(--text-secondary); }
    .event-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 10px; border-radius: 20px; font-size: .72rem; font-weight: 500;
    }
    .event-badge-active   { background:var(--blue-dim);   color: #93c5fd; border:1px solid rgba(59,130,246,.3); }
    .event-badge-done     { background:var(--green-dim);  color: #6ee7b7; border:1px solid rgba(16,185,129,.3); }
    .event-badge-none     { background:rgba(255,255,255,.05); color:var(--text-muted); border:1px solid var(--border); }

    /* ═══════════════════════════════════════════════
       SECTION HEADER
    ═══════════════════════════════════════════════ */
    .section-header {
        display: flex; align-items: center; gap: 8px;
        margin: 18px 0 10px; font-size: .8rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: .08em;
        color: var(--text-muted);
    }
    .section-header::after {
        content: ''; flex: 1; height: 1px;
        background: var(--border);
    }
    .section-icon {
        width: 26px; height: 26px; border-radius: 7px;
        background: var(--teal-dim);
        display: flex; align-items: center; justify-content: center;
        color: var(--teal); font-size: .8rem;
    }

    /* ═══════════════════════════════════════════════
       SYSTEM METRICS GRID
    ═══════════════════════════════════════════════ */
    .metrics-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 10px; margin-bottom: 12px;
    }
    .metric-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 14px 14px 12px;
        position: relative; overflow: hidden;
        transition: transform .25s, box-shadow .25s, border-color .25s;
        box-shadow: var(--shadow-card);
    }
    .metric-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(0,0,0,.45);
        border-color: var(--border-strong);
    }
    .metric-card::after {
        content: '';
        position: absolute; top:0; left:0; right:0; height:2px;
        background: var(--accent-color, var(--teal));
        border-radius: 2px 2px 0 0;
    }
    .metric-label {
        font-size: .72rem; font-weight: 500; text-transform: uppercase;
        letter-spacing: .07em; color: var(--text-muted); margin-bottom: 6px;
        display: flex; align-items: center; gap: 5px;
    }
    .metric-value {
        font-family: var(--font-mono);
        font-size: 1.55rem; font-weight: 500;
        line-height: 1; margin-bottom: 4px;
        color: var(--accent-color, var(--teal));
    }
    .metric-sub { font-size: .68rem; color: var(--text-muted); margin-bottom: 8px; }
    .metric-bar {
        height: 4px; background: var(--border);
        border-radius: 2px; overflow: hidden;
    }
    .metric-bar-fill {
        height: 100%; border-radius: 2px;
        background: var(--accent-color, var(--teal));
        transition: width .8s cubic-bezier(.22,1,.36,1);
    }

    /* ═══════════════════════════════════════════════
       CACHE STRIP (admin only)
    ═══════════════════════════════════════════════ */
    .cache-strip {
        display: flex; align-items: center; gap: 12px;
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: var(--radius-md); padding: 12px 14px;
        margin-bottom: 12px; box-shadow: var(--shadow-card);
    }
    .cache-strip-icon {
        width: 38px; height: 38px; border-radius: 10px;
        background: rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.2);
        display:flex; align-items:center; justify-content:center;
        color:#f87171; font-size:1.1rem; flex-shrink:0;
    }
    .cache-strip-text { flex:1; min-width:0; }
    .cache-strip-text strong { font-size:.85rem; color:var(--text-primary); }
    .cache-strip-text small  { font-size:.72rem; color:var(--text-muted); display:block; }
    .btn-clear {
        background: linear-gradient(135deg,#ef4444,#dc2626);
        color:#fff; border:none; padding:7px 14px; border-radius:9px;
        font-size:.78rem; font-weight:600; cursor:pointer; flex-shrink:0;
        transition: all .2s; white-space:nowrap;
    }
    .btn-clear:hover { transform:translateY(-1px); box-shadow:0 4px 14px rgba(239,68,68,.4); }

    /* ═══════════════════════════════════════════════
       QUICK ACTIONS GRID
    ═══════════════════════════════════════════════ */
    .actions-grid {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 8px; margin-bottom: 12px;
    }
    .action-btn {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 14px 8px 12px;
        display: flex; flex-direction: column; align-items: center; gap: 7px;
        color: var(--text-secondary); font-size: .72rem; font-weight: 500;
        text-align: center; cursor: pointer;
        transition: all .22s; box-shadow: var(--shadow-card);
        position: relative; overflow: hidden;
    }
    .action-btn:active { transform: scale(.96); }
    .action-btn:hover {
        background: var(--bg-card-hover);
        border-color: var(--teal);
        color: var(--teal);
        box-shadow: 0 0 0 1px var(--teal), 0 8px 24px rgba(0,0,0,.4);
        transform: translateY(-2px);
    }
    .action-btn .action-icon {
        width: 38px; height: 38px; border-radius: 11px;
        background: var(--bg-base);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; transition: all .22s;
        border: 1px solid var(--border);
    }
    .action-btn:hover .action-icon {
        background: var(--teal-dim); border-color: var(--teal);
        color: var(--teal);
    }
    .action-btn span { line-height: 1.3; }

    /* ═══════════════════════════════════════════════
       STATS GRID
    ═══════════════════════════════════════════════ */
    .stats-grid {
        display: grid; grid-template-columns: repeat(2, 1fr);
        gap: 10px; margin-bottom: 12px;
    }
    .stat-tile {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 16px 14px;
        position: relative; overflow: hidden;
        box-shadow: var(--shadow-card);
        transition: transform .25s, border-color .25s;
    }
    .stat-tile:hover { transform: translateY(-3px); border-color: var(--border-strong); }
    .stat-tile::before {
        content:''; position:absolute; top:0; left:0; right:0; height:3px;
        background: var(--tile-color, var(--teal)); border-radius:2px 2px 0 0;
    }
    .stat-tile-icon {
        width:34px; height:34px; border-radius:9px;
        background: var(--teal-dim);
        display:flex; align-items:center; justify-content:center;
        font-size:1rem; margin-bottom:10px;
        color: var(--tile-color, var(--teal));
        border: 1px solid var(--border);
    }
    .stat-num {
        font-family: var(--font-mono);
        font-size: 2rem; font-weight: 500; line-height:1;
        color: var(--tile-color, var(--teal)); margin-bottom:4px;
    }
    .stat-lbl { font-size:.75rem; color:var(--text-muted); font-weight:500; }

    /* ═══════════════════════════════════════════════
       CHART CARD
    ═══════════════════════════════════════════════ */
    .chart-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 18px; margin-bottom: 12px;
        box-shadow: var(--shadow-card);
    }
    .chart-title {
        font-size: .85rem; font-weight: 600;
        color: var(--text-secondary); margin-bottom: 14px;
        display: flex; align-items: center; gap: 8px;
    }
    .chart-wrap { height: 220px; }

    /* ═══════════════════════════════════════════════
       EVENT LIST
    ═══════════════════════════════════════════════ */
    .events-list { margin-bottom: 12px; display: flex; flex-direction: column; gap: 8px; }
    .event-item {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 14px;
        display: flex; gap: 12px; align-items: flex-start;
        transition: transform .22s, border-color .22s;
        box-shadow: var(--shadow-card);
        position: relative;
    }
    .event-item:hover { transform: translateX(4px); border-color: var(--border-strong); }
    .event-dot {
        width: 10px; height: 10px; border-radius: 50%;
        margin-top: 5px; flex-shrink: 0;
    }
    .dot-ongoing   { background:#3b82f6; box-shadow:0 0 8px rgba(59,130,246,.6); }
    .dot-completed { background:#10b981; box-shadow:0 0 8px rgba(16,185,129,.4); }
    .dot-cancelled { background:#ef4444; }
    .dot-postponed { background:#f59e0b; }
    .dot-waiting   { background:#8b5cf6; }
    .event-body { flex:1; min-width:0; }
    .event-name {
        font-size:.9rem; font-weight:600; color:var(--text-primary);
        margin-bottom:4px; overflow:hidden; text-overflow:ellipsis;
    }
    .event-name.cancelled { text-decoration:line-through; opacity:.55; }
    .event-meta {
        display:flex; align-items:center; gap:6px;
        font-size:.75rem; color:var(--text-muted);
    }
    /* Status badges */
    .status-pill {
        display:inline-flex; align-items:center; gap:4px;
        padding:3px 8px; border-radius:20px;
        font-size:.68rem; font-weight:500;
        flex-shrink:0; margin-top:2px;
    }
    .badge-ongoing   { background:rgba(59,130,246,.15);  color:#93c5fd; border:1px solid rgba(59,130,246,.3);  }
    .badge-completed { background:rgba(16,185,129,.15);  color:#6ee7b7; border:1px solid rgba(16,185,129,.3);  }
    .badge-cancelled { background:rgba(239,68,68,.15);   color:#fca5a5; border:1px solid rgba(239,68,68,.3);   }
    .badge-postponed { background:rgba(245,158,11,.15);  color:#fcd34d; border:1px solid rgba(245,158,11,.3);  }
    .badge-waiting   { background:rgba(139,92,246,.15);  color:#c4b5fd; border:1px solid rgba(139,92,246,.3);  }

    .empty-state {
        text-align:center; padding:40px 20px; color:var(--text-muted);
    }
    .empty-state i { font-size:2.5rem; margin-bottom:10px; opacity:.4; display:block; }

    /* ═══════════════════════════════════════════════
       BOTTOM NAV
    ═══════════════════════════════════════════════ */
    .bottom-nav {
        position:fixed; bottom:0; left:0; right:0;
        height: var(--nav-height);
        background: var(--nav-bg);
        backdrop-filter: blur(20px) saturate(1.4);
        -webkit-backdrop-filter: blur(20px) saturate(1.4);
        border-top:1px solid var(--border);
        display:flex; justify-content:space-around; align-items:center;
        z-index:1050; padding: 0 4px;
    }
    .nav-btn {
        display:flex; flex-direction:column; align-items:center; gap:4px;
        padding:6px 16px; border-radius:var(--radius-sm);
        color:var(--text-muted); font-size:.68rem; font-weight:500;
        transition:all .2s; border:none; background:none; cursor:pointer;
        -webkit-tap-highlight-color:transparent;
    }
    .nav-btn i { font-size:1.25rem; line-height:1; }
    .nav-btn.active { color:var(--teal); }
    .nav-btn:hover { color:var(--teal); }

    /* ═══════════════════════════════════════════════
       MODALS
    ═══════════════════════════════════════════════ */
    .modal-content {
        background: var(--bg-surface);
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-xl);
        color: var(--text-primary);
    }
    .modal-header {
        border-bottom: 1px solid var(--border);
        padding: 18px 20px 16px;
        background: linear-gradient(135deg, var(--teal-dim), transparent);
        border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    }
    .modal-title { font-size: .95rem; font-weight: 600; }
    .modal-body  { padding: 20px; }
    .modal-footer { border-top: 1px solid var(--border); padding: 14px 20px; }
    .btn-close { transition: opacity .2s; }
    [data-theme="dark"] .btn-close,
    @media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) .btn-close { filter: invert(1) opacity(.6); } }
    [data-theme="dark"] .btn-close { filter: invert(1) opacity(.6); }
    .btn-close:hover { opacity:1; }

    /* Form elements */
    .form-label { font-size:.82rem; color:var(--text-secondary); margin-bottom:5px; font-weight:500; }
    .form-control {
        background: var(--bg-input); border: 1px solid var(--border-strong);
        color: var(--text-primary); border-radius: var(--radius-sm); font-size:.9rem;
        padding:10px 13px; font-family:var(--font-main);
        transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus {
        background: var(--bg-input); color: var(--text-primary);
        border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-dim);
        outline: none;
    }
    .form-control::placeholder { color: var(--text-muted); }

    /* Buttons */
    .btn-primary-ent {
        background: linear-gradient(135deg, var(--teal), #0aa396);
        border: none; color: #0d1b2a; font-weight: 600;
        padding: 11px 20px; border-radius: var(--radius-sm);
        width:100%; font-size:.9rem; cursor:pointer; font-family:var(--font-main);
        transition:all .2s;
    }
    .btn-primary-ent:hover { transform:translateY(-1px); box-shadow:0 4px 16px var(--teal-glow); }
    .btn-secondary-ent {
        background: var(--bg-base); border: 1px solid var(--border-strong);
        color: var(--text-secondary); font-weight: 500;
        padding: 10px 20px; border-radius: var(--radius-sm);
        width:100%; font-size:.9rem; cursor:pointer; font-family:var(--font-main);
        transition:all .2s; margin-top: 8px;
    }
    .btn-secondary-ent:hover { background:var(--bg-card-hover); }

    /* System modal metrics */
    .sys-metric-row { margin-bottom:20px; }
    .sys-metric-top {
        display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;
    }
    .sys-metric-name { font-size:.85rem; font-weight:600; display:flex; align-items:center; gap:7px; }
    .sys-badge {
        font-family:var(--font-mono); font-size:.72rem; font-weight:500;
        padding:3px 9px; border-radius:20px;
    }
    .sys-progress { height:8px; background:var(--border); border-radius:4px; overflow:hidden; margin-bottom:5px; }
    .sys-bar { height:100%; border-radius:4px; transition:width 1s cubic-bezier(.22,1,.36,1); }
    .sys-detail { font-size:.72rem; color:var(--text-muted); }

    /* Stats modal cards */
    .stats-modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px; }
    .sm-card {
        background:var(--bg-card); border:1px solid var(--border);
        border-radius:var(--radius-md); padding:14px; text-align:center;
    }
    .sm-card i { font-size:1.5rem; margin-bottom:6px; display:block; }
    .sm-card h3 { font-family:var(--font-mono); font-size:1.7rem; font-weight:500; margin:0 0 2px; }
    .sm-card small { color:var(--text-muted); font-size:.72rem; }

    /* Divider */
    .ent-divider { border:none; border-top:1px solid var(--border); margin:16px 0; }

    /* ═══════════════════════════════════════════════
       RESPONSIVE
    ═══════════════════════════════════════════════ */
    @media (max-width: 360px) {
        .actions-grid { grid-template-columns: repeat(2,1fr); }
        .stats-grid   { grid-template-columns: 1fr; }
        .metrics-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header class="app-header fade-up">
    <img src="/images/logo.jpg" alt="Logo" class="header-logo">
    <div class="header-titles">
        <h1>ระบบปฏิทินและนัดหมาย</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
    </div>
    <span class="header-version" onclick="window.location.href='changelogs'" title="ดูบันทึกเวอร์ชัน">
        v<?= htmlspecialchars($current_version) ?>
    </span>
    <div style="display:flex;flex-direction:column;align-items:center;gap:2px">
    <button class="theme-toggle-btn" id="themeToggle" aria-label="สลับธีม" title="ธีม: Auto">
        <i class="bi bi-sun-fill icon-sun" aria-hidden="true"></i>
        <i class="bi bi-moon-fill icon-moon" aria-hidden="true"></i>
    </button>
    <span id="themeModeLabel" style="font-size:.58rem;color:var(--text-muted);line-height:1">Auto</span>
    </div>
</header>

<!-- ═══ MAIN ═══ -->
<main class="page-content">

    <!-- Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="ent-alert ent-alert-success alert-dismissible fade show fade-up" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars($_SESSION['success']) ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="ent-alert ent-alert-danger alert-dismissible fade show fade-up" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Welcome Card -->
    <div class="welcome-card fade-up d1">
        <div class="welcome-top">
            <div class="avatar" data-bs-toggle="modal" data-bs-target="#profileModal">
                <?= mb_strtoupper(mb_substr($first_name, 0, 1)) ?>
            </div>
            <div class="welcome-info">
                <div class="welcome-name">สวัสดีคุณ <?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?></div>
                <div class="welcome-sub">
                    <span class="role-pill <?= $role === 'admin' ? 'role-admin' : 'role-user' ?>">
                        <i class="bi bi-<?= $role === 'admin' ? 'shield-check' : 'person' ?>"></i>
                        <?= $role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป' ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="date-strip">
            <span class="date-text"><i class="bi bi-calendar3 me-2"></i><?= htmlspecialchars($current_date_thai) ?></span>
            <?php if ($total_events > 0): ?>
                <span class="event-badge event-badge-active"><i class="bi bi-list-task"></i><?= $total_events ?> งาน</span>
            <?php elseif ($completed_events > 0): ?>
                <span class="event-badge event-badge-done"><i class="bi bi-check-circle"></i>เสร็จสิ้น</span>
            <?php else: ?>
                <span class="event-badge event-badge-none"><i class="bi bi-calendar-x"></i>ว่าง</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Metrics -->
    <div class="section-header fade-up d2">
        <div class="section-icon"><i class="bi bi-cpu"></i></div>
        สถานะระบบ
    </div>

    <div class="metrics-grid fade-up d2">
        <?php
        $metrics = [
            ['RAM',     'bi-memory',   $ram_usage['usage'],     round($ram_usage['used'],1).'/'   .round($ram_usage['total'],1).' GB',  metricColor($ram_usage['usage'])],
            ['CPU',     'bi-cpu',      $cpu_usage,              'ปริมาณการใช้งาน',                 metricColor($cpu_usage)],
            ['Storage', 'bi-hdd',      $storage_usage['usage'], round($storage_usage['used'],1).'/' .round($storage_usage['total'],1).' GB', metricColor($storage_usage['usage'])],
            ['PHP',     'bi-code-slash', 0,                     phpversion(),                      '#8b5cf6'],
        ];
        foreach ($metrics as [$label, $icon, $pct, $sub, $color]): ?>
        <div class="metric-card" style="--accent-color:<?= $color ?>;"
             data-bs-toggle="modal" data-bs-target="#systemModal">
            <div class="metric-label"><i class="bi <?= $icon ?>"></i><?= $label ?></div>
            <div class="metric-value"><?= $pct > 0 ? round($pct,1).'%' : $sub ?></div>
            <div class="metric-sub"><?= $pct > 0 ? $sub : '' ?></div>
            <?php if ($pct > 0): ?>
            <div class="metric-bar">
                <div class="metric-bar-fill" style="width:<?= min($pct,100) ?>%"></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Cache strip (admin only) -->
    <?php if ($role === 'admin'): ?>
    <div class="cache-strip fade-up d2">
        <div class="cache-strip-icon"><i class="bi bi-trash3"></i></div>
        <div class="cache-strip-text">
            <strong>Cache ระบบ</strong>
            <small><?= round($cache_size,2) ?> MB — ข้อมูลชั่วคราวสะสม</small>
        </div>
        <a href="?action=clear_cache" class="btn-clear"
           onclick="return confirm('ยืนยันการเคลียร์ Cache?')">
            <i class="bi bi-arrow-clockwise me-1"></i>เคลียร์
        </a>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="section-header fade-up d3">
        <div class="section-icon"><i class="bi bi-grid"></i></div>
        เมนูหลัก
    </div>

    <div class="actions-grid fade-up d3">
        <?php
        $actions = [
            ['mobile',    'bi-house',            'หน้าแรก'],
            ['m_manage',  'bi-calendar-plus',    'จัดการงาน'],
        ];
        if ($role === 'admin') {
            $actions[] = ['persons.php',   'bi-people',            'บุคลากร'];
            $actions[] = ['departments.php','bi-building',         'หน่วยงาน'];
            $actions[] = ['dress',          'bi-person-lines-fill','เครื่องแต่งกาย'];
            $actions[] = ['chairman',       'bi-person-square',    'ประธาน'];
            $actions[] = ['add_changelog',  'bi-card-list',        'Changelog'];
            $actions[] = ['m_old_events',   'bi-clock-history',    'ประวัติงาน'];
        }
        foreach ($actions as [$href, $icon, $label]): ?>
        <a href="<?= $href ?>" class="action-btn">
            <div class="action-icon"><i class="bi <?= $icon ?>"></i></div>
            <span><?= $label ?></span>
        </a>
        <?php endforeach; ?>
        <div class="action-btn" data-bs-toggle="modal" data-bs-target="#statsModal">
            <div class="action-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <span>สถิติ</span>
        </div>
        <a href="logout" class="action-btn">
            <div class="action-icon"><i class="bi bi-box-arrow-right"></i></div>
            <span>ออกจากระบบ</span>
        </a>
    </div>

    <!-- Status Stats -->
    <div class="section-header fade-up d4">
        <div class="section-icon"><i class="bi bi-speedometer2"></i></div>
        สถานะวาระงาน
    </div>

    <div class="stats-grid fade-up d4">
        <?php
        $tiles = [
            ['กำลังดำเนินการ', $stats_data['ongoing']??0,   'bi-arrow-repeat',    '#3b82f6'],
            ['งานที่ผ่านมา',    $stats_data['completed']??0,'bi-clock-history',   '#64748b'],
            ['เสร็จสิ้นวันนี้', $completed_events,           'bi-check-circle',    '#10b981'],
            ['รอดำเนินการ',    $stats_data['upcoming']??0,  'bi-calendar-event',  '#f59e0b'],
            ['ยกเลิก',         $stats_data['cancelled']??0, 'bi-x-circle',        '#ef4444'],
            ['เลื่อน',          $stats_data['postponed']??0, 'bi-clock',           '#8b5cf6'],
        ];
        foreach ($tiles as [$label, $num, $icon, $color]): ?>
        <div class="stat-tile" style="--tile-color:<?= $color ?>;">
            <div class="stat-tile-icon"><i class="bi <?= $icon ?>"></i></div>
            <div class="stat-num"><?= $num ?></div>
            <div class="stat-lbl"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart -->
    <div class="chart-card fade-up d5">
        <div class="chart-title"><i class="bi bi-pie-chart"></i> สถิติวาระงาน</div>
        <div class="chart-wrap"><canvas id="statsChart"></canvas></div>
    </div>

    <!-- Today's Events -->
    <div class="section-header fade-up d5">
        <div class="section-icon"><i class="bi bi-list-check"></i></div>
        วาระงานวันนี้
    </div>

    <div class="events-list fade-up d6">
        <?php if (empty($today_events)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>ไม่มีวาระงานในวันนี้</p>
        </div>
        <?php else: foreach ($today_events as $ev):
            $st = getEventStatus($ev); ?>
        <div class="event-item">
            <div class="event-dot <?= $st['dot'] ?>"></div>
            <div class="event-body">
                <div class="event-name <?= $st['text']==='ยกเลิก' ? 'cancelled' : '' ?>">
                    <?= htmlspecialchars($ev['title']) ?>
                </div>
                <div class="event-meta">
                    <i class="bi bi-clock"></i>
                    <?= date('H:i', strtotime($ev['time_from'])) ?> น.
                    <?php if ($ev['time_to']): ?>— <?= date('H:i', strtotime($ev['time_to'])) ?> น.<?php endif; ?>
                </div>
            </div>
            <span class="status-pill <?= $st['class'] ?>">
                <i class="bi bi-<?= $st['icon'] ?>"></i>
                <?= $st['text'] ?>
            </span>
        </div>
        <?php endforeach; endif; ?>
    </div>

</main>

<!-- ═══ BOTTOM NAV ═══ -->
<nav class="bottom-nav">
    <a href="mobile" class="nav-btn">
        <i class="bi bi-house"></i><span>หน้าแรก</span>
    </a>
    <a href="m_manage" class="nav-btn">
        <i class="bi bi-calendar-plus"></i><span>จัดการงาน</span>
    </a>
    <button class="nav-btn active" data-bs-toggle="modal" data-bs-target="#systemModal">
        <i class="bi bi-cpu"></i><span>ระบบ</span>
    </button>
    <button class="nav-btn" data-bs-toggle="modal" data-bs-target="#profileModal">
        <i class="bi bi-person-circle"></i><span>โปรไฟล์</span>
    </button>
</nav>

<!-- ═══ MODAL: PROFILE ═══ -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-gear me-2" style="color:var(--teal)"></i>แก้ไขโปรไฟล์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="profileForm" method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">ชื่อ</label>
                        <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">นามสกุล</label>
                        <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านปัจจุบัน</label>
                        <input type="password" class="form-control" name="old_password" placeholder="ยืนยันตัวตนด้วยรหัสผ่านปัจจุบัน" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านใหม่ <small style="color:var(--text-muted)">(ไม่บังคับ)</small></label>
                        <input type="password" class="form-control" name="new_password" placeholder="เว้นว่างหากไม่ต้องการเปลี่ยน">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="form-control" name="confirm_password">
                    </div>
                    <button type="submit" class="btn-primary-ent">
                        <i class="bi bi-check-circle me-2"></i>บันทึกการเปลี่ยนแปลง
                    </button>
                    <button type="button" class="btn-secondary-ent" data-bs-dismiss="modal">
                        ยกเลิก
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: SYSTEM ═══ -->
<div class="modal fade" id="systemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-server me-2" style="color:var(--teal)"></i>สถานะเซิร์ฟเวอร์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                $sys_rows = [
                    ['RAM',     'bi-memory',  $ram_usage['usage'],     metricColor($ram_usage['usage']),     round($ram_usage['used'],2).' GB / '.round($ram_usage['total'],2).' GB (ว่าง '.round($ram_usage['free'],2).' GB)'],
                    ['CPU',     'bi-cpu',     $cpu_usage,              metricColor($cpu_usage),              'ปริมาณการใช้งานปัจจุบัน'],
                    ['Storage', 'bi-hdd',     $storage_usage['usage'], metricColor($storage_usage['usage']), round($storage_usage['used'],2).' GB / '.round($storage_usage['total'],2).' GB (ว่าง '.round($storage_usage['free'],2).' GB)'],
                ];
                foreach ($sys_rows as [$name,$icon,$pct,$color,$detail]): ?>
                <div class="sys-metric-row">
                    <div class="sys-metric-top">
                        <div class="sys-metric-name">
                            <i class="bi <?= $icon ?>" style="color:<?= $color ?>"></i><?= $name ?>
                        </div>
                        <span class="sys-badge" style="background:<?= $color ?>22;color:<?= $color ?>;border:1px solid <?= $color ?>44">
                            <?= round($pct,1) ?>%
                        </span>
                    </div>
                    <div class="sys-progress">
                        <div class="sys-bar" style="width:<?= min($pct,100) ?>%;background:<?= $color ?>"></div>
                    </div>
                    <div class="sys-detail"><?= $detail ?></div>
                </div>
                <?php endforeach; ?>

                <?php if ($role === 'admin'): ?>
                <hr class="ent-divider">
                <div class="sys-metric-row">
                    <div class="sys-metric-top">
                        <div class="sys-metric-name"><i class="bi bi-trash3" style="color:#ef4444"></i>Cache</div>
                        <span class="sys-badge" style="background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)">
                            <?= round($cache_size,2) ?> MB
                        </span>
                    </div>
                    <a href="?action=clear_cache" class="btn-clear w-100 mt-2 d-block text-center"
                       onclick="return confirm('ยืนยันการเคลียร์ Cache ทั้งหมด?')">
                        <i class="bi bi-arrow-clockwise me-1"></i>เคลียร์ Cache
                    </a>
                </div>
                <?php endif; ?>

                <hr class="ent-divider">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.72rem;color:var(--text-muted)">
                    <div>PHP <?= phpversion() ?></div>
                    <div>MySQL <?= mysqli_get_server_info($connect) ?></div>
                    <div>OS: <?= PHP_OS_FAMILY ?></div>
                    <div>v<?= htmlspecialchars($current_version) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-ent" data-bs-dismiss="modal" style="width:auto;margin:0;padding:9px 20px;">ปิด</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: STATS ═══ -->
<div class="modal fade" id="statsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-graph-up-arrow me-2" style="color:var(--teal)"></i>สถิติทั้งหมด</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="stats-modal-grid">
                    <div class="sm-card">
                        <i class="bi bi-calendar-day" style="color:#3b82f6"></i>
                        <h3 style="color:#3b82f6"><?= $daily_stats_data['today'] ?? 0 ?></h3>
                        <small>งานวันนี้</small>
                    </div>
                    <div class="sm-card">
                        <i class="bi bi-calendar-month" style="color:var(--teal)"></i>
                        <h3 style="color:var(--teal)"><?= $daily_stats_data['this_month'] ?? 0 ?></h3>
                        <small>งานเดือนนี้</small>
                    </div>
                    <div class="sm-card">
                        <i class="bi bi-calendar-range" style="color:#10b981"></i>
                        <h3 style="color:#10b981"><?= $daily_stats_data['this_year'] ?? 0 ?></h3>
                        <small>งานปีนี้</small>
                    </div>
                    <div class="sm-card">
                        <i class="bi bi-calendar-check" style="color:#f59e0b"></i>
                        <h3 style="color:#f59e0b"><?= $daily_stats_data['total'] ?? 0 ?></h3>
                        <small>งานทั้งหมด</small>
                    </div>
                </div>
                <hr class="ent-divider">
                <div style="font-size:.82rem;font-weight:600;color:var(--text-secondary);margin-bottom:10px;">
                    <i class="bi bi-pie-chart me-2"></i>สถานะงาน
                </div>
                <?php
                $status_list = [
                    ['กำลังดำเนินการ','bi-arrow-repeat','#3b82f6',$stats_data['ongoing']??0],
                    ['เสร็จสิ้น',       'bi-check-circle','#10b981',$stats_data['completed']??0],
                    ['รอดำเนินการ',    'bi-calendar-event','#f59e0b',$stats_data['upcoming']??0],
                    ['ยกเลิก',         'bi-x-circle','#ef4444',$stats_data['cancelled']??0],
                    ['เลื่อน',          'bi-clock','#8b5cf6',$stats_data['postponed']??0],
                ];
                foreach ($status_list as [$name,$icon,$color,$count]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span style="color:var(--text-secondary);font-size:.85rem;">
                        <i class="bi <?= $icon ?> me-2" style="color:<?= $color ?>"></i><?= $name ?>
                    </span>
                    <span style="font-family:var(--font-mono);font-size:.82rem;color:<?= $color ?>;background:<?= $color ?>22;padding:2px 10px;border-radius:20px;border:1px solid <?= $color ?>44"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-ent" data-bs-dismiss="modal" style="width:auto;margin:0;padding:9px 20px;">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    'use strict';

    /* Chart */
    const ctx = document.getElementById('statsChart');
    if (ctx) {
        const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
        const chartBorderColor  = () => isDark() ? '#162840' : '#ffffff';
        const chartLabelColor   = () => isDark() ? '#8ba3be' : '#64748b';
        const chartTooltipBg    = () => isDark() ? '#112233' : '#ffffff';
        const chartTooltipTitle = () => isDark() ? '#e2edf8' : '#0f172a';
        const chartTooltipBody  = () => isDark() ? '#8ba3be' : '#64748b';
        const chartTooltipBorder= () => isDark() ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

        const chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['กำลังดำเนินการ','เสร็จสิ้น','รอดำเนินการ','ยกเลิก','เลื่อน'],
                datasets: [{
                    data: [
                        <?= (int)($stats_data['ongoing']??0) ?>,
                        <?= (int)($stats_data['completed']??0) ?>,
                        <?= (int)($stats_data['upcoming']??0) ?>,
                        <?= (int)($stats_data['cancelled']??0) ?>,
                        <?= (int)($stats_data['postponed']??0) ?>
                    ],
                    backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'],
                    borderWidth: 2,
                    borderColor: chartBorderColor(),
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: chartLabelColor(), padding: 16, usePointStyle: true,
                            font: { size: 11, family: 'Anuphan' }
                        }
                    },
                    tooltip: {
                        backgroundColor: chartTooltipBg(),
                        borderColor: chartTooltipBorder(), borderWidth: 1,
                        titleColor: chartTooltipTitle(), bodyColor: chartTooltipBody(),
                        padding: 12, cornerRadius: 10,
                        titleFont: { size: 13, family: 'Anuphan', weight: '600' },
                        bodyFont: { size: 12, family: 'Anuphan' },
                        callbacks: {
                            label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' งาน'
                        }
                    }
                },
                cutout: '65%',
                animation: { animateRotate: true, duration: 900, easing: 'easeInOutQuart' }
            }
        });

        // Re-render chart when theme changes
        const themeObserver = new MutationObserver(() => {
            chartInstance.data.datasets[0].borderColor = chartBorderColor();
            chartInstance.options.plugins.legend.labels.color = chartLabelColor();
            chartInstance.options.plugins.tooltip.backgroundColor = chartTooltipBg();
            chartInstance.options.plugins.tooltip.titleColor = chartTooltipTitle();
            chartInstance.options.plugins.tooltip.bodyColor = chartTooltipBody();
            chartInstance.options.plugins.tooltip.borderColor = chartTooltipBorder();
            chartInstance.update();
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    }

    /* Profile form validation */
    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', function(e){
            const np = this.new_password.value;
            const cp = this.confirm_password.value;
            if (np && np !== cp) {
                e.preventDefault();
                alert('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน');
            }
        });
    }

    /* Auto-dismiss alerts after 4 s */
    document.querySelectorAll('.ent-alert').forEach(el => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });

    /* Auto-refresh every 60 s */
    setTimeout(() => location.reload(), 60000);
})();

/* ─────────────────────────────────────────────────────────────────
   DARK / LIGHT MODE TOGGLE
───────────────────────────────────────────────────────────────── */
(function () {
    const html = document.getElementById('htmlRoot');
    const btn  = document.getElementById('themeToggle');
    if (!html || !btn) return;

    // Apply saved or system theme on load
    const saved = localStorage.getItem('theme-pref');
    if (saved) {
        html.setAttribute('data-theme', saved);
    } else {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
    }

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        /* theme managed by engine */;
        // Update meta theme-color for browser chrome
        const metaLight = document.querySelector('meta[name="theme-color"][media*="light"]');
        const metaDark  = document.querySelector('meta[name="theme-color"][media*="dark"]');
        if (metaLight) metaLight.setAttribute('content', theme === 'dark' ? '#0d1b2a' : '#0a3d54');
        if (metaDark)  metaDark.setAttribute('content',  theme === 'dark' ? '#0d1b2a' : '#0a3d54');
    }

    btn.addEventListener('click', () => {
        const current = html.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });

    // Sync with system preference changes (when no manual override saved)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme-pref')) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });
})();

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
    // Single-icon button (manage style)
    var icon = document.getElementById('themeIcon');
    if (icon) icon.className = pref==='auto' ? 'bi bi-circle-half' : (resolved==='dark'?'bi bi-moon-fill':'bi bi-sun-fill');
    // Dual-icon button (dashboard/index style)
    var sun  = document.querySelector('#themeToggle .icon-sun');
    var moon = document.querySelector('#themeToggle .icon-moon');
    if (sun && moon) {
      if (resolved==='dark') { sun.style.opacity='1'; sun.style.transform='scale(1)'; moon.style.opacity='0'; moon.style.transform='scale(0.5)'; }
      else                   { sun.style.opacity='0'; sun.style.transform='scale(0.5)'; moon.style.opacity='1'; moon.style.transform='scale(1)'; }
    }
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
  // Bind toggle button if not using inline onclick
  document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('themeToggle');
    if (btn && !btn.getAttribute('onclick')) btn.addEventListener('click', window.__themeToggleClick);
  });
})();

</script>
</body>
</html>

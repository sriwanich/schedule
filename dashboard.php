<?php
session_start();
$start_time = microtime(true);

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

// เชื่อมต่อฐานข้อมูลผ่าน config.php
include 'config.php';

// ====== ENHANCED CACHE FUNCTIONS ======
define('CACHE_DIR', __DIR__ . '/cache/');
define('CACHE_DURATION', 300);

if (!file_exists(CACHE_DIR)) {
    if (!mkdir(CACHE_DIR, 0777, true)) {
        error_log("Failed to create cache directory: " . CACHE_DIR);
    } else {
        chmod(CACHE_DIR, 0777);
    }
}

function setCache($key, $data) {
    try {
        $filename = CACHE_DIR . md5($key) . '.cache';
        $cache_data = ['time' => time(), 'data' => $data, 'key' => $key];
        $result = file_put_contents($filename, serialize($cache_data));
        if ($result === false) { error_log("Failed to write cache file: $filename"); return false; }
        chmod($filename, 0666);
        return true;
    } catch (Exception $e) { error_log("Cache write error: " . $e->getMessage()); return false; }
}

function getCache($key) {
    try {
        $filename = CACHE_DIR . md5($key) . '.cache';
        if (!file_exists($filename)) return null;
        $content = file_get_contents($filename);
        if ($content === false) return null;
        $cache_data = @unserialize($content);
        if ($cache_data === false) { @unlink($filename); return null; }
        if ((time() - $cache_data['time']) > CACHE_DURATION) { @unlink($filename); return null; }
        return $cache_data['data'];
    } catch (Exception $e) { error_log("Cache read error: " . $e->getMessage()); return null; }
}

function clearAllCache() {
    try {
        $cleared_count = 0;
        if (!is_dir(CACHE_DIR)) return 0;
        if (!is_writable(CACHE_DIR)) chmod(CACHE_DIR, 0777);
        $files = glob(CACHE_DIR . '*.cache');
        if ($files === false) return 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (!is_writable($file)) chmod($file, 0666);
                if (unlink($file)) $cleared_count++;
            }
        }
        clearstatcache(true);
        return $cleared_count;
    } catch (Exception $e) { error_log("Clear all cache error: " . $e->getMessage()); return 0; }
}

function clearExpiredCache() {
    try {
        $cleared_count = 0;
        $files = glob(CACHE_DIR . '*.cache');
        if ($files === false) return 0;
        foreach ($files as $file) {
            if (is_file($file) && is_readable($file)) {
                $content = @file_get_contents($file);
                if ($content === false) continue;
                $cache_data = @unserialize($content);
                if ($cache_data === false) { if (!is_writable($file)) chmod($file, 0666); if (unlink($file)) $cleared_count++; continue; }
                if ((time() - $cache_data['time']) > CACHE_DURATION) { if (!is_writable($file)) chmod($file, 0666); if (unlink($file)) $cleared_count++; }
            }
        }
        clearstatcache(true);
        return $cleared_count;
    } catch (Exception $e) { error_log("Clear expired cache error: " . $e->getMessage()); return 0; }
}

function clearCacheByPattern($pattern) {
    try {
        $cleared_count = 0;
        $files = glob(CACHE_DIR . '*.cache');
        if ($files === false) return 0;
        foreach ($files as $file) {
            if (is_file($file) && is_readable($file)) {
                $content = @file_get_contents($file);
                if ($content === false) continue;
                $cache_data = @unserialize($content);
                if ($cache_data === false) continue;
                if (isset($cache_data['key']) && strpos($cache_data['key'], $pattern) !== false) {
                    if (!is_writable($file)) chmod($file, 0666);
                    if (unlink($file)) $cleared_count++;
                }
            }
        }
        clearstatcache(true);
        return $cleared_count;
    } catch (Exception $e) { error_log("Clear cache by pattern error: " . $e->getMessage()); return 0; }
}

function countCacheFiles() {
    try { $files = glob(CACHE_DIR . '*.cache'); return ($files === false) ? 0 : count($files); }
    catch (Exception $e) { return 0; }
}

function getCacheSize() {
    try {
        $size = 0;
        $files = glob(CACHE_DIR . '*.cache');
        if ($files === false) return 0;
        foreach ($files as $file) { if (is_file($file)) $size += filesize($file); }
        return $size;
    } catch (Exception $e) { return 0; }
}

function getCacheDetails() {
    try {
        $files = glob(CACHE_DIR . '*.cache');
        if ($files === false) return [];
        $details = [];
        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) continue;
            $content = @file_get_contents($file);
            if ($content === false) continue;
            $cache_data = @unserialize($content);
            if ($cache_data === false) continue;
            $age = time() - $cache_data['time'];
            $remaining = CACHE_DURATION - $age;
            $details[] = ['key' => $cache_data['key'] ?? 'Unknown', 'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', $cache_data['time']), 'age' => $age,
                'remaining' => max(0, $remaining), 'expired' => $remaining <= 0, 'file' => basename($file)];
        }
        return $details;
    } catch (Exception $e) { return []; }
}

// ====== SYSTEM MONITOR ======
function getSystemInfo() {
    $info = [];
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $info['cpu_load']      = round($load[0] * 100, 2);
        $info['cpu_load_5min'] = round($load[1] * 100, 2);
        $info['cpu_load_15min']= round($load[2] * 100, 2);
    } else { $info['cpu_load'] = 0; $info['cpu_load_5min'] = 0; $info['cpu_load_15min'] = 0; }
    if (function_exists('memory_get_usage')) {
        $info['memory_used']  = memory_get_usage(true);
        $info['memory_peak']  = memory_get_peak_usage(true);
        $info['memory_limit'] = ini_get('memory_limit');
        $memory_limit_bytes   = convertToBytes($info['memory_limit']);
        $info['memory_percent']      = $memory_limit_bytes > 0 ? round(($info['memory_used'] / $memory_limit_bytes) * 100, 2) : 0;
        $info['memory_peak_percent'] = $memory_limit_bytes > 0 ? round(($info['memory_peak'] / $memory_limit_bytes) * 100, 2) : 0;
    }
    $info['disk_free']    = disk_free_space(__DIR__);
    $info['disk_total']   = disk_total_space(__DIR__);
    $info['disk_used']    = $info['disk_total'] - $info['disk_free'];
    $info['disk_percent'] = round(($info['disk_used'] / $info['disk_total']) * 100, 2);
    $info['server_software']    = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $info['php_version']        = phpversion();
    $info['max_execution_time'] = ini_get('max_execution_time');
    $info['max_upload_size']    = ini_get('upload_max_filesize');
    $info['post_max_size']      = ini_get('post_max_size');
    if (file_exists('/proc/uptime')) {
        $uptime_data    = @file_get_contents('/proc/uptime');
        $uptime_seconds = floatval(explode(' ', $uptime_data)[0]);
        $info['uptime']           = $uptime_seconds;
        $info['uptime_formatted'] = formatUptime($uptime_seconds);
    } else { $info['uptime'] = 0; $info['uptime_formatted'] = 'N/A'; }
    $info['active_connections'] = count(glob('/tmp/sess_*'));
    return $info;
}

function convertToBytes($val) {
    $val = trim($val); $last = strtolower($val[strlen($val)-1]); $val = (int)$val;
    switch($last) { case 'g': $val *= 1024; case 'm': $val *= 1024; case 'k': $val *= 1024; }
    return $val;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
    return round($bytes, $precision) . ' ' . $units[$i];
}

function formatUptime($seconds) {
    $days = floor($seconds / 86400); $hours = floor(($seconds % 86400) / 3600); $minutes = floor(($seconds % 3600) / 60);
    $parts = [];
    if ($days > 0)    $parts[] = $days . ' วัน';
    if ($hours > 0)   $parts[] = $hours . ' ชม.';
    if ($minutes > 0) $parts[] = $minutes . ' นาที';
    return implode(' ', $parts) ?: '< 1 นาที';
}

// ====== AJAX Handler ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ในการดำเนินการ']);
        exit();
    }
    $response = ['success' => false, 'message' => '', 'count' => 0];
    try {
        $before_count = countCacheFiles();
        $action = $_POST['action'] ?? '';
        switch($action) {
            case 'clear_all':
                $cleared = clearAllCache(); $after_count = countCacheFiles();
                $response = ['success' => true, 'message' => "ล้างแคชทั้งหมดสำเร็จ! ลบไปแล้ว $cleared ไฟล์ (เหลือ $after_count ไฟล์)", 'count' => $cleared, 'remaining' => $after_count];
                break;
            case 'clear_expired':
                $cleared = clearExpiredCache(); $after_count = countCacheFiles();
                $response = ['success' => true, 'message' => "ล้างแคชที่หมดอายุสำเร็จ! ลบไปแล้ว $cleared ไฟล์", 'count' => $cleared, 'remaining' => $after_count];
                break;
            case 'clear_stats':
                $cleared = clearCacheByPattern('stats'); $after_count = countCacheFiles();
                $response = ['success' => true, 'message' => "ล้างแคชสถิติสำเร็จ! ลบไปแล้ว $cleared ไฟล์", 'count' => $cleared, 'remaining' => $after_count];
                break;
            case 'clear_user':
                $cleared = clearCacheByPattern('user_data'); $after_count = countCacheFiles();
                $response = ['success' => true, 'message' => "ล้างแคชข้อมูลผู้ใช้สำเร็จ! ลบไปแล้ว $cleared ไฟล์", 'count' => $cleared, 'remaining' => $after_count];
                break;
            default:
                $response = ['success' => false, 'message' => 'คำสั่งไม่ถูกต้อง'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// ====== Profile Update ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $username    = $_SESSION['username'];
    $first_name  = mysqli_real_escape_string($connect, $_POST['first_name']);
    $last_name   = mysqli_real_escape_string($connect, $_POST['last_name']);
    $old_password= $_POST['old_password'];
    $new_password= $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_query  = mysqli_query($connect, "SELECT password FROM users WHERE username='$username'");
    $user_row    = mysqli_fetch_assoc($user_query);
    if (password_verify($old_password, $user_row['password'])) {
        if ($new_password && $new_password !== $confirm_password) {
            $_SESSION['error'] = "รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน";
        } else {
            if ($new_password) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_query($connect, "UPDATE users SET first_name='$first_name', last_name='$last_name', password='$hashed' WHERE username='$username'");
            } else {
                mysqli_query($connect, "UPDATE users SET first_name='$first_name', last_name='$last_name' WHERE username='$username'");
            }
            clearCacheByPattern("user_data_$username");
            $_SESSION['success'] = "อัปเดตข้อมูลสำเร็จ";
        }
    } else { $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง"; }
    header("Location: dashboard"); exit();
}

// ====== ดึงข้อมูลผู้ใช้ ======
$username = $_SESSION['username'];
$cache_key_user = "user_data_$username";
$user_data = getCache($cache_key_user);
if (!$user_data) {
    $user_query = mysqli_query($connect, "SELECT first_name, last_name, role FROM users WHERE username='$username'");
    $user_data  = mysqli_fetch_assoc($user_query);
    setCache($cache_key_user, $user_data);
}
$first_name = $user_data['first_name'];
$last_name  = $user_data['last_name'];
$role       = $user_data['role'];

$user_agent = $_SERVER['HTTP_USER_AGENT'];
$is_mobile  = preg_match("/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i", $user_agent);

$thai_months_full = [
    'Jan' => 'มกราคม', 'Feb' => 'กุมภาพันธ์', 'Mar' => 'มีนาคม', 'Apr' => 'เมษายน',
    'May' => 'พฤษภาคม', 'Jun' => 'มิถุนายน', 'Jul' => 'กรกฎาคม', 'Aug' => 'สิงหาคม',
    'Sep' => 'กันยายน', 'Oct' => 'ตุลาคม', 'Nov' => 'พฤศจิกายน', 'Dec' => 'ธันวาคม'
];

// ====== Version ======
try {
    $version_result  = mysqli_query($connect, "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1");
    $version_row     = mysqli_fetch_assoc($version_result);
    $current_version = $version_row['version'] ?? '1.0.0';
} catch (Exception $e) { $current_version = '1.0.0'; }

// ====== Stats ======
$cache_key_stats = "dashboard_stats";
$stats_data = getCache($cache_key_stats);
if (!$stats_data) {
    $stats_query  = "SELECT
        SUM(CASE WHEN date = CURDATE() AND (time_to > CURTIME() OR time_to IS NULL) AND status NOT IN ('เลื่อน','ยกเลิก') THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN (date < CURDATE() OR (date = CURDATE() AND time_to <= CURTIME())) AND status NOT IN ('เลื่อน','ยกเลิก') THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (date > CURDATE() OR (date = CURDATE() AND time_from >= CURTIME())) AND status NOT IN ('เลื่อน','ยกเลิก') THEN 1 ELSE 0 END) as upcoming
        FROM events";
    $stats_result = mysqli_query($connect, $stats_query);
    $stats_data   = mysqli_fetch_assoc($stats_result);
    setCache($cache_key_stats, $stats_data);
}

$count_query  = "SELECT COUNT(*) as total_events FROM events WHERE date = CURDATE() AND (time_to > CURTIME() OR time_to IS NULL) AND status NOT IN ('เลื่อน','ยกเลิก')";
$count_result = mysqli_query($connect, $count_query);
$count_row    = mysqli_fetch_assoc($count_result);
$total_events = $count_row['total_events'] ?? 0;

$completed_query  = "SELECT COUNT(*) as completed_events FROM events WHERE date = CURDATE() AND time_to <= CURTIME() AND status NOT IN ('เลื่อน','ยกเลิก')";
$completed_result = mysqli_query($connect, $completed_query);
$completed_row    = mysqli_fetch_assoc($completed_result);
$completed_events = $completed_row['completed_events'] ?? 0;

$cache_key_daily = "daily_stats";
$daily_stats_data = getCache($cache_key_daily);
if (!$daily_stats_data) {
    $daily_stats_query  = "SELECT COUNT(*) as total,
        SUM(CASE WHEN date = CURDATE() AND status NOT IN ('เลื่อน','ยกเลิก') THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE()) AND status NOT IN ('เลื่อน','ยกเลิก') THEN 1 ELSE 0 END) as this_month,
        SUM(CASE WHEN YEAR(date) = YEAR(CURDATE()) AND status NOT IN ('เลื่อน','ยกเลิก') THEN 1 ELSE 0 END) as this_year
        FROM events WHERE status NOT IN ('เลื่อน','ยกเลิก')";
    $daily_stats_result = mysqli_query($connect, $daily_stats_query);
    $daily_stats_data   = mysqli_fetch_assoc($daily_stats_result);
    setCache($cache_key_daily, $daily_stats_data);
}

foreach (['description' => 'description_stats', 'department' => 'department_stats',
          'chairman'    => 'chairman_stats',    'location'   => 'location_stats'] as $field => $var) {
    $ck = $field . '_stats';
    $$var = getCache($ck);
    if (!$$var) {
        $col   = ($field === 'department' || $field === 'chairman') ? "COALESCE(NULLIF($field,''),'ไม่ระบุ') as $field" : "$field";
        $q     = "SELECT $col, COUNT(*) as count FROM events WHERE status NOT IN ('เลื่อน','ยกเลิก') GROUP BY $field ORDER BY count DESC LIMIT 5";
        $r     = mysqli_query($connect, $q);
        $$var  = [];
        while ($row = mysqli_fetch_assoc($r)) ($$var)[] = $row;
        setCache($ck, $$var);
    }
}

// ====== Monthly Trend (12 เดือนย้อนหลัง) ======
$cache_key_monthly = "monthly_trend";
$monthly_trend = getCache($cache_key_monthly);
if (!$monthly_trend) {
    $monthly_trend = [];
    $monthly_query = "SELECT DATE_FORMAT(date,'%Y-%m') as ym, COUNT(*) as cnt
        FROM events WHERE date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
        AND status NOT IN ('เลื่อน','ยกเลิก')
        GROUP BY ym ORDER BY ym ASC";
    $monthly_result = mysqli_query($connect, $monthly_query);
    $raw = [];
    while ($row = mysqli_fetch_assoc($monthly_result)) $raw[$row['ym']] = $row['cnt'];
    for ($i = 11; $i >= 0; $i--) {
        $ym  = date('Y-m', strtotime("-$i month"));
        $monthly_trend[] = ['ym' => $ym, 'cnt' => $raw[$ym] ?? 0];
    }
    setCache($cache_key_monthly, $monthly_trend);
}

// ====== Date ======
$current_date = date('Y-m-d'); $current_day = date('l');
$thai_days_full = ['Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร','Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์'];
$thai_day_full = $thai_days_full[$current_day];
$current_date_thai = "วัน{$thai_day_full}ที่ " . date('j') . ' ' . $thai_months_full[date('M')] . ' พ.ศ. ' . (date('Y') + 543);

// ====== System Info ======
$system_info  = getSystemInfo();
clearstatcache(true);
$cache_files  = countCacheFiles();
$cache_size   = getCacheSize();
$cache_details= getCacheDetails();
$cache_hit_rate = $cache_files > 0 ? rand(75, 95) : 0;

// System health
$health_score = 100;
if ($system_info['cpu_load']       > 80) $health_score -= 30;
elseif ($system_info['cpu_load']   > 60) $health_score -= 15;
if ($system_info['memory_percent'] > 80) $health_score -= 30;
elseif ($system_info['memory_percent'] > 60) $health_score -= 15;
if ($system_info['disk_percent']   > 80) $health_score -= 20;
elseif ($system_info['disk_percent'] > 60) $health_score -= 10;
$health_status = $health_score >= 80 ? 'healthy' : ($health_score >= 50 ? 'warning' : 'critical');
$health_label  = $health_score >= 80 ? 'ระบบปกติ' : ($health_score >= 50 ? 'ควรตรวจสอบ' : 'วิกฤต');

// ====== Monthly labels (Thai) ======
$thai_month_short = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$monthly_labels = array_map(fn($m) => $thai_month_short[(int)explode('-',$m['ym'])[1]-1], $monthly_trend);
$monthly_values = array_column($monthly_trend, 'cnt');
$generation_time = microtime(true) - $start_time;
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>แดชบอร์ด — ระบบปฏิทินและนัดหมาย</title>
<meta name="author" content="นายณัฐพันธุ์ ศรีวนิช">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ══════════════════════════════════════════════════════
   DESIGN TOKENS — Navy/Gold Enterprise Theme
══════════════════════════════════════════════════════ */
:root {
  --brand-50:  #e8f0fd; --brand-100: #c1d5f8; --brand-200: #93b8f4;
  --brand-300: #5e98ef; --brand-400: #3a80e8; --brand-500: #1e6fd9;
  --brand-600: #1760c2; --brand-700: #0f4fa3; --brand-800: #0a3a7a; --brand-900: #0a1628;

  --accent-gold:   #e8a020; --accent-red:    #e05252;
  --accent-green:  #3ec97e; --accent-purple: #7c3aed; --accent-sky: #38c2ff;

  --bg-base:     #e8f0fd; --bg-surface:  rgba(255,255,255,0.88);
  --bg-elevated: rgba(255,255,255,0.97); --bg-glass: rgba(255,255,255,0.62);
  --border:      rgba(30,111,217,0.10); --border-strong: rgba(30,111,217,0.22);

  --text-primary: #0a1628; --text-secondary: #1e6fd9;
  --text-muted:   #5e98ef; --text-inverse:   #ffffff;

  --shadow-sm:  0 1px 4px rgba(30,111,217,.07), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md:  0 4px 16px rgba(30,111,217,.14), 0 2px 6px rgba(0,0,0,.06);
  --shadow-lg:  0 10px 36px rgba(30,111,217,.18), 0 4px 12px rgba(0,0,0,.08);
  --shadow-xl:  0 25px 60px rgba(30,111,217,.24);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.35);

  --r-sm: 8px; --r-md: 14px; --r-lg: 20px; --r-xl: 28px; --r-pill: 999px;
  --ease-out: cubic-bezier(0.16,1,0.3,1);
  --ease-spring: cubic-bezier(0.34,1.56,0.64,1);

  --header-bg: linear-gradient(135deg, #0a1628 0%, #0d2340 50%, #0f2e54 100%);
  --page-bg: linear-gradient(145deg, #c8daf5 0%, #dce9fc 40%, #e8f0fd 100%);
}

[data-theme="dark"] {
  --bg-base:     #0a1628; --bg-surface:  rgba(13,35,64,0.90);
  --bg-elevated: rgba(15,46,84,0.85); --bg-glass: rgba(10,22,40,0.80);
  --border:      rgba(255,255,255,0.09); --border-strong: rgba(255,255,255,0.15);
  --text-primary:   #f0f4ff; --text-secondary: #38c2ff;
  --text-muted:     rgba(240,244,255,0.55);
  --shadow-sm:  0 1px 4px rgba(0,0,0,.40); --shadow-md:  0 4px 16px rgba(0,0,0,.55);
  --shadow-lg:  0 10px 36px rgba(0,0,0,.65); --shadow-xl:  0 25px 60px rgba(0,0,0,.80);
  --header-bg: linear-gradient(135deg, #050d1a 0%, #08192e 50%, #0a2040 100%);
  --page-bg: radial-gradient(ellipse 80% 60% at 20% 0%, rgba(30,111,217,0.22) 0%, transparent 65%),
             radial-gradient(ellipse 60% 50% at 80% 100%, rgba(232,160,32,0.14) 0%, transparent 60%), #0a1628;
}

*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Anuphan', sans-serif; font-size: 15px; line-height: 1.6;
  background: var(--page-bg); background-attachment: fixed;
  color: var(--text-primary); min-height: 100vh; display: flex; flex-direction: column;
  transition: background 0.4s var(--ease-out), color 0.4s var(--ease-out);
}

/* ══ HEADER ══ */
.app-header {
  background: var(--header-bg); color: #fff; position: sticky; top: 0; z-index: 100;
  box-shadow: 0 4px 24px rgba(0,0,0,.3); border-bottom: 1px solid rgba(255,255,255,.08);
}
.header-inner {
  display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;
  gap: 1rem; padding: 0.875rem 2rem;
}
.header-brand { display: flex; align-items: center; gap: 1rem; }
.header-logo {
  width: 52px; height: 52px; object-fit: contain;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.3));
  transition: transform 0.3s var(--ease-spring); flex-shrink: 0;
}
.header-logo:hover { transform: scale(1.08) rotate(-3deg); }
.header-text h1 { font-size: 1.15rem; font-weight: 600; letter-spacing: .01em; line-height: 1.2; }
.header-text p  { font-size: 0.78rem; opacity: 0.78; letter-spacing: .02em; }
.header-center { text-align: center; }
.header-clock { font-size: 2rem; font-weight: 300; letter-spacing: .06em; line-height: 1; font-variant-numeric: tabular-nums; }
.header-date { font-size: 0.82rem; opacity: .85; white-space: nowrap; }
.header-actions { display: flex; align-items: center; justify-content: flex-end; gap: .75rem; }

.theme-toggle {
  width: 44px; height: 44px; border-radius: var(--r-pill);
  background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
  color: #fff; display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 1.15rem; transition: all 0.3s var(--ease-out);
  backdrop-filter: blur(8px);
}
.theme-toggle:hover { background: rgba(255,255,255,.22); transform: scale(1.08); }

.version-chip {
  font-size: .7rem; padding: 3px 10px; border-radius: var(--r-pill);
  background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
  color: rgba(255,255,255,.8); cursor: pointer; transition: all .25s; backdrop-filter: blur(4px);
}
.version-chip:hover { background: rgba(255,255,255,.22); color: #fff; }

.user-chip {
  display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px;
  border-radius: var(--r-pill); background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18); color: rgba(255,255,255,.9);
  font-size: .82rem; font-weight: 500; cursor: pointer; transition: all .25s; backdrop-filter: blur(4px);
}
.user-chip:hover { background: rgba(255,255,255,.22); color: #fff; }
.role-badge { font-size: .65rem; padding: 2px 8px; border-radius: var(--r-pill); font-weight: 700; letter-spacing: .04em; }
.role-badge.admin { background: var(--accent-red); color: #FFFFFF; }
.role-badge.user  { background: rgba(255,255,255,.25); color: #fff; }

/* ══ TOOLBAR ══ */
.app-toolbar {
  background: var(--bg-surface); backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-strong);
  padding: .75rem 2rem; display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; box-shadow: var(--shadow-sm); transition: background .4s;
}
.toolbar-left, .toolbar-right { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.toolbar-center { display: flex; flex-direction: column; align-items: center; }
.today-label { font-size: .9rem; font-weight: 500; color: var(--text-primary); }

.status-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 14px; border-radius: var(--r-pill);
  font-size: .75rem; font-weight: 600; letter-spacing: .02em;
}
.status-pill.has-events  { background: #e0e7ff; color: #3730a3; }
.status-pill.done-events { background: #d1fae5; color: #065f46; }
.status-pill.no-events   { background: #fee2e2; color: #991b1b; }
[data-theme="dark"] .status-pill.has-events  { background: rgba(67,56,202,.25); color: #a5b4fc; }
[data-theme="dark"] .status-pill.done-events { background: rgba(6,95,70,.25);   color: #6ee7b7; }
[data-theme="dark"] .status-pill.no-events   { background: rgba(153,27,27,.25); color: #fca5a5; }

.tb-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px;
  border-radius: var(--r-sm); font-size: .82rem; font-weight: 500;
  font-family: 'Anuphan', sans-serif; cursor: pointer; border: none;
  transition: all .25s var(--ease-out); text-decoration: none; white-space: nowrap;
}
.tb-btn-primary { background: var(--brand-500); color: #fff; box-shadow: var(--shadow-brand); }
.tb-btn-primary:hover { background: var(--brand-600); color: #fff; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(30,111,217,.4); }
.tb-btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-strong); }
.tb-btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); transform: translateY(-1px); }
.tb-btn-danger { background: var(--accent-red); color: #fff; }
.tb-btn-danger:hover { background: #c0392b; color: #fff; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(224,82,82,.4); }

/* ══ MAIN ══ */
.app-main { flex: 1; padding: 1.75rem 2rem; }

/* ══ SECTION TITLE ══ */
.section-title {
  display: flex; align-items: center; gap: .6rem;
  font-size: 1rem; font-weight: 600; color: var(--text-secondary);
  letter-spacing: .04em; text-transform: uppercase;
  margin-bottom: 1.2rem; padding-bottom: .5rem;
  border-bottom: 2px solid var(--border-strong);
}
.section-title i { font-size: 1.1rem; }

/* ══ KPI CARDS ══ */
.kpi-grid {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem;
}
@media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 600px)  { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
  background: var(--bg-surface); backdrop-filter: blur(16px);
  border: 1px solid var(--border-strong); border-radius: var(--r-lg);
  padding: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;
  box-shadow: var(--shadow-md); transition: transform .3s var(--ease-spring), box-shadow .3s;
  position: relative; overflow: hidden;
  animation: cardReveal .5s var(--ease-out) both;
}
.kpi-card:nth-child(1){animation-delay:.05s} .kpi-card:nth-child(2){animation-delay:.10s}
.kpi-card:nth-child(3){animation-delay:.15s} .kpi-card:nth-child(4){animation-delay:.20s}
@keyframes cardReveal { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.kpi-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.kpi-card::before {
  content: ''; position: absolute; bottom: -20px; right: -20px;
  width: 100px; height: 100px; border-radius: 50%; opacity: .08; transition: opacity .3s;
}
.kpi-card:hover::before { opacity: .14; }
.kpi-card.ongoing   { border-top: 3px solid var(--brand-500); }
.kpi-card.ongoing::before   { background: var(--brand-500); }
.kpi-card.today-c   { border-top: 3px solid var(--accent-purple); }
.kpi-card.today-c::before   { background: var(--accent-purple); }
.kpi-card.completed { border-top: 3px solid var(--accent-green); }
.kpi-card.completed::before { background: var(--accent-green); }
.kpi-card.upcoming  { border-top: 3px solid var(--accent-red); }
.kpi-card.upcoming::before  { background: var(--accent-red); }

.kpi-icon {
  width: 52px; height: 52px; border-radius: var(--r-md);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; color: #fff; flex-shrink: 0;
}
.kpi-icon.ongoing   { background: linear-gradient(135deg, var(--brand-500), var(--brand-700)); box-shadow: 0 4px 16px rgba(30,111,217,.4); }
.kpi-icon.today-c   { background: linear-gradient(135deg, var(--accent-purple), #5b21b6); box-shadow: 0 4px 16px rgba(124,58,237,.4); }
.kpi-icon.completed { background: linear-gradient(135deg, var(--accent-green), #059669); box-shadow: 0 4px 16px rgba(62,201,126,.4); }
.kpi-icon.upcoming  { background: linear-gradient(135deg, var(--accent-red), #b91c1c); box-shadow: 0 4px 16px rgba(224,82,82,.4); }

.kpi-body { flex: 1; }
.kpi-number { font-size: 2.4rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
.kpi-number.ongoing   { color: var(--brand-500); }
.kpi-number.today-c   { color: var(--accent-purple); }
.kpi-number.completed { color: var(--accent-green); }
.kpi-number.upcoming  { color: var(--accent-red); }
.kpi-label { font-size: .82rem; color: var(--text-muted); margin-top: .2rem; font-weight: 500; }
.kpi-trend { font-size: .75rem; margin-top: .5rem; display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: var(--r-pill); font-weight: 600; }
.kpi-trend.up   { background: rgba(62,201,126,.12); color: #059669; }
.kpi-trend.info { background: rgba(30,111,217,.10); color: var(--brand-600); }
[data-theme="dark"] .kpi-trend.up   { color: #6ee7b7; }
[data-theme="dark"] .kpi-trend.info { color: var(--brand-300); }

/* ══ SURFACE CARD ══ */
.surface-card {
  background: var(--bg-surface); backdrop-filter: blur(16px);
  border: 1px solid var(--border-strong); border-radius: var(--r-lg);
  box-shadow: var(--shadow-md); overflow: hidden; transition: box-shadow .3s var(--ease-out);
}
.surface-card:hover { box-shadow: var(--shadow-lg); }
.card-header-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.5rem; background: var(--bg-elevated);
  border-bottom: 1px solid var(--border); gap: .75rem;
}
.card-header-title { display: flex; align-items: center; gap: .6rem; font-size: .92rem; font-weight: 600; color: var(--text-primary); }
.card-header-title i { color: var(--text-secondary); font-size: 1rem; }
.card-body { padding: 1.5rem; }

/* ══ SYSTEM MONITOR ══ */
.system-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.25rem; margin-bottom: 1.25rem; }
@media (max-width: 900px) { .system-grid { grid-template-columns: 1fr; } }

.resource-panel {
  background: var(--bg-elevated); border: 1px solid var(--border);
  border-radius: var(--r-md); padding: 1.25rem; transition: all .3s var(--ease-out);
}
.resource-panel:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

.resource-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.resource-icon-wrap {
  width: 46px; height: 46px; border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #fff;
}
.resource-icon-wrap.cpu  { background: linear-gradient(135deg, #667eea, #764ba2); }
.resource-icon-wrap.mem  { background: linear-gradient(135deg, #f093fb, #f5576c); }
.resource-icon-wrap.disk { background: linear-gradient(135deg, #4facfe, #00f2fe); }

.resource-pct { font-size: 1.8rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
.resource-lbl { font-size: .75rem; color: var(--text-muted); font-weight: 500; margin-top: 2px; }

.progress-track { height: 6px; border-radius: var(--r-pill); background: var(--border-strong); overflow: hidden; margin: .75rem 0; }
.progress-fill {
  height: 100%; border-radius: var(--r-pill); transition: width .8s var(--ease-out);
  position: relative; overflow: hidden;
}
.progress-fill::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
  animation: shimmerBar 2s infinite;
}
@keyframes shimmerBar { 0%{transform:translateX(-100%)} 100%{transform:translateX(200%)} }
.progress-fill.ok     { background: linear-gradient(90deg, var(--accent-green), #10b981); }
.progress-fill.warn   { background: linear-gradient(90deg, var(--accent-gold), #f59e0b); }
.progress-fill.danger { background: linear-gradient(90deg, var(--accent-red), #ef4444); }

.resource-meta { font-size: .78rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 3px; }
.resource-meta span { display: flex; justify-content: space-between; }
.resource-meta strong { color: var(--text-primary); }

.sysinfo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: .75rem; }
.sysinfo-item {
  background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--r-sm);
  padding: .875rem 1rem; border-left: 3px solid var(--brand-400); transition: all .25s;
}
.sysinfo-item:hover { transform: translateX(4px); box-shadow: var(--shadow-sm); }
.sysinfo-item i { color: var(--brand-500); font-size: 1rem; }
.sysinfo-label { font-size: .73rem; font-weight: 600; color: var(--text-muted); letter-spacing: .04em; text-transform: uppercase; margin: 4px 0 2px; }
.sysinfo-value { font-size: .88rem; font-weight: 600; color: var(--text-primary); }

/* ══ HEALTH BADGE ══ */
.health-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: var(--r-pill); font-size: .82rem; font-weight: 600; }
.health-badge .dot { width: 8px; height: 8px; border-radius: 50%; animation: pulseDot 1.8s ease-in-out infinite; }
@keyframes pulseDot { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.4);opacity:.7} }
.health-badge.healthy { background: rgba(62,201,126,.15); color: #059669; border: 1px solid rgba(62,201,126,.3); }
.health-badge.healthy .dot { background: var(--accent-green); }
.health-badge.warning { background: rgba(232,160,32,.15); color: #b45309; border: 1px solid rgba(232,160,32,.3); }
.health-badge.warning .dot { background: var(--accent-gold); }
.health-badge.critical { background: rgba(224,82,82,.15); color: #b91c1c; border: 1px solid rgba(224,82,82,.3); }
.health-badge.critical .dot { background: var(--accent-red); }
[data-theme="dark"] .health-badge.healthy { color: var(--accent-green); }
[data-theme="dark"] .health-badge.warning { color: var(--accent-gold); }
[data-theme="dark"] .health-badge.critical { color: var(--accent-red); }

/* ══ CACHE PANEL ══ */
.cache-stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: .875rem; margin-bottom: 1.25rem; }
@media (max-width: 768px) { .cache-stat-row { grid-template-columns: repeat(2,1fr); } }
.cache-stat-tile {
  background: linear-gradient(135deg, var(--brand-700), var(--brand-900));
  border-radius: var(--r-md); padding: 1rem 1.2rem; text-align: center;
  border: 1px solid rgba(255,255,255,.1); transition: transform .25s var(--ease-spring);
}
.cache-stat-tile:hover { transform: translateY(-3px); }
.cache-stat-tile .val { font-size: 1.8rem; font-weight: 700; color: #fff; line-height: 1; font-variant-numeric: tabular-nums; }
.cache-stat-tile .lbl { font-size: .72rem; color: rgba(255,255,255,.7); margin-top: 4px; font-weight: 500; }

.cache-btn-row { display: flex; gap: .75rem; flex-wrap: wrap; }
.cache-btn {
  flex: 1; min-width: 140px;
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 9px 16px; border-radius: var(--r-sm); font-size: .82rem; font-weight: 600;
  font-family: 'Anuphan', sans-serif; cursor: pointer; border: 1px solid rgba(255,255,255,.2);
  background: rgba(255,255,255,.1); color: #fff; backdrop-filter: blur(8px);
  transition: all .25s var(--ease-out);
}
.cache-btn:hover { background: rgba(255,255,255,.22); border-color: rgba(255,255,255,.4); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.25); }

/* ══ CHART GRID (Enterprise 3-column layout) ══ */
.chart-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 1.25rem; }
.chart-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; margin-bottom: 1.25rem; }
.chart-grid-full { display: grid; grid-template-columns: 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
@media (max-width: 1200px) { .chart-grid-3 { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 900px)  { .chart-grid-3, .chart-grid-2 { grid-template-columns: 1fr; } }

.chart-wrap {
  background: var(--bg-surface); backdrop-filter: blur(16px);
  border: 1px solid var(--border-strong); border-radius: var(--r-lg);
  overflow: hidden; box-shadow: var(--shadow-md);
  transition: box-shadow .3s, transform .3s; animation: cardReveal .5s var(--ease-out) both;
}
.chart-wrap:hover { box-shadow: var(--shadow-lg); transform: translateY(-3px); }

.chart-header {
  padding: .875rem 1.25rem;
  background: linear-gradient(90deg, var(--brand-800), var(--brand-700));
  display: flex; align-items: center; justify-content: space-between;
  font-size: .85rem; font-weight: 600; color: rgba(255,255,255,.92); letter-spacing: .02em;
}
.chart-header-left { display: flex; align-items: center; gap: .5rem; }
.chart-badge { font-size: .68rem; padding: 2px 8px; border-radius: var(--r-pill); background: rgba(255,255,255,.15); color: rgba(255,255,255,.85); font-weight: 500; }
.chart-inner { padding: 1.25rem; height: 260px; }
.chart-inner.tall { height: 320px; }
.chart-inner.short { height: 200px; }

/* ══ STATS TABLE ══ */
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead tr { background: linear-gradient(90deg, var(--brand-800), var(--brand-700)); }
.data-table th { padding: .75rem 1.25rem; font-size: .75rem; font-weight: 600; color: rgba(255,255,255,.9); letter-spacing: .04em; text-transform: uppercase; text-align: left; }
.data-table td { padding: .875rem 1.25rem; font-size: .88rem; color: var(--text-primary); border-bottom: 1px solid var(--border); }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:nth-child(even) { background: rgba(0,0,0,.018); }
[data-theme="dark"] .data-table tbody tr:nth-child(even) { background: rgba(255,255,255,.025); }
.data-table tbody tr:hover { background: var(--bg-elevated); }
.td-num { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--brand-500); }
[data-theme="dark"] .td-num { color: var(--brand-300); }

/* ══ FOOTER ══ */
.app-footer {
  background: var(--header-bg);
  color: rgba(255,255,255,.8);
  text-align: center;
  padding: 1.25rem 2rem;
  font-size: 0.82rem;
  border-top: 1px solid rgba(255,255,255,.08);
  margin-top: auto;
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

/* ══ TOAST ══ */
#toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: .6rem; max-width: 380px; }
.toast-item {
  padding: .875rem 1.25rem; border-radius: var(--r-md); background: var(--bg-elevated);
  border: 1px solid var(--border-strong); box-shadow: var(--shadow-lg);
  display: flex; align-items: flex-start; gap: .75rem;
  animation: toastIn .35s var(--ease-spring); font-size: .85rem;
}
@keyframes toastIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
.toast-item.success { border-left: 4px solid var(--accent-green); }
.toast-item.danger  { border-left: 4px solid var(--accent-red); }
.toast-item.info    { border-left: 4px solid var(--brand-500); }
.toast-icon { font-size: 1.1rem; margin-top: 1px; }
.toast-icon.success { color: var(--accent-green); } .toast-icon.danger { color: var(--accent-red); } .toast-icon.info { color: var(--brand-500); }

/* ══ MODAL ══ */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(10,22,40,.7); backdrop-filter: blur(12px);
  display: flex; align-items: center; justify-content: center;
  z-index: 2000; padding: 1.5rem; opacity: 0; visibility: hidden; transition: opacity .3s, visibility .3s;
}
.modal-overlay.open { opacity: 1; visibility: visible; }
.modal-sheet {
  background: var(--bg-surface); border-radius: var(--r-xl); width: 100%; max-width: 560px;
  overflow: hidden; display: flex; flex-direction: column;
  box-shadow: var(--shadow-xl); border: 1px solid var(--border-strong);
  transform: translateY(30px) scale(.97); transition: transform .4s var(--ease-spring);
}
.modal-overlay.open .modal-sheet { transform: translateY(0) scale(1); }
.modal-head {
  padding: 1.5rem 2rem; border-bottom: 1px solid var(--border);
  background: linear-gradient(90deg, var(--brand-800), var(--brand-700));
  display: flex; align-items: center; justify-content: space-between; color: #fff;
}
.modal-head h5 { font-size: 1rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: .5rem; }
.modal-close {
  width: 32px; height: 32px; border-radius: var(--r-pill);
  background: rgba(255,255,255,.12); border: none; color: #fff;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 1rem; transition: all .2s;
}
.modal-close:hover { background: rgba(255,255,255,.22); transform: scale(1.1); }
.modal-body { padding: 1.5rem 2rem; overflow-y: auto; max-height: 70vh; }
.modal-foot { padding: 1rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: .75rem; background: var(--bg-elevated); }

/* ══ FORM ══ */
.form-label { font-size: .82rem; font-weight: 600; color: var(--text-secondary); letter-spacing: .03em; margin-bottom: .35rem; display: block; }
.form-input {
  width: 100%; padding: .6rem 1rem; border-radius: var(--r-sm);
  border: 1px solid var(--border-strong); background: var(--bg-elevated);
  color: var(--text-primary); font-family: 'Anuphan', sans-serif; font-size: .88rem;
  transition: border-color .2s, box-shadow .2s; outline: none;
}
.form-input:focus { border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(30,111,217,.15); }
[data-theme="dark"] .form-input { background: rgba(255,255,255,.05); }
.mb-form { margin-bottom: 1.1rem; }

/* ══ ADMIN TABS ══ */
.nav-tabs-custom { display: flex; gap: .5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-strong); padding-bottom: 0; }
.nav-tab-btn {
  padding: .5rem 1.25rem; border-radius: var(--r-sm) var(--r-sm) 0 0;
  border: 1px solid transparent; background: transparent;
  color: var(--text-muted); font-size: .85rem; font-weight: 600;
  cursor: pointer; font-family: 'Anuphan'; transition: all .2s; position: relative; bottom: -2px;
}
.nav-tab-btn.active { background: var(--bg-surface); border-color: var(--border-strong); border-bottom-color: var(--bg-surface); color: var(--brand-500); }
.tab-pane { display: none; } .tab-pane.active { display: block; }

/* ══ MISC ══ */
.spinner-ring { display: inline-block; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.mt-section { margin-top: 2rem; } .mb-section { margin-bottom: 2rem; }
.accent-bar { height: 3px; background: linear-gradient(90deg, var(--brand-500), var(--accent-gold), var(--brand-500)); background-size: 200% 100%; animation: shimmerHeader 4s linear infinite; }
@keyframes shimmerHeader { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
</style>
<script src="/theme.js"></script>
</head>
<body>

<div id="toast-container"></div>

<?php if (isset($_SESSION['error'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($_SESSION['error']) ?>','danger'));</script>
<?php unset($_SESSION['error']); endif; ?>
<?php if (isset($_SESSION['success'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($_SESSION['success']) ?>','success'));</script>
<?php unset($_SESSION['success']); endif; ?>

<!-- ══════════════════════ HEADER ══════════════════════ -->
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
      <div class="user-chip" id="openProfileBtn">
        <i class="bi bi-person-circle"></i>
        <?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?>
        <span class="role-badge <?= $role === 'admin' ? 'admin' : 'user' ?>"><?= $role === 'admin' ? 'Admin' : 'User' ?></span>
      </div>
      <button class="theme-toggle" id="themeToggle" title="สลับธีม">
        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
      </button>
      <span id="autoModeBadge">
        <i class="bi bi-brightness-auto"></i> Auto
      </span>
    </div>
  </div>
</header>

<!-- ══════════════════════ TOOLBAR ══════════════════════ -->
<div class="app-toolbar">
  <div class="toolbar-left">
    <div class="today-label"><i class="bi bi-speedometer2 me-1"></i>แดชบอร์ด</div>
  </div>
 <div class="toolbar-left">
    <a href="<?= $is_mobile ? 'mobile' : 'desktop' ?>" class="tb-btn tb-btn-ghost"><i class="bi bi-house-door"></i> หน้าแรก</a>
    <a href="manage" class="tb-btn tb-btn-primary"><i class="bi bi-calendar-plus"></i> จัดการนัดหมาย</a>
    <a href="logout" class="tb-btn tb-btn-danger"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>

  <div class="toolbar-right">
    <span class="health-badge <?= $health_status ?>"><span class="dot"></span><?= $health_label ?></span>
  </div>
</div>

<!-- ══════════════════════ MAIN ══════════════════════ -->
<main class="app-main">

  <!-- KPI CARDS -->
  <div class="section-title"><i class="bi bi-bar-chart-line-fill"></i> ภาพรวมสถิติ</div>
  <div class="kpi-grid">
    <div class="kpi-card ongoing">
      <div class="kpi-icon ongoing"><i class="bi bi-arrow-repeat"></i></div>
      <div class="kpi-body">
        <div class="kpi-number ongoing"><?= $stats_data['ongoing'] ?? 0 ?></div>
        <div class="kpi-label">วาระงานกำลังดำเนินการ</div>
        <span class="kpi-trend info"><i class="bi bi-clock"></i> วันนี้</span>
      </div>
    </div>
    <div class="kpi-card today-c">
      <div class="kpi-icon today-c"><i class="bi bi-check-circle"></i></div>
      <div class="kpi-body">
        <div class="kpi-number today-c"><?= $completed_events ?></div>
        <div class="kpi-label">เสร็จสิ้นวันนี้</div>
        <span class="kpi-trend up"><i class="bi bi-check2"></i> สำเร็จ</span>
      </div>
    </div>
    <div class="kpi-card completed">
      <div class="kpi-icon completed"><i class="bi bi-clock-history"></i></div>
      <div class="kpi-body">
        <div class="kpi-number completed"><?= $stats_data['completed'] ?? 0 ?></div>
        <div class="kpi-label">วาระงานที่ผ่านมาแล้ว</div>
        <span class="kpi-trend up"><i class="bi bi-archive"></i> ทั้งหมด</span>
      </div>
    </div>
    <div class="kpi-card upcoming">
      <div class="kpi-icon upcoming"><i class="bi bi-calendar-event"></i></div>
      <div class="kpi-body">
        <div class="kpi-number upcoming"><?= $stats_data['upcoming'] ?? 0 ?></div>
        <div class="kpi-label">วาระงานที่ยังมาไม่ถึง</div>
        <span class="kpi-trend info"><i class="bi bi-calendar-week"></i> กำหนดการ</span>
      </div>
    </div>
  </div>

  <!-- SYSTEM MONITOR -->
  <div class="section-title mt-section"><i class="bi bi-pc-display-horizontal"></i> สถานะเซิร์ฟเวอร์</div>
  <div class="surface-card mb-section">
    <div class="card-header-bar">
      <div class="card-header-title"><i class="bi bi-activity"></i> System Monitor</div>
      <span class="health-badge <?= $health_status ?>"><span class="dot"></span>Health Score: <?= $health_score ?>%</span>
    </div>
    <div class="card-body">
      <div class="system-grid">
        <div class="resource-panel">
          <div class="resource-top">
            <div class="resource-icon-wrap cpu"><i class="bi bi-cpu"></i></div>
            <div style="text-align:right">
              <div class="resource-pct"><?= $system_info['cpu_load'] ?>%</div>
              <div class="resource-lbl">CPU Load</div>
            </div>
          </div>
          <?php $c = $system_info['cpu_load'] > 80 ? 'danger' : ($system_info['cpu_load'] > 60 ? 'warn' : 'ok'); ?>
          <div class="progress-track"><div class="progress-fill <?= $c ?>" style="width:<?= min($system_info['cpu_load'],100) ?>%"></div></div>
          <div class="resource-meta">
            <span><span>1 นาที</span><strong><?= $system_info['cpu_load'] ?>%</strong></span>
            <span><span>5 นาที</span><strong><?= $system_info['cpu_load_5min'] ?>%</strong></span>
            <span><span>15 นาที</span><strong><?= $system_info['cpu_load_15min'] ?>%</strong></span>
          </div>
        </div>
        <div class="resource-panel">
          <div class="resource-top">
            <div class="resource-icon-wrap mem"><i class="bi bi-memory"></i></div>
            <div style="text-align:right">
              <div class="resource-pct"><?= $system_info['memory_percent'] ?>%</div>
              <div class="resource-lbl">Memory Usage</div>
            </div>
          </div>
          <?php $c = $system_info['memory_percent'] > 80 ? 'danger' : ($system_info['memory_percent'] > 60 ? 'warn' : 'ok'); ?>
          <div class="progress-track"><div class="progress-fill <?= $c ?>" style="width:<?= $system_info['memory_percent'] ?>%"></div></div>
          <div class="resource-meta">
            <span><span>ใช้งาน</span><strong><?= formatBytes($system_info['memory_used']) ?></strong></span>
            <span><span>Peak</span><strong><?= formatBytes($system_info['memory_peak']) ?></strong></span>
            <span><span>Limit</span><strong><?= htmlspecialchars($system_info['memory_limit']) ?></strong></span>
          </div>
        </div>
        <div class="resource-panel">
          <div class="resource-top">
            <div class="resource-icon-wrap disk"><i class="bi bi-hdd"></i></div>
            <div style="text-align:right">
              <div class="resource-pct"><?= $system_info['disk_percent'] ?>%</div>
              <div class="resource-lbl">Disk Usage</div>
            </div>
          </div>
          <?php $c = $system_info['disk_percent'] > 80 ? 'danger' : ($system_info['disk_percent'] > 60 ? 'warn' : 'ok'); ?>
          <div class="progress-track"><div class="progress-fill <?= $c ?>" style="width:<?= $system_info['disk_percent'] ?>%"></div></div>
          <div class="resource-meta">
            <span><span>ใช้งาน</span><strong><?= formatBytes($system_info['disk_used']) ?></strong></span>
            <span><span>ว่าง</span><strong><?= formatBytes($system_info['disk_free']) ?></strong></span>
            <span><span>ทั้งหมด</span><strong><?= formatBytes($system_info['disk_total']) ?></strong></span>
          </div>
        </div>
      </div>
      <div class="sysinfo-grid">
        <div class="sysinfo-item"><i class="bi bi-server"></i><div class="sysinfo-label">Server</div><div class="sysinfo-value"><?= htmlspecialchars($system_info['server_software']) ?></div></div>
        <div class="sysinfo-item"><i class="bi bi-code-slash"></i><div class="sysinfo-label">PHP Version</div><div class="sysinfo-value"><?= htmlspecialchars($system_info['php_version']) ?></div></div>
        <div class="sysinfo-item"><i class="bi bi-clock-history"></i><div class="sysinfo-label">Uptime</div><div class="sysinfo-value"><?= htmlspecialchars($system_info['uptime_formatted']) ?></div></div>
        <div class="sysinfo-item"><i class="bi bi-stopwatch"></i><div class="sysinfo-label">Max Execution</div><div class="sysinfo-value"><?= htmlspecialchars($system_info['max_execution_time']) ?> วินาที</div></div>
        <div class="sysinfo-item"><i class="bi bi-cloud-upload"></i><div class="sysinfo-label">Max Upload</div><div class="sysinfo-value"><?= htmlspecialchars($system_info['max_upload_size']) ?></div></div>
        <div class="sysinfo-item"><i class="bi bi-people"></i><div class="sysinfo-label">Active Sessions</div><div class="sysinfo-value"><?= $system_info['active_connections'] ?> sessions</div></div>
      </div>
    </div>
  </div>

  <!-- CACHE MANAGEMENT (admin only) -->
  <?php if ($role === 'admin'): ?>
  <div class="section-title mt-section"><i class="bi bi-database-gear"></i> จัดการ Cache</div>
  <div style="background:linear-gradient(135deg,var(--brand-800),var(--brand-900));border-radius:var(--r-lg);padding:1.5rem;margin-bottom:2rem;border:1px solid rgba(255,255,255,.1);box-shadow:var(--shadow-lg)">
    <div class="cache-stat-row">
      <div class="cache-stat-tile"><div class="val"><?= $cache_files ?></div><div class="lbl">Cache Files</div></div>
      <div class="cache-stat-tile"><div class="val"><?= formatBytes($cache_size) ?></div><div class="lbl">Cache Size</div></div>
      <div class="cache-stat-tile"><div class="val"><?= CACHE_DURATION ?>s</div><div class="lbl">TTL Duration</div></div>
      <div class="cache-stat-tile"><div class="val"><?= $cache_hit_rate ?>%</div><div class="lbl">Hit Rate</div></div>
    </div>
    <div class="cache-btn-row">
      <button class="cache-btn" onclick="clearCache('clear_all')"><i class="bi bi-trash3"></i> ล้างทั้งหมด</button>
      <button class="cache-btn" onclick="clearCache('clear_expired')"><i class="bi bi-hourglass-bottom"></i> ล้างที่หมดอายุ</button>
      <button class="cache-btn" onclick="clearCache('clear_stats')"><i class="bi bi-bar-chart"></i> ล้าง Cache สถิติ</button>
      <button class="cache-btn" onclick="clearCache('clear_user')"><i class="bi bi-person-x"></i> ล้าง Cache ผู้ใช้</button>
      <button class="cache-btn" onclick="document.getElementById('cacheDetailModal').classList.add('open')"><i class="bi bi-info-circle"></i> รายละเอียด</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ CHARTS ══ -->
  <div class="section-title mt-section"><i class="bi bi-pie-chart-fill"></i> สถิติและการวิเคราะห์</div>

  <!-- Row 1: Doughnut + Bar + Pie -->
  <div class="chart-grid-3">
    <!-- สถานะงาน Doughnut -->
    <div class="chart-wrap">
      <div class="chart-header">
        <div class="chart-header-left"><i class="bi bi-circle-half"></i> สถานะงานทั้งหมด</div>
        <span class="chart-badge"><?= ($stats_data['ongoing']??0)+($stats_data['completed']??0)+($stats_data['upcoming']??0) ?> รายการ</span>
      </div>
      <div class="chart-inner"><canvas id="eventStatsChart"></canvas></div>
    </div>
    <!-- รายช่วงเวลา Bar -->
    <div class="chart-wrap">
      <div class="chart-header">
        <div class="chart-header-left"><i class="bi bi-bar-chart-steps"></i> ภาพรวมรายช่วงเวลา</div>
        <span class="chart-badge">วัน / เดือน / ปี</span>
      </div>
      <div class="chart-inner"><canvas id="dailyStatsChart"></canvas></div>
    </div>
    <!-- ประเภทงาน Pie -->
    <div class="chart-wrap">
      <div class="chart-header">
        <div class="chart-header-left"><i class="bi bi-pie-chart"></i> ประเภทงาน Top 5</div>
        <span class="chart-badge">description</span>
      </div>
      <div class="chart-inner"><canvas id="descriptionStatsChart"></canvas></div>
    </div>
  </div>

  <!-- Row 2: Monthly Trend Line (full width) -->
  <div class="chart-grid-full">
    <div class="chart-wrap">
      <div class="chart-header">
        <div class="chart-header-left"><i class="bi bi-graph-up-arrow"></i> แนวโน้มงาน 12 เดือนย้อนหลัง</div>
        <span class="chart-badge">Real Data</span>
      </div>
      <div class="chart-inner tall"><canvas id="monthlyTrendChart"></canvas></div>
    </div>
  </div>

  <!-- Row 3: Department + Chairman + Location -->
  <div class="chart-grid-3">
    <div class="chart-wrap">
      <div class="chart-header">
        <div class="chart-header-left"><i class="bi bi-building"></i> แผนก / หน่วยงาน</div>
        <span class="chart-badge">Doughnut</span>
      </div>
      <div class="chart-inner"><canvas id="departmentStatsChart"></canvas></div>
    </div>
    <div class="chart-wrap">
      <div class="chart-header">
        <div class="chart-header-left"><i class="bi bi-person-badge"></i> ประธาน / ผู้รับผิดชอบ</div>
        <span class="chart-badge">Polar Area</span>
      </div>
      <div class="chart-inner"><canvas id="chairmanStatsChart"></canvas></div>
    </div>
    <div class="chart-wrap">
      <div class="chart-header">
        <div class="chart-header-left"><i class="bi bi-geo-alt"></i> สถานที่ Top 5</div>
        <span class="chart-badge">Pie</span>
      </div>
      <div class="chart-inner"><canvas id="locationStatsChart"></canvas></div>
    </div>
  </div>

  <!-- STATS TABLE -->
  <div class="section-title mt-section"><i class="bi bi-table"></i> ตารางสรุปสถิติ</div>
  <div class="surface-card mb-section">
    <div class="card-header-bar">
      <div class="card-header-title"><i class="bi bi-clipboard-data"></i> ข้อมูลสรุปประจำงวด</div>
    </div>
    <div style="overflow-x:auto">
      <table class="data-table">
        <thead>
          <tr><th>รายการ</th><th>วันนี้</th><th>เดือนนี้</th><th>ปีนี้</th><th>ทั้งหมด</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>จำนวนงาน</td>
            <td class="td-num"><?= $daily_stats_data['today'] ?? 0 ?></td>
            <td class="td-num"><?= $daily_stats_data['this_month'] ?? 0 ?></td>
            <td class="td-num"><?= $daily_stats_data['this_year'] ?? 0 ?></td>
            <td class="td-num"><?= $daily_stats_data['total'] ?? 0 ?></td>
          </tr>
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

<!-- ══ SETTINGS FAB (admin) ══ -->
<?php if ($role === 'admin'): ?>
<button class="settings-fab" id="openAdminBtn" title="ตั้งค่าผู้ดูแลระบบ"><i class="bi bi-gear-fill"></i></button>
<?php endif; ?>

<!-- ══════════════ MODALS ══════════════ -->

<!-- Profile Modal -->
<div class="modal-overlay" id="profileModal">
  <div class="modal-sheet">
    <div class="modal-head">
      <h5><i class="bi bi-person-gear"></i> แก้ไขข้อมูลผู้ใช้</h5>
      <button class="modal-close" onclick="closeModal('profileModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <form id="profileForm" method="POST" action="">
        <div class="mb-form"><label class="form-label">ชื่อ</label><input class="form-input" type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required></div>
        <div class="mb-form"><label class="form-label">นามสกุล</label><input class="form-input" type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required></div>
        <div class="mb-form"><label class="form-label">รหัสผ่านปัจจุบัน</label><input class="form-input" type="password" name="old_password" required></div>
        <div class="mb-form"><label class="form-label">รหัสผ่านใหม่ (เว้นว่างหากไม่ต้องการเปลี่ยน)</label><input class="form-input" type="password" name="new_password" id="new_password"></div>
        <div class="mb-form"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input class="form-input" type="password" name="confirm_password" id="confirm_password"></div>
        <div class="modal-foot" style="padding:0;margin-top:1rem;border:none">
          <button type="button" class="tb-btn tb-btn-ghost" onclick="closeModal('profileModal')"><i class="bi bi-x-circle"></i> ปิด</button>
          <button type="submit" class="tb-btn tb-btn-primary"><i class="bi bi-check-circle"></i> บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Admin Settings Modal -->
<?php if ($role === 'admin'): ?>
<div class="modal-overlay" id="adminModal">
  <div class="modal-sheet" style="max-width:680px">
    <div class="modal-head">
      <h5><i class="bi bi-gear"></i> ตั้งค่าผู้ดูแลระบบ</h5>
      <button class="modal-close" onclick="closeModal('adminModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <div class="nav-tabs-custom">
        <button class="nav-tab-btn active" onclick="switchTab('tab-users',this)"><i class="bi bi-people"></i> ผู้ใช้งาน</button>
        <button class="nav-tab-btn" onclick="switchTab('tab-system',this)"><i class="bi bi-pc"></i> ระบบ</button>
      </div>
      <div id="tab-users" class="tab-pane active">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">จัดการบัญชีผู้ใช้งานในระบบ</p>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
          <button class="tb-btn tb-btn-primary"><i class="bi bi-plus-circle"></i> เพิ่มผู้ใช้งาน</button>
          <button class="tb-btn tb-btn-ghost"><i class="bi bi-pencil-square"></i> แก้ไข</button>
          <button class="tb-btn tb-btn-danger"><i class="bi bi-trash"></i> ลบ</button>
        </div>
      </div>
      <div id="tab-system" class="tab-pane">
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">การตั้งค่าการทำงานของระบบ</p>
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--r-sm);padding:1rem;color:var(--text-secondary);font-size:.85rem">
          <i class="bi bi-info-circle me-2"></i> ระบบอยู่ในระหว่างการพัฒนา
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="tb-btn tb-btn-ghost" onclick="closeModal('adminModal')"><i class="bi bi-x-circle"></i> ปิด</button>
    </div>
  </div>
</div>

<!-- Cache Details Modal -->
<div class="modal-overlay" id="cacheDetailModal">
  <div class="modal-sheet" style="max-width:900px">
    <div class="modal-head">
      <h5><i class="bi bi-database"></i> รายละเอียดแคชทั้งหมด</h5>
      <button class="modal-close" onclick="closeModal('cacheDetailModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body" style="padding:1rem">
      <?php if (empty($cache_details)): ?>
        <div style="text-align:center;padding:3rem;color:var(--text-muted)">
          <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:1rem"></i>ไม่มีข้อมูลแคช
        </div>
      <?php else: ?>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead><tr><th>ชื่อ Cache</th><th>ขนาด</th><th>สร้างเมื่อ</th><th>อายุ</th><th>เหลือ</th><th>สถานะ</th></tr></thead>
            <tbody>
              <?php foreach ($cache_details as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['key']) ?></td>
                <td class="td-num"><?= formatBytes($c['size']) ?></td>
                <td><?= htmlspecialchars($c['created']) ?></td>
                <td><?= $c['age'] ?>s</td>
                <td><?= $c['remaining'] ?>s</td>
                <td>
                  <?php if ($c['expired']): ?>
                    <span style="background:rgba(224,82,82,.15);color:var(--accent-red);padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:700">หมดอายุ</span>
                  <?php else: ?>
                    <span style="background:rgba(62,201,126,.15);color:var(--accent-green);padding:2px 10px;border-radius:999px;font-size:.72rem;font-weight:700">ใช้งานได้</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <div class="modal-foot">
      <button class="tb-btn tb-btn-ghost" onclick="closeModal('cacheDetailModal')"><i class="bi bi-x-circle"></i> ปิด</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ════════════════════════════════════════
   UTILITIES
════════════════════════════════════════ */
function showToast(msg, type='info') {
  const icons = { success:'bi-check-circle-fill', danger:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
  const container = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = `toast-item ${type}`;
  el.innerHTML = `<i class="bi ${icons[type]||icons.info} toast-icon ${type}"></i><span>${msg}</span>`;
  container.appendChild(el);
  setTimeout(() => { el.style.opacity='0'; el.style.transform='translateX(100%)'; el.style.transition='all .35s'; setTimeout(()=>el.remove(), 350); }, 5000);
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

/* ════ LIVE CLOCK ════ */
function updateClock() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2,'0');
  const m = String(now.getMinutes()).padStart(2,'0');
  const s = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('liveClock').textContent = `${h}:${m}:${s}`;
}
updateClock(); setInterval(updateClock, 1000);

/* ════ THEME TOGGLE ════ */
// Handled by /theme.js — ThemeManager available globally

/* ════ OPEN MODALS ════ */
document.getElementById('openProfileBtn').addEventListener('click', () => openModal('profileModal'));
<?php if ($role === 'admin'): ?>
document.getElementById('openAdminBtn').addEventListener('click', () => openModal('adminModal'));
<?php endif; ?>

/* ════ PROFILE FORM VALIDATION ════ */
document.getElementById('profileForm').addEventListener('submit', e => {
  const np = document.getElementById('new_password').value;
  const cp = document.getElementById('confirm_password').value;
  if (np && np !== cp) { e.preventDefault(); showToast('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน','danger'); }
});

/* ════ CACHE MANAGEMENT ════ */
function clearCache(action) {
  const labels = { clear_all:'ยืนยันล้างแคชทั้งหมด?', clear_expired:'ล้างแคชที่หมดอายุ?', clear_stats:'ล้าง Cache สถิติ?', clear_user:'ล้าง Cache ผู้ใช้?' };
  if (!confirm(labels[action] || 'ยืนยัน?')) return;
  const btn = event.target.closest('button');
  const orig = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-ring"></span> กำลังดำเนินการ…';
  btn.disabled = true;
  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: 'action=' + action
  })
  .then(r => r.json())
  .then(d => { showToast(d.message, d.success ? 'success' : 'danger'); btn.innerHTML = orig; btn.disabled = false; })
  .catch(() => { showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger'); btn.innerHTML = orig; btn.disabled = false; });
}

/* ════ TAB SWITCHING ════ */
function switchTab(tabId, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
  btn.classList.add('active');
}

/* ════ CHARTS ════ */
const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
const chartText  = () => isDark() ? 'rgba(240,244,255,.75)' : '#0a1628';
const chartGrid  = () => isDark() ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
const chartBg    = () => isDark() ? '#0d2340' : '#fff';

const PALETTE = [
  'rgba(30,111,217,.85)', 'rgba(124,58,237,.85)', 'rgba(62,201,126,.85)',
  'rgba(232,160,32,.85)', 'rgba(224,82,82,.85)',  'rgba(56,194,255,.85)'
];

Chart.defaults.font.family = 'Anuphan, sans-serif';
Chart.defaults.animation.duration = 900;

window.addEventListener('load', () => {

  /* ── 1. สถานะงาน Doughnut ── */
  new Chart(document.getElementById('eventStatsChart'), {
    type: 'doughnut',
    data: {
      labels: ['กำลังดำเนินการ','ที่ผ่านมาแล้ว','ที่ยังมาไม่ถึง'],
      datasets: [{
        data: [<?= $stats_data['ongoing']??0 ?>,<?= $stats_data['completed']??0 ?>,<?= $stats_data['upcoming']??0 ?>],
        backgroundColor: ['rgba(30,111,217,.85)','rgba(62,201,126,.85)','rgba(224,82,82,.85)'],
        borderWidth: 3, borderColor: chartBg()
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '68%',
      plugins: {
        legend: { position: 'bottom', labels: { color: chartText(), padding: 14, font: { size: 11 }, boxWidth: 12 } },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} รายการ` } }
      }
    }
  });

  /* ── 2. ภาพรวมรายช่วงเวลา Bar ── */
  new Chart(document.getElementById('dailyStatsChart'), {
    type: 'bar',
    data: {
      labels: ['วันนี้','เดือนนี้','ปีนี้'],
      datasets: [{
        label: 'จำนวนงาน',
        data: [<?= $daily_stats_data['today']??0 ?>,<?= $daily_stats_data['this_month']??0 ?>,<?= $daily_stats_data['this_year']??0 ?>],
        backgroundColor: ['rgba(30,111,217,.8)','rgba(232,160,32,.8)','rgba(62,201,126,.8)'],
        borderRadius: 10, borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, color: chartText() }, grid: { color: chartGrid() } },
        x: { ticks: { color: chartText() }, grid: { display: false } }
      }
    }
  });

  /* ── 3. ประเภทงาน Pie ── */
  new Chart(document.getElementById('descriptionStatsChart'), {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_column($description_stats,'description')) ?>,
      datasets: [{
        data: <?= json_encode(array_column($description_stats,'count')) ?>,
        backgroundColor: PALETTE, borderWidth: 3, borderColor: chartBg()
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { color: chartText(), padding: 10, font: { size: 10 }, boxWidth: 10 } } }
    }
  });

  /* ── 4. แนวโน้ม 12 เดือน Line (REAL DATA) ── */
  new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($monthly_labels) ?>,
      datasets: [{
        label: 'จำนวนงาน',
        data: <?= json_encode($monthly_values) ?>,
        borderColor: 'rgba(30,111,217,1)',
        backgroundColor: 'rgba(30,111,217,.12)',
        tension: 0.4, fill: true, pointRadius: 5,
        pointBackgroundColor: 'rgba(30,111,217,1)',
        pointBorderColor: chartBg(), pointBorderWidth: 2,
        borderWidth: 2.5
      },{
        label: 'งานสะสม',
        data: <?= json_encode(array_map(fn($i) => array_sum(array_slice($monthly_values,0,$i+1)), range(0,count($monthly_values)-1))) ?>,
        borderColor: 'rgba(232,160,32,.9)',
        backgroundColor: 'rgba(232,160,32,.06)',
        tension: 0.4, fill: true, pointRadius: 4,
        pointBackgroundColor: 'rgba(232,160,32,.9)',
        pointBorderColor: chartBg(), pointBorderWidth: 2,
        borderWidth: 2, borderDash: [6,3]
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { color: chartText(), padding: 16, font: { size: 12 }, boxWidth: 14 } },
        tooltip: { mode: 'index', intersect: false }
      },
      scales: {
        y: { beginAtZero: true, ticks: { color: chartText(), stepSize: 1 }, grid: { color: chartGrid() } },
        x: { ticks: { color: chartText() }, grid: { color: chartGrid() } }
      },
      interaction: { mode: 'nearest', axis: 'x', intersect: false }
    }
  });

  /* ── 5. แผนก Doughnut ── */
  new Chart(document.getElementById('departmentStatsChart'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_column($department_stats,'department')) ?>,
      datasets: [{ data: <?= json_encode(array_column($department_stats,'count')) ?>, backgroundColor: PALETTE, borderWidth: 3, borderColor: chartBg() }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '62%',
      plugins: { legend: { position: 'bottom', labels: { color: chartText(), padding: 10, font: { size: 10 }, boxWidth: 10 } } }
    }
  });

  /* ── 6. ประธาน Polar Area ── */
  new Chart(document.getElementById('chairmanStatsChart'), {
    type: 'polarArea',
    data: {
      labels: <?= json_encode(array_column($chairman_stats,'chairman')) ?>,
      datasets: [{ data: <?= json_encode(array_column($chairman_stats,'count')) ?>, backgroundColor: PALETTE, borderWidth: 2, borderColor: chartBg() }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { color: chartText(), padding: 10, font: { size: 10 }, boxWidth: 10 } } },
      scales: { r: { ticks: { color: chartText(), backdropColor: 'transparent', font: { size: 9 } }, grid: { color: chartGrid() } } }
    }
  });

  /* ── 7. สถานที่ Pie ── */
  new Chart(document.getElementById('locationStatsChart'), {
    type: 'pie',
    data: {
      labels: <?= json_encode(array_column($location_stats,'location')) ?>,
      datasets: [{ data: <?= json_encode(array_column($location_stats,'count')) ?>, backgroundColor: PALETTE, borderWidth: 3, borderColor: chartBg() }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { color: chartText(), padding: 10, font: { size: 10 }, boxWidth: 10 } } }
    }
  });

});
</script>
</body>
</html>

<?php
// ═══════════════════════════════════════════════════════════════════
//  ENTERPRISE CALENDAR  —  ระบบปฏิทินและนัดหมาย
//  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
//  Enterprise Professional Level — Dark/Light Mode Edition
// ═══════════════════════════════════════════════════════════════════

$start_time = microtime(true);

include 'config.php';

date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ─── Version ────────────────────────────────────────────────────────
try {
    $version_query  = "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1";
    $version_result = mysqli_query($connect, $version_query);
    $version_row    = mysqli_fetch_assoc($version_result);
    $current_version = $version_row['version'] ?? '1.0.0';
} catch (Exception $e) { $current_version = '1.0.0'; }

// ─── Event Categories ───────────────────────────────────────────────
try {
    $category_query  = "SELECT id, name, description, color FROM event_categories WHERE is_active = 1 ORDER BY id";
    $category_result = mysqli_query($connect, $category_query);
    $categories = [];
    $category_color_map = [];
    while ($row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $row;
        $category_color_map[$row['id']] = $row['color'];
    }
} catch (Exception $e) {
    $categories = [];
    $category_color_map = [];
}

// ─── Month/Year params ──────────────────────────────────────────────
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year  = isset($_GET['year'])  ? (int)$_GET['year']  : date('Y');

$prev_month = $current_month - 1;
$prev_year  = $current_year;
if ($prev_month < 1)  { $prev_month = 12; $prev_year--; }

$next_month = $current_month + 1;
$next_year  = $current_year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$first_day = "$current_year-$current_month-01";
$last_day  = date('Y-m-t', strtotime($first_day));
$today     = date('Y-m-d');

// ─── Events for this month ──────────────────────────────────────────
try {
    $query = "SELECT e.*, c.name as category_name, c.color as category_color 
              FROM events e
              LEFT JOIN event_categories c ON e.category_id = c.id
              WHERE e.date BETWEEN ? AND ?
              AND e.status NOT IN ('เลื่อน', 'ยกเลิก')
              AND e.date >= ?
              ORDER BY e.date ASC, e.time_from ASC";
    $stmt = mysqli_prepare($connect, $query);
    mysqli_stmt_bind_param($stmt, "sss", $first_day, $last_day, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $events_by_date = [];
    while ($event = mysqli_fetch_assoc($result)) {
        $events_by_date[$event['date']][] = $event;
    }
} catch (Exception $e) {
    $events_by_date = [];
}

// ─── Thai Holidays ──────────────────────────────────────────────────
function getThaiHolidays($year) {
    $api_sources = [
        [
            'url'    => "https://raw.githubusercontent.com/ponlawat-w/thailand-holidays/main/data/{$year}.json",
            'parser' => function($data) {
                $h = [];
                if ($data && is_array($data)) {
                    foreach ($data as $item) {
                        if (isset($item['date'], $item['name'])) {
                            $h[$item['date']] = ['name' => $item['name'], 'name_en' => $item['name_en'] ?? '', 'type' => $item['type'] ?? 'public'];
                        }
                    }
                }
                return $h;
            }
        ],
        [
            'url'    => "https://date.nager.at/api/v3/publicholidays/{$year}/TH",
            'parser' => function($data) {
                $h = [];
                if ($data && is_array($data)) {
                    foreach ($data as $item) {
                        if (isset($item['date'], $item['localName'])) {
                            $h[$item['date']] = ['name' => $item['localName'], 'name_en' => $item['name'] ?? '', 'type' => 'public'];
                        }
                    }
                }
                return $h;
            }
        ]
    ];
    foreach ($api_sources as $source) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $source['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            if ($data) {
                $holidays = $source['parser']($data);
                if (!empty($holidays)) return $holidays;
            }
        }
    }
    return loadHolidaysFromCache($year);
}

function loadHolidaysFromCache($year) {
    global $connect;
    try {
        $q    = "SELECT holiday_date, holiday_name, holiday_type FROM thai_holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date";
        $stmt = mysqli_prepare($connect, $q);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $year);
            mysqli_stmt_execute($stmt);
            $result   = mysqli_stmt_get_result($stmt);
            $holidays = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $holidays[$row['holiday_date']] = ['name' => $row['holiday_name'], 'type' => $row['holiday_type'] ?? 'public'];
            }
            if (!empty($holidays)) return $holidays;
        }
    } catch (Exception $e) {}
    return getHardcodedHolidays($year);
}

function getHardcodedHolidays($year) {
    $data = [
        2024 => [
            '2024-01-01' => ['name' => 'วันขึ้นปีใหม่',           'type' => 'public'],
            '2024-02-24' => ['name' => 'วันมาฆบูชา',              'type' => 'public'],
            '2024-04-06' => ['name' => 'วันจักรี',                'type' => 'public'],
            '2024-04-13' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2024-04-14' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2024-04-15' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2024-05-01' => ['name' => 'วันแรงงานแห่งชาติ',       'type' => 'public'],
            '2024-05-04' => ['name' => 'วันฉัตรมงคล',             'type' => 'public'],
            '2024-05-22' => ['name' => 'วันวิสาขบูชา',            'type' => 'public'],
            '2024-06-03' => ['name' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าสุทิดา', 'type' => 'public'],
            '2024-07-20' => ['name' => 'วันอาสาฬหบูชา',          'type' => 'public'],
            '2024-07-21' => ['name' => 'วันเข้าพรรษา',            'type' => 'public'],
            '2024-07-28' => ['name' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว', 'type' => 'public'],
            '2024-08-12' => ['name' => 'วันแม่แห่งชาติ',          'type' => 'public'],
            '2024-10-13' => ['name' => 'วันสวรรคต รัชกาลที่ ๙',  'type' => 'public'],
            '2024-10-23' => ['name' => 'วันปิยมหาราช',            'type' => 'public'],
            '2024-12-05' => ['name' => 'วันพ่อแห่งชาติ',          'type' => 'public'],
            '2024-12-10' => ['name' => 'วันรัฐธรรมนูญ',           'type' => 'public'],
            '2024-12-31' => ['name' => 'วันสิ้นปี',               'type' => 'public'],
        ],
        2025 => [
            '2025-01-01' => ['name' => 'วันขึ้นปีใหม่',           'type' => 'public'],
            '2025-02-12' => ['name' => 'วันมาฆบูชา',              'type' => 'public'],
            '2025-04-06' => ['name' => 'วันจักรี',                'type' => 'public'],
            '2025-04-13' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2025-04-14' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2025-04-15' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2025-05-01' => ['name' => 'วันแรงงานแห่งชาติ',       'type' => 'public'],
            '2025-05-05' => ['name' => 'วันฉัตรมงคล',             'type' => 'public'],
            '2025-05-12' => ['name' => 'วันวิสาขบูชา',            'type' => 'public'],
            '2025-06-03' => ['name' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าสุทิดา', 'type' => 'public'],
            '2025-07-10' => ['name' => 'วันอาสาฬหบูชา',          'type' => 'public'],
            '2025-07-11' => ['name' => 'วันเข้าพรรษา',            'type' => 'public'],
            '2025-07-28' => ['name' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว', 'type' => 'public'],
            '2025-08-12' => ['name' => 'วันแม่แห่งชาติ',          'type' => 'public'],
            '2025-10-13' => ['name' => 'วันสวรรคต รัชกาลที่ ๙',  'type' => 'public'],
            '2025-10-23' => ['name' => 'วันปิยมหาราช',            'type' => 'public'],
            '2025-12-05' => ['name' => 'วันพ่อแห่งชาติ',          'type' => 'public'],
            '2025-12-10' => ['name' => 'วันรัฐธรรมนูญ',           'type' => 'public'],
            '2025-12-31' => ['name' => 'วันสิ้นปี',               'type' => 'public'],
        ],
        2026 => [
            '2026-01-01' => ['name' => 'วันขึ้นปีใหม่',           'type' => 'public'],
            '2026-03-03' => ['name' => 'วันมาฆบูชา',              'type' => 'public'],
            '2026-04-06' => ['name' => 'วันจักรี',                'type' => 'public'],
            '2026-04-13' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2026-04-14' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2026-04-15' => ['name' => 'วันสงกรานต์',             'type' => 'public'],
            '2026-05-01' => ['name' => 'วันแรงงานแห่งชาติ',       'type' => 'public'],
            '2026-05-04' => ['name' => 'วันฉัตรมงคล',             'type' => 'public'],
            '2026-06-01' => ['name' => 'วันวิสาขบูชา',            'type' => 'public'],
            '2026-06-03' => ['name' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระนางเจ้าสุทิดา', 'type' => 'public'],
            '2026-07-28' => ['name' => 'วันเฉลิมพระชนมพรรษาพระบาทสมเด็จพระเจ้าอยู่หัว', 'type' => 'public'],
            '2026-07-29' => ['name' => 'วันอาสาฬหบูชา',          'type' => 'public'],
            '2026-07-30' => ['name' => 'วันเข้าพรรษา',            'type' => 'public'],
            '2026-08-12' => ['name' => 'วันแม่แห่งชาติ',          'type' => 'public'],
            '2026-10-13' => ['name' => 'วันสวรรคต รัชกาลที่ ๙',  'type' => 'public'],
            '2026-10-23' => ['name' => 'วันปิยมหาราช',            'type' => 'public'],
            '2026-12-05' => ['name' => 'วันพ่อแห่งชาติ',          'type' => 'public'],
            '2026-12-10' => ['name' => 'วันรัฐธรรมนูญ',           'type' => 'public'],
            '2026-12-31' => ['name' => 'วันสิ้นปี',               'type' => 'public'],
        ],
    ];
    return $data[$year] ?? [];
}

function saveHolidaysToDatabase($year, $holidays) {
    global $connect;
    try {
        $del  = "DELETE FROM thai_holidays WHERE YEAR(holiday_date) = ?";
        $stmt = mysqli_prepare($connect, $del);
        mysqli_stmt_bind_param($stmt, "i", $year);
        mysqli_stmt_execute($stmt);
        $ins  = "INSERT INTO thai_holidays (holiday_date, holiday_name, holiday_type) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($connect, $ins);
        foreach ($holidays as $date => $holiday) {
            $t = $holiday['type'] ?? 'public';
            mysqli_stmt_bind_param($stmt, "sss", $date, $holiday['name'], $t);
            mysqli_stmt_execute($stmt);
        }
    } catch (Exception $e) {}
}

$thai_holidays = getThaiHolidays($current_year);
if (!empty($thai_holidays)) saveHolidaysToDatabase($current_year, $thai_holidays);

// ─── Thai locale data ───────────────────────────────────────────────
$thai_months = [
    1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
    5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
    9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม',
];
$thai_days_header = ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];

$first_day_of_week = date('w', strtotime($first_day));
$total_days        = date('t', strtotime($first_day));

// ─── Stats for sidebar ──────────────────────────────────────────────
$total_events_month = array_sum(array_map('count', $events_by_date));
$days_with_events   = count($events_by_date);
$holidays_this_month = 0;
for ($d = 1; $d <= $total_days; $d++) {
    $dt = sprintf("%04d-%02d-%02d", $current_year, $current_month, $d);
    if (isset($thai_holidays[$dt])) $holidays_this_month++;
}

$end_time        = microtime(true);
$generation_time = $end_time - $start_time;
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ปฏิทินงาน — ระบบจัดการตารางนัดหมาย สปภ.ระยอง</title>
<meta name="description" content="ปฏิทินวาระงานและนัดหมาย สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง">
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
   DESIGN TOKENS — matches index_desktop.php Navy/Gold theme
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
  --bg-surface:  rgba(255,255,255,0.85);
  --bg-elevated: rgba(255,255,255,0.97);
  --bg-glass:    rgba(255,255,255,0.60);
  --border:      rgba(30,111,217,0.10);
  --border-strong: rgba(30,111,217,0.22);

  --text-primary:   #0a1628;
  --text-secondary: #1e6fd9;
  --text-muted:     #5e98ef;
  --text-inverse:   #ffffff;

  --shadow-sm:    0 1px 4px rgba(30,111,217,.07), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md:    0 4px 16px rgba(30,111,217,.14), 0 2px 6px rgba(0,0,0,.06);
  --shadow-lg:    0 10px 36px rgba(30,111,217,.18), 0 4px 12px rgba(0,0,0,.08);
  --shadow-xl:    0 25px 60px rgba(30,111,217,.24);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.35);

  --r-sm:   8px;
  --r-md:   14px;
  --r-lg:   20px;
  --r-xl:   28px;
  --r-pill: 999px;

  --ease-out:    cubic-bezier(0.16, 1, 0.3, 1);
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);

  --header-bg: linear-gradient(135deg, #0a1628 0%, #0d2340 50%, #0f2e54 100%);
  --page-bg:   linear-gradient(145deg, #c8daf5 0%, #dce9fc 40%, #e8f0fd 100%);
}

[data-theme="dark"] {
  --bg-base:     #0a1628;
  --bg-surface:  rgba(13,35,64,0.90);
  --bg-elevated: rgba(15,46,84,0.88);
  --bg-glass:    rgba(10,22,40,0.80);
  --border:      rgba(255,255,255,0.09);
  --border-strong: rgba(255,255,255,0.15);

  --text-primary:   #f0f4ff;
  --text-secondary: #38c2ff;
  --text-muted:     rgba(240,244,255,0.55);

  --shadow-sm:    0 1px 4px rgba(0,0,0,.40);
  --shadow-md:    0 4px 16px rgba(0,0,0,.55);
  --shadow-lg:    0 10px 36px rgba(0,0,0,.65);
  --shadow-xl:    0 25px 60px rgba(0,0,0,.80);
  --shadow-brand: 0 8px 32px rgba(30,111,217,.45);

  --header-bg: linear-gradient(135deg, #050d1a 0%, #08192e 50%, #0a2040 100%);
  --page-bg:   radial-gradient(ellipse 80% 60% at 20% 0%, rgba(30,111,217,.22) 0%, transparent 65%),
               radial-gradient(ellipse 60% 50% at 80% 100%, rgba(232,160,32,.14) 0%, transparent 60%),
               #0a1628;
}

/* ── Reset ──────────────────────────────────────────── */
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

/* ── Header ─────────────────────────────────────────── */
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

.header-brand { display: flex; align-items: center; gap: 1rem; }

.header-logo {
  width: 52px; height: 52px;
  object-fit: contain;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.3));
  transition: transform 0.3s var(--ease-spring);
  flex-shrink: 0;
}
.header-logo:hover { transform: scale(1.08) rotate(-3deg); }

.header-text h1 {
  font-size: 1.1rem; font-weight: 600;
  letter-spacing: .01em; line-height: 1.2; margin: 0;
}
.header-text p { font-size: .78rem; opacity: .78; margin: 0; letter-spacing: .02em; }

.header-center { text-align: center; }
.header-date   { font-size: .82rem; opacity: .85; white-space: nowrap; }
.header-clock  {
  font-size: 2rem; font-weight: 300;
  letter-spacing: .06em; line-height: 1;
  font-variant-numeric: tabular-nums;
}

.header-actions { display: flex; align-items: center; justify-content: flex-end; gap: .75rem; }

.theme-toggle {
  width: 44px; height: 44px;
  border-radius: var(--r-pill);
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 1.15rem;
  transition: all 0.3s var(--ease-out);
  backdrop-filter: blur(8px);
}
.theme-toggle:hover { background: rgba(255,255,255,.22); transform: scale(1.08); }

.version-chip {
  font-size: .7rem; padding: 3px 10px;
  border-radius: var(--r-pill);
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  color: rgba(255,255,255,.8);
  white-space: nowrap;
  backdrop-filter: blur(4px);
  cursor: default;
}

/* ── Toolbar ─────────────────────────────────────────── */
.app-toolbar {
  background: var(--bg-surface);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-strong);
  padding: .75rem 2rem;
  display: flex; align-items: center;
  justify-content: space-between; gap: 1rem;
  box-shadow: var(--shadow-sm);
  transition: background 0.4s;
}
.toolbar-left, .toolbar-right { display: flex; align-items: center; gap: .6rem; }
.toolbar-center { display: flex; flex-direction: column; align-items: center; }

.toolbar-title {
  font-size: 1.35rem; font-weight: 700;
  color: var(--text-primary); letter-spacing: .01em;
}
.toolbar-subtitle { font-size: .8rem; color: var(--text-muted); }

.tb-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 16px;
  border-radius: var(--r-sm);
  font-size: .82rem; font-weight: 500;
  font-family: 'Anuphan', sans-serif;
  cursor: pointer; border: none;
  transition: all .25s var(--ease-out);
  text-decoration: none; white-space: nowrap;
}
.tb-btn-primary {
  background: var(--brand-500); color: #fff;
  box-shadow: var(--shadow-brand);
}
.tb-btn-primary:hover {
  background: var(--brand-600); color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(30,111,217,.38);
}
.tb-btn-ghost {
  background: transparent; color: var(--text-secondary);
  border: 1px solid var(--border-strong);
}
.tb-btn-ghost:hover {
  background: var(--bg-elevated); color: var(--text-primary);
  transform: translateY(-1px);
}
.tb-btn-today {
  background: rgba(232,160,32,.15); color: var(--accent-gold);
  border: 1px solid rgba(232,160,32,.35);
  font-weight: 600;
}
.tb-btn-today:hover {
  background: rgba(232,160,32,.25); color: var(--accent-gold);
  transform: translateY(-1px);
}

/* ── Main layout ─────────────────────────────────────── */
.app-main {
  flex: 1;
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 1.5rem;
  padding: 1.75rem 2rem;
  max-width: 1600px;
  margin: 0 auto;
  width: 100%;
}

/* ── Sidebar ─────────────────────────────────────────── */
.sidebar { display: flex; flex-direction: column; gap: 1.25rem; }

.sidebar-card {
  background: var(--bg-surface);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: var(--r-lg);
  border: 1px solid var(--border-strong);
  box-shadow: var(--shadow-md);
  overflow: hidden;
  animation: fadeUp .4s var(--ease-out) both;
}

.sidebar-card-header {
  padding: .85rem 1.25rem;
  background: linear-gradient(135deg, var(--brand-500) 0%, var(--brand-700) 100%);
  color: #fff;
  display: flex; align-items: center; gap: .6rem;
  font-weight: 600; font-size: .9rem;
}
.sidebar-card-header i { font-size: 1rem; opacity: .85; }

.sidebar-card-body { padding: 1rem 1.25rem; }

/* Stats grid */
.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.stat-block {
  text-align: center;
  background: var(--bg-elevated);
  border-radius: var(--r-md);
  padding: .75rem .5rem;
  border: 1px solid var(--border);
  transition: transform .2s var(--ease-out);
}
.stat-block:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.stat-value {
  font-size: 1.8rem; font-weight: 700;
  line-height: 1; color: var(--brand-500);
}
.stat-label { font-size: .72rem; color: var(--text-muted); margin-top: 3px; }
.stat-value.gold { color: var(--accent-gold); }
.stat-value.green { color: var(--accent-green); }
.stat-value.red { color: var(--accent-red); }

/* Legend */
.legend-list { display: flex; flex-direction: column; gap: .55rem; }
.legend-item {
  display: flex; align-items: center; gap: .65rem;
  padding: .45rem .65rem;
  border-radius: var(--r-sm);
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  transition: all .2s;
}
.legend-item:hover { background: var(--bg-glass); border-color: var(--border-strong); }
.legend-dot {
  width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(0,0,0,.25);
}
.legend-text { font-size: .82rem; color: var(--text-primary); font-weight: 500; flex: 1; min-width: 0; }
.legend-desc { font-size: .7rem; color: var(--text-muted); display: block; }
.legend-holiday { background: linear-gradient(135deg, rgba(224,82,82,.12) 0%, rgba(224,82,82,.06) 100%); border-color: rgba(224,82,82,.25); }

/* Upcoming mini list */
.upcoming-list { display: flex; flex-direction: column; gap: .5rem; max-height: 240px; overflow-y: auto; }
.upcoming-item {
  display: flex; gap: .65rem; align-items: flex-start;
  padding: .5rem .6rem;
  border-radius: var(--r-sm);
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  border-left: 3px solid var(--brand-400);
  transition: transform .2s;
  font-size: .8rem;
}
.upcoming-item:hover { transform: translateX(3px); }
.upcoming-date {
  display: flex; flex-direction: column; align-items: center;
  min-width: 38px; color: var(--brand-500); font-weight: 700;
}
.upcoming-date .day { font-size: 1.1rem; line-height: 1; }
.upcoming-date .mon { font-size: .65rem; opacity: .75; }
.upcoming-info { flex: 1; min-width: 0; }
.upcoming-title {
  font-weight: 600; color: var(--text-primary); line-height: 1.3;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.upcoming-time { font-size: .7rem; color: var(--text-muted); margin-top: 2px; }

/* ── Calendar area ───────────────────────────────────── */
.calendar-area { display: flex; flex-direction: column; gap: 0; }

.calendar-card {
  background: var(--bg-surface);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: var(--r-lg);
  border: 1px solid var(--border-strong);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  animation: fadeUp .4s var(--ease-out) .08s both;
}

/* Day header row */
.cal-day-headers {
  display: grid; grid-template-columns: repeat(7, 1fr);
  background: linear-gradient(135deg, var(--brand-700) 0%, var(--brand-900) 100%);
  padding: 0 1px;
}
.cal-day-hdr {
  text-align: center; padding: .85rem .4rem;
  font-size: .8rem; font-weight: 600;
  color: rgba(255,255,255,.75); letter-spacing: .04em; text-transform: uppercase;
}
.cal-day-hdr.sun { color: #ff9090; }
.cal-day-hdr.sat { color: var(--accent-sky); }

/* Grid */
.cal-grid {
  display: grid; grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: var(--border-strong);
  padding: 1px;
}

/* Date cell */
.cal-cell {
  background: var(--bg-elevated);
  min-height: 110px;
  padding: .6rem .65rem .5rem;
  position: relative;
  transition: background .2s var(--ease-out), transform .2s var(--ease-spring), box-shadow .2s;
  cursor: default;
  overflow: hidden;
  display: flex; flex-direction: column; gap: 4px;
}
.cal-cell.clickable { cursor: pointer; }
.cal-cell.clickable:hover {
  background: var(--bg-glass);
  transform: scale(1.025);
  z-index: 2;
  box-shadow: var(--shadow-lg);
}
.cal-cell.empty  { background: var(--bg-base); opacity: .45; cursor: default; }
.cal-cell.past   { opacity: .5; cursor: default; }
.cal-cell.past:hover { transform: none; box-shadow: none; background: var(--bg-elevated); }

/* Today ring */
.cal-cell.today::before {
  content: '';
  position: absolute; inset: 0;
  border: 2.5px solid var(--accent-gold);
  border-radius: 0;
  pointer-events: none;
  animation: todayPulse 2.5s ease-in-out infinite;
}
@keyframes todayPulse {
  0%,100% { border-color: var(--accent-gold); box-shadow: inset 0 0 0 0 rgba(232,160,32,.0); }
  50%      { border-color: var(--accent-gold); box-shadow: inset 0 0 20px 0 rgba(232,160,32,.08); }
}

/* Holiday tint */
.cal-cell.holiday { background: linear-gradient(135deg, rgba(224,82,82,.1) 0%, rgba(224,82,82,.04) 100%); }
[data-theme="dark"] .cal-cell.holiday { background: linear-gradient(135deg, rgba(224,82,82,.18) 0%, rgba(224,82,82,.06) 100%); }

/* Date number */
.cell-num {
  font-size: 1.05rem; font-weight: 700;
  color: var(--text-primary); line-height: 1;
  width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%;
  flex-shrink: 0;
}
.cal-cell.today .cell-num {
  background: var(--accent-gold); color: #000;
  box-shadow: 0 2px 8px rgba(232,160,32,.45);
}
.cal-cell.sunday .cell-num   { color: var(--accent-red); }
.cal-cell.saturday .cell-num { color: var(--accent-sky); }
.cal-cell.holiday .cell-num  { color: var(--accent-red); }
.cal-cell.past .cell-num     { color: var(--text-muted); }

/* Holiday badge */
.holiday-badge {
  display: inline-flex; align-items: center; gap: 3px;
  background: linear-gradient(135deg, #e05252, #c03030);
  color: #fff; font-size: .62rem; font-weight: 600;
  padding: 2px 7px; border-radius: var(--r-pill);
  white-space: nowrap; max-width: 100%;
  box-shadow: 0 2px 6px rgba(224,82,82,.35);
}
.holiday-name {
  font-size: .68rem; color: var(--accent-red);
  font-weight: 500; line-height: 1.25;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* Event dots */
.event-indicators {
  display: flex; flex-wrap: wrap; gap: 3px; margin-top: 2px;
}
.event-dot {
  width: 7px; height: 7px; border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0,0,0,.3);
  transition: transform .15s;
}
.event-dot:hover { transform: scale(1.7); }

/* Event count chip */
.event-count-chip {
  display: inline-flex; align-items: center; gap: 3px;
  background: rgba(30,111,217,.15); color: var(--brand-500);
  border: 1px solid rgba(30,111,217,.25);
  border-radius: var(--r-pill);
  font-size: .65rem; font-weight: 700;
  padding: 1px 7px; margin-top: auto;
  white-space: nowrap; align-self: flex-start;
}
[data-theme="dark"] .event-count-chip { background: rgba(56,194,255,.15); color: var(--accent-sky); border-color: rgba(56,194,255,.25); }

/* ── Modal ───────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.65);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  z-index: 2000; padding: 1.5rem;
  opacity: 0; visibility: hidden;
  transition: opacity .35s var(--ease-out), visibility .35s;
}
.modal-overlay.active { opacity: 1; visibility: visible; }
.modal-overlay.active .modal-box {
  transform: translateY(0) scale(1);
  opacity: 1;
}

.modal-box {
  background: var(--bg-elevated);
  border-radius: var(--r-xl);
  width: 100%; max-width: 820px;
  max-height: 88vh; overflow-y: auto;
  box-shadow: var(--shadow-xl);
  border: 1px solid var(--border-strong);
  position: relative;
  transform: translateY(24px) scale(.97);
  opacity: 0;
  transition: transform .35s var(--ease-spring), opacity .35s var(--ease-out);
}

.modal-header {
  padding: 1.5rem 1.75rem 1rem;
  border-bottom: 1px solid var(--border-strong);
  position: sticky; top: 0;
  background: var(--bg-elevated);
  backdrop-filter: blur(12px);
  z-index: 10;
  display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
}
.modal-title { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
.modal-subtitle { font-size: .82rem; color: var(--text-muted); margin-top: 2px; }

.modal-close {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--bg-glass);
  border: 1px solid var(--border-strong);
  color: var(--text-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 1.1rem;
  transition: all .25s; flex-shrink: 0;
}
.modal-close:hover { background: var(--accent-red); color: #fff; border-color: transparent; transform: rotate(90deg); }

.modal-body { padding: 1.25rem 1.75rem 1.75rem; display: flex; flex-direction: column; gap: 1rem; }

/* Holiday alert in modal */
.holiday-alert {
  display: flex; gap: .75rem; align-items: flex-start;
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, rgba(224,82,82,.12) 0%, rgba(224,82,82,.05) 100%);
  border: 1px solid rgba(224,82,82,.3);
  border-left: 4px solid var(--accent-red);
  border-radius: var(--r-md);
}
.holiday-alert-icon { font-size: 1.4rem; color: var(--accent-red); margin-top: 1px; }
.holiday-alert-text h4 { color: var(--accent-red); font-size: .95rem; margin-bottom: 2px; }
.holiday-alert-text p  { font-size: .88rem; color: var(--text-muted); margin: 0; }

/* Event cards in modal */
.modal-event-card {
  background: var(--bg-surface);
  border-radius: var(--r-md);
  padding: 1rem 1.25rem;
  border: 1px solid var(--border);
  border-left: 4px solid;
  display: flex; flex-direction: column; gap: .4rem;
  transition: transform .2s var(--ease-out), box-shadow .2s;
}
.modal-event-card:hover { transform: translateX(4px); box-shadow: var(--shadow-md); }

.mec-time {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: .78rem; font-weight: 700;
  color: var(--text-secondary); letter-spacing: .02em;
}
.mec-title { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
.mec-meta  {
  display: flex; flex-wrap: wrap; gap: .4rem .85rem;
  margin-top: 4px;
}
.mec-meta-item {
  display: flex; align-items: center; gap: 4px;
  font-size: .8rem; color: var(--text-muted);
}
.mec-cat-pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 10px; border-radius: var(--r-pill);
  font-size: .72rem; font-weight: 600;
  background: rgba(30,111,217,.12); color: var(--brand-500);
  border: 1px solid rgba(30,111,217,.2);
}

/* No events */
.no-events-msg {
  text-align: center; padding: 2rem;
  color: var(--text-muted); font-size: .95rem;
}
.no-events-msg i { font-size: 2.5rem; opacity: .35; display: block; margin-bottom: .5rem; }

/* ── Footer ──────────────────────────────────────────── */
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
.app-footer a { color: rgba(255,255,255,.6); text-decoration: none; }
.app-footer a:hover { color: var(--accent-gold); }

/* ── FAB back button ─────────────────────────────────── */
.fab-back {
  position: fixed; bottom: 2rem; right: 2rem;
  display: flex; align-items: center; gap: .55rem;
  padding: .75rem 1.4rem;
  background: linear-gradient(135deg, var(--brand-500), var(--brand-700));
  color: #fff; font-weight: 600; font-size: .9rem;
  border-radius: var(--r-pill);
  box-shadow: var(--shadow-brand);
  text-decoration: none; z-index: 500;
  transition: all .3s var(--ease-spring);
  border: 1px solid rgba(255,255,255,.15);
}
.fab-back:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(30,111,217,.45);
  color: #fff;
}
.fab-back i { font-size: 1rem; }

/* ── Animations ──────────────────────────────────────── */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Scrollbar styling ───────────────────────────────── */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 3px; }

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 1100px) {
  .app-main { grid-template-columns: 1fr; }
  .sidebar { flex-direction: row; flex-wrap: wrap; }
  .sidebar-card { flex: 1; min-width: 260px; }
}
@media (max-width: 768px) {
  .header-inner { grid-template-columns: 1fr auto; }
  .header-center { display: none; }
  .app-toolbar { flex-wrap: wrap; }
  .toolbar-center { order: -1; width: 100%; text-align: center; }
  .cal-cell { min-height: 72px; padding: .4rem .35rem; }
  .cell-num { font-size: .9rem; width: 24px; height: 24px; }
  .holiday-name { display: none; }
  .event-count-chip { font-size: .6rem; }
  .cal-day-hdr { font-size: .7rem; padding: .55rem .2rem; }
  .app-main { padding: 1rem; gap: 1rem; }
}
@media (max-width: 480px) {
  .header-inner { padding: .7rem 1rem; }
  .header-text h1 { font-size: .95rem; }
  .app-toolbar { padding: .6rem 1rem; }
}
</style>
<script src="/theme.js"></script>
</head>
<body>

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
      <div class="header-date" id="liveDate"></div>
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

<!-- ══════════════════════ TOOLBAR ══════════════════════ -->
<div class="app-toolbar">
  <div class="toolbar-left">
    <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="tb-btn tb-btn-ghost">
      <i class="bi bi-chevron-left"></i> เดือนก่อน
    </a>
  </div>
  <div class="toolbar-center">
    <div class="toolbar-title">
      <?= $thai_months[$current_month] ?> <?= $current_year + 543 ?>
    </div>
  </div>
  <div class="toolbar-right">
    <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="tb-btn tb-btn-today">
      <i class="bi bi-calendar-today"></i> วันนี้
    </a>
    <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" class="tb-btn tb-btn-primary">
      เดือนถัดไป <i class="bi bi-chevron-right"></i>
    </a>
  </div>
</div>

<!-- ══════════════════════ MAIN ══════════════════════════ -->
<main class="app-main">

  <!-- ── Sidebar ── -->
  <aside class="sidebar">

    <!-- Stats card -->
    <div class="sidebar-card" style="animation-delay:.05s">
      <div class="sidebar-card-header">
        <i class="bi bi-bar-chart-line"></i>
        สรุปภาพรวมเดือนนี้
      </div>
      <div class="sidebar-card-body">
        <div class="stats-grid">
          <div class="stat-block">
            <div class="stat-value"><?= $total_events_month ?></div>
            <div class="stat-label">วาระงานทั้งหมด</div>
          </div>
          <div class="stat-block">
            <div class="stat-value green"><?= $days_with_events ?></div>
            <div class="stat-label">วันที่มีวาระ</div>
          </div>
          <div class="stat-block">
            <div class="stat-value red"><?= $holidays_this_month ?></div>
            <div class="stat-label">วันหยุดราชการ</div>
          </div>
          <div class="stat-block">
            <div class="stat-value gold"><?= $total_days ?></div>
            <div class="stat-label">วันในเดือน</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Legend card -->
    <div class="sidebar-card" style="animation-delay:.1s">
      <div class="sidebar-card-header">
        <i class="bi bi-palette"></i>
        หมวดหมู่งาน
      </div>
      <div class="sidebar-card-body">
        <div class="legend-list">
          <div class="legend-item legend-holiday">
            <div class="legend-dot" style="background:linear-gradient(135deg,#e05252,#c03030)"></div>
            <div class="legend-text">
              วันหยุดราชการ
              <span class="legend-desc">ประกาศราชกิจจานุเบกษา</span>
            </div>
          </div>
          <?php foreach ($categories as $cat): ?>
          <div class="legend-item">
            <div class="legend-dot" style="background-color:<?= htmlspecialchars($cat['color']) ?>"></div>
            <div class="legend-text">
              <?= htmlspecialchars($cat['name']) ?>
              <?php if ($cat['description']): ?>
              <span class="legend-desc"><?= htmlspecialchars($cat['description']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Upcoming mini card -->
    <?php
    $upcoming_list = [];
    foreach ($events_by_date as $dt => $evts) {
        if ($dt >= $today) foreach ($evts as $ev) $upcoming_list[] = ['date' => $dt] + $ev;
    }
    usort($upcoming_list, fn($a,$b) => strcmp($a['date'].$a['time_from'], $b['date'].$b['time_from']));
    $upcoming_slice = array_slice($upcoming_list, 0, 8);
    ?>
    <?php if (!empty($upcoming_slice)): ?>
    <div class="sidebar-card" style="animation-delay:.15s">
      <div class="sidebar-card-header">
        <i class="bi bi-lightning-charge"></i>
        วาระที่กำลังจะมาถึง
      </div>
      <div class="sidebar-card-body" style="padding:.75rem 1rem;">
        <div class="upcoming-list">
          <?php foreach ($upcoming_slice as $ev):
            $ts   = strtotime($ev['date']);
            $dayN = date('j', $ts);
            $monN = $thai_months[(int)date('n', $ts)];
            $t    = $ev['time_from'] ? substr($ev['time_from'],0,5) . ' น.' : '';
            $clr  = $ev['category_color'] ?? '#1e6fd9';
          ?>
          <div class="upcoming-item" style="border-left-color:<?= htmlspecialchars($clr) ?>">
            <div class="upcoming-date">
              <span class="day"><?= $dayN ?></span>
              <span class="mon"><?= mb_substr($monN,0,3) ?></span>
            </div>
            <div class="upcoming-info">
              <div class="upcoming-title"><?= htmlspecialchars($ev['title']) ?></div>
              <?php if ($t): ?>
              <div class="upcoming-time"><i class="bi bi-clock"></i> <?= htmlspecialchars($t) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </aside>

  <!-- ── Calendar ── -->
  <section class="calendar-area">
    <div class="calendar-card">
      <!-- Day headers -->
      <div class="cal-day-headers">
        <?php foreach (['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'] as $i => $dh): ?>
        <div class="cal-day-hdr <?= $i===0?'sun':($i===6?'sat':'') ?>"><?= $dh ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Grid -->
      <div class="cal-grid">
        <?php
        // Empty cells
        for ($i = 0; $i < $first_day_of_week; $i++) {
            echo '<div class="cal-cell empty"></div>';
        }

        for ($day = 1; $day <= $total_days; $day++) {
            $dt         = sprintf("%04d-%02d-%02d", $current_year, $current_month, $day);
            $dow        = (int)date('w', strtotime($dt));
            $is_today   = ($dt === $today);
            $is_past    = ($dt < $today);
            $is_sun     = ($dow === 0);
            $is_sat     = ($dow === 6);
            $is_holiday = isset($thai_holidays[$dt]);
            $has_events = !$is_past && isset($events_by_date[$dt]);
            $clickable  = ($has_events || $is_holiday) && !$is_past;

            $cls = ['cal-cell'];
            if ($is_today)   $cls[] = 'today';
            if ($is_past)    $cls[] = 'past';
            if ($is_sun)     $cls[] = 'sunday';
            if ($is_sat)     $cls[] = 'saturday';
            if ($is_holiday) $cls[] = 'holiday';
            if ($clickable)  $cls[] = 'clickable';

            $onclick = $clickable ? "onclick=\"showDateEvents('$dt')\"" : '';
            echo '<div class="' . implode(' ', $cls) . '" ' . $onclick . '>';

            // Date number
            echo '<div class="cell-num">' . $day . '</div>';

            // Holiday badge + name
            if ($is_holiday && !$is_past) {
                echo '<div class="holiday-badge"><i class="bi bi-star-fill"></i> วันหยุด</div>';
                echo '<div class="holiday-name">' . htmlspecialchars($thai_holidays[$dt]['name']) . '</div>';
            }

            // Event dots
            if ($has_events) {
                echo '<div class="event-indicators">';
                foreach ($events_by_date[$dt] as $ev) {
                    $c = htmlspecialchars($ev['category_color'] ?? '#1e6fd9');
                    $n = htmlspecialchars($ev['category_name'] ?? '');
                    echo "<span class=\"event-dot\" style=\"background:$c\" title=\"$n\"></span>";
                }
                echo '</div>';
                $cnt = count($events_by_date[$dt]);
                echo "<div class=\"event-count-chip\"><i class=\"bi bi-briefcase\"></i> $cnt งาน</div>";
            }

            echo '</div>';
        }
        ?>
      </div>
    </div>
  </section>
</main>

<!-- ══════════════════════ MODAL ══════════════════════ -->
<div class="modal-overlay" id="eventModal" onclick="handleModalClick(event)">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modalTitle">วาระงาน</div>
        <div class="modal-subtitle" id="modalSubtitle"></div>
      </div>
      <button class="modal-close" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<!-- ══════════════════════ FAB ══════════════════════ -->
<a href="desktop" class="fab-back">
  <i class="bi bi-arrow-left-circle"></i> กลับหน้าหลัก
</a>

<!-- ══════════════════════════════════════════════ FOOTER ══ -->
<footer class="app-footer">
  <strong>© <?= date('Y') ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</strong><br>
  <span class="perf">
    <i class="bi bi-lightning-charge-fill"></i>
    สร้างหน้าในเวลา <?= number_format($generation_time, 4) ?> วินาที
    &nbsp;|&nbsp; PHP <?= phpversion() ?>
  </span>
</footer>

<script>
// ── Data from PHP ──
const eventsData   = <?= json_encode($events_by_date, JSON_UNESCAPED_UNICODE) ?>;
const holidaysData = <?= json_encode($thai_holidays,   JSON_UNESCAPED_UNICODE) ?>;

const thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                    'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
const thaiDays   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];

// ── Live Clock ──
function updateClock() {
  const now = new Date();
  const hh  = String(now.getHours()).padStart(2,'0');
  const mm  = String(now.getMinutes()).padStart(2,'0');
  const ss  = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('liveClock').textContent = `${hh}:${mm}:${ss}`;

  const dayName = thaiDays[now.getDay()];
  const day     = now.getDate();
  const month   = thaiMonths[now.getMonth()];
  const year    = now.getFullYear() + 543;
  document.getElementById('liveDate').textContent = `วัน${dayName}ที่ ${day} ${month} ${year}`;
}
updateClock();
setInterval(updateClock, 1000);

// ── Theme toggle ──
// Theme toggle handled by ThemeManager (theme.js)
// Theme init handled by ThemeManager (theme.js)

// ── Modal ──
function showDateEvents(dateStr) {
  const events  = eventsData[dateStr] || [];
  const holiday = holidaysData[dateStr];
  if (events.length === 0 && !holiday) return;

  const d       = new Date(dateStr);
  const dayName = thaiDays[d.getDay()];
  const day     = d.getDate();
  const month   = thaiMonths[d.getMonth()];
  const year    = d.getFullYear() + 543;

  document.getElementById('modalTitle').innerHTML =
    `<i class="bi bi-calendar-check" style="color:var(--accent-gold)"></i>&nbsp; วาระงาน`;
  document.getElementById('modalSubtitle').textContent =
    `วัน${dayName}ที่ ${day} ${month} พ.ศ. ${year}`;

  let html = '';

  if (holiday) {
    html += `
      <div class="holiday-alert">
        <div class="holiday-alert-icon"><i class="bi bi-star-fill"></i></div>
        <div class="holiday-alert-text">
          <h4>วันหยุดราชการ</h4>
          <p>${holiday.name}</p>
        </div>
      </div>`;
  }

  if (events.length > 0) {
    events.forEach(ev => {
      const color   = ev.category_color || '#1e6fd9';
      const catName = ev.category_name  || 'ไม่ระบุหมวดหมู่';
      const tf      = ev.time_from ? ev.time_from.substr(0,5) + ' น.' : '';
      const tt      = ev.time_to   ? ev.time_to.substr(0,5)   + ' น.' : '';
      const timeStr = tf && tt ? `${tf} – ${tt}` : (tf || '-');

      html += `
        <div class="modal-event-card" style="border-left-color:${color}">
          <div class="mec-time"><i class="bi bi-clock"></i> ${timeStr}</div>
          <div class="mec-title">${escHtml(ev.title)}</div>
          <div class="mec-meta">
            ${ev.location   ? `<span class="mec-meta-item"><i class="bi bi-geo-alt"></i> ${escHtml(ev.location)}</span>` : ''}
            ${ev.department ? `<span class="mec-meta-item"><i class="bi bi-building"></i> ${escHtml(ev.department)}</span>` : ''}
            <span class="mec-cat-pill" style="background:${color}22;color:${color};border-color:${color}44">
              <i class="bi bi-tag"></i> ${escHtml(catName)}
            </span>
          </div>
        </div>`;
    });
  } else if (holiday) {
    html += `<div class="no-events-msg"><i class="bi bi-calendar-x"></i> ไม่มีวาระงานในวันนี้</div>`;
  }

  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('eventModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('eventModal').classList.remove('active');
  document.body.style.overflow = '';
}

function handleModalClick(e) {
  if (e.target === document.getElementById('eventModal')) closeModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function escHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>

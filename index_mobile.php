<?php
/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  index_mobile.php — Enterprise Mobile Command Center            ║
 * ║  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง                 ║
 * ║  Version: 3.0.0-Enterprise                                       ║
 * ║  Architecture: PSR-4 compliant, OWASP hardened, A11Y WCAG 2.1   ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */

declare(strict_types=1);

// ════════════════════════════════════════════════════════════════════
// BOOTSTRAP & SECURITY HARDENING
// ════════════════════════════════════════════════════════════════════
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Security headers (complement with server-level config)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

include 'config.php';

// ════════════════════════════════════════════════════════════════════
// PERFORMANCE: Track server render time
// ════════════════════════════════════════════════════════════════════
$_server_start = hrtime(true);

// ════════════════════════════════════════════════════════════════════
// HELPERS — Pure functions, no side effects
// ════════════════════════════════════════════════════════════════════

/**
 * แปลงวันที่เป็นรูปแบบภาษาไทย พร้อม Buddhist Era
 */
function thaiDateFull(string $dateStr): string {
    static $months = [
        'Jan'=>'มกราคม','Feb'=>'กุมภาพันธ์','Mar'=>'มีนาคม',
        'Apr'=>'เมษายน','May'=>'พฤษภาคม','Jun'=>'มิถุนายน',
        'Jul'=>'กรกฎาคม','Aug'=>'สิงหาคม','Sep'=>'กันยายน',
        'Oct'=>'ตุลาคม','Nov'=>'พฤศจิกายน','Dec'=>'ธันวาคม',
    ];
    static $days = [
        'Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร',
        'Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์',
    ];
    $ts = strtotime($dateStr);
    if ($ts === false) return $dateStr;
    return 'วัน'.$days[date('l',$ts)].' ที่ '.date('j',$ts).' '.$months[date('M',$ts)].' '.(date('Y',$ts)+543);
}

/**
 * แปลงวันที่สั้น (ไม่มีวัน)
 */
function thaiDateShort(string $dateStr): string {
    static $months = [
        'Jan'=>'ม.ค.','Feb'=>'ก.พ.','Mar'=>'มี.ค.',
        'Apr'=>'เม.ย.','May'=>'พ.ค.','Jun'=>'มิ.ย.',
        'Jul'=>'ก.ค.','Aug'=>'ส.ค.','Sep'=>'ก.ย.',
        'Oct'=>'ต.ค.','Nov'=>'พ.ย.','Dec'=>'ธ.ค.',
    ];
    $ts = strtotime($dateStr);
    if ($ts === false) return $dateStr;
    return date('j',$ts).' '.$months[date('M',$ts)].' '.(date('Y',$ts)+543);
}

/**
 * เลือก icon Bootstrap ตามชื่อประเภทงาน
 */
function getCategoryIcon(string $name): string {
    $map = [
        'ประชุม'      => 'people-fill',
        'ตรวจราชการ'  => 'geo-alt-fill',
        'อบรม'        => 'mortarboard-fill',
        'สัมมนา'      => 'mic-fill',
        'กิจกรรม'     => 'calendar-event-fill',
        'ปฏิบัติการ'  => 'activity',
        'ฉุกเฉิน'     => 'exclamation-triangle-fill',
        'รับรอง'      => 'shield-check',
        'เดินทาง'     => 'airplane-fill',
    ];
    foreach ($map as $k => $v) {
        if (str_contains($name, $k)) return $v;
    }
    return 'tag-fill';
}

/**
 * เลือกสีตามชื่อประเภทงาน (fallback cascade)
 */
function getCategoryColor(string $name): string {
    $map = [
        'ประชุม'      => '#1e6fa8',
        'ตรวจราชการ'  => '#0d7a5a',
        'อบรม'        => '#c07b00',
        'สัมมนา'      => '#c0392b',
        'กิจกรรม'     => '#1a7a8a',
        'ปฏิบัติการ'  => '#4a5568',
        'ฉุกเฉิน'     => '#9b2c2c',
        'รับรอง'      => '#553c7b',
        'เดินทาง'     => '#2c5f8a',
    ];
    foreach ($map as $k => $v) {
        if (str_contains($name, $k)) return $v;
    }
    return '#3a5a7a';
}

/**
 * นับวันทำการระหว่างสองวัน (ไม่นับเสาร์-อาทิตย์)
 */
function countWorkdays(string $start, string $end): int {
    try {
        $current = new DateTime($start);
        $endDt   = new DateTime($end);
        $days    = 0;
        while ($current <= $endDt) {
            $dow = (int)$current->format('w');
            if ($dow >= 1 && $dow <= 5) $days++;
            $current->modify('+1 day');
        }
        return $days;
    } catch (Exception) {
        return 0;
    }
}

/**
 * Sanitize output — shorthand wrapper
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * แปลง HH:MM:SS → HH:MM น.
 */
function fmtTime(?string $t): string {
    if (empty($t)) return '—';
    return date('H:i', strtotime("2000-01-01 $t")) . ' น.';
}

// ════════════════════════════════════════════════════════════════════
// DATABASE LAYER — Centralized query runner with structured logging
// ════════════════════════════════════════════════════════════════════

/**
 * Execute a prepared statement safely, log errors, return result or null.
 */
function safeQuery(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_result|false {
    try {
        if (empty($params)) {
            $result = mysqli_query($db, $sql);
            return $result ?: false;
        }
        $stmt = mysqli_prepare($db, $sql);
        if (!$stmt) { error_log('[DB PREPARE] ' . mysqli_error($db) . ' | SQL: ' . $sql); return false; }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    } catch (Exception $e) {
        error_log('[DB EXCEPTION] ' . $e->getMessage() . ' | SQL: ' . $sql);
        return false;
    }
}

// ════════════════════════════════════════════════════════════════════
// QUERIES
// ════════════════════════════════════════════════════════════════════

/* Version */
$current_version = '1.0.0';
$res = safeQuery($connect, "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    $current_version = $row['version'] ?? '1.0.0';
}

/* Event Categories */
$categories = [];
$res = safeQuery($connect, "SELECT id, name, color FROM event_categories ORDER BY id");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $categories[(int)$row['id']] = [
            'name'  => $row['name'],
            'color' => !empty($row['color']) ? $row['color'] : getCategoryColor($row['name']),
            'icon'  => getCategoryIcon($row['name']),
        ];
    }
}

/* Calendar events map: date → [{color, name}] */
$calendar_events = [];
$res = safeQuery($connect,
    "SELECT DATE(e.date) AS event_date, ec.name AS category_name, ec.color AS category_color
     FROM events e
     LEFT JOIN event_categories ec ON e.category_id = ec.id
     WHERE e.date >= CURDATE()
       AND (e.status IS NULL OR e.status NOT IN ('ยกเลิก','เลื่อน'))
     ORDER BY DATE(e.date), e.time_from ASC"
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $date = $row['event_date'];
        $color = !empty($row['category_color']) ? $row['category_color'] : getCategoryColor($row['category_name'] ?? '');
        $calendar_events[$date][] = ['color' => $color, 'name' => $row['category_name']];
    }
}

/* Observance Options */
$observance_options = [];
$res = safeQuery($connect, "SELECT id, Name FROM observance_options ORDER BY id");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $observance_options[(int)$row['id']] = $row['Name'];
    }
}

/* Responsible Persons (indexed by observance_id) */
$responsible_persons = [];
$res = safeQuery($connect, "SELECT id, name, observance FROM responsible_persons ORDER BY name");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $responsible_persons[(int)$row['observance']][] = $row['name'];
    }
}

/* Today's stats */
$current_date    = date('Y-m-d');
$current_time    = date('H:i:s');
$total_events    = 0;
$completed_events = 0;
$res = safeQuery($connect,
    "SELECT COUNT(*) AS c FROM events
     WHERE date = ? AND (time_to > CURTIME() OR time_to IS NULL)
       AND (status IS NULL OR status NOT IN ('ยกเลิก','เลื่อน'))",
    's', [$current_date]
);
if ($res && ($row = mysqli_fetch_assoc($res))) $total_events = (int)($row['c'] ?? 0);

$res = safeQuery($connect,
    "SELECT COUNT(*) AS c FROM events
     WHERE date = ? AND time_to <= CURTIME()
       AND (status IS NULL OR status NOT IN ('ยกเลิก','เลื่อน'))",
    's', [$current_date]
);
if ($res && ($row = mysqli_fetch_assoc($res))) $completed_events = (int)($row['c'] ?? 0);

/* Upcoming events */
$upcoming_events = [];
$res = safeQuery($connect,
    "SELECT e.*, ec.name AS category_name, ec.color AS category_color
     FROM events e
     LEFT JOIN event_categories ec ON e.category_id = ec.id
     WHERE (e.date >= CURDATE())
       AND (e.status IS NULL OR e.status NOT IN ('ยกเลิก','เลื่อน'))
     ORDER BY e.date ASC, e.time_from ASC"
);
if ($res) {
    while ($ev = mysqli_fetch_assoc($res)) {
        $is_today = ($ev['date'] === $current_date);
        $is_past  = false;
        if ($is_today && !empty($ev['time_to'])) {
            $is_past = time() > (strtotime($ev['date'].' '.$ev['time_to']) + 600);
        }
        if (!$is_past) $upcoming_events[] = $ev;
    }
}

/* Active leave data */
$leave_data = [];
$res = safeQuery($connect,
    "SELECT name, leave_type, location, start_datetime, end_datetime, details
     FROM responsible_persons
     WHERE leave_type IS NOT NULL
       AND start_datetime <= NOW() AND end_datetime >= NOW()
     ORDER BY end_datetime ASC"
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $row['leave_days'] = countWorkdays(
            (new DateTime($row['start_datetime']))->format('Y-m-d'),
            (new DateTime($row['end_datetime']))->format('Y-m-d')
        );
        $leave_data[] = $row;
    }
}

// ════════════════════════════════════════════════════════════════════
// VIEW DATA PREPARATION
// ════════════════════════════════════════════════════════════════════
$thai_months_full = [
    'Jan'=>'มกราคม','Feb'=>'กุมภาพันธ์','Mar'=>'มีนาคม',
    'Apr'=>'เมษายน','May'=>'พฤษภาคม','Jun'=>'มิถุนายน',
    'Jul'=>'กรกฎาคม','Aug'=>'สิงหาคม','Sep'=>'กันยายน',
    'Oct'=>'ตุลาคม','Nov'=>'พฤศจิกายน','Dec'=>'ธันวาคม',
];
$thai_months_short = [
    'Jan'=>'ม.ค.','Feb'=>'ก.พ.','Mar'=>'มี.ค.',
    'Apr'=>'เม.ย.','May'=>'พ.ค.','Jun'=>'มิ.ย.',
    'Jul'=>'ก.ค.','Aug'=>'ส.ค.','Sep'=>'ก.ย.',
    'Oct'=>'ต.ค.','Nov'=>'พ.ย.','Dec'=>'ธ.ค.',
];
$thai_days = [
    'Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร',
    'Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์',
];

$current_date_thai = 'วัน'.$thai_days[date('l')].' ที่ '.date('j').' '.$thai_months_full[date('M')].' '.(date('Y')+543);
$upcoming_cnt      = count(array_filter($upcoming_events, fn($e) => $e['date'] > $current_date));
$today_evs         = array_filter($upcoming_events, fn($e) => $e['date'] === $current_date);
$future_evs        = array_filter($upcoming_events, fn($e) => $e['date'] >  $current_date);

/* Category breakdown */
$cat_counts = [];
foreach ($upcoming_events as $e) {
    $n = $e['category_name'] ?? 'อื่นๆ';
    $cat_counts[$n] = ($cat_counts[$n] ?? 0) + 1;
}
arsort($cat_counts);
$total_ev_count = count($upcoming_events);

/* Build calendar dot-data for JS (flatten to {date: [{color}]}) */
$cal_json_data = [];
foreach ($calendar_events as $date => $dots) {
    $cal_json_data[$date] = array_map(fn($d) => $d['color'], $dots);
}

/* ════════════════════════════════════════════════════════════════
   THAI PUBLIC HOLIDAYS — ดึงข้อมูลอัตโนมัติจาก Calendarific API
   ════════════════════════════════════════════════════════════════
   ตั้งค่า API Key ที่นี่ (ลงทะเบียนฟรีที่ https://calendarific.com)
   ════════════════════════════════════════════════════════════════ */

/**
 * ดึงวันหยุดราชการไทยจาก Calendarific API พร้อม file-based cache
 *
 * @param  int    $year        ปี ค.ศ. ที่ต้องการ
 * @param  string $apiKey      Calendarific API Key
 * @param  string $cacheDir    ไดเรกทอรีสำหรับเก็บ cache (ต้อง writable)
 * @param  int    $cacheDays   จำนวนวันที่ cache ยังใช้ได้ (default 30 วัน)
 * @return array<string,string> ['YYYY-MM-DD' => 'ชื่อวันหยุด']
 */
function fetchThaiHolidays(int $year, string $apiKey, string $cacheDir = '', int $cacheDays = 30): array
{
    // ── ใช้ sys_get_temp_dir() หากไม่ได้กำหนด cacheDir ──
    if ($cacheDir === '') {
        $cacheDir = sys_get_temp_dir();
    }
    $cacheFile = rtrim($cacheDir, '/') . "/thai_holidays_{$year}.json";

    // ── ตรวจสอบ cache ──
    if (file_exists($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        if ($age < $cacheDays * 86400) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }
    }

    // ── เรียก API ──
    if (empty($apiKey) || $apiKey === 'YOUR_CALENDARIFIC_API_KEY') {
        error_log('[Holidays] ยังไม่ได้ตั้งค่า CALENDARIFIC_API_KEY');
        return [];
    }

    $url = sprintf(
        'https://calendarific.com/api/v2/holidays?api_key=%s&country=TH&year=%d&type=national',
        urlencode($apiKey),
        $year
    );

    $ctx = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'timeout'         => 8,
            'ignore_errors'   => true,
            'header'          => "Accept: application/json\r\nUser-Agent: ThaiHolidayFetcher/1.0\r\n",
        ],
        'ssl' => ['verify_peer' => true],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        error_log("[Holidays] ไม่สามารถเชื่อมต่อ Calendarific API ปี {$year}");
        return [];
    }

    $json = json_decode($raw, true);
    if (!isset($json['response']['holidays']) || !is_array($json['response']['holidays'])) {
        error_log("[Holidays] รูปแบบ response ไม่ถูกต้องจาก Calendarific API ปี {$year}: " . substr($raw, 0, 200));
        return [];
    }

    // ── แปลชื่อวันหยุดภาษาอังกฤษ → ภาษาไทย ──
    $nameMap = [
        "New Year's Day"                              => 'วันปีใหม่',
        "New Year's Day (observed)"                   => 'วันหยุดชดเชยวันปีใหม่',
        'Makha Bucha'                                 => 'วันมาฆบูชา',
        'Chakri Memorial Day'                         => 'วันจักรี',
        'Chakri Day'                                  => 'วันจักรี',
        'Songkran Festival'                           => 'วันสงกรานต์',
        'Songkran'                                    => 'วันสงกรานต์',
        'National Labour Day'                         => 'วันแรงงาน',
        'Labor Day'                                   => 'วันแรงงาน',
        'Coronation Day'                              => 'วันฉัตรมงคล',
        'HM Queen Suthida\'s Birthday'                => 'วันเฉลิมพระชนมพรรษา ส.ก.',
        "Queen Suthida's Birthday"                    => 'วันเฉลิมพระชนมพรรษา ส.ก.',
        'Visakha Bucha'                               => 'วันวิสาขบูชา',
        'Visaka Bucha'                                => 'วันวิสาขบูชา',
        'Asanha Bucha'                                => 'วันอาสาฬหบูชา',
        'Asalha Bucha'                                => 'วันอาสาฬหบูชา',
        'Buddhist Lent Day'                           => 'วันเข้าพรรษา',
        "HM King Vajiralongkorn's Birthday"           => 'วันเฉลิมพระชนมพรรษา ร.10',
        "King Vajiralongkorn's Birthday"              => 'วันเฉลิมพระชนมพรรษา ร.10',
        "HM Queen Mother's Birthday"                  => 'วันแม่แห่งชาติ',
        "National Mother's Day"                       => 'วันแม่แห่งชาติ',
        "Mother's Day"                                => 'วันแม่แห่งชาติ',
        'Anniversary of King Bhumibol Adulyadej'      => 'วันสวรรคต ร.9',
        'King Bhumibol Adulyadej Memorial Day'        => 'วันสวรรคต ร.9',
        'Chulalongkorn Day'                           => 'วันปิยมหาราช',
        "HM King Bhumibol's Birthday"                 => 'วันพ่อแห่งชาติ',
        "National Father's Day"                       => 'วันพ่อแห่งชาติ',
        "Father's Day"                                => 'วันพ่อแห่งชาติ',
        'Constitution Day'                            => 'วันรัฐธรรมนูญ',
        "New Year's Eve"                              => 'วันสิ้นปี',
        'Bridge Public Holiday'                       => 'วันหยุดพิเศษ (กรอ.)',
        'Royal Ploughing Ceremony'                    => 'วันพืชมงคล',
    ];

    $holidays = [];
    foreach ($json['response']['holidays'] as $h) {
        $isoDate = $h['date']['iso'] ?? '';           // "YYYY-MM-DD" หรือ "YYYY-MM-DDThh:mm:ssZ"
        $dateKey = substr($isoDate, 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) continue;

        $engName = $h['name'] ?? '';
        // ค้นหาชื่อไทยจาก map; ถ้าไม่พบใช้ชื่ออังกฤษเดิม
        $thaiName = $nameMap[$engName] ?? null;
        if (!$thaiName) {
            // partial match
            foreach ($nameMap as $eng => $th) {
                if (stripos($engName, $eng) !== false || stripos($eng, $engName) !== false) {
                    $thaiName = $th;
                    break;
                }
            }
        }
        $holidays[$dateKey] = $thaiName ?? $engName;
    }
    ksort($holidays);

    // ── บันทึก cache ──
    if (!empty($holidays)) {
        @file_put_contents($cacheFile, json_encode($holidays, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    return $holidays;
}

/**
 * ข้อมูล fallback (ใช้เมื่อ API ใช้งานไม่ได้ หรือยังไม่ได้ตั้งค่า API Key)
 */
function getThaiHolidaysFallback(): array
{
    return [
        /* ── 2568 (2025) ── */
        '2025-01-01' => 'วันปีใหม่',
        '2025-02-12' => 'วันมาฆบูชา',
        '2025-04-06' => 'วันจักรี',
        '2025-04-13' => 'วันสงกรานต์',
        '2025-04-14' => 'วันสงกรานต์',
        '2025-04-15' => 'วันสงกรานต์',
        '2025-05-01' => 'วันแรงงาน',
        '2025-05-05' => 'วันฉัตรมงคล',
        '2025-05-11' => 'วันวิสาขบูชา',
        '2025-06-03' => 'วันเฉลิมพระชนมพรรษา ส.ก.',
        '2025-07-10' => 'วันอาสาฬหบูชา',
        '2025-07-11' => 'วันเข้าพรรษา',
        '2025-07-28' => 'วันเฉลิมพระชนมพรรษา ร.10',
        '2025-08-12' => 'วันแม่แห่งชาติ',
        '2025-10-13' => 'วันสวรรคต ร.9',
        '2025-10-23' => 'วันปิยมหาราช',
        '2025-12-05' => 'วันพ่อแห่งชาติ',
        '2025-12-10' => 'วันรัฐธรรมนูญ',
        '2025-12-31' => 'วันสิ้นปี',
        /* ── 2569 (2026) ── */
        '2026-01-01' => 'วันปีใหม่',
        '2026-03-03' => 'วันมาฆบูชา',
        '2026-04-06' => 'วันจักรี',
        '2026-04-13' => 'วันสงกรานต์',
        '2026-04-14' => 'วันสงกรานต์',
        '2026-04-15' => 'วันสงกรานต์',
        '2026-05-01' => 'วันแรงงาน',
        '2026-05-05' => 'วันฉัตรมงคล',
        '2026-05-31' => 'วันวิสาขบูชา',
        '2026-06-03' => 'วันเฉลิมพระชนมพรรษา ส.ก.',
        '2026-07-28' => 'วันเฉลิมพระชนมพรรษา ร.10',
        '2026-07-29' => 'วันอาสาฬหบูชา',
        '2026-07-30' => 'วันเข้าพรรษา',
        '2026-08-12' => 'วันแม่แห่งชาติ',
        '2026-10-13' => 'วันสวรรคต ร.9',
        '2026-10-23' => 'วันปิยมหาราช',
        '2026-12-05' => 'วันพ่อแห่งชาติ',
        '2026-12-10' => 'วันรัฐธรรมนูญ',
        '2026-12-31' => 'วันสิ้นปี',
    ];
}

// ── ตั้งค่า API Key ──────────────────────────────────────────────
// 1) แนะนำให้กำหนดใน config.php:  define('CALENDARIFIC_API_KEY', 'your_key_here');
// 2) หรือกำหนดตรงนี้ (ไม่แนะนำใน production):
//    $calendarific_api_key = 'your_key_here';
// ─────────────────────────────────────────────────────────────────
$calendarific_api_key = defined('CALENDARIFIC_API_KEY') ? CALENDARIFIC_API_KEY : ($calendarific_api_key ?? '');

// ── โฟลเดอร์ cache (ต้อง writable) ──
// แนะนำใช้ __DIR__ . '/cache/holidays' แล้ว mkdir ให้ก่อน
$holidays_cache_dir = defined('HOLIDAYS_CACHE_DIR') ? HOLIDAYS_CACHE_DIR : sys_get_temp_dir();

// ── ดึงข้อมูลปีปัจจุบัน + ปีถัดไป ──────────────────────────────
$currentYear = (int)date('Y');
$thai_holidays = [];

if (!empty($calendarific_api_key) && $calendarific_api_key !== 'YOUR_CALENDARIFIC_API_KEY') {
    // ดึงจาก API (มี cache อัตโนมัติ 30 วัน)
    $h_this = fetchThaiHolidays($currentYear,   $calendarific_api_key, $holidays_cache_dir);
    $h_next = fetchThaiHolidays($currentYear+1, $calendarific_api_key, $holidays_cache_dir);
    $thai_holidays = array_merge($h_this, $h_next);
    ksort($thai_holidays);

    // ถ้า API คืนค่าว่าง ให้ fallback
    if (empty($thai_holidays)) {
        $thai_holidays = getThaiHolidaysFallback();
        error_log('[Holidays] ใช้ข้อมูล fallback เนื่องจาก API ไม่ส่งข้อมูลกลับมา');
    }
} else {
    // ยังไม่ได้ตั้งค่า API Key — ใช้ข้อมูล fallback
    $thai_holidays = getThaiHolidaysFallback();
}

$_server_ms = round((hrtime(true) - $_server_start) / 1e6, 1);
?>
<!DOCTYPE html>
<html lang="th" data-theme="light" id="htmlRoot">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0a3d54" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#061f2a" media="(prefers-color-scheme: dark)">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="description" content="ปฏิทินและนัดหมายงาน — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง">
<title>ปฏิทินและนัดหมายงาน — ปภ.ระยอง</title>
<link rel="manifest" href="/manifest.json">

<!-- Preconnect -->
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Bootstrap 5.3 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════════════════════════
   DESIGN TOKENS — Enterprise command-center palette
   ══════════════════════════════════════════════════════════════════ */
:root {
    /* Core brand */
    --navy:         #0a3d54;
    --navy-deep:    #061f2a;
    --teal:         #0891b2;
    --teal-light:   #22d3ee;
    --teal-surface: #ecfeff;
    --amber:        #d97706;
    --amber-light:  #fbbf24;
    --crimson:      #b91c1c;
    --crimson-light:#fca5a5;
    --emerald:      #047857;
    --emerald-light:#6ee7b7;

    /* Surfaces */
    --surface-0:    #ffffff;
    --surface-1:    #f8fafc;
    --surface-2:    #f1f5f9;
    --surface-3:    #e2e8f0;

    /* Text */
    --text-primary:   #0f172a;
    --text-secondary: #475569;
    --text-muted:     #94a3b8;
    --text-inverse:   #ffffff;

    /* Borders */
    --border-subtle:  #e2e8f0;
    --border-medium:  #cbd5e1;

    /* Elevation */
    --shadow-xs:  0 1px 2px rgba(0,0,0,.04);
    --shadow-sm:  0 2px 8px rgba(0,0,0,.06);
    --shadow-md:  0 4px 20px rgba(0,0,0,.08);
    --shadow-lg:  0 8px 32px rgba(0,0,0,.12);
    --shadow-teal:0 4px 16px rgba(8,145,178,.25);

    /* Radii */
    --r-xs: 4px;
    --r-sm: 8px;
    --r-md: 12px;
    --r-lg: 16px;
    --r-xl: 20px;
    --r-2xl:24px;

    /* Transitions */
    --ease-out:  cubic-bezier(.16,1,.3,1);
    --ease-in:   cubic-bezier(.4,0,1,1);
    --ease-std:  cubic-bezier(.4,0,.2,1);

    /* Header gradient */
    --header-grad: linear-gradient(150deg, #061f2a 0%, #0a3d54 35%, #0a5f73 65%, #0891b2 100%);
}

/* Dark mode — triggered by data-theme="dark" OR system preference when no manual override */
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
        --surface-0:    #0f172a;
        --surface-1:    #1e293b;
        --surface-2:    #1e293b;
        --surface-3:    #334155;
        --text-primary:   #f1f5f9;
        --text-secondary: #94a3b8;
        --text-muted:     #64748b;
        --border-subtle:  #1e293b;
        --border-medium:  #334155;
        --teal-surface:   #083344;
        --header-grad: linear-gradient(150deg, #020d14 0%, #061f2a 40%, #0a3d54 100%);
    }
}
[data-theme="dark"] {
    --surface-0:    #0f172a;
    --surface-1:    #1e293b;
    --surface-2:    #1e293b;
    --surface-3:    #334155;
    --text-primary:   #f1f5f9;
    --text-secondary: #94a3b8;
    --text-muted:     #64748b;
    --border-subtle:  #1e293b;
    --border-medium:  #334155;
    --teal-surface:   #083344;
    --header-grad: linear-gradient(150deg, #020d14 0%, #061f2a 40%, #0a3d54 100%);
}

/* Theme toggle button */
.theme-toggle-btn {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.22);
    color: rgba(255,255,255,.9);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .92rem;
    flex-shrink: 0;
    transition: background .2s var(--ease-std), transform .3s var(--ease-out);
    backdrop-filter: blur(8px);
}
.theme-toggle-btn:hover {
    background: rgba(255,255,255,.22);
    transform: rotate(20deg) scale(1.08);
}
.theme-toggle-btn .icon-sun,
.theme-toggle-btn .icon-moon { transition: opacity .2s, transform .3s var(--ease-out); }
[data-theme="dark"] .theme-toggle-btn .icon-sun  { opacity: 1; transform: scale(1); }
[data-theme="dark"] .theme-toggle-btn .icon-moon { opacity: 0; transform: scale(.5); position: absolute; }
[data-theme="light"] .theme-toggle-btn .icon-sun  { opacity: 0; transform: scale(.5); position: absolute; }
[data-theme="light"] .theme-toggle-btn .icon-moon { opacity: 1; transform: scale(1); }

/* ══════════════════════════════════════════════════════════════════
   RESET & BASE
   ══════════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body {
    font-family: 'Anuphan', system-ui, sans-serif;
    font-weight: 400;
    font-size: 15px;
    background: var(--surface-1);
    color: var(--text-primary);
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    overscroll-behavior: none;
}
a { color: var(--teal); text-decoration: none; }
a:hover { color: var(--navy); }
button { font-family: inherit; }
code, .mono { font-family: 'IBM Plex Mono', monospace; }

/* Focus ring */
:focus-visible {
    outline: 2px solid var(--teal-light);
    outline-offset: 2px;
    border-radius: var(--r-xs);
}

/* ══════════════════════════════════════════════════════════════════
   SCROLLBAR
   ══════════════════════════════════════════════════════════════════ */
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-medium); border-radius: 2px; }

/* ══════════════════════════════════════════════════════════════════
   HEADER — Immersive command bar
   ══════════════════════════════════════════════════════════════════ */
.site-header {
    background: var(--header-grad);
    padding: 0;
    position: relative;
    overflow: hidden;
    z-index: 100;
}

/* Geometric accent shapes */
.site-header::before {
    content: '';
    position: absolute;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(8,145,178,.18) 0%, transparent 70%);
    top: -80px; right: -60px;
    pointer-events: none;
}
.site-header::after {
    content: '';
    position: absolute;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(34,211,238,.1) 0%, transparent 70%);
    bottom: -40px; left: 5%;
    pointer-events: none;
}

.header-inner {
    position: relative;
    z-index: 1;
    padding: 16px 16px 14px;
}

.header-top {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
}

.logo-wrap {
    width: 48px; height: 48px;
    border-radius: 12px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.22);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    backdrop-filter: blur(8px);
    overflow: hidden;
}
.logo-wrap img {
    width: 36px; height: 36px;
    border-radius: 8px;
    object-fit: cover;
}

.header-text { flex: 1; min-width: 0; }
.header-text h1 {
    font-size: 1rem;
    font-weight: 500;
    color: #fff;
    letter-spacing: .1px;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.header-text p {
    font-size: .70rem;
    color: rgba(255,255,255,.72);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* KPI chips */
.header-kpis {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.kpi-chip {
    background: rgba(255,255,255,.1);
    border: .5px solid rgba(255,255,255,.18);
    border-radius: 20px;
    padding: 5px 12px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .72rem;
    backdrop-filter: blur(4px);
    transition: background .2s var(--ease-std);
}
.kpi-chip:hover { background: rgba(255,255,255,.18); }
.kpi-num {
    font-size: .9rem;
    font-weight: 700;
    line-height: 1;
}
.kpi-label { opacity: .85; }

/* Version tag */
.version-tag {
    position: absolute;
    bottom: 10px; right: 14px;
    background: rgba(255,255,255,.1);
    border: .5px solid rgba(255,255,255,.2);
    border-radius: var(--r-sm);
    padding: 3px 10px;
    color: rgba(255,255,255,.65);
    font-size: .6rem;
    font-family: 'IBM Plex Mono', monospace;
    cursor: pointer;
    transition: background .2s;
    z-index: 2;
    letter-spacing: .3px;
}
.version-tag:hover { background: rgba(255,255,255,.18); color: #fff; }

/* ══════════════════════════════════════════════════════════════════
   DATE RIBBON — Today's contextual strip
   ══════════════════════════════════════════════════════════════════ */
.date-ribbon {
    background: var(--surface-0);
    border-bottom: 1px solid var(--border-subtle);
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    position: sticky;
    top: 0;
    z-index: 90;
    box-shadow: var(--shadow-xs);
}
.date-ribbon-text {
    font-size: .82rem;
    font-weight: 600;
    color: var(--navy);
    display: flex;
    align-items: center;
    gap: 6px;
}
.date-ribbon-text i { color: var(--teal); font-size: .85rem; }

.ribbon-badge {
    font-size: .7rem;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 500;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
}
.rb-work    { background: rgba(8,145,178,.12);  color: #0a5f73; border: .5px solid rgba(8,145,178,.25); }
.rb-done    { background: rgba(4,120,87,.1);    color: #047857; border: .5px solid rgba(4,120,87,.2); }
.rb-empty   { background: rgba(185,28,28,.08);  color: #b91c1c; border: .5px solid rgba(185,28,28,.15); }

/* ══════════════════════════════════════════════════════════════════
   MAIN LAYOUT
   ══════════════════════════════════════════════════════════════════ */
.main-content {
    padding: 12px 12px 100px;
    flex: 1;
    max-width: 640px;
    margin: 0 auto;
    width: 100%;
}

/* ══════════════════════════════════════════════════════════════════
   CARD COMPONENT SYSTEM
   ══════════════════════════════════════════════════════════════════ */
.card-ent {
    background: var(--surface-0);
    border: 1px solid var(--border-subtle);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 12px;
    transition: box-shadow .2s var(--ease-std);
}

.card-header-ent {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-0);
}
.card-header-ent h3 {
    font-size: .88rem;
    font-weight: 600;
    color: var(--navy);
    display: flex;
    align-items: center;
    gap: 7px;
}
.card-header-ent h3 i { color: var(--teal); }

/* ══════════════════════════════════════════════════════════════════
   CALENDAR WIDGET
   ══════════════════════════════════════════════════════════════════ */
.cal-controls {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cal-btn {
    width: 30px; height: 30px;
    border: 1px solid var(--border-medium);
    border-radius: var(--r-sm);
    background: var(--surface-1);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: .78rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .15s var(--ease-std);
}
.cal-btn:hover { background: var(--teal); color: #fff; border-color: var(--teal); transform: scale(1.05); }
.cal-btn.toggle-btn { background: transparent; border-color: var(--border-subtle); }
.cal-btn.toggle-btn i { transition: transform .3s var(--ease-out); }
.cal-btn.toggle-btn.collapsed i { transform: rotate(180deg); }

.cal-body { padding: 12px 12px 10px; }

.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}
.cal-dh {
    text-align: center;
    font-size: .64rem;
    font-weight: 600;
    letter-spacing: .5px;
    color: var(--text-muted);
    padding: 5px 0;
    text-transform: uppercase;
}
.cal-dh.sun { color: var(--crimson); }
.cal-dh.sat { color: var(--teal); }

.cal-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding-top: 5px;
    border-radius: var(--r-sm);
    cursor: pointer;
    font-size: .68rem;
    color: var(--text-primary);
    transition: all .15s var(--ease-std);
    user-select: none;
    position: relative;
}
.cal-day-num {
    line-height: 1.2;
    font-weight: 400;
}
.cal-day.sun-col .cal-day-num { color: var(--crimson); }
.cal-day.sat-col .cal-day-num { color: var(--teal); }
.cal-day.holiday .cal-day-num { color: var(--crimson) !important; font-weight: 700; }
.cal-day.today .cal-day-num,
.cal-day.selected .cal-day-num { color: #fff !important; }
.cal-day:hover:not(.other-month) { background: var(--surface-2); transform: scale(1.05); }
.cal-day.today {
    background: var(--teal);
    color: #fff !important;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(8,145,178,.4);
}
.cal-day.selected {
    background: var(--navy);
    color: #fff !important;
    font-weight: 600;
}
.cal-day.other-month { color: var(--border-medium); cursor: default; pointer-events: none; }

.event-dots {
    display: flex;
    gap: 2px;
    justify-content: center;
    margin-top: 3px;
    flex-wrap: wrap;
    max-width: 90%;
    max-height: 12px;
    overflow: hidden;
}
.event-dot {
    width: 4px; height: 4px;
    border-radius: 50%;
    flex-shrink: 0;
}

.cal-legend {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding: 8px 0 2px;
    border-top: 1px solid var(--border-subtle);
    margin-top: 8px;
}
.leg-item   { display: flex; align-items: center; gap: 5px; font-size: .66rem; color: var(--text-muted); }
.leg-dot    { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.cal-footer-info {
    padding: 8px 14px;
    background: var(--surface-1);
    border-top: 1px solid var(--border-subtle);
    font-size: .72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 7px;
    min-height: 36px;
}
.cal-footer-info i { color: var(--teal); }

/* ══════════════════════════════════════════════════════════════════
   TABS
   ══════════════════════════════════════════════════════════════════ */
.tabs-wrap {
    display: flex;
    border-bottom: 1px solid var(--border-subtle);
    background: var(--surface-0);
    padding: 0 4px;
    gap: 2px;
}
.tab-btn {
    flex: 1;
    padding: 11px 4px;
    text-align: center;
    font-size: .72rem;
    font-family: 'Anuphan', sans-serif;
    font-weight: 500;
    color: var(--text-muted);
    background: transparent;
    border: none;
    border-bottom: 2.5px solid transparent;
    cursor: pointer;
    transition: all .18s var(--ease-std);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    white-space: nowrap;
}
.tab-btn:hover:not(.active) { color: var(--teal); }
.tab-btn.active {
    color: var(--navy);
    border-bottom-color: var(--teal);
    background: rgba(8,145,178,.04);
}
.tab-btn .tab-count {
    background: var(--teal);
    color: #fff;
    border-radius: 10px;
    padding: 0px 5px;
    font-size: .6rem;
    min-width: 16px;
    text-align: center;
    line-height: 1.6;
}
.tab-btn:not(.active) .tab-count { background: var(--surface-2); color: var(--text-muted); }

.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ══════════════════════════════════════════════════════════════════
   EVENT CARDS
   ══════════════════════════════════════════════════════════════════ */
.ev-section { padding: 10px 12px 12px; }

.ev-date-group {
    border: 1px solid var(--border-subtle);
    border-radius: var(--r-lg);
    overflow: hidden;
    margin-bottom: 10px;
    background: var(--surface-0);
    box-shadow: var(--shadow-xs);
    position: relative;
    transition: box-shadow .2s;
}
.ev-date-group:hover { box-shadow: var(--shadow-sm); }
.ev-date-group.is-today {
    border-color: rgba(217,119,6,.35);
    box-shadow: 0 0 0 2px rgba(217,119,6,.08), var(--shadow-sm);
}
.ev-date-group.is-today::before {
    content: none; /* disable pseudo-element — now rendered inline */
}

/* Inline วันนี้ badge inside the date bar */
.today-badge {
    background: var(--amber);
    color: #fff;
    font-size: .6rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 10px;
    letter-spacing: .4px;
    flex-shrink: 0;
    white-space: nowrap;
}

.ev-date-bar {
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, var(--navy-deep), var(--navy));
    gap: 8px;
    overflow: hidden;
}
.ev-date-group.is-today .ev-date-bar {
    /* no extra padding needed — flex layout handles it */
}
.ev-date-bar.future-bar {
    background: linear-gradient(90deg, #1a3a2e, #1e5540);
}
.ev-date-label {
    color: rgba(255,255,255,.9);
    font-size: .78rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    flex: 1;
    overflow: hidden;
}
.ev-date-label > i { flex-shrink: 0; }
.ev-date-label-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ev-count-pill {
    background: rgba(255,255,255,.15);
    color: rgba(255,255,255,.9);
    border-radius: 10px;
    padding: 2px 9px;
    font-size: .65rem;
    font-weight: 500;
    flex-shrink: 0;
    white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════════════
   CALENDAR — HOLIDAY STYLES
   ══════════════════════════════════════════════════════════════════ */
.cal-day.holiday:not(.today):not(.selected) {
    background: rgba(185,28,28,.07);
}
.cal-day.holiday .cal-day-num { color: var(--crimson) !important; }
.cal-day.holiday.sun-col .cal-day-num,
.cal-day.holiday .cal-day-num { color: var(--crimson) !important; }

/* Holiday marker — flag icon top-right corner */
.cal-day.holiday::after {
    content: '🇹🇭';
    position: absolute;
    top: 1px; right: 2px;
    font-size: .55rem;
    line-height: 1;
    opacity: .9;
    pointer-events: none;
}
.cal-day.today::after,
.cal-day.selected::after { display: none; } /* hide on today/selected — bg already prominent */

/* Holiday short label below the day number */
.cal-holiday-label {
    font-size: .52rem;
    line-height: 1.15;
    color: var(--crimson);
    text-align: center;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0 2px;
    font-weight: 400;
    opacity: .9;
}
.cal-day.today .cal-holiday-label,
.cal-day.selected .cal-holiday-label { color: rgba(255,255,255,.9); }

/* Legend holiday item — larger dot + flag */
.leg-item.holiday-leg {
    background: rgba(185,28,28,.07);
    border: .5px solid rgba(185,28,28,.2);
    border-radius: 10px;
    padding: 2px 7px 2px 5px;
    color: var(--crimson);
    font-weight: 600;
}
.leg-item.holiday-leg .leg-dot {
    background: var(--crimson);
    width: 10px; height: 10px;
}

.ev-item { padding: 13px 14px; position: relative; }
.ev-item + .ev-item { border-top: 1px solid var(--border-subtle); }

/* Event top row */
.ev-top-row { margin-bottom: 8px; }

.cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 20px;
    font-size: .66rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
    letter-spacing: .2px;
}

.ev-title {
    font-size: .85rem;
    font-weight: 400;
    line-height: 1.45;
    color: var(--text-primary);
}

/* Status pills */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: .66rem;
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: .1px;
}
.sp-upcoming {
    background: rgba(8,145,178,.1);
    color: #0a5f73;
    border: .5px solid rgba(8,145,178,.2);
}
.sp-ongoing {
    background: rgba(217,119,6,.12);
    color: #92400e;
    border: .5px solid rgba(217,119,6,.25);
    animation: glow-pulse 2.5s ease-in-out infinite;
}
.sp-done {
    background: rgba(71,85,105,.08);
    color: var(--text-muted);
    border: .5px solid var(--border-subtle);
}

@keyframes glow-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(217,119,6,.3); }
    50%       { box-shadow: 0 0 0 4px rgba(217,119,6,0); }
}

/* Meta rows */
.ev-meta {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 6px;
    line-height: 1.5;
}
.ev-meta-icon {
    color: var(--teal);
    font-size: .76rem;
    margin-top: 2px;
    flex-shrink: 0;
    width: 14px;
    text-align: center;
}
.ev-meta-val  { font-size: .78rem; color: var(--text-secondary); }
.ev-meta-val b { color: var(--text-primary); font-weight: 600; }
.ev-meta-val a { color: var(--teal); }
.ev-meta-val a:hover { color: var(--navy); text-decoration: underline; }

/* Person badges */
.person-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 9px;
    margin: 2px 3px 0 0;
    background: rgba(8,145,178,.07);
    border: .5px solid rgba(8,145,178,.2);
    border-radius: 12px;
    font-size: .68rem;
    color: var(--navy);
    cursor: pointer;
    transition: all .15s var(--ease-std);
    text-decoration: none;
    font-weight: 500;
}
.person-badge:hover {
    background: var(--teal);
    color: #fff;
    border-color: var(--teal);
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(8,145,178,.3);
}
.with-text { font-size: .72rem; color: var(--text-muted); margin: 2px 2px 0; }

/* ══════════════════════════════════════════════════════════════════
   OBSERVANCE TAB
   ══════════════════════════════════════════════════════════════════ */
.obs-section { padding: 10px 12px 12px; }

.obs-group {
    border: 1px solid var(--border-subtle);
    border-radius: var(--r-md);
    overflow: hidden;
    margin-bottom: 8px;
    background: var(--surface-0);
}
.obs-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 14px;
    cursor: pointer;
    background: var(--surface-1);
    transition: background .15s;
    border: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
}
.obs-group-header:hover { background: var(--teal-surface); }
.obs-group-header[aria-expanded="false"] .obs-chevron { transform: rotate(180deg); }
.obs-chevron { transition: transform .25s var(--ease-out); color: var(--text-muted); font-size: .76rem; }

.obs-group-name  { font-size: .85rem; font-weight: 600; color: var(--navy); }
.obs-meta { display: flex; align-items: center; gap: 8px; }
.obs-group-count { font-size: .68rem; color: var(--text-muted); background: var(--surface-2); padding: 2px 8px; border-radius: 10px; }
.obs-group-body  { padding: 6px 14px 12px; border-top: 1px solid var(--border-subtle); }

.person-list-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-subtle);
    font-size: .82rem;
    color: var(--text-primary);
}
.person-list-item:last-child { border-bottom: none; }
.person-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--teal), var(--navy));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .6rem;
    font-weight: 700;
    flex-shrink: 0;
    letter-spacing: .5px;
}

/* ══════════════════════════════════════════════════════════════════
   STATS TAB
   ══════════════════════════════════════════════════════════════════ */
.stats-section { padding: 12px; }

.kpi-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 14px;
}
.kpi-card {
    background: var(--surface-0);
    border: 1px solid var(--border-subtle);
    border-radius: var(--r-md);
    padding: 14px;
    position: relative;
    overflow: hidden;
    transition: transform .15s, box-shadow .15s;
}
.kpi-card:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}
.kpi-card.primary::before   { background: var(--teal); }
.kpi-card.warning::before   { background: var(--amber); }
.kpi-card.success::before   { background: var(--emerald); }
.kpi-card.danger::before    { background: var(--crimson); }

.kpi-big {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
    font-variant-numeric: tabular-nums;
}
.kpi-big.primary   { color: var(--teal); }
.kpi-big.warning   { color: var(--amber); }
.kpi-big.success   { color: var(--emerald); }
.kpi-big.danger    { color: var(--crimson); }

.kpi-desc { font-size: .7rem; color: var(--text-muted); font-weight: 500; }
.kpi-icon {
    position: absolute;
    bottom: 10px; right: 12px;
    font-size: 1.4rem;
    opacity: .12;
}

.section-divider {
    font-size: .64rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--text-muted);
    padding: 8px 0 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-subtle);
}

/* Category progress bars */
.cat-bar-wrap { margin-bottom: 10px; }
.cat-bar-label {
    display: flex;
    justify-content: space-between;
    font-size: .76rem;
    margin-bottom: 5px;
    color: var(--text-secondary);
}
.cat-bar-label span:first-child { color: var(--text-primary); font-weight: 500; }
.cat-bar-track  { height: 5px; background: var(--surface-2); border-radius: 3px; overflow: hidden; }
.cat-bar-fill   { height: 100%; border-radius: 3px; transition: width .6s var(--ease-out); min-width: 3px; }

/* Performance row */
.perf-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: var(--surface-1);
    border-radius: var(--r-md);
    border: 1px solid var(--border-subtle);
    margin-top: 10px;
}
.perf-label { font-size: .72rem; color: var(--text-muted); }
.perf-val   { font-size: .72rem; font-family: 'IBM Plex Mono', monospace; color: var(--teal); font-weight: 500; }

/* ══════════════════════════════════════════════════════════════════
   BOTTOM SHEET (Modal)
   ══════════════════════════════════════════════════════════════════ */
.sheet-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 2000;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s var(--ease-std);
    backdrop-filter: blur(2px);
}
.sheet-overlay.open {
    opacity: 1;
    pointer-events: all;
}

.sheet-panel {
    background: var(--surface-0);
    width: 100%;
    max-width: 560px;
    border-radius: var(--r-2xl) var(--r-2xl) 0 0;
    max-height: 80dvh;
    overflow-y: auto;
    transform: translateY(100%);
    transition: transform .3s var(--ease-out);
    overscroll-behavior: contain;
}
.sheet-overlay.open .sheet-panel { transform: translateY(0); }

.sheet-handle {
    width: 40px; height: 4px;
    border-radius: 2px;
    background: var(--border-medium);
    margin: 12px auto 0;
}
.sheet-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-subtle);
    position: sticky;
    top: 0;
    background: var(--surface-0);
    z-index: 1;
}
.sheet-head h4 {
    font-size: .92rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.sheet-close {
    width: 30px; height: 30px;
    border: 1px solid var(--border-medium);
    background: var(--surface-1);
    border-radius: 50%;
    cursor: pointer;
    font-size: .85rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .15s;
}
.sheet-close:hover { background: var(--crimson); color: #fff; border-color: var(--crimson); }
.sheet-body { padding: 14px 18px 40px; }

/* Leave cards */
.leave-card {
    border: 1px solid var(--border-subtle);
    border-left: 3px solid var(--teal);
    border-radius: var(--r-md);
    padding: 12px 14px;
    margin-bottom: 10px;
    background: var(--surface-0);
    transition: box-shadow .15s;
}
.leave-card:hover { box-shadow: var(--shadow-sm); }
.leave-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}
.leave-card-name {
    font-size: .88rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 6px;
}
.leave-type-badge {
    font-size: .65rem;
    padding: 2px 9px;
    border-radius: 10px;
    background: rgba(8,145,178,.1);
    color: var(--teal);
    font-weight: 600;
    border: .5px solid rgba(8,145,178,.2);
    white-space: nowrap;
}
.leave-days-badge {
    background: rgba(185,28,28,.1);
    color: var(--crimson);
    border: .5px solid rgba(185,28,28,.2);
    border-radius: 10px;
    padding: 2px 9px;
    font-size: .7rem;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.leave-meta {
    font-size: .75rem;
    color: var(--text-muted);
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.leave-meta i { color: var(--teal); }

/* ══════════════════════════════════════════════════════════════════
   FLOATING ACTION BUTTONS
   ══════════════════════════════════════════════════════════════════ */
.fab-leave {
    position: fixed;
    bottom: 80px;
    left: 16px;
    z-index: 900;
    background: var(--crimson);
    color: #fff;
    border: none;
    border-radius: 24px;
    padding: 10px 18px;
    font-size: .8rem;
    font-family: 'Anuphan', sans-serif;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 7px;
    box-shadow: 0 4px 14px rgba(185,28,28,.4);
    transition: all .2s var(--ease-out);
}
.fab-leave:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(185,28,28,.5);
    background: #991b1b;
}
.fab-leave .fab-badge {
    background: rgba(255,255,255,.25);
    border-radius: 10px;
    padding: 1px 6px;
    font-size: .65rem;
    font-weight: 700;
}

.fab-manage {
    position: fixed;
    bottom: 20px;
    right: 16px;
    z-index: 900;
    background: linear-gradient(135deg, var(--teal), var(--navy));
    color: #fff;
    border: none;
    border-radius: 24px;
    padding: 10px 20px;
    font-size: .8rem;
    font-family: 'Anuphan', sans-serif;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 7px;
    box-shadow: 0 4px 16px rgba(8,145,178,.4);
    transition: all .2s var(--ease-out);
    text-decoration: none;
}
.fab-manage:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 22px rgba(8,145,178,.5);
    color: #fff;
    filter: brightness(1.08);
}

/* ══════════════════════════════════════════════════════════════════
   FOOTER
   ══════════════════════════════════════════════════════════════════ */
.site-footer {
    background: var(--navy-deep);
    color: rgba(255,255,255,.6);
    text-align: center;
    padding: 10px 14px;
    font-size: .68rem;
    margin-top: auto;
    line-height: 1.7;
}
.perf-line {
    opacity: .5;
    font-size: .62rem;
    font-family: 'IBM Plex Mono', monospace;
    margin-top: 2px;
}

/* ══════════════════════════════════════════════════════════════════
   EMPTY STATES
   ══════════════════════════════════════════════════════════════════ */
.empty-state {
    text-align: center;
    padding: 32px 20px;
    color: var(--text-muted);
}
.empty-state i { font-size: 2.2rem; display: block; margin-bottom: 10px; opacity: .4; }
.empty-state p { font-size: .84rem; }

/* ══════════════════════════════════════════════════════════════════
   ANIMATIONS & UTILITIES
   ══════════════════════════════════════════════════════════════════ */
@keyframes slide-up {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-in { animation: slide-up .3s var(--ease-out) both; }

.hidden { display: none !important; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border-width: 0; }

/* ══════════════════════════════════════════════════════════════════
   SKELETON LOADER
   ══════════════════════════════════════════════════════════════════ */
@keyframes shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}
.skeleton {
    background: linear-gradient(90deg, var(--surface-2) 25%, var(--surface-3) 50%, var(--surface-2) 75%);
    background-size: 400px 100%;
    animation: shimmer 1.4s ease-in-out infinite;
    border-radius: var(--r-sm);
}

/* ══════════════════════════════════════════════════════════════════
   PRINT OPTIMIZATION
   ══════════════════════════════════════════════════════════════════ */
@media print {
    .site-header, .date-ribbon, .fab-leave, .fab-manage, .sheet-overlay { display: none !important; }
    body { background: white; }
    .card-ent { box-shadow: none; border: 1px solid #ddd; }
}
<!-- Theme: apply saved preference immediately to prevent flash -->
<script>
(function(){
    var saved = localStorage.getItem('theme');
    var sys   = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.getElementById('htmlRoot').setAttribute('data-theme', saved || sys);
})();
</script>
</style>
</head>
<body>

<!-- ════════════════════════════════════════════════════════════════
     HEADER
     ════════════════════════════════════════════════════════════════ -->
<header class="site-header" role="banner">
    <div class="header-inner">
        <div class="header-top">
            <div class="logo-wrap" aria-hidden="true">
                <img src="/images/logo.png" alt="ตราสัญลักษณ์ ปภ.ระยอง" width="36" height="36"
                     onerror="this.parentElement.innerHTML='<span style=\'font-size:22px\'>🛡️</span>'">
            </div>
            <div class="header-text">
                <h1>ปฏิทินและนัดหมายงาน</h1>
                <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
            </div>
            <button class="theme-toggle-btn" id="themeToggle"
                    aria-label="สลับธีม Dark/Light"
                    title="สลับ Dark / Light Mode">
                <i class="bi bi-sun-fill icon-sun" aria-hidden="true"></i>
                <i class="bi bi-moon-stars-fill icon-moon" aria-hidden="true"></i>
            </button>
        </div>

        <div class="header-kpis" role="list" aria-label="สถิติโดยสรุป">
            <div class="kpi-chip" role="listitem">
                <span class="kpi-num" aria-label="วาระงานวันนี้"><?= $total_events ?></span>
                <span class="kpi-label">วันนี้</span>
            </div>
            <div class="kpi-chip" role="listitem">
                <span class="kpi-num" aria-label="กำลังมาถึง"><?= $upcoming_cnt ?></span>
                <span class="kpi-label">กำลังมาถึง</span>
            </div>
            <?php if (!empty($leave_data)): ?>
            <div class="kpi-chip" role="listitem" style="border-color:rgba(252,165,165,.3)">
                <i class="bi bi-person-dash" aria-hidden="true"></i>
                <span class="kpi-num"><?= count($leave_data) ?></span>
                <span class="kpi-label">ลา</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <button class="version-tag" onclick="window.location.href='changelogs'"
            title="ดูบันทึกการเปลี่ยนแปลง" aria-label="เวอร์ชัน <?= e($current_version) ?>">
        เวอร์ชัน <?= e($current_version) ?>
    </button>
</header>

<!-- ════════════════════════════════════════════════════════════════
     DATE RIBBON
     ════════════════════════════════════════════════════════════════ -->
<div class="date-ribbon" role="status" aria-live="polite" aria-label="สถานะวันนี้">
    <span class="date-ribbon-text">
        <i class="bi bi-calendar-check" aria-hidden="true"></i>
        <?= $current_date_thai ?>
    </span>
    <?php if ($total_events > 0): ?>
        <span class="ribbon-badge rb-work">
            <i class="bi bi-circle-fill" style="font-size:.5rem" aria-hidden="true"></i>
            มีวาระ <?= $total_events ?> งาน
        </span>
    <?php elseif ($completed_events > 0): ?>
        <span class="ribbon-badge rb-done">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            เสร็จสิ้นแล้ว
        </span>
    <?php else: ?>
        <span class="ribbon-badge rb-empty">
            <i class="bi bi-dash-circle" aria-hidden="true"></i>
            ไม่มีวาระงาน
        </span>
    <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════════════════════
     MAIN CONTENT
     ════════════════════════════════════════════════════════════════ -->
<main class="main-content" role="main">

    <!-- ── CALENDAR CARD ── -->
    <div class="card-ent animate-in" id="calCard" aria-label="ปฏิทินประจำเดือน">
        <div class="card-header-ent">
            <h3 id="calMonthYear" aria-live="polite">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span>กำลังโหลด…</span>
            </h3>
            <div class="cal-controls">
                <button class="cal-btn" id="prevMonth" aria-label="เดือนก่อน">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                <button class="cal-btn" id="nextMonth" aria-label="เดือนถัดไป">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
                <button class="cal-btn toggle-btn" id="calToggle" aria-label="ซ่อน/แสดงปฏิทิน"
                        aria-expanded="true" aria-controls="calBody">
                    <i class="bi bi-chevron-up" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div id="calBody">
            <div class="cal-body">
                <div class="cal-grid" id="calGrid" role="grid" aria-label="ตารางปฏิทิน">
                    <div class="cal-dh sun" role="columnheader" aria-label="วันอาทิตย์">อา</div>
                    <div class="cal-dh"     role="columnheader" aria-label="วันจันทร์">จ</div>
                    <div class="cal-dh"     role="columnheader" aria-label="วันอังคาร">อ</div>
                    <div class="cal-dh"     role="columnheader" aria-label="วันพุธ">พ</div>
                    <div class="cal-dh"     role="columnheader" aria-label="วันพฤหัสบดี">พฤ</div>
                    <div class="cal-dh"     role="columnheader" aria-label="วันศุกร์">ศ</div>
                    <div class="cal-dh sat" role="columnheader" aria-label="วันเสาร์">ส</div>
                </div>
                <div class="cal-legend" id="calLegend" aria-label="คำอธิบายสัญลักษณ์"></div>
            </div>
        </div>

        <div class="cal-footer-info" role="status" aria-live="polite">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span id="calInfo">กดเลือกวันเพื่อกรองวาระงาน</span>
        </div>
    </div>

    <!-- ── TABS CARD ── -->
    <div class="card-ent animate-in" style="animation-delay:.05s; padding:0" role="region" aria-label="ข้อมูลวาระงาน">
        <div class="tabs-wrap" role="tablist" aria-label="เมนูข้อมูล">
            <button class="tab-btn active" role="tab" aria-selected="true" aria-controls="tab-events"
                    id="btn-events" onclick="switchTab('events',this)">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                วาระงาน
                <span class="tab-count"><?= count($upcoming_events) ?></span>
            </button>
            <button class="tab-btn" role="tab" aria-selected="false" aria-controls="tab-observance"
                    id="btn-observance" onclick="switchTab('observance',this)">
                <i class="bi bi-people" aria-hidden="true"></i>
                ชุดพิธี
            </button>
            <button class="tab-btn" role="tab" aria-selected="false" aria-controls="tab-stats"
                    id="btn-stats" onclick="switchTab('stats',this)">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                สถิติ
            </button>
        </div>

        <!-- ── TAB: EVENTS ── -->
        <div id="tab-events" class="tab-pane active" role="tabpanel" aria-labelledby="btn-events">
            <div class="ev-section" id="eventsContainer">
            <?php
            $group_date = null;
            foreach ($upcoming_events as $ev):
                $ev_date   = $ev['date'];
                $is_today  = ($ev_date === $current_date);
                $thai_ev_date = thaiDateFull($ev_date);
                $cat_name  = $ev['category_name'] ?? 'อื่นๆ';
                $cat_color = !empty($ev['category_color']) ? $ev['category_color'] : getCategoryColor($cat_name);
                $cat_icon  = getCategoryIcon($cat_name);
                $ev_id     = (int)$ev['id'];

                /* Status computation */
                $t_from = $ev['time_from'] ?? '';
                $t_to   = $ev['time_to']   ?? '';
                $status = '';
                $countdown_label = '';
                if ($is_today && !empty($t_from)) {
                    if ($current_time < $t_from) {
                        $diff_sec = strtotime($ev_date.' '.$t_from) - time();
                        if ($diff_sec > 0) {
                            $hrs  = (int)floor($diff_sec / 3600);
                            $mins = (int)floor(($diff_sec % 3600) / 60);
                            $countdown_label = $hrs > 0
                                ? "จะเริ่มในอีก {$hrs} ชม. {$mins} นาที"
                                : "จะเริ่มในอีก {$mins} นาที";
                            $status = 'upcoming';
                        }
                    } elseif (!empty($t_to) && $current_time >= $t_from && $current_time <= $t_to) {
                        $status = 'ongoing';
                    } elseif (!empty($t_to) && $current_time > $t_to) {
                        $status = 'done';
                    }
                }

                $ev_count_in_day = count(array_filter($upcoming_events, fn($x) => $x['date'] === $ev_date));

                /* Open new group */
                if ($group_date !== $ev_date):
                    if ($group_date !== null) echo '</div><!-- /ev-date-group -->';
                    $group_date = $ev_date;
            ?>
            <div class="ev-date-group<?= $is_today ? ' is-today' : '' ?>"
                 data-group-date="<?= e($ev_date) ?>"
                 role="group"
                 aria-label="<?= e($thai_ev_date) ?>">
                <div class="ev-date-bar<?= !$is_today ? ' future-bar' : '' ?>" aria-hidden="true">
                    <span class="ev-date-label">
                        <i class="bi bi-calendar-date"></i>
                        <span class="ev-date-label-text"><?= e($thai_ev_date) ?></span>
                    </span>
                    <?php if ($is_today): ?>
                    <span class="today-badge">วันนี้</span>
                    <?php endif; ?>
                    <span class="ev-count-pill"><?= $ev_count_in_day ?> งาน</span>
                </div>
            <?php else: ?>
                <div role="separator" style="height:1px;background:var(--border-subtle)"></div>
            <?php endif; ?>

            <!-- EVENT ITEM -->
            <article class="ev-item" id="event-<?= $ev_id ?>" data-event-date="<?= e($ev_date) ?>">

                <div class="ev-top-row">
                    <span class="cat-badge" style="background:<?= e($cat_color) ?>"
                          aria-label="ประเภท: <?= e($cat_name) ?>">
                        <i class="bi bi-<?= e($cat_icon) ?>" aria-hidden="true"></i>
                        <?= e($cat_name) ?>
                    </span>
                    <h4 class="ev-title"><?= e($ev['title']) ?></h4>
                </div>

                <?php if ($is_today && !empty($status)): ?>
                <div>
                    <?php if ($status === 'upcoming'): ?>
                        <span class="status-pill sp-upcoming" id="cd-<?= $ev_id ?>" role="status">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <span class="cd-text"><?= e($countdown_label) ?></span>
                        </span>
                        <script>
                        (function(){
                            const el    = document.getElementById('cd-<?= $ev_id ?>');
                            const txtEl = el?.querySelector('.cd-text');
                            if (!el || !txtEl) return;
                            const target = new Date('<?= $ev_date ?>T<?= substr($t_from,0,5) ?>:00');
                            const fmt = ms => {
                                if (ms <= 0) return null;
                                const h = Math.floor(ms/3600000);
                                const m = Math.floor((ms%3600000)/60000);
                                return h > 0 ? `จะเริ่มในอีก ${h} ชม. ${m} นาที` : `จะเริ่มในอีก ${m} นาที`;
                            };
                            const tick = () => {
                                const diff = target - Date.now();
                                const label = fmt(diff);
                                if (!label) {
                                    el.className = 'status-pill sp-ongoing';
                                    el.innerHTML = '<i class="bi bi-record-circle-fill"></i> กำลังดำเนินการ';
                                    return;
                                }
                                txtEl.textContent = label;
                            };
                            setInterval(tick, 30000);
                        })();
                        </script>
                    <?php elseif ($status === 'ongoing'): ?>
                        <span class="status-pill sp-ongoing" role="status">
                            <i class="bi bi-record-circle-fill" aria-hidden="true"></i>
                            กำลังดำเนินการ
                        </span>
                    <?php elseif ($status === 'done'): ?>
                        <span class="status-pill sp-done">
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            เสร็จสิ้น
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Time -->
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-clock" aria-hidden="true"></i></span>
                    <span class="ev-meta-val">
                        <b><?= !empty($t_from) ? fmtTime($t_from) : '—' ?></b>
                        <?php if (!empty($t_to)): ?></b><?php endif; ?>
                    </span>
                </div>

                <!-- Location -->
                <?php if (!empty($ev['location'])): ?>
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                    <span class="ev-meta-val">
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($ev['location']) ?>"
                           target="_blank" rel="noopener noreferrer"
                           aria-label="แผนที่: <?= e($ev['location']) ?>">
                            <?= e($ev['location']) ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Dress Code -->
                <?php if (!empty($ev['Dress'])): ?>
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                    <span class="ev-meta-val"><b>การแต่งกาย:</b> <?= e($ev['Dress']) ?></span>
                </div>
                <?php endif; ?>

                <!-- Department -->
                <?php if (!empty($ev['department'])): ?>
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
                    <span class="ev-meta-val"><b>เจ้าของเรื่อง:</b> <?= e($ev['department']) ?></span>
                </div>
                <?php endif; ?>

                <!-- Chairman -->
                <?php if (!empty($ev['chairman'])): ?>
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                    <span class="ev-meta-val"><b>ประธาน:</b> <?= e($ev['chairman']) ?></span>
                </div>
                <?php endif; ?>

                <!-- Assigned persons (description field) -->
                <?php if (!empty($ev['description'])):
                    $raw_tags      = array_values(array_filter(array_map('trim', explode(' ', $ev['description']))));
                    $director_tags = array_values(array_filter($raw_tags, fn($t) => stripos($t, 'หน.ปภ') !== false));
                    $other_tags    = array_values(array_filter($raw_tags, fn($t) => stripos($t, 'หน.ปภ') === false));
                    $has_director  = !empty($director_tags);
                    $render_tags   = $has_director ? array_merge($director_tags, $other_tags) : $other_tags;
                ?>
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                    <span class="ev-meta-val" style="display:flex;flex-wrap:wrap;align-items:center;gap:2px">
                        <b><?= $has_director ? 'ลงนัด:' : 'มอบหมาย:' ?></b>
                        <?php
                        $first_other = true;
                        foreach ($render_tags as $tag):
                            if ($has_director && $first_other && !empty($other_tags) && in_array($tag, $other_tags, true)):
                                $first_other = false;
                        ?>
                            <span class="with-text">พร้อมด้วย</span>
                        <?php
                            endif;
                            $obs_id  = array_search($tag, $observance_options, true);
                            $persons = ($obs_id !== false && isset($responsible_persons[$obs_id]))
                                       ? $responsible_persons[$obs_id]
                                       : [];
                            $persons_csv = htmlspecialchars(implode(',', $persons), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        ?>
                            <?php if ($persons): ?>
                                <a href="#"
                                   class="person-badge"
                                   role="button"
                                   onclick="event.preventDefault();App.showPersonModal('<?= e($tag) ?>','<?= $persons_csv ?>')"
                                   aria-label="ดูรายชื่อ <?= e($tag) ?>">
                                    <?= e($tag) ?>
                                </a>
                            <?php else: ?>
                                <span class="person-badge" aria-label="<?= e($tag) ?>"><?= e($tag) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </span>
                </div>
                <?php endif; ?>

                <!-- File Attachment -->
                <?php if (!empty($ev['file_name'])):
                    $ext  = strtolower(pathinfo($ev['file_name'], PATHINFO_EXTENSION));
                    $icon = match(true) {
                        $ext === 'pdf'                             => 'filetype-pdf',
                        in_array($ext, ['jpg','jpeg','png','gif'])=> 'file-image',
                        in_array($ext, ['doc','docx'])            => 'filetype-doc',
                        in_array($ext, ['xls','xlsx'])            => 'filetype-xlsx',
                        default                                    => 'file-earmark',
                    };
                ?>
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i></span>
                    <span class="ev-meta-val">
                        <a href="uploads/<?= e($ev['file_name']) ?>" download
                           aria-label="ดาวน์โหลดเอกสารแนบ <?= strtoupper($ext) ?>">
                            เอกสารแนบ (<?= strtoupper($ext) ?>)
                        </a>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Coordinator -->
                <?php if (!empty($ev['coordinator_name']) || !empty($ev['coordinator_phone'])): ?>
                <div class="ev-meta">
                    <span class="ev-meta-icon"><i class="bi bi-headset" aria-hidden="true"></i></span>
                    <span class="ev-meta-val">
                        <b>ผู้ประสานงาน:</b>
                        <?= e($ev['coordinator_name'] ?? '') ?>
                        <?php if (!empty($ev['coordinator_phone'])): ?>
                            <a href="tel:<?= e($ev['coordinator_phone']) ?>"
                               style="margin-left:5px"
                               aria-label="โทร <?= e($ev['coordinator_phone']) ?>">
                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                <?= e($ev['coordinator_phone']) ?>
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>

            </article><!-- /ev-item -->

            <?php endforeach;
            if ($group_date !== null) echo '</div><!-- /ev-date-group -->';

            if (empty($upcoming_events)): ?>
            <div class="empty-state" role="status">
                <i class="bi bi-calendar-x" aria-hidden="true"></i>
                <p>ไม่มีวาระงานในขณะนี้</p>
            </div>
            <?php endif; ?>

            </div><!-- /#eventsContainer -->
        </div><!-- /#tab-events -->

        <!-- ── TAB: OBSERVANCE ── -->
        <div id="tab-observance" class="tab-pane" role="tabpanel" aria-labelledby="btn-observance">
            <div class="obs-section">
            <?php if (empty($responsible_persons)): ?>
                <div class="empty-state" role="status">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <p>ไม่พบข้อมูลผู้ปฏิบัติ</p>
                </div>
            <?php else: ?>
                <?php foreach ($observance_options as $obs_id => $obs_name):
                    $persons = $responsible_persons[$obs_id] ?? [];
                    $uid     = 'obs-' . $obs_id;
                ?>
                <div class="obs-group" role="region" aria-labelledby="<?= $uid ?>-header">
                    <button class="obs-group-header"
                            id="<?= $uid ?>-header"
                            aria-expanded="false"
                            aria-controls="<?= $uid ?>-body"
                            onclick="App.toggleObs(this)">
                        <span class="obs-group-name"><?= e($obs_name) ?></span>
                        <span class="obs-meta">
                            <span class="obs-group-count"><?= count($persons) ?> คน</span>
                            <i class="bi bi-chevron-up obs-chevron" aria-hidden="true"></i>
                        </span>
                    </button>
                    <div class="obs-group-body" id="<?= $uid ?>-body" hidden>
                        <?php if (empty($persons)): ?>
                            <p style="font-size:.78rem;color:var(--text-muted);padding:8px 0">ไม่มีข้อมูล</p>
                        <?php else: ?>
                            <?php foreach ($persons as $p): ?>
                            <div class="person-list-item">
                                <div class="person-avatar" aria-hidden="true">
                                    <?= mb_substr($p, -2, 2, 'UTF-8') ?>
                                </div>
                                <span><?= e($p) ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div><!-- /#tab-observance -->

        <!-- ── TAB: STATS ── -->
        <div id="tab-stats" class="tab-pane" role="tabpanel" aria-labelledby="btn-stats">
            <div class="stats-section">

                <!-- KPI Grid -->
                <div class="kpi-grid" role="list" aria-label="สถิติวาระงาน">
                    <div class="kpi-card primary" role="listitem">
                        <div class="kpi-big primary"><?= count($today_evs) ?></div>
                        <div class="kpi-desc">วาระงานวันนี้</div>
                        <i class="bi bi-calendar-day kpi-icon" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-card warning" role="listitem">
                        <div class="kpi-big warning"><?= count($future_evs) ?></div>
                        <div class="kpi-desc">กำลังมาถึง</div>
                        <i class="bi bi-calendar-event kpi-icon" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-card success" role="listitem">
                        <div class="kpi-big success"><?= $completed_events ?></div>
                        <div class="kpi-desc">เสร็จสิ้นวันนี้</div>
                        <i class="bi bi-check-circle kpi-icon" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-card danger" role="listitem">
                        <div class="kpi-big danger"><?= count($leave_data) ?></div>
                        <div class="kpi-desc">บุคลากรลา</div>
                        <i class="bi bi-person-dash kpi-icon" aria-hidden="true"></i>
                    </div>
                </div>

                <!-- Category breakdown -->
                <?php if (!empty($cat_counts)): ?>
                <div class="section-divider">วาระงานตามประเภท</div>
                <?php foreach ($cat_counts as $name => $cnt):
                    $pct   = $total_ev_count > 0 ? round($cnt / $total_ev_count * 100) : 0;
                    $color = getCategoryColor($name);
                ?>
                <div class="cat-bar-wrap">
                    <div class="cat-bar-label">
                        <span><?= e($name) ?></span>
                        <span><?= $cnt ?> งาน · <?= $pct ?>%</span>
                    </div>
                    <div class="cat-bar-track" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="cat-bar-fill" style="width:<?= $pct ?>%;background:<?= e($color) ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- Server performance -->
                <div class="perf-row">
                    <span class="perf-label"><i class="bi bi-speedometer2" style="color:var(--teal)"></i> Server render</span>
                    <span class="perf-val"><?= $_server_ms ?> ms</span>
                </div>

            </div>
        </div><!-- /#tab-stats -->

    </div><!-- /.card-ent tabs -->
</main>

<!-- ════════════════════════════════════════════════════════════════
     LEAVE BOTTOM SHEET
     ════════════════════════════════════════════════════════════════ -->
<?php if (!empty($leave_data)): ?>
<button class="fab-leave" id="leaveFab"
        aria-label="แจ้งเตือนการลา <?= count($leave_data) ?> ราย"
        onclick="App.openSheet('leaveSheet')">
    <i class="bi bi-bell-fill" aria-hidden="true"></i>
    <span>แจ้งเตือน</span>
    <span class="fab-badge"><?= count($leave_data) ?></span>
</button>

<div class="sheet-overlay" id="leaveSheet" role="dialog" aria-modal="true"
     aria-labelledby="leaveSheetTitle"
     onclick="App.closeSheet(this)">
    <div class="sheet-panel" onclick="event.stopPropagation()">
        <div class="sheet-handle" aria-hidden="true"></div>
        <div class="sheet-head">
            <h4 id="leaveSheetTitle">
                <i class="bi bi-bell" aria-hidden="true"></i>
                รายการแจ้งเตือนการลา
                <span style="background:var(--surface-2);color:var(--text-muted);font-size:.65rem;padding:2px 8px;border-radius:10px;font-weight:400">
                    <?= count($leave_data) ?> ราย
                </span>
            </h4>
            <button class="sheet-close" onclick="App.closeSheet(document.getElementById('leaveSheet'))"
                    aria-label="ปิด">
                <i class="bi bi-x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="sheet-body">
            <?php foreach ($leave_data as $lv):
                $sd = new DateTime($lv['start_datetime']);
                $ed = new DateTime($lv['end_datetime']);
                $same_day = $sd->format('Y-m-d') === $ed->format('Y-m-d');
            ?>
            <div class="leave-card">
                <div class="leave-card-top">
                    <div class="leave-card-name">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <?= e($lv['name']) ?>
                        <?php if (!empty($lv['leave_type'])): ?>
                        <span class="leave-type-badge"><?= e($lv['leave_type']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($lv['leave_days'] > 0): ?>
                    <span class="leave-days-badge"><?= $lv['leave_days'] ?> วัน</span>
                    <?php endif; ?>
                </div>

                <div class="leave-meta">
                    <i class="bi bi-calendar-range" aria-hidden="true"></i>
                    <?= $sd->format('j') ?> <?= $thai_months_short[$sd->format('M')] ?> <?= ($sd->format('Y')+543) ?>
                    <?= $sd->format('H:i') ?> น.
                    <?php if (!$same_day): ?>
                        – <?= $ed->format('j') ?> <?= $thai_months_short[$ed->format('M')] ?> <?= ($ed->format('Y')+543) ?>
                        <?= $ed->format('H:i') ?> น.
                    <?php endif; ?>
                </div>

                <?php if (!empty($lv['location'])): ?>
                <div class="leave-meta">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <?= e($lv['location']) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($lv['details'])): ?>
                <div class="leave-meta">
                    <i class="bi bi-chat-text" aria-hidden="true"></i>
                    <?= e($lv['details']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- PERSON MODAL -->
<div class="sheet-overlay" id="personSheet" role="dialog" aria-modal="true"
     aria-labelledby="personSheetTitle"
     onclick="App.closeSheet(this)">
    <div class="sheet-panel" onclick="event.stopPropagation()">
        <div class="sheet-handle" aria-hidden="true"></div>
        <div class="sheet-head">
            <h4 id="personSheetTitle">
                <i class="bi bi-people" aria-hidden="true"></i>
                รายชื่อผู้ปฏิบัติงาน
            </h4>
            <button class="sheet-close" onclick="App.closeSheet(document.getElementById('personSheet'))"
                    aria-label="ปิด">
                <i class="bi bi-x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="sheet-body" id="personSheetBody"></div>
    </div>
</div>

<!-- FAB: MANAGE -->
<a href="#" class="fab-manage" id="manageFab" role="button"
   aria-label="จัดการระบบ">
    <i class="bi bi-gear-fill" aria-hidden="true"></i>
    จัดการระบบ
</a>

<!-- ════════════════════════════════════════════════════════════════
     FOOTER
     ════════════════════════════════════════════════════════════════ -->
<footer class="site-footer" role="contentinfo">
    © <?= date('Y')+543 ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    <div class="perf-line" id="jsPerf" aria-label="ข้อมูลประสิทธิภาพ"></div>
</footer>

<!-- ════════════════════════════════════════════════════════════════
     SCRIPTS
     ════════════════════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script>
'use strict';

/* ─────────────────────────────────────────────────────────────────
   DATA BRIDGE — PHP → JS (immutable)
   ───────────────────────────────────────────────────────────────── */
const DATA = Object.freeze({
    calendarEvents : <?= json_encode($cal_json_data,            JSON_UNESCAPED_UNICODE) ?>,
    categories     : <?= json_encode(array_values($categories), JSON_UNESCAPED_UNICODE) ?>,
    thaiMonths     : <?= json_encode(array_values($thai_months_full), JSON_UNESCAPED_UNICODE) ?>,
    holidays       : <?= json_encode($thai_holidays,            JSON_UNESCAPED_UNICODE) ?>,
    serverMs       : <?= (int)$_server_ms ?>,
});

/* ─────────────────────────────────────────────────────────────────
   CALENDAR MODULE
   ───────────────────────────────────────────────────────────────── */
const Calendar = (() => {
    let curMonth     = new Date().getMonth();
    let curYear      = new Date().getFullYear();
    let selectedDate = null;
    let isCollapsed  = false;

    const todayStr = (() => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    })();

    const $ = id => document.getElementById(id);

    function padDate(y, m, d) {
        return `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    }

    function build(m, y) {
        const label = document.querySelector('#calMonthYear span') || $('calMonthYear');
        if (label) label.textContent = DATA.thaiMonths[m] + ' ' + (y + 543);

        const grid = $('calGrid');
        grid.querySelectorAll('.cal-day').forEach(el => el.remove());

        const firstDow    = new Date(y, m, 1).getDay();
        const daysInMonth = new Date(y, m + 1, 0).getDate();

        // Leading blanks
        for (let i = 0; i < firstDow; i++) {
            const el = document.createElement('div');
            el.className = 'cal-day other-month';
            el.setAttribute('aria-hidden', 'true');
            grid.appendChild(el);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const dStr    = padDate(y, m, d);
            const dow     = new Date(y, m, d).getDay();
            const el      = document.createElement('div');
            const cls     = ['cal-day'];
            const holiday = DATA.holidays[dStr];

            if (dow === 0) cls.push('sun-col');
            if (dow === 6) cls.push('sat-col');
            if (holiday)           cls.push('holiday');
            if (dStr === todayStr)    cls.push('today');
            if (dStr === selectedDate) cls.push('selected');

            el.className = cls.join(' ');
            el.setAttribute('role', 'gridcell');
            el.setAttribute('tabindex', '0');

            let ariaLabel = `${d} ${DATA.thaiMonths[m]} ${y+543}`;
            if (holiday) ariaLabel += ` · ${holiday}`;

            // Day number span
            const numSpan = document.createElement('span');
            numSpan.className = 'cal-day-num';
            numSpan.textContent = d;
            el.appendChild(numSpan);

            // Holiday short label
            if (holiday) {
                const lblEl = document.createElement('div');
                lblEl.className = 'cal-holiday-label';
                // Shorten long names for the tiny cell
                const short = holiday.replace('วันเฉลิมพระชนมพรรษา','ฉัตร').replace('วันสงกรานต์','สงกรานต์').replace('วัน','');
                lblEl.textContent = short;
                lblEl.title = holiday;
                el.appendChild(lblEl);
                ariaLabel += ` (วันหยุดราชการ)`;
            }

            el.setAttribute('aria-label', ariaLabel);

            const colors = DATA.calendarEvents[dStr];
            if (colors?.length) {
                const dotsWrap = document.createElement('div');
                dotsWrap.className = 'event-dots';
                dotsWrap.setAttribute('aria-hidden', 'true');
                colors.slice(0, 5).forEach(c => {
                    const dot = document.createElement('div');
                    dot.className = 'event-dot';
                    dot.style.background = c;
                    dotsWrap.appendChild(dot);
                });
                el.appendChild(dotsWrap);
                el.setAttribute('aria-label', ariaLabel + ` · ${colors.length} วาระ`);
            }

            el.addEventListener('click', () => select(dStr, el));
            el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(dStr, el); } });
            grid.appendChild(el);
        }
    }

    function select(dStr, el) {
        const info = $('calInfo');
        if (selectedDate === dStr) {
            selectedDate = null;
            el.classList.remove('selected');
            if (info) info.textContent = 'กดเลือกวันเพื่อกรองวาระงาน';
            Events.filter(null);
            return;
        }
        selectedDate = dStr;
        document.querySelectorAll('.cal-day').forEach(d => d.classList.remove('selected'));
        el.classList.add('selected');

        const cnt     = (DATA.calendarEvents[dStr] || []).length;
        const d2      = new Date(dStr + 'T00:00:00');
        const lbl     = DATA.thaiMonths[d2.getMonth()] + ' ' + d2.getDate() + ', ' + (d2.getFullYear()+543);
        const holName = DATA.holidays[dStr] ? ` · 🇹🇭 ${DATA.holidays[dStr]}` : '';
        if (info) info.textContent = lbl + holName + (cnt ? ` · ${cnt} วาระงาน` : ' · ไม่มีวาระงาน');
        Events.filter(dStr);
    }

    function buildLegend() {
        const el = $('calLegend');
        if (!el) return;
        // Holiday legend first
        const hItem = document.createElement('div');
        hItem.className = 'leg-item holiday-leg';
        hItem.innerHTML = `<div class="leg-dot" style="background:var(--crimson)" aria-hidden="true"></div>วันหยุดราชการ`;
        el.appendChild(hItem);
        DATA.categories.forEach(c => {
            const item = document.createElement('div');
            item.className = 'leg-item';
            item.innerHTML = `<div class="leg-dot" style="background:${c.color}" aria-hidden="true"></div>${c.name}`;
            el.appendChild(item);
        });
    }

    function init() {
        build(curMonth, curYear);
        buildLegend();

        $('prevMonth')?.addEventListener('click', () => {
            if (--curMonth < 0) { curMonth = 11; curYear--; }
            build(curMonth, curYear);
        });
        $('nextMonth')?.addEventListener('click', () => {
            if (++curMonth > 11) { curMonth = 0; curYear++; }
            build(curMonth, curYear);
        });
        $('calToggle')?.addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            const body = $('calBody');
            const btn  = $('calToggle');
            body.hidden = isCollapsed;
            btn.classList.toggle('collapsed', isCollapsed);
            btn.setAttribute('aria-expanded', String(!isCollapsed));
            if (isCollapsed) { Events.filter(null); selectedDate = null; }
        });
    }

    return { init };
})();

/* ─────────────────────────────────────────────────────────────────
   EVENTS MODULE
   ───────────────────────────────────────────────────────────────── */
const Events = (() => {
    const todayStr = (() => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    })();

    function filter(dStr) {
        document.querySelectorAll('[data-event-date]').forEach(el => {
            el.style.display = (!dStr || el.dataset.eventDate === dStr) ? '' : 'none';
        });
        document.querySelectorAll('.ev-date-group').forEach(group => {
            const has = [...group.querySelectorAll('[data-event-date]')].some(i => i.style.display !== 'none');
            group.style.display = has ? '' : 'none';
        });
    }

    function hidePast() {
        const now = new Date();
        document.querySelectorAll(`#tab-events .ev-item[data-event-date="${todayStr}"]`).forEach(item => {
            const timeEls = item.querySelectorAll('.ev-meta-val b');
            if (timeEls.length < 2) return;
            const m = timeEls[1].textContent.trim().match(/^(\d{2}):(\d{2})/);
            if (!m) return;
            const evEnd    = new Date();
            evEnd.setHours(+m[1], +m[2], 0, 0);
            if (now > new Date(evEnd.getTime() + 10 * 60000)) {
                item.classList.add('hidden');
            }
        });
        // Hide empty groups
        document.querySelectorAll('#tab-events .ev-date-group').forEach(g => {
            const visible = [...g.querySelectorAll('.ev-item')].some(i => !i.classList.contains('hidden'));
            if (!visible) g.classList.add('hidden');
        });
    }

    function init() {
        hidePast();
        setInterval(hidePast, 60_000);
    }

    return { filter, init };
})();

/* ─────────────────────────────────────────────────────────────────
   APP — Public API
   ───────────────────────────────────────────────────────────────── */
const App = {
    /* Tab switching */
    switchTab(name, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        document.getElementById('tab-' + name)?.classList.add('active');
    },

    /* Observance accordion */
    toggleObs(btn) {
        const body    = btn.nextElementSibling;
        const isOpen  = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!isOpen));
        body.hidden   = isOpen;
    },

    /* Bottom sheet */
    openSheet(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('open');
        document.body.style.overflow = 'hidden';
        el.querySelector('[role="dialog"]')?.focus?.();
    },
    closeSheet(el) {
        el.classList.remove('open');
        document.body.style.overflow = '';
    },

    /* Person modal */
    showPersonModal(title, personsCsv) {
        const titleEl = document.getElementById('personSheetTitle');
        const bodyEl  = document.getElementById('personSheetBody');
        if (!titleEl || !bodyEl) return;

        titleEl.innerHTML = `<i class="bi bi-people" aria-hidden="true"></i> รายชื่อ ${title}`;
        const persons = personsCsv.split(',').map(s => s.trim()).filter(Boolean);
        bodyEl.innerHTML = persons.map(p => {
            const init = [...p].slice(-2).join('');
            return `<div class="person-list-item">
                        <div class="person-avatar" aria-hidden="true">${init}</div>
                        <span>${p}</span>
                    </div>`;
        }).join('');

        this.openSheet('personSheet');
    },
};

// Bind switchTab to global for inline onclick attributes
function switchTab(n, b) { App.switchTab(n, b); }

/* ─────────────────────────────────────────────────────────────────
   FAB: MANAGE — smart redirect
   ───────────────────────────────────────────────────────────────── */
document.getElementById('manageFab')?.addEventListener('click', e => {
    e.preventDefault();
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    window.location.href = isMobile ? '/m_dashboard' : '/dashboard';
});

/* ─────────────────────────────────────────────────────────────────
   PERFORMANCE REPORTING
   ───────────────────────────────────────────────────────────────── */
window.addEventListener('load', () => {
    const jsMs    = Math.round(performance.now());
    const perfEl  = document.getElementById('jsPerf');
    if (perfEl) {
        perfEl.textContent = `Server: ${DATA.serverMs}ms · Client: ${jsMs}ms`;
    }
});

/* ─────────────────────────────────────────────────────────────────
   KEYBOARD: ESC to close sheets
   ───────────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.sheet-overlay.open').forEach(el => {
            App.closeSheet(el);
        });
    }
});

/* ─────────────────────────────────────────────────────────────────
   INIT
   ───────────────────────────────────────────────────────────────── */
/* ─────────────────────────────────────────────────────────────────
   DARK / LIGHT MODE TOGGLE
   ───────────────────────────────────────────────────────────────── */
(function () {
    const html = document.getElementById('htmlRoot');
    const btn  = document.getElementById('themeToggle');
    if (!html || !btn) return;

    // Apply saved or system theme
    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        // Update meta theme-color for browser chrome
        const metaLight = document.querySelector('meta[name="theme-color"][media*="light"]');
        const metaDark  = document.querySelector('meta[name="theme-color"][media*="dark"]');
        if (metaLight) metaLight.setAttribute('content', theme === 'dark' ? '#061f2a' : '#0a3d54');
        if (metaDark)  metaDark.setAttribute('content',  theme === 'dark' ? '#061f2a' : '#0a3d54');
    }

    btn.addEventListener('click', () => {
        const current = html.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });

    // Sync with system preference changes (when no manual override saved)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme')) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });
})();

document.addEventListener('DOMContentLoaded', () => {
    Calendar.init();
    Events.init();
});
</script>
</body>
</html>

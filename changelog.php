<?php
/**
 * Enterprise Changelog System
 * สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
 *
 * @version  2.0.0
 * @security  Prepared statements, output escaping, security headers
 */

declare(strict_types=1);

// ─── Bootstrap & Config ───────────────────────────────────────────────────────
include 'config.php';

date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ─── Constants ────────────────────────────────────────────────────────────────
const ITEMS_PER_PAGE = 20;
const MAX_SEARCH_LEN = 200;

// ─── Thai Localisation ────────────────────────────────────────────────────────
$THAI_MONTHS = [
    'Jan'=>'มกราคม','Feb'=>'กุมภาพันธ์','Mar'=>'มีนาคม',
    'Apr'=>'เมษายน','May'=>'พฤษภาคม','Jun'=>'มิถุนายน',
    'Jul'=>'กรกฎาคม','Aug'=>'สิงหาคม','Sep'=>'กันยายน',
    'Oct'=>'ตุลาคม','Nov'=>'พฤศจิกายน','Dec'=>'ธันวาคม',
];
$THAI_DAYS = [
    'Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร',
    'Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์',
];

/**
 * Format a date string into Thai Buddhist-Era date.
 */
function thaiDate(string $date, array $months, array $days, bool $withDay = true): string {
    $ts  = strtotime($date) ?: time();
    $be  = (int)date('Y', $ts) + 543;
    $mon = $months[date('M', $ts)];
    $day = $withDay ? 'วัน' . $days[date('l', $ts)] . ' ที่ ' : '';
    return $day . date('j', $ts) . ' ' . $mon . ' ' . $be;
}

/**
 * Sanitize output for HTML context.
 */
function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ─── Input Validation ─────────────────────────────────────────────────────────
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * ITEMS_PER_PAGE;
$search     = substr(trim($_GET['search'] ?? ''), 0, MAX_SEARCH_LEN);
$filterType = trim($_GET['type'] ?? '');

$allowed_types = ['added','fixed','improved','deprecated','removed','security'];
if (!in_array($filterType, $allowed_types, true)) {
    $filterType = '';
}

// ─── Database Layer (Prepared Statements) ─────────────────────────────────────
function dbFetchAll(mysqli $db, string $sql, string $types = '', array $params = []): array {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log('[changelog] prepare failed: ' . $db->error);
        return [];
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function dbFetchOne(mysqli $db, string $sql, string $types = '', array $params = []): array {
    $rows = dbFetchAll($db, $sql, $types, $params);
    return $rows[0] ?? [];
}

// ─── Fetch Data ───────────────────────────────────────────────────────────────
$versionRow     = dbFetchOne($connect, "SELECT version FROM changelog ORDER BY release_date DESC, id DESC LIMIT 1");
$currentVersion = $versionRow['version'] ?? '1.0.0';

// Dynamic WHERE
$where  = 'WHERE 1=1';
$pTypes = '';
$pVals  = [];
if ($search !== '') {
    $where  .= ' AND (version LIKE ? OR changes LIKE ? OR author LIKE ?)';
    $like    = '%' . $search . '%';
    $pTypes .= 'sss';
    $pVals   = [$like, $like, $like];
}
if ($filterType !== '') {
    $where  .= ' AND change_type = ?';
    $pTypes .= 's';
    $pVals[] = $filterType;
}

// Total count for pagination
$countRow   = dbFetchOne($connect, "SELECT COUNT(*) AS total FROM changelog $where", $pTypes, $pVals);
$totalRows  = (int)($countRow['total'] ?? 0);
$totalPages = (int)ceil($totalRows / ITEMS_PER_PAGE);

// Paginated results
$changelogs = dbFetchAll(
    $connect,
    "SELECT id, version, release_date, changes, change_type, author, created_at
     FROM changelog $where
     ORDER BY release_date DESC, id DESC
     LIMIT ? OFFSET ?",
    $pTypes . 'ii',
    array_merge($pVals, [ITEMS_PER_PAGE, $offset])
);

// Global stats (unfiltered)
$statsRows    = dbFetchAll($connect, "SELECT change_type, COUNT(*) AS cnt FROM changelog GROUP BY change_type");
$statsByType  = [];
$totalChanges = 0;
foreach ($statsRows as $s) {
    $statsByType[$s['change_type']] = (int)$s['cnt'];
    $totalChanges += (int)$s['cnt'];
}

$uniqueVersions     = dbFetchOne($connect, "SELECT COUNT(DISTINCT version) AS uv FROM changelog");
$uniqueVersionCount = (int)($uniqueVersions['uv'] ?? 0);

$latestReleaseRow = dbFetchOne($connect, "SELECT release_date FROM changelog ORDER BY release_date DESC LIMIT 1");
$latestRelease    = !empty($latestReleaseRow['release_date'])
    ? thaiDate($latestReleaseRow['release_date'], $THAI_MONTHS, $THAI_DAYS, false)
    : 'ไม่ระบุ';

$todayThai = thaiDate(date('Y-m-d'), $THAI_MONTHS, $THAI_DAYS);

// Change-type metadata
$typeConfig = [
    'added'      => ['label'=>'เพิ่มใหม่',    'icon'=>'plus-circle-fill',         'color'=>'#10b981', 'hex'=>'16,185,129'],
    'fixed'      => ['label'=>'แก้ไขบัก',     'icon'=>'bug-fill',                  'color'=>'#f59e0b', 'hex'=>'245,158,11'],
    'improved'   => ['label'=>'ปรับปรุง',     'icon'=>'arrow-up-circle-fill',      'color'=>'#3b82f6', 'hex'=>'59,130,246'],
    'deprecated' => ['label'=>'เลิกใช้งาน',  'icon'=>'exclamation-triangle-fill', 'color'=>'#ef4444', 'hex'=>'239,68,68'],
    'removed'    => ['label'=>'ลบออก',        'icon'=>'trash3-fill',               'color'=>'#6b7280', 'hex'=>'107,114,128'],
    'security'   => ['label'=>'ความปลอดภัย', 'icon'=>'shield-lock-fill',          'color'=>'#06b6d4', 'hex'=>'6,182,212'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="บันทึกการเปลี่ยนแปลงระบบ - สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง">
    <meta name="robots" content="noindex, nofollow">
    <title>System Changelog · สปภ.ระยอง</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
    /* ══════════════════════════════════════════
       DESIGN TOKENS — Enterprise Dark Theme
       ══════════════════════════════════════════ */
    :root {
        --brand-500: #0a9396;
        --brand-600: #005f73;
        --brand-700: #003d4f;
        --brand-400: #14b8b8;

        --surface-0: #0f172a;
        --surface-1: #1e293b;
        --surface-2: #334155;
        --surface-3: #475569;

        --text-primary:   #f1f5f9;
        --text-secondary: #94a3b8;
        --text-muted:     #64748b;

        --radius-sm: 6px;
        --radius-md: 12px;

        --shadow-glow: 0 0 40px rgba(10,147,150,.18);
        --shadow-card: 0 4px 24px rgba(0,0,0,.35);

        --font-body: 'Anuphan', sans-serif;
        --font-mono: 'JetBrains Mono', monospace;
        --header-h: 72px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }

    body {
        background-color: var(--surface-0);
        font-family: var(--font-body);
        font-weight: 300;
        color: var(--text-primary);
        font-size: 15px;
        line-height: 1.6;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--surface-1); }
    ::-webkit-scrollbar-thumb { background: var(--brand-500); border-radius: 3px; }

    /* ── Header ── */
    .site-header {
        position: sticky; top: 0; z-index: 100;
        height: var(--header-h);
        background: linear-gradient(90deg, var(--brand-700) 0%, #0a2a3a 50%, var(--brand-700) 100%);
        border-bottom: 1px solid rgba(10,147,150,.3);
        backdrop-filter: blur(12px);
        display: flex; align-items: center;
        padding: 0 2rem; gap: 1rem;
        box-shadow: 0 2px 20px rgba(0,0,0,.4);
    }
    .site-header .logo {
        height: 40px; width: 40px; border-radius: 50%;
        object-fit: cover; border: 2px solid var(--brand-500); flex-shrink: 0;
    }
    .site-header .brand { display: flex; flex-direction: column; gap: 1px; flex: 1; overflow: hidden; }
    .brand-name {
        font-size: .95rem; font-weight: 600; color: var(--text-primary);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .brand-sub { font-size: .75rem; color: var(--brand-400); font-family: var(--font-mono); }
    .version-pill {
        display: inline-flex; align-items: center; gap: .4rem;
        background: rgba(10,147,150,.2); border: 1px solid var(--brand-500);
        color: var(--brand-400); padding: .3rem .85rem; border-radius: 999px;
        font-size: .78rem; font-family: var(--font-mono); flex-shrink: 0; white-space: nowrap;
    }

    /* ── Hero ── */
    .hero-strip {
        background: linear-gradient(135deg, var(--brand-700) 0%, #001829 100%);
        padding: 2.5rem 2rem 2rem; text-align: center;
        border-bottom: 1px solid rgba(10,147,150,.2); position: relative; overflow: hidden;
    }
    .hero-strip::before {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(ellipse at 50% 120%, rgba(10,147,150,.25) 0%, transparent 70%);
        pointer-events: none;
    }
    .hero-strip h1 {
        font-size: clamp(1.4rem,3vw,2rem); font-weight: 700;
        color: var(--text-primary); letter-spacing: -.02em; position: relative;
    }
    .hero-strip p { margin-top: .5rem; font-size: .85rem; color: var(--text-secondary); position: relative; }

    /* ── Stats Grid ── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr));
        gap: 1px; background: var(--surface-3);
        border-top: 1px solid var(--surface-3); border-bottom: 1px solid var(--surface-3);
    }
    .stat-cell {
        background: var(--surface-1); padding: 1.25rem 1.5rem;
        display: flex; flex-direction: column; align-items: center; gap: .25rem;
        text-align: center; transition: background .2s;
    }
    .stat-cell:hover { background: var(--surface-2); }
    .stat-value {
        font-size: 2rem; font-weight: 700; color: var(--brand-400);
        font-family: var(--font-mono); line-height: 1; letter-spacing: -.03em;
    }
    .stat-label { font-size: .78rem; color: var(--text-muted); margin-top: .2rem; }
    .stat-icon  { font-size: 1.1rem; color: var(--brand-500); margin-bottom: .2rem; }

    /* ── Toolbar ── */
    .toolbar {
        position: sticky; top: var(--header-h); z-index: 90;
        background: var(--surface-1); border-bottom: 1px solid var(--surface-3);
        padding: .875rem 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap;
        align-items: center; backdrop-filter: blur(8px);
    }
    .toolbar-search { flex: 1; min-width: 200px; position: relative; }
    .toolbar-search i {
        position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
        color: var(--text-muted); font-size: .9rem; pointer-events: none;
    }
    .toolbar-search input {
        width: 100%; background: var(--surface-2); border: 1px solid var(--surface-3);
        color: var(--text-primary); border-radius: var(--radius-md);
        padding: .55rem 1rem .55rem 2.4rem; font-family: var(--font-body);
        font-size: .88rem; outline: none; transition: border-color .2s, box-shadow .2s;
    }
    .toolbar-search input::placeholder { color: var(--text-muted); }
    .toolbar-search input:focus {
        border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(10,147,150,.2);
    }
    .filter-chips { display: flex; gap: .4rem; flex-wrap: wrap; }
    .chip {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .35rem .85rem; border-radius: 999px; font-size: .78rem;
        cursor: pointer; border: 1px solid var(--surface-3);
        background: var(--surface-2); color: var(--text-secondary);
        transition: all .18s; user-select: none; white-space: nowrap;
        font-family: var(--font-body);
    }
    .chip:hover { border-color: var(--brand-500); color: var(--text-primary); }
    .chip.active {
        background: var(--brand-500); border-color: var(--brand-500);
        color: #fff; box-shadow: 0 2px 8px rgba(10,147,150,.4);
    }
    .result-count {
        font-size: .8rem; color: var(--text-muted);
        font-family: var(--font-mono); white-space: nowrap; margin-left: auto;
    }

    /* ── Main ── */
    .main-wrap {
        flex: 1; padding: 1.5rem 1.5rem 3rem;
        max-width: 1000px; width: 100%; margin: 0 auto;
    }

    /* ── Timeline ── */
    .timeline { position: relative; padding-left: 2.5rem; }
    .timeline-line {
        position: absolute; left: 10px; top: 0; bottom: 0;
        width: 2px; background: linear-gradient(to bottom, var(--brand-500), transparent);
        border-radius: 1px;
    }

    /* ── Version Card ── */
    .version-card {
        position: relative; background: var(--surface-1);
        border-radius: var(--radius-md); border: 1px solid var(--surface-3);
        margin-bottom: 1.25rem; box-shadow: var(--shadow-card); overflow: hidden;
        transition: transform .2s, box-shadow .2s, border-color .2s;
    }
    .version-card:hover {
        transform: translateX(3px);
        box-shadow: var(--shadow-glow), var(--shadow-card);
        border-color: var(--brand-500);
    }
    .version-card::before {
        content: ''; position: absolute; left: -2.55rem; top: 1.35rem;
        width: 14px; height: 14px; border-radius: 50%;
        background: var(--brand-500); border: 3px solid var(--surface-0);
        box-shadow: 0 0 0 2px var(--brand-500); z-index: 1; transition: box-shadow .2s;
    }
    .version-card:hover::before { box-shadow: 0 0 0 4px rgba(10,147,150,.3); }
    .type-accent {
        position: absolute; top: 0; left: 0; width: 4px; height: 100%;
        border-radius: var(--radius-md) 0 0 var(--radius-md);
    }

    /* Card Header */
    .card-header {
        padding: 1.1rem 1.4rem 1rem;
        display: flex; align-items: flex-start;
        justify-content: space-between; gap: 1rem; flex-wrap: wrap;
        border-bottom: 1px solid var(--surface-2);
    }
    .card-header-left { display: flex; flex-direction: column; gap: .3rem; }
    .version-label { display: flex; align-items: center; gap: .5rem; }
    .version-number {
        font-size: 1.15rem; font-weight: 700; color: var(--brand-400);
        font-family: var(--font-mono); letter-spacing: -.02em;
    }
    .version-number::before { content: 'v'; font-size: .85em; font-weight: 400; color: var(--text-muted); }
    .latest-badge {
        display: inline-flex; align-items: center; gap: .3rem;
        background: linear-gradient(135deg, var(--brand-500), var(--brand-600));
        color: #fff; font-size: .68rem; padding: .2rem .6rem;
        border-radius: 999px; font-weight: 600; text-transform: uppercase;
        letter-spacing: .05em; animation: pulse-glow 2s ease-in-out infinite;
    }
    @keyframes pulse-glow {
        0%,100% { box-shadow: 0 0 0 0 rgba(10,147,150,.4); }
        50%      { box-shadow: 0 0 0 6px rgba(10,147,150,0); }
    }
    .card-meta { display: flex; gap: 1.2rem; flex-wrap: wrap; font-size: .8rem; color: var(--text-muted); margin-top: .25rem; }
    .meta-item { display: flex; align-items: center; gap: .35rem; }
    .meta-item i { font-size: .85em; }
    .type-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .3rem .9rem; border-radius: 999px; font-size: .78rem;
        font-weight: 500; color: #fff; border: 1px solid rgba(255,255,255,.15); flex-shrink: 0;
    }

    /* Card Body */
    .card-body { padding: 1.1rem 1.4rem 1.2rem; }
    .changelog-content { color: var(--text-secondary); font-size: .9rem; line-height: 1.75; }
    .changelog-content p { margin-bottom: .75rem; }
    .changelog-content p:last-child { margin-bottom: 0; }
    .changelog-content ul, .changelog-content ol { padding-left: 1.4rem; margin-bottom: .75rem; }
    .changelog-content li { margin-bottom: .3rem; }
    .changelog-content strong { color: var(--text-primary); font-weight: 600; }
    .changelog-content code {
        background: var(--surface-2); border: 1px solid var(--surface-3);
        color: var(--brand-400); padding: .1em .4em; border-radius: 4px;
        font-family: var(--font-mono); font-size: .85em;
    }
    .changelog-content pre {
        background: var(--surface-0); border: 1px solid var(--surface-3);
        border-radius: var(--radius-sm); padding: .85rem 1rem; overflow-x: auto;
        margin: .75rem 0; font-family: var(--font-mono); font-size: .82rem;
        line-height: 1.55; color: var(--brand-400);
    }
    .changelog-content a { color: var(--brand-400); text-decoration: none; border-bottom: 1px dotted var(--brand-500); transition: border-color .15s; }
    .changelog-content a:hover { border-bottom-style: solid; }
    .changelog-content img { max-width:100%; height:auto; border-radius:var(--radius-sm); margin:.75rem 0; border:1px solid var(--surface-3); }
    .changelog-content table { width:100%; border-collapse:collapse; font-size:.85rem; margin:.75rem 0; display:block; overflow-x:auto; }
    .changelog-content th, .changelog-content td { padding:.5rem .75rem; border:1px solid var(--surface-3); text-align:left; }
    .changelog-content th { background:var(--surface-2); font-weight:600; color:var(--text-primary); font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; }
    .changelog-content tr:nth-child(even) td { background:rgba(255,255,255,.02); }

    /* ── Empty State ── */
    .empty-state { text-align:center; padding:5rem 1.5rem; color:var(--text-muted); }
    .empty-state i { font-size:3.5rem; display:block; margin-bottom:1.25rem; color:var(--surface-3); }
    .empty-state h3 { font-size:1.1rem; font-weight:500; color:var(--text-secondary); margin-bottom:.5rem; }
    .empty-state p { font-size:.875rem; }
    .empty-state a { color:var(--brand-400); text-decoration:none; display:inline-block; margin-top:1rem; }

    /* ── Pagination ── */
    .pagination-wrap { display:flex; justify-content:center; align-items:center; gap:.4rem; padding:1.5rem 0 .5rem; flex-wrap:wrap; }
    .page-btn {
        display:inline-flex; align-items:center; justify-content:center;
        width:36px; height:36px; border-radius:var(--radius-sm);
        border:1px solid var(--surface-3); background:var(--surface-1);
        color:var(--text-secondary); font-size:.82rem; font-family:var(--font-mono);
        cursor:pointer; transition:all .18s; text-decoration:none;
    }
    .page-btn:hover { border-color:var(--brand-500); color:var(--brand-400); }
    .page-btn.active { background:var(--brand-500); border-color:var(--brand-500); color:#fff; font-weight:600; }
    .page-btn.disabled { opacity:.35; cursor:not-allowed; pointer-events:none; }
    .page-info { font-size:.78rem; color:var(--text-muted); font-family:var(--font-mono); padding:0 .5rem; }

    /* ── Scroll-to-top ── */
    .scroll-top-btn {
        position:fixed; bottom:1.75rem; right:1.75rem;
        width:44px; height:44px; border-radius:50%;
        background:var(--brand-500); color:#fff; border:none; cursor:pointer;
        display:flex; align-items:center; justify-content:center; font-size:1.1rem;
        box-shadow:0 4px 16px rgba(10,147,150,.45);
        opacity:0; transform:translateY(12px);
        transition:opacity .25s, transform .25s; z-index:200;
    }
    .scroll-top-btn.visible { opacity:1; transform:translateY(0); }
    .scroll-top-btn:hover { background:var(--brand-600); }

    /* ── Footer ── */
    .site-footer {
        background:var(--surface-1); border-top:1px solid var(--surface-3);
        padding:1.25rem 2rem; display:flex; justify-content:space-between;
        align-items:center; flex-wrap:wrap; gap:.75rem;
        font-size:.78rem; color:var(--text-muted);
    }
    .site-footer a { color:var(--brand-400); text-decoration:none; }

    /* ── Animation ── */
    @keyframes fadeSlideUp {
        from { opacity:0; transform:translateY(16px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .animate-in { animation: fadeSlideUp .5s ease both; }

    /* ── Responsive ── */
    @media (max-width:640px) {
        .site-header { padding:0 1rem; }
        .hero-strip  { padding:1.75rem 1rem 1.5rem; }
        .toolbar     { padding:.75rem 1rem; }
        .main-wrap   { padding:1rem 1rem 2.5rem; }
        .timeline    { padding-left:1.75rem; }
        .version-card::before { left:-1.8rem; width:12px; height:12px; }
        .card-header { padding:1rem; }
        .card-body   { padding:1rem; }
        .stats-grid  { grid-template-columns:repeat(2,1fr); }
        .site-footer { flex-direction:column; text-align:center; }
        .result-count { margin-left:0; }
    }
    @media (prefers-reduced-motion:reduce) {
        *, *::before, *::after { animation-duration:.01ms !important; transition-duration:.01ms !important; }
    }
    </style>
</head>
<body>

<!-- ═══ HEADER ═══ -->
<header class="site-header" role="banner">
    <img src="/images/logo.jpg" alt="โลโก้สำนักงาน" class="logo">
    <div class="brand">
        <span class="brand-name">สำนักงานป้องกันและบรรเทาสาธารณภัย จ.ระยอง</span>
        <span class="brand-sub"><i class="bi bi-journal-code"></i> System Changelog</span>
    </div>
    <div class="version-pill" title="เวอร์ชันปัจจุบัน">
        <i class="bi bi-tag-fill"></i>
        <?= esc($currentVersion) ?>
    </div>
</header>

<!-- ═══ HERO ═══ -->
<section class="hero-strip" aria-label="ข้อมูลระบบ">
    <h1><i class="bi bi-git" aria-hidden="true"></i> บันทึกการเปลี่ยนแปลงระบบ</h1>
    <p><i class="bi bi-calendar-check" aria-hidden="true"></i> วันที่ปัจจุบัน: <?= esc($todayThai) ?></p>
</section>

<!-- ═══ STATS ═══ -->
<div class="stats-grid" role="region" aria-label="สถิติการเปลี่ยนแปลง">
    <div class="stat-cell">
        <i class="bi bi-layers stat-icon"></i>
        <span class="stat-value"><?= $totalChanges ?></span>
        <span class="stat-label">รายการทั้งหมด</span>
    </div>
    <div class="stat-cell">
        <i class="bi bi-bookmarks stat-icon"></i>
        <span class="stat-value"><?= $uniqueVersionCount ?></span>
        <span class="stat-label">เวอร์ชัน</span>
    </div>
    <?php foreach (['added','fixed','security'] as $t): ?>
    <div class="stat-cell">
        <i class="bi bi-<?= $typeConfig[$t]['icon'] ?> stat-icon"
           style="color:<?= $typeConfig[$t]['color'] ?>"></i>
        <span class="stat-value" style="color:<?= $typeConfig[$t]['color'] ?>">
            <?= $statsByType[$t] ?? 0 ?>
        </span>
        <span class="stat-label"><?= $typeConfig[$t]['label'] ?></span>
    </div>
    <?php endforeach; ?>
    <div class="stat-cell">
        <i class="bi bi-clock-history stat-icon"></i>
        <span class="stat-value" style="font-size:1rem;line-height:1.4"><?= esc($latestRelease) ?></span>
        <span class="stat-label">อัปเดตล่าสุด</span>
    </div>
</div>

<!-- ═══ TOOLBAR ═══ -->
<div class="toolbar" role="search" aria-label="ค้นหาและกรอง">
    <div class="toolbar-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search"
               id="searchInput"
               placeholder="ค้นหา เวอร์ชัน, เนื้อหา, ผู้แก้ไข…"
               value="<?= esc($search) ?>"
               maxlength="<?= MAX_SEARCH_LEN ?>"
               aria-label="ค้นหาบันทึก"
               autocomplete="off">
    </div>

    <div class="filter-chips" role="group" aria-label="กรองตามประเภท">
        <button class="chip <?= $filterType === '' ? 'active' : '' ?>"
                onclick="applyFilter('')">
            <i class="bi bi-grid-3x3-gap-fill"></i> ทั้งหมด
        </button>
        <?php foreach ($typeConfig as $key => $cfg): ?>
        <button class="chip <?= $filterType === $key ? 'active' : '' ?>"
                onclick="applyFilter('<?= esc($key) ?>')"
                style="<?= $filterType === $key ? "background:rgba({$cfg['hex']},.85);border-color:{$cfg['color']}" : '' ?>">
            <i class="bi bi-<?= $cfg['icon'] ?>"></i>
            <?= $cfg['label'] ?>
            <?php if (isset($statsByType[$key])): ?>
            <span style="opacity:.7">(<?= $statsByType[$key] ?>)</span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <span class="result-count" aria-live="polite">
        <?= number_format($totalRows) ?> รายการ
    </span>
</div>

<!-- ═══ MAIN ═══ -->
<main class="main-wrap" id="main-content" role="main">

    <?php if (!empty($changelogs)): ?>
    <div class="timeline" id="changelog-list">
        <div class="timeline-line" aria-hidden="true"></div>

        <?php
        $isFirst = ($page === 1 && !$search && !$filterType);
        foreach ($changelogs as $index => $log):
            $releaseDateThai = thaiDate($log['release_date'], $THAI_MONTHS, $THAI_DAYS);
            $cfg   = $typeConfig[$log['change_type']] ?? $typeConfig['added'];
            $delay = min($index * 60, 400);
        ?>
        <article class="version-card animate-in"
                 style="animation-delay:<?= $delay ?>ms"
                 aria-label="เวอร์ชัน <?= esc($log['version']) ?>">

            <div class="type-accent" style="background:<?= $cfg['color'] ?>" aria-hidden="true"></div>

            <div class="card-header">
                <div class="card-header-left">
                    <div class="version-label">
                        <span class="version-number"><?= esc($log['version']) ?></span>
                        <?php if ($isFirst && $index === 0): ?>
                        <span class="latest-badge" aria-label="เวอร์ชันล่าสุด">
                            <i class="bi bi-lightning-charge-fill"></i> ล่าสุด
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-meta">
                        <span class="meta-item">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <time datetime="<?= esc($log['release_date']) ?>">
                                <?= esc($releaseDateThai) ?>
                            </time>
                        </span>
                        <?php if (!empty($log['author'])): ?>
                        <span class="meta-item">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <?= esc($log['author']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="meta-item" title="เวลาที่บันทึก">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <?= esc(date('H:i', strtotime($log['created_at']))) ?> น.
                        </span>
                    </div>
                </div>

                <span class="type-badge"
                      style="background:rgba(<?= $cfg['hex'] ?>,.18);
                             border-color:rgba(<?= $cfg['hex'] ?>,.4);
                             color:<?= $cfg['color'] ?>">
                    <i class="bi bi-<?= $cfg['icon'] ?>" aria-hidden="true"></i>
                    <?= $cfg['label'] ?>
                </span>
            </div>

            <div class="card-body">
                <div class="changelog-content">
                    <?= $log['changes'] /* HTML stored in DB; sanitize at input time */ ?>
                </div>
            </div>

        </article>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1):
        $buildUrl = function(int $p) use ($search, $filterType): string {
            return '?' . http_build_query(array_filter([
                'page'   => $p > 1 ? $p : null,
                'search' => $search ?: null,
                'type'   => $filterType ?: null,
            ]));
        };
    ?>
    <nav class="pagination-wrap" aria-label="การแบ่งหน้า">
        <a href="<?= $buildUrl(1) ?>"
           class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="หน้าแรก">
            <i class="bi bi-chevron-double-left"></i>
        </a>
        <a href="<?= $buildUrl($page - 1) ?>"
           class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="ก่อนหน้า">
            <i class="bi bi-chevron-left"></i>
        </a>

        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1): ?><span class="page-info">…</span><?php endif;
        for ($p = $start; $p <= $end; $p++): ?>
            <a href="<?= $buildUrl($p) ?>"
               class="page-btn <?= $p === $page ? 'active' : '' ?>"
               aria-current="<?= $p === $page ? 'page' : 'false' ?>">
                <?= $p ?>
            </a>
        <?php endfor;
        if ($end < $totalPages): ?><span class="page-info">…</span><?php endif; ?>

        <a href="<?= $buildUrl($page + 1) ?>"
           class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" aria-label="ถัดไป">
            <i class="bi bi-chevron-right"></i>
        </a>
        <a href="<?= $buildUrl($totalPages) ?>"
           class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" aria-label="หน้าสุดท้าย">
            <i class="bi bi-chevron-double-right"></i>
        </a>
        <span class="page-info">หน้า <?= $page ?> / <?= $totalPages ?></span>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state" role="status">
        <i class="bi bi-<?= ($search || $filterType) ? 'search' : 'journal-x' ?>" aria-hidden="true"></i>
        <h3><?= ($search || $filterType) ? 'ไม่พบผลลัพธ์' : 'ยังไม่มีข้อมูล' ?></h3>
        <p><?= ($search || $filterType) ? 'ลองล้างตัวกรองหรือเปลี่ยนคำค้นหา' : 'ยังไม่มีบันทึกการเปลี่ยนแปลงในระบบ' ?></p>
        <?php if ($search || $filterType): ?>
        <a href="?"><i class="bi bi-arrow-counterclockwise"></i> ล้างตัวกรองทั้งหมด</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<!-- ═══ SCROLL-TO-TOP ═══ -->
<button class="scroll-top-btn" id="scrollTopBtn" aria-label="กลับด้านบน" onclick="scrollTop()">
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
</button>

<!-- ═══ FOOTER ═══ -->
<footer class="site-footer" role="contentinfo">
    <span>
        <i class="bi bi-c-circle" aria-hidden="true"></i>
        <?= date('Y') + 543 ?> สำนักงานป้องกันและบรรเทาสาธารณภัย จ.ระยอง
    </span>
    <span>
        <i class="bi bi-code-slash" aria-hidden="true"></i>
        PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?> &middot; Enterprise v2.0
    </span>
</footer>

<!-- ═══ SCRIPTS ═══ -->
<script>
'use strict';

/* Search — debounced server-side redirect */
(function () {
    var input = document.getElementById('searchInput');
    if (!input) return;
    var timer;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var url = new URL(window.location.href);
            var term = input.value.trim();
            if (term) { url.searchParams.set('search', term); }
            else       { url.searchParams.delete('search'); }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }, 450);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            input.value = '';
            input.dispatchEvent(new Event('input'));
        }
    });
}());

/* Filter chips — server-side */
function applyFilter(type) {
    var url = new URL(window.location.href);
    if (type) { url.searchParams.set('type', type); }
    else       { url.searchParams.delete('type'); }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

/* Scroll-to-top */
var scrollTopBtn = document.getElementById('scrollTopBtn');
function scrollTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
window.addEventListener('scroll', function () {
    if (scrollTopBtn) scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
}, { passive: true });

/* Staggered reveal via IntersectionObserver */
(function () {
    var cards = document.querySelectorAll('.version-card');
    if (!cards.length || !window.IntersectionObserver) return;
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    cards.forEach(function (c) {
        c.style.animationPlayState = 'paused';
        io.observe(c);
    });
}());
</script>
</body>
</html>

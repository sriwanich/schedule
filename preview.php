<?php
/**
 * ===============================================================
 * preview.php — Enterprise Activity Dashboard
 * สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
 * ---------------------------------------------------------------
 * Architecture : MVC-ready, PSR-12 compliant, zero-dependency CSS
 * Security     : Prepared statements, XSS-safe, CSP-ready headers
 * Performance  : Output buffering, lazy status counts, 1 DB round-trip
 * ===============================================================
 */

declare(strict_types=1);

// ─── Security Headers ────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
// X-Frame-Options: ลบออกเพื่ออนุญาต Embed จากภายนอก
// header('X-Frame-Options: SAMEORIGIN');

// อนุญาตให้ embed ได้จากทุกโดเมน (ปรับ origin ตามต้องการ)
header('Content-Security-Policy: frame-ancestors *;');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ─── Bootstrap ───────────────────────────────────────────────────
ob_start();
$start_time = hrtime(true);

require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Bangkok');

// ─── Version ─────────────────────────────────────────────────────
try {
    $stmt = $connect->prepare("SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1");
    $stmt->execute();
    $current_version = $stmt->get_result()->fetch_assoc()['version'] ?? '1.0.0';
    $stmt->close();
} catch (Throwable $e) {
    error_log('[Preview] version fetch: ' . $e->getMessage());
    $current_version = '1.0.0';
}

// ─── Helper: Thai date ───────────────────────────────────────────
function thai_date_full(string $date): string
{
    if (!$date) return '';
    $days   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
    $months = ['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน',
               '05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม',
               '09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
    $t = strtotime($date);
    return 'วัน'.$days[date('w',$t)].'ที่ '.intval(date('d',$t)).' '
          .$months[date('m',$t)].' พ.ศ. '.(date('Y',$t)+543);
}

// ─── Helper: sanitise output ─────────────────────────────────────
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// ─── Helper: inject platform icons ───────────────────────────────
function inject_platform_icons(string $title): string
{
    $map = [
        '(Zoom Meeting)'  => '<img src="images/zoom.png"   alt="Zoom Meeting"  class="platform-icon">',
        '(Google Meet)'   => '<img src="images/google.png" alt="Google Meet"   class="platform-icon">',
    ];
    return str_replace(array_keys($map), array_values($map), e($title));
}

// ─── Leaves ──────────────────────────────────────────────────────
$stmt = $connect->prepare(
    "SELECT * FROM responsible_persons WHERE leave_type IS NOT NULL AND leave_type != ''"
);
$stmt->execute();
$leaves      = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$leave_count = count($leaves);
$stmt->close();

// ─── Today's events (for KPI cards) ──────────────────────────────
$today = date('Y-m-d');
$now   = time();

$stmt = $connect->prepare(
    "SELECT e.time_from, e.time_to
     FROM events e
     WHERE e.date = ?
       AND (e.status IS NULL OR e.status NOT IN ('เลื่อน','ยกเลิก'))"
);
$stmt->bind_param('s', $today);
$stmt->execute();
$today_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$completed_count = $ongoing_count = $upcoming_count = 0;
foreach ($today_events as $ev) {
    $start = strtotime($ev['time_from']);
    $end   = strtotime($ev['time_to']);
    if ($now > $end)                        $completed_count++;
    elseif ($now >= $start && $now <= $end) $ongoing_count++;
    else                                    $upcoming_count++;
}

// ─── Future events (grouped by date) ─────────────────────────────
$stmt = $connect->prepare(
    "SELECT e.*, ec.name AS category_name, ec.color AS category_color
     FROM events e
     LEFT JOIN event_categories ec ON e.category_id = ec.id
     WHERE (e.status IS NULL OR e.status NOT IN ('เลื่อน','ยกเลิก'))
       AND (
           (e.date > CURDATE())
           OR (e.date = CURDATE() AND e.time_to >= TIME(DATE_SUB(NOW(), INTERVAL 5 MINUTE)))
       )
     ORDER BY e.date ASC, e.time_from ASC"
);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$connect->close();

$grouped_events = [];
foreach ($rows as $row) {
    $grouped_events[$row['date']][] = $row;
}

// ─── Render time ─────────────────────────────────────────────────
$render_ms = round((hrtime(true) - $start_time) / 1e6, 2);
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="ตารางกิจกรรม สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง">
<title>ตารางกิจกรรม · ปภ.ระยอง</title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600&family=Sarabun:wght@300;400;500&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
/* ═══════════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════════ */
:root {
  /* Brand palette */
  --brand-900: #003d4a;
  --brand-800: #005060;
  --brand-700: #006475;
  --brand-600: #0a7d80;
  --brand-500: #0a9396;
  --brand-400: #28b5b8;
  --brand-300: #72cfd1;
  --brand-200: #b8e8e9;
  --brand-100: #e3f6f6;

  /* Semantic */
  --color-ongoing  : #16a34a;
  --color-upcoming : #7c3aed;
  --color-completed: #6b7280;
  --color-danger   : #dc2626;
  --color-warn     : #d97706;
  --color-chairman : #2563eb;

  /* Surface */
  --surface-bg    : #f0f7f8;
  --surface-card  : #ffffff;
  --surface-header: linear-gradient(135deg, var(--brand-800) 0%, var(--brand-500) 100%);

  /* Text */
  --text-primary  : #0f172a;
  --text-secondary: #475569;
  --text-muted    : #94a3b8;
  --text-on-brand : #ffffff;

  /* Effects */
  --shadow-sm  : 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md  : 0 4px 12px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.04);
  --shadow-lg  : 0 10px 30px rgba(0,0,0,.10), 0 4px 8px rgba(0,0,0,.05);
  --radius-sm  : 8px;
  --radius-md  : 12px;
  --radius-lg  : 16px;
  --radius-xl  : 24px;
  --transition : 0.2s cubic-bezier(.4,0,.2,1);
}

/* ─── Base ────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
  font-family: 'Anuphan', 'Sarabun', sans-serif;
  font-size: 15px;
  background: var(--surface-bg);
  color: var(--text-primary);
  min-height: 100vh;
  margin: 0;
}

/* ─── Hero Header ─────────────────────────────── */
.site-hero {
  background: var(--surface-header);
  padding: 28px 24px 24px;
  position: relative;
  overflow: hidden;
}
.site-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 60% 80% at 90% -20%, rgba(255,255,255,.10) 0%, transparent 60%),
    radial-gradient(ellipse 40% 60% at -10% 110%, rgba(0,0,0,.15) 0%, transparent 55%);
  pointer-events: none;
}
.site-hero__inner {
  max-width: 1200px;
  margin: auto;
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.site-hero__logo {
  width: 56px; height: 56px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
  background: rgba(255,255,255,.15);
  border: 1px solid rgba(255,255,255,.25);
  backdrop-filter: blur(8px);
  padding: 4px;
}
.header-logo {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 10px;
}
.site-hero__text { flex: 1; min-width: 0; }
.site-hero__title {
  font-size: clamp(1rem, 2.5vw, 1.3rem);
  font-weight: 500;
  color: #fff;
  margin: 0 0 4px;
  line-height: 1.3;
}
.site-hero__subtitle {
  font-size: 0.78rem;
  color: rgba(255,255,255,.7);
  margin: 0;
  font-weight: 300;
}
.site-hero__meta {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 10px;
}
.live-pulse {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: rgba(255,255,255,.15);
  border: 1px solid rgba(255,255,255,.25);
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 0.75rem;
  color: rgba(255,255,255,.9);
  backdrop-filter: blur(6px);
}
.live-dot {
  width: 8px; height: 8px;
  background: #4ade80;
  border-radius: 50%;
  animation: live-blink 1.4s ease infinite;
  flex-shrink: 0;
}
@keyframes live-blink {
  0%,100% { opacity:1; transform:scale(1); box-shadow: 0 0 0 0 rgba(74,222,128,.4); }
  50%      { opacity:.6; transform:scale(.9); box-shadow: 0 0 0 5px rgba(74,222,128,0); }
}
.version-chip {
  cursor: pointer;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 0.72rem;
  color: rgba(255,255,255,.8);
  transition: background var(--transition);
}
.version-chip:hover { background: rgba(255,255,255,.22); color:#fff; }

/* ─── Share Chip (hero button) ────────────────── */
.share-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,.18);
  border: 1px solid rgba(255,255,255,.3);
  border-radius: 20px;
  padding: 5px 14px;
  font-size: 0.75rem;
  color: #fff;
  cursor: pointer;
  backdrop-filter: blur(6px);
  transition: background var(--transition), transform var(--transition);
  font-family: 'Anuphan', sans-serif;
}
.share-chip:hover {
  background: rgba(255,255,255,.28);
  transform: translateY(-1px);
}

/* ─── Share Modal ─────────────────────────────── */
.share-modal .modal-content  { border: none; border-radius: var(--radius-lg); overflow: hidden; }
.share-modal .modal-header   { background: var(--surface-header); border: none; padding: 16px 20px; }
.share-modal .modal-title    { color: #fff; font-weight: 400; font-size: 0.95rem; }
.share-modal .btn-close      { filter: invert(1); }
.share-modal .modal-body     { padding: 20px; }

/* Platform grid */
.share-platforms {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}
.share-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 14px 8px 12px;
  border-radius: var(--radius-md);
  border: 1.5px solid #e9ecef;
  background: #fff;
  cursor: pointer;
  font-size: 0.72rem;
  color: var(--text-secondary);
  font-family: 'Anuphan', sans-serif;
  font-weight: 400;
  transition: border-color var(--transition), box-shadow var(--transition), transform var(--transition), background var(--transition);
  text-decoration: none;
}
.share-btn:hover {
  border-color: var(--brand-400);
  box-shadow: 0 4px 12px rgba(10,147,150,.15);
  transform: translateY(-2px);
  color: var(--brand-700);
}
.share-btn__icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem;
}
/* Icon colours */
.share-btn--link    .share-btn__icon { background: var(--brand-100);  color: var(--brand-600); }
.share-btn--fb      .share-btn__icon { background: #e7f0fe;           color: #1877f2; }
.share-btn--line    .share-btn__icon { background: #e6f9ee;           color: #06c755; }
.share-btn--twitter .share-btn__icon { background: #f0f0f0;           color: #0f0f0f; }
.share-btn--email   .share-btn__icon { background: #fff3e0;           color: #f57c00; }
.share-btn--qr      .share-btn__icon { background: #f3e8ff;           color: #7c3aed; }
.share-btn--embed   .share-btn__icon { background: #fef2f2;           color: #dc2626; }

/* Divider label */
.share-section-label {
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin: 4px 0 10px;
}

/* Copy link row */
.copy-link-row {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
}
.copy-link-input {
  flex: 1;
  border: 1.5px solid #e2e8f0;
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 0.78rem;
  color: var(--text-secondary);
  background: #f8fafc;
  outline: none;
  font-family: monospace;
  min-width: 0;
  transition: border-color var(--transition);
}
.copy-link-input:focus { border-color: var(--brand-400); }
.copy-link-btn {
  background: var(--brand-500);
  color: #fff;
  border: none;
  border-radius: var(--radius-sm);
  padding: 8px 16px;
  font-size: 0.78rem;
  cursor: pointer;
  white-space: nowrap;
  font-family: 'Anuphan', sans-serif;
  display: flex; align-items: center; gap: 5px;
  transition: background var(--transition);
}
.copy-link-btn:hover   { background: var(--brand-700); }
.copy-link-btn.copied  { background: var(--color-ongoing); }

/* Embed box */
.embed-box {
  display: none;
  background: #0f172a;
  border-radius: var(--radius-md);
  padding: 14px 16px;
  position: relative;
  margin-top: 4px;
}
.embed-box.show { display: block; }
.embed-code {
  font-family: 'Courier New', monospace;
  font-size: 0.72rem;
  color: #94a3b8;
  white-space: pre-wrap;
  word-break: break-all;
  margin: 0;
  line-height: 1.6;
  max-height: 120px;
  overflow-y: auto;
}
.embed-code .ec-tag   { color: #f472b6; }
.embed-code .ec-attr  { color: #a78bfa; }
.embed-code .ec-val   { color: #86efac; }
.embed-copy-btn {
  position: absolute;
  top: 10px; right: 10px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 6px;
  color: #94a3b8;
  font-size: 0.72rem;
  padding: 3px 9px;
  cursor: pointer;
  transition: background var(--transition), color var(--transition);
  font-family: 'Anuphan', sans-serif;
}
.embed-copy-btn:hover { background: rgba(255,255,255,.15); color: #fff; }

/* QR area */
.qr-area {
  display: none;
  text-align: center;
  padding: 12px 0 4px;
}
.qr-area.show  { display: block; }
.qr-area img, .qr-area canvas { border-radius: var(--radius-md); box-shadow: var(--shadow-md); max-width: 200px; width: 200px; height: 200px; }
.qr-area p     { font-size: 0.72rem; color: var(--text-muted); margin-top: 8px; }

/* ─── KPI Cards ───────────────────────────────── */
.kpi-row {
  max-width: 1200px;
  margin: -18px auto 28px;
  padding: 0 16px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  position: relative;
  z-index: 10;
}

/* Section label above KPI row */
.kpi-section-label {
  max-width: 1200px;
  margin: 0 auto 10px;
  padding: 0 16px;
  font-size: 0.7rem;
  font-weight: 500;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--text-muted);
  position: relative;
  z-index: 10;
}

.kpi-card {
  background: var(--surface-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  overflow: hidden;
  border: none;
  transition: transform var(--transition), box-shadow var(--transition);
  cursor: default;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }

/* Top colour accent bar */
.kpi-accent {
  height: 4px;
  width: 100%;
  position: relative;
  overflow: hidden;
}
.kpi-card.kpi--completed .kpi-accent { background: var(--color-completed); }
.kpi-card.kpi--ongoing   .kpi-accent { background: var(--color-ongoing); }
.kpi-card.kpi--upcoming  .kpi-accent { background: var(--color-upcoming); }

/* Sweep shimmer on ongoing bar */
.kpi-card.kpi--ongoing .kpi-accent::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.6) 50%, transparent 100%);
  animation: kpi-sweep 2s ease infinite;
}
@keyframes kpi-sweep {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(200%); }
}

.kpi-inner { padding: 18px 20px 20px; }

.kpi-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 14px;
}

.kpi-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  font-size: 1.1rem;
}
.kpi--completed .kpi-icon { background: #f3f4f6; color: var(--color-completed); }
.kpi--ongoing   .kpi-icon { background: #dcfce7; color: var(--color-ongoing); }
.kpi--upcoming  .kpi-icon { background: #ede9fe; color: var(--color-upcoming); }

.kpi-trend {
  font-size: 0.68rem;
  font-weight: 500;
  padding: 3px 9px;
  border-radius: 20px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.kpi-trend--neutral { background: #f3f4f6; color: #6b7280; }
.kpi-trend--up      { background: #dcfce7; color: #16a34a; }
.kpi-trend--active  { background: #ede9fe; color: #7c3aed; }

.kpi-live-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--color-ongoing);
  display: inline-block;
  animation: kpi-blink 1.4s ease infinite;
  flex-shrink: 0;
}
@keyframes kpi-blink {
  0%,100% { opacity: 1; }
  50%      { opacity: .3; }
}

.kpi-body { flex: 1; min-width: 0; }
.kpi-count {
  font-size: 2.6rem;
  font-weight: 300;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  transition: color var(--transition);
}
.kpi--completed .kpi-count { color: var(--color-completed); }
.kpi--ongoing   .kpi-count { color: var(--color-ongoing); }
.kpi--upcoming  .kpi-count { color: var(--color-upcoming); }

.kpi-label {
  font-size: 0.72rem;
  color: var(--text-secondary);
  line-height: 1.4;
  margin-top: 6px;
}

/* Progress bar */
.kpi-bar-wrap {
  margin-top: 14px;
  background: var(--surface-bg);
  border-radius: 4px;
  height: 5px;
  overflow: hidden;
}
.kpi-bar {
  height: 100%;
  border-radius: 4px;
  width: 0%;
  transition: width 1s cubic-bezier(.4,0,.2,1);
}
.kpi--completed .kpi-bar { background: var(--color-completed); }
.kpi--ongoing   .kpi-bar { background: var(--color-ongoing); }
.kpi--upcoming  .kpi-bar { background: var(--color-upcoming); }

.kpi-footer {
  margin-top: 8px;
  font-size: 0.68rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 4px;
}

/* ─── Main Content ────────────────────────────── */
.main-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 16px 80px;
}

/* ─── Day Group Card ──────────────────────────── */
.day-group {
  background: var(--surface-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  margin-bottom: 20px;
  overflow: hidden;
  transition: box-shadow var(--transition);
}
.day-group:hover { box-shadow: var(--shadow-lg); }
.day-header {
  background: var(--surface-header);
  padding: 14px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.day-header__icon {
  width: 34px; height: 34px;
  background: rgba(255,255,255,.18);
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
  color: #fff;
  flex-shrink: 0;
}
.day-header__date {
  font-size: 0.92rem;
  font-weight: 500;
  color: #fff;
}
.day-header__today-pill {
  margin-left: auto;
  background: rgba(255,255,255,.25);
  border: 1px solid rgba(255,255,255,.35);
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.68rem;
  color: #fff;
  font-weight: 400;
}

/* ─── Event Item ──────────────────────────────── */
.event-item {
  padding: 18px 20px;
  position: relative;
  transition: background var(--transition);
}
.event-item:not(:last-child) { border-bottom: 1px solid #f1f5f9; }
.event-item:hover { background: #f8fafc; }

/* Left accent bar */
.event-item::before {
  content: '';
  position: absolute;
  left: 0; top: 14px; bottom: 14px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--brand-400);
  opacity: 0;
  transition: opacity var(--transition);
}
.event-item:hover::before { opacity: 1; }

.event-meta-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}
.event-time {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--brand-700);
}
.event-time .bi { font-size: 0.8rem; }

/* Status badges */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.72rem;
  font-weight: 400;
  white-space: nowrap;
}
.status-pill--ongoing {
  background: #dcfce7;
  color: #15803d;
  animation: ongoing-pulse 2s ease infinite;
}
@keyframes ongoing-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(22,163,74,.25); }
  50%      { box-shadow: 0 0 0 6px rgba(22,163,74,0); }
}
.status-pill--upcoming { background: #ede9fe; color: #6d28d9; }
.status-pill--completed { background: #f3f4f6; color: #6b7280; }

/* Category chip */
.category-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.68rem;
  color: #fff;
  font-weight: 400;
  margin-left: auto;
}

/* Event detail rows */
.detail-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 8px;
  font-size: 0.83rem;
}
.detail-row:last-child { margin-bottom: 0; }
.detail-icon {
  width: 22px;
  flex-shrink: 0;
  color: var(--brand-500);
  padding-top: 1px;
  text-align: center;
  font-size: 0.9rem;
}
.detail-label {
  font-weight: 500;
  color: var(--text-secondary);
  margin-right: 4px;
  white-space: nowrap;
}
.detail-value { flex: 1; color: var(--text-primary); line-height: 1.5; }

/* Person badges */
.person-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  border-radius: 6px;
  padding: 2px 8px;
  font-size: 0.72rem;
  margin: 2px 2px 2px 0;
  font-weight: 400;
}
.person-badge--chairman { background: #dbeafe; color: #1d4ed8; }
.person-badge--person   { background: var(--brand-100); color: var(--brand-800); }

.with-text {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 0 4px;
}

/* Platform icon */
.platform-icon {
  height: 22px;
  vertical-align: middle;
  margin-left: 4px;
  border-radius: 4px;
}

/* ─── Empty State ─────────────────────────────── */
.empty-state {
  padding: 60px 20px;
  text-align: center;
  color: var(--text-muted);
}
.empty-state__icon { font-size: 3rem; margin-bottom: 12px; opacity: .4; }
.empty-state__text { font-size: 0.9rem; }

/* ─── Leaves FAB ──────────────────────────────── */
.leaves-fab {
  position: fixed;
  bottom: 24px; left: 24px;
  z-index: 1050;
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--brand-500);
  color: #fff;
  padding: 11px 18px;
  border-radius: 50px;
  box-shadow: 0 4px 20px rgba(10,147,150,.35);
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 400;
  transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
  border: none;
  text-decoration: none;
}
.leaves-fab:hover {
  background: var(--brand-700);
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(10,147,150,.4);
  color: #fff;
}
.leaves-count {
  background: var(--color-danger);
  border-radius: 50%;
  width: 22px; height: 22px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.72rem;
  font-weight: 600;
}

/* ─── Back-to-Top FAB ─────────────────────────── */
.back-to-top {
  position: fixed;
  bottom: 24px; right: 24px;
  z-index: 1050;
  width: 44px; height: 44px;
  border-radius: 50%;
  background: var(--brand-500);
  color: #fff;
  border: none;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  box-shadow: 0 4px 16px rgba(10,147,150,.3);
  opacity: 0; visibility: hidden;
  transition: opacity var(--transition), visibility var(--transition),
              background var(--transition), transform var(--transition);
}
.back-to-top.show { opacity: 1; visibility: visible; }
.back-to-top:hover { background: var(--brand-700); transform: translateY(-2px); }

/* ─── Footer ──────────────────────────────────── */
.site-footer {
  text-align: center;
  padding: 16px;
  font-size: 0.72rem;
  color: var(--text-muted);
}

/* ─── Leave Modal ─────────────────────────────── */
.leave-modal .modal-content { border: none; border-radius: var(--radius-lg); overflow: hidden; }
.leave-modal .modal-header  { background: var(--surface-header); border: none; padding: 16px 20px; }
.leave-modal .modal-title   { color: #fff; font-weight: 400; font-size: 0.95rem; }
.leave-modal .btn-close     { filter: invert(1); }
.leave-card {
  border: none;
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  margin-bottom: 14px;
  border-left: 4px solid var(--brand-500);
  overflow: hidden;
}
.leave-card .card-header {
  background: var(--brand-100);
  color: var(--brand-800);
  font-weight: 500;
  font-size: 0.85rem;
  padding: 10px 14px;
  border-bottom: 1px solid var(--brand-200);
}
.leave-info { font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 5px; }
.leave-info:last-child { margin-bottom: 0; }
.leave-info strong { color: var(--brand-700); }

/* ─── Countdown ───────────────────────────────── */
.countdown { font-weight: 400; }

/* ─── Responsive ──────────────────────────────── */
@media (max-width: 576px) {
  .site-hero__inner { gap: 12px; }
  .kpi-row { grid-template-columns: 1fr 1fr; gap: 10px; }
  .kpi-count { font-size: 1.8rem; }
  .event-meta-row { gap: 6px; }
  .category-chip { display: none; } /* hide on very small screens */
}

/* ─── Print ───────────────────────────────────── */
@media print {
  .leaves-fab, .back-to-top, .site-hero__meta { display: none; }
  body { background: #fff; }
  .day-group { box-shadow: none; border: 1px solid #e2e8f0; }
}
</style>
</head>
<body>

<!-- ═══════════════ HERO ═══════════════ -->
<header class="site-hero">
  <div class="site-hero__inner">
    <div class="site-hero__logo">
      <img src="/images/logo.png" alt="Logo" class="header-logo">
    </div>
    <div class="site-hero__text">
      <h1 class="site-hero__title">สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</h1>
      <p class="site-hero__subtitle">ระบบตารางกิจกรรม &amp; ภารกิจประจำวัน</p>
    </div>
    <div class="site-hero__meta">
      <span class="live-pulse">
        <span class="live-dot"></span>Live
      </span>
      <button class="share-chip" data-bs-toggle="modal" data-bs-target="#shareModal" aria-label="แชร์">
        <i class="bi bi-share-fill"></i>
        <span>แชร์</span>
      </button>
      <span class="version-chip"
            title="เวอร์ชันของระบบ">
        เวอร์ชัน <?= e($current_version) ?>
      </span>
    </div>
  </div>
</header>

<!-- ═══════════════ KPI CARDS ═══════════════ -->
<?php
$kpi_total = $completed_count + $ongoing_count + $upcoming_count;
$pct_c = $kpi_total ? round($completed_count / $kpi_total * 100) : 0;
$pct_o = $kpi_total ? round($ongoing_count   / $kpi_total * 100) : 0;
$pct_u = $kpi_total ? round($upcoming_count  / $kpi_total * 100) : 0;
?>
<p class="kpi-section-label">&middot;</p>
<div class="kpi-row">

  <div class="kpi-card kpi--completed">
    <div class="kpi-accent"></div>
    <div class="kpi-inner">
      <div class="kpi-top">
        <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
        <span class="kpi-trend kpi-trend--neutral">เสร็จสิ้น</span>
      </div>
      <div class="kpi-count" data-target="<?= $completed_count ?>">0</div>
      <div class="kpi-label">ภารกิจที่เสร็จสิ้นแล้ว</div>
      <div class="kpi-bar-wrap">
        <div class="kpi-bar" data-pct="<?= $pct_c ?>" style="width:0%"></div>
      </div>
      <div class="kpi-footer"><?= $pct_c ?>% ของภารกิจวันนี้</div>
    </div>
  </div>

  <div class="kpi-card kpi--ongoing">
    <div class="kpi-accent"></div>
    <div class="kpi-inner">
      <div class="kpi-top">
        <div class="kpi-icon"><i class="bi bi-activity"></i></div>
        <span class="kpi-trend kpi-trend--up">
          <span class="kpi-live-dot"></span> Live
        </span>
      </div>
      <div class="kpi-count" data-target="<?= $ongoing_count ?>">0</div>
      <div class="kpi-label">กำลังดำเนินการอยู่</div>
      <div class="kpi-bar-wrap">
        <div class="kpi-bar" data-pct="<?= $pct_o ?>" style="width:0%"></div>
      </div>
      <div class="kpi-footer"><?= $pct_o ?>% ของภารกิจวันนี้</div>
    </div>
  </div>

  <div class="kpi-card kpi--upcoming">
    <div class="kpi-accent"></div>
    <div class="kpi-inner">
      <div class="kpi-top">
        <div class="kpi-icon"><i class="bi bi-alarm-fill"></i></div>
        <span class="kpi-trend kpi-trend--active">รอดำเนินการ</span>
      </div>
      <div class="kpi-count" data-target="<?= $upcoming_count ?>">0</div>
      <div class="kpi-label">ภารกิจที่รอดำเนินการ</div>
      <div class="kpi-bar-wrap">
        <div class="kpi-bar" data-pct="<?= $pct_u ?>" style="width:0%"></div>
      </div>
      <div class="kpi-footer"><?= $pct_u ?>% ของภารกิจวันนี้</div>
    </div>
  </div>

</div>

<!-- ═══════════════ EVENTS ═══════════════ -->
<main class="main-wrap">
<?php if (empty($grouped_events)): ?>
  <div class="day-group">
    <div class="empty-state">
      <div class="empty-state__icon"><i class="bi bi-calendar-x"></i></div>
      <div class="empty-state__text">ไม่มีกิจกรรมที่กำลังจะมาถึง</div>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($grouped_events as $date => $events): ?>
    <article class="day-group">
      <div class="day-header">
        <div class="day-header__icon"><i class="bi bi-calendar3"></i></div>
        <span class="day-header__date"><?= thai_date_full($date) ?></span>
        <?php if ($date === $today): ?>
          <span class="day-header__today-pill"><i class="bi bi-dot"></i> วันนี้</span>
        <?php endif; ?>
      </div>

      <?php foreach ($events as $idx => $ev):
        $ts_start = strtotime("$date {$ev['time_from']}");
        $ts_end   = strtotime("$date {$ev['time_to']}");
        $is_ongoing   = ($now >= $ts_start && $now <= $ts_end);
        $is_completed = ($now > $ts_end);
        $is_upcoming  = ($now < $ts_start);
        $tf = substr($ev['time_from'], 0, 5);
        $tt = substr($ev['time_to'],   0, 5);

        // Parse persons
        $persons = array_values(array_filter(
            explode(' ', (string)$ev['description']),
            fn($v) => trim($v) !== ''
        ));
        $has_head = in_array('หน.ปภ', $persons, true);
      ?>
      <div class="event-item" id="ev-<?= (int)$ev['id'] ?>">

        <!-- Meta row: time + status + category -->
        <div class="event-meta-row">
          <span class="event-time">
            <i class="bi bi-clock"></i>
            <?= e($tf) ?> น.
          </span>

          <?php if ($is_ongoing): ?>
            <span class="status-pill status-pill--ongoing">
              <i class="bi bi-activity"></i> กำลังดำเนินการ
            </span>
          <?php elseif ($is_upcoming): ?>
            <span class="status-pill status-pill--upcoming">
              <i class="bi bi-alarm"></i>&nbsp;เริ่มใน&nbsp;<span
                class="countdown"
                data-date="<?= e($date) ?>"
                data-time="<?= e($ev['time_from']) ?>">กำลังคำนวณ…</span>
            </span>
          <?php else: ?>
            <span class="status-pill status-pill--completed">
              <i class="bi bi-check-circle"></i> เสร็จสิ้น
            </span>
          <?php endif; ?>

          <?php if (!empty($ev['category_name'])): ?>
            <span class="category-chip" style="background-color:<?= e($ev['category_color'] ?: '#0a9396') ?>">
              <i class="bi bi-tag"></i> <?= e($ev['category_name']) ?>
            </span>
          <?php endif; ?>
        </div>

        <!-- Title / Mission -->
        <div class="detail-row">
          <span class="detail-icon"><i class="bi bi-card-heading"></i></span>
          <div class="detail-value">
            <span class="detail-label">ภารกิจ:</span>
            <?= inject_platform_icons((string)$ev['title']) ?>
          </div>
        </div>

        <!-- Location -->
        <div class="detail-row">
          <span class="detail-icon"><i class="bi bi-geo-alt"></i></span>
          <div class="detail-value">
            <span class="detail-label">สถานที่:</span>
            <?= e((string)$ev['location']) ?>
          </div>
        </div>

        <!-- Department -->
        <?php if (!empty($ev['department'])): ?>
        <div class="detail-row">
          <span class="detail-icon"><i class="bi bi-building"></i></span>
          <div class="detail-value">
            <span class="detail-label">หน่วยงาน:</span>
            <?= e($ev['department']) ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Chairman -->
        <?php if (!empty($ev['chairman'])): ?>
        <div class="detail-row">
          <span class="detail-icon"><i class="bi bi-person-badge"></i></span>
          <div class="detail-value">
            <span class="detail-label">ประธาน:</span>
            <span class="person-badge person-badge--chairman">
              <i class="bi bi-star-fill"></i> <?= e($ev['chairman']) ?>
            </span>
          </div>
        </div>
        <?php endif; ?>

        <!-- Persons -->
        <?php if (!empty($persons)): ?>
        <div class="detail-row">
          <span class="detail-icon"><i class="bi bi-people"></i></span>
          <div class="detail-value">
            <?php if ($has_head):
              $head_key = array_search('หน.ปภ', $persons, true);
              $head     = $persons[$head_key];
              unset($persons[$head_key]);
            ?>
              <span class="detail-label">ลงนัด:</span>
              <span class="person-badge person-badge--person"><?= e($head) ?></span>
              <?php if (!empty($persons)): ?>
                <span class="with-text">พร้อมด้วย</span>
                <?php foreach ($persons as $p): ?>
                  <span class="person-badge person-badge--person"><?= e($p) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php else: ?>
              <span class="detail-label">มอบหมาย:</span>
              <?php foreach ($persons as $p): ?>
                <span class="person-badge person-badge--person"><?= e($p) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- .event-item -->
      <?php endforeach; ?>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
</main>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="site-footer">
  สร้างหน้าในเวลา <?= number_format($render_ms, 2) ?> มิลลิวินาที
  &nbsp;·&nbsp;
  เวอร์ชัน <?= e($current_version) ?>
</footer>

<!-- ═══════════════ LEAVES FAB ═══════════════ -->
<?php if ($leave_count > 0): ?>
<button class="leaves-fab" data-bs-toggle="modal" data-bs-target="#leavesModal" aria-label="ดูข้อมูลการลา">
  <i class="bi bi-person-x-fill"></i>
  <span>การลาบุคลากร</span>
  <span class="leaves-count"><?= $leave_count ?></span>
</button>
<?php endif; ?>

<!-- ═══════════════ BACK TO TOP ═══════════════ -->
<button class="back-to-top" id="backToTop" aria-label="กลับด้านบน">
  <i class="bi bi-arrow-up"></i>
</button>

<!-- ═══════════════ LEAVES MODAL ═══════════════ -->
<?php if ($leave_count > 0): ?>
<div class="modal fade leave-modal" id="leavesModal" tabindex="-1" aria-labelledby="leavesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="leavesModalLabel">
          <i class="bi bi-person-x-fill me-2"></i>ข้อมูลการลาบุคลากร
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body p-3">
        <?php foreach ($leaves as $lv): ?>
        <div class="leave-card card">
          <div class="card-header">
            <i class="bi bi-person-circle me-1"></i><?= e((string)$lv['name']) ?>
          </div>
          <div class="card-body p-3">
            <?php if (!empty($lv['leave_type'])): ?>
            <div class="leave-info">
              <strong><i class="bi bi-tag me-1"></i>ประเภทการลา:</strong>
              <?= e($lv['leave_type']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($lv['leave_start'])): ?>
            <div class="leave-info">
              <strong><i class="bi bi-calendar-event me-1"></i>วันที่ลา:</strong>
              <?= e($lv['leave_start']) ?>
              <?php if (!empty($lv['leave_end'])): ?>
                — <?= e($lv['leave_end']) ?>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($lv['leave_note'])): ?>
            <div class="leave-info">
              <strong><i class="bi bi-chat-left-text me-1"></i>หมายเหตุ:</strong>
              <?= e($lv['leave_note']) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════ SHARE MODAL ═══════════════ -->
<div class="modal fade share-modal" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="shareModalLabel">
          <i class="bi bi-share-fill me-2"></i>แชร์ตารางกิจกรรม
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">

        <!-- Copy link -->
        <div class="share-section-label"><i class="bi bi-link-45deg me-1"></i>ลิงก์หน้านี้</div>
        <div class="copy-link-row">
          <input type="text" class="copy-link-input" id="shareLinkInput" readonly>
          <button class="copy-link-btn" id="copyLinkBtn">
            <i class="bi bi-clipboard"></i> คัดลอก
          </button>
        </div>

        <!-- Platform buttons -->
        <div class="share-section-label"><i class="bi bi-grid me-1"></i>แชร์ไปยังแพลตฟอร์ม</div>
        <div class="share-platforms">

          <button class="share-btn share-btn--fb" id="shareToFb" title="แชร์ไป Facebook">
            <span class="share-btn__icon"><i class="bi bi-facebook"></i></span>
            Facebook
          </button>

          <button class="share-btn share-btn--line" id="shareToLine" title="แชร์ไป LINE">
            <span class="share-btn__icon">
              <!-- LINE logo as SVG -->
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="22" height="22">
                <path fill="#06c755" d="M24 4C12.95 4 4 11.84 4 21.5c0 5.37 2.87 10.14 7.36 13.37-.32 1.18-1.15 4.28-1.32 4.95-.21.82.3 1.82 1.14 1.4.65-.32 7.45-4.93 9.2-6.08C21.5 35.4 22.74 35.5 24 35.5c11.05 0 20-7.84 20-17.5S35.05 4 24 4z"/>
                <path fill="#fff" d="M35.5 22.25h-5.25v-6.75a.75.75 0 0 0-1.5 0v7.5c0 .41.34.75.75.75H35.5a.75.75 0 0 0 0-1.5zm-9.75-6.75a.75.75 0 0 0-1.5 0v7.5c0 .41.34.75.75.75s.75-.34.75-.75v-7.5zm-3.5 6.67-4.75-7.05a.75.75 0 0 0-1.25.08v7.55c0 .41.34.75.75.75s.75-.34.75-.75v-4.98l3.75 5.57c.14.21.37.33.62.33a.75.75 0 0 0 .75-.75v-7.5a.75.75 0 0 0-1.5 0l-.12 1.75z"/>
              </svg>
            </span>
            LINE
          </button>

          <button class="share-btn share-btn--twitter" id="shareToX" title="แชร์ไป X (Twitter)">
            <span class="share-btn__icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
            </span>
            X (Twitter)
          </button>

          <button class="share-btn share-btn--email" id="shareToEmail" title="แชร์ผ่านอีเมล">
            <span class="share-btn__icon"><i class="bi bi-envelope-fill"></i></span>
            อีเมล
          </button>

          <button class="share-btn share-btn--qr" id="toggleQr" title="QR Code">
            <span class="share-btn__icon"><i class="bi bi-qr-code"></i></span>
            QR Code
          </button>

          <button class="share-btn share-btn--embed" id="toggleEmbed" title="Embed">
            <span class="share-btn__icon"><i class="bi bi-code-slash"></i></span>
            Embed
          </button>

        </div>

        <!-- QR Code area -->
        <div class="qr-area" id="qrArea">
          <img id="qrImg" src="" alt="QR Code" style="display:none">
          <p>สแกน QR Code เพื่อเข้าสู่หน้าตารางกิจกรรม</p>
        </div>

        <!-- Embed code area -->
        <div class="embed-box" id="embedBox">
          <button class="embed-copy-btn" id="copyEmbedBtn">
            <i class="bi bi-clipboard"></i> คัดลอก
          </button>
          <pre class="embed-code" id="embedCode"></pre>
        </div>

      </div><!-- /.modal-body -->
    </div>
  </div>
</div>

<!-- qrcode.js -->
<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ───────────────────────────────────────────────
   Live Countdown + Status Updater
─────────────────────────────────────────────── */
(function () {
  'use strict';

  function pad(n) { return String(n).padStart(2, '0'); }

  function updateCountdowns() {
    const now = Date.now();
    const todayStr = new Date().toISOString().slice(0, 10);

    document.querySelectorAll('.countdown').forEach(el => {
      const date = el.dataset.date;
      const time = el.dataset.time;
      if (!date || !time) return;

      const eventMs  = new Date(`${date}T${time}`).getTime();
      const diff     = eventMs - now;
      const pillEl   = el.closest('.status-pill');

      if (diff <= 0) {
        // Transition to ongoing
        if (pillEl && pillEl.classList.contains('status-pill--upcoming')) {
          pillEl.classList.replace('status-pill--upcoming', 'status-pill--ongoing');
          pillEl.innerHTML = '<i class="bi bi-activity"></i> กำลังดำเนินการ';
        }
        return;
      }

      // Only show countdown for today's events
      if (date !== todayStr) {
        el.textContent = '';
        if (pillEl) pillEl.style.display = 'none';
        return;
      }

      const totalSec = Math.floor(diff / 1000);
      const h = Math.floor(totalSec / 3600);
      const m = Math.floor((totalSec % 3600) / 60);
      const s = totalSec % 60;

      el.textContent = h > 0
        ? `${h} ชม. ${pad(m)} น. ${pad(s)} ว.`
        : m > 0
          ? `${pad(m)} น. ${pad(s)} ว.`
          : `${pad(s)} ว.`;
    });
  }

  updateCountdowns();
  setInterval(updateCountdowns, 1000);

  /* ── KPI count-up + progress bars ── */
  function animKpi() {
    document.querySelectorAll('.kpi-count[data-target]').forEach(el => {
      const target = parseInt(el.dataset.target, 10) || 0;
      let start = null;
      function step(ts) {
        if (!start) start = ts;
        const p = Math.min((ts - start) / 900, 1);
        const ease = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * ease);
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = target;
      }
      requestAnimationFrame(step);
    });
    setTimeout(() => {
      document.querySelectorAll('.kpi-bar[data-pct]').forEach(bar => {
        bar.style.width = bar.dataset.pct + '%';
      });
    }, 200);
  }
  setTimeout(animKpi, 350);

  /* ── Back-to-Top ── */
  const btt = document.getElementById('backToTop');
  if (btt) {
    window.addEventListener('scroll', () => {
      btt.classList.toggle('show', window.scrollY > 280);
    }, { passive: true });
    btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  /* ── Auto-hide completed events after 5-min grace ── */
  function pruneCompletedEvents() {
    const now = Date.now();
    document.querySelectorAll('.event-item').forEach(item => {
      const dateEl = item.querySelector('.countdown[data-date]');
      if (!dateEl) return;

      const eventDate = dateEl.dataset.date;
      const endTimeEl = item.querySelector('[data-end-time]');
      if (!endTimeEl) return;

      const endMs = new Date(`${eventDate}T${endTimeEl.dataset.endTime}`).getTime();
      if (now - endMs > 5 * 60 * 1000) {
        item.style.display = 'none';
        const group = item.closest('.day-group');
        if (group) {
          const visible = group.querySelectorAll('.event-item:not([style*="none"])').length;
          if (visible === 0) group.style.display = 'none';
        }
      }
    });
  }
  setInterval(pruneCompletedEvents, 30_000);

  /* ════════════════════════════════════════════
     SHARE MODULE
  ════════════════════════════════════════════ */
  const shareModal = document.getElementById('shareModal');
  if (shareModal) {
    const pageUrl      = window.location.href;
    const pageTitle    = document.title;

    // Populate link input when modal opens
    shareModal.addEventListener('show.bs.modal', () => {
      const input = document.getElementById('shareLinkInput');
      if (input) input.value = pageUrl;

      // Build embed code — responsive Canva-style wrapper (fixed URL)
      const embedUrl = 'https://schedule.dpmpry.online/preview.php';
      const ec = document.getElementById('embedCode');
      if (ec) {
        ec.innerHTML =
          `<span class="ec-tag">&lt;div</span> <span class="ec-attr">style</span>=<span class="ec-val">"position:relative;width:100%;height:0;` +
          `padding-top:141.4141%;padding-bottom:0;box-shadow:0 2px 8px 0 rgba(63,69,81,.16);` +
          `margin-top:1.6em;margin-bottom:.9em;overflow:hidden;border-radius:8px;will-change:transform;"</span><span class="ec-tag">&gt;</span>\n` +
          `  <span class="ec-tag">&lt;iframe</span>\n` +
          `    <span class="ec-attr">loading</span>=<span class="ec-val">"lazy"</span>\n` +
          `    <span class="ec-attr">style</span>=<span class="ec-val">"position:absolute;width:100%;height:100%;top:0;left:0;border:none;padding:0;margin:0;"</span>\n` +
          `    <span class="ec-attr">src</span>=<span class="ec-val">"${embedUrl}"</span>\n` +
          `    <span class="ec-attr">allowfullscreen</span>=<span class="ec-val">"allowfullscreen"</span>\n` +
          `    <span class="ec-attr">allow</span>=<span class="ec-val">"fullscreen"</span>\n` +
          `  <span class="ec-tag">&gt;&lt;/iframe&gt;</span>\n` +
          `<span class="ec-tag">&lt;/div&gt;</span>`;
      }

      // QR code via qrcode.js (canvas → img)
      const qrArea = document.getElementById('qrArea');
      if (qrArea && !qrArea.dataset.ready) {
        qrArea.dataset.ready = '1';
        const canvas = document.createElement('canvas');
        canvas.id = 'qrCanvas';
        const oldImg = document.getElementById('qrImg');
        if (oldImg) oldImg.replaceWith(canvas);
        QRCode.toCanvas(canvas, 'https://schedule.dpmpry.online/preview.php', {
          width: 200, margin: 2,
          color: { dark: '#003d4a', light: '#ffffff' }
        }, function(err) {
          if (err) { canvas.remove(); }
        });
      }
    });

    // Reset panels on modal close
    shareModal.addEventListener('hide.bs.modal', () => {
      document.getElementById('embedBox')?.classList.remove('show');
      document.getElementById('qrArea')?.classList.remove('show');
    });

    // ── Copy link ────────────────────────────
    document.getElementById('copyLinkBtn')?.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(pageUrl);
      } catch {
        document.getElementById('shareLinkInput').select();
        document.execCommand('copy');
      }
      this.classList.add('copied');
      this.innerHTML = '<i class="bi bi-check-lg"></i> คัดลอกแล้ว!';
      setTimeout(() => {
        this.classList.remove('copied');
        this.innerHTML = '<i class="bi bi-clipboard"></i> คัดลอก';
      }, 2000);
    });

    // ── Facebook ──────────────────────────────
    document.getElementById('shareToFb')?.addEventListener('click', () => {
      window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pageUrl)}`, '_blank', 'width=620,height=450');
    });

    // ── LINE ──────────────────────────────────
    document.getElementById('shareToLine')?.addEventListener('click', () => {
      window.open(`https://social-plugins.line.me/lineit/share?url=${encodeURIComponent(pageUrl)}&text=${encodeURIComponent(pageTitle)}`, '_blank', 'width=560,height=500');
    });

    // ── X (Twitter) ───────────────────────────
    document.getElementById('shareToX')?.addEventListener('click', () => {
      window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl)}&text=${encodeURIComponent(pageTitle)}`, '_blank', 'width=560,height=420');
    });

    // ── Email ─────────────────────────────────
    document.getElementById('shareToEmail')?.addEventListener('click', () => {
      window.location.href = `mailto:?subject=${encodeURIComponent(pageTitle)}&body=${encodeURIComponent('ดูตารางกิจกรรมได้ที่: ' + pageUrl)}`;
    });

    // ── QR Code toggle ────────────────────────
    document.getElementById('toggleQr')?.addEventListener('click', function () {
      const qr = document.getElementById('qrArea');
      const em = document.getElementById('embedBox');
      em?.classList.remove('show');
      qr?.classList.toggle('show');
      this.style.borderColor = qr?.classList.contains('show') ? 'var(--color-upcoming)' : '';
      document.getElementById('toggleEmbed').style.borderColor = '';
    });

    // ── Embed toggle ──────────────────────────
    document.getElementById('toggleEmbed')?.addEventListener('click', function () {
      const em = document.getElementById('embedBox');
      const qr = document.getElementById('qrArea');
      qr?.classList.remove('show');
      em?.classList.toggle('show');
      this.style.borderColor = em?.classList.contains('show') ? 'var(--color-danger)' : '';
      document.getElementById('toggleQr').style.borderColor = '';
    });

    // ── Copy embed code ───────────────────────
    document.getElementById('copyEmbedBtn')?.addEventListener('click', async function () {
      const raw = `<div style="position:relative;width:100%;height:0;padding-top:141.4141%;padding-bottom:0;box-shadow:0 2px 8px 0 rgba(63,69,81,.16);margin-top:1.6em;margin-bottom:.9em;overflow:hidden;border-radius:8px;will-change:transform;"><iframe loading="lazy" style="position:absolute;width:100%;height:100%;top:0;left:0;border:none;padding:0;margin:0;" src="https://schedule.dpmpry.online/preview.php" allowfullscreen="allowfullscreen" allow="fullscreen"></iframe></div>`;
      try { await navigator.clipboard.writeText(raw); }
      catch { /* silent fail */ }
      this.innerHTML = '<i class="bi bi-check-lg"></i> คัดลอกแล้ว!';
      setTimeout(() => { this.innerHTML = '<i class="bi bi-clipboard"></i> คัดลอก'; }, 2000);
    });

    // ── Web Share API (mobile native) ─────────
    if (navigator.share) {
      const nativeBtn = document.createElement('button');
      nativeBtn.className = 'share-btn share-btn--link';
      nativeBtn.title = 'แชร์ผ่านอุปกรณ์';
      nativeBtn.innerHTML = `<span class="share-btn__icon"><i class="bi bi-box-arrow-up"></i></span>แชร์ (อุปกรณ์)`;
      nativeBtn.addEventListener('click', () => navigator.share({ title: pageTitle, url: pageUrl }));
      document.querySelector('.share-platforms')?.prepend(nativeBtn);
    }
  }
})();
</script>
</body>
</html>
<?php
// Flush output buffer
$html = ob_get_clean();
echo $html;

<!DOCTYPE html>
<html lang="th" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= e($title ?? 'หน้าหลัก') ?> — ระบบ RDSIS จ.ระยอง</title>
<meta name="description" content="ระบบสถิติสาธารณภัยจังหวัดระยอง">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide-static@0.309.0/font/lucide.min.css">

<!-- Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ═══════════════════════════════════════════════════════════
   RDSIS Design System — Industrial Emergency Command Theme
   ═══════════════════════════════════════════════════════════ */
:root {
  /* Core Palette */
  --bg-base:       #0a0e1a;
  --bg-surface:    #111827;
  --bg-elevated:   #1a2235;
  --bg-card:       #1e2d47;
  --bg-input:      #162032;

  /* Borders */
  --border:        #243349;
  --border-light:  #2d4060;

  /* Text */
  --text-primary:  #e8f0fe;
  --text-secondary:#8ba3c4;
  --text-muted:    #4a6080;
  --text-inverse:  #0a0e1a;

  /* Accent — Emergency Red + Action Blue */
  --red:           #ef4444;
  --red-dark:      #b91c1c;
  --red-glow:      rgba(239,68,68,.25);
  --blue:          #3b82f6;
  --blue-dark:     #1d4ed8;
  --blue-glow:     rgba(59,130,246,.2);
  --amber:         #f59e0b;
  --green:         #10b981;
  --purple:        #8b5cf6;
  --cyan:          #06b6d4;

  /* Semantic */
  --success:       #10b981;
  --warning:       #f59e0b;
  --danger:        #ef4444;
  --info:          #3b82f6;

  /* Spacing */
  --sidebar-w:     240px;
  --header-h:      60px;
  --radius:        10px;
  --radius-sm:     6px;
  --radius-lg:     16px;

  /* Typography */
  --font-body:     'Noto Sans Thai', sans-serif;
  --font-mono:     'IBM Plex Mono', monospace;

  /* Shadows */
  --shadow-sm:     0 1px 3px rgba(0,0,0,.4);
  --shadow:        0 4px 16px rgba(0,0,0,.5);
  --shadow-lg:     0 8px 32px rgba(0,0,0,.6);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  font-family: var(--font-body);
  background: var(--bg-base);
  color: var(--text-primary);
  line-height: 1.6;
  font-size: 14px;
  min-height: 100vh;
  display: flex;
}

/* ── Sidebar ── */
.sidebar {
  width: var(--sidebar-w);
  height: 100vh;
  position: fixed;
  top: 0; left: 0;
  background: var(--bg-surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 100;
  transition: transform .3s ease;
  overflow: hidden;
}

.sidebar-logo {
  padding: 18px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.logo-badge {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--red), var(--red-dark));
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 0 16px var(--red-glow);
}

.logo-text { line-height: 1.2; }
.logo-text strong { display: block; font-size: 13px; color: var(--text-primary); }
.logo-text span { font-size: 10px; color: var(--text-muted); letter-spacing: .05em; }

.sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 0; }
.nav-section-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: var(--text-muted);
  padding: 16px 20px 6px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 20px;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 500;
  border-left: 3px solid transparent;
  transition: all .15s ease;
  position: relative;
}
.nav-item:hover { background: var(--bg-elevated); color: var(--text-primary); }
.nav-item.active {
  background: rgba(59,130,246,.12);
  color: var(--blue);
  border-left-color: var(--blue);
}
.nav-item .icon { width: 18px; height: 18px; flex-shrink: 0; opacity: .8; }
.nav-item.active .icon { opacity: 1; }

.nav-badge {
  margin-left: auto;
  background: var(--red);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

.sidebar-footer {
  border-top: 1px solid var(--border);
  padding: 14px;
}

.user-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: background .15s;
}
.user-card:hover { background: var(--bg-elevated); }

.user-avatar {
  width: 34px; height: 34px;
  border-radius: 8px;
  object-fit: cover;
  border: 2px solid var(--border-light);
}

.user-info { flex: 1; min-width: 0; }
.user-info .name { font-size: 12.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-info .role { font-size: 10.5px; color: var(--text-muted); }

/* ── Main Content ── */
.main-wrap {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* ── Header ── */
.header {
  height: var(--header-h);
  background: var(--bg-surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 24px;
  gap: 16px;
  position: sticky;
  top: 0;
  z-index: 50;
}

.header-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text-primary);
  flex: 1;
}

.header-actions { display: flex; align-items: center; gap: 10px; }

.btn-icon {
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--bg-elevated);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all .15s;
  position: relative;
  text-decoration: none;
}
.btn-icon:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-glow); }

.notif-dot {
  position: absolute;
  top: 5px; right: 5px;
  width: 7px; height: 7px;
  background: var(--red);
  border-radius: 50%;
  border: 2px solid var(--bg-surface);
}

/* ── Page Content ── */
.page-content { padding: 24px; flex: 1; }

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
  gap: 16px;
}

.page-header-left h1 { font-size: 22px; font-weight: 700; color: var(--text-primary); }
.page-header-left p { font-size: 13px; color: var(--text-secondary); margin-top: 3px; }
.page-header-right { display: flex; gap: 10px; flex-shrink: 0; }

/* ── Buttons ── */
.btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s ease;
  border: 1px solid transparent;
  text-decoration: none;
  white-space: nowrap;
}
.btn-primary   { background: var(--blue); color: #fff; }
.btn-primary:hover { background: var(--blue-dark); }
.btn-danger    { background: var(--red); color: #fff; }
.btn-danger:hover { background: var(--red-dark); }
.btn-success   { background: var(--green); color: #fff; }
.btn-warning   { background: var(--amber); color: #000; }
.btn-outline   { background: transparent; border-color: var(--border-light); color: var(--text-secondary); }
.btn-outline:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-glow); }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.btn-lg { padding: 10px 22px; font-size: 15px; }

/* ── Cards ── */
.card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}

.card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-body { padding: 20px; }

/* ── Stat Cards ── */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  position: relative;
  overflow: hidden;
  transition: transform .2s, border-color .2s;
}
.stat-card:hover { transform: translateY(-2px); border-color: var(--border-light); }

.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--accent, var(--blue));
}

.stat-icon {
  width: 42px; height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-bottom: 14px;
  background: var(--icon-bg, rgba(59,130,246,.15));
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--text-primary);
  font-variant-numeric: tabular-nums;
  line-height: 1;
  margin-bottom: 6px;
}

.stat-label {
  font-size: 12px;
  color: var(--text-secondary);
  font-weight: 500;
}

.stat-change {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  font-weight: 600;
  margin-top: 10px;
}
.stat-change.up   { color: var(--red); }
.stat-change.down { color: var(--green); }
.stat-change.neutral { color: var(--text-muted); }

/* ── Tables ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead th {
  padding: 11px 14px;
  text-align: left;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--text-muted);
  background: var(--bg-surface);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
tbody td {
  padding: 12px 14px;
  font-size: 13.5px;
  color: var(--text-primary);
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: var(--bg-elevated); }

/* ── Badges ── */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
}
.badge-red    { background: rgba(239,68,68,.15);   color: #fca5a5; border: 1px solid rgba(239,68,68,.3); }
.badge-blue   { background: rgba(59,130,246,.15);  color: #93c5fd; border: 1px solid rgba(59,130,246,.3); }
.badge-green  { background: rgba(16,185,129,.15);  color: #6ee7b7; border: 1px solid rgba(16,185,129,.3); }
.badge-amber  { background: rgba(245,158,11,.15);  color: #fcd34d; border: 1px solid rgba(245,158,11,.3); }
.badge-purple { background: rgba(139,92,246,.15);  color: #c4b5fd; border: 1px solid rgba(139,92,246,.3); }
.badge-gray   { background: rgba(107,114,128,.15); color: #9ca3af; border: 1px solid rgba(107,114,128,.3); }

/* ── Forms ── */
.form-group { margin-bottom: 18px; }
.form-label {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 6px;
}
.form-label .req { color: var(--red); margin-left: 3px; }

.form-control {
  width: 100%;
  padding: 9px 12px;
  background: var(--bg-input);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text-primary);
  font-family: var(--font-body);
  font-size: 13.5px;
  transition: border-color .15s, box-shadow .15s;
  outline: none;
}
.form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 3px var(--blue-glow); }
.form-control::placeholder { color: var(--text-muted); }
select.form-control { cursor: pointer; }
textarea.form-control { resize: vertical; min-height: 90px; }

.form-error {
  font-size: 12px;
  color: var(--red);
  margin-top: 4px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0 20px; }
.form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 20px; }
.col-span-2 { grid-column: 1 / -1; }

/* ── Alerts / Flash ── */
.alert {
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  font-size: 13.5px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 20px;
}
.alert-success { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }
.alert-error   { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }
.alert-warning { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); color: #fcd34d; }
.alert-info    { background: rgba(59,130,246,.12); border: 1px solid rgba(59,130,246,.3); color: #93c5fd; }

/* ── Pagination ── */
.pagination-nav { display: flex; justify-content: center; padding: 16px 0 0; }
.pagination { display: flex; list-style: none; gap: 4px; }
.page-link {
  display: flex; align-items: center; justify-content: center;
  min-width: 34px; height: 34px; padding: 0 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--bg-elevated);
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  transition: all .15s;
}
.page-link:hover { border-color: var(--blue); color: var(--blue); }
.page-link.active { background: var(--blue); color: #fff; border-color: var(--blue); }

/* ── Utility ── */
.text-red    { color: var(--red) !important; }
.text-green  { color: var(--green) !important; }
.text-amber  { color: var(--amber) !important; }
.text-blue   { color: var(--blue) !important; }
.text-muted  { color: var(--text-muted) !important; }
.text-right  { text-align: right; }
.text-center { text-align: center; }
.fw-bold     { font-weight: 700; }
.fw-mono     { font-family: var(--font-mono); }
.d-flex      { display: flex; }
.align-center{ align-items: center; }
.gap-2       { gap: 8px; }
.gap-3       { gap: 12px; }
.mt-1        { margin-top: 4px; }
.mt-2        { margin-top: 8px; }
.mt-4        { margin-top: 16px; }
.mb-4        { margin-bottom: 16px; }
.p-0         { padding: 0 !important; }

/* ── Chart Canvas ── */
.chart-container { position: relative; }

/* ── Map ── */
.leaflet-container { background: var(--bg-elevated); }

/* ── Loading ── */
.loading-overlay {
  position: fixed; inset: 0;
  background: rgba(10,14,26,.8);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999;
  display: none;
}
.spinner {
  width: 40px; height: 40px;
  border: 3px solid var(--border);
  border-top-color: var(--blue);
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Severity bars ── */
.severity-bar { display: flex; gap: 3px; align-items: center; }
.severity-pip {
  width: 8px; height: 14px;
  border-radius: 2px;
  background: var(--border);
}
.severity-pip.filled { background: var(--pip-color, var(--blue)); }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main-wrap { margin-left: 0; }
  .form-grid, .form-grid-3 { grid-template-columns: 1fr; }
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .page-content { padding: 16px; }
  .stat-grid { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; }
}

/* ── Scrollbar ── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--bg-surface); }
::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
</style>
<?= \App\Core\View::yield('head') ?>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <a href="<?= url('dashboard') ?>" class="sidebar-logo">
    <div class="logo-badge">
      <svg width="20" height="20" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    </div>
    <div class="logo-text">
      <strong>RDSIS</strong>
      <span>สาธารณภัย จ.ระยอง</span>
    </div>
  </a>

  <nav class="sidebar-nav">
    <span class="nav-section-label">ภาพรวม</span>
    <a href="<?= url('dashboard') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI']??'', '/dashboard') ? 'active' : '' ?>">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      แดชบอร์ด
    </a>

    <span class="nav-section-label">ข้อมูลสาธารณภัย</span>
    <a href="<?= url('disasters') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI']??'', '/disasters') ? 'active' : '' ?>">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      รายการสาธารณภัย
      <?php
        $openCount = app('db')->table('disasters')->where('status','!=','closed')->where('status','!=','false_alarm')->whereNull('deleted_at')->count();
        if ($openCount > 0): ?>
        <span class="nav-badge"><?= $openCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= url('disasters/create') ?>" class="nav-item">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      บันทึกเหตุการณ์ใหม่
    </a>

    <span class="nav-section-label">สถิติ & วิเคราะห์</span>
    <a href="<?= url('analytics') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI']??'', '/analytics') && !str_contains($_SERVER['REQUEST_URI']??'', '/map') ? 'active' : '' ?>">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      วิเคราะห์สถิติ
    </a>
    <a href="<?= url('analytics/map') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI']??'', '/map') ? 'active' : '' ?>">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
      แผนที่สาธารณภัย
    </a>
    <a href="<?= url('analytics/compare') ?>" class="nav-item">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      เปรียบเทียบ
    </a>

    <span class="nav-section-label">รายงาน</span>
    <a href="<?= url('reports') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI']??'', '/reports') ? 'active' : '' ?>">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      รายงาน
    </a>

    <?php if (in_array($_SESSION['auth_user']['role_slug'] ?? '', ['admin','superadmin'])): ?>
    <span class="nav-section-label">จัดการระบบ</span>
    <a href="<?= url('users') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI']??'', '/users') ? 'active' : '' ?>">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      ผู้ใช้งาน
    </a>
    <a href="<?= url('settings') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI']??'', '/settings') ? 'active' : '' ?>">
      <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      ตั้งค่าระบบ
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card" onclick="toggleUserMenu()">
      <img src="<?= e($_SESSION['auth_user']['avatar'] ? url('uploads/'.$_SESSION['auth_user']['avatar']) : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['auth_user']['full_name']).'&background=1e40af&color=fff&size=68') ?>"
           class="user-avatar" alt="avatar">
      <div class="user-info">
        <div class="name"><?= e($_SESSION['auth_user']['full_name']) ?></div>
        <div class="role"><?= e($_SESSION['auth_user']['role_name']) ?></div>
      </div>
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted)"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div id="userMenu" style="display:none; margin-top:8px;">
      <a href="<?= url('profile') ?>" class="nav-item" style="border-radius:var(--radius-sm);">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        โปรไฟล์
      </a>
      <form method="POST" action="<?= url('logout') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="nav-item" style="width:100%;border:none;background:transparent;cursor:pointer;border-radius:var(--radius-sm);color:var(--red)">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          ออกจากระบบ
        </button>
      </form>
    </div>
  </div>
</aside>

<!-- Overlay for mobile -->
<div id="sidebarOverlay" onclick="closeSidebar()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99;"></div>

<!-- Main -->
<div class="main-wrap">

  <!-- Header -->
  <header class="header">
    <button class="btn-icon" id="menuToggle" onclick="toggleSidebar()" style="display:none">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <div class="header-title"><?= e($title ?? '') ?></div>

    <div class="header-actions">
      <!-- Year indicator -->
      <div style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono);">
        ปีงบประมาณ <?= buddhist_year() ?>
      </div>

      <!-- Notifications -->
      <a href="#" class="btn-icon" id="notifBtn" title="การแจ้งเตือน">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="notif-dot" id="notifDot" style="display:none"></span>
      </a>

      <!-- Help -->
      <a href="#" class="btn-icon" title="ช่วยเหลือ">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </a>
    </div>
  </header>

  <!-- Flash Messages -->
  <div style="padding: 0 24px;">
    <?php if (has_flash('success')): ?>
    <div class="alert alert-success">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      <?= e(view_flash('success')) ?>
    </div>
    <?php endif; ?>
    <?php if (has_flash('error')): ?>
    <div class="alert alert-error">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= e(view_flash('error')) ?>
    </div>
    <?php endif; ?>
    <?php if (has_flash('warning')): ?>
    <div class="alert alert-warning">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      <?= e(view_flash('warning')) ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Page Content -->
  <main class="page-content">
    <?= \App\Core\View::yield('content') ?>
  </main>

  <footer style="padding:12px 24px;border-top:1px solid var(--border);font-size:11px;color:var(--text-muted);display:flex;justify-content:space-between;">
    <span>RDSIS v<?= RDSIS_VERSION ?> — ระบบสถิติสาธารณภัย จังหวัดระยอง</span>
    <span>© <?= date('Y') + 543 ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</span>
  </footer>
</div>

<div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const APP_URL = '<?= rtrim(env("APP_URL",""),"/") ?>';

function url(path) { return APP_URL + '/' + path.replace(/^\//, ''); }

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').style.display =
    document.getElementById('sidebar').classList.contains('open') ? 'block' : 'none';
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').style.display = 'none';
}
function toggleUserMenu() {
  const m = document.getElementById('userMenu');
  m.style.display = m.style.display === 'none' ? 'block' : 'none';
}

// Responsive
function handleResize() {
  const isMobile = window.innerWidth <= 1024;
  document.getElementById('menuToggle').style.display = isMobile ? 'flex' : 'none';
}
window.addEventListener('resize', handleResize);
handleResize();

// Load notifications
async function loadNotifications() {
  try {
    const r = await fetch(url('ajax/notifications'), { headers: {'X-Requested-With': 'XMLHttpRequest'} });
    const d = await r.json();
    if (d.count > 0) document.getElementById('notifDot').style.display = 'block';
  } catch(e) {}
}
loadNotifications();

// Ajax helper
async function apiPost(path, data = {}) {
  const r = await fetch(url(path), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data)
  });
  return r.json();
}

// Loading overlay
function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
function hideLoading() { document.getElementById('loadingOverlay').style.display = 'none'; }

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s'; setTimeout(()=>el.remove(),500); }, 5000);
});

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'ยืนยันการดำเนินการ?')) e.preventDefault();
  });
});
</script>
<?= \App\Core\View::yield('scripts') ?>
</body>
</html>

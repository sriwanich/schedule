<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<!-- ── Summary Stats ────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1>แดชบอร์ดสาธารณภัย</h1>
    <p>ข้อมูล ณ <?= thai_date() ?> | ปี พ.ศ. <?= buddhist_year($year) ?></p>
  </div>
  <div class="page-header-right">
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
      <select name="year" class="form-control" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($available_years as $y): ?>
          <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>พ.ศ. <?= $y + 543 ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="<?= url('disasters/create') ?>" class="btn btn-primary">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      บันทึกเหตุการณ์
    </a>
    <a href="<?= url('disasters/export/pdf?year='.$year) ?>" class="btn btn-outline">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      ส่งออกรายงาน
    </a>
  </div>
</div>

<!-- KPI Cards -->
<div class="stat-grid">
  <?php
  $kpis = [
    ['total',        'เหตุการณ์ทั้งหมด',    $stats['total'] ?? 0,               'warning',  '#f59e0b', 'rgba(245,158,11,.15)',  '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
    ['dead',         'ผู้เสียชีวิต',         $stats['total_dead'] ?? 0,           'danger',   '#ef4444', 'rgba(239,68,68,.15)',   '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>'],
    ['injured',      'ผู้บาดเจ็บ',            $stats['total_injured'] ?? 0,        'warning',  '#f59e0b', 'rgba(245,158,11,.15)',  '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    ['affected',     'ผู้ได้รับผลกระทบ',      $stats['total_affected'] ?? 0,       'info',     '#3b82f6', 'rgba(59,130,246,.15)',  '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>'],
    ['households',   'ครัวเรือนเสียหาย',      $stats['total_households'] ?? 0,     'purple',   '#8b5cf6', 'rgba(139,92,246,.15)',  '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
    ['loss',         'ความเสียหาย (บาท)',     $stats['total_loss'] ?? 0,           'danger',   '#ef4444', 'rgba(239,68,68,.15)',   '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
  ];
  foreach ($kpis as [$key, $label, $value, $colorClass, $color, $iconBg, $iconPath]):
    $change  = $growth_rate ?? 0;
    $isLoss  = $key === 'loss';
    $display = $isLoss ? '฿' . number_format((float)$value / 1000000, 2) . ' ล้าน' : number_format((int)$value);
  ?>
  <div class="stat-card" style="--accent:<?= $color ?>">
    <div class="stat-icon" style="background:<?= $iconBg ?>">
      <svg width="20" height="20" fill="none" stroke="<?= $color ?>" stroke-width="2" viewBox="0 0 24 24">
        <?= $iconPath ?>
      </svg>
    </div>
    <div class="stat-value"><?= $display ?></div>
    <div class="stat-label"><?= $label ?></div>
    <?php if ($key === 'total' && $change != 0): ?>
    <div class="stat-change <?= $change > 0 ? 'up' : 'down' ?>">
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <?= $change > 0 ? '<polyline points="18 15 12 9 6 15"/>' : '<polyline points="6 9 12 15 18 9"/>' ?>
      </svg>
      <?= abs($change) ?>% <?= $change > 0 ? 'เพิ่มขึ้น' : 'ลดลง' ?>จากปีก่อน
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row 1 -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px;">

  <!-- Monthly Trend Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        แนวโน้มรายเดือน
      </div>
      <div style="display:flex;gap:8px;font-size:12px;">
        <span style="color:var(--blue);">● เหตุการณ์</span>
        <span style="color:var(--red);">● ผู้เสียชีวิต</span>
        <span style="color:var(--amber);">● ผู้ได้รับผลกระทบ</span>
      </div>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:240px">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Category Donut -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0110 10"/></svg>
        ประเภทสาธารณภัย
      </div>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:180px">
        <canvas id="categoryChart"></canvas>
      </div>
      <div id="categoryLegend" style="margin-top:12px;font-size:12px;display:flex;flex-direction:column;gap:5px;"></div>
    </div>
  </div>
</div>

<!-- Charts Row 2 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

  <!-- Amphure Bar Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        สถิติรายอำเภอ
      </div>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:220px">
        <canvas id="amphureChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Severity Distribution -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        ระดับความรุนแรง
      </div>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:220px">
        <canvas id="severityChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Recent Alerts + Quick Stats -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">

  <!-- Recent Alerts -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <span style="width:8px;height:8px;background:var(--red);border-radius:50%;display:inline-block;animation:blink 1.5s infinite"></span>
        เหตุการณ์ที่ยังดำเนินการอยู่
      </div>
      <a href="<?= url('disasters?status[]=reported&status[]=dispatched&status[]=responding') ?>" style="font-size:12px;color:var(--blue);text-decoration:none;">ดูทั้งหมด →</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>เลขที่</th>
            <th>ประเภท</th>
            <th>อำเภอ</th>
            <th>ความรุนแรง</th>
            <th>สถานะ</th>
            <th>วันที่</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px 0">
            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block;opacity:.3"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            ไม่มีเหตุการณ์ที่ยังดำเนินการอยู่
          </td></tr>
          <?php else: foreach ($recent as $r): ?>
          <tr>
            <td>
              <a href="<?= url('disasters/'.$r['id']) ?>" style="color:var(--blue);text-decoration:none;font-family:var(--font-mono);font-size:12px;">
                <?= e($r['incident_number']) ?>
              </a>
            </td>
            <td>
              <span style="display:flex;align-items:center;gap:6px;">
                <span style="width:8px;height:8px;border-radius:50%;background:<?= e($r['color']) ?>"></span>
                <?= e($r['category_name']) ?>
              </span>
            </td>
            <td><?= e($r['amphure_name']) ?></td>
            <td>
              <?php $sev = \App\Models\Disaster::$severities[$r['severity']] ?? []; ?>
              <span class="badge" style="background:<?= $sev['color'] ?? '#6b7280' ?>22;color:<?= $sev['color'] ?? '#9ca3af' ?>;border-color:<?= $sev['color'] ?? '#6b7280' ?>44">
                <?= $sev['label'] ?? $r['severity'] ?>
              </span>
            </td>
            <td>
              <?php $st = \App\Models\Disaster::$statuses[$r['status']] ?? []; ?>
              <span class="badge" style="background:<?= $st['color'] ?? '#6b7280' ?>22;color:<?= $st['color'] ?? '#9ca3af' ?>;border-color:<?= $st['color'] ?? '#6b7280' ?>44">
                <?= $st['label'] ?? $r['status'] ?>
              </span>
            </td>
            <td style="color:var(--text-secondary);font-size:12px;"><?= format_date($r['incident_date']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Quick Stats Panel -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Closed rate -->
    <div class="card">
      <div class="card-header"><div class="card-title">อัตราการปิดเหตุการณ์</div></div>
      <div class="card-body">
        <?php
          $total  = max(1, (int)($stats['total'] ?? 1));
          $closed = (int)($stats['closed_count'] ?? 0);
          $pct    = round($closed / $total * 100);
        ?>
        <div style="font-size:38px;font-weight:700;color:var(--green);font-variant-numeric:tabular-nums"><?= $pct ?>%</div>
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:12px"><?= $closed ?> / <?= $total ?> เหตุการณ์</div>
        <div style="background:var(--bg-base);border-radius:4px;height:8px;overflow:hidden">
          <div style="width:<?= $pct ?>%;height:100%;background:var(--green);border-radius:4px;transition:width 1s"></div>
        </div>
      </div>
    </div>

    <!-- Catastrophic + Major -->
    <div class="card">
      <div class="card-header"><div class="card-title">เหตุการณ์ร้ายแรง</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:12px;color:var(--text-muted)">ร้ายแรงมาก (Catastrophic)</div>
            <div style="font-size:22px;font-weight:700;color:#ef4444"><?= $stats['catastrophic_count'] ?? 0 ?></div>
          </div>
          <div style="width:40px;height:40px;background:rgba(239,68,68,.12);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <svg width="20" height="20" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
        </div>
        <div style="height:1px;background:var(--border)"></div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:12px;color:var(--text-muted)">รุนแรง (Major)</div>
            <div style="font-size:22px;font-weight:700;color:#f59e0b"><?= $stats['major_count'] ?? 0 ?></div>
          </div>
          <div style="width:40px;height:40px;background:rgba(245,158,11,.12);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <svg width="20" height="20" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Total economic loss -->
    <div class="card" style="--accent:var(--red);border-top:3px solid var(--red)">
      <div class="card-body" style="text-align:center;padding:20px">
        <div style="font-size:11px;color:var(--text-muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px">มูลค่าความเสียหายรวม</div>
        <div style="font-size:22px;font-weight:700;color:var(--red)">
          ฿<?= number_format(($stats['total_loss'] ?? 0) / 1000000, 2) ?> ล้าน
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">บาท</div>
      </div>
    </div>

  </div>
</div>

<?php \App\Core\View::endSection(); ?>

<?php \App\Core\View::section('scripts'); ?>
<script>
Chart.defaults.color         = '#8ba3c4';
Chart.defaults.borderColor   = '#243349';
Chart.defaults.font.family   = "'Noto Sans Thai', sans-serif";
Chart.defaults.font.size     = 12;

const monthNames = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

// Monthly chart data
const monthlyData = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: monthNames,
    datasets: [
      {
        label: 'เหตุการณ์',
        data: monthlyData.map(m => m.total),
        backgroundColor: 'rgba(59,130,246,.6)',
        borderColor: '#3b82f6',
        borderWidth: 1,
        borderRadius: 4,
        yAxisID: 'y',
      },
      {
        label: 'ผู้เสียชีวิต',
        data: monthlyData.map(m => m.dead),
        type: 'line',
        borderColor: '#ef4444',
        backgroundColor: 'rgba(239,68,68,.1)',
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: '#ef4444',
        fill: true,
        tension: 0.4,
        yAxisID: 'y1',
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(36,51,73,.6)' } },
      y: {
        type: 'linear', position: 'left',
        grid: { color: 'rgba(36,51,73,.6)' },
        ticks: { stepSize: 1 },
        title: { display: true, text: 'จำนวนเหตุการณ์', color: '#8ba3c4', font: { size: 10 } }
      },
      y1: {
        type: 'linear', position: 'right',
        grid: { drawOnChartArea: false },
        ticks: { stepSize: 1 },
        title: { display: true, text: 'ผู้เสียชีวิต', color: '#ef4444', font: { size: 10 } }
      }
    }
  }
});

// Category chart
const catData = <?= json_encode($by_category, JSON_UNESCAPED_UNICODE) ?>;
const catLabels = catData.map(c => c.name);
const catValues = catData.map(c => parseInt(c.total));
const catColors = catData.map(c => c.color || '#3b82f6');

const catCtx = document.getElementById('categoryChart');
new Chart(catCtx, {
  type: 'doughnut',
  data: {
    labels: catLabels,
    datasets: [{ data: catValues, backgroundColor: catColors.map(c => c + 'cc'), borderColor: catColors, borderWidth: 1 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} เหตุการณ์` } }
    },
    cutout: '65%'
  }
});

// Custom legend
const legend = document.getElementById('categoryLegend');
catData.filter(c => c.total > 0).forEach(c => {
  legend.innerHTML += `<div style="display:flex;align-items:center;gap:7px;justify-content:space-between">
    <span style="display:flex;align-items:center;gap:5px">
      <span style="width:9px;height:9px;border-radius:3px;background:${c.color};flex-shrink:0"></span>
      <span>${c.name}</span>
    </span>
    <strong style="font-variant-numeric:tabular-nums">${c.total}</strong>
  </div>`;
});

// Amphure chart
const ampData = <?= json_encode($by_amphure, JSON_UNESCAPED_UNICODE) ?>;
new Chart(document.getElementById('amphureChart'), {
  type: 'bar',
  data: {
    labels: ampData.map(a => a.name),
    datasets: [{
      label: 'จำนวนเหตุการณ์',
      data: ampData.map(a => parseInt(a.total)),
      backgroundColor: 'rgba(139,92,246,.6)',
      borderColor: '#8b5cf6',
      borderWidth: 1,
      borderRadius: 4,
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(36,51,73,.6)' }, ticks: { stepSize: 1 } },
      y: { grid: { display: false } }
    }
  }
});

// Severity chart
const sevLabels = ['เล็กน้อย', 'ปานกลาง', 'รุนแรง', 'ร้ายแรงมาก'];
const sevColors = ['#10b981','#f59e0b','#ef4444','#7c2d12'];
const sevData   = <?= json_encode([
  $stats['total'] ?? 0,
  ($stats['major_count'] ?? 0) + ($stats['catastrophic_count'] ?? 0),
  $stats['major_count'] ?? 0,
  $stats['catastrophic_count'] ?? 0,
], JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('severityChart'), {
  type: 'polarArea',
  data: {
    labels: sevLabels,
    datasets: [{ data: sevData, backgroundColor: sevColors.map(c => c + '55'), borderColor: sevColors, borderWidth: 1 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'right', labels: { padding: 10, boxWidth: 10 } } }
  }
});
</script>
<?php \App\Core\View::endSection(); ?>

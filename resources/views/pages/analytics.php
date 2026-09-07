<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div>
    <h1>วิเคราะห์ข้อมูลสาธารณภัย</h1>
    <p>สถิติเชิงลึก แนวโน้ม และการเปรียบเทียบ</p>
  </div>
  <div class="page-header-right">
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <label style="font-size:.85rem;color:var(--neutral-600)">ปีงบประมาณ:</label>
      <select name="year" class="form-control" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>พ.ศ. <?= $y + 543 ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="<?= url('disasters/export/xlsx?year=' . $year) ?>" class="btn btn-outline">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      ส่งออก Excel
    </a>
    <a href="<?= url('disasters/export/pdf?year=' . $year) ?>" class="btn btn-primary">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      ส่งออก PDF
    </a>
  </div>
</div>

<!-- Row 1: Monthly Trend + Category Donut -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">

  <!-- Monthly Bar Chart -->
  <div class="card">
    <div class="card-header">
      <h2>แนวโน้มรายเดือน ปี พ.ศ. <?= $year + 543 ?></h2>
      <div style="display:flex;gap:6px">
        <button class="btn btn-ghost btn-sm" onclick="toggleChartType('monthly','bar')">แท่ง</button>
        <button class="btn btn-ghost btn-sm" onclick="toggleChartType('monthly','line')">เส้น</button>
      </div>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:280px">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Category Donut -->
  <div class="card">
    <div class="card-header"><h2>สัดส่วนประเภทภัย</h2></div>
    <div class="card-body">
      <div class="chart-container" style="height:280px">
        <canvas id="categoryChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Row 2: By District Bar + Severity -->
<div style="display:grid;grid-template-columns:3fr 1fr;gap:20px;margin-bottom:20px">

  <!-- By Amphure -->
  <div class="card">
    <div class="card-header">
      <h2>สถิติรายอำเภอ</h2>
      <select id="amphureMetric" class="form-control" style="width:auto;font-size:.8rem" onchange="updateAmphureChart()">
        <option value="count">จำนวนเหตุการณ์</option>
        <option value="dead">ผู้เสียชีวิต</option>
        <option value="affected">ผู้ได้รับผลกระทบ</option>
        <option value="economic_loss">ความเสียหาย (บาท)</option>
      </select>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px">
        <canvas id="amphureChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Severity -->
  <div class="card">
    <div class="card-header"><h2>ระดับความรุนแรง</h2></div>
    <div class="card-body">
      <div class="chart-container" style="height:260px">
        <canvas id="severityChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Row 3: Multi-year Trend -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h2>แนวโน้ม 5 ปีย้อนหลัง</h2>
    <div style="display:flex;gap:6px">
      <button class="btn btn-ghost btn-sm active-tab" data-metric="incidents" onclick="switchYearlyMetric(this, 'incidents')">เหตุการณ์</button>
      <button class="btn btn-ghost btn-sm" data-metric="dead" onclick="switchYearlyMetric(this, 'dead')">เสียชีวิต</button>
      <button class="btn btn-ghost btn-sm" data-metric="affected" onclick="switchYearlyMetric(this, 'affected')">ผลกระทบ</button>
      <button class="btn btn-ghost btn-sm" data-metric="economic_loss" onclick="switchYearlyMetric(this, 'economic_loss')">ความเสียหาย</button>
    </div>
  </div>
  <div class="card-body">
    <div class="chart-container" style="height:240px">
      <canvas id="yearlyChart"></canvas>
    </div>
  </div>
</div>

<!-- Row 4: Category Details Table -->
<div class="card">
  <div class="card-header"><h2>รายละเอียดสถิติรายประเภทภัย</h2></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>ประเภทภัย</th>
          <th style="text-align:right">จำนวนครั้ง</th>
          <th style="text-align:right">ร้อยละ</th>
          <th style="text-align:right">ผู้เสียชีวิต</th>
          <th style="text-align:right">ผู้บาดเจ็บ</th>
          <th style="text-align:right">ผู้ได้รับผลกระทบ</th>
          <th style="text-align:right">ความเสียหาย (บาท)</th>
          <th>สัดส่วน</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= e($cat['color'] ?? '#2471A3') ?>;flex-shrink:0"></span>
              <?= e($cat['name']) ?>
            </div>
          </td>
          <td style="text-align:right;font-weight:600"><?= number_format((int)$cat['count']) ?></td>
          <td style="text-align:right"><?= number_format((float)$cat['percentage'], 1) ?>%</td>
          <td style="text-align:right;color:var(--danger)"><?= number_format((int)$cat['dead']) ?></td>
          <td style="text-align:right;color:var(--warning)"><?= number_format((int)($cat['injured'] ?? 0)) ?></td>
          <td style="text-align:right"><?= number_format((int)$cat['affected']) ?></td>
          <td style="text-align:right">฿<?= number_format((float)$cat['economic_loss']) ?></td>
          <td style="min-width:120px">
            <div style="background:var(--neutral-200);border-radius:4px;height:8px;overflow:hidden">
              <div style="background:<?= e($cat['color'] ?? '#2471A3') ?>;height:100%;width:<?= number_format((float)$cat['percentage'], 1) ?>%;border-radius:4px;transition:width .8s"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Sarabun', sans-serif";
Chart.defaults.color = '#595980';
Chart.defaults.plugins.legend.labels.usePointStyle = true;

// ── Data from PHP ─────────────────────────────────────────────
const monthlyData = <?= json_encode([
  'labels'    => array_column($monthly, 'month'),
  'incidents' => array_column($monthly, 'incidents'),
  'dead'      => array_column($monthly, 'dead'),
  'affected'  => array_column($monthly, 'affected'),
], JSON_UNESCAPED_UNICODE) ?>;

const thaiMonths = ['', 'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
const allMonths  = Array.from({length:12}, (_,i) => thaiMonths[i+1]);
const incidents  = allMonths.map((_,i) => {
  const idx = monthlyData.labels.indexOf(String(i+1));
  return idx >= 0 ? monthlyData.incidents[idx] : 0;
});
const dead = allMonths.map((_,i) => {
  const idx = monthlyData.labels.indexOf(String(i+1));
  return idx >= 0 ? parseInt(monthlyData.dead[idx]) : 0;
});

// ── Monthly Chart ─────────────────────────────────────────────
let monthlyChart = new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: allMonths,
    datasets: [
      { label: 'จำนวนเหตุการณ์', data: incidents, backgroundColor: 'rgba(36,113,163,.7)', borderRadius: 6, borderSkipped: false },
      { label: 'ผู้เสียชีวิต', data: dead, backgroundColor: 'rgba(192,57,43,.7)', borderRadius: 6, borderSkipped: false, yAxisID: 'y1' },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
      y:  { beginAtZero: true, title: { display: true, text: 'จำนวนเหตุการณ์' } },
      y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'ผู้เสียชีวิต' }, grid: { drawOnChartArea: false } },
    }
  }
});

function toggleChartType(name, type) {
  monthlyChart.config.type = type;
  monthlyChart.update();
}

// ── Category Donut ────────────────────────────────────────────
const catData = <?= json_encode([
  'labels' => array_column($categories, 'name'),
  'data'   => array_column($categories, 'count'),
  'colors' => array_column($categories, 'color'),
], JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('categoryChart'), {
  type: 'doughnut',
  data: {
    labels: catData.labels,
    datasets: [{ data: catData.data, backgroundColor: catData.colors.map(c => c || '#2471A3'), borderWidth: 2, borderColor: '#fff' }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } } },
    cutout: '62%',
  }
});

// ── Amphure Chart ─────────────────────────────────────────────
const ampData = <?= json_encode([
  'labels'        => array_column($amphures, 'name'),
  'count'         => array_column($amphures, 'count'),
  'dead'          => array_column($amphures, 'dead'),
  'affected'      => array_column($amphures, 'affected'),
  'economic_loss' => array_column($amphures, 'economic_loss'),
], JSON_UNESCAPED_UNICODE) ?>;

let amphureChart = new Chart(document.getElementById('amphureChart'), {
  type: 'bar',
  data: {
    labels: ampData.labels,
    datasets: [{ label: 'จำนวนเหตุการณ์', data: ampData.count, backgroundColor: 'rgba(36,113,163,.75)', borderRadius: 5 }]
  },
  options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true } },
  }
});

function updateAmphureChart() {
  const metric = document.getElementById('amphureMetric').value;
  amphureChart.data.datasets[0].data  = ampData[metric];
  amphureChart.data.datasets[0].label = document.getElementById('amphureMetric').selectedOptions[0].text;
  amphureChart.update();
}

// ── Severity ──────────────────────────────────────────────────
const sevData = <?= json_encode([
  'labels' => array_column($severity, 'severity'),
  'data'   => array_column($severity, 'count'),
], JSON_UNESCAPED_UNICODE) ?>;

const sevColors = { minor:'#27AE60', moderate:'#F39C12', major:'#E67E22', catastrophic:'#C0392B' };
const sevLabels = { minor:'เล็กน้อย', moderate:'ปานกลาง', major:'รุนแรง', catastrophic:'ร้ายแรงมาก' };

new Chart(document.getElementById('severityChart'), {
  type: 'pie',
  data: {
    labels: sevData.labels.map(l => sevLabels[l] || l),
    datasets: [{ data: sevData.data, backgroundColor: sevData.labels.map(l => sevColors[l] || '#ccc'), borderWidth: 2, borderColor: '#fff' }]
  },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});

// ── Yearly Trend ──────────────────────────────────────────────
const yrData = <?= json_encode([
  'labels'        => array_map(fn($r) => 'พ.ศ. ' . ((int)$r['year'] + 543), $yearly_trend),
  'incidents'     => array_column($yearly_trend, 'incidents'),
  'dead'          => array_column($yearly_trend, 'dead'),
  'affected'      => array_column($yearly_trend, 'affected'),
  'economic_loss' => array_column($yearly_trend, 'economic_loss'),
], JSON_UNESCAPED_UNICODE) ?>;

let yearlyChart = new Chart(document.getElementById('yearlyChart'), {
  type: 'line',
  data: {
    labels: yrData.labels,
    datasets: [{
      label: 'จำนวนเหตุการณ์', data: yrData.incidents,
      borderColor: '#2471A3', backgroundColor: 'rgba(36,113,163,.1)',
      borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 5,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } },
  }
});

function switchYearlyMetric(btn, metric) {
  document.querySelectorAll('[data-metric]').forEach(b => b.classList.remove('active-tab'));
  btn.classList.add('active-tab');
  yearlyChart.data.datasets[0].data  = yrData[metric];
  yearlyChart.update();
}
</script>

<?php \App\Core\View::endSection(); ?>

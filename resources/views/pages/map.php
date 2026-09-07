<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div>
    <h1>แผนที่สาธารณภัย</h1>
    <p>ตำแหน่งเหตุการณ์สาธารณภัยจังหวัดระยอง — <span id="mapPointCount">กำลังโหลด...</span></p>
  </div>
  <div class="page-header-right">
    <select id="filterYear" class="form-control" style="width:auto">
      <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>พ.ศ. <?= $y + 543 ?></option>
      <?php endforeach; ?>
    </select>
    <select id="filterCategory" class="form-control" style="width:auto">
      <option value="">ทุกประเภทภัย</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="filterSeverity" class="form-control" style="width:auto">
      <option value="">ทุกระดับ</option>
      <option value="minor">เล็กน้อย</option>
      <option value="moderate">ปานกลาง</option>
      <option value="major">รุนแรง</option>
      <option value="catastrophic">ร้ายแรงมาก</option>
    </select>
    <button class="btn btn-primary" onclick="loadMapData()">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
      โหลดข้อมูล
    </button>
  </div>
</div>

<!-- Layout: Map + Sidebar Panel -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;height:calc(100vh - 180px)">

  <!-- Map -->
  <div class="card" style="overflow:hidden;padding:0">
    <!-- Map Legend -->
    <div style="position:absolute;z-index:1000;top:80px;right:340px;background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-md);padding:12px 14px;font-size:.8rem;pointer-events:none">
      <div style="font-weight:700;margin-bottom:8px;color:var(--brand-800)">ระดับความรุนแรง</div>
      <?php foreach ([['เล็กน้อย','#27AE60'],['ปานกลาง','#F39C12'],['รุนแรง','#E67E22'],['ร้ายแรงมาก','#C0392B']] as [$label, $color]): ?>
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
        <span style="width:12px;height:12px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;border:2px solid rgba(0,0,0,.15)"></span>
        <?= $label ?>
      </div>
      <?php endforeach; ?>
      <div style="border-top:1px solid var(--neutral-200);margin-top:8px;padding-top:8px;font-weight:700;color:var(--brand-800)">ความหนาแน่น</div>
      <div style="display:flex;align-items:center;gap:4px;margin-top:6px">
        <div style="width:60px;height:8px;background:linear-gradient(90deg,rgba(36,113,163,.2),rgba(192,57,43,.9));border-radius:4px"></div>
        <span>น้อย → มาก</span>
      </div>
    </div>
    <div id="map" style="height:100%;width:100%"></div>
  </div>

  <!-- Right Panel -->
  <div style="display:flex;flex-direction:column;gap:12px;overflow-y:auto">

    <!-- Province Stats -->
    <div class="card">
      <div class="card-header"><h2>สรุปรายอำเภอ</h2></div>
      <div style="max-height:220px;overflow-y:auto">
        <table class="data-table" style="font-size:.8rem">
          <thead>
            <tr><th>อำเภอ</th><th style="text-align:right">ครั้ง</th><th style="text-align:right">เสียชีวิต</th></tr>
          </thead>
          <tbody id="amphureTableBody">
            <tr><td colspan="3" style="text-align:center;padding:16px;color:var(--neutral-400)">กำลังโหลด...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Selected Incident Detail -->
    <div class="card" id="incidentPanel" style="display:none">
      <div class="card-header">
        <h2>รายละเอียดเหตุการณ์</h2>
        <button class="btn btn-ghost btn-sm" onclick="document.getElementById('incidentPanel').style.display='none'">✕</button>
      </div>
      <div class="card-body" id="incidentDetail" style="font-size:.85rem"></div>
    </div>

    <!-- Active Incidents List -->
    <div class="card" style="flex:1">
      <div class="card-header"><h2 id="listTitle">เหตุการณ์ล่าสุด</h2></div>
      <div id="incidentList" style="max-height:300px;overflow-y:auto;font-size:.82rem"></div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.leaflet-container { font-family: 'Sarabun', sans-serif !important; }
.custom-marker-icon { display:flex;align-items:center;justify-content:center;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid rgba(0,0,0,.2);box-shadow:0 2px 6px rgba(0,0,0,.3); }
.custom-marker-icon span { transform:rotate(45deg); }
</style>

<script>
// Initialize Leaflet map centered on Rayong
const map = L.map('map', { center: [12.6817, 101.2819], zoom: 10, zoomControl: true });

// Base layers
const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap', maxZoom: 18
});

const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
  attribution: '© Esri', maxZoom: 18
});

osm.addTo(map);
L.control.layers({'แผนที่ถนน': osm, 'ภาพดาวเทียม': satellite}, {}, { position: 'topright' }).addTo(map);

// State
let markerLayer = L.layerGroup().addTo(map);
let heatData    = [];

// Severity colors & sizes
const sevConfig = {
  minor:        { color: '#27AE60', size: 10 },
  moderate:     { color: '#F39C12', size: 14 },
  major:        { color: '#E67E22', size: 18 },
  catastrophic: { color: '#C0392B', size: 24 },
};
const sevLabels = { minor:'เล็กน้อย', moderate:'ปานกลาง', major:'รุนแรง', catastrophic:'ร้ายแรงมาก' };

// Load map data
async function loadMapData() {
  const year     = document.getElementById('filterYear').value;
  const category = document.getElementById('filterCategory').value;
  const severity = document.getElementById('filterSeverity').value;

  const params = new URLSearchParams({ year });
  if (category) params.append('category_id', category);
  if (severity) params.append('severity', severity);

  const res  = await rdsisFetch('/map/data?' + params);
  const data = await res.json();

  renderPoints(data.points || []);
  renderAmphureTable(data.amphures || []);
}

function renderPoints(points) {
  markerLayer.clearLayers();

  document.getElementById('mapPointCount').textContent = points.length + ' จุดเหตุการณ์';

  points.forEach(p => {
    if (!p.latitude || !p.longitude) return;

    const cfg  = sevConfig[p.severity] || sevConfig.minor;
    const size = cfg.size;

    const icon = L.divIcon({
      html: `<div class="custom-marker-icon" style="width:${size}px;height:${size}px;background:${cfg.color}"><span></span></div>`,
      iconSize: [size, size],
      iconAnchor: [size/2, size],
      className: '',
    });

    const marker = L.marker([p.latitude, p.longitude], { icon });

    marker.bindPopup(`
      <div style="font-family:'Sarabun',sans-serif;min-width:220px;padding:4px">
        <div style="font-weight:700;font-size:.95rem;color:#142B47;margin-bottom:6px">
          ${p.category_icon || '⚠️'} ${p.category_name}
        </div>
        <div style="font-size:.82rem;color:#595980;margin-bottom:8px">${p.incident_number} | ${p.incident_date}</div>
        <div style="margin-bottom:4px"><b>อำเภอ:</b> ${p.amphure_name}</div>
        <div style="margin-bottom:4px">
          <b>ระดับ:</b>
          <span style="background:${cfg.color};color:#fff;padding:2px 6px;border-radius:10px;font-size:.75rem">${sevLabels[p.severity]}</span>
        </div>
        ${p.casualties_dead > 0 ? `<div style="color:#C0392B;margin-bottom:4px"><b>เสียชีวิต:</b> ${p.casualties_dead} ราย</div>` : ''}
        ${p.affected_people > 0 ? `<div style="margin-bottom:4px"><b>ผลกระทบ:</b> ${parseInt(p.affected_people).toLocaleString('th')} ราย</div>` : ''}
        <div style="font-size:.8rem;color:#7F7FA0;margin-top:6px;border-top:1px solid #eee;padding-top:6px">${p.description?.substring(0,100) || ''}${p.description?.length > 100 ? '...' : ''}</div>
        <a href="/disasters/${p.id}" style="display:block;margin-top:8px;color:#2471A3;font-size:.8rem;text-decoration:none">→ ดูรายละเอียด</a>
      </div>
    `, { maxWidth: 280 });

    marker.addTo(markerLayer);
  });

  renderIncidentList(points);
}

function renderAmphureTable(amphures) {
  const tbody = document.getElementById('amphureTableBody');
  if (!amphures.length) {
    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:12px;color:var(--neutral-400)">ไม่มีข้อมูล</td></tr>';
    return;
  }
  tbody.innerHTML = amphures.map((a, i) => `
    <tr style="${i % 2 === 0 ? 'background:var(--neutral-50)' : ''}">
      <td>${a.name}</td>
      <td style="text-align:right;font-weight:600">${parseInt(a.count).toLocaleString('th')}</td>
      <td style="text-align:right;color:var(--danger)">${parseInt(a.dead || 0).toLocaleString('th')}</td>
    </tr>`).join('');
}

function renderIncidentList(points) {
  const list = document.getElementById('incidentList');
  document.getElementById('listTitle').textContent = 'เหตุการณ์ (' + points.length + ' รายการ)';

  if (!points.length) {
    list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--neutral-400)">ไม่มีข้อมูล</div>';
    return;
  }

  list.innerHTML = points.slice(0, 30).map(p => {
    const cfg = sevConfig[p.severity] || sevConfig.minor;
    return `
    <div style="padding:10px 14px;border-bottom:1px solid var(--neutral-100);cursor:pointer;transition:background var(--transition)"
         onmouseover="this.style.background='var(--brand-50)'" onmouseout="this.style.background='#fff'"
         onclick="focusPoint(${p.latitude},${p.longitude})">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">
        <span style="width:8px;height:8px;border-radius:50%;background:${cfg.color};flex-shrink:0"></span>
        <span style="font-weight:600;font-size:.8rem">${p.incident_number}</span>
        <span style="font-size:.7rem;color:var(--neutral-500);margin-left:auto">${p.incident_date}</span>
      </div>
      <div style="font-size:.78rem;color:var(--neutral-600)">${p.category_name} · ${p.amphure_name}</div>
      ${p.casualties_dead > 0 ? `<div style="font-size:.72rem;color:var(--danger)">เสียชีวิต ${p.casualties_dead} ราย</div>` : ''}
    </div>`;
  }).join('');
}

function focusPoint(lat, lng) {
  map.flyTo([lat, lng], 14, { duration: 1 });
}

// Initial load
loadMapData();

// Auto-reload on filter change
['filterYear','filterCategory','filterSeverity'].forEach(id => {
  document.getElementById(id)?.addEventListener('change', loadMapData);
});
</script>

<?php \App\Core\View::endSection(); ?>

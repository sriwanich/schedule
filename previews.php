
<?php
Header always unset X-Frame-Options
Header always set Content-Security-Policy "frame-ancestors 'self' https://your-parent-domain.com"
// preview.php - แผนที่สาธารณะ ระบบบริหารจัดการศูนย์พักพิงจังหวัดระยอง (Enterprise Edition)
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';
 
$pageTitle = 'แผนที่ศูนย์พักพิงอัจฉริยะ';
$db = Database::getInstance();
 
// ดึงข้อมูลการตั้งค่าระบบด้วยประสิทธิภาพสูง
$settings = $db->fetchAll("SELECT key_name, value FROM settings");
$cfg = [];
foreach ($settings as $s) {
    $cfg[$s['key_name']] = $s['value'];
}
 
// ค่าเริ่มต้นความปลอดภัยสูง — จุดกลางจังหวัดระยอง
$mapCenterLat = $cfg['map_center_lat'] ?? '12.706835';
$mapCenterLng = $cfg['map_center_lng'] ?? '101.182877';
$mapZoom      = (int)($cfg['map_zoom'] ?? 11);
$siteName     = $cfg['site_name'] ?? 'ระบบบริหารจัดการศูนย์พักพิงจังหวัดระยอง';
$agencyName   = $cfg['agency_name'] ?? 'สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง';
$agencyPhone  = $cfg['agency_phone'] ?? '038694129';
$logoPath     = !empty($cfg['site_logo']) && file_exists(UPLOAD_PATH . $cfg['site_logo'])
    ? BASE_URL . '/uploads/' . $cfg['site_logo']
    : null;
 
// ดึงข้อมูลอำเภอที่มีการใช้งานอยู่จริง
$districtsRaw = $db->fetchAll(
    "SELECT DISTINCT district FROM checkpoints WHERE status = 'active' AND district IS NOT NULL AND district != '' ORDER BY district"
);
$districtsList = array_column($districtsRaw, 'district');
$districtsListJson = json_encode($districtsList, JSON_UNESCAPED_UNICODE);

// ดึงพิกัดที่ว่าการอำเภอจากฐานข้อมูล (แทนค่า hardcode เดิม)
$districtOfficesRaw = $db->fetchAll(
    "SELECT name, office_lat, office_lng, office_name, office_address, office_phone, office_image
     FROM districts
     WHERE office_lat IS NOT NULL AND office_lng IS NOT NULL
     ORDER BY id ASC"
);
$districtOfficesMap = [];
foreach ($districtOfficesRaw as $do) {
    $districtOfficesMap[$do['name']] = [
        'lat'     => (float)$do['office_lat'],
        'lng'     => (float)$do['office_lng'],
        'name'    => $do['office_name'] ?: ('ที่ว่าการอำเภอ' . $do['name']),
        'address' => $do['office_address'] ?? '',
        'phone'   => $do['office_phone'] ?? '',
        'image'   => $do['office_image'] ? (BASE_URL . '/uploads/' . $do['office_image']) : '',
    ];
}
$districtOfficesJson = json_encode($districtOfficesMap, JSON_UNESCAPED_UNICODE);
 
// ดึงข้อมูลประเภทศูนย์พักพิงทั้งหมด
$checkpointTypesList = $db->fetchAll(
    "SELECT id, name, code, color, icon, description FROM checkpoint_types WHERE is_active = 1 ORDER BY sort_order"
);
$checkpointTypesJson = json_encode($checkpointTypesList, JSON_UNESCAPED_UNICODE);
 
// ดึงข้อมูลศูนย์พักพิง Active พร้อมข้อมูลสิ่งอำนวยความสะดวก เสบียง และข้อมูลสถิติประชากร
$checkpoints = $db->fetchAll(
    "SELECT c.*, 
            ct.name as type_name, ct.color as type_color, ct.icon as type_icon, ct.code as type_code,
            ci.filename as primary_image
     FROM checkpoints c
     JOIN checkpoint_types ct ON c.type_id = ct.id
     LEFT JOIN checkpoint_images ci ON ci.checkpoint_id = c.id AND ci.is_primary = 1
     WHERE c.status = 'active'
     ORDER BY c.name"
);
 
// ดึงข้อมูลเหตุการณ์ภัยพิบัติล่าสุด (เฉพาะเหตุการณ์ที่กำลังเกิดขึ้น)
$incidents = $db->fetchAll(
    "SELECT i.*, it.name as type_name, it.color as type_color, c.name as checkpoint_name
     FROM incidents i
     JOIN incident_types it ON i.type_id = it.id
     LEFT JOIN checkpoints c ON i.checkpoint_id = c.id
     WHERE i.status IN ('open', 'in_progress')
     ORDER BY i.severity DESC, i.created_at DESC
     LIMIT 30"
);
 
// คำนวณสถิติภาพรวมระดับบริหาร (KPI)
$totalCheckpoints  = count($checkpoints);
$totalCapacity     = array_sum(array_column($checkpoints, 'capacity'));
$totalOccupancy    = array_sum(array_column($checkpoints, 'current_occupancy'));
$totalIncidents    = count($incidents);
$criticalIncidents = count(array_filter($incidents, fn($i) => $i['severity'] === 'critical'));
$occupancyPercent  = $totalCapacity > 0 ? round(($totalOccupancy / $totalCapacity) * 100) : 0;
 
// คำนวณสถิติเชิงลึกแยกตามพื้นที่อำเภอ
$statsByDistrict = [];
foreach ($checkpoints as $cp) {
    $dname = $cp['district'] ?: 'ไม่ระบุพื้นที่';
    if (!isset($statsByDistrict[$dname])) {
        $statsByDistrict[$dname] = ['count' => 0, 'capacity' => 0, 'occupancy' => 0];
    }
    $statsByDistrict[$dname]['count']++;
    $statsByDistrict[$dname]['capacity']  += (int)$cp['capacity'];
    $statsByDistrict[$dname]['occupancy'] += (int)$cp['current_occupancy'];
}
uasort($statsByDistrict, fn($a,$b) => $b['count'] - $a['count']);
$statsByDistrictJson = json_encode($statsByDistrict, JSON_UNESCAPED_UNICODE);
 
// แมปข้อมูลลงโครงสร้าง JSON สำหรับนำไปใช้งานใน JavaScript Core Layer
$checkpointsJson = [];
foreach ($checkpoints as $cp) {
    $occupancyPct = ($cp['capacity'] > 0) ? round(($cp['current_occupancy'] / $cp['capacity']) * 100) : 0;
    $checkpointsJson[] = [
        'id'                 => $cp['id'],
        'name'               => $cp['name'],
        'lat'                => (float)$cp['latitude'],
        'lng'                => (float)$cp['longitude'],
        'type_id'            => (int)$cp['type_id'],
        'type'               => $cp['type_name'],
        'type_color'         => $cp['type_color'],
        'type_icon'          => $cp['type_icon'],
        'type_code'          => $cp['type_code'],
        'province'           => 'ระยอง',
        'district'           => $cp['district'],
        'sub_district'       => $cp['sub_district'],
        'address'            => $cp['address'],
        'description'        => $cp['description'],
        'department'         => $cp['department'],
        'contact_name'       => $cp['contact_name'],
        'contact_phone'      => $cp['contact_phone'],
        'contact_phone2'     => $cp['contact_phone2'],
        'emergency_phone'    => $cp['emergency_phone'],
        'manager_name'       => $cp['manager_name'],
        'officer_count'      => (int)$cp['officer_count'],
        'capacity'           => (int)$cp['capacity'],
        'current_occupancy'  => (int)$cp['current_occupancy'],
        'occupancy_pct'      => $occupancyPct,
        'occupancy_male'     => (int)($cp['occupancy_male'] ?? 0),
        'occupancy_female'   => (int)($cp['occupancy_female'] ?? 0),
        'occupancy_child'    => (int)($cp['occupancy_child'] ?? 0),
        'occupancy_elderly'  => (int)($cp['occupancy_elderly'] ?? 0),
        'occupancy_disabled' => (int)($cp['occupancy_disabled'] ?? 0),
        'occupancy_pregnant' => (int)($cp['occupancy_pregnant'] ?? 0),
        'occupancy_bedridden'=> (int)($cp['occupancy_bedridden'] ?? 0),
        
        // สิ่งอำนวยความสะดวก
        'facility_toilet'          => (bool)$cp['facility_toilet'],
        'facility_water'           => (bool)$cp['facility_water'],
        'facility_electricity'     => (bool)$cp['facility_electricity'],
        'facility_generator'       => (bool)$cp['facility_generator'],
        'facility_kitchen'         => (bool)$cp['facility_kitchen'],
        'facility_medical'         => (bool)$cp['facility_medical'],
        'facility_internet'        => (bool)$cp['facility_internet'],
        'facility_disabled_access' => (bool)$cp['facility_disabled_access'],
        'facility_charging'        => (bool)($cp['facility_charging'] ?? false),
        'facility_women_room'      => (bool)($cp['facility_women_room'] ?? false),
        'facility_child_area'      => (bool)($cp['facility_child_area'] ?? false),
        'facility_parking'         => (bool)($cp['facility_parking'] ?? false),
        'facility_aircondition'    => (bool)($cp['facility_aircondition'] ?? false),
        'facility_laundry'         => (bool)($cp['facility_laundry'] ?? false),
        'facility_pet_area'        => (bool)($cp['facility_pet_area'] ?? false),
        
        // ข้อมูลโครงสร้างสุขอนามัย
        'toilet_male_count'    => (int)($cp['toilet_male_count'] ?? 0),
        'toilet_female_count'  => (int)($cp['toilet_female_count'] ?? 0),
        'shower_male_count'    => (int)($cp['shower_male_count'] ?? 0),
        'shower_female_count'  => (int)($cp['shower_female_count'] ?? 0),
        'toilet_notes'         => $cp['toilet_notes'] ?? '',
        
        // คลังสิ่งของบรรเทาทุกข์
        'bedding_sets'         => (int)($cp['bedding_sets'] ?? 0),
        'blanket_count'        => (int)($cp['blanket_count'] ?? 0),
        'pillow_count'         => (int)($cp['pillow_count'] ?? 0),
        'mat_count'            => (int)($cp['mat_count'] ?? 0),
        'supply_clothing_sets' => (int)($cp['supply_clothing_sets'] ?? 0),
        'supply_hygiene_kits'  => (int)($cp['supply_hygiene_kits'] ?? 0),
        'supply_notes'         => $cp['supply_notes'] ?? '',
        
        // เสบียงอาหารและพลังงานหลัก
        'food_supply_days'    => (float)($cp['food_supply_days'] ?? 0),
        'water_supply_liters' => (int)($cp['water_supply_liters'] ?? 0),
        'disaster_type'       => $cp['disaster_type'],
        'operation_hours'     => $cp['operation_hours'],
        'status'              => $cp['status'],
        'image_url'           => !empty($cp['primary_image']) ? BASE_URL . '/uploads/' . $cp['primary_image'] : null
    ];
}
 
$incidentsJson = [];
foreach ($incidents as $inc) {
    $incidentsJson[] = [
        'id'         => $inc['id'],
        'title'      => $inc['title'],
        'lat'        => $inc['latitude'] ? (float)$inc['latitude'] : null,
        'lng'        => $inc['longitude'] ? (float)$inc['longitude'] : null,
        'type'       => $inc['type_name'],
        'type_color' => $inc['type_color'],
        'severity'   => $inc['severity'],
        'status'     => $inc['status'],
        'checkpoint' => $inc['checkpoint_name'],
        'created_at' => function_exists('thaiDate') ? thaiDate($inc['created_at']) : $inc['created_at']
    ];
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="#090d16" id="themeColorMeta">
<meta name="description" content="ระบบแผนที่ภูมิสารสนเทศแสดงพิกัดศูนย์พักพิงจังหวัดระยอง ข้อมูลแบบความแม่นยำสูง Real-time">
<title><?= sanitize($siteName) ?> - แผนที่ศูนย์พักพิงระดับบริหาร</title>
 
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
 
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
 
<style>
/* ================================================================
   ENTERPRISE DESIGN CORE TOKENS
   ================================================================ */
:root {
  --accent:       #2563eb;
  --accent-light: #60a5fa;
  --success:      #10b981;
  --warning:      #f59e0b;
  --danger:       #ef4444;
  --critical:     #dc2626;
  --radius-sm:    8px;
  --radius-md:    12px;
  --radius-lg:    20px;
  --font:         'Anuphan', sans-serif;
  --transition:   all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  --header-h:     64px;
  --statsbar-h:   48px;
  --bottom-nav-h: 68px;
}
 
[data-theme="dark"] {
  --bg-base:        #090d16;
  --bg-surface:     #111827;
  --bg-card:        #1f2937;
  --bg-hover:       #374151;
  --border:         #374151;
  --border-light:   #4b5563;
  --text-primary:   #f9fafb;
  --text-secondary: #9ca3af;
  --text-muted:     #6b7280;
  --accent-glow:    rgba(37, 99, 235, 0.25);
  --shadow-md:      0 4px 12px rgba(0, 0, 0, 0.4);
  --shadow-lg:      0 12px 32px rgba(0, 0, 0, 0.6);
  --map-bg:         #111827;
  --scrollbar-thumb: #4b5563;
  --glass-bg:       rgba(31, 41, 55, 0.8);
}
 
[data-theme="light"] {
  --bg-base:        #f3f4f6;
  --bg-surface:     #ffffff;
  --bg-card:        #ffffff;
  --bg-hover:       #f9fafb;
  --border:         #e5e7eb;
  --border-light:   #d1d5db;
  --text-primary:   #111827;
  --text-secondary: #4b5563;
  --text-muted:     #9ca3af;
  --accent-glow:    rgba(37, 99, 235, 0.12);
  --shadow-md:      0 4px 12px rgba(0, 0, 0, 0.05);
  --shadow-lg:      0 12px 32px rgba(0, 0, 0, 0.1);
  --map-bg:         #e5e7eb;
  --scrollbar-thumb: #cbd5e1;
  --glass-bg:       rgba(255, 255, 255, 0.85);
}
 
/* Base Body CSS Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--font);
  background: var(--bg-base);
  color: var(--text-primary);
  font-size: 14px;
  line-height: 1.6;
  overflow: hidden;
  transition: background 0.3s ease, color 0.3s ease;
  padding-top: env(safe-area-inset-top);
  padding-left: env(safe-area-inset-left);
  padding-right: env(safe-area-inset-right);
}
 
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 4px; }
 
/* ================================================================
   HEADER & CONTROL LAYOUT
   ================================================================ */
.preview-header {
  position: relative;
  z-index: 1000;
  background: var(--bg-surface);
  border-bottom: 1px solid var(--border);
  height: var(--header-h);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
}
 
.header-brand { display: flex; align-items: center; gap: 14px; min-width: 0; }
.header-logo {
  width: 42px; height: 42px; border-radius: var(--radius-md);
  background: linear-gradient(135deg, #2563eb, #06b6d4);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 20px; flex-shrink: 0; box-shadow: var(--shadow-md);
}
.header-logo img { width: 100%; height: 100%; object-fit: cover; }
 
.header-title h1 {
  font-size: 16px; font-weight: 700; color: var(--text-primary);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;
}
.header-title p { font-size: 11px; color: var(--text-secondary); }
 
.header-agency-badge { display: none; }
 
/* Filter Engine Style UI */
.desktop-filter-bar { display: flex; align-items: center; gap: 12px; flex: 1; justify-content: center; max-width: 800px; margin: 0 20px; }
.filter-group {
  display: flex; align-items: center; gap: 8px; background: var(--bg-base);
  border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 6px 12px; height: 38px; width: 100%;
}
.filter-group label { font-size: 12px; color: var(--text-muted); white-space: nowrap; font-weight: 500; }
.filter-group select, .filter-group input {
  background: transparent; border: none; color: var(--text-primary); font-family: var(--font);
  font-size: 13px; outline: none; width: 100%; cursor: pointer;
}
.filter-group select option { background: var(--bg-surface); color: var(--text-primary); }
 
.btn-hdr {
  padding: 0 16px; height: 38px; border-radius: var(--radius-sm); font-size: 13px;
  font-weight: 600; font-family: var(--font); cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; transition: var(--transition);
}
.btn-hdr-primary { background: var(--accent); color: #fff; }
.btn-hdr-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
.btn-hdr-ghost { background: var(--bg-hover); border: 1px solid var(--border); color: var(--text-secondary); }
.btn-hdr-ghost:hover { color: var(--text-primary); background: var(--border); }
 
.header-actions { display: flex; align-items: center; gap: 8px; }
.icon-btn {
  width: 38px; height: 38px; border-radius: var(--radius-sm); background: var(--bg-surface);
  border: 1px solid var(--border); color: var(--text-secondary); display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 14px; transition: var(--transition); text-decoration: none;
}
.icon-btn:hover, .icon-btn.active { border-color: var(--accent); color: var(--accent-light); background: var(--bg-hover); }
 
/* ================================================================
   STATS INTEGRATED SYSTEM BAR
   ================================================================ */
.stats-bar {
  background: var(--bg-surface); border-bottom: 1px solid var(--border);
  height: var(--statsbar-h); display: flex; align-items: center; padding: 0 24px; overflow-x: auto;
}
.sb-item { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 0 16px; border-right: 1px solid var(--border); white-space: nowrap; }
.sb-item:last-child { border-right: none; }
.sb-item strong { font-size: 15px; font-weight: 700; color: var(--text-primary); }
.sb-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
 
.occ-wrap { display: flex; align-items: center; gap: 10px; }
.occ-track { width: 80px; height: 6px; background: var(--bg-base); border-radius: 4px; overflow: hidden; }
.occ-fill { height: 100%; transition: width 0.5s ease-in-out; }
.occ-low { background: var(--success); }
.occ-med { background: var(--warning); }
.occ-high { background: var(--danger); }
 
/* Advanced Expandable Analytical Panel */
.stats-panel {
  background: var(--bg-surface); border-bottom: 1px solid var(--border);
  max-height: 0; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.stats-panel.open { max-height: 400px; overflow-y: auto; }
.stats-panel-inner { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; padding: 20px 24px; }
.district-card { background: var(--bg-base); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px; box-shadow: var(--shadow-md); }
.district-card-head { display: flex; justify-content: space-between; align-items: center; font-weight: 600; margin-bottom: 8px; }
.district-badge { background: var(--accent-glow); color: var(--accent-light); border-radius: 30px; padding: 2px 10px; font-size: 11px; font-weight: 700; border: 1px solid rgba(59,130,246,0.2); }
.district-occ-row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-secondary); margin-top: 6px; }
.district-occ-track { flex: 1; height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; }
.district-occ-fill { height: 100%; border-radius: 3px; }
 
/* ================================================================
   GEOSPATIAL EXPERT MAP COMPONENT
   ================================================================ */
.map-wrapper {
  position: relative;
  height: calc(100vh - var(--header-h) - var(--statsbar-h));
  background: var(--map-bg);
}
#map { width: 100%; height: 100%; z-index: 1; }
 
/* Marker Cluster Modern Enterprise Theme Styling Override */
.marker-cluster-small, .marker-cluster-medium, .marker-cluster-large { background-color: rgba(37, 99, 235, 0.2) !important; }
.marker-cluster-small div, .marker-cluster-medium div, .marker-cluster-large div { background-color: var(--accent) !important; color: white !important; font-family: var(--font) !important; font-weight: 700 !important; }
 
/* Advanced Popup UI layout styling */
.leaflet-popup-content-wrapper { background: var(--glass-bg) !important; backdrop-filter: blur(12px) !important; border: 1px solid var(--border) !important; border-radius: var(--radius-md) !important; box-shadow: var(--shadow-lg) !important; color: var(--text-primary) !important; padding: 0 !important; overflow: hidden; }
.leaflet-popup-content { margin: 0 !important; width: 320px !important; }
.leaflet-popup-tip { background: var(--bg-card) !important; border: 1px solid var(--border) !important; }
 
.popup-header-img { width: 100%; height: 140px; object-fit: cover; background: #2d3748; }
.popup-body { padding: 16px; max-height: 340px; overflow-y: auto; }
.popup-title { font-size: 16px; font-weight: 700; margin-bottom: 6px; color: var(--text-primary); line-height: 1.4; }
.popup-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-bottom: 12px; }
 
.popup-section-title { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin: 12px 0 6px 0; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid var(--border); padding-bottom: 4px; }
.popup-data-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 12.5px; color: var(--text-secondary); }
.popup-data-row i { color: var(--accent-light); width: 16px; text-align: center; }
 
/* Custom Popup Interactive Component Grid tabs */
.popup-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
.popup-stat-box { background: var(--bg-base); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 6px; text-align: center; }
.popup-stat-box span { display: block; font-size: 10px; color: var(--text-muted); }
.popup-stat-box strong { font-size: 14px; color: var(--text-primary); }
 
.fac-flex-container { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.fac-tag { font-size: 11px; padding: 2px 8px; border-radius: 20px; background: var(--bg-base); border: 1px solid var(--border); color: var(--text-secondary); }
.fac-tag.active { background: rgba(16, 185, 129, 0.15); border-color: var(--success); color: #10b981; font-weight: 500; }
 
.popup-actions-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 14px; }
.popup-btn-action { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 0; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; font-weight: 600; text-align: center; transition: var(--transition); }
.popup-btn-nav {
  background: var(--accent);
  color: #ffffff !important;
  border: 1.5px solid transparent;
  text-shadow: 0 1px 2px rgba(0,0,0,0.25);
}
.popup-btn-phone {
  background: var(--success);
  color: #ffffff !important;
  border: 1.5px solid transparent;
  text-shadow: 0 1px 2px rgba(0,0,0,0.25);
}
[data-theme="light"] .popup-btn-nav {
  background: var(--accent);
  color: #ffffff !important;
  border-color: #1d4ed8;
  text-shadow: 0 1px 2px rgba(0,0,0,0.35);
}
[data-theme="light"] .popup-btn-phone {
  background: #059669;
  color: #ffffff !important;
  border-color: #047857;
  text-shadow: 0 1px 2px rgba(0,0,0,0.35);
}
[data-theme="light"] .popup-btn-nav:hover {
  background: #1d4ed8;
  color: #ffffff !important;
}
[data-theme="light"] .popup-btn-phone:hover {
  background: #047857;
  color: #ffffff !important;
}
.popup-btn-action:hover { opacity: 0.9; transform: translateY(-1px); }
 
/* Map Float Widgets styling */
.district-label {
  position: absolute; top: 20px; left: 50%; transform: translateX(-50%); z-index: 500;
  background: var(--glass-bg); backdrop-filter: blur(8px); border: 1px solid var(--border);
  color: var(--text-primary); font-size: 14px; font-weight: 600; padding: 8px 24px;
  border-radius: 30px; box-shadow: var(--shadow-lg); pointer-events: none; opacity: 0; transition: opacity 0.4s ease;
}
.district-label.show { opacity: 1; }
 
.map-loading {
  position: absolute; top: 20px; left: 20px; z-index: 500; background: var(--bg-surface);
  border: 1px solid var(--border); padding: 8px 16px; border-radius: var(--radius-sm);
  font-size: 12px; color: var(--text-primary); display: none; align-items: center; gap: 8px; box-shadow: var(--shadow-md);
}
.map-loading.show { display: flex; }
 
/* Float Map Legend Control Widget */
.legend {
  position: absolute; bottom: 24px; right: 24px; z-index: 500;
  background: var(--glass-bg); backdrop-filter: blur(12px); border: 1px solid var(--border);
  border-radius: var(--radius-md); padding: 16px; width: 220px; box-shadow: var(--shadow-lg);
}
.legend h4 { font-size: 12px; font-weight: 700; color: var(--text-primary); text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid var(--border); padding-bottom: 4px; }
.legend-item { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 12px; color: var(--text-secondary); cursor: pointer; transition: var(--transition); }
.legend-item:hover { color: var(--text-primary); }
.legend-shape { width: 14px; height: 14px; border: 2px solid #fff; box-shadow: var(--shadow-md); flex-shrink: 0; }
.legend-shape.circle { border-radius: 50%; }
.legend-shape.rounded { border-radius: 3px; }
.legend-shape.diamond { transform: rotate(45deg); width: 10px; height: 10px; margin: 2px; }
.legend-shape.hexagon { clip-path: polygon(50% 0%, 95% 25%, 95% 75%, 50% 100%, 5% 75%, 5% 25%); border: none !important; width: 14px; height: 14px; }
 
.legend-toggle { display: none; }
 
/* ================================================================
   MOBILE SYSTEM BOTTOM SHEET LAYOUT MECHANICS
   ================================================================ */
.bottom-nav { display: none; }
.drawer-overlay, .stats-sheet-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; opacity: 0; visibility: hidden; transition: var(--transition); }
.drawer-overlay.open, .stats-sheet-overlay.open { opacity: 1; visibility: visible; }
 
.filter-drawer, .stats-sheet {
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 2001; background: var(--bg-surface);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0; padding: 24px;
  transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.1, 0.76, 0.55, 0.94);
  max-height: 75vh; overflow-y: auto; box-shadow: 0 -8px 32px rgba(0,0,0,0.2);
}
.filter-drawer.open, .stats-sheet.open { transform: translateY(0); }
.drawer-handle { width: 40px; height: 4px; background: var(--border-light); border-radius: 2px; margin: -8px auto 16px auto; }
.drawer-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
 
.drawer-section { margin-bottom: 16px; }
.drawer-label { font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; }
.drawer-select { width: 100%; background: var(--bg-base); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text-primary); font-family: var(--font); font-size: 14px; padding: 10px 12px; outline: none; }
 
.drawer-btn-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 24px; }
 
/* Mobile Advanced KPI Container Elements Grid */
.kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
.kpi-card { background: var(--bg-base); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px; border-top: 4px solid var(--accent); }
.kpi-card.green { border-top-color: var(--success); }
.kpi-card.orange { border-top-color: var(--warning); }
.kpi-card.red { border-top-color: var(--danger); }
.kpi-label { font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
.kpi-val { font-size: 20px; font-weight: 700; }
.kpi-sub { font-size: 11px; color: var(--text-secondary); }
 
/* ================================================================
   SYSTEM GLOBAL INTERACTION COMPONENTS (TOAST / LIGHTBOX / SCREENS)
   ================================================================ */
.lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 5000; align-items: center; justify-content: center; padding: 20px; }
.lightbox.open { display: flex; }
.lightbox img { max-width: 100%; max-height: 85vh; border-radius: var(--radius-sm); box-shadow: var(--shadow-lg); }
.lightbox-close { position: absolute; top: 24px; right: 24px; color: white; background: transparent; border: none; font-size: 24px; cursor: pointer; }
 
.loading-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column; gap: 16px; color: white; }
.loading-overlay.active { display: flex; }
.spinner { width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.2); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
 
.toast-stack { position: fixed; bottom: 24px; right: 24px; z-index: 9000; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast { background: var(--bg-surface); border: 1px solid var(--border); border-left: 4px solid var(--accent); border-radius: var(--radius-sm); padding: 12px 20px; font-size: 13px; color: var(--text-primary); box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 10px; animation: toastIn 0.2s cubic-bezier(0, 0, 0.2, 1); pointer-events: all; }
.toast.success { border-left-color: var(--success); }
.toast.error { border-left-color: var(--danger); }
.toast.warning { border-left-color: var(--warning); }
@keyframes toastIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
 
.preview-footer { background: var(--bg-surface); border-top: 1px solid var(--border); padding: 12px 24px; text-align: center; font-size: 12px; color: var(--text-muted); }
 
/* ระยะทางที่ว่าการอำเภอ – District Card */
.district-dist-row {
  display: flex; align-items: center; gap: 6px; margin-top: 6px;
  font-size: 12px; color: var(--text-secondary); flex-wrap: wrap;
  padding-top: 5px; border-top: 1px dashed var(--border);
}
 
/* ================================================================
   RESPONSIVE DESIGN BREAKPOINTS (MOBILE TO SMARTPHONE SCALE)
   ================================================================ */
@media (max-width: 1023px) {
  .desktop-filter-bar { display: none; }
  .preview-header { padding: 0 16px; height: var(--header-h); }
  .header-brand { display: flex; }
  .header-title { display: none; }
  .header-agency-badge { display: flex; align-items: center; font-size: 14px; font-weight: 700; color: var(--text-primary); }
  
  .stats-bar { display: none; }
  .map-wrapper { height: calc(100vh - var(--header-h) - var(--bottom-nav-h)); }
  .legend { display: none; }
  
  /* Active Display Mobile Navigation Elements */
  .bottom-nav {
    display: flex; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1500;
    background: var(--bg-surface); border-top: 1px solid var(--border); height: var(--bottom-nav-h);
    padding-bottom: env(safe-area-inset-bottom); align-items: center; justify-content: space-around;
  }
  .bnav-item { background: transparent; border: none; color: var(--text-secondary); display: flex; flex-direction: column; align-items: center; gap: 4px; font-family: var(--font); font-size: 11px; cursor: pointer; width: 20%; -webkit-tap-highlight-color: transparent; }
  .bnav-item i { font-size: 18px; }
  .bnav-item.active, .bnav-item:active { color: var(--accent-light); }
  .bnav-item-staff { color: var(--accent-light) !important; border-left: 1px solid var(--border); }
  
  .legend-toggle { display: flex; position: absolute; bottom: 16px; right: 16px; z-index: 500; width: 44px; height: 44px; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border); color: var(--text-primary); align-items: center; justify-content: center; font-size: 16px; box-shadow: var(--shadow-lg); }
  .legend.mobile-open { display: block; bottom: 76px; right: 16px; width: calc(100% - 32px); max-width: 300px; }
  
  .toast-stack { bottom: calc(var(--bottom-nav-h) + 16px); left: 16px; right: 16px; }
  .preview-footer { display: none; }
}
 
@media print {
  .preview-header, .stats-bar, .bottom-nav, .legend, .legend-toggle, .filter-drawer, .stats-sheet, .loading-overlay, .toast-stack, .preview-footer { display: none !important; }
  .map-wrapper, #map { height: 100vh !important; width: 100% !important; border-radius: 0 !important; }
}
</style>
</head>
<body>
 
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
  <div id="loadingText" style="font-weight: 500;">กำลังจัดเตรียมแผนที่ระดับบริหาร...</div>
</div>
 
<header class="preview-header">
  <div class="header-brand">
    <div class="header-logo">
      <?php if ($logoPath): ?>
        <img src="<?= $logoPath ?>" alt="ตราสัญลักษณ์">
      <?php else: ?><i class="fa-solid fa-shield-halved"></i><?php endif; ?>
    </div>
    <div class="header-title">
      <h1><?= sanitize($siteName) ?></h1>
      <p><?= sanitize($agencyName) ?></p>
    </div>
  </div>
 
  <div class="header-agency-badge">
     <i class="fa-solid fa-shield-halved" style="color:var(--accent-light); margin-right:8px;"></i> ศูนย์พักพิงระยอง
  </div>
 
  <div class="desktop-filter-bar">
    <div class="filter-group" style="max-width: 220px;">
      <i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted)"></i>
      <input type="text" id="searchInput" placeholder="ค้นหาชื่อศูนย์ / พื้นที่...">
    </div>
    <div class="filter-group" style="max-width: 160px;">
      <label>อำเภอ</label>
      <select id="districtSelect">
        <option value="">ทั้งหมด</option>
        <?php foreach ($districtsList as $d): ?>
          <option value="<?= htmlspecialchars(trim($d), ENT_QUOTES, 'UTF-8') ?>"><?= sanitize($d) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group" style="max-width: 200px;">
      <label>ประเภทศูนย์</label>
      <select id="typeSelect">
        <option value="">ทั้งหมด</option>
        <?php
        $types = $db->fetchAll("SELECT DISTINCT ct.id, ct.name FROM checkpoint_types ct JOIN checkpoints c ON ct.id = c.type_id WHERE c.status='active' AND ct.is_active=1 ORDER BY ct.sort_order");
        foreach ($types as $t): ?>
          <option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn-hdr btn-hdr-primary" onclick="CoreMapEngine.applyActiveFilters()"><i class="fa-solid fa-filter"></i> กรองข้อมูล</button>
    <button class="btn-hdr btn-hdr-ghost" onclick="CoreMapEngine.resetActiveFilters()"><i class="fa-solid fa-arrow-rotate-left"></i> รีเซ็ต</button>
  </div>
 
  <div class="header-actions">
    <button class="icon-btn" id="themeBtn" onclick="ThemeManagementEngine.cycleThemeSystem()" title="เปลี่ยนโหมดสีแสดงผล"><i class="fa-solid fa-circle-half-stroke"></i></button>
    <button class="icon-btn" id="mapStyleBtn" onclick="CoreMapEngine.cycleMapStyle()" title="เปลี่ยนรูปแบบแผนที่ (Carto/OSM/Satellite)"><i class="fa-solid fa-map"></i></button>
    <button class="icon-btn" onclick="CoreMapEngine.triggerGeolocate()" title="ระบุพิกัดตำแหน่งปัจจุบัน"><i class="fa-solid fa-crosshairs"></i></button>
    <button class="icon-btn" id="statsExpandBtn" onclick="CoreMapEngine.toggleAnalyticalPanel()" title="เปิดแผงวิเคราะห์ข้อมูลแยกพื้นที่"><i class="fa-solid fa-chart-pie"></i></button>
    <button class="icon-btn" onclick="CoreMapEngine.triggerMapExport()" title="ดาวน์โหลดภาพถ่ายแผนที่สาธารณะ"><i class="fa-solid fa-camera"></i></button>
  </div>
</header>
 
<div class="stats-bar" id="statsBar">
  <div class="sb-item">
    <span class="sb-dot" style="background:var(--accent);"></span>
    <span>ศูนย์พักพิงทั้งหมด: <strong id="sbTotal"><?= $totalCheckpoints ?></strong> แห่ง</span>
  </div>
  <div class="sb-item">
    <i class="fa-solid fa-users" style="color: var(--accent-light);"></i>
    <span>ครองเตียงรวม: <strong id="sbOcc"><?= number_format($totalOccupancy) ?></strong> <span style="color:var(--text-muted)">/ <span id="sbCap"><?= number_format($totalCapacity) ?></span> คน</span></span>
  </div>
  <div class="sb-item">
    <div class="occ-wrap">
      <span style="font-size:12px; color:var(--text-secondary)">อัตราความหนาแน่น:</span>
      <div class="occ-track">
        <div class="occ-fill" id="sbOccBar" style="width: 0%;"></div>
      </div>
      <span id="sbOccPct" style="font-weight: 700;">0%</span>
    </div>
  </div>
  <div class="sb-item">
    <i class="fa-solid fa-map-location-dot"></i>
    <span>เปิดครอบคลุม: <strong id="sbDistricts">0</strong> อำเภอ</span>
  </div>
  <?php if ($totalIncidents > 0): ?>
  <div class="sb-item" style="margin-left: auto; border-right: none;">
    <span class="sb-dot" style="background:var(--danger); animation: pulse 2s infinite;"></span>
    <span style="color:var(--danger); font-weight:600;">ภัยพิบัติที่กำลังเกิด: <strong id="sbInc" style="color:var(--danger)"><?= $totalIncidents ?></strong> จุด</span>
  </div>
  <?php endif; ?>
</div>
 
<div class="stats-panel" id="statsPanel">
  <div class="stats-panel-inner" id="statsPanelInner"></div>
</div>
 
<div class="map-wrapper">
  <div id="map"></div>
 
  <div class="district-label" id="districtLabel"></div>
  <div class="map-loading" id="mapLoading"><div class="spinner" style="width:14px; height:14px; border-width:2px;"></div> ค้นหารูปแปลงพิกัดขอบเขตทางปกครอง...</div>
 
  <div class="legend" id="legend">
    <h4><i class="fa-solid fa-layer-group" style="margin-right:6px;"></i> คำอธิบายสัญลักษณ์</h4>
    <div id="legendDynamicContainer"></div>
    <div style="height: 1px; background: var(--border); margin: 10px 0;"></div>
    <h4><i class="fa-solid fa-triangle-exclamation" style="margin-right:6px;"></i> สถานการณ์ภัยพิบัติ</h4>
    <div class="legend-item" style="cursor:default;"><div class="legend-shape circle" style="background:#dc2626; width:12px; height:12px; border:2px solid #fff;"></div><span>เหตุวิกฤตความรุนแรงสูง</span></div>
    <div class="legend-item" style="cursor:default;"><div class="legend-shape circle" style="background:#f59e0b; width:12px; height:12px; border:2px solid #fff;"></div><span>สถานการณ์เฝ้าระวัง</span></div>
    <div style="height: 1px; background: var(--border); margin: 10px 0;"></div>
    <h4><i class="fa-solid fa-building-columns" style="margin-right:6px;"></i> ที่ว่าการอำเภอ</h4>
    <div class="legend-item" onclick="CoreMapEngine.toggleDistrictOfficeLayer()" title="แสดง/ซ่อน ที่ว่าการอำเภอและเส้นระยะทาง">
      <div class="legend-shape rounded" style="background:#b45309; width:14px; height:14px; border:2px solid #fef3c7; box-shadow:0 0 0 1.5px #b45309; display:flex; align-items:center; justify-content:center; border-radius:4px;"><i class="fa-solid fa-building-columns" style="font-size:8px; color:white;"></i></div>
      <span id="officeLayerLegendLabel">ที่ว่าการอำเภอ + เส้นระยะทาง (เปิด)</span>
    </div>
    <div class="legend-item" style="cursor:default;">
      <div style="width:18px; height:2px; background:#a855f7; flex-shrink:0; border-top:2px dashed #a855f7;"></div>
      <span style="font-size:11px;">ระยะห่างระหว่างศูนย์พักพิง (เมื่อเลือกอำเภอ)</span>
    </div>
  </div>
 
  <button class="legend-toggle" id="legendToggle" onclick="CoreMapEngine.toggleMobileLegend()" title="เปิดสัญลักษณ์แผนที่"><i class="fa-solid fa-legend">🗺️</i></button>
</div>
 
<footer class="preview-footer">
  <i class="fa-regular fa-copyright"></i> <?= date('Y') ?> <?= sanitize($agencyName) ?> | สายด่วนนิรภัยศูนย์ปฏิบัติการฉุกเฉิน โทร. <?= sanitize($agencyPhone) ?> | ดึงฐานข้อมูลเมื่อ: <?= function_exists('thaiDate') ? thaiDate(date('Y-m-d H:i:s')) : date('Y-m-d H:i:s') ?>
</footer>
 
<nav class="bottom-nav" id="bottomNav">
  <button class="bnav-item" onclick="CoreMapEngine.triggerGeolocate()"><i class="fa-solid fa-location-crosshairs"></i><span>ตำแหน่งของฉัน</span></button>
  <button class="bnav-item" onclick="CoreMapEngine.openMobileDrawer('filterDrawer')"><i class="fa-solid fa-magnifying-glass"></i><span>ค้นหาศูนย์</span></button>
  <button class="bnav-item" onclick="CoreMapEngine.openMobileDrawer('statsSheet')"><i class="fa-solid fa-chart-simple"></i><span>สถิติรวม</span></button>
  <button class="bnav-item" onclick="CoreMapEngine.toggleMobileLegend()"><i class="fa-solid fa-map-pin"></i><span>สัญลักษณ์</span></button>
  <button class="bnav-item bnav-item-staff" onclick="window.location.href='index.php'" title="เข้าสู่ระบบสำหรับเจ้าหน้าที่"><i class="fa-solid fa-user-shield"></i><span>เจ้าหน้าที่</span></button>
</nav>
 
<div class="drawer-overlay" id="filterDrawerOverlay" onclick="CoreMapEngine.closeMobileDrawer('filterDrawer')"></div>
<div class="filter-drawer" id="filterDrawer">
  <div class="drawer-handle"></div>
  <div class="drawer-title"><i class="fa-solid fa-magnifying-glass"></i> ค้นหาศูนย์ข้อมูลละเอียด</div>
  <div class="drawer-section">
    <div class="drawer-label">คำค้นหาหลัก (Keyword)</div>
    <input type="text" class="drawer-select" id="mSearchInput" placeholder="พิมพ์ชื่อสถานที่ ขอบเขต หรือประเภทศาลา..." style="background:var(--bg-base); border:1px solid var(--border)">
  </div>
  <div class="drawer-section">
    <div class="drawer-label">พื้นที่อำเภอ</div>
    <select class="drawer-select" id="mDistrictSelect">
      <option value="">ทั้งหมด</option>
      <?php foreach ($districtsList as $d): ?>
        <option value="<?= htmlspecialchars(trim($d), ENT_QUOTES, 'UTF-8') ?>"><?= sanitize($d) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="drawer-section">
    <div class="drawer-label">ระบุประเภทโครงสร้างศูนย์</div>
    <select class="drawer-select" id="mTypeSelect">
      <option value="">ทั้งหมด</option>
      <?php foreach ($types as $t): ?>
        <option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="drawer-btn-row">
    <button class="drawer-btn btn-hdr-ghost" onclick="CoreMapEngine.resetActiveFilters(); CoreMapEngine.closeMobileDrawer('filterDrawer');" style="height:44px; justify-content:center; border-radius:var(--radius-sm)"><i class="fa-solid fa-trash-can"></i> ล้างการกรอง</button>
    <button class="drawer-btn btn-hdr-primary" onclick="CoreMapEngine.applyFiltersFromMobileDrawer()" style="height:44px; justify-content:center; border-radius:var(--radius-sm)"><i class="fa-solid fa-magnifying-glass"></i> ค้นหาตอนนี้</button>
  </div>
</div>
 
<div class="stats-sheet-overlay" id="statsSheetOverlay" onclick="CoreMapEngine.closeMobileDrawer('statsSheet')"></div>
<div class="stats-sheet" id="statsSheet">
  <div class="drawer-handle"></div>
  <div class="drawer-title"><i class="fa-solid fa-chart-line"></i> รายงานสถิติสถานการณ์ความจุรวม</div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">ศูนย์เปิดทำการ</div>
      <div class="kpi-val" id="kpiTotal"><?= $totalCheckpoints ?></div>
      <div class="kpi-sub">แห่งในพื้นที่จังหวัด</div>
    </div>
    <div class="kpi-card green">
      <div class="kpi-label">จำนวนผู้พักพิงรวม</div>
      <div class="kpi-val" id="kpiOcc"><?= number_format($totalOccupancy) ?></div>
      <div class="kpi-sub">จากแผนความจุ <?= number_format($totalCapacity) ?> ราย</div>
    </div>
    <div class="kpi-card orange">
      <div class="kpi-label">อัตราครองเตียงเฉลี่ย</div>
      <div class="kpi-val" id="kpiPct"><?= $occupancyPercent ?>%</div>
      <div class="kpi-sub">ของพื้นที่เปิดบริการทั้งหมด</div>
    </div>
    <div class="kpi-card red">
      <div class="kpi-label">จุดเสี่ยงภัยภายนอก</div>
      <div class="kpi-val" style="color:var(--danger);"><?= $totalIncidents ?></div>
      <div class="kpi-sub">วิกฤตรุนแรงสูง <?= $criticalIncidents ?> แห่ง</div>
    </div>
  </div>
  <div style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:12px; letter-spacing:0.5px;">วิเคราะห์ขีดความสามารถรายอำเภอ</div>
  <div class="stats-panel-inner" id="statsSheetDistricts" style="padding:0; display:flex; flex-direction:column; gap:8px;"></div>
</div>
 
<div class="lightbox" id="lightbox" onclick="CoreMapEngine.closeImageLightbox()">
  <button class="lightbox-close" onclick="CoreMapEngine.closeImageLightbox()"><i class="fa-solid fa-xmark"></i></button>
  <img id="lightboxImg" src="" alt="ภาพสื่อสตรีมมิ่งความละเอียดสูง">
</div>
 
<div class="toast-stack" id="toastStack"></div>
 
<script>
/**
 * 1. REAL-TIME MULTI-THEME ENGINE CONTROLLER
 * จัดการธีม Dark / Light / Auto อย่างสมบูรณ์แบบระดับ Enterprise
 */
const ThemeManagementEngine = {
  activeThemeState: 'dark',
  systemDarkMediaQuery: window.matchMedia('(prefers-color-scheme: dark)'),
 
  initializeThemeEngine() {
    this.activeThemeState = localStorage.getItem('shelter_theme') || 'auto';
    this.executeThemeRuntimeChange(this.activeThemeState, false);
    
    // ลงทะเบียนเหตุการณ์เพื่อตรวจจับการเปลี่ยนแปลงสภาวะแสงของระบบปฏิบัติการแบบ Real-time
    this.systemDarkMediaQuery.addEventListener('change', () => {
      if (this.activeThemeState === 'auto') {
        this.executeThemeRuntimeChange('auto', false);
      }
    });
  },
 
  cycleThemeSystem() {
    const states = ['dark', 'light', 'auto'];
    let index = states.indexOf(this.activeThemeState);
    this.activeThemeState = states[(index + 1) % states.length];
    localStorage.setItem('shelter_theme', this.activeThemeState);
    this.executeThemeRuntimeChange(this.activeThemeState, true);
  },
 
  executeThemeRuntimeChange(theme, triggerToast = false) {
    const htmlNode = document.documentElement;
    let computedTheme = theme;
    
    if (theme === 'auto') {
      computedTheme = this.systemDarkMediaQuery.matches ? 'dark' : 'light';
    }
 
    htmlNode.setAttribute('data-theme', computedTheme);
    
    // ปรับปรุงค่า Theme color ของอุปกรณ์พกพาในลักษณะ Dynamic Meta
    const metaColor = document.getElementById('themeColorMeta');
    if (metaColor) metaColor.content = computedTheme === 'light' ? '#f3f4f6' : '#090d16';
 
    const icons = { dark: '<i class="fa-solid fa-moon"></i>', light: '<i class="fa-solid fa-sun"></i>', auto: '<i class="fa-solid fa-circle-half-stroke"></i>' };
    const btn = document.getElementById('themeBtn');
    if (btn) btn.innerHTML = icons[theme];
 
    // เรียกเปลี่ยนฐานชั้นข้อมูลแผนที่ (Base Mapping Tiles Layer) ให้สัมพันธ์กับสภาวะธีม (ถ้าเปิดโหมด Theme อยู่)
    if (typeof CoreMapEngine !== 'undefined' && CoreMapEngine.leafletMapObject) {
      CoreMapEngine.hotSwapMapBaseTiles(computedTheme);
    }
 
    if (triggerToast) {
      const modeText = { dark: 'โหมดมืด (Dark Mode)', light: 'โหมดสว่าง (Light Mode)', auto: 'ปรับตามระบบอัตโนมัติ (Auto Mode)' };
      CoreMapEngine.displayNotification(`สลับระบบแสงหน้าจอเป็น: ${modeText[theme]}`, 'info');
    }
  }
};
 
/**
 * 2. GEOSPATIAL MAP CORE PLATFORM INTERACTION ENGINE
 * สถาปัตยกรรมจัดการชั้นข้อมูลภูมิสารสนเทศ แผนที่ และฟังก์ชันการคำนวณทั้งหมด
 */
const CoreMapEngine = {
  leafletMapObject: null,
  activeTilesLayer: null,
  activeMapStyle: 'theme', // 'theme', 'osm', 'satellite'
  clusterGroupLayer: null,
  incidentLayers: [],
  boundaryGeoJsonLayer: null,
  districtOfficeLayer: null,
  districtOfficeLayerVisible: true,
  popupRouteLayer: null,
  interShelterLayer: null,
  checkpointsRawData: <?= json_encode($checkpointsJson, JSON_UNESCAPED_UNICODE) ?>,
  incidentsRawData: <?= json_encode($incidentsJson, JSON_UNESCAPED_UNICODE) ?>,
  typesReferenceMap: <?= $checkpointTypesJson ?>,
  isMobileLegendActive: false,
 
  // =========================================================
  // พิกัดที่ว่าการอำเภอในจังหวัดระยอง (WGS84)
  // =========================================================
  // พิกัดที่ว่าการอำเภอ — โหลดจากฐานข้อมูลผ่าน PHP (แทนค่า hardcode เดิม)
  districtOffices: <?= $districtOfficesJson ?>,
 
  // คำนวณระยะทาง Haversine (กม.) ระหว่างสองพิกัด
  haversineDistanceKm(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng/2) * Math.sin(dLng/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  },
 
  // ดึงระยะทางและข้อมูลที่ว่าการอำเภอสำหรับ checkpoint
  getDistrictOfficeDistance(cp) {
    const office = this.districtOffices[cp.district];
    if (!office || !cp.lat || !cp.lng) return null;
    const km = this.haversineDistanceKm(cp.lat, cp.lng, office.lat, office.lng);
    return { km: km, name: office.name };
  },
 
  // =========================================================
  // เส้นทางตามถนนจริงผ่าน OSRM (Open Source Routing Machine)
  // ใช้ public demo server — จำกัดไม่เกิน 1 คำขอ/วินาที ตามนโยบายการใช้งาน
  // =========================================================
  osrmRouteCache: {},
  osrmRequestQueue: Promise.resolve(),
 
  // ต่อคิวคำขอให้ทำงานทีละรายการ ห่างกันอย่างน้อย 1.1 วินาที เพื่อไม่ให้ชน rate limit ของ demo server
  queueOsrmRequest(taskFn) {
    const run = () => taskFn().catch(() => null);
    const scheduled = this.osrmRequestQueue.then(() => run().then(result => {
      return new Promise(r => setTimeout(() => r(result), 1100));
    }));
    this.osrmRequestQueue = scheduled.catch(() => {});
    return scheduled;
  },
 
  // ขอเส้นทางถนนจริง (ระยะทางสั้นที่สุด) ระหว่างจุดสองจุดจาก OSRM
  // คืนค่า { km, latlngs } โดย latlngs คือพิกัดเส้นทางสำหรับวาดบนแผนที่ Leaflet
  async fetchRoadRoute(fromLat, fromLng, toLat, toLng) {
    const cacheKey = `${fromLat.toFixed(5)},${fromLng.toFixed(5)}|${toLat.toFixed(5)},${toLng.toFixed(5)}`;
    if (this.osrmRouteCache[cacheKey]) return this.osrmRouteCache[cacheKey];
 
    const result = await this.queueOsrmRequest(async () => {
      const url = `https://router.project-osrm.org/route/v1/driving/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson`;
      const resp = await fetch(url);
      if (!resp.ok) throw new Error('OSRM HTTP ' + resp.status);
      const data = await resp.json();
      if (data.code !== 'Ok' || !data.routes || !data.routes.length) throw new Error('OSRM no route');
 
      const route = data.routes[0];
      const latlngs = route.geometry.coordinates.map(c => [c[1], c[0]]); // GeoJSON เป็น [lng,lat] ต้องสลับเป็น [lat,lng] สำหรับ Leaflet
      return { km: route.distance / 1000, latlngs: latlngs };
    });
 
    if (result) this.osrmRouteCache[cacheKey] = result;
    return result;
  },
 
  // =========================================================
  // เรนเดอร์หมุดที่ว่าการอำเภอ + เส้นทางถนนจริงแสดงระยะทางไปยังศูนย์พักพิง
  // คำนวณเส้นทาง (OSRM) เฉพาะตอนกรองอำเภอ/มีศูนย์แสดงผลจำนวนน้อยเท่านั้น
  // =========================================================
  renderDistrictOfficeLayer() {
    if (!this.districtOfficeLayer) return;
    this.districtOfficeLayer.clearLayers();
    if (!this.districtOfficeLayerVisible) return;
 
    // เฉพาะอำเภอที่มีศูนย์พักพิงที่กำลังแสดงผลอยู่บนแผนที่ในขณะนี้
    const visibleCheckpoints = this.clusterGroupLayer.getLayers()
      .map(m => m.checkpointPayload)
      .filter(Boolean);
 
    const districtsToShow = new Set(visibleCheckpoints.map(cp => cp.district).filter(Boolean));
 
    // ถ้ายังไม่มีหมุดศูนย์ใดๆ แสดงผล (เช่น ตอนเริ่มต้นก่อนโหลดหมุด) ให้ใช้ทุกอำเภอที่มีข้อมูลในระบบ
    const targetDistricts = districtsToShow.size > 0
      ? districtsToShow
      : new Set(this.checkpointsRawData.map(cp => cp.district).filter(Boolean));
 
    // นับจำนวนศูนย์พักพิงทั้งหมดที่จะต้องคำนวณเส้นทาง เพื่อจำกัดไม่ให้ยิงคำขอ OSRM มากเกินไปพร้อมกัน
    let totalCheckpointsToRoute = 0;
    targetDistricts.forEach(d => {
      totalCheckpointsToRoute += visibleCheckpoints.filter(cp => cp.district === d).length;
    });
    // ถ้าแสดงผลทุกอำเภอพร้อมกัน (ไม่ได้กรอง) จำนวนจุดจะมากเกินไปสำหรับ public OSRM demo server
    // จึงวาดเฉพาะหมุดที่ว่าการอำเภอ โดยใช้เส้นประระยะตรง (Haversine) แทนเส้นถนนจริง เพื่อไม่ให้ช้าหรือโดน rate limit
    const useRoadRouting = totalCheckpointsToRoute > 0 && totalCheckpointsToRoute <= 15;
 
    targetDistricts.forEach(districtName => {
      const office = this.districtOffices[districtName];
      if (!office) return;
 
      // หมุดที่ว่าการอำเภอ — สีส้มทองเพื่อแยกชัดจากศูนย์พักพิง
      const officeIcon = L.divIcon({
        className: 'district-office-marker',
        html: `<div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
          <div style="background:#b45309; width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; border:3px solid #fef3c7; box-shadow:0 0 0 2px #b45309, var(--shadow-md); font-size:17px;">
            <i class="fa-solid fa-building-columns"></i>
          </div>
          <div style="background:#b45309; color:#fff; font-size:9px; font-weight:700; padding:1px 5px; border-radius:6px; white-space:nowrap; box-shadow:var(--shadow-md); letter-spacing:0.3px;"></div>
        </div>`,
        iconSize: [38, 54],
        iconAnchor: [19, 54],
        popupAnchor: [0, -56]
      });
 
      const officeMarker = L.marker([office.lat, office.lng], { icon: officeIcon, zIndexOffset: 500 });
 
// นับศูนย์พักพิงในอำเภอนี้ที่กำลังแสดงผล พร้อมสร้างรายการศูนย์และระยะห่างเรียงจากใกล้ไปไกล
      const districtCheckpoints = visibleCheckpoints.filter(cp => cp.district === districtName);
      let distNote = '';
      if (districtCheckpoints.length > 0) {
        // คำนวณระยะทางและจัดเรียงข้อมูล
        const cpList = districtCheckpoints.map(cp => {
          return {
            name: cp.name,
            km: this.haversineDistanceKm(cp.lat, cp.lng, office.lat, office.lng)
          };
        }).sort((a, b) => a.km - b.km);

        // สร้าง HTML แสดงรายการศูนย์พักพิง
        const cpListHtml = cpList.map(item => {
           const displayKm = item.km >= 10 ? item.km.toFixed(1) : item.km.toFixed(2);
           const distColor = item.km <= 5 ? 'var(--success)' : item.km <= 15 ? 'var(--warning)' : 'var(--danger)';
           return `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:11.5px;">
                     <span style="flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-right:8px; color:var(--text-primary);" title="${this.escapeToken(item.name)}">
                       <i class="fa-solid fa-circle-dot" style="color:${distColor}; font-size:8px; margin-right:4px;"></i>${this.escapeToken(item.name)}
                     </span>
                     <strong style="color:${distColor}; white-space:nowrap;">${displayKm} กม.</strong>
                   </div>`;
        }).join('');

        // นำไปประกอบเข้ากับ distNote พร้อมใส่ Scrollbar กรณีมีศูนย์พักพิงหลายแห่ง
        // สร้าง unique id สำหรับ list ใน popup เพื่ออัพเดทระยะถนนจริงทีหลัง
        const officePopupListId = `office-cp-list-${districtName.replace(/\s/g,'')}`;
        distNote = `<div style="font-size:12px; color:var(--text-secondary); margin-top:10px; border-top:1px solid var(--border); padding-top:8px;">
          <div style="margin-bottom:8px;">ศูนย์พักพิงในพื้นที่: <strong>${districtCheckpoints.length}</strong> แห่ง</div>
          <div id="${officePopupListId}" style="max-height: 140px; overflow-y: auto; padding-right: 4px; border-bottom: 1px solid var(--border); margin-bottom: 6px;">
            ${cpListHtml}
          </div>
          <div id="${officePopupListId}-note" style="font-size:10.5px; color:var(--text-muted); line-height:1.3;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size:9px;"></i> กำลังคำนวณระยะตามถนนจริง...
          </div>
        </div>`;
      }
      
      officeMarker.bindPopup(`
        <div style="padding:14px; font-family:var(--font); min-width:220px;">
          <div style="font-weight:700; color:var(--text-primary); font-size:14px; margin-bottom:4px;"><i class="fa-solid fa-building-columns" style="color:#b45309;"></i> ${this.escapeToken(office.name)}</div>
          <div style="font-size:12px; color:var(--text-muted);">อ.${this.escapeToken(districtName)} จ.ระยอง</div>
          ${distNote}
        </div>`, { maxWidth: 280 });

      // เมื่อ popup เปิด → ค่อยเริ่มคำนวณระยะถนนจริงและอัพเดท DOM ที่มีอยู่จริงแล้ว
      if (districtCheckpoints.length > 0 && districtCheckpoints.length <= 15) {
        const officePopupListId = `office-cp-list-${districtName.replace(/\s/g,'')}`;
        const capturedCheckpoints = districtCheckpoints.slice();
        const capturedOffice = office;
        officeMarker.on('popupopen', () => {
          Promise.all(
            capturedCheckpoints.map(cp =>
              this.fetchRoadRoute(cp.lat, cp.lng, capturedOffice.lat, capturedOffice.lng)
                .then(r => ({ name: cp.name, roadKm: r ? r.km : null, straightKm: this.haversineDistanceKm(cp.lat, cp.lng, capturedOffice.lat, capturedOffice.lng) }))
                .catch(() => ({ name: cp.name, roadKm: null, straightKm: this.haversineDistanceKm(cp.lat, cp.lng, capturedOffice.lat, capturedOffice.lng) }))
            )
          ).then(results => {
            results.sort((a, b) => (a.roadKm ?? a.straightKm) - (b.roadKm ?? b.straightKm));
            const newHtml = results.map(item => {
              const km = item.roadKm ?? item.straightKm;
              const isRoad = item.roadKm !== null;
              const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
              const distColor = km <= 5 ? 'var(--success)' : km <= 15 ? 'var(--warning)' : 'var(--danger)';
              const roadIcon = isRoad ? `<i class="fa-solid fa-road" style="font-size:8px; margin-right:2px;"></i>` : '';
              return `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:11.5px;">
                        <span style="flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-right:8px; color:var(--text-primary);" title="${this.escapeToken(item.name)}">
                          <i class="fa-solid fa-circle-dot" style="color:${distColor}; font-size:8px; margin-right:4px;"></i>${this.escapeToken(item.name)}
                        </span>
                        <strong style="color:${distColor}; white-space:nowrap;">${roadIcon}${displayKm} กม.</strong>
                      </div>`;
            }).join('');
            const listEl = document.getElementById(officePopupListId);
            if (listEl) listEl.innerHTML = newHtml;
            const noteEl = document.getElementById(officePopupListId + '-note');
            if (noteEl) noteEl.innerHTML = `<i class="fa-solid fa-road" style="font-size:9px;"></i> ระยะตามถนนจริง`;
          });
        });
      } else if (districtCheckpoints.length > 0) {
        const officePopupListId = `office-cp-list-${districtName.replace(/\s/g,'')}`;
        officeMarker.on('popupopen', () => {
          const noteEl = document.getElementById(officePopupListId + '-note');
          if (noteEl) noteEl.innerHTML = `* ระยะประมาณเส้นตรง (มีศูนย์พักพิงมากกว่า 15 แห่ง)`;
        });
      }

      this.districtOfficeLayer.addLayer(officeMarker);
 
      // เส้นทางจากศูนย์พักพิงไปยังที่ว่าการอำเภอ
      districtCheckpoints.forEach(cp => {
        if (useRoadRouting) {
          this.drawRoadRouteToOffice(cp, office);
        } else {
          this.drawStraightLineToOffice(cp, office);
        }
      });
    });
  },
 
  // วาดเส้นประตรง (Haversine) ใช้เป็นค่าประมาณเมื่อมีจุดมากเกินกว่าจะยิง OSRM พร้อมกันได้
  drawStraightLineToOffice(cp, office) {
    const km = this.haversineDistanceKm(cp.lat, cp.lng, office.lat, office.lng);
    const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
    const lineColor = km <= 5 ? '#16a34a' : km <= 15 ? '#d97706' : '#dc2626';
 
    const connectorLine = L.polyline(
      [[cp.lat, cp.lng], [office.lat, office.lng]],
      { color: lineColor, weight: 2, opacity: 0.5, dashArray: '5, 7' }
    );
    this.districtOfficeLayer.addLayer(connectorLine);
    this.placeDistanceLabel((cp.lat + office.lat) / 2, (cp.lng + office.lng) / 2, displayKm, lineColor);
  },
 
  // วาดเส้นทางตามถนนจริงจาก OSRM (async) — เริ่มด้วยเส้นประตรงชั่วคราวระหว่างรอผลลัพธ์
  async drawRoadRouteToOffice(cp, office) {
    const lineColor = '#0ea5e9';
    const tempLine = L.polyline(
      [[cp.lat, cp.lng], [office.lat, office.lng]],
      { color: lineColor, weight: 2, opacity: 0.35, dashArray: '3, 8' }
    );
    this.districtOfficeLayer.addLayer(tempLine);
 
    const route = await this.fetchRoadRoute(cp.lat, cp.lng, office.lat, office.lng);
 
    // ลบเส้นประชั่วคราวออกก่อนวาดผลลัพธ์จริง (เผื่อผู้ใช้สลับเลเยอร์ไปแล้วเลเยอร์ถูก clear)
    if (this.districtOfficeLayer.hasLayer(tempLine)) {
      this.districtOfficeLayer.removeLayer(tempLine);
    } else {
      return; // เลเยอร์ถูกล้างไปแล้วระหว่างรอผลลัพธ์ ไม่ต้องวาดต่อ
    }
 
    if (route && route.latlngs && route.latlngs.length > 1) {
      const km = route.km;
      const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
      const roadColor = km <= 5 ? '#16a34a' : km <= 15 ? '#d97706' : '#dc2626';
 
      const roadLine = L.polyline(route.latlngs, { color: roadColor, weight: 3, opacity: 0.75 });
      this.districtOfficeLayer.addLayer(roadLine);
 
      const midIdx = Math.floor(route.latlngs.length / 2);
      const [midLat, midLng] = route.latlngs[midIdx];
      this.placeDistanceLabel(midLat, midLng, displayKm, roadColor, true);
    } else {
      // OSRM ใช้ไม่ได้/ไม่พบเส้นทาง — ใช้เส้นประตรงเป็นค่าสำรอง
      this.drawStraightLineToOffice(cp, office);
    }
  },
 
  // วางป้ายแสดงระยะทางบนแผนที่ ณ ตำแหน่งที่กำหนด
  placeDistanceLabel(lat, lng, displayKm, color, isRoad = false) {
    const icon = L.divIcon({
      className: 'district-dist-label',
      html: `<div style="background:var(--bg-surface); border:1px solid ${color}; color:${color}; font-size:10.5px; font-weight:700; padding:1px 6px; border-radius:10px; white-space:nowrap; box-shadow:var(--shadow-sm); transform:translate(-50%,-50%); display:flex; align-items:center; gap:3px;">${isRoad ? '<i class="fa-solid fa-road" style="font-size:8px;"></i> ' : ''}${displayKm} กม.</div>`,
      iconSize: [0, 0],
      iconAnchor: [0, 0]
    });
    this.districtOfficeLayer.addLayer(L.marker([lat, lng], { icon: icon, interactive: false, zIndexOffset: 400 }));
  },
 
  toggleDistrictOfficeLayer() {
    this.districtOfficeLayerVisible = !this.districtOfficeLayerVisible;
    this.renderDistrictOfficeLayer();
 
    const currentDistrict = (document.getElementById('districtSelect') || {}).value || '';
    if (this.districtOfficeLayerVisible && currentDistrict) {
      const currentCheckpoints = this.clusterGroupLayer.getLayers().map(m => m.checkpointPayload).filter(Boolean);
      this.renderInterShelterLinks(currentDistrict, currentCheckpoints);
    } else if (this.interShelterLayer) {
      this.interShelterLayer.clearLayers();
    }
 
    const label = document.getElementById('officeLayerLegendLabel');
    if (label) label.textContent = `ที่ว่าการอำเภอ + เส้นระยะทาง (${this.districtOfficeLayerVisible ? 'เปิด' : 'ปิด'})`;
    this.displayNotification(
      this.districtOfficeLayerVisible ? 'แสดงที่ว่าการอำเภอและเส้นระยะทางแล้ว' : 'ซ่อนที่ว่าการอำเภอและเส้นระยะทางแล้ว',
      'info'
    );
  },
 
  // เมื่อเปิด popup ของศูนย์พักพิง: คำนวณและวาดเส้นทางถนนจริงไปยังที่ว่าการอำเภอเฉพาะจุดนั้น
  async showPopupRouteToOffice(cp) {
    if (!this.popupRouteLayer) return;
    this.popupRouteLayer.clearLayers();
 
    const office = this.districtOffices[cp.district];
    if (!office) return;
 
    const requestToken = (this._popupRouteToken = (this._popupRouteToken || 0) + 1);
 
    const tempLine = L.polyline(
      [[cp.lat, cp.lng], [office.lat, office.lng]],
      { color: '#0ea5e9', weight: 2, opacity: 0.35, dashArray: '3, 8' }
    );
    this.popupRouteLayer.addLayer(tempLine);
 
    // 1. เส้นทางถนนจริงจากศูนย์พักพิงไปที่ว่าการอำเภอ
    const officeRoutePromise = this.fetchRoadRoute(cp.lat, cp.lng, office.lat, office.lng);
 
    // 2. เส้นทางถนนจริงสำหรับศูนย์ใกล้เคียง (สูงสุด 4 แห่ง)
    const sameDistrict = this.checkpointsRawData.filter(o =>
      o.id !== cp.id && o.district === cp.district && o.lat && o.lng
    ).map(o => ({
      id: o.id,
      name: o.name,
      lat: o.lat,
      lng: o.lng,
      km: this.haversineDistanceKm(cp.lat, cp.lng, o.lat, o.lng)
    })).sort((a, b) => a.km - b.km).slice(0, 4);
 
    const nearbyRoutePromises = sameDistrict.map(o =>
      this.fetchRoadRoute(cp.lat, cp.lng, o.lat, o.lng)
        .then(r => ({ id: o.id, km: r ? r.km : null }))
        .catch(() => ({ id: o.id, km: null }))
    );
 
    // รอผลลัพธ์ทุกเส้นทาง
    const [officeRoute, ...nearbyRoutes] = await Promise.all([officeRoutePromise, ...nearbyRoutePromises]);
 
    if (requestToken !== this._popupRouteToken) return;
 
    this.popupRouteLayer.clearLayers();
 
    // — วาดเส้นทางถนนจริงบนแผนที่ และอัพเดทตัวเลขในป๊อปอัพ
    if (officeRoute && officeRoute.latlngs && officeRoute.latlngs.length > 1) {
      const km = officeRoute.km;
      const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
      const roadColor = km <= 5 ? '#16a34a' : km <= 15 ? '#d97706' : '#dc2626';
      const roadLine = L.polyline(officeRoute.latlngs, { color: roadColor, weight: 4, opacity: 0.85 });
      this.popupRouteLayer.addLayer(roadLine);
 
      const midIdx = Math.floor(officeRoute.latlngs.length / 2);
      const [midLat, midLng] = officeRoute.latlngs[midIdx];
      const labelIcon = L.divIcon({
        className: 'district-dist-label',
        html: `<div style="background:var(--bg-surface); border:1px solid ${roadColor}; color:${roadColor}; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; box-shadow:var(--shadow-md); transform:translate(-50%,-50%); display:flex; align-items:center; gap:4px;"><i class="fa-solid fa-road" style="font-size:9px;"></i> ${displayKm} กม. ตามถนน</div>`,
        iconSize: [0, 0],
        iconAnchor: [0, 0]
      });
      this.popupRouteLayer.addLayer(L.marker([midLat, midLng], { icon: labelIcon, interactive: false, zIndexOffset: 600 }));
 
      // อัพเดทตัวเลขระยะถนนจริงในแถว "ห่างจากที่ว่าการ" ของ popup
      const distEl = document.getElementById(`popup-office-dist-${cp.id}`);
      if (distEl) {
        const distColor = km <= 5 ? 'var(--success)' : km <= 15 ? 'var(--warning)' : 'var(--danger)';
        const officeName = office.name || ('ที่ว่าการอำเภอ' + cp.district);
        distEl.innerHTML = `ห่างจาก${this.escapeToken(officeName)}: <strong style="color:${distColor};">${displayKm} กม.</strong> <span style="font-size:10px; color:var(--text-muted);"><i class="fa-solid fa-road" style="font-size:9px;"></i> ถนนจริง</span>`;
      }
    } else {
      // OSRM ใช้ไม่ได้ — ใช้เส้นประตรงเป็นค่าสำรอง พร้อมระยะ Haversine
      const km = this.haversineDistanceKm(cp.lat, cp.lng, office.lat, office.lng);
      const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
      const fallbackLine = L.polyline(
        [[cp.lat, cp.lng], [office.lat, office.lng]],
        { color: '#94a3b8', weight: 2, opacity: 0.5, dashArray: '5, 7' }
      );
      this.popupRouteLayer.addLayer(fallbackLine);
      const officeLabelEl = document.getElementById(`popup-office-dist-label-${cp.id}`);
      if (officeLabelEl) officeLabelEl.innerHTML = `<span style="font-size:10px; color:var(--text-muted);">~เส้นตรง</span>`;
      this.displayNotification('ไม่สามารถคำนวณเส้นทางถนนได้ในขณะนี้ แสดงระยะเส้นตรงประมาณการแทน', 'warning');
    }
 
    // — อัพเดทระยะถนนจริงสำหรับศูนย์ใกล้เคียงใน popup
    let allNearbyResolved = true;
    nearbyRoutes.forEach((res, idx) => {
      const o = sameDistrict[idx];
      if (!o) return;
      const el = document.getElementById(`popup-near-${cp.id}-${o.id}`);
      if (!el) return;
      const km = res.km !== null ? res.km : parseFloat(el.dataset.straight || 0);
      const isRoad = res.km !== null;
      if (!isRoad) { allNearbyResolved = false; }
      const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
      const distColor = km <= 5 ? 'var(--success)' : km <= 15 ? 'var(--warning)' : 'var(--danger)';
      el.style.color = distColor;
      el.innerHTML = `${isRoad ? '<i class="fa-solid fa-road" style="font-size:8px; margin-right:2px;"></i>' : ''}${displayKm} กม.`;
    });
    const nearNoteEl = document.getElementById(`popup-near-note-${cp.id}`);
    if (nearNoteEl && sameDistrict.length > 0) {
      nearNoteEl.innerHTML = allNearbyResolved
        ? `<i class="fa-solid fa-road" style="font-size:9px;"></i> ระยะตามถนนจริง`
        : `<i class="fa-solid fa-road" style="font-size:9px;"></i> ระยะตามถนนจริง (บางเส้นใช้เส้นตรงประมาณการ)`;
    }
  },
 
  clearPopupRoute() {
    this._popupRouteToken = (this._popupRouteToken || 0) + 1; // ยกเลิกผลลัพธ์ที่กำลังรออยู่
    if (this.popupRouteLayer) this.popupRouteLayer.clearLayers();
  },
 
  // =========================================================
  // เส้นทางถนนจริง + ระยะห่างระหว่างศูนย์พักพิงด้วยกันเองในอำเภอที่เลือก
  // เชื่อมครบทุกคู่ที่เป็นไปได้ (complete graph) — แสดงเมื่อกรองเลือกอำเภอเท่านั้น
  // =========================================================
  INTER_SHELTER_MAX_NODES: 10, // จำกัดจำนวนศูนย์สูงสุดที่จะเชื่อมครบทุกคู่ (10 จุด = 45 เส้น) กันยิง OSRM มากเกินไป
 
  renderInterShelterLinks(districtName, checkpoints) {
    if (!this.interShelterLayer) return;
    this.interShelterLayer.clearLayers();
 
    if (!districtName || !this.districtOfficeLayerVisible) return;
 
    const validCheckpoints = (checkpoints || []).filter(cp => cp.lat && cp.lng);
    if (validCheckpoints.length < 2) return;
 
    if (validCheckpoints.length > this.INTER_SHELTER_MAX_NODES) {
      this.displayNotification(
        `อำเภอนี้มีศูนย์พักพิง ${validCheckpoints.length} แห่ง เกินขีดจำกัดการเชื่อมเส้นระยะห่างทุกคู่ (สูงสุด ${this.INTER_SHELTER_MAX_NODES} แห่ง) จึงไม่แสดงเส้นเชื่อมระหว่างศูนย์`,
        'warning'
      );
      return;
    }
 
    // สร้างคู่ทั้งหมดที่เป็นไปได้ระหว่างศูนย์พักพิง (n*(n-1)/2 คู่)
    const pairs = [];
    for (let i = 0; i < validCheckpoints.length; i++) {
      for (let j = i + 1; j < validCheckpoints.length; j++) {
        pairs.push([validCheckpoints[i], validCheckpoints[j]]);
      }
    }
 
    pairs.forEach(([a, b]) => this.drawInterShelterRoute(a, b));
  },
 
  // วาดเส้นทางถนนจริงระหว่างศูนย์พักพิงสองแห่ง พร้อมป้ายระยะทาง (เริ่มด้วยเส้นประชั่วคราวระหว่างรอผลลัพธ์)
  async drawInterShelterRoute(cpA, cpB) {
    const tempLine = L.polyline(
      [[cpA.lat, cpA.lng], [cpB.lat, cpB.lng]],
      { color: '#a855f7', weight: 1.5, opacity: 0.3, dashArray: '2, 6' }
    );
    this.interShelterLayer.addLayer(tempLine);
 
    const route = await this.fetchRoadRoute(cpA.lat, cpA.lng, cpB.lat, cpB.lng);
 
    if (this.interShelterLayer.hasLayer(tempLine)) {
      this.interShelterLayer.removeLayer(tempLine);
    } else {
      return; // เลเยอร์ถูกล้างไปแล้วระหว่างรอผลลัพธ์ (เช่น ผู้ใช้เปลี่ยนตัวกรองไปแล้ว)
    }
 
    let latlngs, km;
    if (route && route.latlngs && route.latlngs.length > 1) {
      latlngs = route.latlngs;
      km = route.km;
    } else {
      // OSRM ใช้ไม่ได้ — สำรองด้วยเส้นตรง Haversine
      latlngs = [[cpA.lat, cpA.lng], [cpB.lat, cpB.lng]];
      km = this.haversineDistanceKm(cpA.lat, cpA.lng, cpB.lat, cpB.lng);
    }
 
    const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
    const lineColor = '#a855f7';
 
    const finalLine = L.polyline(latlngs, { color: lineColor, weight: 2, opacity: 0.6, dashArray: '6, 4' });
    finalLine.bindTooltip(
      `<i class="fa-solid fa-arrows-left-right"></i> ${this.escapeToken(cpA.name)} ↔ ${this.escapeToken(cpB.name)}: <strong>${displayKm} กม.</strong>`,
      { sticky: true }
    );
    this.interShelterLayer.addLayer(finalLine);
 
    const midIdx = Math.floor(latlngs.length / 2);
    const [midLat, midLng] = latlngs[midIdx];
    const labelIcon = L.divIcon({
      className: 'inter-shelter-dist-label',
      html: `<div style="background:var(--bg-surface); border:1px solid ${lineColor}; color:${lineColor}; font-size:10px; font-weight:700; padding:1px 6px; border-radius:10px; white-space:nowrap; box-shadow:var(--shadow-md); transform:translate(-50%,-50%);">${displayKm} กม.</div>`,
      iconSize: [0, 0],
      iconAnchor: [0, 0]
    });
    this.interShelterLayer.addLayer(L.marker([midLat, midLng], { icon: labelIcon, interactive: false, zIndexOffset: 350 }));
  },
 
  initGeospatialSystem() {
    ThemeManagementEngine.initializeThemeEngine();
 
    const currentTheme = document.documentElement.getAttribute('data-theme');
    
    // ตั้งค่าพิกัดแผนที่พื้นฐาน
    this.leafletMapObject = L.map('map', {
      zoomControl: !window.matchMedia('(max-width: 1023px)').matches,
      attributionControl: true,
      maxZoom: 18,
      minZoom: 7
    }).setView([<?= $mapCenterLat ?>, <?= $mapCenterLng ?>], <?= $mapZoom ?>);
 
    this.updateBaseLayer();
    
    L.control.scale({ metric: true, imperial: false, position: 'bottomleft' }).addTo(this.leafletMapObject);
 
    this.clusterGroupLayer = L.markerClusterGroup({
      showCoverageOnHover: false,
      maxClusterRadius: 45,
      spiderfyOnMaxZoom: true
    });
    this.leafletMapObject.addLayer(this.clusterGroupLayer);
 
    this.districtOfficeLayer = L.layerGroup();
    this.leafletMapObject.addLayer(this.districtOfficeLayer);
 
    this.popupRouteLayer = L.layerGroup();
    this.leafletMapObject.addLayer(this.popupRouteLayer);
 
    this.interShelterLayer = L.layerGroup();
    this.leafletMapObject.addLayer(this.interShelterLayer);
 
    this.renderDynamicLegendUI();
    this.populateMapMarkers();
    this.renderDistrictOfficeLayer();
    this.loadAdministrativeBoundary('ระยอง');
    this.recalculateAnalyticalDashboardStats();
 
    // บังคับแปลงโครงสร้างไอคอนเมื่อขอบเขตหน้าจอถูกเปลี่ยนสภาพ (Viewport Responsive)
    window.addEventListener('resize', () => this.handleViewportReorientation());
  },
 
  cycleMapStyle() {
    const styles = ['theme', 'osm', 'satellite'];
    let index = styles.indexOf(this.activeMapStyle);
    this.activeMapStyle = styles[(index + 1) % styles.length];

    this.updateBaseLayer();

    const icons = {
      theme: '<i class="fa-solid fa-map"></i>',
      osm: '<i class="fa-solid fa-map-location-dot"></i>',
      satellite: '<i class="fa-solid fa-satellite"></i>'
    };
    
    const btn = document.getElementById('mapStyleBtn');
    if (btn) btn.innerHTML = icons[this.activeMapStyle];

    const styleNames = {
      theme: 'แผนที่ปรับตามธีม (Carto)',
      osm: 'แผนที่ถนนทั่วไป (OSM)',
      satellite: 'แผนที่ดาวเทียม (Satellite)'
    };
    this.displayNotification(`รูปแบบแผนที่: ${styleNames[this.activeMapStyle]}`, 'info');
  },

  updateBaseLayer() {
    if (this.activeTilesLayer) {
      this.leafletMapObject.removeLayer(this.activeTilesLayer);
    }

    let tileUrl;
    let attribution;

    if (this.activeMapStyle === 'theme') {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      tileUrl = currentTheme === 'dark' 
        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
      attribution = '© OpenStreetMap contributors © CARTO';
    } else if (this.activeMapStyle === 'osm') {
      tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
      attribution = '© OpenStreetMap contributors';
    } else if (this.activeMapStyle === 'satellite') {
      // ใช้งาน Esri World Imagery สำหรับแผนที่ดาวเทียมคุณภาพสูงและเสถียร
      tileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
      attribution = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';
    }

    this.activeTilesLayer = L.tileLayer(tileUrl, {
      attribution: attribution,
      subdomains: 'abcd',
      maxZoom: 20
    });

    this.leafletMapObject.addLayer(this.activeTilesLayer);
    this.activeTilesLayer.bringToBack();
  },

  hotSwapMapBaseTiles(themeMode) {
    // จะทำการเปลี่ยนเฉพาะเมื่อรูปแบบแผนที่ถูกตั้งไว้เป็น "ปรับตามธีม" เท่านั้น
    if (this.activeMapStyle === 'theme') {
      this.updateBaseLayer();
    }
  },
 
  generateCustomMarkerIcon(color, code, name) {
    const isMobileDevice = window.innerWidth <= 1023;
    const dimension = isMobileDevice ? 30 : 36;
    const radiusAnchor = dimension / 2;
    
    const glyphs = { MAIN: 'fa-building-shield', SUB: 'fa-house-medical', TEMP: 'fa-tent', COMMUNITY: 'fa-users-gear' };
    const shapes = { MAIN: 'circle', SUB: 'rounded', TEMP: 'diamond', COMMUNITY: 'hexagon' };
    
    const glyph = glyphs[code] || 'fa-circle-dot';
    const shape = shapes[code] || 'circle';
 
    let htmlWrap = '';
    
    if (shape === 'circle') {
      htmlWrap = `<div class="legend-shape circle" style="background:${color}; width:${dimension}px; height:${dimension}px; line-height:${dimension}px; text-align:center; color:white; border:2px solid #fff; box-shadow:var(--shadow-md); font-size:14px;"><i class="fa-solid ${glyph}"></i></div>`;
    } else if (shape === 'rounded') {
      htmlWrap = `<div class="legend-shape rounded" style="background:${color}; width:${dimension}px; height:${dimension}px; line-height:${dimension}px; text-align:center; color:white; border:2px solid #fff; box-shadow:var(--shadow-md); font-size:14px; border-radius:6px;"><i class="fa-solid ${glyph}"></i></div>`;
    } else if (shape === 'diamond') {
      const innerOffset = isMobileDevice ? 20 : 25;
      htmlWrap = `<div class="legend-shape diamond" style="background:${color}; width:${innerOffset}px; height:${innerOffset}px; border:2px solid #fff; box-shadow:var(--shadow-md); display:flex; align-items:center; justify-content:center;"><i class="fa-solid ${glyph}" style="transform:rotate(-45deg); color:white; font-size:11px;"></i></div>`;
    } else if (shape === 'hexagon') {
      htmlWrap = `<div class="legend-shape hexagon" style="background:${color}; width:${dimension}px; height:${dimension}px; display:flex; align-items:center; justify-content:center; color:white; box-shadow:var(--shadow-md); font-size:12px;"><i class="fa-solid ${glyph}"></i></div>`;
    }
 
    return L.divIcon({
      className: 'custom-gis-marker',
      html: htmlWrap,
      iconSize: [dimension, dimension],
      iconAnchor: [radiusAnchor, radiusAnchor],
      popupAnchor: [0, -(radiusAnchor + 4)]
    });
  },
 
  populateMapMarkers() {
    this.clusterGroupLayer.clearLayers();
    this.incidentLayers.forEach(lyr => this.leafletMapObject.removeLayer(lyr));
    this.incidentLayers = [];
 
    // 1. ประมวลผลและสร้างหมุดศูนย์พักพิง (Shelter Checkpoints Layers)
    this.checkpointsRawData.forEach(cp => {
      if (!cp.lat || !cp.lng) return;
      
      const icon = this.generateCustomMarkerIcon(cp.type_color, cp.type_code, cp.name);
      const marker = L.marker([cp.lat, cp.lng], { icon: icon });
      
      marker.bindPopup(() => this.compilePopupMarkupTemplate(cp), {
        maxWidth: 320,
        minWidth: 300
      });
 
      marker.on('popupopen', () => this.showPopupRouteToOffice(cp));
      marker.on('popupclose', () => this.clearPopupRoute());
      
      marker.checkpointPayload = cp; // แนบข้อมูลในออบเจกต์เพื่อการค้นหาขั้นสูง
      this.clusterGroupLayer.addLayer(marker);
    });
 
    // 2. ประมวลผลและสร้างหมุดจุดเสี่ยงภัยสถานการณ์ฉุกเฉิน (Incident Vector Markers)
    this.incidentsRawData.forEach(inc => {
      if (!inc.lat || !inc.lng) return;
 
      const markerColor = inc.severity === 'critical' ? '#dc2626' : '#f59e0b';
      const pulseAnimation = inc.severity === 'critical' ? 'animation: pulse 1.5s infinite;' : '';
      const incidentIcon = L.divIcon({
        className: 'incident-marker-node',
        html: `<div style="background:${markerColor}; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; border:2px solid white; box-shadow:var(--shadow-md); font-size:11px; ${pulseAnimation}"><i class="fa-solid fa-triangle-exclamation"></i></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
      });
 
      const popupHtml = `
        <div style="padding:12px; font-family:var(--font); width:24px; min-width:220px;">
          <div style="font-weight:700; color:var(--danger); font-size:14px; margin-bottom:4px;"><i class="fa-solid fa-triangle-exclamation"></i> แจ้งเตือนเหตุภัยพิบัติ</div>
          <div style="font-weight:600; color:var(--text-primary); margin-bottom:6px;">${this.escapeToken(inc.title)}</div>
          <div style="font-size:12px; color:var(--text-secondary);">ประเภท: ${this.escapeToken(inc.type)}</div>
          <div style="font-size:12px; color:var(--text-secondary);">ความรุนแรง: ${inc.severity === 'critical' ? '<span style="color:var(--danger); font-weight:700;">วิกฤตร้ายแรง</span>' : 'เฝ้าระวัง'}</div>
          <div style="font-size:11px; color:var(--text-muted); margin-top:6px; border-top:1px solid var(--border); padding-top:4px;">รายงานเมื่อ: ${inc.created_at}</div>
        </div>`;
 
      const marker = L.marker([inc.lat, inc.lng], { icon: incidentIcon }).bindPopup(popupHtml);
      marker.addTo(this.leafletMapObject);
      this.incidentLayers.push(marker);
    });
  },
 
  compilePopupMarkupTemplate(cp) {
    const pct = cp.capacity > 0 ? Math.min(Math.round((cp.current_occupancy / cp.capacity) * 100), 100) : 0;
    const colorBar = pct >= 80 ? 'var(--danger)' : pct >= 50 ? 'var(--warning)' : 'var(--success)';
    
    const bannerImg = cp.image_url 
      ? `<img class="popup-header-img" src="${cp.image_url}" alt="รูปภาพศูนย์" onclick="CoreMapEngine.openImageLightbox('${cp.image_url}')">`
      : `<div class="popup-header-img" style="display:flex; align-items:center; justify-content:center; color:#4a5568; background:#1e293b;"><i class="fa-solid fa-image" style="font-size:36px; opacity:0.3;"></i></div>`;
 
    // ชุดโครงสร้างสิ่งอำนวยความสะดวก
    const facKeys = [
      { key: 'facility_toilet', icon: 'fa-restroom', label: 'ห้องน้ำ' },
      { key: 'facility_water', icon: 'fa-faucet-drip', label: 'น้ำอุปโภคบริโภค' },
      { key: 'facility_electricity', icon: 'fa-bolt', label: 'ไฟฟ้าหลัก' },
      { key: 'facility_generator', icon: 'fa-engine', label: 'เครื่องปั่นไฟสำรอง' },
      { key: 'facility_kitchen', icon: 'fa-kitchen-set', label: 'โรงครัวกลาง' },
      { key: 'facility_medical', icon: 'fa-briefcase-medical', label: 'จุดปฐมพยาบาล' },
      { key: 'facility_internet', icon: 'fa-wifi', label: 'อินเทอร์เน็ต' },
      { key: 'facility_disabled_access', icon: 'fa-wheelchair', label: 'ทางลาดผู้พิการ' }
    ];
 
    let facHtmlTags = '';
    facKeys.forEach(f => {
      if (cp[f.key]) {
        facHtmlTags += `<span class="fac-tag active"><i class="fa-solid ${f.icon}"></i> ${f.label}</span>`;
      }
    });
 
    return `
      <div>
        ${bannerImg}
        <div class="popup-body">
          <div class="popup-title">${this.escapeToken(cp.name)}</div>
          <span class="popup-badge" style="background:${cp.type_color}18; color:${cp.type_color}; border:1px solid ${cp.type_color}40;"><i class="fa-solid fa-tags"></i> ${this.escapeToken(cp.type)}</span>
          
          <div class="popup-data-row"><i class="fa-solid fa-map-pin"></i><span>ต.${this.escapeToken(cp.sub_district || '-')} อ.${this.escapeToken(cp.district || '-')} จ.ระยอง</span></div>
          ${(() => {
            const dist = this.getDistrictOfficeDistance(cp);
            if (!dist) return '';
            const km = dist.km;
            const displayKm = km >= 10 ? km.toFixed(1) : km.toFixed(2);
            const distColor = km <= 5 ? 'var(--success)' : km <= 15 ? 'var(--warning)' : 'var(--danger)';
            return `<div class="popup-data-row">
              <i class="fa-solid fa-building-columns" style="color:${distColor};"></i>
              <span id="popup-office-dist-${cp.id}">ห่างจาก${this.escapeToken(dist.name)}: <strong style="color:${distColor};">${displayKm} กม.</strong> <span style="font-size:10px; color:var(--text-muted);" id="popup-office-dist-label-${cp.id}"><i class="fa-solid fa-spinner fa-spin" style="font-size:9px;"></i></span></span>
            </div>`;
          })()}
          ${cp.manager_name ? `<div class="popup-data-row"><i class="fa-solid fa-user-tie"></i><span>ผบ.ศูนย์: ${this.escapeToken(cp.manager_name)}</span></div>` : ''}
          
          <div class="popup-section-title"><i class="fa-solid fa-chart-pie"></i> ข้อมูลขีดความสามารถการรองรับ</div>
          <div class="popup-data-row" style="justify-content:space-between; margin-bottom:2px;">
            <span >อัตราความจุ: <strong>${cp.current_occupancy.toLocaleString()}</strong> / ${cp.capacity.toLocaleString()} คน</span>
            คิดเป็น<span style="color:${colorBar}; font-weight:700;">${pct}%</span>
          </div>
          <div class="district-occ-track" style="height:6px; background:var(--bg-base);"><div style="width:${pct}%; height:100%; background:${colorBar}; border-radius:3px;"></div></div>
          
          <div class="popup-grid-2" style="margin-top:10px;">
            <div class="popup-stat-box"><span>กลุ่มเปราะบาง (คน)</span><strong>${((cp.occupancy_elderly || 0) + (cp.occupancy_disabled || 0) + (cp.occupancy_bedridden || 0)).toLocaleString()}</strong></div>
            <div class="popup-stat-box"><span>เสบียงอาหารคงเหลือ</span><strong>${cp.food_supply_days > 0 ? cp.food_supply_days + ' วัน' : 'วิกฤต/ไม่มีข้อมูล'}</strong></div>
          </div>
 
          ${facHtmlTags ? `<div class="popup-section-title"><i class="fa-solid fa-hand-holding-heart"></i> สิ่งอำนวยความสะดวกผ่านเกณฑ์</div><div class="fac-flex-container">${facHtmlTags}</div>` : ''}
 
          ${(() => {
            // ระยะทางระหว่างศูนย์พักพิงในอำเภอเดียวกัน
            const sameDistrict = this.checkpointsRawData.filter(o =>
              o.id !== cp.id && o.district === cp.district && o.lat && o.lng
            );
            if (!sameDistrict.length) return '';
 
            const nearbyList = sameDistrict
              .map(o => ({
                id: o.id,
                name: o.name,
                lat: o.lat,
                lng: o.lng,
                km: this.haversineDistanceKm(cp.lat, cp.lng, o.lat, o.lng)
              }))
              .sort((a, b) => a.km - b.km)
              .slice(0, 4); // แสดงสูงสุด 4 แห่งใกล้ที่สุด
 
            const rows = nearbyList.map(o => {
              const displayKm = o.km >= 10 ? o.km.toFixed(1) : o.km.toFixed(2);
              const distColor = o.km <= 5 ? 'var(--success)' : o.km <= 15 ? 'var(--warning)' : 'var(--danger)';
              return `<div class="popup-data-row" style="justify-content:space-between;">
                <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px;"><i class="fa-solid fa-circle-dot" style="color:${distColor}; font-size:9px;"></i> ${this.escapeToken(o.name)}</span>
                <strong id="popup-near-${cp.id}-${o.id}" data-straight="${o.km}" style="color:${distColor}; margin-left:6px; white-space:nowrap;">${displayKm} กม.</strong>
              </div>`;
            }).join('');
 
            return `<div class="popup-section-title"><i class="fa-solid fa-arrows-left-right"></i> ศูนย์ใกล้เคียงในอำเภอเดียวกัน</div>
              ${rows}
              <div id="popup-near-note-${cp.id}" style="font-size:10.5px; color:var(--text-muted); margin-top:2px; margin-bottom:4px;"><i class="fa-solid fa-spinner fa-spin" style="font-size:9px;"></i> กำลังคำนวณระยะตามถนนจริง...</div>`;
          })()}
 
          <div class="popup-actions-layout">
            <a href="https://www.google.com/maps/search/?api=1&query=${cp.lat},${cp.lng}" target="_blank" class="popup-btn-action popup-btn-nav"><i class="fa-solid fa-route"></i> นำทางภายนอก</a>
            <a href="public_checkpoint.php?id=${cp.id}" target="_blank" class="popup-btn-action popup-btn-phone"><i class="fa fa-search"></i> รายละเอียด →</a>
          </div>
        </div>
      </div>`;
  },
 
  applyActiveFilters() {
    const searchKeyword = (document.getElementById('searchInput').value || '').trim().toLowerCase();
    const targetDistrict = (document.getElementById('districtSelect').value || '').trim();
    const targetTypeId = document.getElementById('typeSelect').value;
 
    this.executeFilterCoreLogic(searchKeyword, targetDistrict, targetTypeId);
  },
 
  applyFiltersFromMobileDrawer() {
    const searchKeyword = (document.getElementById('mSearchInput').value || '').trim().toLowerCase();
    const targetDistrict = (document.getElementById('mDistrictSelect').value || '').trim();
    const targetTypeId = document.getElementById('mTypeSelect').value;
 
    // ซิงค์ค่าลง Desktop control UI เพื่อความเสถียรของสเตต
    document.getElementById('searchInput').value = searchKeyword;
    document.getElementById('districtSelect').value = targetDistrict;
    document.getElementById('typeSelect').value = targetTypeId;
 
    this.closeMobileDrawer('filterDrawer');
    this.executeFilterCoreLogic(searchKeyword, targetDistrict, targetTypeId);
  },
 
  executeFilterCoreLogic(keyword, district, typeId) {
    let matchCount = 0;
    const markers = this.clusterGroupLayer.getLayers();
    const matchedCheckpoints = [];
 
    this.clusterGroupLayer.clearLayers();
 
    this.checkpointsRawData.forEach((cp, idx) => {
      const matchKeyword = !keyword || cp.name.toLowerCase().includes(keyword) || (cp.address && cp.address.toLowerCase().includes(keyword));
      const matchDistrict = !district || cp.district === district;
      const matchType = !typeId || cp.type_id === parseInt(typeId);
 
      if (matchKeyword && matchDistrict && matchType) {
        const icon = this.generateCustomMarkerIcon(cp.type_color, cp.type_code, cp.name);
        const marker = L.marker([cp.lat, cp.lng], { icon: icon }).bindPopup(() => this.compilePopupMarkupTemplate(cp), { maxWidth: 320, minWidth: 300 });
        marker.on('popupopen', () => this.showPopupRouteToOffice(cp));
        marker.on('popupclose', () => this.clearPopupRoute());
        marker.checkpointPayload = cp;
        this.clusterGroupLayer.addLayer(marker);
        matchedCheckpoints.push(cp);
        matchCount++;
      }
    });
 
    this.recalculateAnalyticalDashboardStats();
    this.renderDistrictOfficeLayer();
 
    if (district) {
      this.renderInterShelterLinks(district, matchedCheckpoints);
    } else if (this.interShelterLayer) {
      this.interShelterLayer.clearLayers();
    }
 
    this.displayNotification(`ผลการกรองขั้นสูง: พบพื้นที่ตรงเงื่อนไข ${matchCount} แห่ง`, 'success');
 
    if (district) {
      this.loadAdministrativeBoundary(`อำเภอ${district} ระยอง ประเทศไทย`, 6);
    } else {
      this.loadAdministrativeBoundary('ระยอง');
    }
  },
 
  resetActiveFilters() {
    ['searchInput', 'districtSelect', 'typeSelect', 'mSearchInput', 'mDistrictSelect', 'mTypeSelect'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
 
    this.populateMapMarkers();
    this.loadAdministrativeBoundary('ระยอง');
    this.recalculateAnalyticalDashboardStats();
    this.renderDistrictOfficeLayer();
    if (this.interShelterLayer) this.interShelterLayer.clearLayers();
    this.displayNotification('ล้างการจัดตัวกรอง เรียบร้อยแล้ว', 'info');
  },
 
  recalculateAnalyticalDashboardStats() {
    const currentActiveLayers = this.clusterGroupLayer.getLayers();
    const activePayloads = currentActiveLayers.map(m => {
      return this.checkpointsRawData.find(raw => raw.lat === m.getLatLng().lat && raw.lng === m.getLatLng().lng);
    }).filter(Boolean);
 
    const count = activePayloads.length;
    const occ = activePayloads.reduce((sum, current) => sum + current.current_occupancy, 0);
    const cap = activePayloads.reduce((sum, current) => sum + current.capacity, 0);
    const uniqueDistricts = new Set(activePayloads.map(cp => cp.district).filter(Boolean));
 
    const percent = cap > 0 ? Math.round((occ / cap) * 100) : 0;
    const barColorClass = percent >= 80 ? 'occ-high' : percent >= 50 ? 'occ-med' : 'occ-low';
    const hexColor = percent >= 80 ? 'var(--danger)' : percent >= 50 ? 'var(--warning)' : 'var(--success)';
 
    // ปรับเปลี่ยนข้อมูลบน DOM Node Component ทั้งระบบ (Desktop + Mobile Sheet)
    this.assignDOMText('sbTotal', count);
    this.assignDOMText('sbOcc', occ.toLocaleString());
    this.assignDOMText('sbCap', cap.toLocaleString());
    this.assignDOMText('sbDistricts', uniqueDistricts.size);
    
    const fillBar = document.getElementById('sbOccBar');
    if (fillBar) {
      fillBar.style.width = `${Math.min(percent, 100)}%`;
      fillBar.className = `occ-fill ${barColorClass}`;
    }
    
    const textPct = document.getElementById('sbOccPct');
    if (textPct) { textPct.textContent = `${percent}%`; textPct.style.color = hexColor; }
 
    // คัดลอกค่าลงฟังก์ชัน Mobile KPI Layout
    this.assignDOMText('kpiTotal', count);
    this.assignDOMText('kpiOcc', occ.toLocaleString());
    const mPct = document.getElementById('kpiPct');
    if (mPct) { mPct.textContent = `${percent}%`; mPct.style.color = hexColor; }
 
    this.renderDistrictAnalyticalGridCards(activePayloads);
  },
 
  renderDistrictAnalyticalGridCards(activeData) {
    const grouped = {};
    activeData.forEach(cp => {
      const name = cp.district || 'ไม่ระบุอำเภอ';
      if (!grouped[name]) grouped[name] = { count: 0, occ: 0, cap: 0, distSum: 0, distCount: 0, minDist: Infinity, maxDist: 0 };
      grouped[name].count++;
      grouped[name].occ += cp.current_occupancy;
      grouped[name].cap += cp.capacity;
      // คำนวณระยะทางจากที่ว่าการอำเภอ
      const distInfo = this.getDistrictOfficeDistance(cp);
      if (distInfo) {
        grouped[name].distSum += distInfo.km;
        grouped[name].distCount++;
        if (distInfo.km < grouped[name].minDist) grouped[name].minDist = distInfo.km;
        if (distInfo.km > grouped[name].maxDist) grouped[name].maxDist = distInfo.km;
      }
    });
 
    const markup = Object.entries(grouped).map(([district, metrics]) => {
      const pct = metrics.cap > 0 ? Math.min(Math.round((metrics.occ / metrics.cap) * 100), 100) : 0;
      const color = pct >= 80 ? 'var(--danger)' : pct >= 50 ? 'var(--warning)' : 'var(--success)';
      
      // สร้าง HTML แสดงระยะทาง
      let distHtml = '';
      if (metrics.distCount > 0) {
        const avgKm = metrics.distSum / metrics.distCount;
        const avgDisplay = avgKm >= 10 ? avgKm.toFixed(1) : avgKm.toFixed(2);
        const distColor = avgKm <= 5 ? 'var(--success)' : avgKm <= 15 ? 'var(--warning)' : 'var(--danger)';
        const minDisplay = metrics.minDist >= 10 ? metrics.minDist.toFixed(1) : metrics.minDist.toFixed(2);
        const maxDisplay = metrics.maxDist >= 10 ? metrics.maxDist.toFixed(1) : metrics.maxDist.toFixed(2);
        distHtml = `
          <div class="district-dist-row">
            <i class="fa-solid fa-building-columns" style="color:${distColor}; font-size:11px;"></i>
            <span>ห่างที่ว่าการฯ เฉลี่ย <strong style="color:${distColor};">${avgDisplay} กม.</strong></span>
            <span style="color:var(--text-muted); font-size:11px;">(${minDisplay}–${maxDisplay} กม.)</span>
          </div>`;
      }
 
      return `
        <div class="district-card">
          <div class="district-card-head">
            <span><i class="fa-solid fa-map-location" style="color:var(--accent-light); margin-right:4px;"></i> อ.${this.escapeToken(district)}</span>
            <span class="district-badge">${metrics.count} ศูนย์</span>
          </div>
          <div class="district-occ-row">
            <span>${metrics.occ.toLocaleString()} / ${metrics.cap.toLocaleString()} คน</span>
            <div class="district-occ-track"><div style="width:${pct}%; height:100%; background:${color}; border-radius:3px;"></div></div>
            <span style="color:${color}; font-weight:700;">${pct}%</span>
          </div>
          ${distHtml}
        </div>`;
    }).join('');
 
    ['statsPanelInner', 'statsSheetDistricts'].forEach(id => {
      const container = document.getElementById(id);
      if (container) container.innerHTML = markup || '<div style="color:var(--text-muted); padding:12px; text-align:center; font-size:13px; width:100%">ไม่มีผลลัพธ์ข้อมูลเชิงลึกในพื้นที่นี้</div>';
    });
  },
 
  async loadAdministrativeBoundary(queryStr, customAdminLevel = 4) {
    const loaderLabel = document.getElementById('mapLoading');
    if (loaderLabel) loaderLabel.style.display = 'flex';
    
    if (this.boundaryGeoJsonLayer) {
      this.leafletMapObject.removeLayer(this.boundaryGeoJsonLayer);
      this.boundaryGeoJsonLayer = null;
    }
 
    try {
      let searchQuery = queryStr;
      if (queryStr === 'ระยอง') searchQuery = 'จังหวัดระยอง ประเทศไทย';
      
      const endpoint = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(searchQuery)}&format=json&polygon_geojson=1&limit=3&accept-language=th`;
      const response = await fetch(endpoint, { headers: { 'User-Agent': 'rayong-smart-shelter-enterprise/3.5' } });
      const rawPayload = await response.json();
      
      const targetPoly = rawPayload.find(r => r.class === 'boundary' && ['Polygon', 'MultiPolygon'].includes(r.geojson?.type)) || rawPayload.find(r => ['Polygon', 'MultiPolygon'].includes(r.geojson?.type));
      
      if (targetPoly?.geojson) {
        this.boundaryGeoJsonLayer = L.geoJSON(targetPoly.geojson, {
          style: {
            color: queryStr === 'ระยอง' ? 'var(--warning)' : 'var(--accent)',
            weight: 2.5,
            dashArray: '6, 6',
            fillOpacity: 0.03,
            interactive: false
          }
        }).addTo(this.leafletMapObject);
 
        const bounds = this.boundaryGeoJsonLayer.getBounds();
        if (bounds.isValid()) {
          this.leafletMapObject.fitBounds(bounds, { padding: [24, 24], maxZoom: queryStr === 'ระยอง' ? 11 : 13 });
        }
        this.flashBoundaryBannerNotification(queryStr === 'ระยอง' ? 'พื้นที่จังหวัดระยอง' : queryStr.split(' ')[0]);
      }
    } catch (err) {
      console.error('สถาปัตยกรรมขอบเขต GeoJSON ขัดข้อง', err);
    } finally {
      if (loaderLabel) loaderLabel.style.display = 'none';
    }
  },
 
  flashBoundaryBannerNotification(textName) {
    const banner = document.getElementById('districtLabel');
    if (!banner) return;
    banner.innerHTML = `<i class="fa-solid fa-map-clip"></i> ขอบเขตปกครอง: ${this.escapeToken(textName)}`;
    banner.classList.add('show');
    clearTimeout(banner._dismissTimer);
    banner._dismissTimer = setTimeout(() => { banner.classList.remove('show'); }, 4000);
  },
 
  triggerGeolocate() {
    if (!navigator.geolocation) {
      this.displayNotification('อุปกรณ์ตรวจพบว่าไม่สนับสนุนระบบระบุตำแหน่ง GPS', 'error');
      return;
    }
    this.displayNotification('กำลังขอรับสัญญาณดาวน์โหลดพิกัดพิกัดตำแหน่งวิทยุ...', 'info');
    
    navigator.geolocation.getCurrentPosition(
      pos => {
        const { latitude: lat, longitude: lng, accuracy } = pos.coords;
        this.leafletMapObject.setView([lat, lng], 14);
        
        L.circle([lat, lng], { radius: accuracy / 2, color: 'var(--accent)', fillOpacity: 0.1, weight: 1 }).addTo(this.leafletMapObject);
        L.marker([lat, lng], {
          icon: L.divIcon({
            className: 'my-loc-dot',
            html: '<div style="background:#2563eb; width:16px; height:16px; border-radius:50%; border:3px solid white; box-shadow:0 0 0 4px rgba(37,99,235,0.4); animation: pulse 2s infinite;"></div>',
            iconSize: [16, 16], iconAnchor: [8, 8]
          })
        }).addTo(this.leafletMapObject).bindPopup('<b>📍 คุณอยู่ที่นี่</b><br>ข้อมูลความแม่นยำสูงระบบ GPS').openPopup();
        
        this.displayNotification('ระบุตำแหน่งของท่านพิกัดแผนที่ เรียบร้อยแล้ว', 'success');
      },
      () => this.displayNotification('การเข้าถึงพิกัดล้มเหลว สัญญาณถูกปฏิเสธ หรือเครือข่ายขัดข้อง', 'error'),
      { enableHighAccuracy: true, timeout: 8000 }
    );
  },
 
  triggerMapExport() {
    const loader = document.getElementById('loadingOverlay');
    const loadText = document.getElementById('loadingText');
    if (loader) { loader.classList.add('active'); loadText.textContent = 'ระบบสัมปทานแผนที่ กำลังจับภาพความคมชัดสูง...'; }
 
    setTimeout(async () => {
      try {
        const structuralContainers = document.querySelectorAll('.leaflet-control-container, .legend, #districtLabel, .map-loading');
        structuralContainers.forEach(c => c.style.display = 'none');
        
        const targetCanvas = await html2canvas(document.getElementById('map'), {
          scale: 2,
          useCORS: true,
          allowTaint: false,
          logging: false
        });
 
        structuralContainers.forEach(c => c.style.display = '');
        
        const downloadAnchor = document.createElement('a');
        downloadAnchor.download = `SHELTER_MAP_RAYONG_SNAPSHOT_${new Date().toISOString().slice(0,10)}.png`;
        downloadAnchor.href = targetCanvas.toDataURL('image/png');
        downloadAnchor.click();
 
        this.displayNotification('ดาวน์โหลดไฟล์ภาพถ่ายแผนที่บริหารสำเร็จ', 'success');
      } catch (ex) {
        this.displayNotification('เกิดข้อผิดพลาดในการประมวลผล Render Canvas', 'error');
      } finally {
        if (loader) loader.classList.remove('active');
      }
    }, 600);
  },
 
  renderDynamicLegendUI() {
    const container = document.getElementById('legendDynamicContainer');
    if (!container) return;
    
    const shapes = { MAIN: 'circle', SUB: 'rounded', TEMP: 'diamond', COMMUNITY: 'hexagon' };
 
    container.innerHTML = this.typesReferenceMap.map(t => {
      const cls = shapes[t.code] || 'circle';
      return `
        <div class="legend-item" onclick="CoreMapEngine.filterByLegendTypeCode(${t.id})">
          <div class="legend-shape ${cls}" style="background:${t.color}; border-color:rgba(255,255,255,0.7)"></div>
          <span style="font-weight:500; color:var(--text-primary)">${this.escapeToken(t.name)}</span>
        </div>`;
    }).join('');
  },
 
  filterByLegendTypeCode(typeId) {
    document.getElementById('typeSelect').value = typeId;
    this.applyActiveFilters();
  },
 
  toggleAnalyticalPanel() {
    const panel = document.getElementById('statsPanel');
    const btn = document.getElementById('statsExpandBtn');
    const isOpen = panel.classList.toggle('open');
    btn.classList.toggle('active', isOpen);
  },
 
  openMobileDrawer(id) {
    document.getElementById(id).classList.add('open');
    document.getElementById(`${id}Overlay`).classList.add('open');
    document.body.style.overflow = 'hidden';
  },
 
  closeMobileDrawer(id) {
    document.getElementById(id).classList.remove('open');
    document.getElementById(`${id}Overlay`).classList.remove('open');
    document.body.style.overflow = '';
  },
 
  toggleMobileLegend() {
    const leg = document.getElementById('legend');
    this.isMobileLegendActive = !this.isMobileLegendActive;
    leg.classList.toggle('mobile-open', this.isMobileLegendActive);
  },
 
  openImageLightbox(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightbox').classList.add('open');
  },
 
  closeImageLightbox() {
    document.getElementById('lightbox').classList.remove('open');
  },
 
  handleViewportReorientation() {
    this.clusterGroupLayer.getLayers().forEach(m => {
      if (m.checkpointPayload) {
        const cp = m.checkpointPayload;
        m.setIcon(this.generateCustomMarkerIcon(cp.type_color, cp.type_code, cp.name));
      }
    });
  },
 
  displayNotification(message, type = 'info') {
    const stack = document.getElementById('toastStack');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const glyphs = { success: 'fa-circle-check', error: 'fa-triangle-exclamation', info: 'fa-circle-info', warning: 'fa-bell' };
    const glyph = glyphs[type] || 'fa-circle-info';
    
    toast.innerHTML = `<i class="fa-solid ${glyph}" style="font-size:16px;"></i><span style="font-weight:500;">${message}</span>`;
    stack.appendChild(toast);
    
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  },
 
  escapeToken(str) {
    if (!str) return '';
    const tempNode = document.createElement('div');
    tempNode.textContent = str;
    return tempNode.innerHTML;
  },
 
  assignDOMText(id, text) {
    const target = document.getElementById(id);
    if (target) target.textContent = text;
  }
};
 
document.addEventListener('DOMContentLoaded', () => CoreMapEngine.initGeospatialSystem());
</script>
</body>
</html>
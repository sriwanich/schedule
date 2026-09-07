<?php
\App\Core\View::extends('app');
\App\Core\View::section('content');
$isEdit = !empty($disaster);
$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors'], $_SESSION['_old_input']);
?>

<div class="page-header">
  <div class="page-header-left">
    <h1><?= $isEdit ? 'แก้ไขข้อมูลสาธารณภัย' : 'บันทึกเหตุการณ์ใหม่' ?></h1>
    <p><?= $isEdit ? 'แก้ไข: ' . e($disaster['incident_number']) : 'กรอกข้อมูลเหตุการณ์สาธารณภัยให้ครบถ้วน' ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= url('disasters') ?>" class="btn btn-outline">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      ย้อนกลับ
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  <div>
    <strong>กรุณาตรวจสอบข้อมูล:</strong>
    <ul style="margin-top:4px;padding-left:16px">
      <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= $isEdit ? url('disasters/'.$disaster['id']) : url('disasters') ?>" id="disasterForm" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($isEdit): echo method_field('POST'); endif; ?>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:16px;">

    <!-- Left Column -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Section 1: Basic Info -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            ข้อมูลพื้นฐาน
          </div>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">ประเภทสาธารณภัย <span class="req">*</span></label>
              <select name="category_id" class="form-control <?= isset($errors['category_id']) ? 'border-red' : '' ?>">
                <option value="">-- เลือกประเภท --</option>
                <?php foreach ($disaster_categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= old('category_id', $disaster['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['category_id'])): ?>
              <div class="form-error"><?= e($errors['category_id']) ?></div>
              <?php endif; ?>
            </div>

            <div class="form-group">
              <label class="form-label">ระดับความรุนแรง <span class="req">*</span></label>
              <select name="severity" class="form-control">
                <option value="">-- เลือกระดับ --</option>
                <?php foreach ($severities as $key => $s): ?>
                <option value="<?= $key ?>" <?= old('severity', $disaster['severity'] ?? '') === $key ? 'selected' : '' ?>>
                  <?= $s['label'] ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">วันที่เกิดเหตุ <span class="req">*</span></label>
              <input type="date" name="incident_date" class="form-control"
                     value="<?= e(old('incident_date', $disaster['incident_date'] ?? date('Y-m-d'))) ?>"
                     max="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
              <label class="form-label">เวลาที่เกิดเหตุ</label>
              <input type="time" name="incident_time" class="form-control"
                     value="<?= e(old('incident_time', $disaster['incident_time'] ?? '')) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">ระดับการแจ้งเตือน</label>
              <select name="alert_level" class="form-control">
                <?php foreach ([1=>'ระดับ 1 - เฝ้าระวัง',2=>'ระดับ 2 - ระมัดระวัง',3=>'ระดับ 3 - อันตราย',4=>'ระดับ 4 - ฉุกเฉิน'] as $lvl => $lbl): ?>
                <option value="<?= $lvl ?>" <?= old('alert_level', $disaster['alert_level'] ?? 1) == $lvl ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">สถานะ</label>
              <select name="status" class="form-control">
                <?php foreach ($statuses as $key => $s): ?>
                <option value="<?= $key ?>" <?= old('status', $disaster['status'] ?? 'reported') === $key ? 'selected' : '' ?>>
                  <?= $s['label'] ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">รายละเอียดเหตุการณ์ <span class="req">*</span></label>
            <textarea name="description" class="form-control" rows="4" placeholder="อธิบายเหตุการณ์สาธารณภัยโดยละเอียด..."><?= e(old('description', $disaster['description'] ?? '')) ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">สาเหตุ</label>
            <textarea name="cause" class="form-control" rows="2" placeholder="สาเหตุของการเกิดสาธารณภัย..."><?= e(old('cause', $disaster['cause'] ?? '')) ?></textarea>
          </div>
        </div>
      </div>

      <!-- Section 2: Location -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            ที่ตั้งเหตุการณ์
          </div>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">อำเภอ <span class="req">*</span></label>
              <select name="amphure_id" class="form-control" id="amphureSelect" onchange="loadTambons(this.value)">
                <option value="">-- เลือกอำเภอ --</option>
                <?php foreach ($amphures as $a): ?>
                <option value="<?= $a['id'] ?>" <?= old('amphure_id', $disaster['amphure_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">ตำบล</label>
              <select name="tambon_id" class="form-control" id="tambonSelect">
                <option value="">-- เลือกตำบล --</option>
              </select>
            </div>

            <div class="form-group col-span-2">
              <label class="form-label">รายละเอียดสถานที่</label>
              <input type="text" name="location_detail" class="form-control"
                     placeholder="บ้านเลขที่, ถนน, หมู่บ้าน..."
                     value="<?= e(old('location_detail', $disaster['location_detail'] ?? '')) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">ละติจูด (Latitude)</label>
              <input type="number" name="latitude" class="form-control" step="0.0000001"
                     placeholder="12.8321..."
                     value="<?= e(old('latitude', $disaster['latitude'] ?? '')) ?>">
            </div>

            <div class="form-group">
              <label class="form-label">ลองจิจูด (Longitude)</label>
              <input type="number" name="longitude" class="form-control" step="0.0000001"
                     placeholder="101.4005..."
                     value="<?= e(old('longitude', $disaster['longitude'] ?? '')) ?>">
            </div>
          </div>

          <!-- Mini map picker -->
          <div id="mapPicker" style="height:200px;border-radius:8px;border:1px solid var(--border);overflow:hidden;margin-top:8px;"></div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:6px">คลิกบนแผนที่เพื่อระบุตำแหน่ง</div>
        </div>
      </div>

      <!-- Section 3: Impact -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            ผลกระทบต่อประชาชน
          </div>
        </div>
        <div class="card-body">
          <div class="form-grid-3">
            <?php $impactFields = [
              ['casualties_dead',     'ผู้เสียชีวิต (ราย)',          'var(--red)'],
              ['casualties_injured',  'ผู้บาดเจ็บ (ราย)',            'var(--amber)'],
              ['casualties_missing',  'ผู้สูญหาย (ราย)',             'var(--purple)'],
              ['affected_people',     'ผู้ได้รับผลกระทบ (ราย)',      'var(--blue)'],
              ['affected_households', 'ครัวเรือนที่ได้รับผลกระทบ',  'var(--blue)'],
              ['evacuated_people',    'ผู้อพยพ (ราย)',               'var(--cyan)'],
            ]; foreach ($impactFields as [$fname, $flabel, $fcol]): ?>
            <div class="form-group">
              <label class="form-label" style="color:<?= $fcol ?>"><?= $flabel ?></label>
              <input type="number" name="<?= $fname ?>" class="form-control" min="0"
                     value="<?= e(old($fname, $disaster[$fname] ?? 0)) ?>">
            </div>
            <?php endforeach; ?>
          </div>

          <div class="form-grid-3">
            <div class="form-group">
              <label class="form-label">บ้านเรือนเสียหาย (หลัง)</label>
              <input type="number" name="damage_buildings" class="form-control" min="0"
                     value="<?= e(old('damage_buildings', $disaster['damage_buildings'] ?? 0)) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">พื้นที่เกษตรเสียหาย (ไร่)</label>
              <input type="number" name="damage_agricultural_rai" class="form-control" min="0" step="0.01"
                     value="<?= e(old('damage_agricultural_rai', $disaster['damage_agricultural_rai'] ?? 0)) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">มูลค่าความเสียหาย (บาท)</label>
              <input type="number" name="economic_loss" class="form-control" min="0" step="0.01"
                     value="<?= e(old('economic_loss', $disaster['economic_loss'] ?? 0)) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Section 4: Actions -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            การดำเนินการ & บทเรียน
          </div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">การดำเนินการที่ได้ดำเนินการ</label>
            <textarea name="actions_taken" class="form-control" rows="3" placeholder="ระบุการดำเนินการช่วยเหลือ..."><?= e(old('actions_taken', $disaster['actions_taken'] ?? '')) ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">บทเรียนที่ได้รับ</label>
            <textarea name="lessons_learned" class="form-control" rows="2" placeholder="บทเรียนและข้อเสนอแนะ..."><?= e(old('lessons_learned', $disaster['lessons_learned'] ?? '')) ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">หมายเหตุ</label>
            <textarea name="notes" class="form-control" rows="2"><?= e(old('notes', $disaster['notes'] ?? '')) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Environmental Data -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 14.76V3.5a2.5 2.5 0 00-5 0v11.26a4.5 4.5 0 105 0z"/></svg>
            ข้อมูลสภาพอากาศ
          </div>
        </div>
        <div class="card-body">
          <?php $envFields = [
            ['water_level_cm',  'ระดับน้ำ (ซม.)',      '0.01'],
            ['rainfall_mm',     'ปริมาณน้ำฝน (มม.)',   '0.1'],
            ['wind_speed_kmh',  'ความเร็วลม (กม./ชม.)','0.1'],
            ['affected_area_km2','พื้นที่ได้รับผล (ตร.กม.)','0.0001'],
          ]; foreach ($envFields as [$fn, $fl, $fs]): ?>
          <div class="form-group">
            <label class="form-label"><?= $fl ?></label>
            <input type="number" name="<?= $fn ?>" class="form-control" min="0" step="<?= $fs ?>"
                   value="<?= e(old($fn, $disaster[$fn] ?? '')) ?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Response Info -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 010 7.07"/></svg>
            การตอบสนอง
          </div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">จำนวนเจ้าหน้าที่ที่ส่ง</label>
            <input type="number" name="responders_count" class="form-control" min="0"
                   value="<?= e(old('responders_count', $disaster['responders_count'] ?? 0)) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">แหล่งข้อมูล</label>
            <input type="text" name="data_source" class="form-control"
                   placeholder="แหล่งที่มาของข้อมูล..."
                   value="<?= e(old('data_source', $disaster['data_source'] ?? '')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" name="is_published" value="1" <?= old('is_published', $disaster['is_published'] ?? 1) ? 'checked' : '' ?> style="margin-right:6px">
              เผยแพร่ข้อมูลนี้
            </label>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="card">
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v14a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?= $isEdit ? 'บันทึกการแก้ไข' : 'บันทึกเหตุการณ์' ?>
          </button>
          <a href="<?= url('disasters') ?>" class="btn btn-outline" style="width:100%;justify-content:center">ยกเลิก</a>
        </div>
      </div>

    </div>
  </div>
</form>

<?php \App\Core\View::endSection(); ?>

<?php \App\Core\View::section('scripts'); ?>
<script>
// Load tambons on amphure change
async function loadTambons(amphureId, selectedId = null) {
  const sel = document.getElementById('tambonSelect');
  sel.innerHTML = '<option value="">-- กำลังโหลด --</option>';
  if (!amphureId) { sel.innerHTML = '<option value="">-- เลือกตำบล --</option>'; return; }

  try {
    const r = await fetch(url(`ajax/tambons/${amphureId}`));
    const d = await r.json();
    sel.innerHTML = '<option value="">-- เลือกตำบล --</option>';
    d.data.forEach(t => {
      const opt = new Option(t.name, t.id);
      if (t.id == selectedId) opt.selected = true;
      sel.add(opt);
    });
  } catch(e) { sel.innerHTML = '<option value="">-- เกิดข้อผิดพลาด --</option>'; }
}

// Init if editing
<?php if ($isEdit && !empty($disaster['amphure_id'])): ?>
loadTambons(<?= (int)$disaster['amphure_id'] ?>, <?= (int)($disaster['tambon_id'] ?? 0) ?>);
<?php endif; ?>

// Map picker
const mapLat = <?= old('latitude', $disaster['latitude'] ?? 12.8321) ?>;
const mapLng = <?= old('longitude', $disaster['longitude'] ?? 101.4005) ?>;

const map = L.map('mapPicker', { zoomControl: true }).setView([mapLat, mapLng], 11);
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
  attribution: '© OpenStreetMap © CARTO'
}).addTo(map);

const icon = L.divIcon({
  html: '<div style="width:14px;height:14px;background:#ef4444;border-radius:50%;border:2px solid #fff;box-shadow:0 0 8px rgba(239,68,68,.8)"></div>',
  className: '', iconAnchor: [7,7]
});

let marker = null;
if (mapLat && mapLng) {
  marker = L.marker([mapLat, mapLng], {icon}).addTo(map);
}

map.on('click', e => {
  const {lat, lng} = e.latlng;
  document.querySelector('[name=latitude]').value  = lat.toFixed(7);
  document.querySelector('[name=longitude]').value = lng.toFixed(7);
  if (marker) map.removeLayer(marker);
  marker = L.marker([lat, lng], {icon}).addTo(map);
});
</script>
<?php \App\Core\View::endSection(); ?>

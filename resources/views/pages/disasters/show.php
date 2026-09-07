<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div class="page-header-left">
    <div class="breadcrumb">
      <a href="<?= url('/') ?>">หน้าหลัก</a>
      <span class="breadcrumb-sep">›</span>
      <a href="<?= url('/disasters') ?>">เหตุการณ์สาธารณภัย</a>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current"><?= e($disaster['incident_number']) ?></span>
    </div>
    <h1><?= e($disaster['category_name']) ?></h1>
    <p><?= e($disaster['amphure_name']) ?> &middot; <?= format_date($disaster['incident_date']) ?></p>
  </div>
  <div class="page-header-right">
    <a href="<?= url('/disasters') ?>" class="btn btn-outline">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      กลับ
    </a>
    <?php if (can('disasters.edit')): ?>
    <a href="<?= url('/disasters/' . $disaster['id'] . '/edit') ?>" class="btn btn-primary">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      แก้ไข
    </a>
    <?php endif; ?>
    <a href="<?= url('/disasters/' . $disaster['id'] . '/export/pdf') ?>" class="btn btn-outline" target="_blank">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      PDF
    </a>
  </div>
</div>

<!-- Status Bar -->
<div class="card mb-5" style="border-left:4px solid var(--accent-color, var(--primary))">
  <div class="card-body" style="padding:16px 24px">
    <div class="flex items-center gap-5 flex-wrap">
      <div>
        <div class="info-label">รหัสเหตุการณ์</div>
        <div style="font-family:var(--font-mono);font-weight:700;font-size:16px;color:var(--primary)"><?= e($disaster['incident_number']) ?></div>
      </div>
      <div>
        <div class="info-label">สถานะ</div>
        <span class="badge status-<?= e($disaster['status']) ?>">
          <?php
          $statusLabels = ['reported'=>'รับแจ้ง','dispatched'=>'ส่งหน่วย','responding'=>'กำลังดำเนินการ','controlled'=>'ควบคุมได้','closed'=>'ปิดเหตุ','false_alarm'=>'แจ้งเท็จ'];
          echo $statusLabels[$disaster['status']] ?? $disaster['status'];
          ?>
        </span>
      </div>
      <div>
        <div class="info-label">ระดับความรุนแรง</div>
        <span class="badge severity-<?= e($disaster['severity']) ?>">
          <?php
          $sevLabels = ['minor'=>'เล็กน้อย','moderate'=>'ปานกลาง','major'=>'รุนแรง','catastrophic'=>'วิกฤต'];
          echo $sevLabels[$disaster['severity']] ?? $disaster['severity'];
          ?>
        </span>
      </div>
      <div>
        <div class="info-label">ประเภทสาธารณภัย</div>
        <div class="font-semibold text-sm"><?= e($disaster['category_name']) ?></div>
      </div>
      <div style="margin-left:auto">
        <div class="info-label">แหล่งข้อมูล</div>
        <div class="text-sm text-secondary"><?= e($disaster['data_source'] ?? 'ระบบ RDSIS') ?></div>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

  <!-- Left Column -->
  <div>

    <!-- Basic Info -->
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          ข้อมูลเหตุการณ์
        </h3>
      </div>
      <div class="card-body">
        <div class="info-grid">
          <div class="info-item">
            <div class="info-label">วันที่เกิดเหตุ</div>
            <div class="info-value"><?= thai_date($disaster['incident_date']) ?> <?= $disaster['incident_time'] ? '| ' . $disaster['incident_time'] : '' ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">พื้นที่เกิดเหตุ</div>
            <div class="info-value"><?= e($disaster['amphure_name']) ?><?= $disaster['tambon_name'] ? ' › ' . $disaster['tambon_name'] : '' ?></div>
          </div>
          <?php if ($disaster['location_detail']): ?>
          <div class="info-item" style="grid-column:1/-1">
            <div class="info-label">รายละเอียดสถานที่</div>
            <div class="info-value"><?= e($disaster['location_detail']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($disaster['latitude'] && $disaster['longitude']): ?>
          <div class="info-item">
            <div class="info-label">พิกัด GPS</div>
            <div class="info-value" style="font-family:var(--font-mono);font-size:12px">
              <?= number_format((float)$disaster['latitude'], 6) ?>, <?= number_format((float)$disaster['longitude'], 6) ?>
            </div>
          </div>
          <?php endif; ?>
          <div class="info-item">
            <div class="info-label">วันที่รับแจ้ง</div>
            <div class="info-value"><?= thai_date($disaster['reported_at']) ?></div>
          </div>
          <?php if ($disaster['controlled_at']): ?>
          <div class="info-item">
            <div class="info-label">ควบคุมเหตุได้เมื่อ</div>
            <div class="info-value"><?= thai_date($disaster['controlled_at']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($disaster['closed_at']): ?>
          <div class="info-item">
            <div class="info-label">ปิดเหตุเมื่อ</div>
            <div class="info-value"><?= thai_date($disaster['closed_at']) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($disaster['description']): ?>
        <div class="divider"></div>
        <div>
          <div class="info-label" style="margin-bottom:8px">รายละเอียดเหตุการณ์</div>
          <p style="font-size:14px;line-height:1.8;color:var(--text-primary)"><?= nl2br(e($disaster['description'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($disaster['cause']): ?>
        <div class="divider"></div>
        <div>
          <div class="info-label" style="margin-bottom:8px">สาเหตุ</div>
          <p style="font-size:14px;line-height:1.8"><?= nl2br(e($disaster['cause'])) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Impact Statistics -->
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          ผลกระทบ
        </h3>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
          <?php
          $impacts = [
            ['ผู้เสียชีวิต', $disaster['casualties_dead'] ?? 0, '#ef4444', 'rgba(239,68,68,.1)'],
            ['ผู้บาดเจ็บ', $disaster['casualties_injured'] ?? 0, '#f59e0b', 'rgba(245,158,11,.1)'],
            ['ผู้สูญหาย', $disaster['casualties_missing'] ?? 0, '#8b5cf6', 'rgba(139,92,246,.1)'],
            ['ผู้ได้รับผลกระทบ', $disaster['affected_people'] ?? 0, '#3b82f6', 'rgba(59,130,246,.1)'],
            ['ครัวเรือนเสียหาย', $disaster['affected_households'] ?? 0, '#0891b2', 'rgba(8,145,178,.1)'],
            ['ผู้อพยพ', $disaster['evacuated_people'] ?? 0, '#16a34a', 'rgba(22,163,74,.1)'],
          ];
          foreach ($impacts as [$label, $value, $color, $bg]):
          ?>
          <div style="background:<?= $bg ?>;border-radius:10px;padding:14px;text-align:center">
            <div style="font-size:22px;font-weight:800;color:<?= $color ?>"><?= number_format((int)$value) ?></div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:3px"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Economic Damage -->
        <div class="divider"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
          <div>
            <div class="info-label">อาคารเสียหาย</div>
            <div class="info-value large"><?= number_format((int)($disaster['damage_buildings'] ?? 0)) ?> <small style="font-size:13px;font-weight:400">หลัง</small></div>
          </div>
          <div>
            <div class="info-label">พื้นที่การเกษตรเสียหาย</div>
            <div class="info-value large"><?= number_format((float)($disaster['damage_agricultural_rai'] ?? 0), 2) ?> <small style="font-size:13px;font-weight:400">ไร่</small></div>
          </div>
          <div>
            <div class="info-label">มูลค่าความเสียหาย</div>
            <div class="info-value large" style="color:var(--danger)">
              ฿<?= number_format((float)($disaster['economic_loss'] ?? 0) / 1000000, 2) ?> <small style="font-size:13px;font-weight:400">ล้านบาท</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions Taken -->
    <?php if ($disaster['actions_taken']): ?>
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">การดำเนินการ</h3>
      </div>
      <div class="card-body">
        <p style="font-size:14px;line-height:1.8"><?= nl2br(e($disaster['actions_taken'])) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Environmental Data -->
    <?php if ($disaster['water_level_cm'] || $disaster['rainfall_mm'] || $disaster['wind_speed_kmh']): ?>
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">ข้อมูลสภาพอากาศ ณ เวลาเกิดเหตุ</h3>
      </div>
      <div class="card-body">
        <div class="info-grid">
          <?php if ($disaster['water_level_cm']): ?>
          <div class="info-item">
            <div class="info-label">ระดับน้ำ</div>
            <div class="info-value"><?= number_format((float)$disaster['water_level_cm'], 1) ?> ซม.</div>
          </div>
          <?php endif; ?>
          <?php if ($disaster['rainfall_mm']): ?>
          <div class="info-item">
            <div class="info-label">ปริมาณฝน</div>
            <div class="info-value"><?= number_format((float)$disaster['rainfall_mm'], 1) ?> มม.</div>
          </div>
          <?php endif; ?>
          <?php if ($disaster['wind_speed_kmh']): ?>
          <div class="info-item">
            <div class="info-label">ความเร็วลม</div>
            <div class="info-value"><?= number_format((float)$disaster['wind_speed_kmh'], 1) ?> กม./ชม.</div>
          </div>
          <?php endif; ?>
          <?php if ($disaster['affected_area_km2']): ?>
          <div class="info-item">
            <div class="info-label">พื้นที่ได้รับผลกระทบ</div>
            <div class="info-value"><?= number_format((float)$disaster['affected_area_km2'], 2) ?> ตร.กม.</div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Images -->
    <?php $images = $disaster['images'] ? (is_string($disaster['images']) ? json_decode($disaster['images'], true) : $disaster['images']) : []; ?>
    <?php if (!empty($images)): ?>
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">รูปภาพ</h3>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">
          <?php foreach ($images as $img): ?>
          <a href="<?= url('/uploads/' . e($img)) ?>" target="_blank">
            <img src="<?= url('/uploads/' . e($img)) ?>" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Right Column -->
  <div>

    <!-- Mini Map -->
    <?php if ($disaster['latitude'] && $disaster['longitude']): ?>
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">ตำแหน่งเกิดเหตุ</h3>
      </div>
      <div class="card-body p-0">
        <div id="detail-map" style="height:240px;border-radius:0 0 var(--radius-lg) var(--radius-lg)"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Response Summary -->
    <?php if (!empty($responses)): ?>
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">หน่วยปฏิบัติการ</h3>
      </div>
      <div class="card-body">
        <?php foreach ($responses as $r): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
          <div style="width:34px;height:34px;background:var(--primary-light);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="15" height="15" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600"><?= e($r['team_name'] ?? 'ไม่ระบุหน่วย') ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= thai_date($r['dispatched_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Aids Provided -->
    <?php if (!empty($aids)): ?>
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">ความช่วยเหลือที่มอบให้</h3>
      </div>
      <div class="card-body p-0">
        <table class="table">
          <thead><tr><th>รายการ</th><th class="text-right">จำนวน</th><th class="text-right">มูลค่า</th></tr></thead>
          <tbody>
          <?php foreach ($aids as $aid): ?>
          <tr>
            <td><?= e($aid['aid_name']) ?></td>
            <td class="text-right"><?= number_format((float)$aid['quantity'], 2) ?> <?= e($aid['unit'] ?? '') ?></td>
            <td class="text-right">฿<?= number_format((float)$aid['total_cost'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Meta Info -->
    <div class="card mb-5">
      <div class="card-header">
        <h3 class="card-title">ข้อมูลระบบ</h3>
      </div>
      <div class="card-body">
        <div class="info-item mb-4">
          <div class="info-label">บันทึกโดย</div>
          <div class="info-value text-sm"><?= e($disaster['reporter_name'] ?? '-') ?></div>
        </div>
        <div class="info-item mb-4">
          <div class="info-label">วันที่บันทึก</div>
          <div class="info-value text-sm"><?= thai_date($disaster['created_at']) ?></div>
        </div>
        <?php if ($disaster['verified_at']): ?>
        <div class="info-item mb-4">
          <div class="info-label">ตรวจสอบโดย</div>
          <div class="info-value text-sm"><?= e($disaster['verifier_name'] ?? '-') ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">วันที่ตรวจสอบ</div>
          <div class="info-value text-sm"><?= thai_date($disaster['verified_at']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Actions -->
    <?php if (can('disasters.delete')): ?>
    <div class="card">
      <div class="card-body">
        <button class="btn btn-danger w-full"
                data-confirm="คุณต้องการลบเหตุการณ์นี้ใช่หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้"
                data-action="<?= url('/disasters/' . $disaster['id']) ?>"
                data-method="DELETE">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          ลบเหตุการณ์นี้
        </button>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php \App\Core\View::endSection(); ?>

<?php \App\Core\View::section('scripts'); ?>
<script>
// Detail Map
<?php if ($disaster['latitude'] && $disaster['longitude']): ?>
document.addEventListener('DOMContentLoaded', function() {
  const map = L.map('detail-map', {
    center: [<?= (float)$disaster['latitude'] ?>, <?= (float)$disaster['longitude'] ?>],
    zoom: 14,
    zoomControl: true,
    scrollWheelZoom: false,
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
  }).addTo(map);

  const icon = L.divIcon({
    className: '',
    html: '<div style="width:28px;height:28px;background:#ef4444;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.4)"></div>',
    iconSize: [28, 28], iconAnchor: [14, 14],
  });

  L.marker([<?= (float)$disaster['latitude'] ?>, <?= (float)$disaster['longitude'] ?>], { icon })
    .bindPopup('<?= e($disaster['incident_number']) ?> - <?= e($disaster['amphure_name']) ?>')
    .addTo(map)
    .openPopup();
});
<?php endif; ?>
</script>
<?php \App\Core\View::endSection(); ?>

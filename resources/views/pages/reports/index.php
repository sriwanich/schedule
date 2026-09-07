<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div class="page-header-left">
    <h1>รายงาน</h1>
    <p>สร้างและดาวน์โหลดรายงานสถิติสาธารณภัย</p>
  </div>
  <div class="page-header-right">
    <button class="btn btn-primary" data-modal="modal-new-report">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      สร้างรายงานใหม่
    </button>
  </div>
</div>

<!-- Quick Report Templates -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:32px">
  <?php
  $templates = [
    ['สรุปรายปี',         'annual',   '#1d4ed8', 'M4 6h16M4 12h16M4 18h7', 'สรุปสถิติสาธารณภัยรายปี พร้อมกราฟ'],
    ['สรุปรายเดือน',      'monthly',  '#7c3aed', 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', 'สถิติแยกตามเดือน'],
    ['วิเคราะห์พื้นที่',  'analytics','#0891b2', 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7', 'วิเคราะห์ตามพื้นที่อำเภอ'],
    ['รายงานความเสียหาย', 'damage',   '#dc2626', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'สรุปความเสียหายทางเศรษฐกิจ'],
    ['รายงานผู้ประสบภัย', 'victims',  '#f59e0b', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2', 'สรุปจำนวนผู้ได้รับผลกระทบ'],
    ['รายงานที่กำหนดเอง', 'custom',   '#16a34a', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'เลือกช่วงเวลาและตัวกรองเอง'],
  ];
  foreach ($templates as [$title, $type, $color, $iconPath, $desc]):
  ?>
  <div class="card" style="cursor:pointer;transition:all .2s" onclick="openReportModal('<?= $type ?>', '<?= $title ?>')" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
    <div class="card-body" style="text-align:center;padding:24px 16px">
      <div style="width:44px;height:44px;background:<?= $color ?>1a;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
        <svg width="20" height="20" fill="none" stroke="<?= $color ?>" stroke-width="2" viewBox="0 0 24 24"><path d="<?= $iconPath ?>"/></svg>
      </div>
      <div style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:4px"><?= $title ?></div>
      <div style="font-size:12px;color:var(--text-muted)"><?= $desc ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Recent Reports -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">รายงานที่สร้างแล้ว</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>ชื่อรายงาน</th>
            <th>ประเภท</th>
            <th>รูปแบบ</th>
            <th>สถานะ</th>
            <th>สร้างโดย</th>
            <th>วันที่</th>
            <th>ขนาด</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($paginated['data'])): ?>
          <tr>
            <td colspan="8">
              <div class="empty-state">
                <svg class="empty-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
                <div class="empty-title">ยังไม่มีรายงาน</div>
                <div class="empty-text">คลิก "สร้างรายงานใหม่" เพื่อสร้างรายงานแรกของคุณ</div>
              </div>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($paginated['data'] as $report): ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:13px"><?= e($report['title']) ?></div>
            </td>
            <td>
              <?php
              $typeLabels = ['summary'=>'สรุป','detail'=>'ละเอียด','analytics'=>'วิเคราะห์','annual'=>'รายปี','monthly'=>'รายเดือน','custom'=>'กำหนดเอง'];
              echo '<span class="badge badge-info">' . ($typeLabels[$report['type']] ?? $report['type']) . '</span>';
              ?>
            </td>
            <td>
              <span class="badge badge-gray"><?= strtoupper(e($report['format'])) ?></span>
            </td>
            <td>
              <?php
              $statusClass = ['pending'=>'badge-warning','processing'=>'badge-info','completed'=>'badge-success','failed'=>'badge-danger'];
              $statusLabel = ['pending'=>'รอ','processing'=>'กำลังสร้าง','completed'=>'พร้อม','failed'=>'ล้มเหลว'];
              $sc = $statusClass[$report['status']] ?? 'badge-gray';
              $sl = $statusLabel[$report['status']] ?? $report['status'];
              ?>
              <span class="badge <?= $sc ?>"><?= $sl ?></span>
            </td>
            <td class="text-sm text-secondary"><?= e(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')) ?></td>
            <td class="text-sm text-muted"><?= format_date($report['created_at']) ?></td>
            <td class="text-sm text-muted">
              <?php
              $size = (int)($report['file_size'] ?? 0);
              echo $size > 0 ? ($size > 1048576 ? round($size / 1048576, 1) . ' MB' : round($size / 1024, 0) . ' KB') : '-';
              ?>
            </td>
            <td>
              <div class="flex gap-2">
                <?php if ($report['status'] === 'completed' && $report['file_path']): ?>
                <a href="<?= url('/reports/' . $report['uuid'] . '/download') ?>" class="btn btn-sm btn-primary" title="ดาวน์โหลด">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  ดาวน์โหลด
                </a>
                <?php endif; ?>
                <?php if ($report['status'] === 'failed'): ?>
                <span class="text-xs text-danger" title="<?= e($report['error_message'] ?? '') ?>">ล้มเหลว</span>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline"
                        data-confirm="ต้องการลบรายงานนี้?"
                        data-action="<?= url('/reports/' . $report['id']) ?>"
                        data-method="DELETE"
                        title="ลบ">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($paginated['data']) && $paginated['last_page'] > 1): ?>
    <div style="padding:16px 24px;border-top:1px solid var(--border)">
      <?= paginate_links($paginated) ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- New Report Modal -->
<div id="modal-new-report" class="modal-backdrop">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-report-title">สร้างรายงาน</h3>
      <button class="modal-close">✕</button>
    </div>
    <form id="report-form" method="POST" action="<?= url('/reports/generate') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="type" id="report-type" value="summary">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label required">ชื่อรายงาน</label>
          <input type="text" name="title" id="report-title-input" class="form-control" placeholder="ระบุชื่อรายงาน" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">ปีเริ่มต้น (พ.ศ.)</label>
            <input type="number" name="year_from" class="form-control" placeholder="เช่น 2567"
                   value="<?= buddhist_year((int)date('Y')) - 1 ?>" min="2540" max="<?= buddhist_year() ?>">
          </div>
          <div class="form-group">
            <label class="form-label">ปีสิ้นสุด (พ.ศ.)</label>
            <input type="number" name="year_to" class="form-control" placeholder="เช่น 2568"
                   value="<?= buddhist_year() ?>" min="2540" max="<?= buddhist_year() ?>">
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">อำเภอ (ถ้ามี)</label>
            <select name="amphure_id" class="form-control">
              <option value="">-- ทุกอำเภอ --</option>
              <?php foreach ($amphures ?? [] as $a): ?>
              <option value="<?= $a['id'] ?>"><?= e($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">ประเภทสาธารณภัย (ถ้ามี)</label>
            <select name="category_id" class="form-control">
              <option value="">-- ทุกประเภท --</option>
              <?php foreach ($categories ?? [] as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">รูปแบบไฟล์</label>
          <div style="display:flex;gap:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
              <input type="radio" name="format" value="pdf" checked> PDF
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
              <input type="radio" name="format" value="xlsx"> Excel (.xlsx)
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
              <input type="radio" name="format" value="csv"> CSV
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="RDSIS.Modal.close('modal-new-report')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary" id="btn-generate-report">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          สร้างรายงาน
        </button>
      </div>
    </form>
  </div>
</div>

<?php \App\Core\View::endSection(); ?>

<?php \App\Core\View::section('scripts'); ?>
<script>
function openReportModal(type, title) {
  document.getElementById('report-type').value        = type;
  document.getElementById('modal-report-title').textContent = 'สร้าง' + title;
  document.getElementById('report-title-input').value = title + ' ปี พ.ศ. <?= buddhist_year() ?>';
  RDSIS.Modal.open('modal-new-report');
}

document.getElementById('report-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-generate-report');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> กำลังสร้าง...';
  try {
    const fd   = new FormData(this);
    const resp = await RDSIS.post('/reports/generate', fd, true);
    RDSIS.Modal.close('modal-new-report');
    RDSIS.Toast.success('กำลังสร้างรายงาน', 'รายงานจะพร้อมในไม่กี่วินาที');
    setTimeout(() => location.reload(), 2500);
  } catch (err) {
    RDSIS.Toast.error('เกิดข้อผิดพลาด', err.message || 'ไม่สามารถสร้างรายงานได้');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'สร้างรายงาน';
  }
});
</script>
<?php \App\Core\View::endSection(); ?>

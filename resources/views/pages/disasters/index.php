<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div class="page-header-left">
    <h1>รายการสาธารณภัย</h1>
    <p>ทั้งหมด <?= format_number($paginated['total']) ?> เหตุการณ์</p>
  </div>
  <div class="page-header-right">
    <a href="<?= url('disasters/create') ?>" class="btn btn-primary">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      บันทึกใหม่
    </a>
    <a href="<?= url('disasters/export/excel?' . http_build_query($filters)) ?>" class="btn btn-outline">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
      Excel
    </a>
    <a href="<?= url('disasters/export/pdf?' . http_build_query($filters)) ?>" class="btn btn-outline">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      PDF
    </a>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <form method="GET" id="filterForm">
    <div class="card-body">
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr;gap:12px;align-items:end;">
        <div>
          <label class="form-label">ค้นหา</label>
          <div style="position:relative">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                 style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted)">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" name="search" class="form-control" style="padding-left:32px"
                   placeholder="เลขที่, รายละเอียด, สถานที่..."
                   value="<?= e($filters['search'] ?? '') ?>">
          </div>
        </div>
        <div>
          <label class="form-label">ประเภท</label>
          <select name="category_id" class="form-control">
            <option value="">-- ทุกประเภท --</option>
            <?php foreach ($disaster_categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">อำเภอ</label>
          <select name="amphure_id" class="form-control">
            <option value="">-- ทุกอำเภอ --</option>
            <?php foreach ($amphures as $a): ?>
            <option value="<?= $a['id'] ?>" <?= ($filters['amphure_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">สถานะ</label>
          <select name="status" class="form-control">
            <option value="">-- ทุกสถานะ --</option>
            <?php foreach ($statuses as $key => $s): ?>
            <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">ปี</label>
          <select name="year" class="form-control">
            <option value="">-- ทุกปี --</option>
            <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= ($filters['year'] ?? '') == $y ? 'selected' : '' ?>>พ.ศ. <?= $y + 543 ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary" style="flex:1">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            ค้นหา
          </button>
          <a href="<?= url('disasters') ?>" class="btn btn-outline" title="ล้างตัวกรอง">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </a>
        </div>
      </div>
    </div>
  </form>
</div>

<!-- Table -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>เลขที่เหตุการณ์</th>
          <th>วันที่</th>
          <th>ประเภท</th>
          <th>อำเภอ</th>
          <th>ความรุนแรง</th>
          <th>ผู้เสียชีวิต</th>
          <th>ผู้ได้รับผลกระทบ</th>
          <th>ความเสียหาย</th>
          <th>สถานะ</th>
          <th style="text-align:center">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($paginated['data'])): ?>
        <tr>
          <td colspan="10" style="text-align:center;padding:40px;color:var(--text-muted)">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;opacity:.3"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            ไม่พบข้อมูลสาธารณภัย
          </td>
        </tr>
        <?php else: foreach ($paginated['data'] as $d): ?>
        <tr>
          <td>
            <a href="<?= url('disasters/'.$d['id']) ?>" style="color:var(--blue);text-decoration:none;font-family:var(--font-mono);font-size:12px;font-weight:600">
              <?= e($d['incident_number']) ?>
            </a>
          </td>
          <td style="font-size:12.5px;color:var(--text-secondary)"><?= format_date($d['incident_date']) ?></td>
          <td>
            <span style="display:flex;align-items:center;gap:6px;">
              <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?= e($d['category_color'] ?? '#6b7280') ?>"></span>
              <span style="font-size:13px"><?= e($d['category_name']) ?></span>
            </span>
          </td>
          <td style="font-size:13px"><?= e($d['amphure_name']) ?></td>
          <td>
            <?php $sev = $severities[$d['severity']] ?? []; ?>
            <span class="badge" style="background:<?= $sev['color'] ?? '#6b7280' ?>22;color:<?= $sev['color'] ?? '#9ca3af' ?>;border-color:<?= $sev['color'] ?? '#6b7280' ?>44">
              <?= $sev['label'] ?? $d['severity'] ?>
            </span>
          </td>
          <td class="text-center <?= $d['casualties_dead'] > 0 ? 'text-red fw-bold' : 'text-muted' ?>">
            <?= $d['casualties_dead'] > 0 ? $d['casualties_dead'] : '-' ?>
          </td>
          <td class="fw-mono" style="font-size:12.5px"><?= $d['affected_people'] > 0 ? format_number((int)$d['affected_people']) : '-' ?></td>
          <td style="font-size:12px;color:<?= $d['economic_loss'] > 0 ? 'var(--amber)' : 'var(--text-muted)' ?>">
            <?= $d['economic_loss'] > 0 ? '฿' . number_format((float)$d['economic_loss']/1000000, 2) . ' ล.' : '-' ?>
          </td>
          <td>
            <?php $st = $statuses[$d['status']] ?? []; ?>
            <span class="badge" style="background:<?= $st['color'] ?? '#6b7280' ?>22;color:<?= $st['color'] ?? '#9ca3af' ?>;border-color:<?= $st['color'] ?? '#6b7280' ?>44">
              <?= $st['label'] ?? $d['status'] ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px;justify-content:center">
              <a href="<?= url('disasters/'.$d['id']) ?>" class="btn-icon" style="width:28px;height:28px" title="ดูรายละเอียด">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
              <a href="<?= url('disasters/'.$d['id'].'/edit') ?>" class="btn-icon" style="width:28px;height:28px" title="แก้ไข">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </a>
              <form method="POST" action="<?= url('disasters/'.$d['id'].'/delete') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn-icon" style="width:28px;height:28px;color:var(--red)"
                        onclick="return confirm('ยืนยันการลบเหตุการณ์ <?= e($d['incident_number']) ?>?')" title="ลบ">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paginated['last_page'] > 1): ?>
  <div style="padding:0 16px 16px">
    <?= paginate_links($paginated) ?>
  </div>
  <?php endif; ?>
</div>

<?php \App\Core\View::endSection(); ?>

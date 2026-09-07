<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div class="page-header-left">
    <h1>จัดการผู้ใช้งาน</h1>
    <p>รายการผู้ใช้งานในระบบ RDSIS</p>
  </div>
  <div class="page-header-right">
    <?php if (can('users.create')): ?>
    <button class="btn btn-primary" data-modal="modal-user-form">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      เพิ่มผู้ใช้งาน
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <?php
  $roleColors = ['admin'=>'#ef4444','officer'=>'#1d4ed8','manager'=>'#7c3aed','viewer'=>'#16a34a'];
  $roleIcons  = ['admin'=>'🔐','officer'=>'👮','manager'=>'📊','viewer'=>'👁'];
  foreach ($roleStats ?? [] as $rs):
    $color = $roleColors[$rs['slug']] ?? '#64748b';
  ?>
  <div class="stat-card" style="--accent:<?= $color ?>">
    <div class="stat-icon" style="background:<?= $color ?>1a;font-size:20px"><?= $roleIcons[$rs['slug']] ?? '👤' ?></div>
    <div class="stat-label"><?= e($rs['name']) ?></div>
    <div class="stat-value"><?= number_format((int)$rs['count']) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- User Table -->
<div class="card">
  <!-- Filter -->
  <div class="filter-bar">
    <form method="GET" style="display:contents">
      <div class="filter-group">
        <label class="filter-label">บทบาท</label>
        <select name="role_id" class="form-control" onchange="this.form.submit()">
          <option value="">ทั้งหมด</option>
          <?php foreach ($roles ?? [] as $r): ?>
          <option value="<?= $r['id'] ?>" <?= ($filters['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">สถานะ</label>
        <select name="is_active" class="form-control" onchange="this.form.submit()">
          <option value="">ทั้งหมด</option>
          <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>ใช้งาน</option>
          <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>ระงับ</option>
        </select>
      </div>
      <div class="filter-group flex-1">
        <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อ, อีเมล, ชื่อผู้ใช้..."
               value="<?= e($filters['search'] ?? '') ?>" style="max-width:280px">
      </div>
      <button type="submit" class="btn btn-outline btn-sm">ค้นหา</button>
      <a href="<?= url('/users') ?>" class="btn btn-ghost btn-sm">ล้าง</a>
    </form>
  </div>

  <div class="card-body p-0">
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>ผู้ใช้งาน</th>
            <th>บทบาท</th>
            <th>หน่วยงาน / ตำแหน่ง</th>
            <th>เข้าใช้ล่าสุด</th>
            <th>สถานะ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($paginated['data'])): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:40px">ไม่พบผู้ใช้งาน</td></tr>
          <?php else: ?>
          <?php foreach ($paginated['data'] as $user): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0">
                  <?= mb_substr($user['first_name'] ?? 'U', 0, 1) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:13px"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                  <div style="font-size:11px;color:var(--text-muted)">@<?= e($user['username']) ?> &middot; <?= e($user['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <?php
              $rClass = ['admin'=>'badge-danger','officer'=>'badge-info','manager'=>'badge-purple','viewer'=>'badge-success'];
              echo '<span class="badge ' . ($rClass[$user['role_slug'] ?? ''] ?? 'badge-gray') . '">' . e($user['role_name'] ?? '-') . '</span>';
              ?>
            </td>
            <td>
              <div style="font-size:13px"><?= e($user['department'] ?? '-') ?></div>
              <div style="font-size:11px;color:var(--text-muted)"><?= e($user['position'] ?? '') ?></div>
            </td>
            <td class="text-sm text-muted">
              <?= $user['last_login_at'] ? format_date($user['last_login_at'], 'd/m/Y H:i') : 'ยังไม่เคยเข้าใช้' ?>
            </td>
            <td>
              <span class="badge <?= $user['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $user['is_active'] ? 'ใช้งาน' : 'ระงับ' ?>
              </span>
            </td>
            <td>
              <div class="flex gap-2">
                <?php if (can('users.edit')): ?>
                <button class="btn btn-sm btn-outline" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">แก้ไข</button>
                <?php endif; ?>
                <?php if (can('users.delete') && $user['id'] != ($_SESSION['auth_user']['id'] ?? 0)): ?>
                <button class="btn btn-sm btn-outline"
                        style="color:var(--danger);border-color:var(--danger)"
                        data-confirm="ต้องการลบผู้ใช้ <?= e($user['first_name']) ?> ?"
                        data-action="<?= url('/users/' . $user['id']) ?>"
                        data-method="DELETE">ลบ</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($paginated['last_page'] > 1): ?>
    <div style="padding:16px 24px;border-top:1px solid var(--border)">
      <?= paginate_links($paginated) ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- User Form Modal -->
<div id="modal-user-form" class="modal-backdrop">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <h3 class="modal-title" id="user-modal-title">เพิ่มผู้ใช้งาน</h3>
      <button class="modal-close">✕</button>
    </div>
    <form id="user-form" method="POST" action="<?= url('/users') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="_user_id" id="edit-user-id" value="">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label required">ชื่อ</label>
            <input type="text" name="first_name" id="uf-first" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label required">นามสกุล</label>
            <input type="text" name="last_name" id="uf-last" class="form-control" required>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label required">ชื่อผู้ใช้</label>
            <input type="text" name="username" id="uf-username" class="form-control" required autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label required">อีเมล</label>
            <input type="email" name="email" id="uf-email" class="form-control" required>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" id="pwd-label">รหัสผ่าน <span style="color:var(--danger)" id="pwd-req">*</span></label>
            <input type="password" name="password" id="uf-password" class="form-control" autocomplete="new-password">
            <div class="form-text" id="pwd-hint"></div>
          </div>
          <div class="form-group">
            <label class="form-label required">บทบาท</label>
            <select name="role_id" id="uf-role" class="form-control" required>
              <?php foreach ($roles ?? [] as $r): ?>
              <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">หน่วยงาน</label>
          <input type="text" name="department" id="uf-dept" class="form-control" placeholder="เช่น ฝ่ายป้องกันและปราบปราม">
        </div>
        <div class="form-group">
          <label class="form-label">ตำแหน่ง</label>
          <input type="text" name="position" id="uf-pos" class="form-control" placeholder="เช่น นักวิเคราะห์นโยบาย">
        </div>
        <div class="form-group">
          <label class="form-label">โทรศัพท์</label>
          <input type="text" name="phone" id="uf-phone" class="form-control">
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
            <input type="checkbox" name="is_active" value="1" id="uf-active" checked>
            เปิดใช้งานบัญชีนี้
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="RDSIS.Modal.close('modal-user-form')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary" id="btn-save-user">บันทึก</button>
      </div>
    </form>
  </div>
</div>

<?php \App\Core\View::endSection(); ?>

<?php \App\Core\View::section('scripts'); ?>
<script>
function editUser(user) {
  document.getElementById('user-modal-title').textContent = 'แก้ไขผู้ใช้งาน';
  document.getElementById('edit-user-id').value   = user.id;
  document.getElementById('uf-first').value       = user.first_name || '';
  document.getElementById('uf-last').value        = user.last_name  || '';
  document.getElementById('uf-username').value    = user.username   || '';
  document.getElementById('uf-email').value       = user.email      || '';
  document.getElementById('uf-role').value        = user.role_id    || '';
  document.getElementById('uf-dept').value        = user.department || '';
  document.getElementById('uf-pos').value         = user.position   || '';
  document.getElementById('uf-phone').value       = user.phone      || '';
  document.getElementById('uf-active').checked    = !!user.is_active;
  document.getElementById('pwd-req').style.display = 'none';
  document.getElementById('pwd-hint').textContent  = 'เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน';
  document.getElementById('user-form').action = '<?= url('/users') ?>/' + user.id;
  RDSIS.Modal.open('modal-user-form');
}

document.getElementById('user-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-user');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';
  RDSIS.Forms.clearErrors(this);
  try {
    const fd = new FormData(this);
    const data = await RDSIS.post(this.action.replace(location.origin, ''), fd, true);
    RDSIS.Modal.close('modal-user-form');
    RDSIS.Toast.success('บันทึกสำเร็จ');
    setTimeout(() => location.reload(), 1000);
  } catch (err) {
    if (err.errors) RDSIS.Forms.showErrors(this, err.errors);
    RDSIS.Toast.error('เกิดข้อผิดพลาด', err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'บันทึก';
  }
});
</script>
<?php \App\Core\View::endSection(); ?>

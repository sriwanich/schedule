<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div class="page-header-left">
    <h1>โปรไฟล์ของฉัน</h1>
    <p>จัดการข้อมูลส่วนตัวและการตั้งค่าบัญชี</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start">

  <!-- Profile Card -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-body" style="text-align:center;padding:32px 24px">
        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;margin:0 auto 16px">
          <?= mb_substr($user['first_name'] ?? 'U', 0, 1) ?>
        </div>
        <div style="font-size:18px;font-weight:700;color:var(--text-primary)"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">@<?= e($user['username']) ?></div>
        <span class="badge badge-info" style="margin-top:10px"><?= e($user['role_name'] ?? '') ?></span>
      </div>
      <div class="card-footer" style="text-align:center">
        <div class="info-item">
          <div class="info-label">เข้าใช้ล่าสุด</div>
          <div class="info-value text-sm"><?= $user['last_login_at'] ? thai_date($user['last_login_at'], 'j M Y H:i') : '-' ?></div>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="card">
      <div class="card-header" style="padding:14px 16px">
        <h3 class="card-title" style="font-size:13px">กิจกรรมล่าสุด</h3>
      </div>
      <div class="card-body" style="padding:0;max-height:300px;overflow-y:auto">
        <?php if (empty($logs)): ?>
        <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">ยังไม่มีกิจกรรม</div>
        <?php else: ?>
        <?php foreach (array_slice($logs, 0, 10) as $log): ?>
        <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px">
          <div style="width:6px;height:6px;border-radius:50%;background:var(--primary);margin-top:6px;flex-shrink:0"></div>
          <div>
            <div style="font-size:12px;font-weight:600"><?= e($log['module']) ?> › <?= e($log['action']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= thai_date($log['created_at'], 'j M Y H:i') ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Edit Forms -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Personal Info -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">ข้อมูลส่วนตัว</h3>
      </div>
      <form id="profile-form" class="card-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label required">ชื่อ</label>
            <input type="text" name="first_name" class="form-control" value="<?= e($user['first_name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label required">นามสกุล</label>
            <input type="text" name="last_name" class="form-control" value="<?= e($user['last_name'] ?? '') ?>" required>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">อีเมล</label>
            <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">โทรศัพท์</label>
            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">หน่วยงาน</label>
            <input type="text" name="department" class="form-control" value="<?= e($user['department'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">ตำแหน่ง</label>
            <input type="text" name="position" class="form-control" value="<?= e($user['position'] ?? '') ?>">
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button type="submit" class="btn btn-primary" id="btn-save-profile">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            บันทึกข้อมูล
          </button>
        </div>
      </form>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">เปลี่ยนรหัสผ่าน</h3>
      </div>
      <form id="password-form" class="card-body">
        <div class="form-group">
          <label class="form-label required">รหัสผ่านปัจจุบัน</label>
          <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label required">รหัสผ่านใหม่</label>
            <input type="password" name="new_password" class="form-control" autocomplete="new-password"
                   minlength="8" required>
            <div class="form-text">อย่างน้อย 8 ตัวอักษร</div>
          </div>
          <div class="form-group">
            <label class="form-label required">ยืนยันรหัสผ่านใหม่</label>
            <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button type="submit" class="btn btn-warning" id="btn-change-pwd">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            เปลี่ยนรหัสผ่าน
          </button>
        </div>
      </form>
    </div>

    <!-- Account Info (readonly) -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">ข้อมูลบัญชี</h3>
      </div>
      <div class="card-body">
        <div class="info-grid">
          <div class="info-item">
            <div class="info-label">ชื่อผู้ใช้</div>
            <div class="info-value" style="font-family:var(--font-mono)"><?= e($user['username']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">บทบาทในระบบ</div>
            <div class="info-value"><?= e($user['role_name'] ?? '-') ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">วันที่สร้างบัญชี</div>
            <div class="info-value text-sm"><?= thai_date($user['created_at']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">เปลี่ยนรหัสผ่านล่าสุด</div>
            <div class="info-value text-sm"><?= $user['password_changed_at'] ? thai_date($user['password_changed_at']) : 'ยังไม่เคยเปลี่ยน' ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">IP ล่าสุด</div>
            <div class="info-value text-sm" style="font-family:var(--font-mono)"><?= e($user['last_login_ip'] ?? '-') ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">สถานะบัญชี</div>
            <div class="info-value">
              <span class="badge <?= $user['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $user['is_active'] ? 'ใช้งานปกติ' : 'ระงับการใช้งาน' ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php \App\Core\View::endSection(); ?>

<?php \App\Core\View::section('scripts'); ?>
<script>
document.getElementById('profile-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-save-profile');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';
  RDSIS.Forms.clearErrors(this);
  try {
    const fd = new FormData(this);
    const data = await RDSIS.post('/profile', fd, true);
    RDSIS.Toast.success('บันทึกสำเร็จ', 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว');
  } catch (err) {
    if (err.errors) RDSIS.Forms.showErrors(this, err.errors);
    RDSIS.Toast.error('เกิดข้อผิดพลาด', err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/></svg> บันทึกข้อมูล';
  }
});

document.getElementById('password-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-change-pwd');
  const newPwd  = this.querySelector('[name="new_password"]').value;
  const confPwd = this.querySelector('[name="confirm_password"]').value;
  if (newPwd !== confPwd) {
    RDSIS.Toast.error('รหัสผ่านไม่ตรงกัน', 'กรุณากรอกรหัสผ่านใหม่ให้ตรงกัน');
    return;
  }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';
  try {
    const fd = new FormData(this);
    const data = await RDSIS.post('/profile/password', fd, true);
    RDSIS.Toast.success('เปลี่ยนรหัสผ่านสำเร็จ');
    this.reset();
  } catch (err) {
    RDSIS.Toast.error('เกิดข้อผิดพลาด', err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/></svg> เปลี่ยนรหัสผ่าน';
  }
});
</script>
<?php \App\Core\View::endSection(); ?>

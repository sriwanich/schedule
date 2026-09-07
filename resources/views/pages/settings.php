<?php \App\Core\View::extends('app'); \App\Core\View::section('content'); ?>

<div class="page-header">
  <div class="page-header-left">
    <h1>ตั้งค่าระบบ</h1>
    <p>กำหนดค่าการทำงานของระบบ RDSIS</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:24px">

  <!-- Sidebar Tabs -->
  <div class="card" style="align-self:start">
    <div class="card-body" style="padding:8px">
      <?php
      $tabs = [
        ['general',      'ทั่วไป',            'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ['notification', 'การแจ้งเตือน',     'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
        ['report',       'รายงาน',            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['security',     'ความปลอดภัย',      'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ['system',       'ระบบ',              'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v10m0 0h10m-10 0H5m5 0v6m0-6h4m0 0v6m0-6h4'],
      ];
      $activeTab = $_GET['tab'] ?? 'general';
      foreach ($tabs as [$tab, $label, $icon]):
        $active = $activeTab === $tab ? 'style="background:var(--primary-light);color:var(--primary)"' : '';
      ?>
      <a href="?tab=<?= $tab ?>" class="nav-item" <?= $active ?> style="border-radius:8px;margin:1px 0">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="<?= $icon ?>"/></svg>
        <span><?= $label ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Settings Form -->
  <div class="card">
    <form method="POST" action="<?= url('/settings') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="<?= e($activeTab) ?>">

      <div class="card-header">
        <h3 class="card-title">
          <?php
          $tabTitles = ['general'=>'ตั้งค่าทั่วไป','notification'=>'การแจ้งเตือน','report'=>'การออกรายงาน','security'=>'ความปลอดภัย','system'=>'ระบบ'];
          echo $tabTitles[$activeTab] ?? 'ตั้งค่า';
          ?>
        </h3>
        <button type="submit" class="btn btn-primary btn-sm">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          บันทึก
        </button>
      </div>

      <div class="card-body">
        <?php
        // Filter settings by group (tab)
        $groupMap = ['general'=>'general','notification'=>'notification','report'=>'report','security'=>'security','system'=>'system'];
        $group    = $groupMap[$activeTab] ?? 'general';
        $grouped  = array_filter($settings ?? [], fn($s) => $s['group'] === $group);
        ?>

        <?php if (empty($grouped)): ?>
        <div class="empty-state" style="padding:40px 0">
          <div class="empty-title">ไม่มีการตั้งค่าในหมวดนี้</div>
        </div>
        <?php else: ?>
        <?php foreach ($grouped as $setting): ?>
        <div class="form-group">
          <label class="form-label" for="setting_<?= e($setting['key']) ?>">
            <?= e($setting['label'] ?? $setting['key']) ?>
          </label>
          <?php if ($setting['type'] === 'boolean'): ?>
          <div style="display:flex;align-items:center;gap:10px">
            <label class="toggle-switch" style="position:relative;display:inline-block;width:44px;height:24px">
              <input type="hidden" name="settings[<?= e($setting['key']) ?>]" value="0">
              <input type="checkbox" id="setting_<?= e($setting['key']) ?>"
                     name="settings[<?= e($setting['key']) ?>]" value="1"
                     <?= $setting['value'] ? 'checked' : '' ?>
                     style="position:absolute;opacity:0;width:0;height:0">
              <span style="position:absolute;cursor:pointer;inset:0;background:var(--gray-300);border-radius:24px;transition:.3s"></span>
            </label>
            <span class="text-sm text-secondary"><?= e($setting['description'] ?? '') ?></span>
          </div>
          <?php elseif ($setting['type'] === 'text'): ?>
          <textarea id="setting_<?= e($setting['key']) ?>" name="settings[<?= e($setting['key']) ?>]"
                    class="form-control" rows="3"><?= e($setting['value'] ?? '') ?></textarea>
          <?php else: ?>
          <input type="<?= in_array($setting['type'], ['integer','float']) ? 'number' : 'text' ?>"
                 id="setting_<?= e($setting['key']) ?>"
                 name="settings[<?= e($setting['key']) ?>]"
                 class="form-control"
                 value="<?= e($setting['value'] ?? '') ?>">
          <?php endif; ?>
          <?php if ($setting['description'] && $setting['type'] !== 'boolean'): ?>
          <div class="form-text"><?= e($setting['description']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </form>
  </div>
</div>

<?php \App\Core\View::endSection(); ?>

<?php \App\Core\View::section('scripts'); ?>
<style>
.toggle-switch input:checked + span { background: var(--primary) !important; }
.toggle-switch span::before {
  content: ''; position: absolute;
  height: 18px; width: 18px; left: 3px; bottom: 3px;
  background: white; border-radius: 50%; transition: .3s;
}
.toggle-switch input:checked + span::before { transform: translateX(20px); }
</style>
<?php \App\Core\View::endSection(); ?>

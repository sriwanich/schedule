<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ — RDSIS จังหวัดระยอง</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg-base:#0a0e1a; --bg-card:#111827; --border:#1e2d47; --border-light:#2d4060;
  --text:#e8f0fe; --text-muted:#4a6080; --text-secondary:#8ba3c4;
  --red:#ef4444; --blue:#3b82f6; --blue-dark:#1d4ed8;
  --font: 'Noto Sans Thai', sans-serif; --mono: 'IBM Plex Mono', monospace;
}

body {
  font-family: var(--font);
  background: var(--bg-base);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  position: relative;
  overflow: hidden;
}

/* Animated background grid */
body::before {
  content: '';
  position: fixed; inset: 0;
  background-image:
    linear-gradient(rgba(59,130,246,.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(59,130,246,.04) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}

/* Glow orbs */
.orb {
  position: fixed;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  animation: pulse 8s ease-in-out infinite;
}
.orb-1 { width: 400px; height: 400px; background: rgba(239,68,68,.08); top: -100px; right: -100px; }
.orb-2 { width: 300px; height: 300px; background: rgba(59,130,246,.06); bottom: -50px; left: -50px; animation-delay: -4s; }

@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.1)} }

.login-wrap {
  width: 100%;
  max-width: 420px;
  position: relative;
  z-index: 1;
}

/* Header Badge */
.brand {
  text-align: center;
  margin-bottom: 32px;
}

.brand-icon {
  width: 60px; height: 60px;
  background: linear-gradient(135deg, var(--red), #b91c1c);
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 16px;
  box-shadow: 0 0 40px rgba(239,68,68,.3);
}

.brand-title {
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -.02em;
  line-height: 1.2;
  margin-bottom: 6px;
}

.brand-sub {
  font-size: 13px;
  color: var(--text-secondary);
  line-height: 1.5;
}

.brand-version {
  display: inline-block;
  margin-top: 8px;
  font-family: var(--mono);
  font-size: 10px;
  color: var(--text-muted);
  background: var(--border);
  padding: 2px 8px;
  border-radius: 4px;
}

/* Card */
.login-card {
  background: var(--bg-card);
  border: 1px solid var(--border-light);
  border-radius: 16px;
  padding: 36px;
  box-shadow: 0 24px 64px rgba(0,0,0,.6);
}

.login-card h2 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 4px;
}
.login-card .sub { font-size: 13px; color: var(--text-secondary); margin-bottom: 28px; }

/* Form */
.field { margin-bottom: 20px; }
.field label {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 7px;
  letter-spacing: .03em;
}

.input-wrap { position: relative; }
.input-icon {
  position: absolute;
  left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--text-muted);
  pointer-events: none;
}

input[type=text], input[type=password] {
  width: 100%;
  padding: 11px 12px 11px 40px;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border-light);
  border-radius: 8px;
  color: var(--text);
  font-family: var(--font);
  font-size: 14px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
input::placeholder { color: var(--text-muted); }

.show-pass {
  position: absolute;
  right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  line-height: 0;
}
.show-pass:hover { color: var(--text-secondary); }

.btn-login {
  width: 100%;
  padding: 12px;
  background: var(--blue);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-family: var(--font);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 8px;
}
.btn-login:hover { background: var(--blue-dark); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(59,130,246,.4); }
.btn-login:active { transform: translateY(0); }

.alert-error {
  padding: 11px 14px;
  background: rgba(239,68,68,.12);
  border: 1px solid rgba(239,68,68,.3);
  border-radius: 8px;
  color: #fca5a5;
  font-size: 13.5px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 20px;
}

/* Footer info */
.login-footer {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 12px;
  color: var(--text-muted);
}

.status-dot {
  width: 6px; height: 6px;
  background: #10b981;
  border-radius: 50%;
  animation: blink 2s ease infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

/* Decorative */
.deco-line {
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-light), transparent);
  margin: 20px 0;
}

/* Emergency bar */
.emergency-bar {
  margin-top: 20px;
  padding: 10px 16px;
  background: rgba(239,68,68,.08);
  border: 1px solid rgba(239,68,68,.2);
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12.5px;
  color: #fca5a5;
}
.emergency-bar strong { font-family: var(--mono); font-size: 15px; }
</style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="login-wrap">
  <div class="brand">
    <div class="brand-icon">
      <svg width="30" height="30" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
    </div>
    <div class="brand-title">ระบบสถิติสาธารณภัย<br>จังหวัดระยอง</div>
    <div class="brand-sub">Rayong Disaster Statistics Information System</div>
    <span class="brand-version">RDSIS v<?= RDSIS_VERSION ?></span>
  </div>

  <div class="login-card">
    <h2>เข้าสู่ระบบ</h2>
    <p class="sub">กรุณากรอกข้อมูลผู้ใช้งานของท่าน</p>

    <?php if (has_flash('error')): ?>
    <div class="alert-error">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?= e(view_flash('error')) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('login') ?>">
      <?= csrf_field() ?>

      <div class="field">
        <label>ชื่อผู้ใช้ / อีเมล</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="username" placeholder="username หรือ email" value="<?= e(old('username')) ?>" autocomplete="username" autofocus>
        </div>
      </div>

      <div class="field">
        <label>รหัสผ่าน</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          <input type="password" name="password" id="password" placeholder="รหัสผ่าน" autocomplete="current-password">
          <button type="button" class="show-pass" onclick="togglePass()">
            <svg id="eyeIcon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        เข้าสู่ระบบ
      </button>
    </form>

    <div class="deco-line"></div>

    <div class="login-footer">
      <div class="status-dot"></div>
      ระบบพร้อมใช้งาน | เขตเวลา UTC+7 | <?= thai_date() ?>
    </div>
  </div>

  <div class="emergency-bar">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
    สายด่วนสาธารณภัย: <strong>1784</strong>
    <span style="margin-left:auto;color:rgba(252,165,165,.5);">ตลอด 24 ชั่วโมง</span>
  </div>
</div>

<script>
function togglePass() {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>

/**
 * ═══════════════════════════════════════════════════════════════
 *  UNIFIED THEME MANAGER  —  ระบบปฏิทินและนัดหมาย
 *  สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
 *
 *  Features:
 *  - Shared theme across ALL pages via localStorage key 'appTheme'
 *  - Modes: 'light' | 'dark' | 'auto'
 *  - Auto mode: dark 18:00–06:00, light 06:00–18:00 (Bangkok time)
 *  - Updates theme icon on every page consistently
 *  - Broadcasts theme changes to other open tabs via BroadcastChannel
 *  - Exposes: ThemeManager.toggle(), ThemeManager.setMode(), ThemeManager.getMode()
 * ═══════════════════════════════════════════════════════════════
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'appTheme';       // Single key shared by all pages
  const CHANNEL_NAME = 'theme-sync';   // BroadcastChannel name

  // ── Helpers ──────────────────────────────────────────────────
  function isDayTime() {
    const h = new Date().getHours(); // Bangkok = server TZ (Asia/Bangkok)
    return h >= 6 && h < 18;        // 06:00–17:59 = day
  }

  function resolveTheme(mode) {
    if (mode === 'auto') return isDayTime() ? 'light' : 'dark';
    return mode === 'dark' ? 'dark' : 'light';
  }

  function getStoredMode() {
    return localStorage.getItem(STORAGE_KEY) || 'light';
  }

  // ── Badge style (injected after DOM ready to sit last in cascade) ─
  function injectBadgeStyle() {
    if (document.getElementById('__themeBadgeStyle')) return;
    var s = document.createElement('style');
    s.id = '__themeBadgeStyle';
    s.textContent =
      '#autoModeBadge{display:none !important;}' +
      '#autoModeBadge.visible{display:inline-flex !important;align-items:center;gap:3px;' +
      'padding:2px 8px;border-radius:999px;background:rgba(232,160,32,.25);color:#e8a020;' +
      'font-size:.68rem;font-weight:700;letter-spacing:.03em;white-space:nowrap;}';
    document.head.appendChild(s);
  }

  // ── Apply ─────────────────────────────────────────────────────
  function applyTheme(mode) {
    const theme = resolveTheme(mode);
    document.documentElement.setAttribute('data-theme', theme);
    updateAllIcons(mode, theme);
  }

  function updateAllIcons(mode, theme) {
    // Update every theme icon on the page (some pages may have multiple)
    document.querySelectorAll('#themeIcon, .theme-icon-el').forEach(function (icon) {
      if (mode === 'auto') {
        icon.className = 'bi bi-brightness-auto';
      } else if (theme === 'dark') {
        icon.className = 'bi bi-sun-fill';
      } else {
        icon.className = 'bi bi-moon-stars-fill';
      }
    });

    // Update theme-toggle button title
    document.querySelectorAll('#themeToggle, .theme-toggle-btn').forEach(function (btn) {
      const labels = { light: 'เปลี่ยนเป็นโหมดมืด', dark: 'เปลี่ยนเป็นโหมดสว่าง', auto: 'ปิด Auto Mode' };
      btn.title = labels[mode] || 'สลับธีม';
    });

    // Update auto-mode indicator badge — use class toggle to avoid inline-style conflict
    const autoBadge = document.getElementById('autoModeBadge');
    if (autoBadge) {
      // Strip any leftover inline display so our CSS class takes over
      autoBadge.style.removeProperty('display');
      if (mode === 'auto') {
        autoBadge.classList.add('visible');
      } else {
        autoBadge.classList.remove('visible');
      }
    }
  }

  // ── Toggle cycle: light → dark → auto → light ────────────────
  function toggle() {
    const current = getStoredMode();
    const next = current === 'light' ? 'dark' : current === 'dark' ? 'auto' : 'light';
    setMode(next);
  }

  function setMode(mode) {
    localStorage.setItem(STORAGE_KEY, mode);
    applyTheme(mode);
    // Broadcast to other tabs
    try {
      const bc = new BroadcastChannel(CHANNEL_NAME);
      bc.postMessage({ type: 'theme-change', mode: mode });
      bc.close();
    } catch (e) { /* BroadcastChannel not supported in some browsers */ }
  }

  function getMode() { return getStoredMode(); }

  // ── Init: apply immediately (before paint) ───────────────────
  applyTheme(getStoredMode());

  // ── Auto-mode: re-evaluate every minute ─────────────────────
  setInterval(function () {
    if (getStoredMode() === 'auto') applyTheme('auto');
  }, 60000);

  // ── Listen for changes from other tabs ───────────────────────
  try {
    const bc = new BroadcastChannel(CHANNEL_NAME);
    bc.onmessage = function (e) {
      if (e.data && e.data.type === 'theme-change') {
        applyTheme(e.data.mode);
      }
    };
  } catch (e) { /* fallback: no cross-tab sync */ }

  // ── DOM ready: wire up buttons ───────────────────────────────
  function wireButtons() {
    injectBadgeStyle();   // must be first — appends to <head> last in cascade
    document.querySelectorAll('#themeToggle, .theme-toggle-btn').forEach(function (btn) {
      // Remove old listeners by cloning
      const fresh = btn.cloneNode(true);
      btn.parentNode.replaceChild(fresh, btn);
      fresh.addEventListener('click', toggle);
    });
    // Re-apply now that DOM + badge style are both ready
    applyTheme(getStoredMode());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireButtons);
  } else {
    wireButtons();
  }

  // ── Expose public API ────────────────────────────────────────
  window.ThemeManager = { toggle: toggle, setMode: setMode, getMode: getMode };
})();

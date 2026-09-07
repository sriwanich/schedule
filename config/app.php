<?php
/**
 * Schedule Enterprise — Application Config
 * ★ Phase 3: Enterprise Refactor
 */

// ── Environment ──────────────────────────────────────────────
define('APP_ENV',     getenv('APP_ENV')  ?: 'production');   // production | development
define('APP_DEBUG',   APP_ENV === 'development');
define('APP_VERSION', '3.0.0');
define('BASE_URL',    getenv('BASE_URL') ?: 'https://schedule.dpmpry.online');
define('APP_NAME',    'ระบบปฏิทินและนัดหมาย');
define('APP_TZ',      'Asia/Bangkok');

// ── Security ─────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600 * 8);    // 8 hours
define('CSRF_TOKEN_NAME',  '_csrf_token');
define('RATE_LIMIT_LOGIN', 5);           // max login attempts per window
define('RATE_LIMIT_WINDOW', 300);        // 5 minutes window

// ── Cache ────────────────────────────────────────────────────
define('CACHE_DIR',      __DIR__ . '/../cache/');
define('CACHE_DURATION', 300);           // 5 minutes default TTL

// ── Upload ───────────────────────────────────────────────────
define('UPLOAD_DIR',     __DIR__ . '/../uploads/');
define('UPLOAD_MAX_MB',  10);
define('ALLOWED_TYPES',  ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif']);

// ── Google reCAPTCHA ─────────────────────────────────────────
define('RECAPTCHA_SITE_KEY',   getenv('RECAPTCHA_SITE')   ?: '6LfBiYUsAAAAAOIGZ5mf6Sa-lEywuBGyPDxipTbo');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET') ?: '6LfBiYUsAAAAAPKJSzAjedxLsjuLJYRhiXoqo6t5');

// ── Security Headers ─────────────────────────────────────────
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── Error reporting ──────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set(APP_TZ);
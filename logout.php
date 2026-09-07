<?php
/**
 * Schedule Enterprise — Logout
 * ล้าง session + Remember Me token (cookie & DB) ให้ครบ
 */

// ─── Security Headers ────────────────────────────────────────────────────────
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/modules/Auth/AuthMiddleware.php';

use Schedule\Auth\AuthMiddleware;

// ─── เริ่ม Session (ถ้ายังไม่ได้เริ่ม) ──────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ─── ชื่อ Cookie ตรงกับที่ login.php กำหนด ──────────────────────────────────
define('REMEMBER_COOKIE_TOKEN', 'sch_rem_token');
define('REMEMBER_COOKIE_USER',  'sch_rem_user');

/**
 * ลบ cookie โดยตั้ง expires ในอดีต
 */
function clearCookie(string $name): void {
    if (isset($_COOKIE[$name])) {
        setcookie($name, '', [
            'expires'  => 1,          // 1 January 1970 — หมดอายุทันที
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        unset($_COOKIE[$name]);       // ล้างออกจาก super-global ด้วย
    }
}

// ─── 1. ล้าง Remember Me Token ใน Database ───────────────────────────────────
// ต้องทำก่อน session_destroy() เพราะยังต้องการ user_id
$userId = $_SESSION['user_id'] ?? null;

if ($userId !== null) {
    try {
        getPDO()->prepare(
            "UPDATE users
             SET remember_token         = NULL,
                 remember_token_expires = NULL
             WHERE id = :id"
        )->execute([':id' => $userId]);
    } catch (\Exception $e) {
        // ถ้า column ยังไม่มีหรือ DB error → ข้ามได้ ไม่หยุด logout
    }
}

// หรือถ้าไม่มี user_id ใน session แต่ยังมี cookie token → ล้างจาก token โดยตรง
if ($userId === null && isset($_COOKIE[REMEMBER_COOKIE_TOKEN])) {
    try {
        getPDO()->prepare(
            "UPDATE users
             SET remember_token         = NULL,
                 remember_token_expires = NULL
             WHERE remember_token = :token"
        )->execute([':token' => $_COOKIE[REMEMBER_COOKIE_TOKEN]]);
    } catch (\Exception $e) {
        // ข้ามไป
    }
}

// ─── 2. ลบ Remember Me Cookies ───────────────────────────────────────────────
clearCookie(REMEMBER_COOKIE_TOKEN);
clearCookie(REMEMBER_COOKIE_USER);

// ─── 3. ล้าง Session ─────────────────────────────────────────────────────────
$_SESSION = [];     // ล้าง super-global ก่อน

// ลบ session cookie ด้วย (browser จะลบ PHPSESSID)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => 1,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Strict',
        ]
    );
}

session_destroy();

// ─── 4. Redirect ไปหน้า Login ────────────────────────────────────────────────
header('Location: /login.php');
exit();

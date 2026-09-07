<?php
/**
 * Schedule Enterprise — Login Page
 * ★ Phase 3: Rate limiting, CSRF, bcrypt, reCAPTCHA v2
 * ★ Phase 4: Token-based Remember Me (7 days)
 */

// ─── Security Headers ────────────────────────────────────────────────────────
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/modules/Auth/AuthMiddleware.php';
require_once __DIR__ . '/modules/Auth/AuthController.php';

use Schedule\Auth\AuthMiddleware;
use Schedule\Auth\AuthController;

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

// ─── CSP Nonce ───────────────────────────────────────────────────────────────
$nonce = base64_encode(random_bytes(16));

header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'nonce-{$nonce}' https://www.google.com https://www.gstatic.com; "
    . "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; "
    . "font-src https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
    . "img-src 'self' data:; "
    . "frame-src https://www.google.com; "
    . "connect-src 'self';");

// ─── Remember Me Constants ───────────────────────────────────────────────────
define('REMEMBER_COOKIE_TOKEN', 'sch_rem_token'); // ชื่อ cookie ที่เก็บ token จริง
define('REMEMBER_COOKIE_USER',  'sch_rem_user');  // ชื่อ cookie ที่เก็ย username (สำหรับ autofill)
define('REMEMBER_TTL',          7 * 24 * 60 * 60); // 7 วัน (วินาที)

// ─── Helper: สร้าง Remember Me Token ────────────────────────────────────────
function generateRememberToken(): string {
    return bin2hex(random_bytes(32)); // 64 ตัวอักษร hex
}

// ─── Helper: ตั้งค่า Cookie ──────────────────────────────────────────────────
function setRememberCookie(string $name, string $value, int $expires): void {
    setcookie($name, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// ─── Helper: ลบ Cookie ───────────────────────────────────────────────────────
function clearRememberCookie(string $name): void {
    setcookie($name, '', [
        'expires'  => 1,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// ─── ตรวจสอบ Remember Me Token (ก่อน check session ปกติ) ───────────────────
// ถ้ามี token ใน cookie และยังไม่ได้ login → ตรวจสอบกับฐานข้อมูล
if (!AuthMiddleware::isLoggedIn() && isset($_COOKIE[REMEMBER_COOKIE_TOKEN])) {
    $cookieToken = $_COOKIE[REMEMBER_COOKIE_TOKEN];
    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            "SELECT id, username, role FROM users
             WHERE remember_token = :token
               AND remember_token_expires > NOW()
             LIMIT 1"
        );
        $stmt->execute([':token' => $cookieToken]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Token ถูกต้อง → ต่ออายุ token (rolling window) และสร้าง session
            $newToken   = generateRememberToken();
            $newExpires = date('Y-m-d H:i:s', time() + REMEMBER_TTL);

            $pdo->prepare(
                "UPDATE users
                 SET remember_token = :token, remember_token_expires = :expires
                 WHERE id = :id"
            )->execute([
                ':token'   => $newToken,
                ':expires' => $newExpires,
                ':id'      => $user['id'],
            ]);

            // อัปเดต cookie ให้ token ใหม่
            setRememberCookie(REMEMBER_COOKIE_TOKEN, $newToken, time() + REMEMBER_TTL);

            // สร้าง session เหมือนการ login ปกติ
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['logged_in'] = true;

            // Redirect ไป dashboard
            $redirect = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . ($redirect ?: (AuthMiddleware::isMobileOrTablet() ? 'm_dashboard' : 'dashboard')));
            exit();
        } else {
            // Token หมดอายุหรือไม่ถูกต้อง → ล้าง cookie
            clearRememberCookie(REMEMBER_COOKIE_TOKEN);
            clearRememberCookie(REMEMBER_COOKIE_USER);
        }
    } catch (\Exception $e) {
        // ถ้า DB error ให้ผ่านไป (ผู้ใช้จะเห็นหน้า login ปกติ)
        clearRememberCookie(REMEMBER_COOKIE_TOKEN);
    }
}

// Already logged in → redirect
if (AuthMiddleware::isLoggedIn()) {
    header('Location: ' . (AuthMiddleware::isMobileOrTablet() ? 'm_dashboard' : 'dashboard'));
    exit();
}

$error = '';

// ─── อ่านชื่อผู้ใช้จาก cookie (สำหรับ autofill เท่านั้น) ──────────────────
$rememberedUser = '';
if (isset($_COOKIE[REMEMBER_COOKIE_USER])) {
    $decoded = base64_decode($_COOKIE[REMEMBER_COOKIE_USER], true);
    if ($decoded !== false) {
        $rememberedUser = htmlspecialchars($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// ─── POST: ประมวลผลฟอร์ม Login ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
    } else {
        $controller = new AuthController(getPDO());
        $result = $controller->login(
            trim($_POST['username'] ?? ''),
            $_POST['password'] ?? '',
            $_POST['g-recaptcha-response'] ?? ''
        );

        if ($result['success']) {
            $rememberChecked = !empty($_POST['remember_me']);
            $username        = trim($_POST['username'] ?? '');

            if ($rememberChecked) {
                // ─── สร้างและบันทึก Remember Me Token ──────────────────
                $token   = generateRememberToken();
                $expires = date('Y-m-d H:i:s', time() + REMEMBER_TTL);

                try {
                    $pdo = getPDO();
                    $pdo->prepare(
                        "UPDATE users
                         SET remember_token = :token, remember_token_expires = :expires
                         WHERE username = :username"
                    )->execute([
                        ':token'    => $token,
                        ':expires'  => $expires,
                        ':username' => $username,
                    ]);

                    // ตั้ง secure cookie 2 อัน:
                    // 1. token cookie (สำหรับ auto-login)
                    setRememberCookie(REMEMBER_COOKIE_TOKEN, $token, time() + REMEMBER_TTL);
                    // 2. username cookie (สำหรับ autofill เท่านั้น — ไม่ใช่ sensitive)
                    setRememberCookie(REMEMBER_COOKIE_USER, base64_encode($username), time() + REMEMBER_TTL);
                } catch (\Exception $e) {
                    // ถ้า update ไม่ได้ (คอลัมน์ยังไม่มี) ให้ข้ามไป — login ยังทำงานได้
                }
            } else {
                // ผู้ใช้ uncheck → ล้าง token ทั้งใน DB และ cookie
                try {
                    getPDO()->prepare(
                        "UPDATE users
                         SET remember_token = NULL, remember_token_expires = NULL
                         WHERE username = :username"
                    )->execute([':username' => trim($_POST['username'] ?? '')]);
                } catch (\Exception $e) { /* ไม่มีคอลัมน์ → ข้ามไป */ }

                clearRememberCookie(REMEMBER_COOKIE_TOKEN);
                clearRememberCookie(REMEMBER_COOKIE_USER);
            }

            $redirect = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . ($redirect ?: (AuthMiddleware::isMobileOrTablet() ? 'm_dashboard' : 'dashboard')));
            exit();
        }
        $error = $result['error'];
    }
}

// CSRF token for form
$csrfToken = AuthMiddleware::csrfToken();

// Latest version
try {
    $ver = getPDO()->query("SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1")->fetchColumn();
    $versionText = $ver ? 'เวอร์ชัน ' . htmlspecialchars($ver, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
} catch (\Exception $e) {
    $versionText = '';
}
?>
<!DOCTYPE html>
<html lang="th" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>เข้าสู่ระบบ — <?= APP_NAME ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a1628">

    <!-- Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="/images/logo.webp" as="image" type="image/webp">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
          integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkA=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">

    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer nonce="<?= htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"></script>

    <style>
        /* ─── Design Tokens ─────────────────────────────────────────── */
        :root {
            --clr-bg-start:    #0a1628;
            --clr-bg-mid:      #0d2340;
            --clr-bg-end:      #1a0a2e;
            --clr-accent:      #e8a020;
            --clr-accent-dim:  rgba(232, 160, 32, 0.18);
            --clr-blue:        #1e6fd9;
            --clr-blue-glow:   rgba(30, 111, 217, 0.45);
            --clr-surface:     rgba(255, 255, 255, 0.055);
            --clr-surface-hover: rgba(255, 255, 255, 0.08);
            --clr-border:      rgba(255, 255, 255, 0.10);
            --clr-border-focus: rgba(30, 111, 217, 0.60);
            --clr-text:        #f0f4ff;
            --clr-text-muted:  rgba(240, 244, 255, 0.55);
            --clr-danger:      #e05252;
            --clr-danger-dim:  rgba(224, 82, 82, 0.15);
            --clr-success:     #3ec97e;

            --radius-card:    24px;
            --radius-input:   10px;
            --radius-pill:    50px;
            --shadow-card:    0 32px 80px rgba(0, 0, 0, 0.65), 0 0 0 1px var(--clr-border);
            --transition:     0.35s cubic-bezier(0.22, 1, 0.36, 1);
            --font-main:      'Kanit', system-ui, sans-serif;
        }

        /* ─── Reset ─────────────────────────────────────────────────── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            -webkit-text-size-adjust: 100%;
        }

        /* ─── Body & Background ─────────────────────────────────────── */
        body {
            font-family: var(--font-main);
            background-color: var(--clr-bg-start);
            background-image:
                radial-gradient(ellipse 80% 60% at 20% 0%,  rgba(30, 111, 217, 0.22) 0%, transparent 65%),
                radial-gradient(ellipse 60% 50% at 80% 100%, rgba(232, 160, 32, 0.14) 0%, transparent 60%),
                radial-gradient(ellipse 50% 80% at 50% 50%,  rgba(10, 22, 40, 1) 0%, var(--clr-bg-end) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            color: var(--clr-text);
        }

        /* ─── Grid Overlay ──────────────────────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        /* ─── Orb Lights ────────────────────────────────────────────── */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
            z-index: 0;
            animation: orbFloat var(--dur, 20s) ease-in-out infinite;
        }
        .orb--blue { width: 480px; height: 480px; background: rgba(30, 111, 217, 0.18); top: -120px; left: -80px; --dur: 22s; }
        .orb--gold { width: 360px; height: 360px; background: rgba(232, 160, 32, 0.14); bottom: -80px; right: -60px; --dur: 18s; animation-direction: reverse; }
        .orb--red  { width: 280px; height: 280px; background: rgba(224, 82, 82, 0.10); top: 60%; left: 60%; --dur: 25s; }

        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0); }
            33%       { transform: translate(30px, -40px); }
            66%       { transform: translate(-20px, 30px); }
        }

        /* ─── Particle Canvas ───────────────────────────────────────── */
        #particle-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* ─── Login Card ────────────────────────────────────────────── */
        .login-card {
            position: relative;
            z-index: 10;
            width: min(460px, calc(100vw - 32px));
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-card);
            backdrop-filter: blur(24px) saturate(1.5);
            -webkit-backdrop-filter: blur(24px) saturate(1.5);
            box-shadow: var(--shadow-card);
            padding: 44px 40px 36px;
            animation: splashIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes splashIn {
            from { opacity: 0; transform: translateY(32px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        .login-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--radius-card);
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        /* ─── Logo Area ─────────────────────────────────────────────── */
        .logo-wrap {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-ring {
            position: relative;
            width: 96px;
            height: 96px;
            margin: 0 auto 16px;
        }

        .logo-ring__svg {
            position: absolute;
            inset: -8px;
            width: calc(100% + 16px);
            height: calc(100% + 16px);
            animation: spinRing 8s linear infinite;
        }

        .logo-ring__svg circle {
            fill: none;
            stroke: url(#ringGrad);
            stroke-width: 1.5;
            stroke-dasharray: 6 12;
        }

        @keyframes spinRing {
            to { transform: rotate(360deg); }
        }

        .logo-ring__img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            padding: 10px;
            animation: logoPulse 4s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }

        @keyframes logoPulse {
            0%, 100% { filter: drop-shadow(0 0 12px rgba(30,111,217,0.5)); transform: scale(1); }
            50%       { filter: drop-shadow(0 0 24px rgba(232,160,32,0.6)); transform: scale(1.04); }
        }

        .login-title {
            font-size: clamp(20px, 5vw, 24px);
            font-weight: 700;
            background: linear-gradient(120deg, #ffffff 30%, var(--clr-accent) 80%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .login-subtitle {
            font-size: 13px;
            color: var(--clr-text-muted);
            font-weight: 300;
            letter-spacing: 0.3px;
        }

        /* ─── Error Box ─────────────────────────────────────────────── */
        .error-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--clr-danger-dim);
            border: 1px solid rgba(224, 82, 82, 0.35);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            font-weight: 400;
            color: #fca5a5;
            margin-bottom: 20px;
            animation: shakeIn 0.4s ease;
        }

        @keyframes shakeIn {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-6px); }
            40%       { transform: translateX(6px); }
            60%       { transform: translateX(-4px); }
            80%       { transform: translateX(4px); }
        }

        .error-box i { font-size: 15px; color: var(--clr-danger); flex-shrink: 0; }

        /* ─── Form ──────────────────────────────────────────────────── */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--clr-text-muted);
            margin-bottom: 7px;
            letter-spacing: 0.3px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--clr-text-muted);
            font-size: 14px;
            pointer-events: none;
            transition: color 0.2s;
            z-index: 1;
        }

        .form-input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-input);
            padding: 11px 14px 11px 40px;
            font-family: var(--font-main);
            font-size: 15px;
            font-weight: 400;
            color: var(--clr-text);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            -webkit-appearance: none;
        }

        .form-input::placeholder {
            color: rgba(240,244,255,0.28);
        }

        .form-input:focus {
            border-color: var(--clr-border-focus);
            background: rgba(30,111,217,0.08);
            box-shadow: 0 0 0 3px rgba(30,111,217,0.18);
        }

        .form-input:focus + .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--clr-blue);
        }

        .form-input.has-toggle { padding-right: 44px; }

        .toggle-pw {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #ffffff;
            border: 1px solid #ccc;
            border-radius: 6px;
            width: 34px;
            height: 30px;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, border-color 0.2s, transform 0.15s, box-shadow 0.2s;
            z-index: 2;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }

        .toggle-pw:hover {
            background: #f0f0f0;
            border-color: #999;
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .toggle-pw:focus-visible { outline: 2px solid var(--clr-border-focus); outline-offset: 2px; }
        .toggle-pw[aria-pressed="true"] {
            background: #e8f0fe;
            border-color: #1e6fd9;
        }
        .toggle-pw[aria-pressed="true"] svg { stroke: #1e6fd9; }
        .toggle-pw:active { transform: translateY(-50%) scale(0.92); }

        /* ─── reCAPTCHA wrapper ──────────────────────────────────────── */
        .recaptcha-wrap {
            margin-bottom: 20px;
            transform: scale(0.92);
            transform-origin: left center;
        }

        /* ─── Submit Button ─────────────────────────────────────────── */
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, var(--clr-blue) 0%, #1458b0 100%);
            border: none;
            border-radius: var(--radius-input);
            padding: 13px 20px;
            font-family: var(--font-main);
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            letter-spacing: 0.4px;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
            box-shadow: 0 4px 20px rgba(30,111,217,0.40);
        }

        .btn-login::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.10) 0%, transparent 60%);
            border-radius: inherit;
            pointer-events: none;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(30,111,217,0.55);
            filter: brightness(1.08);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(30,111,217,0.35);
        }

        /* ─── Remember Me + Forgot row ─────────────────────────────── */
        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 14px;
            margin-bottom: 0;
            gap: 8px;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            font-size: 13px;
            color: var(--clr-text-muted);
            transition: color 0.2s;
        }

        .remember-label:hover { color: var(--clr-text); }

        .remember-label input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .checkmark {
            width: 17px;
            height: 17px;
            border-radius: 5px;
            border: 1.5px solid var(--clr-border);
            background: rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }

        .remember-label input[type="checkbox"]:checked ~ .checkmark {
            background: var(--clr-blue);
            border-color: var(--clr-blue);
            box-shadow: 0 0 0 3px rgba(30,111,217,0.20);
        }

        .checkmark::after {
            content: '';
            display: none;
            width: 4px;
            height: 8px;
            border: 2px solid #fff;
            border-top: none;
            border-left: none;
            transform: rotate(45deg) translateY(-1px);
        }

        .remember-label input[type="checkbox"]:checked ~ .checkmark::after {
            display: block;
        }

        /* ─── Remember badge (แสดงเมื่อ remember token มีผล) ─────────── */
        .remember-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--clr-success);
            background: rgba(62, 201, 126, 0.12);
            border: 1px solid rgba(62, 201, 126, 0.30);
            border-radius: var(--radius-pill);
            padding: 3px 10px;
            margin-bottom: 14px;
        }

        .remember-badge i { font-size: 10px; }

        /* Forgot password link */
        .forgot-link {
            font-size: 13px;
            color: var(--clr-text-muted);
            text-decoration: none;
            transition: color 0.2s;
            white-space: nowrap;
        }

        .forgot-link:hover { color: var(--clr-accent); }

        /* ─── Divider ───────────────────────────────────────────────── */
        .card-divider {
            border: none;
            border-top: 1px solid var(--clr-border);
            margin: 20px 0 16px;
        }

        /* ─── Status Strip ──────────────────────────────────────────── */
        .status-strip {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 500;
            color: var(--clr-text-muted);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--clr-success);
            box-shadow: 0 0 8px var(--clr-success);
            animation: dotPulse 2s ease-in-out infinite;
        }

        @keyframes dotPulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.4; }
        }

        /* ─── Version Badge ─────────────────────────────────────────── */
        .version-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 500;
            color: var(--clr-text-muted);
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-pill);
            padding: 5px 12px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }

        .version-badge:hover {
            background: var(--clr-accent-dim);
            border-color: rgba(232,160,32,0.30);
            color: var(--clr-accent);
            transform: translateY(-1px);
        }

        /* ─── Footer ────────────────────────────────────────────────── */
        .page-footer {
            position: relative;
            z-index: 10;
            text-align: center;
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .page-footer__copy {
            font-size: 12px;
            color: rgba(255,255,255,0.35);
            font-weight: 300;
        }

        /* ─── Accessibility ─────────────────────────────────────────── */
        .sr-only {
            position: absolute; width: 1px; height: 1px;
            padding: 0; margin: -1px; overflow: hidden;
            clip: rect(0,0,0,0); white-space: nowrap; border: 0;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ─── Responsive ────────────────────────────────────────────── */
        @media (max-width: 380px) {
            .login-card { padding: 36px 24px 28px; }
            .logo-ring  { width: 80px; height: 80px; }
            .recaptcha-wrap { transform: scale(0.82); }
        }
    </style>
</head>
<body>

    <!-- Ambient Orbs -->
    <div class="orb orb--blue" aria-hidden="true"></div>
    <div class="orb orb--gold" aria-hidden="true"></div>
    <div class="orb orb--red"  aria-hidden="true"></div>

    <!-- Particle Canvas -->
    <canvas id="particle-canvas" aria-hidden="true"></canvas>

    <!-- Wrapper -->
    <div style="display:flex; flex-direction:column; align-items:center;">

        <!-- ─── Login Card ────────────────────────────────────────── -->
        <main class="login-card" role="main" aria-label="แบบฟอร์มเข้าสู่ระบบ">

            <!-- SVG Gradient Defs -->
            <svg width="0" height="0" style="position:absolute" aria-hidden="true">
                <defs>
                    <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%"   stop-color="#1e6fd9"/>
                        <stop offset="50%"  stop-color="#e8a020"/>
                        <stop offset="100%" stop-color="#1e6fd9"/>
                    </linearGradient>
                </defs>
            </svg>

            <!-- Logo + Title -->
            <div class="logo-wrap">
                <div class="logo-ring" aria-hidden="true">
                    <svg class="logo-ring__svg" viewBox="0 0 136 136" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="68" cy="68" r="64"/>
                    </svg>
                    <img class="logo-ring__img"
                         src="/images/logo.webp"
                         alt="โลโก้สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง"
                         width="96" height="96"
                         loading="eager" decoding="async">
                </div>
                <h1 class="login-title">เข้าสู่ระบบ</h1>
                <p class="login-subtitle">ระบบปฏิทินและจัดการนัดหมาย</p>
            </div>

            <!-- Error -->
            <?php if ($error): ?>
                <div class="error-box" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" autocomplete="on" novalidate>
                <input type="hidden"
                       name="<?= CSRF_TOKEN_NAME ?>"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label" for="username">ชื่อผู้ใช้</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user input-icon" aria-hidden="true"></i>
                        <input type="text"
                               id="username"
                               name="username"
                               class="form-input"
                               value="<?= htmlspecialchars($_POST['username'] ?? $rememberedUser, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                               placeholder="กรอกชื่อผู้ใช้"
                               required
                               autocomplete="username"
                               autofocus>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="passwordInput">รหัสผ่าน</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                        <input type="password"
                               id="passwordInput"
                               name="password"
                               class="form-input has-toggle"
                               placeholder="กรอกรหัสผ่าน"
                               required
                               autocomplete="current-password">
                        <button type="button"
                                class="toggle-pw"
                                id="togglePwBtn"
                                aria-label="แสดง/ซ่อนรหัสผ่าน"
                                aria-pressed="false">
                            <!-- eye-open -->
                            <svg id="pwEye" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <!-- eye-off (hidden by default) -->
                            <svg id="pwEyeOff" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me + Forgot -->
                <div class="form-row">
                    <label class="remember-label">
                        <input type="checkbox" id="rememberMe" name="remember_me" value="1"
                               <?= $rememberedUser ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        จดจำฉัน <span style="font-size:11px; opacity:0.6;">(7 วัน)</span>
                    </label>
                    <a href="/forgot_password" class="forgot-link">
                        <i class="fa-solid fa-key" aria-hidden="true" style="margin-right:4px; font-size:11px"></i>
                        ลืมรหัสผ่าน?
                    </a>
                </div>

                <!-- reCAPTCHA -->
                <div class="recaptcha-wrap" style="margin-top:18px;">
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"></div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true" style="margin-right:8px"></i>
                    เข้าสู่ระบบ
                </button>
            </form>

            <!-- Divider + Status -->
            <hr class="card-divider">
            <div class="status-strip" aria-hidden="true">
                <div class="status-item">
                    <span class="status-dot"></span>
                    <span>ระบบออนไลน์</span>
                </div>
                <div class="status-item">
                    <i class="fa-solid fa-lock" style="font-size:10px; color: var(--clr-success)"></i>
                    <span>SSL ปลอดภัย</span>
                </div>
            </div>

        </main><!-- /.login-card -->

        <!-- Footer -->
        <footer class="page-footer">
            <?php if ($versionText): ?>
                <a href="/changelog.php"
                   class="version-badge"
                   aria-label="<?= htmlspecialchars($versionText, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> — ดูรายละเอียด">
                    <i class="fa-solid fa-tag" style="font-size:10px" aria-hidden="true"></i>
                    <?= htmlspecialchars($versionText, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </a>
            <?php endif; ?>
            <p class="page-footer__copy">
                &copy; <?= date('Y') ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
            </p>
        </footer>

    </div><!-- /.wrapper -->

    <!-- ─── Scripts ──────────────────────────────────────────────── -->
    <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';

        /* ── Particle System ── */
        (function initParticles() {
            const canvas = document.getElementById('particle-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const setW = () => canvas.width  = window.innerWidth;
            const setH = () => canvas.height = window.innerHeight;
            setW(); setH();

            const count = window.innerWidth < 480 ? 40 : 70;
            const particles = Array.from({ length: count }, () => ({
                x:  Math.random() * canvas.width,
                y:  Math.random() * canvas.height,
                r:  Math.random() * 1.4 + 0.4,
                dx: (Math.random() - 0.5) * 0.35,
                dy: (Math.random() - 0.5) * 0.35,
                o:  Math.random() * 0.4 + 0.1,
            }));

            let raf;
            function draw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                particles.forEach(p => {
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(200,220,255,${p.o})`;
                    ctx.fill();
                    p.x += p.dx;
                    p.y += p.dy;
                    if (p.x < 0 || p.x > canvas.width)  p.dx *= -1;
                    if (p.y < 0 || p.y > canvas.height) p.dy *= -1;
                });
                raf = requestAnimationFrame(draw);
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { cancelAnimationFrame(raf); }
                else { raf = requestAnimationFrame(draw); }
            });

            window.addEventListener('resize', () => { setW(); setH(); }, { passive: true });
            draw();
        })();

        /* ── Password Toggle ── */
        (function initPasswordToggle() {
            const btn    = document.getElementById('togglePwBtn');
            const inp    = document.getElementById('passwordInput');
            const eyeOn  = document.getElementById('pwEye');
            const eyeOff = document.getElementById('pwEyeOff');
            if (!btn || !inp || !eyeOn || !eyeOff) return;

            btn.addEventListener('click', function () {
                const isHidden = inp.type === 'password';
                inp.type           = isHidden ? 'text' : 'password';
                eyeOn.style.display  = isHidden ? 'none'  : '';
                eyeOff.style.display = isHidden ? ''      : 'none';
                btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                inp.focus();
            });

            /* รีเซ็ต type กลับเป็น password ก่อน submit
               เพื่อให้ browser รู้ว่าเป็น password field และเสนอบันทึก */
            const form = inp.closest('form');
            if (form) {
                form.addEventListener('submit', function () {
                    inp.type = 'password';
                });
            }
        })();

    });
    </script>
</body>
</html>

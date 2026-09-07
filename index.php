<?php

declare(strict_types=1);

/**
 * Application Entry Point - Splash / Loader Page
 *
 * @package    DPMP Rayong Schedule System
 * @version    See changelog table
 * @author     DPMP Rayong IT Department
 * @license    Proprietary
 */

// ─── Security Headers ────────────────────────────────────────────────────────
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
// CSP header is sent after $nonce is created below
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// ─── Bootstrap ───────────────────────────────────────────────────────────────
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
    . "script-src 'self' 'nonce-{$nonce}'; "
    . "style-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; "
    . "font-src https://fonts.gstatic.com; "
    . "img-src 'self' data:; "
    . "media-src 'self'; "
    . "connect-src 'self';");

// ─── Config & DB ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';  // Must define $connect (mysqli)

// ─── Cache Control ───────────────────────────────────────────────────────────
$cacheKey  = 'app_version';
$cacheTTL  = 300; // seconds
$cacheFile = sys_get_temp_dir() . '/dpmp_' . md5($cacheKey) . '.cache';

$versionText = 'ไม่พบเวอร์ชัน';

// Simple file-based cache to avoid repeated DB hits on every page load
if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $versionText = file_get_contents($cacheFile) ?: $versionText;
} else {
    try {
        if (!isset($connect) || !($connect instanceof mysqli)) {
            throw new RuntimeException('Database connection unavailable.');
        }

        $stmt = $connect->prepare(
            'SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1'
        );

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $connect->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $raw = (string) ($row['version'] ?? '');

            if ($raw !== '') {
                $versionText = 'เวอร์ชัน ' . htmlspecialchars($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                file_put_contents($cacheFile, $versionText, LOCK_EX);
            }
        }

        $stmt->close();
    } catch (RuntimeException $e) {
        // Log error without exposing internals to client
        error_log('[DPMP][index] Version fetch failed: ' . $e->getMessage());
    } finally {
        if (isset($connect) && $connect instanceof mysqli) {
            $connect->close();
        }
    }
}

// ─── Detect User-Agent Server-Side (progressive enhancement) ─────────────────
$ua       = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isMobile = (bool) preg_match('/iphone|android.*mobile|windows phone/', $ua);
$isTablet = (bool) preg_match('/ipad|tablet/', $ua);
$deviceHint = ($isMobile || $isTablet) ? 'mobile' : 'desktop';

?>
<!DOCTYPE html>
<html lang="th" dir="ltr" data-device="<?= htmlspecialchars($deviceHint, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Primary Meta -->
    <title>ปฏิทินและนัดหมายงาน — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</title>
    <meta name="description" content="ระบบปฏิทินและจัดการนัดหมาย — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a1628">

    <!-- Open Graph -->
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="https://schedule.dpmpryg.online/">
    <meta property="og:title"       content="ปฏิทินและนัดหมายงาน">
    <meta property="og:description" content="ระบบปฏิทินและจัดการนัดหมาย — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง">
    <meta property="og:image"       content="https://schedule.dpmpryg.online/images/preview.jpg">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="ปฏิทินและนัดหมายงาน">
    <meta name="twitter:description" content="ระบบปฏิทินและจัดการนัดหมาย — สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง">
    <meta name="twitter:image"       content="https://schedule.dpmpryg.online/images/preview.jpg">

    <!-- Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload"    href="/images/logo.webp" as="image" type="image/webp">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
          integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkA=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">

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
            --clr-border:      rgba(255, 255, 255, 0.10);
            --clr-text:        #f0f4ff;
            --clr-text-muted:  rgba(240, 244, 255, 0.55);
            --clr-danger:      #e05252;
            --clr-success:     #3ec97e;

            --radius-card:    24px;
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
        .orb--blue  { width: 480px; height: 480px; background: rgba(30, 111, 217, 0.18); top: -120px; left: -80px; --dur: 22s; }
        .orb--gold  { width: 360px; height: 360px; background: rgba(232, 160, 32, 0.14);  bottom: -80px; right: -60px; --dur: 18s; animation-direction: reverse; }
        .orb--red   { width: 280px; height: 280px; background: rgba(224, 82, 82, 0.10);   top: 60%;  left: 60%; --dur: 25s; }

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

        /* ─── Splash Card ───────────────────────────────────────────── */
        .splash {
            position: relative;
            z-index: 10;
            width: min(480px, calc(100vw - 32px));
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-card);
            backdrop-filter: blur(24px) saturate(1.5);
            -webkit-backdrop-filter: blur(24px) saturate(1.5);
            box-shadow: var(--shadow-card);
            padding: 48px 40px 40px;
            text-align: center;
            animation: splashIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes splashIn {
            from { opacity: 0; transform: translateY(32px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        /* Inner top highlight border */
        .splash::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--radius-card);
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        /* ─── Logo Area ─────────────────────────────────────────────── */
        .logo-ring {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 28px;
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
            background: rgba(255, 255, 255, 0.06);
            padding: 12px;
            animation: logoPulse 4s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }

        @keyframes logoPulse {
            0%, 100% { filter: drop-shadow(0 0 12px rgba(30, 111, 217, 0.5)); transform: scale(1); }
            50%       { filter: drop-shadow(0 0 24px rgba(232, 160, 32, 0.6)); transform: scale(1.04); }
        }

        /* ─── Text ──────────────────────────────────────────────────── */
        .dept-name {
            font-size: clamp(14px, 3.8vw, 17px);
            font-weight: 400;
            line-height: 1.6;
            color: var(--clr-text-muted);
            margin-bottom: 10px;
            letter-spacing: 0.2px;
        }

        .sys-title {
            font-size: clamp(20px, 5.5vw, 26px);
            font-weight: 700;
            line-height: 1.3;
            background: linear-gradient(120deg, #ffffff 30%, var(--clr-accent) 80%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 32px;
        }

        /* ─── Loading Dots / Text ───────────────────────────────────── */
        .loader-letters {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            height: 44px;
            margin-bottom: 28px;
        }

        .loader-letters__char {
            display: inline-block;
            font-size: 22px;
            font-weight: 700;
            color: var(--clr-text-muted);
            letter-spacing: 2px;
            animation: charBounce 1.6s ease-in-out infinite;
            animation-delay: calc(var(--i) * 0.10s);
        }

        @keyframes charBounce {
            0%, 60%, 100% { transform: translateY(0); color: var(--clr-text-muted); }
            30% {
                transform: translateY(-14px);
                color: var(--clr-accent);
                text-shadow: 0 4px 16px var(--clr-accent);
            }
        }

        /* ─── Progress ──────────────────────────────────────────────── */
        .progress-wrap {
            margin-bottom: 28px;
        }

        .progress-track {
            height: 6px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-pill);
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            border-radius: var(--radius-pill);
            background: linear-gradient(90deg, var(--clr-blue), #38c2ff, var(--clr-accent));
            background-size: 200% 100%;
            animation: shimmer 2s linear infinite;
            transition: width 0.15s ease;
            box-shadow: 0 0 12px var(--clr-blue-glow);
        }

        @keyframes shimmer {
            0%   { background-position: 200% center; }
            100% { background-position: -200% center; }
        }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .progress-meta__label {
            font-size: 12px;
            font-weight: 500;
            color: var(--clr-text-muted);
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .progress-meta__pct {
            font-size: 13px;
            font-weight: 600;
            color: var(--clr-accent);
            font-variant-numeric: tabular-nums;
            transition: color 0.2s;
        }



        /* ─── Version Badge ─────────────────────────────────────────── */
        .version-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--clr-text-muted);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-pill);
            padding: 6px 14px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }

        .version-badge:hover {
            background: var(--clr-accent-dim);
            border-color: rgba(232, 160, 32, 0.30);
            color: var(--clr-accent);
            transform: translateY(-1px);
        }

        .version-badge:focus-visible {
            outline: 2px solid var(--clr-accent);
            outline-offset: 3px;
        }

        /* ─── Status Strip ──────────────────────────────────────────── */
        .status-strip {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--clr-border);
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

        /* ─── Toast Notification ────────────────────────────────────── */
        .toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: rgba(20, 30, 50, 0.95);
            border: 1px solid var(--clr-border);
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--clr-text);
            backdrop-filter: blur(12px);
            z-index: 9999;
            transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.4s;
            opacity: 0;
            white-space: nowrap;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        /* ─── Accessibility ─────────────────────────────────────────── */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* ─── Responsive ────────────────────────────────────────────── */
        @media (max-width: 380px) {
            .splash { padding: 36px 24px 32px; }
            .logo-ring { width: 100px; height: 100px; }
            .sys-title { margin-bottom: 24px; }
        }

        @media (prefers-color-scheme: light) {
            /* System respects dark theme; override if needed */
        }
    </style>
</head>

<body>
    <!-- Ambient Orbs -->
    <div class="orb orb--blue"  aria-hidden="true"></div>
    <div class="orb orb--gold"  aria-hidden="true"></div>
    <div class="orb orb--red"   aria-hidden="true"></div>

    <!-- Particle Canvas -->
    <canvas id="particle-canvas" aria-hidden="true"></canvas>

    <!-- ─── Splash Card ─────────────────────────────────────────── -->
    <main class="splash" role="main" aria-label="หน้าโหลดระบบ">

        <!-- SVG Gradient Defs (inline for CSP compliance) -->
        <svg width="0" height="0" style="position:absolute" aria-hidden="true">
            <defs>
                <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%"   stop-color="#1e6fd9"/>
                    <stop offset="50%"  stop-color="#e8a020"/>
                    <stop offset="100%" stop-color="#1e6fd9"/>
                </linearGradient>
            </defs>
        </svg>

        <!-- Logo -->
        <div class="logo-ring" aria-hidden="true">
            <svg class="logo-ring__svg" viewBox="0 0 136 136" xmlns="http://www.w3.org/2000/svg">
                <circle cx="68" cy="68" r="64"/>
            </svg>
            <img class="logo-ring__img"
                 src="/images/logo.webp"
                 alt="โลโก้สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง"
                 width="120"
                 height="120"
                 loading="eager"
                 decoding="async">
        </div>

        <!-- Org / System Name -->
        <p class="dept-name">
            สำนักงานป้องกันและบรรเทาสาธารณภัย<br>จังหวัดระยอง
        </p>
        <h1 class="sys-title">ระบบปฏิทินและจัดการนัดหมาย</h1>

        <!-- Loading Letters -->
        <div class="loader-letters" role="status" aria-live="polite" aria-label="กำลังโหลด">
            <?php
                $letters = str_split('LOADING');
                foreach ($letters as $idx => $char) {
                    $i = $idx + 1;
                    echo "<span class=\"loader-letters__char\" style=\"--i:{$i}\" aria-hidden=\"true\">"
                       . htmlspecialchars($char, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                       . "</span>";
                }
            ?>
            <span class="sr-only">กำลังโหลดระบบ กรุณารอสักครู่</span>
        </div>

        <!-- Progress Bar -->
        <div class="progress-wrap" aria-hidden="true">
            <div class="progress-track">
                <div class="progress-fill" id="js-progress" role="progressbar"
                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                     style="width:0%"></div>
            </div>
            <div class="progress-meta">
                <span class="progress-meta__label">
                    <i class="fa-solid fa-shield-halved" style="font-size:11px"></i>
                    กำลังตรวจสอบสิทธิ์
                </span>
                <span class="progress-meta__pct" id="js-pct">0%</span>
            </div>
        </div>



        <!-- Version + Status -->
        <a href="/changelog.php"
           class="version-badge"
           aria-label="<?= htmlspecialchars($versionText, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> — ดูรายละเอียด">
            <i class="fa-solid fa-tag" style="font-size:11px" aria-hidden="true"></i>
            <?= htmlspecialchars($versionText, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
        </a>

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
    </main>

    <!-- Toast -->
    <div id="js-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true"></div>

    <!-- Hidden audio -->
    <audio id="js-ding" src="/sounds/ding.mp3" preload="auto" aria-hidden="true"></audio>

    <!-- ─── Scripts ────────────────────────────────────────────── -->
    <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
    (function () {
        'use strict';

        /* ── Server hint injected by PHP ── */
        const DEVICE_HINT = document.documentElement.dataset.device || 'desktop';

        /* ── Particle System ── */
        (function initParticles() {
            const canvas  = document.getElementById('particle-canvas');
            if (!canvas) return;
            const ctx     = canvas.getContext('2d');
            const W       = () => canvas.width  = window.innerWidth;
            const H       = () => canvas.height = window.innerHeight;
            W(); H();

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

            // Pause when tab hidden — save CPU
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { cancelAnimationFrame(raf); }
                else { raf = requestAnimationFrame(draw); }
            });

            window.addEventListener('resize', () => { W(); H(); }, { passive: true });
            draw();
        })();

        /* ── Progress Loader ── */
        const progressEl = document.getElementById('js-progress');
        const pctEl      = document.getElementById('js-pct');
        let   pct        = 0;
        let   ticker;

        function updateProgress(val) {
            pct = Math.min(100, val);
            if (progressEl) {
                progressEl.style.width = pct + '%';
                progressEl.setAttribute('aria-valuenow', pct);
            }
            if (pctEl) pctEl.textContent = pct + '%';
        }

        function startProgress() {
            ticker = setInterval(() => {
                const step = Math.floor(Math.random() * 8) + 4;
                updateProgress(pct + step);
                if (pct >= 100) {
                    clearInterval(ticker);
                    onLoadComplete();
                }
            }, 140);
        }

        /* ── Redirect Logic ── */
        function resolveTarget() {
            // JS UA check as a secondary layer; PHP hint is primary
            const ua       = navigator.userAgent.toLowerCase();
            const jsMobile = /iphone|android.*mobile|windows phone|ipad|tablet/.test(ua);
            const hint     = DEVICE_HINT;
            return (hint === 'mobile' || jsMobile) ? '/mobile' : '/desktop';
        }

        function navigate() {
            window.location.href = resolveTarget();
        }

        /* ── Completion ── */
        function onLoadComplete() {
            try {
                const audio = document.getElementById('js-ding');
                if (audio) audio.play().catch(() => { /* autoplay blocked — ignore */ });
            } catch (_) {}

            if ('vibrate' in navigator) {
                navigator.vibrate([60, 30, 60]);
            }

            showToast('✓ โหลดเสร็จสมบูรณ์ — กำลังเปลี่ยนหน้า…');

            setTimeout(navigate, 600);
        }

        /* ── Toast ── */
        function showToast(msg, duration) {
            const t = document.getElementById('js-toast');
            if (!t) return;
            t.textContent = msg;
            t.classList.add('show');
            setTimeout(() => { t.classList.remove('show'); }, duration || 3000);
        }

        /* ── Keyboard Shortcut (Enter) ── */
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.defaultPrevented) {
                clearInterval(ticker);
                navigate();
            }
        });

        /* ── Start ── */
        startProgress();

    })();
    </script>
</body>
</html>

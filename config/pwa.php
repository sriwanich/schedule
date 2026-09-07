<?php

declare(strict_types=1);

/**
 * PWA & Web Push Configuration
 * ─────────────────────────────────────────────────────────
 * สร้าง VAPID keys ด้วยคำสั่ง:
 *   php vendor/bin/web-push generate-vapid-keys
 * หรือ:
 *   node -e "const wp=require('web-push'); const k=wp.generateVAPIDKeys(); console.log(JSON.stringify(k))"
 */

return [

    // ── VAPID (Voluntary Application Server Identification) ──────────────
    'vapid' => [
        'subject'     => env('VAPID_SUBJECT',    'mailto:admin@your-domain.com'),
        'public_key'  => env('VAPID_PUBLIC_KEY',  ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    // ── Web Push Defaults ─────────────────────────────────────────────────
    'push' => [
        'ttl'           => (int)env('PUSH_TTL', 86400),   // ความอยู่รอดของ message บน push server (วินาที)
        'urgency'       => env('PUSH_URGENCY', 'normal'),  // very-low | low | normal | high
        'batch_size'    => (int)env('PUSH_BATCH_SIZE', 50), // ส่งสูงสุดกี่ subscription ต่อ batch
        'timeout'       => (int)env('PUSH_TIMEOUT', 30),   // curl timeout
    ],

    // ── Notification Advance Reminders (นาทีล่วงหน้า) ────────────────────
    'reminder_minutes' => [15, 60, 1440],   // 15 นาที, 1 ชั่วโมง, 1 วัน

    // ── PWA Manifest ─────────────────────────────────────────────────────
    'manifest' => [
        'name'             => env('APP_NAME', 'ระบบตารางงาน'),
        'short_name'       => env('PWA_SHORT_NAME', 'ตารางงาน'),
        'description'      => 'ระบบบริหารจัดการตารางงานและนัดหมาย',
        'start_url'        => '/',
        'display'          => 'standalone',       // fullscreen | standalone | minimal-ui | browser
        'orientation'      => 'portrait-primary',
        'background_color' => '#ffffff',
        'theme_color'      => '#1a56db',
        'lang'             => 'th',
        'scope'            => '/',
        'categories'       => ['productivity', 'utilities'],
        'icons'            => [
            ['src' => '/images/icon-72.png',   'sizes' => '72x72',   'type' => 'image/png'],
            ['src' => '/images/icon-96.png',   'sizes' => '96x96',   'type' => 'image/png'],
            ['src' => '/images/icon-128.png',  'sizes' => '128x128', 'type' => 'image/png'],
            ['src' => '/images/icon-144.png',  'sizes' => '144x144', 'type' => 'image/png'],
            ['src' => '/images/icon-152.png',  'sizes' => '152x152', 'type' => 'image/png'],
            ['src' => '/images/icon-192.png',  'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => '/images/icon-384.png',  'sizes' => '384x384', 'type' => 'image/png'],
            ['src' => '/images/icon-512.png',  'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
        'shortcuts' => [
            [
                'name'       => 'สร้างนัดหมายใหม่',
                'short_name' => 'เพิ่มงาน',
                'url'        => '/form',
                'icons'      => [['src' => '/images/shortcut-add.png', 'sizes' => '96x96']],
            ],
            [
                'name'       => 'ปฏิทิน',
                'short_name' => 'ปฏิทิน',
                'url'        => '/calendar',
                'icons'      => [['src' => '/images/shortcut-cal.png', 'sizes' => '96x96']],
            ],
        ],
    ],

    // ── Service Worker Cache ──────────────────────────────────────────────
    'cache' => [
        'version'        => env('SW_CACHE_VERSION', 'v1'),
        'offline_page'   => '/offline.html',
        'precache_urls'  => [
            '/',
            '/calendar',
            '/offline.html',
            '/assets/css/app.css',
            '/assets/js/app.js',
            '/assets/js/notifications.js',
        ],
        'runtime_cache'  => [
            '/api/events',
            '/api/holidays',
        ],
    ],

];
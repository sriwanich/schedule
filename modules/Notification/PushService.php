<?php

declare(strict_types=1);

namespace Modules\Notification;

use App\Core\Database;

/**
 * PushService
 * ─────────────────────────────────────────────────────────
 * VAPID Web Push wrapper
 * ต้องการ package: composer require minishlink/web-push
 *
 * ฟีเจอร์:
 *   • Subscribe / Unsubscribe endpoint
 *   • Send push notification แบบ single & batch
 *   • Auto-remove expired/invalid subscriptions
 *   • Reminder logic สำหรับ events ที่ใกล้ถึง
 */
class PushService
{
    private const TABLE = 'push_subscriptions';

    /** @var \Minishlink\WebPush\WebPush|null */
    private mixed $webPush = null;

    public function __construct(private Database $db)
    {
        $this->initWebPush();
    }

    // ── Subscription Management ──────────────────────────────────────────

    /**
     * บันทึก subscription ใหม่จาก browser
     *
     * @param array $subscription  { endpoint, keys: { auth, p256dh } }
     * @param int   $userId
     */
    public function subscribe(array $subscription, int $userId): bool
    {
        if (!$this->isValidSubscription($subscription)) return false;

        $endpoint = $subscription['endpoint'];

        // Upsert (ถ้า endpoint ซ้ำ update แทน)
        $existing = $this->db->selectOne(
            "SELECT id FROM " . self::TABLE . " WHERE endpoint = ?",
            [$endpoint]
        );

        if ($existing) {
            $this->db->query(
                "UPDATE " . self::TABLE . "
                 SET user_id = ?, auth = ?, p256dh = ?, is_active = 1, updated_at = NOW()
                 WHERE endpoint = ?",
                [$userId, $subscription['keys']['auth'], $subscription['keys']['p256dh'], $endpoint]
            );
        } else {
            $this->db->query(
                "INSERT INTO " . self::TABLE . "
                    (user_id, endpoint, auth, p256dh, user_agent, is_active, created_at, updated_at)
                 VALUES (?,?,?,?,?,1,NOW(),NOW())",
                [
                    $userId,
                    $endpoint,
                    $subscription['keys']['auth'],
                    $subscription['keys']['p256dh'],
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]
            );
        }

        return true;
    }

    /**
     * ยกเลิก subscription
     */
    public function unsubscribe(string $endpoint, int $userId): bool
    {
        $this->db->query(
            "UPDATE " . self::TABLE . " SET is_active = 0, updated_at = NOW()
             WHERE endpoint = ? AND user_id = ?",
            [$endpoint, $userId]
        );
        return true;
    }

    /**
     * ตรวจว่า user นี้มี subscription ที่ active ไหม
     */
    public function isSubscribed(int $userId): bool
    {
        return (bool)$this->db->selectOne(
            "SELECT id FROM " . self::TABLE . " WHERE user_id = ? AND is_active = 1 LIMIT 1",
            [$userId]
        );
    }

    // ── Send Notifications ────────────────────────────────────────────────

    /**
     * ส่ง notification ให้ user คนเดียว
     */
    public function sendToUser(int $userId, array $payload): array
    {
        $subs = $this->db->select(
            "SELECT * FROM " . self::TABLE . " WHERE user_id = ? AND is_active = 1",
            [$userId]
        );
        return $this->sendToSubscriptions($subs, $payload);
    }

    /**
     * ส่ง notification ให้ทุก user
     */
    public function broadcast(array $payload): array
    {
        $subs = $this->db->select(
            "SELECT * FROM " . self::TABLE . " WHERE is_active = 1"
        );
        return $this->sendToSubscriptions($subs, $payload);
    }

    /**
     * ส่ง event reminder — เรียกจาก cron job
     * ตรวจ events ที่อีก $minutesBefore นาทีจะถึง
     */
    public function sendEventReminders(int $minutesBefore = 15): int
    {
        $sql = "SELECT e.id, e.title, e.event_date, e.event_time, e.location,
                       ps.user_id, ps.endpoint, ps.auth, ps.p256dh
                FROM events e
                JOIN push_subscriptions ps ON ps.user_id = e.created_by
                WHERE e.deleted_at IS NULL
                  AND e.is_notified = 0
                  AND e.notification_enabled = 1
                  AND ps.is_active = 1
                  AND CONCAT(e.event_date, ' ', COALESCE(e.event_time, '00:00:00'))
                      BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)";

        $rows  = $this->db->select($sql, [$minutesBefore]);
        $count = 0;

        foreach ($rows as $row) {
            $dateStr = thai_date_format($row['event_date']);
            $timeStr = $row['event_time'] ? substr($row['event_time'], 0, 5) . ' น.' : '';

            $payload = [
                'title'   => '🔔 แจ้งเตือนนัดหมาย',
                'body'    => $row['title'] . "\n{$dateStr}" . ($timeStr ? " เวลา {$timeStr}" : ''),
                'icon'    => '/images/icon-192.png',
                'badge'   => '/images/badge-72.png',
                'url'     => '/preview?id=' . $row['id'],
                'tag'     => 'event-' . $row['id'],
                'data'    => ['event_id' => $row['id']],
            ];

            $sub    = ['endpoint' => $row['endpoint'], 'keys' => ['auth' => $row['auth'], 'p256dh' => $row['p256dh']]];
            $result = $this->sendToSubscriptions([$sub], $payload);

            if (!empty($result['sent'])) {
                $this->db->query(
                    "UPDATE events SET is_notified = 1, updated_at = NOW() WHERE id = ?",
                    [$row['id']]
                );
                $count++;
            }
        }

        return $count;
    }

    // ── Low-level Send ────────────────────────────────────────────────────

    private function sendToSubscriptions(array $subs, array $payload): array
    {
        if (!$this->webPush || empty($subs)) {
            return ['sent' => 0, 'failed' => 0, 'errors' => []];
        }

        $json          = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $config        = config('pwa.push');
        $sent          = 0;
        $failed        = 0;
        $errors        = [];
        $invalidEndpoints = [];

        foreach ($subs as $sub) {
            try {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint'        => $sub['endpoint'],
                    'contentEncoding' => 'aesgcm',
                    'keys'            => [
                        'auth'   => $sub['auth'],
                        'p256dh' => $sub['p256dh'],
                    ],
                ]);
                $this->webPush->queueNotification($subscription, $json);
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        // Flush
        foreach ($this->webPush->flush($config['batch_size'] ?? 50) as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $report->getReason();
                // 410 Gone = subscription หมดอายุ ต้องลบ
                if ($report->getResponse()?->getStatusCode() === 410) {
                    $invalidEndpoints[] = $report->getRequest()->getUri()->__toString();
                }
            }
        }

        // ลบ subscriptions ที่ไม่ valid แล้ว
        foreach ($invalidEndpoints as $ep) {
            $this->db->query(
                "UPDATE " . self::TABLE . " SET is_active = 0 WHERE endpoint = ?",
                [$ep]
            );
        }

        return compact('sent', 'failed', 'errors');
    }

    // ── Init ─────────────────────────────────────────────────────────────

    private function initWebPush(): void
    {
        if (!class_exists('\Minishlink\WebPush\WebPush')) return;

        $vapid = config('pwa.vapid');
        if (empty($vapid['public_key']) || empty($vapid['private_key'])) return;

        try {
            $this->webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject'    => $vapid['subject'],
                    'publicKey'  => $vapid['public_key'],
                    'privateKey' => $vapid['private_key'],
                ],
            ], [
                'TTL'     => config('pwa.push.ttl', 86400),
                'urgency' => config('pwa.push.urgency', 'normal'),
                'timeout' => config('pwa.push.timeout', 30),
            ]);
        } catch (\Throwable $e) {
            log_error('WebPush init failed', ['error' => $e->getMessage()]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function isValidSubscription(array $sub): bool
    {
        return !empty($sub['endpoint'])
            && !empty($sub['keys']['auth'])
            && !empty($sub['keys']['p256dh'])
            && filter_var($sub['endpoint'], FILTER_VALIDATE_URL);
    }
}
<?php

declare(strict_types=1);

namespace Modules\GoogleCalendar;

use App\Core\Database;

/**
 * GoogleCalendarService
 * ─────────────────────────────────────────────────────────
 * OAuth2 + Bidirectional sync กับ Google Calendar API
 * ต้องการ: composer require google/apiclient
 *
 * Flow:
 *   1. getAuthUrl()          → redirect ไปยัง Google OAuth consent screen
 *   2. handleCallback()      → แลก code เป็น tokens แล้วบันทึก
 *   3. createEvent()         → สร้าง event บน Google Calendar
 *   4. updateEvent()         → อัพเดท event
 *   5. deleteEvent()         → ลบ event
 *   6. syncFromGoogle()      → Pull events จาก Google มาอัพเดท local
 */
class GoogleCalendarService
{
    private const SCOPES = [\Google\Service\Calendar::CALENDAR];
    private const TABLE_TOKENS = 'google_calendar_tokens';

    private ?\Google\Client $client = null;

    public function __construct(private Database $db)
    {
        $this->initClient();
    }

    // ── OAuth2 Flow ──────────────────────────────────────────────────────

    /**
     * ดึง URL สำหรับ redirect ผู้ใช้ไปยัง Google consent screen
     */
    public function getAuthUrl(int $userId): string
    {
        if (!$this->client) throw new \RuntimeException('Google API Client ไม่พร้อมใช้งาน');

        $this->client->setState(base64_encode(json_encode([
            'user_id' => $userId,
            'csrf'    => bin2hex(random_bytes(16)),
        ])));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');   // บังคับขอ refresh_token ทุกครั้ง

        return $this->client->createAuthUrl();
    }

    /**
     * แลก authorization code เป็น tokens แล้วบันทึก
     */
    public function handleCallback(string $code, string $state): array
    {
        if (!$this->client) return ['success' => false, 'message' => 'Google API Client ไม่พร้อม'];

        try {
            $stateData = json_decode(base64_decode($state), true);
            $userId    = (int)($stateData['user_id'] ?? 0);

            if (!$userId) {
                return ['success' => false, 'message' => 'State ไม่ถูกต้อง'];
            }

            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            if (isset($token['error'])) {
                return ['success' => false, 'message' => 'รับ token ไม่สำเร็จ: ' . $token['error_description']];
            }

            $this->saveToken($userId, $token);
            log_activity('google_calendar_connected', 'google_calendar', $userId);

            return ['success' => true, 'message' => 'เชื่อมต่อ Google Calendar สำเร็จ'];
        } catch (\Throwable $e) {
            log_error('Google OAuth callback failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อ'];
        }
    }

    /**
     * ยกเลิกการเชื่อมต่อ
     */
    public function disconnect(int $userId): void
    {
        $token = $this->loadToken($userId);
        if ($token && $this->client) {
            try { $this->client->revokeToken($token['access_token']); } catch (\Throwable) {}
        }
        $this->db->query("DELETE FROM " . self::TABLE_TOKENS . " WHERE user_id = ?", [$userId]);
        log_activity('google_calendar_disconnected', 'google_calendar', $userId);
    }

    /**
     * ตรวจว่า user นี้ connect แล้วหรือยัง
     */
    public function isConnected(int $userId): bool
    {
        return (bool)$this->db->selectOne(
            "SELECT id FROM " . self::TABLE_TOKENS . " WHERE user_id = ?",
            [$userId]
        );
    }

    // ── CRUD on Google Calendar ──────────────────────────────────────────

    /**
     * สร้าง event บน Google Calendar และ return Google event ID
     */
    public function createEvent(array $event, int $userId = 0): ?string
    {
        $service = $this->getCalendarService($userId ?: ($event['created_by'] ?? 0));
        if (!$service) return null;

        try {
            $gEvent  = $this->toGoogleEvent($event);
            $calId   = $this->getCalendarId($event['created_by'] ?? 0);
            $created = $service->events->insert($calId, $gEvent);
            return $created->getId();
        } catch (\Throwable $e) {
            log_error('Google Calendar createEvent failed', ['error' => $e->getMessage(), 'event_id' => $event['id'] ?? null]);
            return null;
        }
    }

    /**
     * อัพเดท event บน Google Calendar
     */
    public function updateEvent(string $googleEventId, array $event, int $userId = 0): bool
    {
        $service = $this->getCalendarService($userId ?: ($event['created_by'] ?? 0));
        if (!$service) return false;

        try {
            $calId  = $this->getCalendarId($event['created_by'] ?? 0);
            $gEvent = $service->events->get($calId, $googleEventId);
            $this->fillGoogleEvent($gEvent, $event);
            $service->events->update($calId, $googleEventId, $gEvent);
            return true;
        } catch (\Throwable $e) {
            log_error('Google Calendar updateEvent failed', ['error' => $e->getMessage(), 'gcal_id' => $googleEventId]);
            return false;
        }
    }

    /**
     * ลบ event บน Google Calendar
     */
    public function deleteEvent(string $googleEventId, int $userId = 0): bool
    {
        $service = $this->getCalendarService($userId);
        if (!$service) return false;

        try {
            $calId = $this->getCalendarId($userId);
            $service->events->delete($calId, $googleEventId);
            return true;
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 410) return true; // Already deleted
            log_error('Google Calendar deleteEvent failed', ['error' => $e->getMessage()]);
            return false;
        } catch (\Throwable $e) {
            log_error('Google Calendar deleteEvent failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Pull events จาก Google Calendar มาอัพเดท local (bidirectional sync)
     * คืน array ของ events ที่อัพเดทหรือสร้างใหม่
     */
    public function syncFromGoogle(int $userId, string $timeMin = '', string $timeMax = ''): array
    {
        $service = $this->getCalendarService($userId);
        if (!$service) return [];

        $calId   = $this->getCalendarId($userId);
        $params  = [
            'singleEvents' => true,
            'orderBy'      => 'startTime',
            'timeMin'      => $timeMin ?: date('c', strtotime('-1 month')),
            'timeMax'      => $timeMax ?: date('c', strtotime('+3 months')),
            'maxResults'   => 250,
        ];

        try {
            $results = $service->events->listEvents($calId, $params);
            $synced  = [];

            foreach ($results->getItems() as $gEvent) {
                $local = $this->db->selectOne(
                    "SELECT id, updated_at FROM events WHERE google_calendar_id = ? AND deleted_at IS NULL",
                    [$gEvent->getId()]
                );

                if (!$local) {
                    // สร้าง event ใหม่จาก Google
                    $synced[] = ['action' => 'created', 'google_id' => $gEvent->getId(), 'title' => $gEvent->getSummary()];
                } else {
                    // ตรวจว่า Google version ใหม่กว่า local หรือไม่
                    $googleUpdated = $gEvent->getUpdated();
                    if ($googleUpdated && strtotime($googleUpdated) > strtotime($local['updated_at'])) {
                        $this->updateLocalFromGoogle($local['id'], $gEvent);
                        $synced[] = ['action' => 'updated', 'local_id' => $local['id'], 'title' => $gEvent->getSummary()];
                    }
                }
            }

            return $synced;
        } catch (\Throwable $e) {
            log_error('Google Calendar syncFromGoogle failed', ['error' => $e->getMessage(), 'user_id' => $userId]);
            return [];
        }
    }

    // ── Private Helpers ──────────────────────────────────────────────────

    private function initClient(): void
    {
        if (!class_exists('\Google\Client')) return;

        $clientId     = env('GOOGLE_CLIENT_ID', '');
        $clientSecret = env('GOOGLE_CLIENT_SECRET', '');
        if (empty($clientId) || empty($clientSecret)) return;

        $this->client = new \Google\Client();
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI', url('/google-calendar/callback')));
        $this->client->setScopes(self::SCOPES);
        $this->client->setAccessType('offline');
    }

    private function getCalendarService(int $userId): ?\Google\Service\Calendar
    {
        if (!$this->client) return null;

        $token = $this->loadToken($userId);
        if (!$token) return null;

        $this->client->setAccessToken($token);

        // Auto-refresh token ถ้าหมดอายุ
        if ($this->client->isAccessTokenExpired()) {
            if (!$this->client->getRefreshToken()) return null;
            $newToken = $this->client->fetchAccessTokenWithRefreshToken();
            $this->saveToken($userId, $newToken);
        }

        return new \Google\Service\Calendar($this->client);
    }

    private function getCalendarId(int $userId): string
    {
        $row = $this->db->selectOne(
            "SELECT calendar_id FROM " . self::TABLE_TOKENS . " WHERE user_id = ?",
            [$userId]
        );
        return $row['calendar_id'] ?? 'primary';
    }

    private function saveToken(int $userId, array $token): void
    {
        $json    = json_encode($token);
        $calId   = $token['calendar_id'] ?? 'primary';
        $expires = isset($token['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int)$token['expires_in'])
            : null;

        $existing = $this->db->selectOne(
            "SELECT id FROM " . self::TABLE_TOKENS . " WHERE user_id = ?",
            [$userId]
        );

        if ($existing) {
            $this->db->query(
                "UPDATE " . self::TABLE_TOKENS . "
                 SET token_json = ?, expires_at = ?, calendar_id = ?, updated_at = NOW()
                 WHERE user_id = ?",
                [$json, $expires, $calId, $userId]
            );
        } else {
            $this->db->query(
                "INSERT INTO " . self::TABLE_TOKENS . "
                    (user_id, token_json, expires_at, calendar_id, created_at, updated_at)
                 VALUES (?,?,?,?,NOW(),NOW())",
                [$userId, $json, $expires, $calId]
            );
        }
    }

    private function loadToken(int $userId): ?array
    {
        $row = $this->db->selectOne(
            "SELECT token_json FROM " . self::TABLE_TOKENS . " WHERE user_id = ?",
            [$userId]
        );
        return $row ? json_decode($row['token_json'], true) : null;
    }

    private function toGoogleEvent(array $event): \Google\Service\Calendar\Event
    {
        $gEvent = new \Google\Service\Calendar\Event();
        $this->fillGoogleEvent($gEvent, $event);
        return $gEvent;
    }

    private function fillGoogleEvent(\Google\Service\Calendar\Event $gEvent, array $event): void
    {
        $gEvent->setSummary($event['title'] ?? '');

        $desc = [];
        if (!empty($event['department_name'])) $desc[] = 'หน่วยงาน: ' . $event['department_name'];
        if (!empty($event['chairman_name']))   $desc[] = 'ประธาน: ' . $event['chairman_name'];
        if (!empty($event['dress_name']))      $desc[] = 'การแต่งกาย: ' . $event['dress_name'];
        if (!empty($event['description']))     $desc[] = $event['description'];
        if (!empty($desc)) $gEvent->setDescription(implode("\n", $desc));

        if (!empty($event['location'])) {
            $gEvent->setLocation($event['location']);
        }

        // Start
        $start = new \Google\Service\Calendar\EventDateTime();
        if (!empty($event['event_time'])) {
            $start->setDateTime($event['event_date'] . 'T' . $event['event_time']);
            $start->setTimeZone('Asia/Bangkok');
        } else {
            $start->setDate($event['event_date']);
        }
        $gEvent->setStart($start);

        // End
        $end = new \Google\Service\Calendar\EventDateTime();
        if (!empty($event['event_end_time'])) {
            $end->setDateTime($event['event_date'] . 'T' . $event['event_end_time']);
            $end->setTimeZone('Asia/Bangkok');
        } elseif (!empty($event['event_time'])) {
            $endDt = new \DateTime($event['event_date'] . ' ' . $event['event_time'], new \DateTimeZone('Asia/Bangkok'));
            $endDt->modify('+1 hour');
            $end->setDateTime($endDt->format(\DateTime::RFC3339));
            $end->setTimeZone('Asia/Bangkok');
        } else {
            $end->setDate(date('Y-m-d', strtotime($event['event_date'] . ' +1 day')));
        }
        $gEvent->setEnd($end);

        // Reminder
        $reminder    = new \Google\Service\Calendar\EventReminders();
        $notification = new \Google\Service\Calendar\EventReminder();
        $notification->setMethod('popup');
        $notification->setMinutes(15);
        $reminder->setUseDefault(false);
        $reminder->setOverrides([$notification]);
        $gEvent->setReminders($reminder);
    }

    private function updateLocalFromGoogle(int $localId, \Google\Service\Calendar\Event $gEvent): void
    {
        $start  = $gEvent->getStart();
        $end    = $gEvent->getEnd();
        $date   = $start->getDate() ?: substr($start->getDateTime(), 0, 10);
        $time   = $start->getDateTime() ? substr($start->getDateTime(), 11, 8) : null;
        $endTime = $end->getDateTime() ? substr($end->getDateTime(), 11, 8) : null;

        $this->db->query(
            "UPDATE events SET title = ?, event_date = ?, event_time = ?, event_end_time = ?, updated_at = NOW() WHERE id = ?",
            [$gEvent->getSummary(), $date, $time, $endTime, $localId]
        );
    }
}
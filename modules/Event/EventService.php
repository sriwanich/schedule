<?php

declare(strict_types=1);

namespace Modules\Event;

use App\Core\Database;
use Modules\Cache\CacheManager;
use Modules\GoogleCalendar\GoogleCalendarService;

/**
 * EventService
 * ─────────────────────────────────────────────────────────
 * Business logic ทั้งหมด รวม file upload validation
 * Repository รับผิดชอบ SQL, Service รับผิดชอบ business rules
 */
class EventService
{
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    private const MAX_FILE_SIZE  = 10 * 1024 * 1024;   // 10 MB
    private const MAX_FILES      = 5;
    private const UPLOAD_DIR     = 'uploads/events';

    public function __construct(
        private Database              $db,
        private EventRepository       $repo,
        private EventValidator        $validator,
        private CacheManager          $cache,
        private ?GoogleCalendarService $gcal = null
    ) {}

    // ── Create Event ─────────────────────────────────────────────────────

    public function create(array $data, array $files = [], int $userId = 0): array
    {
        // 1. Validate input
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 2. Validate & process uploads
        $uploadErrors = $this->validateFiles($files['attachments'] ?? []);
        if (!empty($uploadErrors)) {
            return ['success' => false, 'errors' => $uploadErrors];
        }

        return $this->db->transaction(function () use ($data, $files, $userId) {
            // Auto-save department ถ้าไม่มีในรายการ
            $data['department_id'] = $this->resolveOrCreateDepartment($data);
            $data['chairman_id']   = $this->resolveOrCreateChairman($data);

            $data['uuid']       = generate_uuid();
            $data['created_by'] = $userId;

            $eventId = $this->repo->create($data);

            // Sync persons
            if (!empty($data['person_ids'])) {
                $this->repo->syncPersons($eventId, (array)$data['person_ids']);
            }

            // Save attachments
            $this->saveAttachments($eventId, $files['attachments'] ?? []);

            // Google Calendar Sync
            if ($this->gcal && !empty($data['sync_google'])) {
                try {
                    $gcalId = $this->gcal->createEvent($this->repo->findWithRelations($eventId));
                    if ($gcalId) {
                        $this->repo->update($eventId, ['google_calendar_id' => $gcalId]);
                    }
                } catch (\Throwable $e) {
                    log_error('Google Calendar create failed', ['error' => $e->getMessage()]);
                }
            }

            // Bust cache
            $this->bustCache();

            log_activity('event_created', 'event', $eventId);

            return [
                'success'  => true,
                'event_id' => $eventId,
                'message'  => 'บันทึกนัดหมายสำเร็จ',
            ];
        });
    }

    // ── Update Event ─────────────────────────────────────────────────────

    public function update(int $id, array $data, array $files = [], int $userId = 0): array
    {
        $event = $this->repo->findWithRelations($id);
        if (!$event) {
            return ['success' => false, 'message' => 'ไม่พบนัดหมายนี้'];
        }

        $errors = $this->validator->validate($data, true);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $uploadErrors = $this->validateFiles($files['attachments'] ?? []);
        if (!empty($uploadErrors)) {
            return ['success' => false, 'errors' => $uploadErrors];
        }

        return $this->db->transaction(function () use ($id, $data, $files, $event) {
            $data['department_id'] = $this->resolveOrCreateDepartment($data);
            $data['chairman_id']   = $this->resolveOrCreateChairman($data);

            $this->repo->update($id, $data);

            if (array_key_exists('person_ids', $data)) {
                $this->repo->syncPersons($id, (array)($data['person_ids'] ?? []));
            }

            $this->saveAttachments($id, $files['attachments'] ?? []);

            // Google Calendar sync
            if ($this->gcal && !empty($event['google_calendar_id'])) {
                try {
                    $this->gcal->updateEvent(
                        $event['google_calendar_id'],
                        $this->repo->findWithRelations($id)
                    );
                } catch (\Throwable $e) {
                    log_error('Google Calendar update failed', ['error' => $e->getMessage()]);
                }
            }

            $this->bustCache();
            log_activity('event_updated', 'event', $id);

            return ['success' => true, 'message' => 'อัพเดทนัดหมายสำเร็จ'];
        });
    }

    // ── Delete Event ─────────────────────────────────────────────────────

    public function delete(int $id): array
    {
        $event = $this->repo->findWithRelations($id);
        if (!$event) {
            return ['success' => false, 'message' => 'ไม่พบนัดหมายนี้'];
        }

        // Google Calendar — ลบออกด้วย
        if ($this->gcal && !empty($event['google_calendar_id'])) {
            try {
                $this->gcal->deleteEvent($event['google_calendar_id']);
            } catch (\Throwable $e) {
                log_error('Google Calendar delete failed', ['error' => $e->getMessage()]);
            }
        }

        $this->repo->delete($id);
        $this->bustCache();

        log_activity('event_deleted', 'event', $id);
        return ['success' => true, 'message' => 'ลบนัดหมายสำเร็จ'];
    }

    // ── Get Events for Calendar ───────────────────────────────────────────

    public function getCalendarEvents(string $from, string $to, ?int $userId = null): array
    {
        $cacheKey = "calendar_{$from}_{$to}_" . ($userId ?? 'all');

        return $this->cache->remember($cacheKey, 300, function () use ($from, $to, $userId) {
            $rows    = $this->repo->findByDateRange($from, $to, $userId ? [$userId] : []);
            $events  = [];

            foreach ($rows as $row) {
                $events[] = [
                    'id'         => $row['id'],
                    'title'      => $row['title'],
                    'start'      => $row['event_date'] . ($row['event_time'] ? 'T' . $row['event_time'] : ''),
                    'end'        => $row['event_date'] . ($row['event_end_time'] ? 'T' . $row['event_end_time'] : ''),
                    'color'      => $row['type_color'] ?? '#1a56db',
                    'url'        => '/preview?id=' . $row['id'],
                    'extendedProps' => [
                        'department' => $row['department_name'],
                        'chairman'   => $row['chairman_name'],
                        'dress'      => $row['dress_name'],
                        'dress_color'=> $row['dress_color'],
                    ],
                ];
            }

            return $events;
        });
    }

    // ── File Upload Validation ────────────────────────────────────────────

    /**
     * Validate ไฟล์แนบจาก $_FILES
     * ตรวจ MIME จาก finfo (ไม่ใช้ extension ที่ user ตั้ง)
     */
    public function validateFiles(array $files): array
    {
        $errors = [];

        // normalise $_FILES structure (multiple files)
        $items = $this->normalizeFilesArray($files);

        if (count($items) > self::MAX_FILES) {
            $errors[] = 'อัพโหลดได้สูงสุด ' . self::MAX_FILES . ' ไฟล์ต่อครั้ง';
            return $errors;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($items as $i => $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors["file_{$i}"] = 'ไฟล์ที่ ' . ($i + 1) . ' เกิดข้อผิดพลาดในการอัพโหลด';
                continue;
            }
            if ($file['size'] > self::MAX_FILE_SIZE) {
                $errors["file_{$i}"] = 'ไฟล์ ' . $file['name'] . ' ใหญ่เกิน 10 MB';
                continue;
            }

            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, self::ALLOWED_MIME, true)) {
                $errors["file_{$i}"] = 'ไฟล์ ' . $file['name'] . ' ไม่ใช่ประเภทที่อนุญาต';
            }
        }

        return $errors;
    }

    // ── Private Helpers ──────────────────────────────────────────────────

    private function saveAttachments(int $eventId, array $files): void
    {
        $items   = $this->normalizeFilesArray($files);
        $uploadPath = base_path(self::UPLOAD_DIR . '/' . $eventId);

        if (!empty($items)) {
            @mkdir($uploadPath, 0755, true);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($items as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) continue;

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = $uploadPath . '/' . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $dest)) continue;

            $this->db->query(
                "INSERT INTO event_attachments (event_id, original_name, stored_name, mime_type, file_size, created_at)
                 VALUES (?,?,?,?,?,NOW())",
                [
                    $eventId,
                    $file['name'],
                    self::UPLOAD_DIR . '/' . $eventId . '/' . $safeName,
                    $finfo->file($dest),
                    $file['size'],
                ]
            );
        }
    }

    private function normalizeFilesArray(array $files): array
    {
        if (empty($files)) return [];
        // สนับสนุนทั้ง files[0] และ multiple upload
        if (isset($files['name']) && is_array($files['name'])) {
            $normalized = [];
            foreach ($files['name'] as $i => $name) {
                $normalized[] = [
                    'name'     => $name,
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
            return $normalized;
        }
        if (isset($files['name'])) {
            return [$files];
        }
        return $files;
    }

    /**
     * Auto-save department ถ้า department_name ส่งมาแต่ id ไม่มี
     */
    private function resolveOrCreateDepartment(array $data): ?int
    {
        if (!empty($data['department_id'])) return (int)$data['department_id'];
        if (empty($data['department_name'])) return null;

        $name = trim($data['department_name']);
        $row  = $this->db->selectOne("SELECT id FROM departments WHERE name = ?", [$name]);
        if ($row) return (int)$row['id'];

        $this->db->query("INSERT INTO departments (name, created_at) VALUES (?,NOW())", [$name]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Auto-save chairman ถ้า chairman_name ส่งมาแต่ id ไม่มี
     */
    private function resolveOrCreateChairman(array $data): ?int
    {
        if (!empty($data['chairman_id'])) return (int)$data['chairman_id'];
        if (empty($data['chairman_name'])) return null;

        $name = trim($data['chairman_name']);
        $row  = $this->db->selectOne("SELECT id FROM chairmans WHERE name = ?", [$name]);
        if ($row) return (int)$row['id'];

        $this->db->query("INSERT INTO chairmans (name, created_at) VALUES (?,NOW())", [$name]);
        return (int)$this->db->lastInsertId();
    }

    private function bustCache(): void
    {
        $this->cache->deletePattern('calendar_*');
        $this->cache->delete('dashboard_today');
        $this->cache->delete('events_upcoming');
    }
}
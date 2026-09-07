<?php

declare(strict_types=1);

namespace Modules\Event;

/**
 * EventValidator
 * ─────────────────────────────────────────────────────────
 * Validate และ Sanitize input ทั้งหมดของ event
 */
class EventValidator
{
    private const VALID_STATUSES = ['active', 'cancelled', 'postponed', 'completed'];
    private const MAX_TITLE_LEN  = 500;
    private const MAX_DESC_LEN   = 5000;
    private const MAX_LOC_LEN    = 500;

    // ── Validate ─────────────────────────────────────────────────────────

    public function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // Required fields (ไม่บังคับสำหรับ update)
        if (!$isUpdate) {
            $required = [
                'title'      => 'ชื่อนัดหมาย/ชื่องาน',
                'event_date' => 'วันที่จัดงาน',
            ];
            foreach ($required as $field => $label) {
                if (empty(trim((string)($data[$field] ?? '')))) {
                    $errors[$field] = "{$label} จำเป็นต้องระบุ";
                }
            }
        }

        // Title
        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (mb_strlen($title) > self::MAX_TITLE_LEN) {
                $errors['title'] = 'ชื่อนัดหมายต้องไม่เกิน ' . self::MAX_TITLE_LEN . ' ตัวอักษร';
            }
            if ($title !== strip_tags($title)) {
                $errors['title'] = 'ชื่อนัดหมายต้องไม่มี HTML';
            }
        }

        // Event date
        if (!empty($data['event_date'])) {
            if (!$this->isValidDate($data['event_date'])) {
                $errors['event_date'] = 'รูปแบบวันที่ไม่ถูกต้อง (YYYY-MM-DD)';
            }
        }

        // Event time
        if (!empty($data['event_time'])) {
            if (!$this->isValidTime($data['event_time'])) {
                $errors['event_time'] = 'รูปแบบเวลาไม่ถูกต้อง (HH:MM)';
            }
        }

        // Event end time
        if (!empty($data['event_end_time'])) {
            if (!$this->isValidTime($data['event_end_time'])) {
                $errors['event_end_time'] = 'รูปแบบเวลาสิ้นสุดไม่ถูกต้อง';
            }
            // end_time ต้องหลัง start_time
            if (!empty($data['event_time']) && $data['event_end_time'] <= $data['event_time']) {
                $errors['event_end_time'] = 'เวลาสิ้นสุดต้องหลังเวลาเริ่มต้น';
            }
        }

        // Status
        if (!empty($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            $errors['status'] = 'สถานะไม่ถูกต้อง';
        }

        // Description
        if (!empty($data['description']) && mb_strlen($data['description']) > self::MAX_DESC_LEN) {
            $errors['description'] = 'รายละเอียดต้องไม่เกิน ' . self::MAX_DESC_LEN . ' ตัวอักษร';
        }

        // Location
        if (!empty($data['location']) && mb_strlen($data['location']) > self::MAX_LOC_LEN) {
            $errors['location'] = 'สถานที่ต้องไม่เกิน ' . self::MAX_LOC_LEN . ' ตัวอักษร';
        }

        // Foreign keys (ตรวจเฉพาะว่าเป็นตัวเลข)
        foreach (['department_id', 'chairman_id', 'dress_id', 'event_type_id'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                if (!is_numeric($data[$field]) || (int)$data[$field] < 1) {
                    $errors[$field] = "ค่า {$field} ไม่ถูกต้อง";
                }
            }
        }

        // Person IDs (array ของ int)
        if (isset($data['person_ids'])) {
            if (!is_array($data['person_ids'])) {
                $errors['person_ids'] = 'ข้อมูลผู้เข้าร่วมไม่ถูกต้อง';
            } else {
                foreach ($data['person_ids'] as $pid) {
                    if (!is_numeric($pid) || (int)$pid < 1) {
                        $errors['person_ids'] = 'รหัสผู้เข้าร่วมไม่ถูกต้อง';
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    // ── Sanitize ─────────────────────────────────────────────────────────

    /**
     * Sanitize input array ก่อนส่งให้ Repository
     */
    public function sanitize(array $data): array
    {
        $sanitized = [];

        if (isset($data['title'])) {
            $sanitized['title'] = $this->sanitizeText($data['title'], self::MAX_TITLE_LEN);
        }
        if (isset($data['event_date'])) {
            $sanitized['event_date'] = $this->sanitizeDate($data['event_date']);
        }
        if (isset($data['event_time'])) {
            $sanitized['event_time'] = $this->sanitizeTime($data['event_time']);
        }
        if (isset($data['event_end_time'])) {
            $sanitized['event_end_time'] = $this->sanitizeTime($data['event_end_time']);
        }
        if (isset($data['location'])) {
            $sanitized['location'] = $this->sanitizeText($data['location'], self::MAX_LOC_LEN);
        }
        if (isset($data['description'])) {
            $sanitized['description'] = $this->sanitizeHtml($data['description']);
        }
        if (isset($data['status'])) {
            $sanitized['status'] = in_array($data['status'], self::VALID_STATUSES) ? $data['status'] : 'active';
        }

        // Integer fields
        foreach (['department_id', 'chairman_id', 'dress_id', 'event_type_id'] as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field] !== '' ? (int)$data[$field] : null;
            }
        }

        // Boolean
        if (isset($data['notification_enabled'])) {
            $sanitized['notification_enabled'] = (int)(bool)$data['notification_enabled'];
        }
        if (isset($data['sync_google'])) {
            $sanitized['sync_google'] = (bool)$data['sync_google'];
        }

        // Arrays
        if (isset($data['person_ids'])) {
            $sanitized['person_ids'] = array_map('intval', (array)$data['person_ids']);
        }

        // Pass-through ที่ validator ใช้
        foreach (['department_name', 'chairman_name'] as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $this->sanitizeText($data[$field], 255);
            }
        }

        return $sanitized;
    }

    // ── Private Sanitizers ───────────────────────────────────────────────

    private function sanitizeText(string $value, int $maxLen): string
    {
        $value = strip_tags(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);
        return mb_substr($value, 0, $maxLen);
    }

    /**
     * อนุญาต HTML เฉพาะ tag ที่ปลอดภัย (สำหรับ description)
     */
    private function sanitizeHtml(string $value): string
    {
        $allowed = '<p><br><strong><em><u><ul><ol><li><a>';
        $value   = strip_tags($value, $allowed);
        // ป้องกัน javascript: ใน href
        $value   = preg_replace('/href\s*=\s*["\']?\s*javascript:/i', 'href="#"', $value);
        return mb_substr(trim($value), 0, self::MAX_DESC_LEN);
    }

    private function sanitizeDate(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        // รองรับ d/m/Y (thai date input)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }

    private function sanitizeTime(string $value): ?string
    {
        $value = trim($value);
        // HH:MM หรือ HH:MM:SS
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $value, $m)) {
            return $m[1] . ':' . $m[2] . ':00';
        }
        return null;
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        [$y, $m, $d] = explode('-', $value);
        return checkdate((int)$m, (int)$d, (int)$y);
    }

    private function isValidTime(string $value): bool
    {
        return (bool)preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', trim($value));
    }
}
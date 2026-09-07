<?php

declare(strict_types=1);

namespace Modules\Event;

use App\Core\Database;

/**
 * EventRepository
 * ─────────────────────────────────────────────────────────
 * DB queries ทั้งหมดผ่าน PDO Prepared Statements
 * แยก logic ออกจาก EventService อย่างชัดเจน
 */
class EventRepository
{
    public function __construct(private Database $db) {}

    // ── Read ─────────────────────────────────────────────────────────────

    /**
     * ดึง events แบบ paginate พร้อม filter
     */
    public function paginate(array $filters = [], int $perPage = 20, int $page = 1): array
    {
        [$sql, $bindings] = $this->buildListQuery($filters);

        // Count total
        $countSql  = "SELECT COUNT(*) as total FROM ({$sql}) sub";
        $totalRow  = $this->db->selectOne($countSql, $bindings);
        $total     = (int)($totalRow['total'] ?? 0);

        // Paginate
        $offset    = ($page - 1) * $perPage;
        $sql      .= " ORDER BY " . $this->buildOrderClause($filters);
        $sql      .= " LIMIT ? OFFSET ?";
        $bindings  = array_merge($bindings, [$perPage, $offset]);

        return [
            'data'          => $this->db->select($sql, $bindings),
            'total'         => $total,
            'per_page'      => $perPage,
            'current_page'  => $page,
            'last_page'     => (int)ceil($total / max($perPage, 1)),
        ];
    }

    /**
     * ดึง events ในช่วงวันที่ (สำหรับปฏิทิน)
     */
    public function findByDateRange(string $from, string $to, array $userIds = []): array
    {
        $sql = "SELECT e.*,
                       d.name AS department_name,
                       c.name AS chairman_name,
                       dr.name AS dress_name,
                       dr.color AS dress_color,
                       et.name AS type_name, et.color AS type_color,
                       GROUP_CONCAT(ep.person_id ORDER BY ep.sort_order SEPARATOR ',') AS person_ids
                FROM events e
                LEFT JOIN departments d  ON e.department_id = d.id
                LEFT JOIN chairmans c    ON e.chairman_id   = c.id
                LEFT JOIN dresses dr     ON e.dress_id      = dr.id
                LEFT JOIN event_types et ON e.event_type_id = et.id
                LEFT JOIN event_persons ep ON e.id = ep.event_id
                WHERE e.deleted_at IS NULL
                  AND e.event_date BETWEEN ? AND ?";

        $bindings = [$from, $to];

        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $sql         .= " AND e.created_by IN ({$placeholders})";
            $bindings     = array_merge($bindings, $userIds);
        }

        $sql .= " GROUP BY e.id ORDER BY e.event_date ASC, e.event_time ASC";

        return $this->db->select($sql, $bindings);
    }

    /**
     * ดึง event เดียวพร้อม relations ทั้งหมด
     */
    public function findWithRelations(int $id): ?array
    {
        $event = $this->db->selectOne(
            "SELECT e.*,
                    d.name  AS department_name,
                    c.name  AS chairman_name,
                    dr.name AS dress_name, dr.color AS dress_color,
                    et.name AS type_name,  et.color AS type_color,
                    u.first_name AS creator_first, u.last_name AS creator_last,
                    u.email AS creator_email
             FROM events e
             LEFT JOIN departments d  ON e.department_id = d.id
             LEFT JOIN chairmans c    ON e.chairman_id   = c.id
             LEFT JOIN dresses dr     ON e.dress_id      = dr.id
             LEFT JOIN event_types et ON e.event_type_id = et.id
             LEFT JOIN users u        ON e.created_by    = u.id
             WHERE e.id = ? AND e.deleted_at IS NULL",
            [$id]
        );

        if (!$event) return null;

        // Load persons
        $event['persons'] = $this->db->select(
            "SELECT p.*, ep.sort_order, ep.role
             FROM persons p
             JOIN event_persons ep ON p.id = ep.person_id
             WHERE ep.event_id = ?
             ORDER BY ep.sort_order",
            [$id]
        );

        // Load attachments
        $event['attachments'] = $this->db->select(
            "SELECT * FROM event_attachments WHERE event_id = ? ORDER BY created_at ASC",
            [$id]
        );

        return $event;
    }

    /**
     * ดึง events ของวันนี้ (สำหรับ dashboard + reminder)
     */
    public function findToday(?int $userId = null): array
    {
        $today = date('Y-m-d');
        $sql   = "SELECT e.*, d.name AS department_name, c.name AS chairman_name
                  FROM events e
                  LEFT JOIN departments d ON e.department_id = d.id
                  LEFT JOIN chairmans   c ON e.chairman_id   = c.id
                  WHERE e.deleted_at IS NULL AND e.event_date = ?";
        $bindings = [$today];

        if ($userId) {
            $sql      .= " AND e.created_by = ?";
            $bindings[] = $userId;
        }

        $sql .= " ORDER BY e.event_time ASC";
        return $this->db->select($sql, $bindings);
    }

    /**
     * ดึง events ที่ใกล้จะถึง (สำหรับ push notification)
     */
    public function findUpcoming(int $withinMinutes = 15): array
    {
        $sql = "SELECT e.*, u.id AS user_id, u.email
                FROM events e
                JOIN users u ON e.created_by = u.id
                JOIN push_subscriptions ps ON ps.user_id = u.id
                WHERE e.deleted_at IS NULL
                  AND e.is_notified = 0
                  AND e.notification_enabled = 1
                  AND CONCAT(e.event_date, ' ', COALESCE(e.event_time, '00:00:00'))
                      BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)",
        return $this->db->select($sql, [$withinMinutes]);
    }

    // ── Write ─────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $this->db->query(
            "INSERT INTO events
                (uuid, title, event_date, event_time, event_end_time,
                 department_id, chairman_id, dress_id, event_type_id,
                 location, description, status, notification_enabled,
                 google_calendar_id, created_by, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
            [
                $data['uuid'],
                $data['title'],
                $data['event_date'],
                $data['event_time']     ?? null,
                $data['event_end_time'] ?? null,
                $data['department_id']  ?? null,
                $data['chairman_id']    ?? null,
                $data['dress_id']       ?? null,
                $data['event_type_id']  ?? null,
                $data['location']       ?? null,
                $data['description']    ?? null,
                $data['status']         ?? 'active',
                $data['notification_enabled'] ?? 1,
                $data['google_calendar_id']   ?? null,
                $data['created_by'],
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set      = [];
        $bindings = [];
        $allowed  = [
            'title','event_date','event_time','event_end_time',
            'department_id','chairman_id','dress_id','event_type_id',
            'location','description','status','notification_enabled','google_calendar_id',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $set[]      = "{$field} = ?";
                $bindings[] = $data[$field];
            }
        }

        if (empty($set)) return false;

        $set[]      = "updated_at = NOW()";
        $bindings[] = $id;

        $this->db->query(
            "UPDATE events SET " . implode(', ', $set) . " WHERE id = ? AND deleted_at IS NULL",
            $bindings
        );

        return true;
    }

    public function delete(int $id): bool
    {
        $this->db->query(
            "UPDATE events SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$id]
        );
        return true;
    }

    // ── Persons ───────────────────────────────────────────────────────────

    public function syncPersons(int $eventId, array $personIds): void
    {
        $this->db->query("DELETE FROM event_persons WHERE event_id = ?", [$eventId]);

        foreach ($personIds as $order => $pid) {
            $this->db->query(
                "INSERT INTO event_persons (event_id, person_id, sort_order) VALUES (?,?,?)",
                [$eventId, (int)$pid, $order]
            );
        }
    }

    // ── Notification Flag ─────────────────────────────────────────────────

    public function markNotified(int $eventId): void
    {
        $this->db->query(
            "UPDATE events SET is_notified = 1, updated_at = NOW() WHERE id = ?",
            [$eventId]
        );
    }

    // ── Statistics ────────────────────────────────────────────────────────

    public function countByMonth(int $year): array
    {
        return $this->db->select(
            "SELECT MONTH(event_date) AS month, COUNT(*) AS total
             FROM events
             WHERE YEAR(event_date) = ? AND deleted_at IS NULL
             GROUP BY MONTH(event_date)
             ORDER BY month",
            [$year]
        );
    }

    public function countByDepartment(int $year): array
    {
        return $this->db->select(
            "SELECT d.name, COUNT(e.id) AS total
             FROM events e
             LEFT JOIN departments d ON e.department_id = d.id
             WHERE YEAR(e.event_date) = ? AND e.deleted_at IS NULL
             GROUP BY d.id, d.name
             ORDER BY total DESC
             LIMIT 10",
            [$year]
        );
    }

    // ── Private Helpers ──────────────────────────────────────────────────

    private function buildListQuery(array $filters): array
    {
        $sql = "SELECT e.id, e.uuid, e.title, e.event_date, e.event_time, e.event_end_time,
                       e.status, e.notification_enabled, e.created_at,
                       d.name AS department_name,
                       c.name AS chairman_name,
                       dr.name AS dress_name, dr.color AS dress_color,
                       et.name AS type_name,  et.color AS type_color
                FROM events e
                LEFT JOIN departments d  ON e.department_id = d.id
                LEFT JOIN chairmans   c  ON e.chairman_id   = c.id
                LEFT JOIN dresses     dr ON e.dress_id      = dr.id
                LEFT JOIN event_types et ON e.event_type_id = et.id
                WHERE e.deleted_at IS NULL";

        $bindings = [];

        if (!empty($filters['search'])) {
            $like         = '%' . $filters['search'] . '%';
            $sql         .= " AND (e.title LIKE ? OR e.location LIKE ? OR e.description LIKE ?)";
            $bindings     = array_merge($bindings, [$like, $like, $like]);
        }
        if (!empty($filters['department_id'])) { $sql .= " AND e.department_id = ?"; $bindings[] = $filters['department_id']; }
        if (!empty($filters['chairman_id']))   { $sql .= " AND e.chairman_id = ?";   $bindings[] = $filters['chairman_id']; }
        if (!empty($filters['status']))        { $sql .= " AND e.status = ?";        $bindings[] = $filters['status']; }
        if (!empty($filters['year']))          { $sql .= " AND YEAR(e.event_date) = ?";  $bindings[] = $filters['year']; }
        if (!empty($filters['month']))         { $sql .= " AND MONTH(e.event_date) = ?"; $bindings[] = $filters['month']; }
        if (!empty($filters['date_from']))     { $sql .= " AND e.event_date >= ?";   $bindings[] = $filters['date_from']; }
        if (!empty($filters['date_to']))       { $sql .= " AND e.event_date <= ?";   $bindings[] = $filters['date_to']; }
        if (!empty($filters['created_by']))    { $sql .= " AND e.created_by = ?";    $bindings[] = $filters['created_by']; }

        return [$sql, $bindings];
    }

    private function buildOrderClause(array $filters): string
    {
        $allowed = ['event_date', 'title', 'status', 'created_at', 'department_id'];
        $sort    = in_array($filters['sort'] ?? '', $allowed) ? $filters['sort'] : 'event_date';
        $dir     = strtoupper($filters['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        return "e.{$sort} {$dir}, e.event_time ASC";
    }
}
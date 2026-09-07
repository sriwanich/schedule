<?php

declare(strict_types=1);

namespace Modules\Export;

/**
 * ICalExporter
 * ─────────────────────────────────────────────────────────
 * สร้างไฟล์ iCalendar (.ics) ตาม RFC 5545
 * ใช้ได้กับ Google Calendar, Outlook, Apple Calendar
 *
 * ไม่ต้องการ library ภายนอก
 */
class ICalExporter
{
    private const PRODID   = '-//Schedule System//Schedule//TH';
    private const VERSION  = '2.0';
    private const CALSCALE = 'GREGORIAN';
    private const METHOD   = 'PUBLISH';
    private const TZ       = 'Asia/Bangkok';

    // ── Build iCal ────────────────────────────────────────────────────────

    /**
     * สร้าง .ics จาก array ของ events
     */
    public function export(array $events, string $calName = ''): string
    {
        if (empty($calName)) {
            $calName = env('APP_NAME', 'ระบบตารางงาน');
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:'  . self::VERSION,
            'PRODID:'   . self::PRODID,
            'CALSCALE:' . self::CALSCALE,
            'METHOD:'   . self::METHOD,
            'X-WR-CALNAME:' . $this->escText($calName),
            'X-WR-TIMEZONE:' . self::TZ,
            $this->buildTimezone(),
        ];

        foreach ($events as $event) {
            $lines[] = $this->buildVEvent($event);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Export event เดียว
     */
    public function exportOne(array $event): string
    {
        return $this->export([$event]);
    }

    /**
     * ส่ง .ics ให้ browser download
     */
    public function download(array $events, string $filename = 'events.ics'): void
    {
        $ics      = $this->export($events);
        $filename = $this->sanitizeFilename($filename);

        header('Content-Type: text/calendar; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($ics));
        header('Cache-Control: no-store, no-cache');
        header('Pragma: no-cache');

        echo $ics;
        exit;
    }

    // ── VEVENT Builder ────────────────────────────────────────────────────

    private function buildVEvent(array $event): string
    {
        $uid        = $this->buildUid($event);
        $created    = $this->toICalDateTime($event['created_at'] ?? date('Y-m-d H:i:s'));
        $modified   = $this->toICalDateTime($event['updated_at'] ?? $event['created_at'] ?? date('Y-m-d H:i:s'));
        $dtstart    = $this->buildDtStart($event);
        $dtend      = $this->buildDtEnd($event);
        $summary    = $this->escText($event['title'] ?? '');
        $location   = !empty($event['location']) ? 'LOCATION:' . $this->escText($event['location']) : '';
        $description= $this->buildDescription($event);
        $status     = $this->mapStatus($event['status'] ?? 'active');
        $sequence   = (int)($event['revision'] ?? 0);

        $lines = array_filter([
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$created}",
            "CREATED:{$created}",
            "LAST-MODIFIED:{$modified}",
            $dtstart,
            $dtend,
            "SUMMARY:{$summary}",
            $description ? "DESCRIPTION:{$description}" : '',
            $location,
            "STATUS:{$status}",
            "SEQUENCE:{$sequence}",
            $this->buildAlarm($event),
            'END:VEVENT',
        ]);

        return implode("\r\n", $lines);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function buildUid(array $event): string
    {
        $base = !empty($event['uuid'])
            ? $event['uuid']
            : 'event-' . ($event['id'] ?? uniqid());
        $host = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?? 'localhost';
        return "{$base}@{$host}";
    }

    private function buildDtStart(array $event): string
    {
        $date = $event['event_date'] ?? date('Y-m-d');
        if (!empty($event['event_time'])) {
            return 'DTSTART;TZID=' . self::TZ . ':' . $this->toICalLocal($date, $event['event_time']);
        }
        return 'DTSTART;VALUE=DATE:' . str_replace('-', '', $date);
    }

    private function buildDtEnd(array $event): string
    {
        $date = $event['event_date'] ?? date('Y-m-d');
        if (!empty($event['event_end_time'])) {
            return 'DTEND;TZID=' . self::TZ . ':' . $this->toICalLocal($date, $event['event_end_time']);
        }
        if (!empty($event['event_time'])) {
            // Default: 1 ชั่วโมง
            $start   = new \DateTime("{$date} {$event['event_time']}", new \DateTimeZone(self::TZ));
            $start->modify('+1 hour');
            return 'DTEND;TZID=' . self::TZ . ':' . $start->format('Ymd\THis');
        }
        // All-day: วันถัดไป
        $next = date('Ymd', strtotime($date . ' +1 day'));
        return "DTEND;VALUE=DATE:{$next}";
    }

    private function buildDescription(array $event): string
    {
        $parts = [];
        if (!empty($event['department_name'])) $parts[] = 'หน่วยงาน: ' . $event['department_name'];
        if (!empty($event['chairman_name']))   $parts[] = 'ประธาน: ' . $event['chairman_name'];
        if (!empty($event['dress_name']))      $parts[] = 'การแต่งกาย: ' . $event['dress_name'];
        if (!empty($event['description'])) {
            $parts[] = strip_tags($event['description']);
        }
        if (!empty($event['id'])) {
            $parts[] = 'ดูรายละเอียด: ' . url('/preview?id=' . $event['id']);
        }
        return $this->escText(implode('\n', $parts));
    }

    private function buildAlarm(array $event): string
    {
        if (empty($event['notification_enabled'])) return '';

        return implode("\r\n", [
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:แจ้งเตือน: ' . $this->escText($event['title'] ?? ''),
            'TRIGGER:-PT15M',   // 15 นาทีก่อน
            'END:VALARM',
        ]);
    }

    /**
     * VTIMEZONE สำหรับ Asia/Bangkok (UTC+7 ไม่มี DST)
     */
    private function buildTimezone(): string
    {
        return implode("\r\n", [
            'BEGIN:VTIMEZONE',
            'TZID:Asia/Bangkok',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0700',
            'TZOFFSETTO:+0700',
            'TZNAME:ICT',
            'DTSTART:19700101T000000',
            'END:STANDARD',
            'END:VTIMEZONE',
        ]);
    }

    private function toICalDateTime(string $datetime): string
    {
        try {
            $dt = new \DateTime($datetime, new \DateTimeZone(self::TZ));
            $dt->setTimezone(new \DateTimeZone('UTC'));
            return $dt->format('Ymd\THis\Z');
        } catch (\Throwable) {
            return date('Ymd\THis\Z');
        }
    }

    private function toICalLocal(string $date, string $time): string
    {
        // YYYYMMDDTHHMMSS (local time, ใช้คู่กับ TZID)
        $dt = new \DateTime("{$date} {$time}", new \DateTimeZone(self::TZ));
        return $dt->format('Ymd\THis');
    }

    /**
     * Escape ตามข้อกำหนด RFC 5545 section 3.3.11
     */
    private function escText(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(['\\', ';', ',', "\n", "\r\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $value);
        return $this->fold($value);
    }

    /**
     * Line folding: แต่ละ line ต้องไม่เกิน 75 octets ตาม RFC 5545
     */
    private function fold(string $str): string
    {
        if (mb_strlen($str) <= 75) return $str;

        $result = '';
        while (mb_strlen($str) > 75) {
            $result .= mb_substr($str, 0, 75) . "\r\n ";
            $str     = mb_substr($str, 75);
        }
        return $result . $str;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'cancelled' => 'CANCELLED',
            'completed' => 'COMPLETED',
            default     => 'CONFIRMED',
        };
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\/\\\:\*\?"<>|]/', '_', $name);
        return mb_substr($name, 0, 200) . (str_ends_with($name, '.ics') ? '' : '.ics');
    }
}
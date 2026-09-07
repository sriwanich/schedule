<?php
require 'db_connect.php';
require 'google_calendar_config.php';

try {
    // ดึงข้อมูลจากฐานข้อมูล
    $query = $pdo->query("SELECT id, title, description, location, date, time_from, time_to, google_calendar_event_id, attendees, status, synced FROM events");
    $events = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        $eventId = $event['google_calendar_event_id'];

        // ตรวจสอบสถานะของกิจกรรม
        if (in_array($event['status'], ['เลื่อน', 'ยกเลิก'])) {
            if (!empty($eventId)) {
                try {
                    // ลบกิจกรรมออกจาก Google Calendar
                    $service->events->delete($calendarId, $eventId);
                    echo "🗑️ ลบกิจกรรมที่ถูกยกเลิก: {$event['title']} <br>";

                    // อัปเดตฐานข้อมูลให้ลบ event_id และทำเครื่องหมายว่าไม่ได้ซิงค์
                    $stmt = $pdo->prepare("UPDATE events SET google_calendar_event_id = NULL, synced = 0 WHERE id = :id");
                    $stmt->bindParam(':id', $event['id']);
                    $stmt->execute();
                } catch (Google\Service\Exception $e) {
                    echo "❌ ไม่สามารถลบกิจกรรมจาก Google Calendar: " . $e->getMessage() . "<br>";
                }
            } else {
                echo "🚫 ข้ามกิจกรรมที่ถูกยกเลิกแล้ว: {$event['title']} <br>";
            }
            continue;
        }

        // ตรวจสอบว่าข้อมูลสำคัญครบถ้วน
        if (empty($event['title']) || empty($event['date']) || empty($event['time_from']) || empty($event['time_to'])) {
            echo "❌ ข้อมูลไม่ครบ: " . json_encode($event) . "<br>";
            continue;
        }

        // ตรวจสอบรูปแบบเวลา (HH:MM หรือ HH:MM:SS)
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $event['time_from']) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $event['time_to'])) {
            echo "❌ รูปแบบเวลาไม่ถูกต้อง: {$event['time_from']} - {$event['time_to']} <br>";
            continue;
        }

        // จัดรูปแบบวันที่และเวลาให้เป็นรูปแบบ ISO 8601
        $startDateTime = "{$event['date']}T{$event['time_from']}";
        $endDateTime = "{$event['date']}T{$event['time_to']}";

        // เปลี่ยนเวลาให้เป็น Unix timestamp สำหรับการเปรียบเทียบ
        $currentTimestamp = time();
        $endTimestamp = strtotime($endDateTime);

        // ถ้ากิจกรรมพ้นเวลาที่กำหนดแล้ว ให้ข้ามการซิงค์
        if ($endTimestamp < $currentTimestamp) {
            echo "🔚 ข้ามกิจกรรม (พ้นกำหนดเวลาแล้ว): {$event['title']} <br>";
            continue;
        }

        // ตรวจสอบและจัดการ attendees
        $attendees = [];
        if (!empty($event['attendees'])) {
            $emails = explode(' ', trim($event['attendees']));
            $attendees = array_map(function ($email) {
                $email = trim($email);
                return filter_var($email, FILTER_VALIDATE_EMAIL) ? ['email' => $email] : null;
            }, $emails);
            $attendees = array_filter($attendees);
        }

        // เพิ่มอีเมล `หน.ปภ.ระยอง` เข้าไปเสมอ
        $mandatoryEmail = 'natthawut.dpmpry@gmail.com';
        $attendees[] = ['email' => $mandatoryEmail];
        $attendees = array_map("unserialize", array_unique(array_map("serialize", $attendees)));

        // สร้างข้อมูลกิจกรรม Google Calendar
        $googleEvent = new Google\Service\Calendar\Event([
            'summary'     => $event['title'],
            'description' => $event['description'] ?? '',
            'location'    => $event['location'] ?? '',
            'start'       => ['dateTime' => $startDateTime, 'timeZone' => 'Asia/Bangkok'],
            'end'         => ['dateTime' => $endDateTime, 'timeZone' => 'Asia/Bangkok'],
            'attendees'   => $attendees
        ]);

        echo "♻️ กำลังซิงค์: " . json_encode($googleEvent) . "<br>";

        try {
            if ($eventId) {
                $service->events->update($calendarId, $eventId, $googleEvent);
                echo "✅ อัปเดตสำเร็จ: {$event['title']} <br>";
            } else {
                $newEvent = $service->events->insert($calendarId, $googleEvent);
                $stmt = $pdo->prepare("UPDATE events SET google_calendar_event_id = :event_id, synced = 1 WHERE id = :id");
                $stmt->bindParam(':event_id', $newEvent->id);
                $stmt->bindParam(':id', $event['id']);
                $stmt->execute();
                echo "✅ เพิ่มกิจกรรมสำเร็จ: {$event['title']} <br>";
            }
            $stmt = $pdo->prepare("UPDATE events SET synced = 1 WHERE id = :id");
            $stmt->bindParam(':id', $event['id']);
            $stmt->execute();
        } catch (Google\Service\Exception $e) {
            echo "❌ Google API Error: " . $e->getMessage() . "<br>";
        }
    }
    echo "🎯 ซิงค์ข้อมูลเสร็จสิ้น";
} catch (Exception $e) {
    echo "❌ ข้อผิดพลาดระบบ: " . $e->getMessage();
}
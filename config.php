<?php

// Start session
if (!session_id()) {
    session_start();
}
// ข้อมูลการเชื่อมต่อฐานข้อมูล
$host = "localhost";
$username = "dpmpryg_sriwanich";
$password = "M@t34a053896077";
$dbname = "dpmpry_schedule";

// สร้างการเชื่อมต่อ
$connect = mysqli_connect($host, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if (!$connect) {
    die("การเชื่อมต่อล้มเหลว: " . mysqli_connect_error());
}

// ตั้งค่าภาษาและเวลา
mysqli_query($connect, "SET NAMES utf8");
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบโครงสร้างตาราง
function checkDatabaseStructure($connect) {
    $requiredColumns = [
        'postpone_date' => "ALTER TABLE events ADD COLUMN postpone_date DATE NULL AFTER status",
        'postpone_time_from' => "ALTER TABLE events ADD COLUMN postpone_time_from TIME NULL AFTER postpone_date",
        'postpone_time_to' => "ALTER TABLE events ADD COLUMN postpone_time_to TIME NULL AFTER postpone_time_from",
        'postpone_reason' => "ALTER TABLE events ADD COLUMN postpone_reason TEXT NULL AFTER postpone_time_to",
        'postpone_file_name' => "ALTER TABLE events ADD COLUMN postpone_file_name VARCHAR(255) NULL AFTER postpone_reason",
        'cancel_reason' => "ALTER TABLE events ADD COLUMN cancel_reason TEXT NULL AFTER postpone_file_name",
        'cancel_file_name' => "ALTER TABLE events ADD COLUMN cancel_file_name VARCHAR(255) NULL AFTER cancel_reason",
        'original_event_id' => "ALTER TABLE events ADD COLUMN original_event_id INT NULL AFTER cancel_file_name"
    ];
    
    $result = mysqli_query($connect, "DESCRIBE events");
    if (!$result) {
        die("ไม่สามารถตรวจสอบโครงสร้างตารางได้: " . mysqli_error($connect));
    }
    
    $existingColumns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $existingColumns[] = $row['Field'];
    }
    
    foreach ($requiredColumns as $column => $sql) {
        if (!in_array($column, $existingColumns)) {
            if (!mysqli_query($connect, $sql)) {
                die("การสร้างคอลัมน์ $column ล้มเหลว: " . mysqli_error($connect));
            }
        }
    }
}

// เรียกใช้ฟังก์ชันตรวจสอบโครงสร้าง
checkDatabaseStructure($connect);

// VAPID keys สำหรับ Web Push Notification
define('VAPID_PUBLIC_KEY', 'BNIta-mvgfazLWIXOJQrmvdEnsxBhMyFwHLSsAx6MJG3IiwazRmPmJkfxRF4pHkZkYUmr3QXsSflSWj7G6Io3U8');
define('VAPID_PRIVATE_KEY', 'HPj0XmLeMEABEPsuJYLCd2485qN_FO7ouE_3gfOdyrM');
define('VAPID_SUBJECT', 'mailto:sriwanich@gmail.com');

define('CALENDARIFIC_API_KEY', 'WIA7bVs21IE76TbUDb5j8N2yTSIZ6Fbc');
define('HOLIDAYS_CACHE_DIR', __DIR__ . '/cache/holidays'); // optional
?>
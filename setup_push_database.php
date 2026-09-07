<?php
/**
 * ไฟล์สำหรับสร้างตารางและคอลัมน์ที่จำเป็นสำหรับ Push Notifications
 * รันไฟล์นี้ครั้งเดียวก่อนใช้งานระบบ
 */

require_once 'config.php';

echo "<h2>🔧 ติดตั้งระบบ Push Notification</h2>";
echo "<hr>";

// 1. สร้างตาราง push_subscriptions
echo "<h3>1. สร้างตาราง push_subscriptions</h3>";
$create_table = "CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL,
    public_key VARCHAR(255),
    auth_token VARCHAR(255),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_active (is_active),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($connect, $create_table)) {
    echo "✅ สร้างตาราง push_subscriptions สำเร็จ<br>";
} else {
    echo "❌ Error: " . mysqli_error($connect) . "<br>";
}

// 2. เพิ่มคอลัมน์ใน events table
echo "<h3>2. เพิ่มคอลัมน์ใน events table</h3>";

$columns_to_add = [
    'notified' => "ALTER TABLE events ADD COLUMN notified TINYINT(1) DEFAULT 0",
    'notified_1hour' => "ALTER TABLE events ADD COLUMN notified_1hour TINYINT(1) DEFAULT 0",
    'notified_tomorrow' => "ALTER TABLE events ADD COLUMN notified_tomorrow TINYINT(1) DEFAULT 0"
];

foreach ($columns_to_add as $column => $sql) {
    // ตรวจสอบว่ามีคอลัมน์อยู่แล้วหรือไม่
    $check = "SHOW COLUMNS FROM events LIKE '$column'";
    $result = mysqli_query($connect, $check);
    
    if (mysqli_num_rows($result) == 0) {
        if (mysqli_query($connect, $sql)) {
            echo "✅ เพิ่มคอลัมน์ '$column' สำเร็จ<br>";
        } else {
            echo "❌ Error adding '$column': " . mysqli_error($connect) . "<br>";
        }
    } else {
        echo "ℹ️ คอลัมน์ '$column' มีอยู่แล้ว<br>";
    }
}

// 3. เพิ่ม indexes สำหรับ performance
echo "<h3>3. เพิ่ม indexes</h3>";

$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_event_datetime ON events(date, time_from)",
    "CREATE INDEX IF NOT EXISTS idx_event_notified ON events(notified, notified_1hour, notified_tomorrow)",
    "CREATE INDEX IF NOT EXISTS idx_event_status ON events(status)"
];

foreach ($indexes as $index_sql) {
    if (mysqli_query($connect, $index_sql)) {
        echo "✅ เพิ่ม index สำเร็จ<br>";
    } else {
        // ถ้า error เพราะมี index อยู่แล้ว ไม่ต้องแสดง error
        if (strpos(mysqli_error($connect), 'Duplicate key name') === false) {
            echo "❌ Error: " . mysqli_error($connect) . "<br>";
        } else {
            echo "ℹ️ Index มีอยู่แล้ว<br>";
        }
    }
}

// 4. ตรวจสอบการติดตั้ง
echo "<h3>4. ตรวจสอบการติดตั้ง</h3>";

// ตรวจสอบตาราง push_subscriptions
$check_table = "SHOW TABLES LIKE 'push_subscriptions'";
$result = mysqli_query($connect, $check_table);
if (mysqli_num_rows($result) > 0) {
    echo "✅ ตาราง push_subscriptions พร้อมใช้งาน<br>";
    
    // นับจำนวน subscriptions
    $count = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as total FROM push_subscriptions"));
    echo "📊 จำนวน subscriptions: " . $count['total'] . "<br>";
} else {
    echo "❌ ไม่พบตาราง push_subscriptions<br>";
}

// ตรวจสอบคอลัมน์ใน events
$check_columns = "SHOW COLUMNS FROM events WHERE Field IN ('notified', 'notified_1hour', 'notified_tomorrow')";
$result = mysqli_query($connect, $check_columns);
if (mysqli_num_rows($result) == 3) {
    echo "✅ คอลัมน์ notification ใน events พร้อมใช้งาน<br>";
} else {
    echo "⚠️ คอลัมน์ notification ใน events ไม่ครบ (พบ " . mysqli_num_rows($result) . "/3)<br>";
}

// 5. แสดงข้อมูลการติดตั้ง
echo "<h3>5. ข้อมูลการติดตั้ง</h3>";
echo "<div style='background: #f0f7f7; padding: 15px; border-radius: 8px; border-left: 4px solid #0a9396;'>";
echo "<strong>✅ การติดตั้งเสร็จสมบูรณ์!</strong><br><br>";
echo "<strong>ขั้นตอนต่อไป:</strong><br>";
echo "1. ติดตั้ง Composer library: <code>composer require minishlink/web-push</code><br>";
echo "2. สร้าง VAPID keys: <code>php vendor/bin/web-push generate-vapid-keys</code><br>";
echo "3. อัพเดท VAPID keys ในไฟล์ต่างๆ<br>";
echo "4. ตั้งค่า Cron Job: <code>*/5 * * * * php /path/to/cron_send_notifications.php</code><br>";
echo "5. ทดสอบระบบ<br>";
echo "</div>";

echo "<hr>";
echo "<p><a href='/' style='display: inline-block; background: #0a9396; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>กลับหน้าหลัก</a></p>";

mysqli_close($connect);
?>

<style>
body {
    font-family: 'Anuphan', sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #e9f5f5;
}
h2 {
    color: #0a9396;
}
h3 {
    color: #005f73;
    margin-top: 20px;
}
code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    color: #d9534f;
}
</style>
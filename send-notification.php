<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // หากใช้ web-push library

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// ดึงข้อมูลงานที่ใกล้ถึงเวลา
$query = "SELECT * FROM events 
          WHERE date = CURDATE() 
          AND time_from BETWEEN CURTIME() AND ADDTIME(CURTIME(), '00:10:00')
          AND (status IS NULL OR status NOT IN ('ยกเลิก', 'เลื่อน'))
          ORDER BY time_from ASC";
$result = mysqli_query($connect, $query);

$near_events = [];
while ($event = mysqli_fetch_assoc($result)) {
    $near_events[] = $event;
}

if (empty($near_events)) {
    exit;
}

// ดึงข้อมูล subscriptions ทั้งหมด
$subscriptions = mysqli_query($connect, "SELECT * FROM push_subscriptions");
$auth = [
    'VAPID' => [
        'subject' => VAPID_SUBJECT,
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
];

$webPush = new WebPush($auth);

foreach ($near_events as $event) {
    $notification = [
        'title' => 'กิจกรรมกำลังมาถึง',
        'body' => "กิจกรรม \"{$event['title']}\" จะเริ่มใน 10 นาที",
        'icon' => '/images/icon-192x192.png',
        'data' => [
            'url' => '/event-details.php?id=' . $event['id']
        ]
    ];

    while ($subscription = mysqli_fetch_assoc($subscriptions)) {
        $pushSubscription = Subscription::create([
            'endpoint' => $subscription['endpoint'],
            'publicKey' => $subscription['public_key'],
            'authToken' => $subscription['auth_token'],
        ]);

        $webPush->queueNotification(
            $pushSubscription,
            json_encode($notification)
        );
    }

    // ส่งการแจ้งเตือนทั้งหมด
    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getRequest()->getUri()->__toString();

        if ($report->isSuccess()) {
            echo "ส่งการแจ้งเตือนสำเร็จสำหรับ {$endpoint}";
        } else {
            echo "ส่งการแจ้งเตือนล้มเหลวสำหรับ {$endpoint}: {$report->getReason()}";

            // ลบ subscription ที่ไม่ทำงานแล้ว
            if ($report->isSubscriptionExpired()) {
                mysqli_query($connect, "DELETE FROM push_subscriptions WHERE endpoint = '$endpoint'");
            }
        }
    }

    // รีเซ็ต pointer ของผลลัพธ์
    mysqli_data_seek($subscriptions, 0);
}
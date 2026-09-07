<?php
include 'config.php';

header('Content-Type: application/json');

// ฟังก์ชันส่ง Push Notification
function sendPushNotification($subscription, $data) {
    $headers = [
        'Authorization: key=' . FIREBASE_SERVER_KEY,
        'Content-Type: application/json'
    ];
    
    $postData = [
        'to' => $subscription['endpoint'],
        'data' => $data,
        'notification' => [
            'title' => $data['title'],
            'body' => $data['body'],
            'icon' => '/images/logo.jpg',
            'click_action' => $data['url'] ?? '/'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// บันทึก Subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'push-subscription') {
    $subscription = json_decode(file_get_contents('php://input'), true);
    
    // บันทึกลงฐานข้อมูล
    $stmt = mysqli_prepare($connect, 
        "INSERT INTO push_subscriptions (endpoint, p256dh, auth, user_id) 
         VALUES (?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE p256dh = ?, auth = ?");
    
    $userId = $_SESSION['user_id'] ?? null;
    mysqli_stmt_bind_param($stmt, "ssssss", 
        $subscription['endpoint'],
        $subscription['keys']['p256dh'],
        $subscription['keys']['auth'],
        $userId,
        $subscription['keys']['p256dh'],
        $subscription['keys']['auth']
    );
    
    mysqli_stmt_execute($stmt);
    echo json_encode(['success' => true]);
    exit;
}

// ส่ง Notification สำหรับงานที่ใกล้ถึงเวลา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'send-notification') {
    $eventId = $_POST['event_id'];
    
    // ดึงข้อมูลงานจากฐานข้อมูล
    $stmt = mysqli_prepare($connect, 
        "SELECT e.*, ps.endpoint, ps.p256dh, ps.auth 
         FROM events e
         LEFT JOIN push_subscriptions ps ON 1=1
         WHERE e.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $eventId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($result);
    
    if ($event) {
        $notificationData = [
            'title' => 'กิจกรรมกำลังมาถึง',
            'body' => "กิจกรรม \"{$event['title']}\" จะเริ่มใน 10 นาที",
            'url' => '/event-details.php?id=' . $event['id']
        ];
        
        // ส่งไปยังทุกอุปกรณ์ที่ลงทะเบียน
        $subscriptions = mysqli_query($connect, "SELECT endpoint, p256dh, auth FROM push_subscriptions");
        while ($sub = mysqli_fetch_assoc($subscriptions)) {
            sendPushNotification($sub, $notificationData);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Event not found']);
    }
    exit;
}

// สำหรับการส่งการแจ้งเตือนจากระบบอื่น
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'custom-notification') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!empty($data['title']) && !empty($data['body'])) {
        $notificationData = [
            'title' => $data['title'],
            'body' => $data['body'],
            'url' => $data['url'] ?? '/'
        ];
        
        // ส่งไปยังทุกอุปกรณ์ที่ลงทะเบียน
        $subscriptions = mysqli_query($connect, "SELECT endpoint, p256dh, auth FROM push_subscriptions");
        while ($sub = mysqli_fetch_assoc($subscriptions)) {
            sendPushNotification($sub, $notificationData);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Title and body are required']);
    }
    exit;
}
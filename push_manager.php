<?php
// ไฟล์สำหรับจัดการ Push Subscriptions
include 'config.php';

header('Content-Type: application/json');

// สร้างตาราง subscriptions ถ้ายังไม่มี
$create_table = "CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL,
    public_key VARCHAR(255),
    auth_token VARCHAR(255),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_endpoint (endpoint(255))
)";
mysqli_query($connect, $create_table);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            if ($action === 'subscribe') {
                // บันทึก subscription
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['endpoint'])) {
                    throw new Exception('Missing endpoint');
                }
                
                $endpoint = mysqli_real_escape_string($connect, $data['endpoint']);
                $public_key = isset($data['keys']['p256dh']) ? mysqli_real_escape_string($connect, $data['keys']['p256dh']) : '';
                $auth_token = isset($data['keys']['auth']) ? mysqli_real_escape_string($connect, $data['keys']['auth']) : '';
                $user_agent = mysqli_real_escape_string($connect, $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // ตรวจสอบว่ามี endpoint นี้แล้วหรือไม่
                $check_query = "SELECT id FROM push_subscriptions WHERE endpoint = '$endpoint'";
                $check_result = mysqli_query($connect, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    // อัพเดทข้อมูล
                    $update_query = "UPDATE push_subscriptions 
                                   SET public_key = '$public_key', 
                                       auth_token = '$auth_token',
                                       user_agent = '$user_agent',
                                       is_active = 1,
                                       updated_at = CURRENT_TIMESTAMP
                                   WHERE endpoint = '$endpoint'";
                    mysqli_query($connect, $update_query);
                } else {
                    // เพิ่มใหม่
                    $insert_query = "INSERT INTO push_subscriptions (endpoint, public_key, auth_token, user_agent) 
                                   VALUES ('$endpoint', '$public_key', '$auth_token', '$user_agent')";
                    mysqli_query($connect, $insert_query);
                }
                
                echo json_encode(['success' => true, 'message' => 'Subscription saved']);
            }
            break;
            
        case 'DELETE':
            if ($action === 'unsubscribe') {
                // ลบ subscription
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['endpoint'])) {
                    throw new Exception('Missing endpoint');
                }
                
                $endpoint = mysqli_real_escape_string($connect, $data['endpoint']);
                $delete_query = "UPDATE push_subscriptions SET is_active = 0 WHERE endpoint = '$endpoint'";
                mysqli_query($connect, $delete_query);
                
                echo json_encode(['success' => true, 'message' => 'Subscription removed']);
            }
            break;
            
        case 'GET':
            if ($action === 'count') {
                // นับจำนวน subscriptions ที่ใช้งานอยู่
                $count_query = "SELECT COUNT(*) as total FROM push_subscriptions WHERE is_active = 1";
                $result = mysqli_query($connect, $count_query);
                $row = mysqli_fetch_assoc($result);
                
                echo json_encode(['success' => true, 'count' => $row['total']]);
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
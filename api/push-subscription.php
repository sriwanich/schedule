<?php
include '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    $endpoint = $data['endpoint'];
    $publicKey = $data['keys']['p256dh'];
    $authToken = $data['keys']['auth'];
    $userId = $_SESSION['user_id'] ?? null;

    // ตรวจสอบว่ามี subscription นี้อยู่แล้วหรือไม่
    $check_stmt = mysqli_prepare($connect, "SELECT id FROM push_subscriptions WHERE endpoint = ?");
    mysqli_stmt_bind_param($check_stmt, "s", $endpoint);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => true, 'message' => 'Subscription already exists']);
        exit;
    }

    // เพิ่ม subscription ใหม่
    $stmt = mysqli_prepare($connect, 
        "INSERT INTO push_subscriptions (endpoint, public_key, auth_token, user_id) 
         VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $endpoint, $publicKey, $authToken, $userId);
    mysqli_stmt_execute($stmt);

    echo json_encode(['success' => true, 'message' => 'Subscription saved']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
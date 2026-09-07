<?php
include '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $date_from = !empty($input['date_from']) ? $input['date_from'] : null;
    $date_to = !empty($input['date_to']) ? $input['date_to'] : null;
    
    // ใช้ฟังก์ชันที่มีอยู่แล้ว
    $export_data = exportEventsToJson($date_from, $date_to);
    
    echo $export_data;
    exit;
}
?>
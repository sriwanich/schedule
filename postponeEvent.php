<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

include 'config.php';

$event_id = intval($_POST['event_id']);
$new_date = $_POST['no_date'] ? NULL : mysqli_real_escape_string($connect, $_POST['new_date']);
$new_time_from = $_POST['no_date'] ? NULL : mysqli_real_escape_string($connect, $_POST['new_time_from']);
$new_time_to = $_POST['no_date'] ? NULL : mysqli_real_escape_string($connect, $_POST['new_time_to']);

// Handle file upload
$file_name = NULL;
if (isset($_FILES['postpone_file']) && $_FILES['postpone_file']['error'] == 0) {
    $file = $_FILES['postpone_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    
    if (in_array($file_ext, $allowed_extensions) && $file['size'] <= 15 * 1024 * 1024) {
        $file_name = uniqid() . "." . $file_ext;
        move_uploaded_file($file['tmp_name'], "uploads/" . $file_name);
    }
}

// Update the original event status
$update_query = "UPDATE events SET 
    status = 'เลื่อน',
    original_event_id = NULL,
    updated_by = 'ระบบ',
    updated = CURRENT_TIMESTAMP
    WHERE id = $event_id";

if (mysqli_query($connect, $update_query)) {
    // Copy the event data to a new record
    $copy_query = "INSERT INTO events (
        title, location, description, Dress, date, time_from, time_to, 
        file_name, department, chairman, coordinator_name, coordinator_phone, 
        attendees, status, original_event_id, created_by, updated_by
    )
    SELECT 
        title, location, description, Dress, 
        " . ($new_date ? "'$new_date'" : "NULL") . ", 
        " . ($new_time_from ? "'$new_time_from'" : "NULL") . ", 
        " . ($new_time_to ? "'$new_time_to'" : "NULL") . ", 
        file_name, department, chairman, coordinator_name, coordinator_phone, 
        attendees, 'ปกติ', $event_id, created_by, 'ระบบ'
    FROM events WHERE id = $event_id";
    
    if (mysqli_query($connect, $copy_query)) {
        $new_event_id = mysqli_insert_id($connect);
        
        // Update the original event with the new event ID
        mysqli_query($connect, "UPDATE events SET original_event_id = $new_event_id WHERE id = $event_id");
        
        echo json_encode(['success' => true, 'message' => 'เลื่อนงานเรียบร้อยแล้ว']);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสร้างงานใหม่']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตสถานะ']);
}
?>
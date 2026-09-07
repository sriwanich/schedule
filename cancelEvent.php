<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

include 'config.php';

// ตรวจสอบว่ามีการส่ง ID มาใน POST หรือไม่
if (!isset($_POST['event_id'])) {
    $_SESSION['alert_message'] = "ไม่พบข้อมูลงานที่ต้องการยกเลิก";
    $_SESSION['alert_type'] = "danger";
    header("Location: manage");
    exit();
}

$event_id = intval($_POST['event_id']);

// ดึงข้อมูลงานปัจจุบัน
$query = "SELECT * FROM events WHERE id=$event_id";
$result = mysqli_query($connect, $query);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    $_SESSION['alert_message'] = "ไม่พบงานที่ต้องการยกเลิก";
    $_SESSION['alert_type'] = "danger";
    header("Location: manage");
    exit();
}

// รับข้อมูลจากฟอร์ม
$cancel_reason = mysqli_real_escape_string($connect, $_POST['cancel_reason']);

// ดึงข้อมูลผู้ใช้จากตาราง users
$username = $_SESSION['username'];
$user_query = "SELECT first_name, last_name FROM users WHERE username='$username'";
$user_result = mysqli_query($connect, $user_query);
$user = mysqli_fetch_assoc($user_result);
$updated_by = $user['first_name'] . ' ' . $user['last_name'];

// ตรวจสอบการอัปโหลดไฟล์
$cancel_file_name = null;
if (isset($_FILES['cancel_file']) && $_FILES['cancel_file']['error'] == 0) {
    $file = $_FILES['cancel_file'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // อนุญาตเฉพาะไฟล์ PDF และรูปภาพ
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['alert_message'] = "อนุญาตเฉพาะไฟล์ PDF, JPG, PNG, และ WEBP เท่านั้น";
        $_SESSION['alert_type'] = "danger";
        header("Location: editEvent?id=$event_id");
        exit();
    } elseif ($file_size > 15 * 1024 * 1024) {
        $_SESSION['alert_message'] = "ขนาดไฟล์ต้องไม่เกิน 15 MB";
        $_SESSION['alert_type'] = "danger";
        header("Location: editEvent?id=$event_id");
        exit();
    } else {
        // สร้างชื่อไฟล์ใหม่
        $cancel_file_name = uniqid() . "." . $file_ext;

        // อัปโหลดไฟล์
        if (!move_uploaded_file($file_tmp, "uploads/" . $cancel_file_name)) {
            $_SESSION['alert_message'] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
            $_SESSION['alert_type'] = "danger";
            header("Location: editEvent?id=$event_id");
            exit();
        }
    }
}

// อัปเดตสถานะงานเป็น "ยกเลิก"
$update_query = "UPDATE events SET 
    status='ยกเลิก', 
    cancel_reason='$cancel_reason', 
    cancel_file_name='$cancel_file_name', 
    updated_by='$updated_by', 
    updated=CURRENT_TIMESTAMP 
    WHERE id=$event_id";

if (mysqli_query($connect, $update_query)) {
    $_SESSION['alert_message'] = "ยกเลิกงานเรียบร้อยแล้ว";
    $_SESSION['alert_type'] = "success";
} else {
    $_SESSION['alert_message'] = "เกิดข้อผิดพลาดในการยกเลิกงาน: " . mysqli_error($connect);
    $_SESSION['alert_type'] = "danger";
}

// Redirect กลับไปยังหน้าแก้ไขงาน
header("Location: editEvent?id=$event_id");
exit();
?>
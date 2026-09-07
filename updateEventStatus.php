<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// เปิดการแสดงข้อผิดพลาด (สำหรับช่วงพัฒนาเท่านั้น)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'config.php';

$response = ['success' => false, 'message' => 'คำขอไม่ถูกต้อง'];

try {
    // ตรวจสอบ method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('อนุญาตเฉพาะคำขอ POST');
    }

    // ตรวจสอบการล็อกอิน
    if (!isset($_SESSION['username'])) {
        throw new Exception('กรุณาเข้าสู่ระบบก่อน');
    }

    // รับข้อมูลจากฟอร์ม
    $input = $_POST;
    $files = $_FILES;

    // ตรวจสอบ event_id
    if (!isset($input['event_id']) || !is_numeric($input['event_id'])) {
        throw new Exception('รหัสงานไม่ถูกต้อง');
    }

    $event_id = (int)$input['event_id'];
    
    // ดึงข้อมูลผู้ใช้
    $username = $_SESSION['username'];
    $stmt = mysqli_prepare($connect, "SELECT first_name, last_name FROM users WHERE username=?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    
    if (!$user = mysqli_fetch_assoc($user_result)) {
        throw new Exception('ไม่พบข้อมูลผู้ใช้');
    }
    
    $updated_by = $user['first_name'] . ' ' . $user['last_name'];

    // ตรวจสอบ action
    if (isset($input['postpone'])) {
        // การเลื่อนงาน
        if (empty($input['new_date']) && empty($input['no_date'])) {
            throw new Exception('กรุณาระบุวันที่ใหม่หรือเลือกไม่มีกำหนดการ');
        }
        
        $new_date = !empty($input['new_date']) ? mysqli_real_escape_string($connect, $input['new_date']) : null;
        $new_time_from = !empty($input['new_time_from']) ? mysqli_real_escape_string($connect, $input['new_time_from']) : null;
        $new_time_to = !empty($input['new_time_to']) ? mysqli_real_escape_string($connect, $input['new_time_to']) : null;
        $postpone_reason = !empty($input['postpone_reason']) ? mysqli_real_escape_string($connect, $input['postpone_reason']) : null;
        
        // อัปโหลดไฟล์
        $postpone_file_name = null;
        if (isset($files['postpone_file']) && $files['postpone_file']['error'] == 0) {
            $file = $files['postpone_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('อนุญาตเฉพาะไฟล์ PDF, JPG, PNG, และ WEBP เท่านั้น');
            } elseif ($file['size'] > 15 * 1024 * 1024) {
                throw new Exception('ขนาดไฟล์ต้องไม่เกิน 15 MB');
            } else {
                $postpone_file_name = uniqid() . '.' . $file_ext;
                if (!move_uploaded_file($file['tmp_name'], "uploads/" . $postpone_file_name)) {
                    throw new Exception('เกิดข้อผิดพลาดในการอัปโหลดไฟล์');
                }
            }
        }
        
        // อัปเดตสถานะงานเดิม
        $update_query = "UPDATE events SET 
            status='เลื่อน', 
            postpone_date=" . ($new_date ? "'$new_date'" : "NULL") . ", 
            postpone_time_from=" . ($new_time_from ? "'$new_time_from'" : "NULL") . ", 
            postpone_time_to=" . ($new_time_to ? "'$new_time_to'" : "NULL") . ", 
            postpone_reason=" . ($postpone_reason ? "'$postpone_reason'" : "NULL") . ", 
            postpone_file_name=" . ($postpone_file_name ? "'$postpone_file_name'" : "NULL") . ", 
            updated_by='$updated_by', 
            updated=NOW() 
            WHERE id=$event_id";
        
        if (!mysqli_query($connect, $update_query)) {
            throw new Exception('เกิดข้อผิดพลาดในการเลื่อนงาน: ' . mysqli_error($connect));
        }
        
        // สร้างงานใหม่หากไม่ได้เลือก "ยังไม่มีกำหนดการแน่นอน"
        if (!isset($input['no_date']) && $new_date) {
            $query = "SELECT * FROM events WHERE id=$event_id";
            $result = mysqli_query($connect, $query);
            $original_event = mysqli_fetch_assoc($result);
            
            $insert_query = "INSERT INTO events (
                title, location, description, Dress, date, time_from, time_to, 
                file_name, department, chairman, coordinator_name, coordinator_phone, 
                attendees, created_by, updated_by, status, original_event_id
            ) VALUES (
                '" . mysqli_real_escape_string($connect, $original_event['title']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['location']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['description']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['Dress']) . "',
                '$new_date',
                " . ($new_time_from ? "'$new_time_from'" : "NULL") . ",
                " . ($new_time_to ? "'$new_time_to'" : "NULL") . ",
                " . (!empty($original_event['file_name']) ? "'" . mysqli_real_escape_string($connect, $original_event['file_name']) . "'" : "NULL") . ",
                '" . mysqli_real_escape_string($connect, $original_event['department']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['chairman']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['coordinator_name']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['coordinator_phone']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['attendees']) . "',
                '" . mysqli_real_escape_string($connect, $original_event['created_by']) . "',
                '$updated_by',
                'ปกติ',
                $event_id
            )";
            
            if (!mysqli_query($connect, $insert_query)) {
                throw new Exception('เกิดข้อผิดพลาดในการสร้างงานใหม่: ' . mysqli_error($connect));
            }
        }
        
        $response['success'] = true;
        $response['message'] = 'ดำเนินการเลื่อนงานเรียบร้อยแล้ว';
        
    } elseif (isset($input['cancel'])) {
        // การยกเลิกงาน
        if (empty($input['cancel_reason'])) {
            throw new Exception('กรุณาระบุเหตุผลการยกเลิก');
        }
        
        $cancel_reason = mysqli_real_escape_string($connect, $input['cancel_reason']);
        
        // อัปโหลดไฟล์
        $cancel_file_name = null;
        if (isset($files['cancel_file']) && $files['cancel_file']['error'] == 0) {
            $file = $files['cancel_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('อนุญาตเฉพาะไฟล์ PDF, JPG, PNG, และ WEBP เท่านั้น');
            } elseif ($file['size'] > 15 * 1024 * 1024) {
                throw new Exception('ขนาดไฟล์ต้องไม่เกิน 15 MB');
            } else {
                $cancel_file_name = uniqid() . '.' . $file_ext;
                if (!move_uploaded_file($file['tmp_name'], "uploads/" . $cancel_file_name)) {
                    throw new Exception('เกิดข้อผิดพลาดในการอัปโหลดไฟล์');
                }
            }
        }
        
        // อัปเดตสถานะงาน
        $update_query = "UPDATE events SET 
            status='ยกเลิก', 
            cancel_reason=" . ($cancel_reason ? "'$cancel_reason'" : "NULL") . ", 
            cancel_file_name=" . ($cancel_file_name ? "'$cancel_file_name'" : "NULL") . ", 
            updated_by='$updated_by', 
            updated=NOW() 
            WHERE id=$event_id";
        
        if (!mysqli_query($connect, $update_query)) {
            throw new Exception('เกิดข้อผิดพลาดในการยกเลิกงาน: ' . mysqli_error($connect));
        }
        
        $response['success'] = true;
        $response['message'] = 'ดำเนินการยกเลิกงานเรียบร้อยแล้ว';
        
    } elseif (isset($input['restore'])) {
        // การคืนสถานะปกติ
        // ตรวจสอบว่ามีงานใหม่ที่ถูกสร้างจากการเลื่อนหรือไม่
        $check_new_event_query = "SELECT id FROM events WHERE original_event_id=$event_id";
        $check_new_event_result = mysqli_query($connect, $check_new_event_query);
        
        // อัปเดตสถานะงาน
        $update_query = "UPDATE events SET 
            status='ปกติ', 
            postpone_date=NULL, 
            postpone_time_from=NULL, 
            postpone_time_to=NULL, 
            postpone_reason=NULL, 
            postpone_file_name=NULL, 
            cancel_reason=NULL, 
            cancel_file_name=NULL, 
            updated_by='$updated_by', 
            updated=NOW() 
            WHERE id=$event_id";
        
        if (!mysqli_query($connect, $update_query)) {
            throw new Exception('เกิดข้อผิดพลาดในการคืนสถานะงาน: ' . mysqli_error($connect));
        }
        
        // ลบงานใหม่ที่ถูกสร้างจากการเลื่อน (ถ้ามี)
        if (mysqli_num_rows($check_new_event_result) > 0) {
            $new_event = mysqli_fetch_assoc($check_new_event_result);
            $delete_query = "DELETE FROM events WHERE id={$new_event['id']}";
            if (!mysqli_query($connect, $delete_query)) {
                throw new Exception('เกิดข้อผิดพลาดในการลบงานที่เลื่อนไว้: ' . mysqli_error($connect));
            }
        }
        
        $response['success'] = true;
        $response['message'] = 'คืนสถานะงานเป็นปกติเรียบร้อยแล้ว';
        
    } else {
        throw new Exception('ไม่พบการกระทำที่ระบุ');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
?>
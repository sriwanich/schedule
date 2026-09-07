<?php
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

// เชื่อมต่อฐานข้อมูล config.php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['username'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // ตรวจสอบรหัสผ่านเก่า
    $user_query = mysqli_query($connect, "SELECT password FROM users WHERE username='$username'");
    $user_data = mysqli_fetch_assoc($user_query);
    if (password_verify($old_password, $user_data['password'])) {
        // อัปเดตชื่อและนามสกุล
        mysqli_query($connect, "UPDATE users SET first_name='$first_name', last_name='$last_name' WHERE username='$username'");

        // อัปเดตรหัสผ่านใหม่ (ถ้ามีการกรอก)
        if (!empty($new_password) && $new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            mysqli_query($connect, "UPDATE users SET password='$hashed_password' WHERE username='$username'");
            $_SESSION['success'] = "อัปเดตข้อมูลและรหัสผ่านสำเร็จ";
        } else {
            $_SESSION['success'] = "อัปเดตข้อมูลสำเร็จ";
        }
    } else {
        $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
    }

    header("Location: m_old_events");
    exit();
}

// ดึงข้อมูลผู้ใช้จากตาราง users
$username = $_SESSION['username'];
$user_query = mysqli_query($connect, "SELECT first_name, last_name, role FROM users WHERE username='$username'");
$user_data = mysqli_fetch_assoc($user_query);
$first_name = $user_data['first_name'];
$last_name = $user_data['last_name'];
$role = $user_data['role'];

// อำหนดชื่อเดือนในภาษาไทย
$thai_months_full = [
    'Jan' => 'มกราคม', 'Feb' => 'กุมภาพันธ์', 'Mar' => 'มีนาคม', 'Apr' => 'เมษายน',
    'May' => 'พฤษภาคม', 'Jun' => 'มิถุนายน', 'Jul' => 'กรกฎาคม', 'Aug' => 'สิงหาคม',
    'Sep' => 'กันยายน', 'Oct' => 'ตุลาคม', 'Nov' => 'พฤศจิกายน', 'Dec' => 'ธันวาคม'
];

// ลบข้อมูลการนัดหมาย
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($connect, "DELETE FROM events WHERE id=$id");
    $_SESSION['success'] = "ลบการนัดหมายสำเร็จ";
    header("Location: m_old_events");
    exit();
}

// ดึงวันทีปัจจุบัน
$current_date = date('Y-m-d');
$current_day = date('l');
$thai_days_full = [
    'Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ',
    'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'
];
$thai_day_full = $thai_days_full[$current_day];
$current_date_thai = "วัน$thai_day_full ที่ " . date('j') . ' ' . 
                    $thai_months_full[date('M')] . ' พ.ศ. ' . (date('Y') + 543);

// ตรวจสอบคำค้นหา
$search_keyword = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_keyword = mysqli_real_escape_string($connect, $_GET['search']);
}

// ========== Pagination Setup ==========
$records_per_page = 8;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ประวัติการนัดหมาย - มือถือ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@200;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0a9396;
            --secondary-color: #94d2bd;
            --accent-color: #ee9b00;
            --dark-color: #005f73;
            --light-color: #e9f5f5;
            --danger-color: #d90429;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            background-color: var(--light-color); 
            font-family: 'Anuphan', sans-serif; 
            color: var(--dark-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0;
        }
        
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            color: white;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .mobile-header img {
            height: 40px;
            margin-bottom: 8px;
        }
        
        .mobile-header h1 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .mobile-header p {
            font-size: 0.8rem;
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        .welcome-info {
            font-size: 0.75rem;
            margin-top: 8px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            cursor: pointer;
        }
        
        .container-fluid {
            padding: 12px;
            flex: 1;
            overflow-y: auto;
        }
        
        .current-date {
            text-align: center;
            margin: 12px 0;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--dark-color);
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .action-buttons .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 10px;
            font-size: 0.85rem;
            border-radius: 8px;
            white-space: nowrap;
        }
        
        .action-buttons .btn i {
            font-size: 1rem;
        }
        
        .btn-primary { 
            background-color: var(--primary-color); 
            border: none;
            color: white;
        }
        
        .btn-primary:active { 
            background-color: var(--dark-color);
        }
        
        .btn-danger { 
            background-color: var(--danger-color);
            border: none;
            color: white;
        }
        
        .btn-danger:active { 
            background-color: #c1121f;
        }
        
        .btn-secondary { 
            background-color: #6c757d;
            border: none;
            color: white;
        }
        
        .btn-secondary:active { 
            background-color: #5a6268;
        }
        
        .search-box {
            margin-bottom: 15px;
        }
        
        .search-box form {
            display: flex;
            gap: 8px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .search-box button {
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .filter-section {
            background-color: white;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .filter-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--primary-color);
            font-size: 0.95rem;
        }
        
        .filter-section .form-group {
            margin-bottom: 10px;
        }
        
        .filter-section label {
            font-size: 0.85rem;
            font-weight: 500;
            display: block;
            margin-bottom: 5px;
        }
        
        .filter-section input,
        .filter-section select {
            width: 100%;
            padding: 8px;
            font-size: 0.9rem;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        
        .filter-buttons button,
        .filter-buttons a {
            flex: 1;
            padding: 8px;
            font-size: 0.85rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
        }
        
        .event-card {
            background: white;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .event-card:active {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        
        .event-card.cancelled {
            border-left-color: var(--danger-color);
            opacity: 0.8;
        }
        
        .event-card.postponed {
            border-left-color: var(--accent-color);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .event-date-time {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .event-date {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.95rem;
        }
        
        .event-time {
            font-size: 0.85rem;
            color: #666;
        }
        
        .event-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .event-status.success {
            background-color: #2b9348;
        }
        
        .event-status.cancelled {
            background-color: var(--danger-color);
        }
        
        .event-status.postponed {
            background-color: var(--accent-color);
            color: var(--dark-color);
        }
        
        .event-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 8px;
            word-break: break-word;
        }
        
        .event-detail {
            display: flex;
            align-items: start;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .event-detail i {
            color: var(--primary-color);
            min-width: 18px;
            margin-top: 2px;
        }
        
        .event-detail span {
            flex: 1;
            word-break: break-word;
        }
        
        .event-attachment {
            display: inline-block;
            padding: 6px 10px;
            background-color: #f0f0f0;
            border-radius: 6px;
            text-decoration: none;
            color: var(--primary-color);
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        .event-attachment:active {
            background-color: var(--secondary-color);
        }
        
        .no-attachment {
            color: #999;
            font-style: italic;
            font-size: 0.85rem;
        }
        
        .no-events {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .no-events i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        #error-message, #success-message {
            display: none;
            position: fixed;
            top: 70px;
            left: 12px;
            right: 12px;
            padding: 12px;
            border-radius: 8px;
            z-index: 1000;
            font-size: 0.9rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        #error-message {
            background-color: var(--danger-color);
            color: white;
        }
        
        #success-message {
            background-color: #2b9348;
            color: white;
        }
        
        .pagination-mobile {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .pagination-mobile a,
        .pagination-mobile span {
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            text-decoration: none;
        }
        
        .pagination-mobile a {
            background-color: white;
            color: var(--primary-color);
            border: 1px solid #ddd;
        }
        
        .pagination-mobile a:active {
            background-color: var(--secondary-color);
        }
        
        .pagination-mobile span.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        footer {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 12px;
            margin-top: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- แสดงข้อความแจ้งเตือนข้อผิดพลาด -->
    <?php if (isset($_SESSION['error'])): ?>
        <div id="error-message"><?php echo $_SESSION['error']; ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- แสดงข้อความแจ้งเตือนสำเร็จ -->
    <?php if (isset($_SESSION['success'])): ?>
        <div id="success-message"><?php echo $_SESSION['success']; ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="mobile-header">
        <img src="/images/logo.jpg" alt="Logo">
        <h1>ประวัติการนัดหมาย</h1>
        <div class="welcome-info" onclick="openProfileModal()">
            <i class="bi bi-person-circle"></i> 
            <?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?>
            <span class="badge <?= $role === 'user' ? 'bg-warning text-dark' : 'bg-danger' ?>" style="font-size: 0.7rem;">
                <?= $role === 'user' ? 'ผู้ใช้ทั่วไป' : 'ผู้ดูแลระบบ' ?>
            </span>
        </div>
    </div>

    <div class="container-fluid">
        <!-- แสดงวันที่ปัจจุบัน -->
        <div class="current-date">
            <i class="bi bi-calendar-date"></i> <?= htmlspecialchars($current_date_thai) ?>
        </div>
        
        <!-- ปุ่มการกระทำ -->
        <div class="action-buttons">
            <a href="mobile" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> ย้อนกลับ
            </a>
            <a href="addEvent" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> เพิ่มนัดหมาย
            </a>
            <a href="logout" class="btn btn-danger" style="grid-column: 1 / -1;">
                <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
            </a>
        </div>
        
        <!-- ส่วนค้นหา -->
        <div class="search-box">
            <form method="get">
                <input type="text" id="searchInput" name="search" 
                       placeholder="ค้นหาการนัดหมาย..." value="<?= htmlspecialchars($search_keyword) ?>">
                <button type="submit" style="width: auto;">
                    <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($search_keyword)): ?>
                    <a href="m_old_events" style="padding: 10px 15px; background-color: #6c757d; color: white; border-radius: 8px; text-decoration: none;">ล้าง</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- แสดงรายการการนัดหมาย -->
        <div id="events-container">
            <?php
            // สร้างเงื่อนไขตัวกรอง
            $filter_conditions = [];
            
            if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
                $start_date = mysqli_real_escape_string($connect, $_GET['start_date']);
                $filter_conditions[] = "date >= '$start_date'";
            }
            
            if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
                $end_date = mysqli_real_escape_string($connect, $_GET['end_date']);
                $filter_conditions[] = "date <= '$end_date'";
            }
            
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $status = mysqli_real_escape_string($connect, $_GET['status']);
                if ($status === 'เสร็จสิ้น') {
                    $filter_conditions[] = "(status IS NULL OR status NOT IN ('ยกเลิก', 'เลื่อน'))";
                } else {
                    $filter_conditions[] = "status = '$status'";
                }
            }
            
            // เงื่อนไขคำค้นหา
            if (!empty($search_keyword)) {
                $filter_conditions[] = "title LIKE '%$search_keyword%'";
            }
            
            // สร้าง query สำหรับนับจำนวนรายการ
            $count_query = "SELECT COUNT(*) as total FROM events 
                           WHERE (date < CURDATE() OR 
                                 (date = CURDATE() AND time_to < CURTIME()) OR
                                 status IN ('ยกเลิก', 'เลื่อน'))";
            
            if (!empty($filter_conditions)) {
                $count_query .= " AND " . implode(" AND ", $filter_conditions);
            }
            
            $count_result = mysqli_query($connect, $count_query);
            $total_records = mysqli_fetch_assoc($count_result)['total'];
            $total_pages = ceil($total_records / $records_per_page);
            
            // สร้าง query ตามเงื่อนไขตัวกรอง
            $base_query = "SELECT * FROM events 
                          WHERE (date < CURDATE() OR 
                                (date = CURDATE() AND time_to < CURTIME()) OR
                                status IN ('ยกเลิก', 'เลื่อน'))";
            
            if (!empty($filter_conditions)) {
                $base_query .= " AND " . implode(" AND ", $filter_conditions);
            }
            
            $base_query .= " ORDER BY date DESC, time_from DESC LIMIT $offset, $records_per_page";
            
            $dbquery = mysqli_query($connect, $base_query);
            
            if (mysqli_num_rows($dbquery) === 0): ?>
                <div class="no-events">
                    <i class="bi bi-inbox"></i>
                    <p><?= !empty($search_keyword) ? 'ไม่พบการนัดหมายที่ตรงกับคำค้นหา "' . htmlspecialchars($search_keyword) . '"' : 'ไม่มีการนัดหมายในอดีต' ?></p>
                </div>
            <?php else:
                while ($result = mysqli_fetch_array($dbquery)) {
                    // จัดรูปแบบวันที่และเวลา
                    if ($result["date"] == '0000-00-00') {
                        $formatted_date = 'ยังไม่มีวันอ่าน';
                        $formatted_time = '-';
                    } else {
                        $date = date('j', strtotime($result["date"]));
                        $month = date('M', strtotime($result["date"]));
                        $year = date('Y', strtotime($result["date"])) + 543;
                        $formatted_date = $date . ' ' . $thai_months_full[$month] . ' ' . $year;
                        $formatted_time = date('H:i', strtotime($result["time_from"]));
                    }
                    
                    // กำหนด class และข้อความสถานะ
                    $status_text = 'เสร็จสิ้น';
                    $status_class = 'success';
                    $card_class = '';
                    
                    if ($result["status"] === 'ยกเลิก') {
                        $status_text = 'ยกเลิก';
                        $status_class = 'cancelled';
                        $card_class = 'cancelled';
                    } elseif ($result["status"] === 'เลื่อน') {
                        $status_text = 'เลื่อน';
                        $status_class = 'postponed';
                        $card_class = 'postponed';
                    }
                    
                    // อธิบายรายละเอียด
                    $description = !empty($result["description"]) ? htmlspecialchars($result["description"]) : '-';
                    
                    // จัดการไฟล์แนบ
                    $file_attachment = '';
                    if (!empty($result["file_name"])) {
                        $file_path = 'uploads/' . htmlspecialchars($result["file_name"]);
                        $file_extension = strtolower(pathinfo($result["file_name"], PATHINFO_EXTENSION));
                        
                        $file_icon = 'bi-file-earmark';
                        if (in_array($file_extension, ['pdf'])) {
                            $file_icon = 'bi-file-earmark-pdf';
                        } elseif (in_array($file_extension, ['doc', 'docx'])) {
                            $file_icon = 'bi-file-earmark-word';
                        } elseif (in_array($file_extension, ['xls', 'xlsx'])) {
                            $file_icon = 'bi-file-earmark-excel';
                        }
                        
                        $file_attachment = '<a href="' . $file_path . '" target="_blank" class="event-attachment">
                            <i class="bi ' . $file_icon . '"></i> ดูไฟล์
                        </a>';
                    }
            ?>
                <div class="event-card <?= $card_class ?>">
                    <div class="event-header">
                        <div class="event-date-time">
                            <div class="event-date">
                                <i class="bi bi-calendar-event"></i> <?= $formatted_date ?>
                            </div>
                            <div class="event-time">
                                <i class="bi bi-clock"></i> <?= $formatted_time ?>
                            </div>
                        </div>
                        <span class="event-status <?= $status_class ?>"><?= $status_text ?></span>
                    </div>
                    
                    <div class="event-title"><?= htmlspecialchars($result["title"]) ?></div>
                    
                    <div class="event-detail">
                        <i class="bi bi-geo-alt"></i>
                        <span><?= htmlspecialchars($result["location"]) ?></span>
                    </div>
                    
                    <div class="event-detail">
                        <i class="bi bi-info-circle"></i>
                        <span>
                            <?php
                            $descriptions = explode(' ', $description);
                            foreach ($descriptions as $desc) {
                                echo '<span class="badge" style="background-color: #005f73; margin-right: 4px; margin-bottom: 4px; display: inline-block;">' . htmlspecialchars($desc) . '</span>';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($file_attachment)): ?>
                        <div style="margin-top: 8px;">
                            <?= $file_attachment ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($role === 'admin'): ?>
                        <div style="display: flex; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                            <a href="editEvent?id=<?= $result['id'] ?>" class="btn btn-sm" style="flex: 1; background-color: var(--secondary-color); color: var(--dark-color); border: none; padding: 8px; border-radius: 6px; text-decoration: none; text-align: center; font-size: 0.85rem;">
                                <i class="bi bi-pencil"></i> แก้ไข
                            </a>
                            <a href="?delete=<?= $result['id'] ?>" class="btn btn-sm" style="flex: 1; background-color: var(--danger-color); color: white; border: none; padding: 8px; border-radius: 6px; text-decoration: none; text-align: center; font-size: 0.85rem; onclick="return confirm('ยืนยันการลบการนัดหมายนี้?');">
                                <i class="bi bi-trash"></i> ลบ
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php } endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_records > 0): ?>
        <div class="pagination-mobile">
            <?php if ($current_page > 1): ?>
                <a href="?page=<?= $current_page - 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?><?= isset($_GET['start_date']) ? '&start_date=' . urlencode($_GET['start_date']) : '' ?><?= isset($_GET['end_date']) ? '&end_date=' . urlencode($_GET['end_date']) : '' ?><?= isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '' ?>">
                    <i class="bi bi-chevron-left"></i> ก่อนหน้า
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            
            if ($start_page > 1) {
                echo '<a href="?page=1' . (!empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '') . (isset($_GET['start_date']) ? '&start_date=' . urlencode($_GET['start_date']) : '') . (isset($_GET['end_date']) ? '&end_date=' . urlencode($_GET['end_date']) : '') . (isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '') . '">1</a>';
                if ($start_page > 2) {
                    echo '<span>...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = ($i == $current_page) ? 'active' : '';
                echo '<span class="' . $active . '">' . $i . '</span>';
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span>...</span>';
                }
                echo '<a href="?page=' . $total_pages . (!empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '') . (isset($_GET['start_date']) ? '&start_date=' . urlencode($_GET['start_date']) : '') . (isset($_GET['end_date']) ? '&end_date=' . urlencode($_GET['end_date']) : '') . (isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '') . '">' . $total_pages . '</a>';
            }
            ?>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?= $current_page + 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?><?= isset($_GET['start_date']) ? '&start_date=' . urlencode($_GET['start_date']) : '' ?><?= isset($_GET['end_date']) ? '&end_date=' . urlencode($_GET['end_date']) : '' ?><?= isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : '' ?>">
                    ถัดไป <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin: 15px 0; font-size: 0.85rem; color: #666;">
            <i class="bi bi-info-circle"></i> 
            แสดง <?= min($offset + 1, $total_records) ?> - <?= min($offset + $records_per_page, $total_records) ?> 
            จากทั้งหมด <?= $total_records ?> รายการ
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        © 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    </footer>

    <!-- Modal แก้ไขโปรไฟล์ -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%); color: white; border: none;">
                    <h5 class="modal-title" id="profileModalLabel">
                        <i class="bi bi-person-gear"></i> แก้ไขข้อมูลส่วนตัว
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 15px;">
                    <form id="profileForm" method="POST" action="">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">ชื่อ</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($first_name) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">นามสกุล</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($last_name) ?>" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="old_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                            <input type="password" class="form-control" id="old_password" name="old_password" required>
                            <small class="form-text text-muted">กรอกรหัสผ่านปัจจุบันเพื่อยืนยันตัวตน</small>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <small class="form-text text-muted">เว้นว่างไว้หากไม่ต้องเปลี่ยนรหัสผ่าน</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid #eee; margin-top: 20px;">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> ปิด
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openProfileModal() {
            const profileModal = new bootstrap.Modal(document.getElementById('profileModal'));
            profileModal.show();
        }

        document.getElementById('profileForm').addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword || confirmPassword) {
                if (newPassword !== confirmPassword) {
                    alert('รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน');
                    event.preventDefault();
                    return false;
                }
            }
        });

        window.onload = function() {
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');
            
            if (errorMessage) {
                errorMessage.style.display = 'block';
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    setTimeout(() => errorMessage.style.display = 'none', 300);
                }, 4000);
            }

            if (successMessage) {
                successMessage.style.display = 'block';
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.style.display = 'none', 300);
                }, 4000);
            }

            const urlParams = new URLSearchParams(window.location.search);
            const startDate = urlParams.get('start_date');
            const endDate = urlParams.get('end_date');
            const status = urlParams.get('status');
            
            if (startDate) document.querySelector('input[name="start_date"]').value = startDate;
            if (endDate) document.querySelector('input[name="end_date"]').value = endDate;
            if (status) document.querySelector('select[name="status"]').value = status;
        }
    </script>
</body>
</html>
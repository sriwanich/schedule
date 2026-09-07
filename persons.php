<?php
include 'config.php';

// ตั้งค่าโซนเวลาและภาษารายงานข้อผิดพลาด
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ระบบแปลงวันที่เป็นภาษาไทย
$thai_months_full = [
    'Jan' => 'มกราคม', 'Feb' => 'กุมภาพันธ์', 'Mar' => 'มีนาคม',
    'Apr' => 'เมษายน', 'May' => 'พฤษภาคม', 'Jun' => 'มิถุนายน',
    'Jul' => 'กรกฎาคม', 'Aug' => 'สิงหาคม', 'Sep' => 'กันยายน',
    'Oct' => 'ตุลาคม', 'Nov' => 'พฤศจิกายน', 'Dec' => 'ธันวาคม'
];

// ฟังก์ชันลบข้อมูล
if (isset($_POST['delete_person'])) {
    $person_id = intval($_POST['person_id']);
    
    try {
        $delete_query = "DELETE FROM responsible_persons WHERE id = ?";
        $stmt = mysqli_prepare($connect, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $person_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'ลบข้อมูลสำเร็จ',
                        text: 'ข้อมูลบุคลากรถูกลบเรียบร้อยแล้ว',
                        confirmButtonColor: '#0a9396'
                    }).then(() => {
                        window.location.href = 'persons.php';
                    });
                });
            </script>";
        }
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถลบข้อมูลได้',
                    confirmButtonColor: '#0a9396'
                });
            });
        </script>";
    }
}

// ฟังก์ชันเพิ่มหรือแก้ไขข้อมูล
if (isset($_POST['save_person'])) {
    $person_id = isset($_POST['person_id']) ? intval($_POST['person_id']) : 0;
    $name = mysqli_real_escape_string($connect, $_POST['name']);
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $is_office_staff = isset($_POST['is_office_staff']) ? 1 : 0;
    $observance = intval($_POST['observance']);
    $group_num = intval($_POST['group']);
    $leave_type = !empty($_POST['leave_type']) ? mysqli_real_escape_string($connect, $_POST['leave_type']) : NULL;
    $location = !empty($_POST['location']) ? mysqli_real_escape_string($connect, $_POST['location']) : NULL;
    $start_datetime = !empty($_POST['start_datetime']) ? mysqli_real_escape_string($connect, $_POST['start_datetime']) : NULL;
    $end_datetime = !empty($_POST['end_datetime']) ? mysqli_real_escape_string($connect, $_POST['end_datetime']) : NULL;
    $details = !empty($_POST['details']) ? mysqli_real_escape_string($connect, $_POST['details']) : NULL;
    
    try {
        if ($person_id > 0) {
            // แก้ไขข้อมูล
            $query = "UPDATE responsible_persons SET 
                      name = ?, email = ?, is_office_staff = ?, observance = ?, 
                      `group` = ?, leave_type = ?, location = ?, 
                      start_datetime = ?, end_datetime = ?, details = ? 
                      WHERE id = ?";
            $stmt = mysqli_prepare($connect, $query);
            mysqli_stmt_bind_param($stmt, "ssiissssssi", $name, $email, $is_office_staff, 
                                 $observance, $group_num, $leave_type, $location, 
                                 $start_datetime, $end_datetime, $details, $person_id);
        } else {
            // เพิ่มข้อมูลใหม่
            $query = "INSERT INTO responsible_persons 
                      (name, email, is_office_staff, observance, `group`, leave_type, 
                       location, start_datetime, end_datetime, details) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($connect, $query);
            mysqli_stmt_bind_param($stmt, "ssiissssss", $name, $email, $is_office_staff, 
                                 $observance, $group_num, $leave_type, $location, 
                                 $start_datetime, $end_datetime, $details);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $message = $person_id > 0 ? 'แก้ไขข้อมูลสำเร็จ' : 'เพิ่มข้อมูลสำเร็จ';
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: '$message',
                        text: 'ข้อมูลบุคลากรถูกบันทึกเรียบร้อยแล้ว',
                        confirmButtonColor: '#0a9396'
                    }).then(() => {
                        window.location.href = 'persons.php';
                    });
                });
            </script>";
        }
    } catch (Exception $e) {
        error_log("Save error: " . $e->getMessage());
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถบันทึกข้อมูลได้',
                    confirmButtonColor: '#0a9396'
                });
            });
        </script>";
    }
}

// ดึงข้อมูลบุคลากรทั้งหมด
$search = isset($_GET['search']) ? mysqli_real_escape_string($connect, $_GET['search']) : '';
$filter_observance = isset($_GET['observance']) ? intval($_GET['observance']) : -1;
$filter_group = isset($_GET['group']) ? intval($_GET['group']) : -1;

$query = "SELECT * FROM responsible_persons WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $types .= "ss";
}

if ($filter_observance >= 0) {
    $query .= " AND observance = ?";
    $params[] = &$filter_observance;
    $types .= "i";
}

if ($filter_group >= 0) {
    $query .= " AND `group` = ?";
    $params[] = &$filter_group;
    $types .= "i";
}

$query .= " ORDER BY name ASC";

try {
    $stmt = mysqli_prepare($connect, $query);
    if (!empty($types)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} catch (Exception $e) {
    error_log("Query error: " . $e->getMessage());
    $result = false;
}

// ดึงข้อมูล observance_options
$observance_options = [];
try {
    $obs_query = "SELECT id, Name FROM observance_options ORDER BY id";
    $obs_result = mysqli_query($connect, $obs_query);
    while ($row = mysqli_fetch_assoc($obs_result)) {
        $observance_options[$row['id']] = $row['Name'];
    }
} catch (Exception $e) {
    error_log("Observance query error: " . $e->getMessage());
}

// สถิติข้อมูล
$stats = [
    'total' => 0,
    'office_staff' => 0,
    'on_leave' => 0
];

try {
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(is_office_staff) as office_staff,
                    SUM(CASE WHEN leave_type IS NOT NULL AND start_datetime <= NOW() AND end_datetime >= NOW() THEN 1 ELSE 0 END) as on_leave
                    FROM responsible_persons";
    $stats_result = mysqli_query($connect, $stats_query);
    $stats = mysqli_fetch_assoc($stats_result);
} catch (Exception $e) {
    error_log("Stats query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <title>จัดการข้อมูลบุคลากร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@100;200;300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #0a9396;
            --secondary-color: #005f73;
            --danger-color: #e74a3b;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --info-color: #4e73df;
            --light-bg: #e9f5f5;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --card-shadow-hover: 0 4px 16px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Anuphan', sans-serif;
            font-weight: 300;
            color: var(--secondary-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* ========== HEADER ========== */
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header h1 {
            font-size: clamp(1.25rem, 4vw, 1.8rem);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .header p {
            margin: 0.25rem 0 0 0;
            font-size: clamp(0.75rem, 2vw, 0.9rem);
            opacity: 0.95;
        }
        
        /* ========== CONTAINER ========== */
        .container-fluid {
            max-width: 1400px;
            padding: 1rem;
            flex: 1;
            margin: 0 auto;
            width: 100%;
        }
        
        /* ========== STATS CARDS ========== */
        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--card-shadow);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.75rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.75rem;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--light-bg) 0%, #f8f9fa 100%);
            transition: var(--transition);
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-item i {
            font-size: clamp(1.25rem, 4vw, 1.5rem);
            color: var(--primary-color);
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .stat-item .stat-number {
            font-size: clamp(1.25rem, 5vw, 1.5rem);
            font-weight: 600;
            color: var(--secondary-color);
            line-height: 1.2;
        }
        
        .stat-item .stat-label {
            font-size: clamp(0.7rem, 2vw, 0.8rem);
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        /* ========== SEARCH & FILTER ========== */
        .search-filter-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--card-shadow);
        }
        
        .search-filter-card .form-control,
        .search-filter-card .form-select {
            font-size: 0.9rem;
            height: 42px;
        }
        
        /* ========== PERSON CARDS ========== */
        .person-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary-color);
            position: relative;
        }
        
        .person-card:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }
        
        .person-card.on-leave {
            border-left-color: var(--danger-color);
            background: linear-gradient(to right, #fff5f5 0%, white 5%);
        }
        
        .person-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px dotted var(--light-bg);
            gap: 0.5rem;
        }
        
        .person-name {
            font-size: clamp(1rem, 3vw, 1.2rem);
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            flex: 1;
            word-break: break-word;
        }
        
        .person-name i {
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .person-actions {
            display: flex;
            gap: 0.35rem;
            flex-shrink: 0;
        }
        
        .btn-action {
            padding: 0.4rem 0.65rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
        }
        
        .btn-action i {
            font-size: 0.95rem;
        }
        
        .btn-edit {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-edit:hover {
            background-color: var(--secondary-color);
            transform: scale(1.05);
        }
        
        .btn-delete {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
            transform: scale(1.05);
        }
        
        .person-details {
            display: grid;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .detail-row {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            word-break: break-word;
        }
        
        .detail-icon {
            color: var(--primary-color);
            min-width: 20px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .detail-row > span {
            flex: 1;
        }
        
        /* ========== BADGES ========== */
        .badge {
            padding: 0.3rem 0.65rem;
            border-radius: 12px;
            font-size: clamp(0.7rem, 2vw, 0.75rem);
            font-weight: 400;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .badge-office {
            background-color: var(--info-color);
            color: white;
        }
        
        .badge-leave {
            background-color: var(--danger-color);
            color: white;
        }
        
        .badge-observance {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-group {
            background-color: var(--warning-color);
            color: #333;
        }
        
        /* ========== LEAVE SECTION ========== */
        .leave-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
            padding: 0.75rem;
            border-radius: 10px;
            margin-top: 0.75rem;
            border-left: 3px solid var(--danger-color);
        }
        
        .leave-info-title {
            font-weight: 600;
            color: var(--danger-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.95rem;
        }
        
        /* ========== FLOATING BUTTONS ========== */
        .float-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.85rem 1.25rem;
            border-radius: 50px;
            font-size: clamp(0.85rem, 2.5vw, 1rem);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 99;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .float-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.3);
        }
        
        .back-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.15rem;
            border-radius: 50px;
            font-size: clamp(0.85rem, 2.5vw, 1rem);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 99;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .back-btn:hover {
            background-color: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.3);
        }
        
        /* ========== MODAL ========== */
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1rem 1.25rem;
        }
        
        .modal-title {
            font-weight: 600;
            font-size: clamp(1rem, 3vw, 1.25rem);
        }
        
        .modal-body {
            padding: 1.25rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--light-bg);
            border-radius: 8px;
            padding: 0.65rem;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(10, 147, 150, 0.25);
        }
        
        .form-check-input {
            border: 2px solid var(--primary-color);
            width: 1.25rem;
            height: 1.25rem;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            font-weight: 400;
            margin-left: 0.35rem;
        }
        
        /* ========== BUTTONS ========== */
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 8px;
            font-weight: 400;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: clamp(3rem, 10vw, 4rem);
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .empty-state h5 {
            font-size: clamp(1rem, 3vw, 1.25rem);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
        }
        
        /* ========== FOOTER ========== */
        footer {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 1rem;
            margin-top: auto;
            font-size: clamp(0.75rem, 2vw, 0.9rem);
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .header {
                padding: 0.85rem;
            }
            
            .container-fluid {
                padding: 0.75rem;
            }
            
            .stats-card {
                grid-template-columns: 1fr;
                gap: 0.5rem;
                padding: 0.75rem;
            }
            
            .person-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .person-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .btn-action {
                flex: 1;
                justify-content: center;
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            
            .float-btn {
                bottom: 15px;
                right: 15px;
                padding: 0.7rem 1rem;
                font-size: 0.85rem;
            }
            
            .float-btn span {
                display: none;
            }
            
            .back-btn {
                bottom: 15px;
                left: 15px;
                padding: 0.7rem 1rem;
            }
            
            .back-btn span {
                display: none;
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .search-filter-card .btn {
                padding: 0.65rem;
            }
        }
        
        @media (max-width: 480px) {
            .person-name {
                font-size: 1rem;
            }
            
            .badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
            }
            
            .detail-row {
                font-size: 0.85rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        
        @media (min-width: 1200px) {
            .container-fluid {
                padding: 1.5rem 2rem;
            }
            
            .stats-card {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* ========== UTILITIES ========== */
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .text-danger {
            color: var(--danger-color) !important;
        }
        
        .text-success {
            color: var(--success-color) !important;
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--light-bg);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body>
    <!-- ส่วนหัวเว็บ -->
    <div class="header">
        <div class="header-content">
            <h1>
                <i class="bi bi-people-fill"></i>
                <span>จัดการข้อมูลบุคลากร</span>
            </h1>
            <p>ระบบจัดการข้อมูลเจ้าหน้าที่และผู้รับผิดชอบ</p>
        </div>
    </div>

    <!-- ส่วนเนื้อหา -->
    <div class="container-fluid">
        <!-- สถิติ -->
        <div class="stats-card">
            <div class="stat-item">
                <i class="bi bi-people"></i>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">บุคลากรทั้งหมด</div>
            </div>
            <div class="stat-item">
                <i class="bi bi-building"></i>
                <div class="stat-number"><?= $stats['office_staff'] ?></div>
                <div class="stat-label">เจ้าหน้าที่สำนักงาน</div>
            </div>
            <div class="stat-item">
                <i class="bi bi-calendar-x"></i>
                <div class="stat-number"><?= $stats['on_leave'] ?></div>
                <div class="stat-label">ลาในขณะนี้</div>
            </div>
        </div>

        <!-- ค้นหาและกรอง -->
        <div class="search-filter-card">
            <form method="GET" action="persons.php">
                <div class="row g-2">
                    <div class="col-12 col-md-5">
                        <input type="text" name="search" class="form-control" 
                               placeholder="ค้นหาชื่อหรืออีเมล..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="observance" class="form-select">
                            <option value="-1">ทุกหน้าที่</option>
                            <?php foreach ($observance_options as $id => $name): ?>
                                <option value="<?= $id ?>" <?= $filter_observance == $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="group" class="form-select">
                            <option value="-1">ทุกกลุ่ม</option>
                            <option value="0" <?= $filter_group === 0 ? 'selected' : '' ?>>กลุ่ม 0</option>
                            <option value="1" <?= $filter_group === 1 ? 'selected' : '' ?>>กลุ่ม 1</option>
                            <option value="2" <?= $filter_group === 2 ? 'selected' : '' ?>>กลุ่ม 2</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> ค้นหา
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- รายการบุคลากร -->
        <div id="persons-container">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php 
                $persons_array = [];
                while ($person = mysqli_fetch_assoc($result)): 
                    $persons_array[] = $person;
                    $is_on_leave = false;
                    if (!empty($person['leave_type']) && !empty($person['start_datetime']) && !empty($person['end_datetime'])) {
                        $now = new DateTime();
                        $start = new DateTime($person['start_datetime']);
                        $end = new DateTime($person['end_datetime']);
                        $is_on_leave = ($now >= $start && $now <= $end);
                    }
                ?>
                <div class="person-card <?= $is_on_leave ? 'on-leave' : '' ?>">
                    <div class="person-header">
                        <div class="person-name">
                            <i class="bi bi-person-circle"></i>
                            <span><?= htmlspecialchars($person['name']) ?></span>
                            <?php if ($person['is_office_staff']): ?>
                                <span class="badge badge-office">
                                    <i class="bi bi-building"></i> เจ้าหน้าที่สำนักงาน
                                </span>
                            <?php endif; ?>
                            <?php if ($is_on_leave): ?>
                                <span class="badge badge-leave">
                                    <i class="bi bi-calendar-x"></i> ลา
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="person-actions">
                            <button class="btn-action btn-edit" onclick="editPerson(<?= $person['id'] ?>)" aria-label="แก้ไข">
                                <i class="bi bi-pencil"></i>
                                <span>แก้ไข</span>
                            </button>
                            <button class="btn-action btn-delete" onclick="deletePerson(<?= $person['id'] ?>, '<?= htmlspecialchars($person['name'], ENT_QUOTES) ?>')" aria-label="ลบ">
                                <i class="bi bi-trash"></i>
                                <span>ลบ</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="person-details">
                        <?php if (!empty($person['email'])): ?>
                        <div class="detail-row">
                            <i class="bi bi-envelope detail-icon"></i>
                            <span><?= htmlspecialchars($person['email']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($observance_options[$person['observance']])): ?>
                        <div class="detail-row">
                            <i class="bi bi-briefcase detail-icon"></i>
                            <span>
                                พิธีการ: 
                                <span class="badge badge-observance">
                                    <?= htmlspecialchars($observance_options[$person['observance']]) ?>
                                </span>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <i class="bi bi-diagram-3 detail-icon"></i>
                            <span>
                                กลุ่ม/ฝ่าย: 
                                <span class="badge badge-group">กลุ่ม <?= $person['group'] ?></span>
                            </span>
                        </div>
                        
                        <?php if ($is_on_leave): ?>
                        <div class="leave-section">
                            <div class="leave-info-title">
                                <i class="bi bi-info-circle"></i>
                                <span>ข้อมูลการลาและการไปราชการ</span>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-clipboard detail-icon"></i>
                                <span>ประเภท: <?= htmlspecialchars($person['leave_type']) ?></span>
                            </div>
                            <?php if (!empty($person['location'])): ?>
                            <div class="detail-row">
                                <i class="bi bi-geo-alt detail-icon"></i>
                                <span><?= htmlspecialchars($person['location']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <i class="bi bi-calendar-range detail-icon"></i>
                                <span>
                                    <?= date('d/m/Y H:i', strtotime($person['start_datetime'])) ?> - 
                                    <?= date('d/m/Y H:i', strtotime($person['end_datetime'])) ?>
                                </span>
                            </div>
                            <?php if (!empty($person['details'])): ?>
                            <div class="detail-row">
                                <i class="bi bi-chat-text detail-icon"></i>
                                <span><?= htmlspecialchars($person['details']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h5>ไม่พบข้อมูลบุคลากร</h5>
                    <p>ลองค้นหาด้วยคำค้นอื่น หรือเพิ่มบุคลากรใหม่</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไขข้อมูล -->
    <div class="modal fade" id="personModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">เพิ่มบุคลากรใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <form id="personForm" method="POST" action="persons.php">
                        <input type="hidden" name="person_id" id="person_id">
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="name" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">อีเมล</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_office_staff" id="is_office_staff">
                                    <label class="form-check-label" for="is_office_staff">
                                        เจ้าหน้าที่สำนักงาน
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">หน้าที่</label>
                                <select class="form-select" name="observance" id="observance">
                                    <?php foreach ($observance_options as $id => $name): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">กลุ่ม</label>
                                <select class="form-select" name="group" id="group">
                                    <option value="0">กลุ่ม 0</option>
                                    <option value="1">กลุ่ม 1</option>
                                    <option value="2">กลุ่ม 2</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <hr>
                                <h6><i class="bi bi-calendar-x"></i> ข้อมูลการลา (ถ้ามี)</h6>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">ประเภทการลา</label>
                                <select class="form-select" name="leave_type" id="leave_type">
                                    <option value="">ไม่ได้ลา</option>
                                    <option value="ลาป่วย">ลาป่วย</option>
                                    <option value="ลากิจ">ลากิจ</option>
                                    <option value="ลาพักผ่อน">ลาพักผ่อน</option>
                                    <option value="ไปราชการ">ไปราชการ</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">สถานที่</label>
                                <input type="text" class="form-control" name="location" id="location" placeholder="ระบุสถานที่ (ถ้ามี)">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">วันเวลาเริ่มต้น</label>
                                <input type="datetime-local" class="form-control" name="start_datetime" id="start_datetime">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">วันเวลาสิ้นสุด</label>
                                <input type="datetime-local" class="form-control" name="end_datetime" id="end_datetime">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">รายละเอียดเพิ่มเติม</label>
                                <textarea class="form-control" name="details" id="details" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex gap-2 justify-content-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> ยกเลิก
                            </button>
                            <button type="submit" name="save_person" class="btn btn-primary">
                                <i class="bi bi-save"></i> บันทึก
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ปุ่มเพิ่มบุคลากร -->
    <button class="float-btn" onclick="openAddModal()" aria-label="เพิ่มบุคลากร">
        <i class="bi bi-plus-circle"></i>
        <span>เพิ่มบุคลากร</span>
    </button>

    <!-- ปุ่มย้อนกลับ -->
    <button class="back-btn" onclick="window.history.back()" aria-label="ย้อนกลับ">
        <i class="bi bi-arrow-left"></i>
        <span>ย้อนกลับ</span>
    </button>

    <!-- Form ลบข้อมูล (ซ่อน) -->
    <form id="deleteForm" method="POST" action="persons.php" style="display: none;">
        <input type="hidden" name="person_id" id="delete_person_id">
        <input type="hidden" name="delete_person" value="1">
    </form>

    <footer>
        © 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const personData = <?= isset($persons_array) ? json_encode($persons_array) : '[]' ?>;

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'เพิ่มบุคลากรใหม่';
            document.getElementById('personForm').reset();
            document.getElementById('person_id').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('personModal'));
            modal.show();
        }

        function editPerson(id) {
            const person = personData.find(p => p.id == id);
            if (!person) return;

            document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลบุคลากร';
            document.getElementById('person_id').value = person.id;
            document.getElementById('name').value = person.name;
            document.getElementById('email').value = person.email || '';
            document.getElementById('is_office_staff').checked = person.is_office_staff == 1;
            document.getElementById('observance').value = person.observance;
            document.getElementById('group').value = person.group;
            document.getElementById('leave_type').value = person.leave_type || '';
            document.getElementById('location').value = person.location || '';
            document.getElementById('start_datetime').value = person.start_datetime ? person.start_datetime.replace(' ', 'T') : '';
            document.getElementById('end_datetime').value = person.end_datetime ? person.end_datetime.replace(' ', 'T') : '';
            document.getElementById('details').value = person.details || '';

            const modal = new bootstrap.Modal(document.getElementById('personModal'));
            modal.show();
        }

        function deletePerson(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                html: `คุณต้องการลบข้อมูลของ <strong>${name}</strong> ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_person_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // Form validation
        document.getElementById('personForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            
            if (!name) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณากรอกข้อมูล',
                    text: 'กรุณากรอกชื่อ-นามสกุล',
                    confirmButtonColor: '#0a9396'
                });
                return false;
            }
        });

        // Close modal on outside click
        document.getElementById('personModal').addEventListener('click', function(e) {
            if (e.target === this) {
                bootstrap.Modal.getInstance(this).hide();
            }
        });

        // Prevent form submission on Enter key in text inputs
        document.querySelectorAll('#personForm input[type="text"], #personForm input[type="email"]').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
<?php
include 'config.php';

// ตั้งค่าโซนเวลาและภาษารายงานข้อผิดพลาด
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ตรวจสอบการเข้าถึง (สามารถเพิ่ม session check ได้ตามต้องการ)

// ดึงข้อมูลเวอร์ชันล่าสุดจากตาราง changelog
try {
    $version_query = "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1";
    $version_result = mysqli_query($connect, $version_query);
    $version_row = mysqli_fetch_assoc($version_result);
    $current_version = $version_row['version'] ?? '1.0.0';
} catch (Exception $e) {
    error_log("Database error when fetching version: " . $e->getMessage());
    $current_version = '1.0.0';
}

// ดึงข้อมูลการแต่งกายทั้งหมด
$dress_options = [];
try {
    $query = "SELECT id, name FROM dress_options ORDER BY id ASC";
    $result = mysqli_query($connect, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $dress_options[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching dress options: " . $e->getMessage());
}

// ฟังก์ชันจัดการ AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add':
                $name = mysqli_real_escape_string($connect, trim($_POST['name']));
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'กรุณาระบุชื่อการแต่งกาย']);
                    exit;
                }
                
                // ตรวจสอบซ้ำ
                $check_query = "SELECT id FROM dress_options WHERE name = ?";
                $check_stmt = mysqli_prepare($connect, $check_query);
                mysqli_stmt_bind_param($check_stmt, "s", $name);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($check_result) > 0) {
                    echo json_encode(['success' => false, 'message' => 'การแต่งกายนี้มีอยู่ในระบบแล้ว']);
                    exit;
                }
                
                $insert_query = "INSERT INTO dress_options (name) VALUES (?)";
                $stmt = mysqli_prepare($connect, $insert_query);
                mysqli_stmt_bind_param($stmt, "s", $name);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'เพิ่มข้อมูลสำเร็จ']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล']);
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = mysqli_real_escape_string($connect, trim($_POST['name']));
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'กรุณาระบุชื่อการแต่งกาย']);
                    exit;
                }
                
                // ตรวจสอบซ้ำ (ยกเว้น id ปัจจุบัน)
                $check_query = "SELECT id FROM dress_options WHERE name = ? AND id != ?";
                $check_stmt = mysqli_prepare($connect, $check_query);
                mysqli_stmt_bind_param($check_stmt, "si", $name, $id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($check_result) > 0) {
                    echo json_encode(['success' => false, 'message' => 'การแต่งกายนี้มีอยู่ในระบบแล้ว']);
                    exit;
                }
                
                $update_query = "UPDATE dress_options SET name = ? WHERE id = ?";
                $stmt = mysqli_prepare($connect, $update_query);
                mysqli_stmt_bind_param($stmt, "si", $name, $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'แก้ไขข้อมูลสำเร็จ']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล']);
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // ตรวจสอบว่ามีการใช้งานในตาราง events หรือไม่
                $check_usage = "SELECT COUNT(*) as count FROM events WHERE Dress = (SELECT name FROM dress_options WHERE id = ?)";
                $check_stmt = mysqli_prepare($connect, $check_usage);
                mysqli_stmt_bind_param($check_stmt, "i", $id);
                mysqli_stmt_execute($check_stmt);
                $usage_result = mysqli_stmt_get_result($check_stmt);
                $usage_row = mysqli_fetch_assoc($usage_result);
                
                if ($usage_row['count'] > 0) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'ไม่สามารถลบได้เนื่องจากมีการใช้งานในระบบ (' . $usage_row['count'] . ' รายการ)'
                    ]);
                    exit;
                }
                
                $delete_query = "DELETE FROM dress_options WHERE id = ?";
                $stmt = mysqli_prepare($connect, $delete_query);
                mysqli_stmt_bind_param($stmt, "i", $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล']);
                }
                break;
                
            case 'get':
                $id = intval($_POST['id']);
                
                $query = "SELECT id, name FROM dress_options WHERE id = ?";
                $stmt = mysqli_prepare($connect, $query);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($row = mysqli_fetch_assoc($result)) {
                    echo json_encode(['success' => true, 'data' => $row]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Error in AJAX handler: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>จัดการข้อมูลการแต่งกาย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@100;200;300;400&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
        }
        
        body { 
            background-color: #e9f5f5; 
            font-family: 'Anuphan', sans-serif; 
            font-weight: 300;
            color: #005f73; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        /* Header Styles - Responsive */
        .header { 
            background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
            color: white; 
            padding: 12px 15px; 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15); 
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
        }
        
        .back-btn {
            background-color: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .back-btn:hover {
            background-color: rgba(255,255,255,0.3);
            transform: translateX(-2px);
        }
        
        .header-center {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 0 10px;
        }
        
        .header-center img { 
            height: 45px; 
            margin-bottom: 8px;
            border-radius: 8px;
        }
        
        .header-center h1 { 
            font-size: 1.3rem;
            margin: 0;
            font-weight: 400;
        }
        
        .header-center p { 
            font-size: 0.8rem;
            margin: 2px 0 0 0;
            opacity: 0.95;
        }
        
        .version-badge {
            background-color: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .version-badge:hover {
            background-color: rgba(255,255,255,0.3);
        }
        
        /* Container - Responsive */
        .container-fluid { 
            padding: 15px; 
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Card Styles - Responsive */
        .card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header-custom {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #0a9396;
        }
        
        .card-header-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-header-custom h5 {
            font-weight: 400;
            color: #0a9396;
            margin: 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge-count {
            background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        /* Button Styles - Responsive */
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(10, 147, 150, 0.3);
        }
        
        .btn-warning {
            background-color: #f6c23e;
            color: #333;
        }
        
        .btn-warning:hover {
            background-color: #e0ac29;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background-color: #e74a3b;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background-color: #858796;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #6c757d;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        /* Table Styles - Responsive */
        .table-responsive {
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
            color: white;
        }
        
        .table thead th {
            border: none;
            padding: 12px 10px;
            font-weight: 400;
            font-size: 0.9rem;
        }
        
        .table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .table-hover tbody tr {
            transition: all 0.2s;
        }
        
        .table-hover tbody tr:hover {
            background-color: #e9f5f5;
            transform: scale(1.01);
        }
        
        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* Modal Styles */
        .modal-header {
            background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        .form-label {
            font-weight: 400;
            color: #005f73;
            margin-bottom: 8px;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 10px 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #0a9396;
            box-shadow: 0 0 0 0.2rem rgba(10, 147, 150, 0.25);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #858796;
        }
        
        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .empty-state p {
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        
        /* Footer */
        footer { 
            background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
            color: white; 
            text-align: center; 
            padding: 15px 10px; 
            margin-top: auto; 
            font-size: 0.85rem;
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .header {
                padding: 10px 12px;
            }
            
            .header-center h1 {
                font-size: 1.1rem;
            }
            
            .header-center p {
                font-size: 0.75rem;
            }
            
            .header-center img {
                height: 40px;
            }
            
            .back-btn {
                padding: 6px 10px;
                font-size: 0.85rem;
            }
            
            .back-btn span {
                display: none;
            }
            
            .version-badge {
                font-size: 0.7rem;
                padding: 3px 8px;
            }
            
            .container-fluid {
                padding: 12px;
            }
            
            .card {
                padding: 15px;
                border-radius: 10px;
            }
            
            .card-header-custom {
                gap: 12px;
            }
            
            .card-header-custom h5 {
                font-size: 1.1rem;
            }
            
            .badge-count {
                font-size: 0.8rem;
                padding: 5px 12px;
            }
            
            .btn {
                font-size: 0.85rem;
                padding: 8px 14px;
            }
            
            .btn-sm {
                padding: 5px 10px;
                font-size: 0.8rem;
            }
            
            /* Mobile Table - Card View */
            .table-responsive {
                overflow-x: auto;
            }
            
            .table thead {
                font-size: 0.85rem;
            }
            
            .table tbody td {
                font-size: 0.85rem;
                padding: 10px 8px;
            }
            
            /* Hide table on very small screens and show card view */
            @media (max-width: 576px) {
                .table-responsive table {
                    display: none;
                }
                
                .mobile-card-view {
                    display: block !important;
                }
            }
            
            .empty-state {
                padding: 30px 15px;
            }
            
            .empty-state i {
                font-size: 3rem;
            }
            
            .empty-state p {
                font-size: 1rem;
            }
            
            footer {
                font-size: 0.8rem;
                padding: 12px 10px;
            }
        }
        
        /* Mobile Card View for Table */
        .mobile-card-view {
            display: none;
        }
        
        .mobile-item-card {
            background: white;
            border: 1px solid #e9f5f5;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .mobile-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .mobile-item-number {
            background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 400;
            font-size: 0.9rem;
        }
        
        .mobile-item-name {
            flex: 1;
            padding: 0 12px;
            font-size: 1rem;
            color: #005f73;
        }
        
        .mobile-item-actions {
            display: flex;
            gap: 6px;
        }
        
        /* Tablet Styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            .container-fluid {
                padding: 15px 20px;
            }
            
            .header-center h1 {
                font-size: 1.2rem;
            }
        }
        
        /* Large Desktop Styles */
        @media (min-width: 1400px) {
            .card {
                padding: 25px;
            }
            
            .container-fluid {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- ส่วนหัวเว็บ -->
    <div class="header">
        <div class="header-content">
            <button class="back-btn" onclick="history.back()">
                <i class="bi bi-arrow-left"></i>
                <span>กลับ</span>
            </button>
            
            <div class="header-center">
                <img src="/images/logo.jpg" alt="Logo">
                <h1>จัดการข้อมูลการแต่งกาย</h1>
                <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
            </div>
            
            <div class="version-badge" onclick="window.location.href='changelogs'" title="ดูบันทึกการเปลี่ยนแปลง">
                v<?= htmlspecialchars($current_version) ?>
            </div>
        </div>
    </div>

    <!-- เนื้อหาหลัก -->
    <div class="container-fluid">
        <div class="card">
            <div class="card-header-custom">
                <div class="card-header-title">
                    <div>
                        <h5>
                            <i class="bi bi-person-badge"></i> 
                            <span>รายการการแต่งกาย</span>
                        </h5>
                        <span class="badge-count">
                            <i class="bi bi-list-ul"></i> 
                            ทั้งหมด <?= count($dress_options) ?> รายการ
                        </span>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-circle"></i> 
                        <span>เพิ่มการแต่งกาย</span>
                    </button>
                </div>
            </div>

            <?php if (empty($dress_options)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>ยังไม่มีข้อมูลการแต่งกาย</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-circle"></i> เพิ่มรายการแรก
                    </button>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="text-center">ลำดับ</th>
                                <th>ชื่อการแต่งกาย</th>
                                <th style="width: 150px;" class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dress_options as $index => $dress): ?>
                            <tr>
                                <td class="text-center"><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($dress['name']) ?></td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <button class="btn btn-warning btn-sm" 
                                                onclick="editDress(<?= $dress['id'] ?>)"
                                                title="แก้ไข">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="deleteDress(<?= $dress['id'] ?>, '<?= htmlspecialchars($dress['name'], ENT_QUOTES) ?>')"
                                                title="ลบ">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Card View -->
                <div class="mobile-card-view">
                    <?php foreach ($dress_options as $index => $dress): ?>
                    <div class="mobile-item-card">
                        <div class="mobile-item-header">
                            <div class="mobile-item-number"><?= $index + 1 ?></div>
                            <div class="mobile-item-name"><?= htmlspecialchars($dress['name']) ?></div>
                            <div class="mobile-item-actions">
                                <button class="btn btn-warning btn-sm" 
                                        onclick="editDress(<?= $dress['id'] ?>)"
                                        title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="deleteDress(<?= $dress['id'] ?>, '<?= htmlspecialchars($dress['name'], ENT_QUOTES) ?>')"
                                        title="ลบ">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal เพิ่มข้อมูล -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">
                        <i class="bi bi-plus-circle"></i> เพิ่มการแต่งกาย
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addForm">
                        <div class="mb-3">
                            <label for="addName" class="form-label">ชื่อการแต่งกาย <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addName" required 
                                   placeholder="เช่น ชุดปกติขาว, กากีคอพับแขนยาว">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> ยกเลิก
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitAdd()">
                        <i class="bi bi-check-circle"></i> บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขข้อมูล -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="bi bi-pencil"></i> แก้ไขการแต่งกาย
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="editId">
                        <div class="mb-3">
                            <label for="editName" class="form-label">ชื่อการแต่งกาย <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editName" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> ยกเลิก
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitEdit()">
                        <i class="bi bi-check-circle"></i> บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        © 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ฟังก์ชันเพิ่มข้อมูล
        function submitAdd() {
            const name = document.getElementById('addName').value.trim();
            
            if (!name) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกข้อมูล',
                    text: 'กรุณาระบุชื่อการแต่งกาย',
                    confirmButtonColor: '#0a9396'
                });
                return;
            }
            
            // แสดง loading
            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่งข้อมูล
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('name', name);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์',
                    confirmButtonColor: '#0a9396'
                });
            });
        }
        
        // ฟังก์ชันแก้ไขข้อมูล
        function editDress(id) {
            // ดึงข้อมูล
            const formData = new FormData();
            formData.append('action', 'get');
            formData.append('id', id);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editId').value = data.data.id;
                    document.getElementById('editName').value = data.data.name;
                    
                    const modal = new bootstrap.Modal(document.getElementById('editModal'));
                    modal.show();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    });
                }
            });
        }
        
        // ฟังก์ชันบันทึกการแก้ไข
        function submitEdit() {
            const id = document.getElementById('editId').value;
            const name = document.getElementById('editName').value.trim();
            
            if (!name) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกข้อมูล',
                    text: 'กรุณาระบุชื่อการแต่งกาย',
                    confirmButtonColor: '#0a9396'
                });
                return;
            }
            
            // แสดง loading
            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่งข้อมูล
            const formData = new FormData();
            formData.append('action', 'edit');
            formData.append('id', id);
            formData.append('name', name);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์',
                    confirmButtonColor: '#0a9396'
                });
            });
        }
        
        // ฟังก์ชันลบข้อมูล
        function deleteDress(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                html: `คุณต้องการลบ "<strong>${name}</strong>" ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#858796',
                confirmButtonText: '<i class="bi bi-trash"></i> ลบ',
                cancelButtonText: '<i class="bi bi-x-circle"></i> ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // แสดง loading
                    Swal.fire({
                        title: 'กำลังลบข้อมูล...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // ส่งข้อมูล
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ!',
                                text: data.message,
                                confirmButtonColor: '#0a9396'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ไม่สามารถลบได้',
                                text: data.message,
                                confirmButtonColor: '#0a9396'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์',
                            confirmButtonColor: '#0a9396'
                        });
                    });
                }
            });
        }
        
        // เคลียร์ฟอร์มเมื่อปิด Modal
        document.getElementById('addModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('addForm').reset();
        });
        
        // กด Enter เพื่อส่งฟอร์ม
        document.getElementById('addName').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitAdd();
            }
        });
        
        document.getElementById('editName').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitEdit();
            }
        });
    </script>
</body>
</html>
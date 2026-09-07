<?php
include 'config.php';

// ตั้งค่าโซนเวลาและภาษารายงานข้อผิดพลาด
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// สร้างโฟลเดอร์สำหรับเก็บไฟล์ถ้ายังไม่มี
$upload_dir = 'uploads/changelog/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ระบบแปลงวันที่เป็นภาษาไทย
$thai_months_full = [
    'Jan' => 'มกราคม', 'Feb' => 'กุมภาพันธ์', 'Mar' => 'มีนาคม',
    'Apr' => 'เมษายน', 'May' => 'พฤษภาคม', 'Jun' => 'มิถุนายน',
    'Jul' => 'กรกฎาคม', 'Aug' => 'สิงหาคม', 'Sep' => 'กันยายน',
    'Oct' => 'ตุลาคม', 'Nov' => 'พฤศจิกายน', 'Dec' => 'ธันวาคม'
];

$thai_days_full = [
    'Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร',
    'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี',
    'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'
];

// ตรวจสอบการดำเนินการ (เพิ่ม/แก้ไข/ลบ)
$action = $_POST['action'] ?? '';
$message = '';
$message_type = '';

if ($action === 'add' || $action === 'edit') {
    $version = mysqli_real_escape_string($connect, $_POST['version']);
    $release_date = mysqli_real_escape_string($connect, $_POST['release_date']);
    
    // อนุญาตให้มี HTML บางแท็กเท่านั้น
    $allowed_tags = '<p><strong><em><u><ol><ul><li><br><a><img><table><tr><td><th><h1><h2><h3><h4><h5><h6><span><div><iframe><video><source>';
    $changes = strip_tags($_POST['changes'], $allowed_tags);
    $changes = mysqli_real_escape_string($connect, $changes);
    
    $change_type = mysqli_real_escape_string($connect, $_POST['change_type']);
    $author = mysqli_real_escape_string($connect, $_POST['author']);
    $id = $_POST['id'] ?? 0;

    try {
        if ($action === 'add') {
            $query = "INSERT INTO changelog (version, release_date, changes, change_type, author) 
                      VALUES ('$version', '$release_date', '$changes', '$change_type', '$author')";
            $message = 'เพิ่มบันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว';
        } else {
            $query = "UPDATE changelog SET 
                      version = '$version',
                      release_date = '$release_date',
                      changes = '$changes',
                      change_type = '$change_type',
                      author = '$author'
                      WHERE id = $id";
            $message = 'แก้ไขบันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว';
        }
        
        mysqli_query($connect, $query);
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Database error: " . $e->getMessage());
    }
} elseif ($action === 'delete') {
    $id = intval($_POST['id']);
    
    try {
        $query = "DELETE FROM changelog WHERE id = $id";
        mysqli_query($connect, $query);
        $message = 'ลบบันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Database error: " . $e->getMessage());
    }
}

// ดึงข้อมูลวันที่ปัจจุบัน
$current_date = date('Y-m-d');
$current_day = date('l');
$thai_day_full = $thai_days_full[$current_day];
$current_date_thai = "วัน$thai_day_full ที่ " . date('j') . ' ' . 
                    $thai_months_full[date('M')] . ' ' . (date('Y') + 543);

// ดึงข้อมูลเวอร์ชันล่าสุด
try {
    $version_query = "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1";
    $version_result = mysqli_query($connect, $version_query);
    $version_row = mysqli_fetch_assoc($version_result);
    $current_version = $version_row['version'] ?? '1.0.0';
} catch (Exception $e) {
    error_log("Database error when fetching version: " . $e->getMessage());
    $current_version = '1.0.0';
}

// ดึงข้อมูล changelog จากฐานข้อมูล
$changelogs = [];
try {
    $query = "SELECT id, version, release_date, changes, change_type, author, created_at 
              FROM changelog 
              ORDER BY release_date DESC, created_at DESC";
    $result = mysqli_query($connect, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $changelogs[] = $row;
    }
} catch (Exception $e) {
    error_log("Error fetching changelog: " . $e->getMessage());
}

// ดึงข้อมูลสำหรับการแก้ไข (หากมี)
$edit_data = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    try {
        $query = "SELECT * FROM changelog WHERE id = $edit_id";
        $result = mysqli_query($connect, $query);
        $edit_data = mysqli_fetch_assoc($result);
    } catch (Exception $e) {
        error_log("Error fetching edit data: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>บันทึกการเปลี่ยนแปลงระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@100;200;300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- QuillJS WYSIWYG Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #0a9396;
            --secondary-color: #005f73;
            --bg-light: #e9f5f5;
            --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body { 
            background-color: var(--bg-light);
            font-family: 'Anuphan', sans-serif; 
            font-weight: 300;
            color: var(--secondary-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-size: 0.95rem;
            margin: 0;
            padding: 0;
        }
        
        /* Header Responsive */
        .header { 
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d7f82 100%);
            color: white; 
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: var(--shadow-md);
            margin-bottom: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header img { 
            height: 45px; 
            margin-bottom: 0.5rem;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .header h1 { 
            margin: 0; 
            font-size: 1.3rem;
            font-weight: 400;
        }
        
        .header p { 
            margin: 0.3rem 0 0 0; 
            font-size: 0.8rem;
            opacity: 0.95;
        }
        
        /* Container Responsive */
        .main-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
            flex: 1;
        }
        
        /* Form Container Responsive */
        .form-container {
            background-color: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(10, 147, 150, 0.1);
        }
        
        .form-title {
            color: var(--primary-color);
            border-bottom: 2px solid rgba(10, 147, 150, 0.2);
            padding-bottom: 0.75rem;
            margin-bottom: 1.25rem;
            font-weight: 500;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Form Controls Responsive */
        .form-label {
            font-weight: 400;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            font-size: 0.9rem;
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 0.5rem 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(10, 147, 150, 0.25);
        }
        
        .form-text {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        /* Version Card Responsive */
        .version-card {
            background-color: white;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-color);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .version-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .version-header {
            background: linear-gradient(to right, #f8f9fa 0%, #ffffff 100%);
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .version-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .version-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .version-date {
            color: #6c757d;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.4rem;
            margin-top: 0.5rem;
        }
        
        .changes-list {
            padding: 1.25rem;
        }
        
        .change-item {
            margin-bottom: 1rem;
        }
        
        .change-type {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 0.75rem;
            font-weight: 400;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            gap: 0.4rem;
        }
        
        .change-type i {
            font-size: 0.85em;
        }
        
        .change-type-added { background: linear-gradient(135deg, #1cc88a 0%, #17a673 100%); }
        .change-type-fixed { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); }
        .change-type-improved { background: linear-gradient(135deg, #4e73df 0%, #2e59d9 100%); }
        .change-type-deprecated { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); }
        .change-type-removed { background: linear-gradient(135deg, #5a5c69 0%, #3a3b45 100%); }
        .change-type-security { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); }
        
        .author-info {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
            padding: 0 1.25rem 1rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.3rem;
        }
        
        /* Buttons Responsive */
        .btn {
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            font-weight: 400;
            transition: all 0.2s;
        }
        
        .btn-action {
            padding: 0.4rem 0.7rem;
            font-size: 0.85rem;
        }
        
        .btn-edit {
            background-color: #4e73df;
            border-color: #4e73df;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
            transform: scale(1.05);
        }
        
        .btn-delete {
            background-color: #e74a3b;
            border-color: #e74a3b;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #be2617;
            border-color: #be2617;
            transform: scale(1.05);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #088689;
            border-color: #088689;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(10, 147, 150, 0.3);
        }
        
        /* QuillJS Responsive */
        .ql-container {
            font-family: 'Anuphan', sans-serif;
            font-size: 0.9rem;
            height: 300px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        
        .ql-toolbar {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
        }
        
        .ql-editor {
            min-height: 250px;
        }
        
        /* Custom style for images and videos in editor */
        .ql-editor img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 0.5rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .ql-editor iframe,
        .ql-editor video {
            max-width: 100%;
            border-radius: 8px;
            margin: 0.5rem 0;
        }
        
        #editor-container {
            margin-bottom: 0.75rem;
            border-radius: 8px;
            overflow: hidden;
        }
        
        textarea[name="changes"] {
            display: none;
        }
        
        /* Changelog Content Responsive */
        .changelog-content {
            line-height: 1.7;
            font-size: 0.9rem;
        }
        
        .changelog-content ul, 
        .changelog-content ol {
            padding-left: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .changelog-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.75rem 0;
            font-size: 0.85rem;
            overflow-x: auto;
            display: block;
        }
        
        .changelog-content table, 
        .changelog-content th, 
        .changelog-content td {
            border: 1px solid #ddd;
        }
        
        .changelog-content th, 
        .changelog-content td {
            padding: 0.6rem;
            text-align: left;
        }
        
        .changelog-content th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        .changelog-content a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .changelog-content a:hover {
            text-decoration: underline;
        }
        
        .changelog-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 0.75rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: block;
        }
        
        .changelog-content iframe,
        .changelog-content video {
            max-width: 100%;
            border-radius: 8px;
            margin: 0.75rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        .empty-state h4 {
            margin-bottom: 0.5rem;
            font-weight: 400;
        }
        
        /* Footer Responsive */
        footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d7f82 100%);
            color: white;
            text-align: center;
            padding: 1rem;
            margin-top: auto;
            font-size: 0.85rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.1rem;
            }
            
            .header p {
                font-size: 0.75rem;
            }
            
            .header img {
                height: 40px;
            }
            
            .main-container {
                padding: 0.75rem;
            }
            
            .form-container {
                padding: 1rem;
                border-radius: 10px;
            }
            
            .form-title {
                font-size: 1rem;
            }
            
            .version-card {
                border-radius: 10px;
            }
            
            .version-header {
                padding: 0.85rem;
            }
            
            .version-title {
                font-size: 1rem;
            }
            
            .version-date {
                font-size: 0.75rem;
            }
            
            .changes-list {
                padding: 1rem;
            }
            
            .change-type {
                font-size: 0.75rem;
                padding: 0.3rem 0.7rem;
            }
            
            .author-info {
                font-size: 0.75rem;
                padding: 0 1rem 0.85rem;
            }
            
            .ql-container {
                height: 200px;
                font-size: 0.85rem;
            }
            
            .ql-toolbar {
                font-size: 0.85rem;
            }
            
            .btn {
                font-size: 0.85rem;
                padding: 0.45rem 0.85rem;
            }
            
            .btn-action {
                padding: 0.35rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: flex-end;
            }
            
            .changelog-content {
                font-size: 0.85rem;
            }
            
            .changelog-content table {
                font-size: 0.8rem;
            }
            
            .empty-state {
                padding: 2rem 1rem;
            }
            
            .empty-state i {
                font-size: 2.5rem;
            }
            
            footer {
                font-size: 0.8rem;
                padding: 0.85rem;
            }
        }
        
        @media (max-width: 576px) {
            .header h1 {
                font-size: 1rem;
            }
            
            .header p {
                font-size: 0.7rem;
            }
            
            .version-info {
                gap: 0.5rem;
            }
            
            .form-label {
                font-size: 0.85rem;
            }
            
            .form-control, .form-select {
                font-size: 0.85rem;
                padding: 0.45rem 0.65rem;
            }
            
            .ql-toolbar.ql-snow .ql-formats {
                margin-right: 8px;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Fade In Animation */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- ส่วนหัวเว็บ -->
    <div class="header">
        <img src="/images/logo.jpg" alt="Logo">
        <h1><i class="bi bi-journal-code"></i> บันทึกการเปลี่ยนแปลงระบบ</h1>
        <p><i class="bi bi-calendar-check"></i> <?= htmlspecialchars($current_date_thai) ?> | <i class="bi bi-tag"></i> v<?= htmlspecialchars($current_version) ?></p>
    </div>
    
    <!-- ส่วนฟอร์มและรายการ -->
    <div class="main-container">
        <!-- ส่วนฟอร์มเพิ่ม/แก้ไขข้อมูล -->
        <div class="form-container fade-in">
            <h3 class="form-title">
                <i class="bi bi-<?= isset($edit_data['id']) ? 'pencil-square' : 'plus-circle' ?>"></i>
                <?= isset($edit_data['id']) ? 'แก้ไขบันทึกการเปลี่ยนแปลง' : 'เพิ่มบันทึกการเปลี่ยนแปลงใหม่' ?>
            </h3>
            
            <form method="post" action="" id="changelogForm">
                <input type="hidden" name="action" value="<?= isset($edit_data['id']) ? 'edit' : 'add' ?>">
                <?php if (isset($edit_data['id'])): ?>
                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <label for="version" class="form-label">เวอร์ชัน <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="version" name="version" 
                               value="<?= htmlspecialchars($edit_data['version'] ?? '') ?>" 
                               placeholder="เช่น 1.0.0" required>
                        <div class="form-text">รูปแบบ: X.X.X</div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <label for="release_date" class="form-label">วันที่เผยแพร่ <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="release_date" name="release_date" 
                               value="<?= htmlspecialchars($edit_data['release_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <label for="change_type" class="form-label">ประเภท <span class="text-danger">*</span></label>
                        <select class="form-select" id="change_type" name="change_type" required>
                            <option value="added" <?= ($edit_data['change_type'] ?? '') === 'added' ? 'selected' : '' ?>>เพิ่ม</option>
                            <option value="fixed" <?= ($edit_data['change_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>แก้ไข</option>
                            <option value="improved" <?= ($edit_data['change_type'] ?? '') === 'improved' ? 'selected' : '' ?>>ปรับปรุง</option>
                            <option value="deprecated" <?= ($edit_data['change_type'] ?? '') === 'deprecated' ? 'selected' : '' ?>>เลิกใช้</option>
                            <option value="removed" <?= ($edit_data['change_type'] ?? '') === 'removed' ? 'selected' : '' ?>>ลบ</option>
                            <option value="security" <?= ($edit_data['change_type'] ?? '') === 'security' ? 'selected' : '' ?>>ความปลอดภัย</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label for="author" class="form-label">ผู้ดำเนินการ</label>
                        <input type="text" class="form-control" id="author" name="author" 
                               value="<?= htmlspecialchars($edit_data['author'] ?? '') ?>"
                               placeholder="ชื่อผู้ดำเนินการ">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">รายการเปลี่ยนแปลง <span class="text-danger">*</span></label>
                    <!-- ตัวแก้ไข QuillJS -->
                    <div id="editor-container"></div>
                    <!-- textarea ที่ซ่อนไว้สำหรับส่งข้อมูล -->
                    <input type="hidden" name="changes" value="<?= htmlspecialchars($edit_data['changes'] ?? '') ?>">
                    <div class="form-text"><i class="bi bi-info-circle"></i> สามารถแทรกรูปภาพ (อัพโหลดหรือ URL) และวิดีโอจาก YouTube/URL ได้</div>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <?php if (isset($edit_data['id'])): ?>
                        <a href="add_changelog.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> ยกเลิก
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
        
        <!-- ส่วนแสดงรายการ changelog -->
        <div class="changelog-container">
            <?php if (!empty($changelogs)): ?>
                <?php foreach ($changelogs as $log): ?>
                    <?php
                    // จัดรูปแบบวันที่ไทย
                    $release_date = "วัน" . $thai_days_full[date('l', strtotime($log['release_date']))] . " ที่ " . 
                                  date('j', strtotime($log['release_date'])) . ' ' . 
                                  $thai_months_full[date('M', strtotime($log['release_date']))] . ' ' . 
                                  (date('Y', strtotime($log['release_date'])) + 543);
                    
                    // แปลงประเภทการเปลี่ยนแปลงเป็นคลาส CSS และไอคอน
                    $change_type_class = 'change-type-' . $log['change_type'];
                    $change_icon = '';
                    $change_text = '';
                    switch($log['change_type']) {
                        case 'added': 
                            $change_icon = 'plus-circle'; 
                            $change_text = 'เพิ่ม';
                            break;
                        case 'fixed': 
                            $change_icon = 'bug'; 
                            $change_text = 'แก้ไข';
                            break;
                        case 'improved': 
                            $change_icon = 'arrow-up-circle'; 
                            $change_text = 'ปรับปรุง';
                            break;
                        case 'deprecated': 
                            $change_icon = 'exclamation-triangle'; 
                            $change_text = 'เลิกใช้';
                            break;
                        case 'removed': 
                            $change_icon = 'trash'; 
                            $change_text = 'ลบ';
                            break;
                        case 'security': 
                            $change_icon = 'shield-lock'; 
                            $change_text = 'ความปลอดภัย';
                            break;
                        default: 
                            $change_icon = 'pencil';
                            $change_text = $log['change_type'];
                    }
                    ?>
                    
                    <div class="version-card fade-in">
                        <div class="version-header">
                            <div class="version-info">
                                <div>
                                    <div class="version-title">
                                        <i class="bi bi-bookmark-star"></i>
                                        เวอร์ชัน <?= htmlspecialchars($log['version']) ?>
                                    </div>
                                    <div class="version-date">
                                        <i class="bi bi-calendar3"></i>
                                        <?= htmlspecialchars($release_date) ?>
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <a href="add_changelog.php?edit=<?= $log['id'] ?>" 
                                       class="btn btn-sm btn-action btn-edit"
                                       title="แก้ไข">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-action btn-delete" 
                                            onclick="confirmDelete(<?= $log['id'] ?>)"
                                            title="ลบ">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="changes-list">
                            <div class="change-item">
                                <span class="change-type <?= $change_type_class ?>">
                                    <i class="bi bi-<?= $change_icon ?>"></i>
                                    <?= $change_text ?>
                                </span>
                            </div>
                            
                            <div class="changelog-content mt-3">
                                <?= $log['changes'] ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($log['author'])): ?>
                            <div class="author-info">
                                <i class="bi bi-person-fill"></i> 
                                <?= htmlspecialchars($log['author']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-journal-x"></i>
                    <h4>ไม่พบบันทึกการเปลี่ยนแปลง</h4>
                    <p>ยังไม่มีข้อมูลบันทึกการเปลี่ยนแปลงระบบ</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <i class="bi bi-c-circle"></i> <?= date('Y') + 543 ?> สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // กำหนด toolbar แบบเต็มรูปแบบพร้อมตัวเลือกรูปภาพและวิดีโอ
        var toolbarOptions = [
          [{ 'font': [] }, { 'size': ['small', false, 'large', 'huge'] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'color': [] }, { 'background': [] }],
          [{ 'script': 'sub'}, { 'script': 'super' }],
          [{ 'header': 1 }, { 'header': 2 }, 'blockquote', 'code-block'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'indent': '-1'}, { 'indent': '+1' }],
          [{ 'direction': 'rtl' }, { 'align': [] }],
          ['link', 'image', 'video'],
          ['clean']
        ];

        // เริ่มต้น Quill editor พร้อม custom image handler
        var quill = new Quill('#editor-container', {
          modules: {
            toolbar: {
              container: toolbarOptions,
              handlers: {
                image: imageHandler,
                video: videoHandler
              }
            }
          },
          placeholder: 'เขียนรายการเปลี่ยนแปลงที่นี่...',
          theme: 'snow'
        });

        // Custom Image Handler - รองรับทั้งอัพโหลดและ URL
        function imageHandler() {
          Swal.fire({
            title: 'เพิ่มรูปภาพ',
            html: `
              <div style="text-align: left;">
                <div class="mb-3">
                  <label class="form-label">เลือกวิธีการเพิ่มรูป:</label>
                  <select id="imageType" class="form-select">
                    <option value="upload">อัพโหลดไฟล์รูปภาพ</option>
                    <option value="url">ใส่ URL รูปภาพ</option>
                  </select>
                </div>
                <div id="uploadSection">
                  <label class="form-label">เลือกไฟล์รูปภาพ:</label>
                  <input type="file" id="imageFile" class="form-control" accept="image/*">
                  <div class="form-text">รองรับไฟล์: JPG, PNG, GIF (ไม่เกิน 5MB)</div>
                </div>
                <div id="urlSection" style="display: none;">
                  <label class="form-label">URL รูปภาพ:</label>
                  <input type="url" id="imageUrl" class="form-control" placeholder="https://example.com/image.jpg">
                  <div class="form-text">ใส่ URL ที่ลิงก์ไปยังรูปภาพโดยตรง</div>
                </div>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'เพิ่มรูปภาพ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0a9396',
            didOpen: () => {
              const imageTypeSelect = document.getElementById('imageType');
              const uploadSection = document.getElementById('uploadSection');
              const urlSection = document.getElementById('urlSection');
              
              imageTypeSelect.addEventListener('change', function() {
                if (this.value === 'upload') {
                  uploadSection.style.display = 'block';
                  urlSection.style.display = 'none';
                } else {
                  uploadSection.style.display = 'none';
                  urlSection.style.display = 'block';
                }
              });
            },
            preConfirm: () => {
              const imageType = document.getElementById('imageType').value;
              
              if (imageType === 'upload') {
                const fileInput = document.getElementById('imageFile');
                if (!fileInput.files || !fileInput.files[0]) {
                  Swal.showValidationMessage('กรุณาเลือกไฟล์รูปภาพ');
                  return false;
                }
                
                const file = fileInput.files[0];
                
                // ตรวจสอบประเภทไฟล์
                if (!file.type.startsWith('image/')) {
                  Swal.showValidationMessage('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
                  return false;
                }
                
                // ตรวจสอบขนาดไฟล์ (5MB)
                if (file.size > 5 * 1024 * 1024) {
                  Swal.showValidationMessage('ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5MB');
                  return false;
                }
                
                return { type: 'upload', file: file };
              } else {
                const url = document.getElementById('imageUrl').value;
                if (!url) {
                  Swal.showValidationMessage('กรุณาใส่ URL รูปภาพ');
                  return false;
                }
                
                // ตรวจสอบรูปแบบ URL
                try {
                  new URL(url);
                } catch (e) {
                  Swal.showValidationMessage('URL ไม่ถูกต้อง');
                  return false;
                }
                
                return { type: 'url', url: url };
              }
            }
          }).then((result) => {
            if (result.isConfirmed) {
              const data = result.value;
              
              if (data.type === 'upload') {
                // แปลงรูปเป็น Base64
                const reader = new FileReader();
                reader.onload = function(e) {
                  const range = quill.getSelection(true);
                  quill.insertEmbed(range.index, 'image', e.target.result);
                  quill.setSelection(range.index + 1);
                };
                reader.readAsDataURL(data.file);
                
                Swal.fire({
                  icon: 'success',
                  title: 'เพิ่มรูปภาพสำเร็จ',
                  timer: 1500,
                  showConfirmButton: false
                });
              } else {
                // ใช้ URL โดยตรง
                const range = quill.getSelection(true);
                quill.insertEmbed(range.index, 'image', data.url);
                quill.setSelection(range.index + 1);
                
                Swal.fire({
                  icon: 'success',
                  title: 'เพิ่มรูปภาพสำเร็จ',
                  timer: 1500,
                  showConfirmButton: false
                });
              }
            }
          });
        }

        // Custom Video Handler - รองรับ YouTube และ URL วิดีโอ
        function videoHandler() {
          Swal.fire({
            title: 'เพิ่มวิดีโอ',
            html: `
              <div style="text-align: left;">
                <label class="form-label">URL วิดีโอ:</label>
                <input type="url" id="videoUrl" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                <div class="form-text mt-2">
                  <strong>รองรับ:</strong><br>
                  • YouTube (youtube.com, youtu.be)<br>
                  • Vimeo (vimeo.com)<br>
                  • URL วิดีโอโดยตรง (.mp4, .webm, .ogg)
                </div>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'เพิ่มวิดีโอ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0a9396',
            preConfirm: () => {
              const url = document.getElementById('videoUrl').value;
              if (!url) {
                Swal.showValidationMessage('กรุณาใส่ URL วิดีโอ');
                return false;
              }
              
              // ตรวจสอบรูปแบบ URL
              try {
                new URL(url);
              } catch (e) {
                Swal.showValidationMessage('URL ไม่ถูกต้อง');
                return false;
              }
              
              return url;
            }
          }).then((result) => {
            if (result.isConfirmed) {
              let videoUrl = result.value;
              
              // แปลง YouTube URL ให้เป็น embed format
              if (videoUrl.includes('youtube.com/watch')) {
                const videoId = new URL(videoUrl).searchParams.get('v');
                if (videoId) {
                  videoUrl = `https://www.youtube.com/embed/${videoId}`;
                }
              } else if (videoUrl.includes('youtu.be/')) {
                const videoId = videoUrl.split('youtu.be/')[1].split('?')[0];
                videoUrl = `https://www.youtube.com/embed/${videoId}`;
              }
              // แปลง Vimeo URL
              else if (videoUrl.includes('vimeo.com/')) {
                const videoId = videoUrl.split('vimeo.com/')[1].split('?')[0];
                videoUrl = `https://player.vimeo.com/video/${videoId}`;
              }
              
              const range = quill.getSelection(true);
              quill.insertEmbed(range.index, 'video', videoUrl);
              quill.setSelection(range.index + 1);
              
              Swal.fire({
                icon: 'success',
                title: 'เพิ่มวิดีโอสำเร็จ',
                timer: 1500,
                showConfirmButton: false
              });
            }
          });
        }

        // เมื่อฟอร์มถูกส่ง ให้คัดลอกเนื้อหาไปยัง hidden input
        var form = document.getElementById('changelogForm');
        if (form) {
          form.onsubmit = function(e) {
            e.preventDefault();
            
            var changesInput = document.querySelector('input[name=changes]');
            var editorContent = document.querySelector('.ql-editor').innerHTML;
            
            // ตรวจสอบว่ามีเนื้อหาหรือไม่
            if (editorContent.trim() === '' || editorContent === '<p><br></p>') {
              Swal.fire({
                icon: 'warning',
                title: 'กรุณาระบุรายการเปลี่ยนแปลง',
                text: 'โปรดกรอกรายละเอียดการเปลี่ยนแปลงก่อนบันทึก',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#0a9396'
              });
              return false;
            }
            
            changesInput.value = editorContent;
            
            // แสดง loading
            Swal.fire({
              title: 'กำลังบันทึกข้อมูล...',
              html: '<div class="loading"></div>',
              allowOutsideClick: false,
              showConfirmButton: false,
              didOpen: () => {
                Swal.showLoading();
              }
            });
            
            // ส่งฟอร์ม
            form.submit();
          };
        }

        // หากมีการแก้ไขข้อมูล ให้ตั้งค่าเนื้อหาเริ่มต้น
        <?php if (isset($edit_data['changes'])): ?>
          var editContent = `<?= str_replace(["\r\n", "\n", "\r"], '', addslashes($edit_data['changes'])) ?>`;
          if (editContent) {
            quill.clipboard.dangerouslyPasteHTML(editContent);
          }
        <?php endif; ?>

        // เมื่อคลิกปุ่มแก้ไข ให้เลื่อนไปยังฟอร์ม
        <?php if (isset($_GET['edit'])): ?>
          setTimeout(function() {
            document.querySelector('.form-container').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
          }, 100);
        <?php endif; ?>

        // แสดง SweetAlert เมื่อมีข้อความ
        <?php if ($message): ?>
          Swal.fire({
            icon: '<?= $message_type ?>',
            title: '<?= $message_type === 'success' ? 'สำเร็จ!' : 'เกิดข้อผิดพลาด!' ?>',
            text: '<?= addslashes($message) ?>',
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#0a9396',
            timer: 3000,
            timerProgressBar: true
          }).then((result) => {
            // Redirect หลังจากปิด alert (สำหรับกรณี add/edit สำเร็จ)
            <?php if ($message_type === 'success' && ($action === 'add' || $action === 'edit')): ?>
              window.location.href = 'add_changelog.php';
            <?php endif; ?>
          });
        <?php endif; ?>
      });

      // ฟังก์ชันยืนยันการลบ
      function confirmDelete(id) {
        Swal.fire({
          title: 'ยืนยันการลบ?',
          text: 'คุณแน่ใจที่จะลบบันทึกนี้? การกระทำนี้ไม่สามารถย้อนกลับได้',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#e74a3b',
          cancelButtonColor: '#6c757d',
          confirmButtonText: '<i class="bi bi-trash"></i> ลบเลย',
          cancelButtonText: '<i class="bi bi-x-circle"></i> ยกเลิก',
          reverseButtons: true
        }).then((result) => {
          if (result.isConfirmed) {
            // แสดง loading
            Swal.fire({
              title: 'กำลังลบข้อมูล...',
              html: '<div class="loading"></div>',
              allowOutsideClick: false,
              showConfirmButton: false,
              didOpen: () => {
                Swal.showLoading();
              }
            });
            
            // สร้างฟอร์มและส่งข้อมูล
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
          }
        });
      }

      // Smooth scroll สำหรับการนำทาง
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        });
      });

      // ป้องกันการส่งฟอร์มซ้ำ
      var formSubmitted = false;
      document.getElementById('changelogForm')?.addEventListener('submit', function() {
        if (formSubmitted) {
          return false;
        }
        formSubmitted = true;
      });
    </script>
</body>
</html>
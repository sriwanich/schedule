<?php
include 'config.php';

// ตั้งค่าโซนเวลาและภาษารายงานข้อผิดพลาด
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

// จัดการการเพิ่มหน่วยงานใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $department_name = trim($_POST['department_name']);
    
    if (!empty($department_name)) {
        try {
            $insert_query = "INSERT INTO department_options (name) VALUES (?)";
            $stmt = mysqli_prepare($connect, $insert_query);
            mysqli_stmt_bind_param($stmt, "s", $department_name);
            
            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => 'เพิ่มหน่วยงานสำเร็จ'];
            } else {
                $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเพิ่มหน่วยงาน'];
            }
            mysqli_stmt_close($stmt);
        } catch (Exception $e) {
            error_log("Error adding department: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } else {
        $response = ['success' => false, 'message' => 'กรุณากรอกชื่อหน่วยงาน'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// จัดการการแก้ไขหน่วยงาน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $department_id = intval($_POST['department_id']);
    $department_name = trim($_POST['department_name']);
    
    if (!empty($department_name) && $department_id > 0) {
        try {
            $update_query = "UPDATE department_options SET name = ? WHERE id = ?";
            $stmt = mysqli_prepare($connect, $update_query);
            mysqli_stmt_bind_param($stmt, "si", $department_name, $department_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => 'แก้ไขหน่วยงานสำเร็จ'];
            } else {
                $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขหน่วยงาน'];
            }
            mysqli_stmt_close($stmt);
        } catch (Exception $e) {
            error_log("Error editing department: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } else {
        $response = ['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// จัดการการลบหน่วยงาน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $department_id = intval($_POST['department_id']);
    
    if ($department_id > 0) {
        try {
            // ตรวจสอบว่ามีการใช้งานหน่วยงานนี้ในตาราง events หรือไม่
            $check_query = "SELECT COUNT(*) as count FROM events WHERE department = (SELECT name FROM department_options WHERE id = ?)";
            $stmt_check = mysqli_prepare($connect, $check_query);
            mysqli_stmt_bind_param($stmt_check, "i", $department_id);
            mysqli_stmt_execute($stmt_check);
            $check_result = mysqli_stmt_get_result($stmt_check);
            $check_row = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($stmt_check);
            
            if ($check_row['count'] > 0) {
                $response = ['success' => false, 'message' => 'ไม่สามารถลบได้เนื่องจากมีการใช้งานหน่วยงานนี้ในระบบ'];
            } else {
                $delete_query = "DELETE FROM department_options WHERE id = ?";
                $stmt = mysqli_prepare($connect, $delete_query);
                mysqli_stmt_bind_param($stmt, "i", $department_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $response = ['success' => true, 'message' => 'ลบหน่วยงานสำเร็จ'];
                } else {
                    $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบหน่วยงาน'];
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            error_log("Error deleting department: " . $e->getMessage());
            $response = ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } else {
        $response = ['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ดึงข้อมูลหน่วยงานทั้งหมด
$departments = [];
$total_departments = 0;
try {
    $query = "SELECT id, name FROM department_options ORDER BY id ASC";
    $result = mysqli_query($connect, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
    $total_departments = count($departments);
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// ค้นหาหน่วยงาน
$search_query = '';
$filtered_departments = $departments;

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $filtered_departments = array_filter($departments, function($dept) use ($search_query) {
        return stripos($dept['name'], $search_query) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการหน่วยงาน - ปฏิทินและนัดหมายงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@100;200;300;400&display=swap" rel="stylesheet">
    
    <style>
        body { 
            background-color: #e9f5f5; 
            font-family: 'Anuphan', sans-serif; 
            font-weight: 300;
            color: #005f73; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }
        
        .header { 
            background-color: #0a9396; 
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            text-align: center; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); 
            position: relative; 
        }
        
        .header img { 
            height: 50px; 
            margin-bottom: 10px; 
        }
        
        .header h1, .header p { 
            margin: 0; 
            white-space: nowrap; 
        }
        
        .header h1 { 
            font-size: 1.5rem; 
        }
        
        .header p { 
            font-size: 0.9rem; 
        }
        
        .container-fluid { 
            padding: 10px; 
            flex: 1; 
            position: relative; 
        }
        
        .stats-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .stats-card .icon {
            font-size: 2rem;
            color: #0a9396;
        }
        
        .stats-card .info h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
            color: #0a9396;
        }
        
        .stats-card .info p {
            margin: 0;
            font-size: 0.9rem;
            color: #005f73;
        }
        
        .search-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .search-card .input-group {
            margin-bottom: 0;
        }
        
        .search-card input {
            border: 2px solid #0a9396;
            border-radius: 25px 0 0 25px;
            padding: 10px 20px;
        }
        
        .search-card button {
            background-color: #0a9396;
            color: white;
            border: 2px solid #0a9396;
            border-radius: 0 25px 25px 0;
            padding: 10px 20px;
        }
        
        .search-card button:hover {
            background-color: #005f73;
            border-color: #005f73;
        }
        
        .department-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .department-card:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .department-card .department-info {
            flex: 1;
        }
        
        .department-card .department-name {
            font-size: 1rem;
            font-weight: bold;
            color: #005f73;
            margin: 0;
        }
        
        .department-card .department-id {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 0;
        }
        
        .department-card .actions {
            display: flex;
            gap: 5px;
        }
        
        .department-card .btn {
            padding: 5px 10px;
            font-size: 0.9rem;
            border-radius: 5px;
        }
        
        .btn-edit {
            background-color: #f6c23e;
            color: white;
            border: none;
        }
        
        .btn-edit:hover {
            background-color: #dda520;
        }
        
        .btn-delete {
            background-color: #e74a3b;
            color: white;
            border: none;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .float-btn {
            position: fixed;
            bottom: 80px;
            right: 20px;
            background-color: #0a9396;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .float-btn:hover {
            background-color: #005f73;
            transform: scale(1.1);
        }
        
        .back-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            background-color: #5a6268;
            color: white;
        }
        
        footer {
            background-color: #0a9396;
            color: white;
            text-align: center;
            padding: 10px 0;
            margin-top: auto;
            font-size: 0.9rem;
        }
        
        .version-badge {
            position: absolute;
            bottom: 0px;
            right: 5px;
            font-size: 0.5rem;
            padding: 2px 5px;
            font-weight: 300;
        }
        
        .modal-header {
            background-color: #0a9396;
            color: white;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .form-label {
            font-weight: bold;
            color: #005f73;
        }
        
        .form-control:focus {
            border-color: #0a9396;
            box-shadow: 0 0 0 0.2rem rgba(10, 147, 150, 0.25);
        }
        
        .btn-primary {
            background-color: #0a9396;
            border-color: #0a9396;
        }
        
        .btn-primary:hover {
            background-color: #005f73;
            border-color: #005f73;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .empty-state h5 {
            margin-bottom: 10px;
        }
        
        .empty-state p {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- ส่วนหัวเว็บ -->
    <div class="header">
        <img src="/images/logo.jpg" alt="Logo">
        <h1>จัดการหน่วยงาน</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
        <div class="version-badge" onclick="window.location.href='changelogs'" title="ดูบันทึกการเปลี่ยนแปลงในแต่ละเวอร์ชั่น">
            เวอร์ชัน <?= htmlspecialchars($current_version) ?>
        </div>
    </div>

    <!-- ส่วนเนื้อหา -->
    <div class="container-fluid">
        <!-- สถิติ -->
        <div class="stats-card">
            <div class="icon">
                <i class="bi bi-building"></i>
            </div>
            <div class="info">
                <h3><?= $total_departments ?></h3>
                <p>หน่วยงานทั้งหมด</p>
            </div>
        </div>

        <!-- ค้นหา -->
        <div class="search-card">
            <form method="GET" action="">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           placeholder="ค้นหาหน่วยงาน..." 
                           name="search" 
                           value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit" class="btn">
                        <i class="bi bi-search"></i> ค้นหา
                    </button>
                </div>
            </form>
            <?php if (!empty($search_query)): ?>
            <div class="mt-2">
                <small class="text-muted">
                    พบ <?= count($filtered_departments) ?> รายการจากการค้นหา "<?= htmlspecialchars($search_query) ?>"
                    <a href="departments.php" class="ms-2">ล้างการค้นหา</a>
                </small>
            </div>
            <?php endif; ?>
        </div>

        <!-- รายการหน่วยงาน -->
        <div id="departments-container">
            <?php if (empty($filtered_departments)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h5>ไม่พบหน่วยงาน</h5>
                    <p>
                        <?php if (!empty($search_query)): ?>
                            ไม่พบหน่วยงานที่ตรงกับคำค้นหา "<?= htmlspecialchars($search_query) ?>"
                        <?php else: ?>
                            ยังไม่มีหน่วยงานในระบบ กดปุ่ม + เพื่อเพิ่มหน่วยงานใหม่
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($filtered_departments as $dept): ?>
                <div class="department-card" data-id="<?= $dept['id'] ?>">
                    <div class="department-info">
                        <p class="department-name"><?= htmlspecialchars($dept['name']) ?></p>
                        <p class="department-id">ID: <?= $dept['id'] ?></p>
                    </div>
                    <div class="actions">
                        <button class="btn btn-edit" 
                                onclick="editDepartment(<?= $dept['id'] ?>, '<?= htmlspecialchars(addslashes($dept['name'])) ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-delete" 
                                onclick="deleteDepartment(<?= $dept['id'] ?>, '<?= htmlspecialchars(addslashes($dept['name'])) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ปุ่มเพิ่มหน่วยงาน -->
    <button class="float-btn" onclick="showAddModal()">
        <i class="bi bi-plus"></i>
    </button>

    <!-- ปุ่มกลับ -->
    <a href="javascript:history.back()" class="back-btn">
        <i class="bi bi-arrow-left"></i> ย้อนกลับ
    </a>

    <!-- Footer -->
    <footer>
        © 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    </footer>

    <!-- Modal เพิ่มหน่วยงาน -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">เพิ่มหน่วยงานใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addForm">
                        <div class="mb-3">
                            <label for="add_department_name" class="form-label">ชื่อหน่วยงาน</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="add_department_name" 
                                   name="department_name" 
                                   required 
                                   placeholder="กรอกชื่อหน่วยงาน">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" onclick="submitAdd()">
                        <i class="bi bi-plus-circle"></i> เพิ่มหน่วยงาน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขหน่วยงาน -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">แก้ไขหน่วยงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_department_id" name="department_id">
                        <div class="mb-3">
                            <label for="edit_department_name" class="form-label">ชื่อหน่วยงาน</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="edit_department_name" 
                                   name="department_name" 
                                   required 
                                   placeholder="กรอกชื่อหน่วยงาน">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" onclick="submitEdit()">
                        <i class="bi bi-check-circle"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        let addModal, editModal;

        document.addEventListener('DOMContentLoaded', function() {
            addModal = new bootstrap.Modal(document.getElementById('addModal'));
            editModal = new bootstrap.Modal(document.getElementById('editModal'));
        });

        function showAddModal() {
            document.getElementById('addForm').reset();
            addModal.show();
        }

        function editDepartment(id, name) {
            document.getElementById('edit_department_id').value = id;
            document.getElementById('edit_department_name').value = name;
            editModal.show();
        }

        function submitAdd() {
            const form = document.getElementById('addForm');
            const formData = new FormData(form);
            formData.append('action', 'add');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            fetch('departments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
                    confirmButtonColor: '#0a9396'
                });
            });
        }

        function submitEdit() {
            const form = document.getElementById('editForm');
            const formData = new FormData(form);
            formData.append('action', 'edit');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            fetch('departments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ผิดพลาด',
                        text: data.message,
                        confirmButtonColor: '#0a9396'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'ผิดพลาด',
                    text: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
                    confirmButtonColor: '#0a9396'
                });
            });
        }

        function deleteDepartment(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                html: `คุณต้องการลบหน่วยงาน<br><strong>"${name}"</strong><br>ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('department_id', id);

                    fetch('departments.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ',
                                text: data.message,
                                confirmButtonColor: '#0a9396'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ผิดพลาด',
                                text: data.message,
                                confirmButtonColor: '#0a9396'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด',
                            text: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
                            confirmButtonColor: '#0a9396'
                        });
                    });
                }
            });
        }

        // ฟังก์ชันป้องกันการกด Enter ใน form
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                    }
                });
            });

            // เพิ่ม animation เมื่อโหลดหน้า
            const cards = document.querySelectorAll('.department-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });

        // Auto-focus ใน modal
        document.getElementById('addModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('add_department_name').focus();
        });

        document.getElementById('editModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('edit_department_name').focus();
        });
    </script>
</body>
</html>
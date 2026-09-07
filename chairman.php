<?php
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['username'])) {
    header("Location: login");
    exit();
}

// เชื่อมต่อฐานข้อมูลผ่าน config.php
include 'config.php';

// ดึงข้อมูลผู้ใช้
$username = $_SESSION['username'];
$user_query = mysqli_query($connect, "SELECT first_name, last_name, role FROM users WHERE username='$username'");
$user_data = mysqli_fetch_assoc($user_query);
$first_name = $user_data['first_name'];
$last_name = $user_data['last_name'];
$role = $user_data['role'];

// กำหนดชื่อเดือนเต็มในภาษาไทย
$thai_months_full = [
    'Jan' => 'มกราคม', 'Feb' => 'กุมภาพันธ์', 'Mar' => 'มีนาคม', 'Apr' => 'เมษายน',
    'May' => 'พฤษภาคม', 'Jun' => 'มิถุนายน', 'Jul' => 'กรกฎาคม', 'Aug' => 'สิงหาคม',
    'Sep' => 'กันยายน', 'Oct' => 'ตุลาคม', 'Nov' => 'พฤศจิกายน', 'Dec' => 'ธันวาคม'
];

// ดึงข้อมูลวันที่ปัจจุบัน
$current_date = date('Y-m-d');
$current_day = date('l');
$thai_days_full = [
    'Sunday' => 'อาทิตย์', 'Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ',
    'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์'
];
$thai_day_full = $thai_days_full[$current_day];
$current_date_thai = "วัน$thai_day_full ที่ " . date('j') . ' ' . 
                    $thai_months_full[date('M')] . ' ' . (date('Y') + 543);

// จัดการ CRUD Operations
$action_message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // เพิ่มข้อมูลใหม่
        if ($action === 'add') {
            $name = mysqli_real_escape_string($connect, $_POST['name']);
            $query = "INSERT INTO chairman_options (name) VALUES ('$name')";
            if (mysqli_query($connect, $query)) {
                $action_message = 'เพิ่มข้อมูลประธานสำเร็จ';
                $action_type = 'success';
            } else {
                $action_message = 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล';
                $action_type = 'error';
            }
        }
        
        // แก้ไขข้อมูล
        if ($action === 'edit') {
            $id = intval($_POST['id']);
            $name = mysqli_real_escape_string($connect, $_POST['name']);
            $query = "UPDATE chairman_options SET name='$name' WHERE id=$id";
            if (mysqli_query($connect, $query)) {
                $action_message = 'แก้ไขข้อมูลประธานสำเร็จ';
                $action_type = 'success';
            } else {
                $action_message = 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล';
                $action_type = 'error';
            }
        }
        
        // ลบข้อมูล
        if ($action === 'delete') {
            $id = intval($_POST['id']);
            $query = "DELETE FROM chairman_options WHERE id=$id";
            if (mysqli_query($connect, $query)) {
                $action_message = 'ลบข้อมูลประธานสำเร็จ';
                $action_type = 'success';
            } else {
                $action_message = 'เกิดข้อผิดพลาดในการลบข้อมูล';
                $action_type = 'error';
            }
        }
    }
}

// ดึงข้อมูลประธานทั้งหมด
$chairman_query = "SELECT * FROM chairman_options ORDER BY id ASC";
$chairman_result = mysqli_query($connect, $chairman_query);
$chairman_list = [];
while ($row = mysqli_fetch_assoc($chairman_result)) {
    $chairman_list[] = $row;
}

// นับจำนวนประธาน
$total_chairman = count($chairman_list);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>จัดการข้อมูลประธาน</title>
    <meta name="author" content="นายดักพันธุ์ ศรีวนิช">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #0a9396;
            --primary-dark: #005f73;
            --primary-light: rgba(10, 147, 150, 0.1);
            --secondary: #94d2bd;
            --accent: #ee9b00;
            --danger: #bb3e03;
            --success: #4c9568;
            --light: #f8f9fa;
            --dark: #212529;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        body {
            font-family: 'Anuphan', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            font-size: 16px;
            font-weight: 400;
            line-height: 1.7;
            padding-bottom: 60px;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            line-height: 1.3;
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        .animate-delay-1 { animation-delay: 0.2s; }
        .animate-delay-2 { animation-delay: 0.4s; }
        .animate-delay-3 { animation-delay: 0.6s; }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1020;
            text-align: center;
        }
        
        .header img {
            height: 40px;
            margin-bottom: 0.5rem;
        }
        
        .header h1 {
            font-size: 1.4rem;
            font-weight: 400;
            margin-bottom: 0.25rem;
        }
        
        .header p {
            font-size: 0.9rem;
            font-weight: 300;
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        /* Date Display */
        .current-date {
            background: white;
            padding: 12px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            text-align: center;
            font-weight: 400;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--primary-dark);
        }
        
        /* Stats Card */
        .stats-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 16px;
            margin-bottom: 16px;
            text-align: center;
            border-left: 4px solid var(--primary);
        }
        
        .stats-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0;
        }
        
        .stats-card p {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        /* Action Button */
        .add-btn {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1010;
            transition: all 0.3s ease;
        }
        
        .add-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
        
        /* Chairman List */
        .chairman-list {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .list-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 1.1rem;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-light);
        }
        
        .chairman-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
            background: var(--light);
            transition: all 0.2s ease;
        }
        
        .chairman-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .chairman-info {
            flex: 1;
        }
        
        .chairman-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 12px;
        }
        
        .chairman-name {
            font-size: 0.95rem;
            color: var(--dark);
            margin-bottom: 0;
        }
        
        .chairman-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit, .btn-delete {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .btn-edit {
            background: var(--accent);
            color: white;
        }
        
        .btn-edit:hover {
            background: #ca8500;
            transform: scale(1.1);
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #9a3302;
            transform: scale(1.1);
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            padding: 8px 0;
            z-index: 1030;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.75rem;
            color: #6c757d;
            text-decoration: none !important;
            transition: color 0.2s ease;
        }
        
        .nav-item:hover {
            color: var(--primary) !important;
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        /* Modal Styles */
        .modal-header {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .modal-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: var(--primary-dark);
            margin-bottom: 8px;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem var(--primary-light);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 16px;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            body {
                font-size: 15px;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
            
            .chairman-name {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header animate-fade-in-up">
        <img src="/images/logo.jpg" alt="Logo">
        <h1>จัดการข้อมูลประธาน</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <!-- Date Display -->
        <div class="current-date animate-fade-in-up animate-delay-1">
            <i class="bi bi-calendar-event"></i>
            <span><?= htmlspecialchars($current_date_thai) ?></span>
        </div>
        
        <!-- Stats Card -->
        <div class="stats-card animate-fade-in-up animate-delay-2">
            <i class="bi bi-people-fill"></i>
            <h3><?= $total_chairman ?></h3>
            <p>จำนวนประธานทั้งหมด</p>
        </div>
        
        <!-- Chairman List -->
        <div class="chairman-list animate-fade-in-up animate-delay-3">
            <div class="list-header">
                <i class="bi bi-list-ul me-2"></i>
                <span>รายการประธาน</span>
            </div>
            
            <?php if (empty($chairman_list)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>ยังไม่มีข้อมูลประธาน<br>คลิกปุ่ม + เพื่อเพิ่มข้อมูลใหม่</p>
                </div>
            <?php else: ?>
                <?php foreach ($chairman_list as $index => $chairman): ?>
                    <div class="chairman-item">
                        <div class="chairman-info">
                            <span class="chairman-number"><?= $index + 1 ?></span>
                            <span class="chairman-name"><?= htmlspecialchars($chairman['name']) ?></span>
                        </div>
                        <div class="chairman-actions">
                            <button class="btn-edit" onclick="editChairman(<?= $chairman['id'] ?>, '<?= htmlspecialchars($chairman['name'], ENT_QUOTES) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-delete" onclick="deleteChairman(<?= $chairman['id'] ?>, '<?= htmlspecialchars($chairman['name'], ENT_QUOTES) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Button -->
    <button class="add-btn" onclick="showAddModal()">
        <i class="bi bi-plus-lg"></i>
    </button>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="m_dashboard" class="nav-item">
            <i class="bi bi-house"></i>
            <span>หน้าแรก</span>
        </a>
        <a href="m_manage" class="nav-item">
            <i class="bi bi-calendar-plus"></i>
            <span>จัดการงาน</span>
        </a>
        <a href="#" class="nav-item active">
            <i class="bi bi-people"></i>
            <span>ประธาน</span>
        </a>
        <a href="logout" class="nav-item">
            <i class="bi bi-box-arrow-right"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // แสดง Animation เมื่อโหลดหน้าเสร็จ
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-fade-in-up');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                setTimeout(() => {
                    el.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1}s forwards`;
                }, 50);
            });
            
            // แสดงข้อความหลัง action
            <?php if ($action_message): ?>
                Swal.fire({
                    icon: '<?= $action_type ?>',
                    title: '<?= $action_type === "success" ? "สำเร็จ!" : "เกิดข้อผิดพลาด!" ?>',
                    text: '<?= $action_message ?>',
                    confirmButtonColor: '#0a9396',
                    confirmButtonText: 'ตลับ',
                    timer: 2000
                });
            <?php endif; ?>
        });
        
        // แสดง Modal เพิ่มประธาน
        function showAddModal() {
            Swal.fire({
                title: '<i class="bi bi-person-plus me-2"></i>เพิ่มประธานใหม่',
                html: `
                    <div class="text-start">
                        <label class="form-label">ชื่อประธาน</label>
                        <input type="text" id="chairman_name" class="form-control" placeholder="กรุณากรอกชื่อประธาน" required>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i>เพิ่มข้อมูล',
                cancelButtonText: '<i class="bi bi-x-circle me-1"></i>ยกเลิก',
                confirmButtonColor: '#0a9396',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const name = document.getElementById('chairman_name').value;
                    if (!name) {
                        Swal.showValidationMessage('กรุณากรอกชื่อประธาน');
                        return false;
                    }
                    return { name: name };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // ส่งข้อมูลไปยังเซิร์ฟเวอร์
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="name" value="${result.value.name}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // แก้ไขข้อมูลประธาน
        function editChairman(id, currentName) {
            Swal.fire({
                title: '<i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลประธาน',
                html: `
                    <div class="text-start">
                        <label class="form-label">ชื่อประธาน</label>
                        <input type="text" id="chairman_name" class="form-control" value="${currentName}" required>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i>บันทึกการแก้ไข',
                cancelButtonText: '<i class="bi bi-x-circle me-1"></i>ยกเลิก',
                confirmButtonColor: '#ee9b00',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
                    const name = document.getElementById('chairman_name').value;
                    if (!name) {
                        Swal.showValidationMessage('กรุณากรอกชื่อประธาน');
                        return false;
                    }
                    return { name: name };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // ส่งข้อมูลไปยังเซิร์ฟเวอร์
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="${id}">
                        <input type="hidden" name="name" value="${result.value.name}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // ลบข้อมูลประธาน
        function deleteChairman(id, name) {
            Swal.fire({
                title: 'ยืนยันการลบข้อมูล?',
                html: `คุณต้องการลบ<br><strong>${name}</strong><br>ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-trash me-1"></i>ลบข้อมูล',
                cancelButtonText: '<i class="bi bi-x-circle me-1"></i>ยกเลิก',
                confirmButtonColor: '#bb3e03',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // ส่งข้อมูลไปยังเซิร์ฟเวอร์
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>

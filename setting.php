<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@200&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #e9f5f5; 
            font-family: 'Anuphan', sans-serif; 
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
            padding: 20px; 
            flex: 1;
        }
        .btn-primary { 
            background-color: #0a9396; 
            border-color: #0a9396; 
        }
        .btn-primary:hover { 
            background-color: #005f73; 
            border-color: #005f73; 
        }
        .btn-secondary { 
            background-color: #6c757d;
            border-color: #6c757d; 
        }
        .btn-secondary:hover { 
            background-color: #5a6268;
            border-color: #5a6268; 
        }
        .btn-danger { 
            background-color: #d9534f; 
            border-color: #d9534f; 
        }
        .btn-danger:hover { 
            background-color: #c9302c; 
            border-color: #c9302c; 
        }
        .card {
            margin-bottom: 15px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #0a9396;
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body {
            padding: 15px;
        }
        .card-body p {
            margin-bottom: 5px;
            white-space: normal; /* อนุญาตให้ขึ้นบรรทัดใหม่ได้ */
            word-wrap: break-word; /* ตัดคำหากยาวเกิน */
        }
        .card-body strong {
            color: #0a9396;
        }
        footer {
            background-color: #0a9396;
            color: white;
            text-align: center;
            padding: 10px 0;
            margin-top: auto;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .menu-container {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 10px;
            width: 100%;
        }
        .menu-container .btn {
            font-size: 0.875rem;
            padding: 6px 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            flex-grow: 1;
            max-width: 120px;
        }
        .floating-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn-floating {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <img src="/images/logo.jpg" alt="Logo">
        <h1>จัดการผู้ใช้งาน</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
    </div>

    <!-- ข้อความยินดีต้อนรับและสถานะผู้ใช้งาน -->
    <center>
        <div class="welcome-message mt-3">
            ยินดีต้อนรับ, <?= htmlspecialchars($first_name) ?> <?= htmlspecialchars($last_name) ?>
            <span class="badge <?= $role === 'user' ? 'bg-warning text-dark' : 'bg-danger text-white' ?>">
                <?= $role === 'user' ? 'ผู้ใช้งานทั่วไป' : 'ผู้ดูแลระบบ' ?>
            </span>
        </div>
    </center>

    <div class="container-fluid">
        <!-- เมนูปุ่มกลับหน้าแรก, เพิ่มผู้ใช้งาน, และออกจากระบบ -->
        <div class="menu-container">
            <a href="index" class="btn btn-secondary">
                <i class="bi bi-house-door"></i> กลับหน้าแรก
            </a>
            <a href="logout" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
            </a>
        </div>

        <!-- แบบฟอร์มเพิ่มผู้ใช้งาน -->
        <div class="card mt-3">
            <div class="card-header">เพิ่มผู้ใช้งาน</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="text" name="username" class="form-control mb-2" placeholder="ชื่อผู้ใช้งาน" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="first_name" class="form-control mb-2" placeholder="ชื่อจริง" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="last_name" class="form-control mb-2" placeholder="นามสกุล" required>
                        </div>
                        <div class="col-md-6">
                            <input type="email" name="email" class="form-control mb-2" placeholder="อีเมล์" required>
                        </div>
                        <div class="col-md-6">
                            <input type="password" name="password" class="form-control mb-2" placeholder="รหัสผ่าน" required>
                        </div>
                        <div class="col-md-6">
                            <select name="role" class="form-control mb-2" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" name="add_user" class="btn btn-primary w-100">เพิ่มผู้ใช้งาน</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- แสดงรายการผู้ใช้งานในรูปแบบ Card -->
        <div class="row mt-3">
            <?php while ($user = mysqli_fetch_assoc($users)): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <div><strong><?= $user['username'] ?></strong></div>
                            <div>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $user['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?delete_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <p><strong>ชื่อจริง:</strong> <?= $user['first_name'] ?></p>
                            <p><strong>นามสกุล:</strong> <?= $user['last_name'] ?></p>
                            <p><strong>อีเมล์:</strong> <?= $user['email'] ?></p>
                            <p><strong>บทบาท:</strong> 
                                <span class="badge <?= $user['role'] === 'user' ? 'bg-warning text-dark' : 'bg-danger text-white' ?>">
                                    <?= $user['role'] === 'user' ? 'ผู้ใช้งานทั่วไป' : 'ผู้ดูแลระบบ' ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal แก้ไขผู้ใช้งาน -->
                <div class="modal fade" id="editModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editModalLabel">แก้ไขผู้ใช้งาน</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                    <div class="mb-3">
                                        <label>ชื่อผู้ใช้งาน</label>
                                        <input type="text" name="username" class="form-control" value="<?= $user['username'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>ชื่อจริง</label>
                                        <input type="text" name="first_name" class="form-control" value="<?= $user['first_name'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>นามสกุล</label>
                                        <input type="text" name="last_name" class="form-control" value="<?= $user['last_name'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>อีเมล์</label>
                                        <input type="email" name="email" class="form-control" value="<?= $user['email'] ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>บทบาท</label>
                                        <select name="role" class="form-control" required>
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="edit_user" class="btn btn-primary">บันทึก</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        © 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
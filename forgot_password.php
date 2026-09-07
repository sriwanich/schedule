<?php
session_start();
include 'config.php'; // เชื่อมต่อฐานข้อมูล

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        // ค้นหาข้อมูลผู้ใช้จากฐานข้อมูล
        $query = "SELECT first_name, last_name FROM users WHERE email = ?";
        if ($stmt = $connect->prepare($query)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                // ดึงข้อมูลผู้ใช้
                $user = $result->fetch_assoc();
                $new_password = bin2hex(random_bytes(4));  // สร้างรหัสผ่านใหม่
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT); // แฮชรหัสผ่าน

                // อัปเดตรหัสผ่านในฐานข้อมูล
                $update_query = "UPDATE users SET password = ? WHERE email = ?";
                if ($update_stmt = $connect->prepare($update_query)) {
                    $update_stmt->bind_param("ss", $hashed_password, $email);

                    if ($update_stmt->execute()) {
                        // ส่งอีเมล
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp-relay.brevo.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = '7a1334003@smtp-brevo.com';  // ชื่อผู้ใช้ SMTP
                            $mail->Password = 'VN6pykWXEGZSQn9x';  // รหัสผ่าน SMTP
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;
                            $mail->CharSet = 'UTF-8';

                            $mail->setFrom('no-reply@dpmpryg.online', 'ขอรหัสผ่านใหม่ - ระบบปฏิทินและนัดหมาย');
                            $mail->addAddress($email);

                            // เนื้อหาอีเมล
                            $mail->isHTML(true);
                            $mail->Subject = 'รหัสผ่านใหม่ของคุณ';
$mail->Body = "
    <div style='max-width: 600px; margin: auto; font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px; border-radius: 10px;'>
        <div style='background-color: #ffffff; border-radius: 4px 4px 0 0; overflow: hidden; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);'>
            
            <!-- Card Header -->
            <div style='padding: 40px 20px 20px; text-align: center;'>
                <img src='https://schedule.dpmpryg.online/images/preview.png' alt='DPMPRY Logo' margin-bottom: 10px;'>
            </div>

            <!-- Card Body -->
            <div style='padding: 20px 30px 40px;'>
                <p style='margin-bottom: 20px; font-size: 16px; color: #333;'>คุณได้ทำการขอรหัสผ่านใหม่จากเรา นี่คือรหัสผ่านใหม่ของคุณ</p>

                <div style='margin: 20px 0; text-align: center;'>
                    <span style='background-color: #D32F2F; color: #ffffff; padding: 10px 20px; font-size: 18px; font-weight: bold; border-radius: 5px; display: inline-block;'>{$new_password}</span>
                </div>

                <p style='font-size: 14px; color: #666;'>กรุณาเข้าสู่ระบบและเปลี่ยนรหัสผ่านใหม่ทันทีเพื่อความปลอดภัย</p>

                <p style='margin-top: 20px; color: #333; font-size: 16px;'>ขอแสดงความนับถือ,<br>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
            </div>
        </div>
    </div>";


                            $mail->send();
                            $success = "รหัสผ่านใหม่ถูกส่งไปยังอีเมลของคุณแล้ว";
                        } catch (Exception $e) {
                            $error = "เกิดข้อผิดพลาดในการส่งอีเมล: " . $mail->ErrorInfo;
                        }
                    } else {
                        $error = "เกิดข้อผิดพลาดในการอัปเดตรหัสผ่าน";
                    }
                    $update_stmt->close();
                }
            } else {
                $error = "ไม่พบอีเมลนี้ในระบบ";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ลืมรหัสผ่าน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anuphan&display=swap" rel="stylesheet">
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
        .forgot-password-container { 
            width: 100%; 
            max-width: 400px; 
            padding: 20px; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.2); 
            margin: auto; 
        }
        .btn-primary { 
            background-color: #0a9396; 
            border: none; 
            width: 100%; 
            transition: background 0.3s, transform 0.2s; 
        }
        .btn-primary:hover { 
            background-color: #005f73; 
            transform: scale(1.05); 
        }
        .message { 
            margin-top: 10px; 
            transition: opacity 0.3s; 
        }
        footer { 
            background-color: #0a9396; 
            color: white; 
            text-align: center; 
            padding: 10px 0; 
            margin-top: auto; 
            font-size: 0.9rem; 
        }
/* Card styles */
.card {
  background-color: #ffffff;
  border-radius: 4px 4px 0 0;
  overflow: hidden;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.card-header {
  padding: 40px 20px 20px;
  text-align: center;
}

.card-header h1 {
  font-size: 42px;
  font-weight: 400;
  color: #111111;
  margin: 0;
}

.card-body {
  padding: 20px 30px 40px;
}

.card-body p {
  margin-bottom: 20px;
  font-size: 16px;
}
    </style>
</head>
<body>
    <div class="header">
        <img src="/images/logo.jpg" alt="Logo">
        <h1>แจ้งเหตุสาธารณภัย</h1>
        <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
    </div><br><br>

    <div class="container-fluid">
        <div class="forgot-password-container text-center">
            <h2 class="mb-3">ลืมรหัสผ่าน</h2>
            <form method="post" action="">
                <div class="mb-3">
                    <input type="email" name="email" class="form-control" placeholder="อีเมล" required>
                </div>
                <button type="submit" class="btn btn-primary">ส่งรหัสผ่านใหม่</button>
            </form>
            <div class="message">
                <?php if ($error): ?>
                    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success mt-3"><?php echo $success; ?></div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <a href="login" class="text-decoration-none">กลับไปหน้าเข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

    <footer>
        © 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง
    </footer>
</body>
</html>

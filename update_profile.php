<?php
session_start();
include 'config.php'; // เปลี่ยนจาก db_config.php เป็น config.php

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
        }

        $_SESSION['success'] = "อัปเดตข้อมูลสำเร็จ";
    } else {
        $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
    }

    header("Location: dashboard"); // เปลี่ยนเส้นทางกลับไปที่ dashboard
    exit();
}
?>
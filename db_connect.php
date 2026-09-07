<?php
$host = 'localhost'; // แก้ไขเป็นค่าจริงของคุณ
$dbname = 'dpmpry_schedule'; // แก้ไขเป็นค่าจริงของคุณ
$username = 'dpmpryg_sriwanich'; // แก้ไขเป็นค่าจริงของคุณ
$password = 'M@t34a053896077'; // แก้ไขเป็นค่าจริงของคุณ

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
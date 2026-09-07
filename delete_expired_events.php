<?php
$connect = mysqli_connect("localhost", "dpmpryg_sriwanich", "M@t34a053896077", "dpmpry_schedule");  
mysqli_query($connect, "SET NAMES 'utf8' ");
date_default_timezone_set('Asia/Bangkok'); // ตั้งค่าโซนเวลาเป็นไทย

$today = date("Y-m-d");
$current_time = date("H:i:s");

// ลบข้อมูลที่วันและเวลาน้อยกว่าปัจจุบัน
$query = "DELETE FROM events WHERE date < '$today' OR (date = '$today' AND time_from < '$current_time')";

if (mysqli_query($connect, $query)) {
    echo "ลบข้อมูลที่เลยกำหนดเวลาเรียบร้อยแล้ว";
} else {
    echo "เกิดข้อผิดพลาดในการลบข้อมูล: " . mysqli_error($connect);
}

mysqli_close($connect);
?>

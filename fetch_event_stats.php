<?php
session_start();
include 'config.php';

// ดึงวันที่เริ่มต้นและสิ้นสุดจากตาราง events
$date_range_query = mysqli_query($connect, "SELECT MIN(date) as start_date, MAX(date) as end_date FROM events");
$date_range_data = mysqli_fetch_assoc($date_range_query);
$start_date = $date_range_data['start_date'];
$end_date = $date_range_data['end_date'];

// ดึงข้อมูลสถิติ
$ongoing_query = mysqli_query($connect, "SELECT COUNT(*) as ongoing FROM events 
    WHERE (date = CURDATE() AND time_from <= CURTIME() AND time_to >= CURTIME())");
$ongoing_data = mysqli_fetch_assoc($ongoing_query);
$ongoing = $ongoing_data['ongoing'];

$completed_query = mysqli_query($connect, "SELECT COUNT(*) as completed FROM events 
    WHERE (date < CURDATE()) OR (date = CURDATE() AND time_to < CURTIME())");
$completed_data = mysqli_fetch_assoc($completed_query);
$completed = $completed_data['completed'];

$upcoming_query = mysqli_query($connect, "SELECT COUNT(*) as upcoming FROM events 
    WHERE (date > CURDATE()) OR (date = CURDATE() AND time_from > CURTIME())");
$upcoming_data = mysqli_fetch_assoc($upcoming_query);
$upcoming = $upcoming_data['upcoming'];

// ส่งข้อมูลกลับเป็น JSON
echo json_encode([
    'ongoing' => $ongoing,
    'completed' => $completed,
    'upcoming' => $upcoming,
    'start_date' => $start_date,
    'end_date' => $end_date
]);
?>
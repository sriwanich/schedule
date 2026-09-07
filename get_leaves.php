<?php
include 'config.php';

header('Content-Type: application/json');

try {
    $query = "SELECT name, leave_type, location, start_datetime, end_datetime, details 
              FROM responsible_persons 
              WHERE leave_type IS NOT NULL AND leave_type != ''
              ORDER BY start_datetime DESC";
    
    $result = mysqli_query($connect, $query);
    $leaves = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $leaves[] = $row;
    }
    
    echo json_encode($leaves);
} catch (Exception $e) {
    echo json_encode([]);
}
?>
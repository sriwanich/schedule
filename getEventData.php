<?php
session_start();
include_once 'config.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'Event ID is required']);
    exit();
}

$eventId = mysqli_real_escape_string($connect, $_GET['id']);

// Query to get event data
$query = "SELECT * FROM events WHERE id = '$eventId'";
$result = mysqli_query($connect, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(['error' => 'Event not found']);
    exit();
}

$eventData = mysqli_fetch_assoc($result);

// Format date and time for better display
if ($eventData['date']) {
    $eventData['date'] = date('Y-m-d', strtotime($eventData['date']));
}

// Return the event data
echo json_encode($eventData);
?>
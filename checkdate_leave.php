<?php
// Database connection settings
$host = 'localhost';
$dbname = 'dpmpry_schedule';
$username = 'dpmpryg_sriwanich';
$password = 'M@t34a053896077';

try {
    // Create a PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current datetime in the same format as your database stores it
    $currentDateTime = date('Y-m-d H:i:s');

    // Prepare the update query
    $updateQuery = "UPDATE responsible_persons 
                    SET leave_type = NULL, 
                        location = NULL, 
                        start_datetime = NULL, 
                        end_datetime = NULL, 
                        details = NULL 
                    WHERE end_datetime IS NOT NULL 
                    AND end_datetime <= :currentDateTime";

    // Prepare and execute the statement
    $stmt = $pdo->prepare($updateQuery);
    $stmt->bindParam(':currentDateTime', $currentDateTime);
    $stmt->execute();

    // Get the number of affected rows
    $affectedRows = $stmt->rowCount();

    echo "Successfully updated $affectedRows record(s) where end_datetime has passed.";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
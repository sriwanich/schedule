<?php
session_start();
include 'config.php'; // ไฟล์เชื่อมต่อฐานข้อมูล
date_default_timezone_set('Asia/Bangkok');

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function thai_date_full($date) {
    if (!$date) return "";
    $thai_days = ["อาทิตย์", "จันทร์", "อังคาร", "พุธ", "พฤหัสบดี", "ศุกร์", "เสาร์"];
    $thai_months = [
        "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน",
        "05" => "พฤษภาคม", "06" => "มิถุนายน", "07" => "กรกฎาคม", "08" => "สิงหาคม",
        "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
    ];
    $time = strtotime($date);
    return "วัน" . $thai_days[date("w", $time)] . " ที่ " . intval(date("d", $time)) . " " . $thai_months[date("m", $time)] . " " . (date("Y", $time) + 543);
}

// ดึงข้อมูลจากฐานข้อมูล
$query = "SELECT * FROM events ORDER BY date ASC, time_from ASC";
$result = mysqli_query($connect, $query);

$grouped_events = [];
$today = date("Y-m-d"); // วันที่ปัจจุบัน
$today_events = []; // เก็บงานในวันนี้
$completed_events = 0; // ตัวแปรสำหรับนับจำนวนงานที่เสร็จสิ้นไปแล้วในวันนี้

while ($row = mysqli_fetch_assoc($result)) {
    $date = $row["date"];
    $isPast = strtotime($row["date"] . " " . $row["time_to"]) < time();

    if (!$isPast) {
        $grouped_events[$date][] = [
            "time_from" => substr($row["time_from"], 0, 5) . " น.",
            "title" => $row["title"],
            "location" => $row["location"],
            "chairman" => $row["chairman"],
            "description" => $row["description"],
            "Dress" => $row["Dress"], // การแต่งกาย
            "department" => $row["department"], // หน่วยงาน
            "file_name" => $row["file_name"], // ไฟล์แนบ
            "coordinator_name" => $row["coordinator_name"], // ผู้ประสานงาน
            "coordinator_phone" => $row["coordinator_phone"] // เบอร์โทร
        ];

        // ตรวจสอบงานในวันนี้
        if ($date == $today) {
            $today_events[] = $row;
        }
    } else {
        // ถ้างานเสร็จสิ้นแล้ว และเป็นงานในวันนี้
        if ($date == $today) {
            $completed_events++;
        }
    }
}
mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทินและนัดหมายงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Anuphan">
    <style>
        body {
            font-family: 'Anuphan', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            height: 100px;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 2rem;
            margin: 0;
        }
        .header p {
            font-size: 1.5rem;
            margin: 0;
        }
        .status-container {
            padding: 10px 20px;
            text-align: center;
        }
        .current-date {
            font-size: 1.6rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #0a9396;
        }
        .badge {
            font-size: 16px;
            padding: 10px 20px;
            border-radius: 20px;
        }
        .badge-primary {
            background-color: #0a9396;
        }
        .badge-info {
            background-color: #4682B4;
        }
        .badge-danger {
            background-color: #ff6b6b;
        }
        .card-container {
            max-width: 100%;
            margin: auto;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #0a9396;
            color: #fff;
            padding: 15px;
            font-weight: bold;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            font-size: 1.2rem;
        }
        .card-body {
            padding: 20px;
            text-align: left;
			font-size: 16px;
        }
        .badge-chairman {
            background-color: #4682B4;
        }
        .badge-person {
            background-color: #0a9396;
        }
        .divider {
            border-top: 2px solid #0a9396;
            margin: 15px 0;
        }
        footer {
            background-color: #0a9396;
            color: white;
            text-align: center;
            padding: 10px 0;
            margin-top: auto;
            font-size: 0.9rem;
        }
        .floating-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #0a9396;
            color: white;
            padding: 15px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease;
        }
        .floating-button:hover {
            background-color: #077b7e;
        }
        .floating-button i {
            margin-right: 8px;
        }
        /* เปลี่ยนสีลิงค์ทั้งหมดให้เป็นสีข้อความปกติ */
        a {
            color: inherit; /* สีลิงค์เหมือนกับสีข้อความปกติ */
            text-decoration: none; /* ลบเส้นใต้ */
        }
        a:hover {
            color: inherit; /* สีลิงค์เมื่อ hover เหมือนกับสีข้อความปกติ */
            text-decoration: none; /* ลบเส้นใต้เมื่อ hover */
        }
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.2rem;
            }
            .header p {
                font-size: 0.8rem;
            }
            .floating-button {
                font-size: 14px;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header w-100">
        <img src="/images/logo.jpg" alt="Logo">
        <div>
            <h1>ปฏิทินและนัดหมายงาน</h1>
            <p>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
        </div>
    </div>

    <!-- แสดงวันที่ปัจจุบัน -->
    <div class="status-container">
        <div class="current-date">
            <?php echo thai_date_full($today); ?>
        </div>
        <?php
        if (!empty($today_events)) {
            echo "<span class='badge badge-primary'>วันนี้มีภารกิจจำนวน (" . count($today_events) . ")ภารกิจ</span>";
        } elseif ($completed_events == 0) {
            // แสดงข้อความ "ไม่มีวาระงานในวันนี้" เฉพาะเมื่อไม่มีงานที่เสร็จสิ้นไปแล้ว
            echo "<span class='badge badge-danger'>ไม่มีภารกิจในวันนี้</span>";
        }

        // แสดงผลงานที่เสร็จสิ้นไปแล้วในวันนี้
        if ($completed_events > 0) {
            echo "<span class='badge badge-info'>วันนี้เสร็จไปแล้วทั้งหมด $completed_events ภารกิจ</span>";
        }
        ?>
    </div>

    <div class="container-fluid card-container">
        <?php
        // แสดงผลข้อมูล
        foreach ($grouped_events as $date => $events) {
            echo "<div class='card'>
                    <div class='card-header'>" . thai_date_full($date) . "</div>";

            foreach ($events as $index => $event) {
                echo "<div class='card-body'>
                        <p><strong><i class='bi bi-clock'></i> เวลา:</strong> {$event['time_from']}</p>
                        <p><strong><i class='bi bi-card-heading'></i> ภารกิจ:</strong> {$event['title']}</p>
                        <p><strong><i class='bi bi-geo-alt'></i> สถานที่:</strong> 
                            <a href='https://www.google.com/maps/search/?api=1&query=" . urlencode($event['location']) . "' target='_blank'>{$event['location']}</a>
                        </p>";

                if (!empty($event['chairman'])) {
                    echo "<p><strong><i class='bi bi-person'></i> ประธาน:</strong> 
                            <span class='badge badge-chairman'>{$event['chairman']}</span>
                          </p>";
                }

                echo "<p><strong><i class='bi bi-people'></i> ผู้ปฏิบัติ:</strong> ";
                $persons = explode(' ', $event['description']);
                foreach ($persons as $person) {
                    if (!empty(trim($person))) {
                        echo "<span class='badge badge-person'>$person</span> ";
                    }
                }
                echo "</p>";

                // แสดงการแต่งกาย
                if (!empty($event['Dress'])) {
                    echo "<p><strong><i class='bi bi-person-bounding-box'></i> การแต่งกาย:</strong> {$event['Dress']}</p>";
                }

                // แสดงหน่วยงาน
                if (!empty($event['department'])) {
                    echo "<p><strong><i class='bi bi-building'></i> เจ้าของเรื่อง:</strong> {$event['department']}</p>";
                }

                // แสดงไฟล์แนบ
                if (!empty($event['file_name'])) {
                    $file_extension = strtolower(pathinfo($event['file_name'], PATHINFO_EXTENSION));
                    $icon = '';

                    // ตรวจสอบนามสกุลไฟล์และกำหนดไอคอนที่เหมาะสม
                    if ($file_extension == 'pdf') {
                        $icon = '<i class="bi bi-filetype-pdf"></i>';
                    } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $icon = '<i class="bi bi-image"></i>';
                    } else {
                        $icon = '<i class="bi bi-file-earmark"></i>'; // ไอคอนเริ่มต้นสำหรับไฟล์อื่นๆ
                    }

                    echo "<p>
                            <a href='uploads/{$event['file_name']}' target='_blank'>{$icon} เอกสารแนบ</a>
                          </p>";
                }

                // แสดงผู้ประสานงานและเบอร์โทร
                if (!empty($event['coordinator_name']) || !empty($event['coordinator_phone'])) {
                    echo "<p><strong><i class='bi bi-person-lines-fill'></i> ผู้ประสานงาน:</strong> ";
                    if (!empty($event['coordinator_name'])) {
                        echo "{$event['coordinator_name']}&nbsp;"; // เว้นวรรค
                    }
                    if (!empty($event['coordinator_phone'])) {
                        echo "<a href='tel:{$event['coordinator_phone']}'><i class='bi bi-telephone'></i> {$event['coordinator_phone']}</a>";
                    }
                    echo "</p>";
                }

                if ($index < count($events) - 1) {
                    echo "<hr class='divider'>";
                }
                echo "</div>";
            }
            echo "</div>";
        }
        ?>
    </div>

    <!-- เพิ่มปุ่มลอย -->
    <div class="floating-button" onclick="handleManageSystem()">
        <i class="bi bi-gear"></i>
        <span>จัดการระบบ</span>
    </div>

    <footer>
        <p>&copy; 2024 สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function handleManageSystem() {
            // ตรวจสอบประเภทอุปกรณ์
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

            if (isMobile) {
                // ถ้าเป็น smartphone/tablet ให้ redirect ไปที่ m_dashboard
                window.location.href = 'm_dashboard';
            } else {
                // ถ้าเป็น PC/laptop ให้ redirect ไปที่ dashboard
                window.location.href = 'dashboard';
            }
        }
    </script>
</body>
</html>
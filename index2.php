<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตารางกิจกรรม</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Anuphan', sans-serif;
            background-color: #FFFFFF;
            text-align: center;
            padding: 10px;
        }
        h2 {
            color: #005f73;
            margin: 20px 0;
        }
/* ===== สไตล์การ์ดที่ปรับปรุงใหม่ ===== */
.card-container {
    max-width: 1200px;
    margin: auto;
    padding: 0 15px;
}

.card {
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
    border: none;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
}

.card-header {
    background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
    color: #ffffff;
    padding: 20px 25px;
    font-weight: 500;
    font-size: 1.1rem;
    border: none;
    position: relative;
    overflow: hidden;
}

.card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0%, 100% { transform: translate(-50%, -50%); }
    50% { transform: translate(0%, 0%); }
}

.card-body {
    padding: 25px;
    text-align: left;
    background: white;
    position: relative;
}

/* ===== ปรับปรุง Info Row ===== */
.info-row {
    display: flex;
    align-items: flex-start;
    margin-bottom: 15px;
    font-size: 0.95rem;
    padding: 10px;
    border-radius: 10px;
    transition: background-color 0.2s ease;
}

.info-row:hover {
    background-color: rgba(10, 147, 150, 0.05);
}

.info-icon {
    margin-right: 12px;
    color: #0a9396;
    min-width: 28px;
    text-align: center;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-content {
    flex: 1;
    line-height: 1.6;
}

.info-content strong {
    color: #005f73;
    font-weight: 600;
}



/* ===== ปรับปรุง Time Category Row ===== */
.time-category-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 12px;
    background: linear-gradient(135deg, rgba(10, 147, 150, 0.08) 0%, rgba(10, 147, 150, 0.03) 100%);
    border-radius: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.time-section {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 200px;
}

.category-section {
    display: flex;
    align-items: center;
}

/* ===== ปรับปรุง Badges ===== */
.badge {
    font-size: 0.85rem;
    margin-right: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.badge-chairman {
    background: linear-gradient(135deg, #4682B4 0%, #3a6d99 100%);
    color: white;
}

.badge-person {
    background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
    color: white;
}

.badge-category {
    font-size: 0.85rem;
    margin-right: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    color: white;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.badge-department {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

/* ===== ปรับปรุง Status Badges ===== */
.badge-ongoing {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
    position: relative;
    animation: pulse-badge 2s infinite;
}

.badge-upcoming {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
    color: white;
}

.badge-cancelled {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.badge-postponed {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #000;
}

.bg-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
    color: white;
}

@keyframes pulse-badge {
    0%, 100% {
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
    }
    50% {
        box-shadow: 0 4px 16px rgba(40, 167, 69, 0.6);
    }
}

/* ===== Divider ===== */
.divider {
    border: none;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, #0a9396 50%, transparent 100%);
    margin: 20px 0;
    opacity: 0.3;
}

/* ===== Status Cards ปรับปรุงใหม่ ===== */
.status-cards {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 40px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    width: 100%;
    padding: 0 15px;
}

.status-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 25px 30px;
    flex: 1;
    min-width: 200px;
    max-width: calc(33.33% - 30px);
    text-align: center;
    border: none;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.status-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    transition: height 0.3s ease;
}

.status-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}

.status-card:hover::before {
    height: 100%;
    opacity: 0.05;
}

.status-card.completed::before {
    background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
}

.status-card.ongoing::before {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
}

.status-card.upcoming::before {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
}

.status-card .count {
    font-size: 3.5rem;
    font-weight: 700;
    margin: 15px 0;
    background: linear-gradient(135deg, #0a9396 0%, #005f73 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.status-card.completed .count {
    background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.status-card.ongoing .count {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.status-card.upcoming .count {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.status-card .title {
    font-size: 0.95rem;
    color: #495057;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.status-card .title i {
    font-size: 1.1rem;
}

/* ===== Zoom Icon ===== */
.zoom-icon {
    height: 28px;
    vertical-align: middle;
    margin-left: 8px;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    transition: transform 0.2s ease;
}

.zoom-icon:hover {
    transform: scale(1.1);
}

/* ===== With Text ===== */
.with-text {
    color: #495057;
    font-size: 0.9rem;
    margin: 0 8px;
    font-weight: 600;
}

/* ===== Responsive Design ===== */
@media (max-width: 768px) {
    .status-card {
        max-width: 100%;
        min-width: 280px;
    }
    
    .card {
        border-radius: 15px;
        margin-bottom: 20px;
    }
    
    .card-header {
        padding: 18px 20px;
        font-size: 1rem;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .time-category-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .category-section {
        margin-left: 0;
        margin-top: 10px;
    }
    
    .status-card .count {
        font-size: 2.8rem;
    }
}

/* ===== No Events Message ===== */
.no-events {
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
    text-align: center;
    font-size: 1.1rem;
}

.no-events i {
    font-size: 3rem;
    display: block;
    margin-bottom: 15px;
    opacity: 0.5;
}
    </style>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Anuphan">
</head>
<body>

<h2>สำนักงานป้องกันและบรรเทาสาธารณภัยจังหวัดระยอง</h2>

<?php
// เริ่มนับเวลาโหลดหน้า
$start_time = microtime(true);

// เชื่อมต่อฐานข้อมูล
include 'config.php';

// ตั้งค่าโซนเวลา
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูลเวอร์ชันล่าสุด
try {
    $version_query = "SELECT version FROM changelog ORDER BY release_date DESC LIMIT 1";
    $version_result = mysqli_query($connect, $version_query);
    $version_row = mysqli_fetch_assoc($version_result);
    $current_version = $version_row['version'] ?? '1.0.0';
} catch (Exception $e) {
    error_log("Database error when fetching version: " . $e->getMessage());
    $current_version = '1.0.0';
}

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
    return "วัน" . $thai_days[date("w", $time)] . "ที่ " . intval(date("d", $time)) . " " . $thai_months[date("m", $time)] . " พ.ศ. " . (date("Y", $time) + 543);
}

// ฟังก์ชันแปลงวันที่และเวลาเป็นภาษาไทย
function thai_datetime($datetime) {
    if (!$datetime) return "";
    $thai_months = [
        "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน",
        "05" => "พฤษภาคม", "06" => "มิถุนายน", "07" => "กรกฎาคม", "08" => "สิงหาคม",
        "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
    ];
    $time = strtotime($datetime);
    $date_part = intval(date("d", $time)) . " " . $thai_months[date("m", $time)] . " พ.ศ. " . (date("Y", $time) + 543);
    $time_part = date("H:i", $time) . " น.";
    return $date_part;
}

// ดึงข้อมูลการลาของบุคลากร
$query_leaves = "SELECT * FROM responsible_persons WHERE leave_type IS NOT NULL AND leave_type != ''";
$result_leaves = mysqli_query($connect, $query_leaves);
$leaves = [];
$leave_count = 0;

while ($row = mysqli_fetch_assoc($result_leaves)) {
    $leaves[] = $row;
    $leave_count++;
}

// ดึงข้อมูลกิจกรรมทั้งหมดของวันนี้
$today = date('Y-m-d');
$query_today = "SELECT e.*, ec.name as category_name, ec.color as category_color 
                FROM events e
                LEFT JOIN event_categories ec ON e.category_id = ec.id
                WHERE e.date = '$today' 
                AND (e.status IS NULL OR e.status NOT IN ('เลื่อน', 'ยกเลิก'))
                ORDER BY e.time_from ASC";
$result_today = mysqli_query($connect, $query_today);

// นับจำนวนกิจกรรมในแต่ละสถานะ
$completed_count = 0;
$ongoing_count = 0;
$upcoming_count = 0;

$current_time = time();
$five_minutes_ago = date('H:i:s', strtotime('-5 minutes'));

while ($row = mysqli_fetch_assoc($result_today)) {
    $event_start = strtotime($row['time_from']);
    $event_end = strtotime($row['time_to']);
    
    if ($current_time > $event_end) {
        // งานจบแล้ว
        $completed_count++;
    } elseif ($current_time >= $event_start && $current_time <= $event_end) {
        // งานกำลังดำเนินการ
        $ongoing_count++;
    } elseif ($current_time < $event_start) {
        // งานยังไม่เริ่ม
        $upcoming_count++;
    }
}

// แสดงการ์ดสถานะ
echo '<div class="status-cards">';
echo '<div class="status-card completed">';
echo '<div class="count">' . $completed_count . '</div>';
echo '<div class="title"><i class="bi bi-check-circle"></i> ภารกิจวันนี้ที่เสร็จสิ้นไปแล้ว</div>';
echo '</div>';

echo '<div class="status-card ongoing">';
echo '<div class="count">' . $ongoing_count . '</div>';
echo '<div class="title"><i class="bi bi-activity"></i> ภารกิจที่กำลังดำเนินการ</div>';
echo '</div>';

echo '<div class="status-card upcoming">';
echo '<div class="count">' . $upcoming_count . '</div>';
echo '<div class="title"><i class="bi bi-alarm"></i> ภารกิจวันนี้ที่รอดำเนินการ</div>';
echo '</div>';
echo '</div>';

// ดึงข้อมูลกิจกรรมจากฐานข้อมูล โดยไม่รวมกิจกรรมที่ถูกเลื่อนหรือยกเลิก
// และไม่รวมกิจกรรมที่จบไปแล้วเกิน 5 นาที
$query = "SELECT e.*, ec.name as category_name, ec.color as category_color 
          FROM events e
          LEFT JOIN event_categories ec ON e.category_id = ec.id
          WHERE (e.status IS NULL OR e.status NOT IN ('เลื่อน', 'ยกเลิก')) 
          AND (
              (e.date > CURDATE()) OR 
              (e.date = CURDATE() AND e.time_to >= TIME(DATE_SUB(NOW(), INTERVAL 5 MINUTE)))
          )
          ORDER BY e.date ASC, e.time_from ASC";
$result = mysqli_query($connect, $query);

// จัดกลุ่มกิจกรรมตามวันที่
$grouped_events = [];
while ($row = mysqli_fetch_assoc($result)) {
    $date = $row["date"];
    $grouped_events[$date][] = [
        "time_from" => $row["time_from"],
        "time_to" => $row["time_to"],
        "formatted_time_from" => substr($row["time_from"], 0, 5) . " น.",
        "formatted_time_to" => substr($row["time_to"], 0, 5) . " น.",
        "title" => $row["title"],
        "location" => $row["location"],
        "department" => $row["department"],
        "chairman" => $row["chairman"],
        "description" => $row["description"],
        "status" => $row["status"],
        "event_id" => $row["id"],
        "category_name" => $row["category_name"],
        "category_color" => $row["category_color"]
    ];
}

// ปิดการเชื่อมต่อฐานข้อมูล
mysqli_close($connect);

// ฟังก์ชันคำนวณเวลาที่เหลือจนถึงเริ่มงาน
function calculate_time_remaining($date, $time_from) {
    $current_time = time();
    $event_datetime = strtotime("$date $time_from");
    
    if ($current_time >= $event_datetime) {
        return false; // งานเริ่มแล้ว
    }
    
    $diff = $event_datetime - $current_time;
    
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    
    return [
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds
    ];
}
?>

<!-- แสดงผลข้อมูลกิจกรรม -->
<div class='card-container'>
    <?php
    if (empty($grouped_events)) {
        echo "<div class='card'>
                <div class='card-body no-events'>
                    <i class='bi bi-calendar-x'></i> ไม่มีกิจกรรมในขณะนี้
                </div>
              </div>";
    } else {
        foreach ($grouped_events as $date => $events) {
            echo "<div class='card'>
                    <div class='card-header'>" . thai_date_full($date) . "</div>";
            
            foreach ($events as $index => $event) {
                $time_remaining = calculate_time_remaining($date, $event['time_from']);
                $is_ongoing = false;
                $is_upcoming = false;
                $is_completed = false;
           
                // ตรวจสอบสถานะงาน
                if ($time_remaining === false) {
                    // งานเริ่มแล้ว
                    $current_time = time();
                    $event_end = strtotime("$date {$event['time_to']}");
                    
                    if ($current_time <= $event_end) {
                        $is_ongoing = true; // งานยังไม่จบ (กำลังดำเนินการ)
                    } else {
                        $is_completed = true; // งานจบแล้ว
                    }
                } else {
                    $is_upcoming = true; // งานยังไม่เริ่ม
                }
                
                echo "<div class='card-body' id='event-{$event['event_id']}'>
                        <div class='time-category-row'>
                            <div class='time-section'>
                                <div class='info-icon'><i class='bi bi-clock'></i></div>
                                <div class='info-content'><strong>เวลา:</strong> {$event['formatted_time_from']}";
                
                // แสดงสถานะงาน
                if ($is_ongoing) {
                    echo " <span class='badge badge-ongoing'><i class='bi bi-activity'></i> กำลังดำเนินการ</span>";
                } elseif ($is_upcoming) {
                    echo " <span class='badge badge-upcoming'><i class='bi bi-alarm'></i> เริ่มใน <span class='countdown' data-date='{$date}' data-time='{$event['time_from']}'>กำลังคำนวณ...</span></span>";
                } elseif ($is_completed) {
                    echo " <span class='badge bg-secondary'><i class='bi bi-check-circle'></i> เสร็จสิ้น</span>";
                }
                
                echo "</div>
                            </div>";
                
                // แสดงประเภทงานทางด้านขวา
                if (!empty($event['category_name'])) {
                    echo "<div class='category-section'>
                            <span class='badge badge-category' style='background-color: {$event['category_color']}'>
                                {$event['category_name']}
                            </span>
                          </div>";
                }
                
                echo "</div>"; // ปิด time-category-row
                        
                echo "<div class='info-row'>
                        <div class='info-icon'><i class='bi bi-card-heading'></i></div>
                        <div class='info-content'><strong>ภารกิจ:</strong> ";
                
                // Check for (Zoom Meeting) and (Google Meet) in title and replace with icons
                $title = $event['title'];
                if (strpos($title, '(Zoom Meeting)') !== false) {
                    $title = str_replace('(Zoom Meeting)', '<img src="images/zoom.png" alt="Zoom Meeting" class="zoom-icon">', $title);
                }
                if (strpos($title, '(Google Meet)') !== false) {
                    $title = str_replace('(Google Meet)', '<img src="images/google.png" alt="Google Meet" class="zoom-icon">', $title);
                }
                echo $title;
                
                echo "</div>
                      </div>";
                        
                echo "<div class='info-row'>
                        <div class='info-icon'><i class='bi bi-geo-alt'></i></div>
                        <div class='info-content'><strong>สถานที่:</strong> {$event['location']}</div>
                      </div>";

                // แสดงข้อมูลหน่วยงานเจ้าของเรื่อง
                if (!empty($event['department'])) {
                    echo "<div class='info-row'>
                            <div class='info-icon'><i class='bi bi-building'></i></div>
                            <div class='info-content'>
                                <strong>หน่วยงานเจ้าของเรื่อง:</strong> 
                                <span>{$event['department']}</span>
                            </div>
                          </div>";
                }

                // แสดงข้อมูล "ประธาน"
                if (!empty($event['chairman'])) {
                    echo "<div class='info-row'>
                            <div class='info-icon'><i class='bi bi-person'></i></div>
                            <div class='info-content'>
                                <strong>ประธาน:</strong> 
                                <span class='badge badge-chairman'>{$event['chairman']}</span>
                            </div>
                          </div>";
                }

                // แสดงข้อมูล "ผู้ปฏิบัติ" (แก้ไขตามข้อกำหนดใหม่)
                echo "<div class='info-row'>
                        <div class='info-icon'><i class='bi bi-people'></i></div>
                        <div class='info-content'>
                            <strong>";

                // แยกและกรองชื่อผู้ปฏิบัติ
                $persons = array_filter(explode(' ', $event['description']), function($value) {
                    return trim($value) !== '';
                });

                if (in_array("หน.ปภ", $persons)) {
                    echo "ลงนัด:</strong> ";
                    
                    // แสดง "หน.ปภ" เป็นคนแรก
                    $first_person_key = array_search("หน.ปภ", $persons);
                    $first_person = $persons[$first_person_key];
                    unset($persons[$first_person_key]);
                    echo "<span class='badge badge-person'>$first_person</span>";
                    
                    // ถ้ามีคนอื่นเหลือให้แสดง "พร้อมด้วย"
                    if (!empty($persons)) {
                        echo " <span class='with-text' STYLE='font-size:16px'><b>พร้อมด้วย</b></span> ";
                        foreach ($persons as $person) {
                            echo "<span class='badge badge-person'>$person</span> ";
                        }
                    }
                } else {
                    echo "มอบหมาย:</strong> ";
                    foreach ($persons as $person) {
                        echo "<span class='badge badge-person'>$person</span> ";
                    }
                }

                echo "</div>
                      </div>";

                // เพิ่มเส้นแบ่งกิจกรรมภายในวันเดียวกัน
                if ($index < count($events) - 1) {
                    echo "<hr class='divider'>";
                }

                echo "</div>";
            }

            echo "</div>";
        }
    }
    ?>
</div>
<!-- แสดงเวอร์ชันและเวลาการโหลดหน้า -->
        <div class="version-info " title="ดูบันทึกการเปลี่ยนแปลงในแต่ละเวอร์ชั่น">
            เวอร์ชัน <?= htmlspecialchars($current_version) ?>
        </div>
<div class="page-load-time">
    หน้านี้สร้างขึ้นในเวลา <?php echo number_format(microtime(true) - $start_time, 3); ?> วินาที
</div>

<!-- ปุ่ม Back to Top -->
<div class="back-to-top" id="backToTop">
    <i class="bi bi-arrow-up"></i>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- JavaScript สำหรับนับเวลาถอยหลัง -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ฟังก์ชันอัปเดตการนับถอยหลัง
    function updateCountdowns() {
        const now = new Date();
        const today = now.toISOString().split('T')[0]; // วันที่ปัจจุบันในรูปแบบ YYYY-MM-DD
        const countdownElements = document.querySelectorAll('.countdown');
        
        countdownElements.forEach(element => {
            const eventDate = element.getAttribute('data-date');
            const eventTime = element.getAttribute('data-time');
            const eventDateTime = new Date(`${eventDate}T${eventTime}`);
            
            // คำนวณเวลาที่เหลือในหน่วยมิลลิวินาที
            const diff = eventDateTime - now;
            
            if (diff <= 0) {
                // งานเริ่มแล้ว
                const eventCard = element.closest('.card-body');
                const timeBadge = element.closest('.badge-upcoming');
                
                if (timeBadge) {
                    timeBadge.classList.replace('badge-upcoming', 'badge-ongoing');
                    timeBadge.innerHTML = '<i class="bi bi-activity"></i> กำลังดำเนินการ';
                    
                    // เพิ่มเอฟเฟกต์กระพริบสำหรับกิจกรรมที่เพิ่งเริ่มในช่วง 5 นาทีแรก
                    const fiveMinutesAfterStart = new Date(eventDateTime.getTime() + (5 * 60 * 1000));
                    if (now < fiveMinutesAfterStart) {
                        timeBadge.style.animation = 'pulse 1s infinite, blink 1s infinite';
                    }
                    
                    // ตรวจสอบว่ายังอยู่ในช่วงเวลางานหรือไม่
                    const timeTo = eventCard.querySelector('.info-content').textContent.match(/- (\d{2}:\d{2})/);
                    if (timeTo) {
                        const endTime = new Date(`${eventDate}T${timeTo[1]}`);
                        const timeSinceEnd = now - endTime;
                        const fiveMinutes = 5 * 60 * 1000; // 5 นาทีในมิลลิวินาที
                        
                        if (timeSinceEnd > 0) {
                            if (timeSinceEnd > fiveMinutes) {
                                // ซ่อนกิจกรรมที่จบไปแล้วเกิน 5 นาที
                                eventCard.style.display = 'none';
                                
                                // ตรวจสอบว่ายังมีกิจกรรมอื่นในวันที่นี้หรือไม่
                                const card = eventCard.closest('.card');
                                const visibleEvents = card.querySelectorAll('.card-body:not([style*="display: none"])').length;
                                if (visibleEvents === 0) {
                                    card.style.display = 'none';
                                }
                            } else {
                                timeBadge.classList.replace('badge-ongoing', 'bg-secondary');
                                timeBadge.innerHTML = '<i class="bi bi-check-circle"></i> เสร็จสิ้น';
                            }
                        }
                    }
                }
            } else {
                // ตรวจสอบว่าเป็นกิจกรรมของวันนี้เท่านั้น
                if (eventDate !== today) {
                    element.closest('.badge-upcoming').style.display = 'none';
                    return;
                }
                
                // คำนวณเวลาที่เหลือ
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                // แสดงผล - ซ่อนชั่วโมงถ้าเป็น 0
                if (hours > 0) {
                    element.innerHTML = `${hours} ชั่วโมง ${minutes} นาที`;
                } else if (minutes > 0) {
                    element.innerHTML = `${minutes} นาที ${seconds} วินาที`;
                }
            }
        });
    }
    
    // อัปเดตทุก 1 วินาที
    updateCountdowns();
    setInterval(updateCountdowns, 1000);
    
    // ฟังก์ชันสำหรับปุ่ม Back to Top
    const backToTopButton = document.getElementById('backToTop');
    
    // ตรวจสอบการเลื่อนหน้าจอ
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });
    
    // เมื่อคลิกปุ่ม ให้เลื่อนขึ้นด้านบน
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});
</script>
</body>
</html>

<?php
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/client_secret_650044454038-ttnukte3pb98kge2vb9bj1cbv1har4tm.apps.googleusercontent.com.json'); // ✅ ใช้ชื่อไฟล์โดยไม่มี '/'
$client->setRedirectUri('https://schedule.dpmpry.online/oauth2callback.php');
$client->addScope(Google_Service_Calendar::CALENDAR);
$client->setAccessType('offline');

if (!isset($_GET['code'])) {
    die('ไม่มีรหัส Authorization');
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
    die('เกิดข้อผิดพลาด: ' . $token['error']);
}

// ✅ บันทึก Token ลงไฟล์ (ใช้ 'token.json' ไม่มี '/')
file_put_contents('token.json', json_encode($token));

echo "✅ การยืนยันสำเร็จ! Token ถูกบันทึกแล้ว";
?>

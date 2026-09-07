<?php
require '/var/www/vhosts/dpmpry.online/schedule.dpmpry.online/vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/client_secret_650044454038-ttnukte3pb98kge2vb9bj1cbv1har4tm.apps.googleusercontent.com.json');
$client->setRedirectUri('https://schedule.dpmpry.online/oauth2callback.php'); // เปลี่ยนเป็น URL ของคุณ
$client->addScope(Google_Service_Calendar::CALENDAR);
$client->setAccessType('offline');
$client->setPrompt('consent');

// โหลด Token ถ้ามี
if (file_exists(__DIR__ . '/token.json')) {
    $accessToken = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
    $client->setAccessToken($accessToken);
}


// รีเฟรช Token หากหมดอายุ
if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents(__DIR__ . 'token.json', json_encode($client->getAccessToken()));
    } else {
        die('❌ Refresh token not found. Please re-authenticate.');
    }
}

$service = new Google_Service_Calendar($client);
$calendarId = 'primary'; // หรือใช้ Calendar ID ที่กำหนดเอง
?>

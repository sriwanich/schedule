<?php
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/client_secret_650044454038-ttnukte3pb98kge2vb9bj1cbv1har4tm.apps.googleusercontent.com.json');
$client->setRedirectUri('https://schedule.dpmpry.online/oauth2callback.php'); // เปลี่ยนเป็น URL ที่ถูกต้อง
$client->addScope(Google_Service_Calendar::CALENDAR);
$client->setAccessType('offline'); // ต้องใช้ offline เพื่อให้ได้ Refresh Token
$client->setPrompt('consent');

$authUrl = $client->createAuthUrl();
echo "เปิดลิงก์นี้ในเบราว์เซอร์ของคุณเพื่อรับโทเค็น: <a href='{$authUrl}'>{$authUrl}</a>";
?>

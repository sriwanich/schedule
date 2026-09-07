<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Dompdf\FontMetrics;

// ตั้งค่า Options
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);

// สร้าง Dompdf instance
$dompdf = new Dompdf($options);

// สร้าง FontMetrics instance
$fontMetrics = $dompdf->getFontMetrics();

// ลงทะเบียนฟอนต์
$fontMetrics->registerFont(
    ['family' => 'THSarabunNew Italic', 'style' => 'normal', 'weight' => 'normal'],
    'vendor/dompdf/dompdf/lib/fonts/THSarabunNew Italic.ttf'
);

echo "Font THSarabunNew Italic registered successfully!";
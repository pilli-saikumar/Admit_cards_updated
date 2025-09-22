<?php
session_start();

// Generate random captcha code
$captcha_code = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 6);

// Store in session
$_SESSION['captcha_code'] = $captcha_code;

// Create blank image
$width = 150;
$height = 50;
$image = imagecreate($width, $height);

// Colors
$bg_color = imagecolorallocate($image, 255, 255, 255); // white
$text_color = imagecolorallocate($image, 0, 0, 0);     // black
$noise_color = imagecolorallocate($image, 100, 120, 180);

// Add noise
for ($i = 0; $i < 1000; $i++) {
    imagefilledellipse($image, mt_rand(0,$width), mt_rand(0,$height), 1, 1, $noise_color);
}

// Add text
$font_size = 20;
$angle = mt_rand(-5, 5);
$font_file = __DIR__ . "/fonts/arial.ttf"; // You need a TTF font file
$bbox = imagettfbbox($font_size, $angle, $font_file, $captcha_code);
$x = ($width - ($bbox[2] - $bbox[0])) / 2;
$y = ($height - ($bbox[1] - $bbox[7])) / 2;
$y += $font_size;

imagettftext($image, $font_size, $angle, $x, $y, $text_color, $font_file, $captcha_code);

// Output image
header("Content-Type: image/png");
imagepng($image);
imagedestroy($image);

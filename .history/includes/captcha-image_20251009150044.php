<?php
session_start();

function generateCaptcha($length = 5)
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $captcha = '';
    for ($i = 0; $i < $length; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $captcha;
}

// Generate new captcha code
$captcha_code = generateCaptcha();
$_SESSION['captcha_code'] = $captcha_code;

// Create image
$width = 130;
$height = 50;
$image = imagecreate($width, $height);

// Colors
$bg_color = imagecolorallocate($image, 255, 255, 255);
$text_color = imagecolorallocate($image, 0, 0, 0);
$line_color = imagecolorallocate($image, 200, 200, 200);

// Add random lines (noise)
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand() % 50, 130, rand() % 50, $line_color);
}

// Add random dots
for ($i = 0; $i < 1000; $i++) {
    imagesetpixel($image, rand() % 130, rand() % 50, $line_color);
}

// Add captcha text
$font_size = 22;
$font = __DIR__ . '/arial.ttf'; // Path to a TTF font file
if (file_exists($font)) {
    imagettftext($image, $font_size, rand(-10, 10), 15, 35, $text_color, $font, $captcha_code);
} else {
    imagestring($image, 5, 30, 15, $captcha_code, $text_color);
}

// Output image
header("Content-Type: image/png");
imagepng($image);
imagedestroy($image);
?>

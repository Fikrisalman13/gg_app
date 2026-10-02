<?php
// Export chart image as JPG from base64 data URL.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$imageData = isset($_POST['image_data']) ? trim((string) $_POST['image_data']) : '';
if ($imageData === '') {
    http_response_code(400);
    echo 'Missing image data';
    exit;
}

$filename = isset($_POST['filename']) ? trim((string) $_POST['filename']) : '';
if ($filename === '') {
    $filename = 'Grafik_IPAL_' . date('Ymd_His');
}
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

if (strpos($imageData, 'data:image/') === 0) {
    $parts = explode(',', $imageData, 2);
    $imageData = isset($parts[1]) ? $parts[1] : '';
}

$binary = base64_decode($imageData, true);
if ($binary === false) {
    http_response_code(400);
    echo 'Invalid image data';
    exit;
}

if (!function_exists('imagecreatefromstring')) {
    http_response_code(500);
    echo 'GD extension is not available';
    exit;
}

$image = @imagecreatefromstring($binary);
if ($image === false) {
    http_response_code(415);
    echo 'Unsupported image format';
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="' . $filename . '.jpg"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

imagejpeg($image, null, 92);
imagedestroy($image);
exit;

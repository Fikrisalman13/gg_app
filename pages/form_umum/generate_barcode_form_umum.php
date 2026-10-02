<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/barcode_token_helper.php';

$ticket = isset($_GET['ticket']) ? strtoupper(trim((string)$_GET['ticket'])) : '';
$format = isset($_GET['format']) ? strtolower(trim((string)$_GET['format'])) : 'png';

if (!preg_match('/^(IKS|IKP|IPC)-[A-Z0-9-]+$/', $ticket)) {
    http_response_code(400);
    if ($format === 'svg') {
        header('Content-Type: image/svg+xml; charset=utf-8');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="120"><text x="16" y="60" font-family="Arial" font-size="16">Ticket tidak valid</text></svg>';
    } else {
        header('Content-Type: image/png');
        $im = imagecreatetruecolor(320, 120);
        $white = imagecolorallocate($im, 255, 255, 255);
        $red = imagecolorallocate($im, 220, 53, 69);
        imagefilledrectangle($im, 0, 0, 319, 119, $white);
        imagestring($im, 4, 16, 50, 'Ticket tidak valid', $red);
        imagepng($im);
        imagedestroy($im);
    }
    exit;
}

$token = '';
if (isset($conn) && $conn) {
    $createdBy = $_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? $_SESSION['username'] ?? null;
    $token = formUmumBarcodeGetOrCreateToken($conn, $ticket, $createdBy);
}

$codeToEncode = (!empty($token)) ? $token : $ticket;

// Standard Code 39 pattern table: 9 elements per character (5 bars, 4 spaces)
// '1' = wide element, '0' = narrow element
$patterns = [
    '0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000',
    '4' => '000110001', '5' => '100110000', '6' => '001110000', '7' => '000100101',
    '8' => '100100100', '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
    'C' => '101001000', 'D' => '000011001', 'E' => '100011000', 'F' => '001011000',
    'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
    'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
    'O' => '100010010', 'P' => '001010010', 'Q' => '000000111', 'R' => '100000110',
    'S' => '001000110', 'T' => '000010110', 'U' => '110000001', 'V' => '011000001',
    'W' => '111000000', 'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
    '-' => '010000101', '.' => '110000100', ' ' => '011000100', '$' => '010101000',
    '/' => '010100010', '+' => '010001010', '%' => '000101010', '*' => '010010100',
];

$value = '*' . $codeToEncode . '*';
$narrow = 2; // narrow element width in px
$wide = 5;   // wide element width in px (2.5x narrow)
$gap = 2;    // inter-character gap space in px
$quiet = 20; // quiet zone at start and end
$barHeight = 76;
$topY = 12;

$x = $quiet;
$bars = '';

foreach (str_split($value) as $char) {
    if (!isset($patterns[$char])) {
        continue;
    }

    $isBar = true;
    foreach (str_split($patterns[$char]) as $bit) {
        $w = ($bit === '1') ? $wide : $narrow;
        if ($isBar) {
            $bars .= '<rect x="' . $x . '" y="' . $topY . '" width="' . $w . '" height="' . $barHeight . '" fill="#000"/>';
        }
        $x += $w;
        $isBar = !$isBar;
    }
    $x += $gap; // inter-character gap (narrow space)
}

$totalWidth = $x - $gap + $quiet;
$totalHeight = $barHeight + ($topY * 2);

if ($format === 'svg') {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store, must-revalidate');
    header('Pragma: no-cache');

    echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $totalWidth . '" height="' . $totalHeight . '" viewBox="0 0 ' . $totalWidth . ' ' . $totalHeight . '" role="img" aria-label="Barcode ' . htmlspecialchars($codeToEncode, ENT_QUOTES, 'UTF-8') . '">';
    echo '<rect width="100%" height="100%" fill="#fff"/>';
    echo $bars;
    echo '</svg>';
    exit;
}

// Default format: PNG via GD (crisp 2x scale)
$scale = 2;
$imgWidth = $totalWidth * $scale;
$imgHeight = $totalHeight * $scale;
$im = imagecreatetruecolor($imgWidth, $imgHeight);
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);
imagefilledrectangle($im, 0, 0, $imgWidth - 1, $imgHeight - 1, $white);

$x = $quiet;
foreach (str_split($value) as $char) {
    if (!isset($patterns[$char])) {
        continue;
    }

    $isBar = true;
    foreach (str_split($patterns[$char]) as $bit) {
        $w = ($bit === '1') ? $wide : $narrow;
        if ($isBar) {
            imagefilledrectangle(
                $im,
                $x * $scale,
                $topY * $scale,
                ($x + $w) * $scale - 1,
                ($topY + $barHeight) * $scale - 1,
                $black
            );
        }
        $x += $w;
        $isBar = !$isBar;
    }
    $x += $gap;
}

header('Content-Type: image/png');
header('Cache-Control: no-store, must-revalidate');
header('Pragma: no-cache');
imagepng($im);
imagedestroy($im);
exit;

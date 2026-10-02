<?php
require_once __DIR__ . '/kb_helpers.php';

kb_context($conn);

$uploadedFile = $_FILES['image'] ?? null;
if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
    kb_fail('upload_image', 'Upload gambar gagal.', 422);
}

if ($uploadedFile['size'] > 2 * 1024 * 1024) {
    kb_fail('upload_image', 'Ukuran gambar maksimal 2 MB.', 422);
}

$mimeType = kb_detect_upload_mime($uploadedFile['tmp_name']);
$extensionMap = [
    'image/gif' => 'gif',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if (!isset($extensionMap[$mimeType])) {
    kb_fail('upload_image', 'File harus berupa gambar.', 422, ['mime' => $mimeType]);
}

$relativeDir = 'uploads/knowledge_base/' . date('Y/m');
$absoluteDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/' . $relativeDir;
if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true)) {
    kb_fail('upload_image', 'Folder upload tidak dapat dibuat.', 500);
}

$fileName = bin2hex(random_bytes(16)) . '.' . $extensionMap[$mimeType];
$absolutePath = $absoluteDir . '/' . $fileName;
$saved = kb_save_uploaded_image($uploadedFile['tmp_name'], $absolutePath, $mimeType);

if (!$saved) {
    kb_fail('upload_image', 'Gagal menyimpan gambar.', 500);
}

kb_json([
    'ok' => true,
    'url' => '/gg_app/' . $relativeDir . '/' . $fileName,
]);

/**
 * Detect uploaded file MIME using file content, not client filename.
 */
function kb_detect_upload_mime(string $temporaryPath): string
{
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    return (string)$fileInfo->file($temporaryPath);
}

/**
 * Save uploaded image with GD compression when possible.
 * GIF is copied as-is to preserve animation.
 */
function kb_save_uploaded_image(string $temporaryPath, string $destinationPath, string $mimeType): bool
{
    if (!extension_loaded('gd') || $mimeType === 'image/gif') {
        return move_uploaded_file($temporaryPath, $destinationPath);
    }

    $image = kb_create_gd_image($temporaryPath, $mimeType);
    if (!$image) {
        return move_uploaded_file($temporaryPath, $destinationPath);
    }

    $image = kb_resize_image_if_needed($image, 1400);
    $saved = kb_write_gd_image($image, $destinationPath, $mimeType);
    imagedestroy($image);

    return $saved;
}

/**
 * Create GD image resource for supported MIME types.
 */
function kb_create_gd_image(string $temporaryPath, string $mimeType)
{
    if ($mimeType === 'image/jpeg') {
        return @imagecreatefromjpeg($temporaryPath);
    }

    if ($mimeType === 'image/png') {
        return @imagecreatefrompng($temporaryPath);
    }

    if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($temporaryPath);
    }

    return false;
}

/**
 * Resize large editor images to keep storage and page rendering practical.
 */
function kb_resize_image_if_needed($image, int $maxDimension)
{
    $width = imagesx($image);
    $height = imagesy($image);

    if ($width <= $maxDimension && $height <= $maxDimension) {
        return $image;
    }

    $scale = min($maxDimension / $width, $maxDimension / $height);
    $newWidth = (int)($width * $scale);
    $newHeight = (int)($height * $scale);
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

    imagealphablending($resizedImage, false);
    imagesavealpha($resizedImage, true);
    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);

    return $resizedImage;
}

/**
 * Persist GD image resource using a reasonable quality/compression setting.
 */
function kb_write_gd_image($image, string $destinationPath, string $mimeType): bool
{
    if ($mimeType === 'image/jpeg') {
        return imagejpeg($image, $destinationPath, 82);
    }

    if ($mimeType === 'image/png') {
        return imagepng($image, $destinationPath, 7);
    }

    if ($mimeType === 'image/webp' && function_exists('imagewebp')) {
        return imagewebp($image, $destinationPath, 82);
    }

    return false;
}

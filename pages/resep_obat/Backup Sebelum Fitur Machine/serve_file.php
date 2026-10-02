<?php
// pages/resep_obat/serve_file.php
// Serves uploaded files (images/PDFs) with proper headers and session check.
session_start();

if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$file = $_GET['file'] ?? '';

// Security: only allow files inside uploads/resep_obat/, no path traversal
if (empty($file) || strpos($file, '..') !== false) {
    http_response_code(400);
    exit('Invalid file');
}

// Normalize: strip leading slashes and force to be inside uploads/resep_obat/
$file = ltrim($file, '/');
// Strip any leading gg_app/ prefix if present
$file = preg_replace('#^gg_app/#', '', $file);

$basePath = realpath(__DIR__ . '/../../');
$fullPath = realpath($basePath . '/' . $file);

// Ensure the resolved path is still within uploads/resep_obat/
$allowedDir = realpath($basePath . '/uploads/resep_obat');
if ($fullPath === false || strpos($fullPath, $allowedDir) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');
readfile($fullPath);
exit;

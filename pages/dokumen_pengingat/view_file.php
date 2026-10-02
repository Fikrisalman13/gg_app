<?php
/**
 * view_file.php - Versi Perbaikan untuk Windows/XAMPP
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$path = isset($_GET['path']) ? urldecode($_GET['path']) : '';
if (!$path) die('Path kosong.');

// Normalisasi path
$baseDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, realpath(__DIR__));
$cleanPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
$fullPath = $baseDir . DIRECTORY_SEPARATOR . $cleanPath;

// Cek keberadaan file secara langsung sebelum realpath (karena realpath sering gagal di Windows jika ada karakter tertentu)
if (file_exists($fullPath)) {
    $targetFile = realpath($fullPath);
    
    // Keamanan: Pastikan tetap di dalam folder dokumen_pengingat
    if ($targetFile && strncasecmp($targetFile, $baseDir, strlen($baseDir)) === 0) {
        $mime = mime_content_type($targetFile);
        if (strtolower(pathinfo($targetFile, PATHINFO_EXTENSION)) === 'pdf') {
            $mime = 'application/pdf';
        }

        // Bersihkan output buffer untuk mencegah file rusak
        if (ob_get_length()) ob_clean();

        $filename = basename($targetFile);
        $disposition = (isset($_GET['download']) && $_GET['download'] == '1') ? 'attachment' : 'inline';

        header("Content-Type: $mime");
        header("Content-Disposition: $disposition; filename=\"$filename\"");
        header("Content-Length: " . filesize($targetFile));
        header("Cache-Control: private, max-age=0, must-revalidate");
        header("Pragma: public");

        $fp = fopen($targetFile, 'rb');
        fpassthru($fp);
        exit;
    } else {
        die("Akses ditolak atau file di luar jangkauan.\nFull: $fullPath\nBase: $baseDir");
    }
} else {
    die("File fisik tidak ditemukan di: " . $fullPath);
}

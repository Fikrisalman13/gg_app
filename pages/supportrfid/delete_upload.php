<?php
require 'auth_check.php';  // Pastikan hanya user yang terautentikasi yang bisa mengakses

// Ambil tanggal mulai dan tanggal selesai dari POST
$tglMulai = $_POST['tgl_mulai'] ?? '';
$tglSelesai = $_POST['tgl_selesai'] ?? '';
$logDir = __DIR__ . '/logs';

// Pastikan user yang aktif adalah IT7 atau sesuai dengan yang diinginkan
$username = $_SESSION['username'] ?? 'Guest';
if ($username !== 'IT7') {
    die('Unauthorized access');
}

// Cek jika tanggal mulai atau tanggal selesai kosong
if (!$tglMulai && !$tglSelesai) {
    // Jika tidak ada rentang tanggal, hapus semua log
    $logFiles = glob($logDir . '/upload_logs_*.json'); // Semua file log
    if (count($logFiles) > 0) {
        foreach ($logFiles as $file) {
            unlink($file);  // Hapus file log
        }
        echo 'Semua log telah dihapus.';
    } else {
        echo 'Tidak ada file log yang ditemukan untuk dihapus.';
    }
    exit;
}

// Format tanggal untuk perbandingan
$tglMulai = strtotime($tglMulai . ' 00:00:00');
$tglSelesai = strtotime($tglSelesai . ' 23:59:59');

// Cek dan hapus file log berdasarkan tanggal
$logFiles = glob($logDir . '/upload_logs_*.json');  // Semua file log
$deletedCount = 0;  // Untuk menghitung jumlah file yang dihapus

foreach ($logFiles as $file) {
    // Ambil tanggal dari nama file (contoh: upload_logs_2025-10-17.json)
    preg_match('/upload_logs_(\d{4}-\d{2}-\d{2})/', basename($file), $matches);
    if (isset($matches[1])) {
        $fileDate = strtotime($matches[1]);

        // Hapus file jika tanggalnya sesuai dengan rentang tanggal yang dipilih
        if ($fileDate >= $tglMulai && $fileDate <= $tglSelesai) {
            unlink($file);  // Hapus file log
            $deletedCount++;
        }
    }
}

// Berikan informasi tentang jumlah log yang dihapus
if ($deletedCount > 0) {
    echo "Log yang sesuai dengan rentang tanggal telah dihapus. Jumlah log yang dihapus: $deletedCount.";
} else {
    echo "Tidak ada log yang sesuai dengan rentang tanggal yang dipilih.";
}

exit;
?>

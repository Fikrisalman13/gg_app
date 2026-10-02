<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['UserId'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Get filter parameters from URL
$filter = $_GET['filter'] ?? 'all';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$day = $_GET['day'] ?? date('d');
/**
 * Helper to compress and resize an image on the fly to a temporary JPEG file.
 * Returns the path to the temporary file, or the original path on failure/no GD.
 */
function getCompressedImageTempPath($originalPath, $maxWidth = 800, $quality = 75) {
    if (empty($originalPath) || !file_exists($originalPath)) {
        return $originalPath;
    }
    
    // Check if GD extension is loaded
    if (!extension_loaded('gd')) {
        return $originalPath;
    }
    
    // Get image info
    $info = @getimagesize($originalPath);
    if ($info === false) {
        return $originalPath;
    }
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
    // If image is already reasonably small and small in file size, skip processing
    if ($width <= $maxWidth && filesize($originalPath) < 200 * 1024) {
        return $originalPath;
    }
    
    // Calculate new dimensions keeping aspect ratio
    $newWidth = $width;
    $newHeight = $height;
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
    }
    
    // Load image resource
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = @imagecreatefromjpeg($originalPath);
            break;
        case 'image/png':
            $source = @imagecreatefrompng($originalPath);
            break;
        case 'image/gif':
            $source = @imagecreatefromgif($originalPath);
            break;
        default:
            return $originalPath;
    }
    
    if (!$source) {
        return $originalPath;
    }
    
    // Create new blank image
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    // Handle transparency for PNG/GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Generate secure temp file path
    $tempDir = sys_get_temp_dir();
    $tempFile = tempnam($tempDir, 'pdf_img_');
    if ($tempFile === false) {
        imagedestroy($thumb);
        imagedestroy($source);
        return $originalPath;
    }
    
    // TCPDF needs correct file extension to detect format
    unlink($tempFile);
    $tempFile .= '.jpg';
    
    // Save as JPEG (best compression ratio for photos)
    $success = imagejpeg($thumb, $tempFile, $quality);
    
    // Clean resources
    imagedestroy($thumb);
    imagedestroy($source);
    
    if ($success) {
        return $tempFile;
    }
    
    return $originalPath;
}

// Function to get all shutdown reports with filters
function getAllShutdownReports($conn, $filter, $month, $year, $day) {
    $sql = "SELECT 
    r.id, 
    r.waktu, 
    r.bagian, 
    r.jumlah_dimatikan, 
    r.jumlah_aktif,
    r.foto, 
    r.keterangan, 
    u.UserName as pelapor, 
    u.UserId,
    e.nama_lengkap as nama_pelapor,
    CASE 
        WHEN r.foto IS NOT NULL AND r.foto <> '' THEN 1 
        ELSE 0 
    END as has_photo
FROM dbo.report_pemadaman r
LEFT JOIN dbo.SMUserMs u ON r.user_id = u.UserId
LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp";
    
    $params = [];
    switch ($filter) {
        case 'daily':
            $sql .= " WHERE DAY(r.waktu) = ? AND MONTH(r.waktu) = ? AND YEAR(r.waktu) = ?";
            $params = [$day, $month, $year];
            break;
        case 'monthly':
            $sql .= " WHERE MONTH(r.waktu) = ? AND YEAR(r.waktu) = ?";
            $params = [$month, $year];
            break;
        case 'yearly':
            $sql .= " WHERE YEAR(r.waktu) = ?";
            $params = [$year];
            break;
    }
    
    $sql .= " ORDER BY r.waktu DESC";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $reports = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $reports[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return $reports;
}

// Function to get summary data with filters
function getSummaryData($conn, $filter, $month, $year, $day) {
    $sql = "SELECT COUNT
	( * ) AS total_laporan,
	SUM ( jumlah_dimatikan ) AS total_dimatikan,
	SUM ( jumlah_aktif ) AS total_aktif,
	SUM ( CASE WHEN foto IS NOT NULL AND foto <> '' THEN 1 ELSE 0 END ) AS total_foto,
	bagian AS departemen 
FROM
	dbo.report_pemadaman";
    
    $params = [];
    switch ($filter) {
        case 'daily':
            $sql .= " WHERE DAY(waktu) = ? AND MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$day, $month, $year];
            break;
        case 'monthly':
            $sql .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$month, $year];
            break;
        case 'yearly':
            $sql .= " WHERE YEAR(waktu) = ?";
            $params = [$year];
            break;
    }
    
    $sql .= " GROUP BY bagian
              ORDER BY COUNT(*) DESC";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $summary = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $summary[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return $summary;
}

// Function to get time period statistics
function getTimePeriodStats($conn, $filter, $month, $year, $day) {
    $stats = [
        'total_reports' => 0,
        'total_shutdown' => 0,
        'total_active' => 0,
        'total_devices' => 0,
        'total_photos' => 0,
        'top_department' => '',
        'most_active_day' => '',
        'avg_shutdown' => 0
    ];
    
    $sql = "SELECT 
                COUNT(*) as total_reports,
                SUM(jumlah_dimatikan) as total_shutdown,
                SUM(jumlah_aktif) as total_active,
                SUM(jumlah_dimatikan + jumlah_aktif) as total_devices,
                SUM(CASE WHEN foto IS NOT NULL AND foto <> '' THEN 1 ELSE 0 END) as total_photos,
                AVG(jumlah_dimatikan) as avg_shutdown
            FROM dbo.report_pemadaman";
    
    $params = [];
    switch ($filter) {
        case 'daily':
            $sql .= " WHERE DAY(waktu) = ? AND MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$day, $month, $year];
            break;
        case 'monthly':
            $sql .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params = [$month, $year];
            break;
        case 'yearly':
            $sql .= " WHERE YEAR(waktu) = ?";
            $params = [$year];
            break;
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $stats['total_reports'] = $row['total_reports'] ?? 0;
            $stats['total_shutdown'] = $row['total_shutdown'] ?? 0;
            $stats['total_active'] = $row['total_active'] ?? 0;
            $stats['total_devices'] = $row['total_devices'] ?? 0;
            $stats['total_photos'] = $row['total_photos'] ?? 0;
            $stats['avg_shutdown'] = round($row['avg_shutdown'] ?? 0, 1);
        }
        sqlsrv_free_stmt($stmt);
    }
    
    $sql_dept = "SELECT TOP 1 bagian as departemen, COUNT(*) as total
                 FROM dbo.report_pemadaman";
    
    $params_dept = [];
    switch ($filter) {
        case 'daily':
            $sql_dept .= " WHERE DAY(waktu) = ? AND MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params_dept = [$day, $month, $year];
            break;
        case 'monthly':
            $sql_dept .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params_dept = [$month, $year];
            break;
        case 'yearly':
            $sql_dept .= " WHERE YEAR(waktu) = ?";
            $params_dept = [$year];
            break;
    }
    
    $sql_dept .= " GROUP BY bagian ORDER BY total DESC";
    
    $stmt_dept = sqlsrv_query($conn, $sql_dept, $params_dept);
    if ($stmt_dept !== false) {
        $row_dept = sqlsrv_fetch_array($stmt_dept, SQLSRV_FETCH_ASSOC);
        if ($row_dept) {
            $stats['top_department'] = $row_dept['departemen'] ?? '';
        }
        sqlsrv_free_stmt($stmt_dept);
    }
    
    if ($filter == 'monthly' || $filter == 'yearly') {
        $sql_day = "SELECT TOP 1 DAY(waktu) as day, COUNT(*) as total
                    FROM dbo.report_pemadaman";
        
        $params_day = [];
        if ($filter == 'monthly') {
            $sql_day .= " WHERE MONTH(waktu) = ? AND YEAR(waktu) = ?";
            $params_day = [$month, $year];
        } else {
            $sql_day .= " WHERE YEAR(waktu) = ?";
            $params_day = [$year];
        }
        
        $sql_day .= " GROUP BY DAY(waktu) ORDER BY total DESC";
        
        $stmt_day = sqlsrv_query($conn, $sql_day, $params_day);
        if ($stmt_day !== false) {
            $row_day = sqlsrv_fetch_array($stmt_day, SQLSRV_FETCH_ASSOC);
            if ($row_day) {
                $stats['most_active_day'] = $row_day['day'] ?? '';
            }
            sqlsrv_free_stmt($stmt_day);
        }
    }
    
    return $stats;
}

try {
    // Get data
    $reports = getAllShutdownReports($conn, $filter, $month, $year, $day);
    $summary = getSummaryData($conn, $filter, $month, $year, $day);
    $stats = getTimePeriodStats($conn, $filter, $month, $year, $day);

    // Create PDF instance
    $pdf = new TCPDF('F', PDF_UNIT, 'A4', true, 'UTF-8', false);
    
    // Set document info
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('PT. Surya Usaha Mandiri');
    $pdf->SetTitle('Laporan Pemadaman IT');
    $pdf->SetSubject('Laporan Rekap Pemadaman IT');
    
    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    
    // Add a page
    $pdf->AddPage();
    
    // Set company header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'PT. Surya Usaha Mandiri', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 7, 'Laporan Rekap Pemadaman Perangkat IT', 0, 1, 'C');
    $pdf->Ln();
    
    // Report information
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(240, 240, 240);
    
    // Filter info
    $filter_info = "Periode: ";
    switch ($filter) {
        case 'daily':
            $filter_info .= "Harian - " . date('d F Y', mktime(0, 0, 0, $month, $day, $year));
            break;
        case 'monthly':
            $filter_info .= "Bulanan - " . date('F Y', mktime(0, 0, 0, $month, 1, $year));
            break;
        case 'yearly':
            $filter_info .= "Tahunan - " . $year;
            break;
        default:
            $filter_info .= "Semua Data";
    }
    
    $pdf->Cell(0, 7, $filter_info, 0, 1);
    $pdf->Cell(0, 7, 'Tanggal Cetak: ' . date('d F Y H:i:s'), 0, 1);
    $pdf->Ln();
    
    // Key Statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'STATISTIK UTAMA', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Create a table for statistics
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.3);
    
    $header = ['Total Laporan', 'Total Perangkat', 'Total Dimatikan', 'Total Aktif'];
    $w = [40, 40, 40, 57];
    
    // Header
    $pdf->SetFont('helvetica', 'B', 10);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell($w[0], 8, number_format($stats['total_reports']), 1, 0, 'C');
    $pdf->Cell($w[1], 8, number_format($stats['total_devices']), 1, 0, 'C');
    $pdf->Cell($w[2], 8, number_format($stats['total_shutdown']), 1, 0, 'C');
    $pdf->Cell($w[3], 8, number_format($stats['total_active']), 1, 1, 'C');
    
    // Second row of stats
    $header2 = ['Dokumentasi Foto', 'Rata-rata Dimatikan'];
    $pdf->SetFont('helvetica', 'B', 10);
    for ($i = 0; $i < count($header2); $i++) {
        $pdf->Cell($w[$i], 8, $header2[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell($w[0], 8, $stats['total_photos'] . ' dari ' . $stats['total_reports'], 1, 0, 'C');
    $pdf->Cell($w[1], 8, number_format($stats['avg_shutdown'], 1), 1, 1, 'C');
   
    
    $pdf->Ln(5);
    
    // Department summary
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'REKAP PER BAGIAN/LOKASI', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    if (!empty($summary)) {
        $header = ['No', 'Bagian/Lokasi', 'Laporan', 'Perangkat', 'Dimatikan', 'Aktif', 'Dokumentasi'];
        $w = [10, 45, 20, 20, 20, 20, 42];
        
        // Header
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', 'B', 10);
        for ($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Data
        $pdf->SetFont('helvetica', '', 10);
        $fill = false;
        $index = 0;
        
        foreach ($summary as $row) {
            $total_devices = $row['total_dimatikan'] + $row['total_aktif'];
            
            // Hitung jumlah foto yang benar-benar ada di sistem
            $actual_photos = 0;
            foreach ($reports as $report) {
                if ($report['bagian'] == $row['departemen'] && $report['has_photo'] && !empty($report['foto'])) {
                    $photoPath = __DIR__ . '/../../uploads/pemadaman/' . $report['foto'];
                    if (file_exists($photoPath)) {
                        $actual_photos++;
                    }
                }
            }
            
            $photo_docs = $actual_photos . '/' . $row['total_laporan'];
            
            $pdf->Cell($w[0], 8, ++$index, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[1], 8, $row['departemen'], 'LR', 0, 'L', $fill);
            $pdf->Cell($w[2], 8, number_format($row['total_laporan']), 'LR', 0, 'C', $fill);
            $pdf->Cell($w[3], 8, number_format($total_devices), 'LR', 0, 'C', $fill);
            $pdf->Cell($w[4], 8, number_format($row['total_dimatikan']), 'LR', 0, 'C', $fill);
            $pdf->Cell($w[5], 8, number_format($row['total_aktif']), 'LR', 0, 'C', $fill);
            $pdf->Cell($w[6], 8, $photo_docs, 'LR', 1, 'C', $fill);
            $fill = !$fill;
        }
        
        $pdf->Cell(array_sum($w), 0, '', 'T');
    } else {
        $pdf->Cell(0, 8, 'Tidak ada data departemen', 1, 1, 'C');
    }
    
    $pdf->Ln(5);
    
    // Detailed reports
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'DETAIL LAPORAN PEMADAMAN', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    if (!empty($reports)) {
        $header = ['No', 'Waktu', 'Bagian/Lokasi', 'Pelapor', 'Total', 'Dimatikan', 'Aktif'];
        $w = [10, 30, 45, 48, 12, 20, 12];
    
        // Header tabel
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('helvetica', 'B', 10);
        for ($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Data
        $pdf->SetFont('helvetica', '', 9);
        $fill = false;
        $index = 0;
        
        foreach ($reports as $report) {
            // Format waktu
            $waktu = $report['waktu'] instanceof DateTime ? 
                     $report['waktu']->format('d-m-Y H:i') : 
                     date('d-m-Y H:i', strtotime($report['waktu']));
            
            $total = $report['jumlah_dimatikan'] + $report['jumlah_aktif'];
            
            $pdf->Cell($w[0], 8, ++$index, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[1], 8, $waktu, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[2], 8, $report['bagian'], 'LR', 0, 'L', $fill);
            $pdf->Cell($w[3], 8, $report['nama_pelapor'] ?? $report['pelapor'], 'LR', 0, 'L', $fill);
            $pdf->Cell($w[4], 8, number_format($total), 'LR', 0, 'C', $fill);
            $pdf->Cell($w[5], 8, number_format($report['jumlah_dimatikan']), 'LR', 0, 'C', $fill);
            $pdf->Cell($w[6], 8, number_format($report['jumlah_aktif']), 'LR', 1, 'C', $fill);        
            $fill = !$fill;
            
            // Pindah ke halaman baru jika diperlukan
            if ($pdf->GetY() > 180) {
                $pdf->AddPage('P');
            }
        }
        
        // Footer table
        $pdf->Cell(array_sum($w), 0, '', 'T');
        
        // Lampiran foto di bagian akhir dokumen
        $photoIndex = 0;
        $hasPhotos = false;
        
        // First check if we have any photos
        foreach ($reports as $report) {
            if ($report['has_photo'] && !empty($report['foto'])) {
                $photoPath = __DIR__ . '/../../uploads/pemadaman/' . $report['foto'];
                if (file_exists($photoPath)) {
                    $hasPhotos = true;
                    break;
                }
            }
        }
        
        if ($hasPhotos) {
            // Check if we need a new page for photos
            if ($pdf->GetY() > 120) { // If less than 120mm space left
                $pdf->AddPage('P');
            } else {
                $pdf->Ln(10); // Just add some space if enough room
            }
            
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'LAMPIRAN DOKUMENTASI FOTO', 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Ln(5);
            
            foreach ($reports as $report) {
                if ($report['has_photo'] && !empty($report['foto'])) {
                    $photoPath = __DIR__ . '/../../uploads/pemadaman/' . $report['foto'];
                    
                    if (file_exists($photoPath)) {
                        $photoIndex++;
                        
                        // Check if we need a new page before adding this attachment
                        if ($pdf->GetY() > 120) { // If less than 120mm space left
                            $pdf->AddPage('P');
                        }
                        
                        // Format waktu untuk lampiran
                        $waktuLampiran = $report['waktu'] instanceof DateTime ? 
                                        $report['waktu']->format('d F Y H:i') : 
                                        date('d F Y H:i', strtotime($report['waktu']));
                        
                        // Create a bordered box for the attachment
                        $pdf->SetFillColor(245, 245, 245);
                        $pdf->SetDrawColor(200, 200, 200);
                        $pdf->SetLineWidth(0.3);
                        
                        // Header box
                        $pdf->SetFont('helvetica', 'B', 11);
                        $pdf->Cell(0, 8, '  Lampiran #' . $photoIndex . ' - Laporan #' . $report['id'], 'TB', 1, 'P', 1);
                        
                        // Information section
                        $pdf->SetFont('helvetica', '', 10);
                        $pdf->Cell(25, 6, 'Departemen:', 0, 0);
                        $pdf->Cell(0, 6, $report['bagian'], 0, 1);
                        $pdf->Cell(25, 6, 'Waktu:', 0, 0);
                        $pdf->Cell(0, 6, $waktuLampiran, 0, 1);
                        $pdf->Cell(25, 6, 'Pelapor:', 0, 0);
                        $pdf->Cell(0, 6, $report['nama_pelapor'] ?? $report['pelapor'], 0, 1);
                        
                        $pdf->Ln(5);
                        
                        // Compress image to a temp file
                        $tempPhotoPath = getCompressedImageTempPath($photoPath, 800, 75);
                        
                        // Add photo with consistent size
                        $maxWidth = 180; // Lebar maksimal 180mm
                        $maxHeight = 120; // Tinggi maksimal 120mm
                        
                        // Get image dimensions and calculate ratio using compressed file
                        list($imgWidth, $imgHeight) = getimagesize($tempPhotoPath);
                        $ratio = min($maxWidth/$imgWidth, $maxHeight/$imgHeight);
                        $width = $imgWidth * $ratio;
                        $height = $imgHeight * $ratio;
                        
                        // Center the image
                        $xPos = ($pdf->GetPageWidth() - $width) / 2;
                        
                        $pdf->Image($tempPhotoPath, $xPos, $pdf->GetY(), $width, $height, '', '', '', false, 300, '', false, false, 0, false, false, true);
                        $pdf->Ln($height + 5);
                        
                        // Clean up temporary file if it was compressed
                        if ($tempPhotoPath !== $photoPath) {
                            @unlink($tempPhotoPath);
                        }
                        
                        // Add description if available
                        if (!empty($report['keterangan'])) {
                            $pdf->SetFont('helvetica', 'B', 10);
                            $pdf->Cell(0, 6, 'Keterangan:', 0, 1);
                            $pdf->SetFont('helvetica', '', 10);
                            $pdf->MultiCell(0, 6, $report['keterangan']);
                        }
                        
                        // Only add separator if not the last item
                        if ($photoIndex < count($reports)) {
                            $pdf->Ln(5);
                            $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - $pdf->GetX(), $pdf->GetY());
                            $pdf->Ln(5);
                        }
                    }
                }
            }
        }
    } else {
        $pdf->Cell(0, 8, 'Tidak ada data laporan pemadaman', 1, 1, 'C');
    }

    // Add footer
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Halaman ' . $pdf->getAliasNumPage() . ' dari ' . $pdf->getAliasNbPages(), 0, 0, 'C');

    // Output PDF
    $filename = 'Laporan_Pemadaman_' . str_replace(' ', '_', $filter_info) . '_' . date('YmdHis') . '.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    if (!headers_sent()) {
        header('Content-Type: text/plain');
    }
    die("Error generating PDF: " . $e->getMessage());
}
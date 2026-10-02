<?php
// generate_pdf.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check permissions
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 56); // 53 is MenuId for asset
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

try {
    // Create PDF instance with landscape orientation
    $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
    
    // Set document info
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('GG System');
    $pdf->SetTitle('Laporan Data Asset');
    $pdf->SetSubject('Laporan Asset');
    
    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Generate judul berdasarkan filter
    $judul = 'LAPORAN DATA ASSET';
    if (!empty($_GET['kategori_name'])) {
        $judul = 'LAPORAN DATA ASSET - ' . strtoupper($_GET['kategori_name']);
    }
    
    // Company Logo and Header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, $judul, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'PT. SURYA USAHA MANDIRI', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Info filter
    $filter_info = "Filter: ";
    
    if (!empty($_GET['kategori_name'])) {
        $filter_info .= "Kategori: " . htmlspecialchars($_GET['kategori_name']) . ", ";
    }
    if (!empty($_GET['lokasi_name'])) {
        $filter_info .= "Lokasi: " . htmlspecialchars($_GET['lokasi_name']) . ", ";
    }
    if (!empty($_GET['status_name'])) {
        $filter_info .= "Status: " . htmlspecialchars($_GET['status_name']) . ", ";
    }
    
    if ($filter_info == "Filter: ") {
        $filter_info = "Semua Data";
    } else {
        $filter_info = rtrim($filter_info, ', ');
    }
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 6, 'Filter Data:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, $filter_info, 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 6, 'Tanggal Cetak:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, date('d F Y H:i:s'), 0, 1);
    $pdf->Ln(10);
    
    // Get filtered data
    $stmt = getFilteredAssetStatement($conn, $_GET);
    
    // Hitung total asset
    $totalAssets = 0;
    $assetData = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $assetData[] = $row;
        $totalAssets++;
    }
    
// Professional Summary Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 8, 'RINGKASAN ASSET', 0, 1, 'C', true);
$pdf->Ln(5);

// Calculate summary by category
$categorySummary = [];
foreach ($assetData as $row) {
    $category = $row['nama_kategori'] ?? 'Unknown';
    if (!isset($categorySummary[$category])) {
        $categorySummary[$category] = 0;
    }
    $categorySummary[$category]++;
}

// Summary Box - Total Assets
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Total Asset:', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, number_format($totalAssets) . ' Asset', 1, 1);
$pdf->Ln(5);

// Category Summary Table Header
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(120, 8, 'Kategori', 1, 0, 'C', true);
$pdf->Cell(0, 8, 'Jumlah', 1, 1, 'C', true);

// Category Summary Data
$pdf->SetFont('helvetica', '', 9);
$fill = false;
foreach ($categorySummary as $category => $count) {
    $pdf->Cell(120, 7, $category, 'LR', 0, 'L', $fill);
    $pdf->Cell(0, 7, number_format($count) . ' Asset', 'LR', 1, 'R', $fill);
    $fill = !$fill;
}
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(10);
    

    
    // Header tabel utama
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'DETAIL ASSET', 0, 1, 'C', true);
    $pdf->Ln(5);
    
    // Header tabel
    $header = ['No', 'Kode Asset', 'Kategori', 'Merk', 'Tipe', 'Lokasi', 'Status', 'Pegawai', 'Keterangan'];
    $w = [10, 25, 25, 25, 30, 25, 20, 45, 62]; // Lebar kolom disesuaikan
    
    // Set header table
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('helvetica', '', 8);
    $fill = false;
    
    foreach ($assetData as $index => $row) {
        // Check if we need a new page
        if ($pdf->GetY() > 170) { // Karena landscape, batas bawah lebih kecil
            $pdf->AddPage('L');
            // Redraw header
            $pdf->SetFont('helvetica', 'B', 9);
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
        }
        
        $pdf->Cell($w[0], 7, $index + 1, 'LR', 0, 'C', $fill);
        $pdf->Cell($w[1], 7, $row['kode_asset_seq'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 7, $row['nama_kategori'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[3], 7, $row['nama_merk'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[4], 7, $row['nama_tipe'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[5], 7, $row['nama_lokasi'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[6], 7, $row['nama_status'] ?? '-', 'LR', 0, 'C', $fill); 
        $pdf->Cell($w[7], 7, $row['nama_lengkap'] ?? '-', 'LR', 0, 'L', $fill);
        
        // Deskripsi dengan MultiCell untuk text wrapping
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($w[8], 7, substr($row['keterangan'] ?? '-', 0, 100), 'LR', 'L', $fill);
        $pdf->SetXY($x + $w[8], $y);
        
        $pdf->Ln();
        $fill = !$fill;
    }
    
    // Closing line
    $pdf->Cell(array_sum($w), 0, '', 'T');
    
    // Output PDF
    $pdf->Output('Laporan Asset IT_' . date('dmY') . '.pdf', 'D');
    
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}

/**
 * Get filtered asset statement for PDF export
 */
function getFilteredAssetStatement($conn, $filters) {
    $sql = "SELECT a.id_asset, a.kode_asset_seq, a.serial_number, a.tanggal_pembelian, 
                   kat.nama_kategori, m.nama_merk, t.nama_tipe,
                   l.nama_lokasi, a.keterangan, s.nama_status, e.nama_lengkap
            FROM dbo.m_asset a
            LEFT JOIN dbo.m_kategori kat ON a.id_kategori = kat.id_kategori
            LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
            LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
            LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
            LEFT JOIN dbo.m_status s ON a.id_status = s.id_status
            LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
            WHERE 1=1";

    $params = [];
    
    // Add filters
    if (!empty($filters['kategori'])) {
        $sql .= " AND a.id_kategori = ?";
        $params[] = $filters['kategori'];
    }
    
    if (!empty($filters['lokasi'])) {
        $sql .= " AND a.id_lokasi = ?";
        $params[] = $filters['lokasi'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND a.id_status = ?";
        $params[] = $filters['status'];
    }
    
    $sql .= " ORDER BY a.kode_asset_seq";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new Exception("Database query failed: " . print_r(sqlsrv_errors(), true));
    }
    
    return $stmt;
}

/**
 * Check user permissions
 */
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $permissions;
}
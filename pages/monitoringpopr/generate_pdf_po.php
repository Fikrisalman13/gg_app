<?php
// generate_pdf.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../koneksi3.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Check permissions
$groupId = $_SESSION['GroupId'];
$menuId = 34; // MenuId for PO Monitoring

$sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);

if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

try {
    // Get filter parameters
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d H:i:s', strtotime('-24 hours'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d H:i:s');

    // Create PDF instance with portrait orientation
    $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
    
    // Set document info
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('GG System');
    $pdf->SetTitle('Monitoring Pembuatan PO');
    $pdf->SetSubject('Laporan Monitoring PO');
    
    // Set margins
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Company Logo and Header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'MONITORING PEMBUATAN PURCHASE ORDER (PO)', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Perusahaan: PT. SURYA USAHA MANDIRI', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Info filter
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 6, 'Periode Monitoring:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, date('d F Y H:i', strtotime($startDate)) . ' - ' . date('d F Y H:i', strtotime($endDate)), 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 6, 'Tanggal Cetak:', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, date('d F Y H:i:s'), 0, 1);
    $pdf->Ln(10);
    
    // Get PO Monitoring Data
    $monitoringQuery = "SELECT
        a.ponmbr AS po_number,
        a.podate AS po_date,
        a.povendorname,
        a.upddate AS created_date,
        smemployee.empname AS creator_name 
    FROM
        prpohd AS a
        LEFT JOIN smemployee ON a.poinitiatorid = smemployee.empid
    WHERE
        a.upddate BETWEEN ? AND ?
    ORDER BY
        a.ponmbr ASC";

    $stmt = $conn3->prepare($monitoringQuery);
    $stmt->execute([$startDate, $endDate]);
    $monitoringData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    $creatorStats = [];
    $totalPOs = 0;
    foreach ($monitoringData as $row) {
        $creator = $row['creator_name'] ?: 'Unknown';
        if (!isset($creatorStats[$creator])) {
            $creatorStats[$creator] = 0;
        }
        $creatorStats[$creator]++;
        $totalPOs++;
    }

    // Professional Summary Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'RINGKASAN PEMBUATAN PO', 0, 1, 'C', true);
    $pdf->Ln(5);
    
    // Summary Box
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 8, 'Total PO Dibuat:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, $totalPOs . ' Purchase Order', 1, 1);
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 8, 'Rata-rata per Pembuat:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    $avg = count($creatorStats) > 0 ? round($totalPOs / count($creatorStats), 1) : 0;
    $pdf->Cell(0, 8, $avg . ' PO per orang', 1, 1);
    $pdf->Ln(8);
    
    // Detailed Creator Stats
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Detail per Pembuat:', 0, 1);
    
    // Table header for creator stats
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(120, 8, 'Nama Pembuat', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Jumlah PO', 1, 0, 'C', true);
    $pdf->Cell(0, 8, 'Persentase', 1, 1, 'C', true);
    
    // Table content for creator stats
    $pdf->SetFont('helvetica', '', 10);
    foreach ($creatorStats as $creator => $count) {
        $percentage = $totalPOs > 0 ? round(($count / $totalPOs) * 100, 1) : 0;
        
        $pdf->Cell(120, 8, $creator, 1);
        $pdf->Cell(30, 8, $count . ' PO', 1, 0, 'C');
        $pdf->Cell(0, 8, $percentage . '%', 1, 1, 'C');
    }
    $pdf->Ln(15);

    // Header tabel utama
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'DETAIL PURCHASE ORDER', 0, 1, 'C', true);
    $pdf->Ln(5);
    
    // Header tabel
    $header = ['No', 'No PO', 'Tgl PO', 'Vendor', 'Tgl Dibuat', 'Pembuat'];
    $w = [10, 30, 25, 120, 35, 45];
    
    // Set header table
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    
    foreach ($monitoringData as $index => $row) {
        // Check if we need a new page
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            // Redraw header
            $pdf->SetFont('helvetica', 'B', 9);
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 9);
        }
        
        $pdf->Cell($w[0], 7, $index + 1, 'LR', 0, 'C', $fill);
        $pdf->Cell($w[1], 7, $row['po_number'], 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 7, date('d/m/Y', strtotime($row['po_date'])), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[3], 7, $row['povendorname'], 'LR', 0, 'L', $fill);
        $pdf->Cell($w[4], 7, date('d/m/Y H:i', strtotime($row['created_date'])), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[5], 7, $row['creator_name'], 'LR', 0, 'L', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
    
    // Closing line
    $pdf->Cell(array_sum($w), 0, '', 'T');
    
    // Output PDF
    $pdf->Output('Monitoring Pembuatan PO_' . date('d-m-Y') . '.pdf', 'D');
    
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
} finally {
    // Close database connections
    if (isset($stmt)) $stmt = null;
    if (isset($conn3)) $conn3 = null;
    if (isset($conn)) sqlsrv_close($conn);
}
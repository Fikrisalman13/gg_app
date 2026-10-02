<?php
// generate_pdf_statuspo.php
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
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d 00:00:00', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d 23:59:59');

    // Create PDF instance with portrait orientation
    $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
    
    // Set document info
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('GG System');
    $pdf->SetTitle('Monitoring Status PO');
    $pdf->SetSubject('Laporan Status PO');
    
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
    $pdf->Cell(0, 8, 'MONITORING STATUS PURCHASE ORDER (PO)', 0, 1, 'C');
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
    
    // Query untuk mendapatkan data PO dengan filter
    $query = "SELECT
                prpohd.ponmbr,
                prpohd.podate,
                prpohd.povendorname,
                prpohd.podesc,
                prpohd.fgstatus
              FROM
                prpohd
              WHERE
                prpohd.podate BETWEEN ? AND ?
                AND prpohd.fgstatus IN ('X', 'O', 'C', 'V', 'U')
                ORDER BY
                prpohd.ponmbr ASC";
    
    $stmt = $conn3->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $poData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung summary berdasarkan status
    $statusSummary = [
        'O' => ['count' => 0, 'label' => 'Open (Belum Disetujui)'],
        'V' => ['count' => 0, 'label' => 'Approve (Sudah Disetujui)'],
        'C' => ['count' => 0, 'label' => 'Cancel (Batal)'],
        'U' => ['count' => 0, 'label' => 'Outstanding (Dalam Proses)'],
        'X' => ['count' => 0, 'label' => 'Close (Selesai)']
    ];
    
    foreach ($poData as $po) {
        if (isset($statusSummary[$po['fgstatus']])) {
            $statusSummary[$po['fgstatus']]['count']++;
        }
    }
    
    $totalPOs = count($poData);
    
    // Professional Summary Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'RINGKASAN STATUS PO', 0, 1, 'C', true);
    $pdf->Ln(5);
    
    // Summary Box
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 8, 'Total PO:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, $totalPOs . ' Purchase Order', 1, 1);
    $pdf->Ln(8);
    
    // Status Summary Table
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Detail per Status:', 0, 1);
    
    // Table header for status stats
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(120, 8, 'Status PO', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Jumlah', 1, 0, 'C', true);
    $pdf->Cell(0, 8, 'Persentase', 1, 1, 'C', true);
    
    // Table content for status stats
    $pdf->SetFont('helvetica', '', 10);
    foreach ($statusSummary as $status => $info) {
        if ($info['count'] > 0) {
            $percentage = $totalPOs > 0 ? round(($info['count'] / $totalPOs) * 100, 1) : 0;
            
            $pdf->Cell(120, 8, $info['label'], 1);
            $pdf->Cell(30, 8, $info['count'] . ' PO', 1, 0, 'C');
            $pdf->Cell(0, 8, $percentage . '%', 1, 1, 'C');
        }
    }
    $pdf->Ln(15);
    
    // Header tabel utama
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, 'DETAIL PURCHASE ORDER', 0, 1, 'C', true);
    $pdf->Ln(5);
    
    // Header tabel (tanpa Cost Center, lebar kolom disesuaikan)
    $header = ['No', 'No PO', 'Tgl PO', 'Vendor', 'Deskripsi', 'Status'];
    $w = [10, 30, 20, 70, 110, 25]; // Lebar kolom disesuaikan
    
    // Set header table
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('helvetica', '', 8); // Ukuran font lebih kecil untuk deskripsi panjang
    $fill = false;
    
    foreach ($poData as $index => $po) {
        // Check if we need a new page
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            // Redraw header
            $pdf->SetFont('helvetica', 'B', 9);
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
        }
        
        // Determine status text and color
        switch ($po['fgstatus']) {
            case 'O':
                $statusText = 'Open';
                $statusColor = [255, 193, 7]; // Yellow
                break;
            case 'V':
                $statusText = 'Approve';
                $statusColor = [40, 167, 69]; // Green
                break;
            case 'C':
                $statusText = 'Cancel';
                $statusColor = [220, 53, 69]; // Red
                break;
            case 'U':
                $statusText = 'Outstanding';
                $statusColor = [23, 162, 184]; // Cyan
                break;
            case 'X':
                $statusText = 'Close';
                $statusColor = [108, 117, 125]; // Gray
                break;
            default:
                $statusText = 'Unknown';
                $statusColor = [108, 117, 125]; // Gray
        }
        
        $pdf->Cell($w[0], 7, $index + 1, 'LR', 0, 'C', $fill);
        $pdf->Cell($w[1], 7, $po['ponmbr'], 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 7, date('d/m/Y', strtotime($po['podate'])), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[3], 7, $po['povendorname'], 'LR', 0, 'L', $fill);
        
        // Deskripsi dengan MultiCell untuk text wrapping
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($w[4], 7, substr($po['podesc'], 0, 100), 'LR', 'L', $fill);
        $pdf->SetXY($x + $w[4], $y);
        
        // Status cell with color
        $pdf->SetFillColor($statusColor[0], $statusColor[1], $statusColor[2]);
        $pdf->SetTextColor(255, 255, 255); // White text
        $pdf->Cell($w[5], 7, $statusText, 'LR', 0, 'C', true);
        
        // Reset colors
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln();
        
        $fill = !$fill;
    }
    
    // Closing line
    $pdf->Cell(array_sum($w), 0, '', 'T');
    
    // Output PDF
    $pdf->Output('Monitoring Status PO_' . date('d-m-Y') . '.pdf', 'D');
    
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
} finally {
    // Close database connections
    if (isset($stmt)) $stmt = null;
    if (isset($conn3)) $conn3 = null;
    if (isset($conn)) sqlsrv_close($conn);
}
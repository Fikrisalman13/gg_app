<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
include '../../koneksi.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// Ambil filter dari query string
$padder_id  = $_GET['padder_id']  ?? '';
$status     = $_GET['status']     ?? '';
$user       = $_GET['user']       ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';
$format     = $_GET['format']     ?? 'pdf';

// Buat kondisi WHERE
$where = " WHERE 1=1 ";
$params = [];

if ($padder_id !== '') {
    $where .= " AND l.padder_id = ? ";
    $params[] = $padder_id;
}
if ($status !== '') {
    $where .= " AND l.status = ? ";
    $params[] = $status;
}
if ($user !== '') {
    $where .= " AND l.changed_by LIKE ? ";
    $params[] = "%$user%";
}
if ($start_date !== '') {
    $where .= " AND CAST(l.changed_at AS DATE) >= ? ";
    $params[] = $start_date;
}
if ($end_date !== '') {
    $where .= " AND CAST(l.changed_at AS DATE) <= ? ";
    $params[] = $end_date;
}

// Query data
$sql = "SELECT 
            l.id,
            l.padder_id,
            p.padder_name,
            l.status,
            l.changed_at,
            FORMAT(l.changed_at, 'dd/MM/yyyy HH:mm') as changed_at_formatted,
            l.changed_by,
            l.remarks,
            p.status as current_status
        FROM dbo.pad_status_log l
        INNER JOIN dbo.pad_m_padder p ON l.padder_id = p.padder_id
        $where
        ORDER BY l.changed_at DESC";

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
}

// Ambil data ke array
$dataRows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dataRows[] = $row;
}

// Tutup statement
sqlsrv_free_stmt($stmt);

if ($format === 'excel') {
    exportExcel($dataRows, $padder_id, $status, $user, $start_date, $end_date);
} else {
    exportPDF($dataRows, $padder_id, $status, $user, $start_date, $end_date);
}

// === FUNGSI EXPORT PDF ===
function exportPDF($dataRows, $padder_id, $status, $user, $start_date, $end_date) {
    // Create new PDF document
    $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Padder Monitoring System');
    $pdf->SetAuthor('PT. Surya Usaha Mandiri');
    $pdf->SetTitle('Laporan Log Status Padder');
    $pdf->SetSubject('Log Status Padder');
    $pdf->SetKeywords('Padder, Log, Status');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'PT. SURYA USAHA MANDIRI', 'LAPORAN LOG STATUS PADDER');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(15);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 20);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 9);
    
    // === HEADER CONTENT ===
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(30, 30, 120);
    $pdf->Cell(0, 10, 'PT. SURYA USAHA MANDIRI', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'LAPORAN LOG STATUS PADDER', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Informasi filter
    $pdf->SetFont('helvetica', '', 10);
    $filterInfo = [];
    
    if ($padder_id !== '') {
        $filterInfo[] = "Padder: " . $padder_id;
    }
    if ($status !== '') {
        $statusLabels = [
            'DIBELI' => 'Dibeli',
            'READY' => 'Ready', 
            'IN_USE' => 'In Use',
            'MAINTENANCE' => 'Maintenance',
            'REPAIR_VENDOR' => 'Repair Vendor',
            'REPAIRED' => 'Repaired',
            'SCRAP' => 'Scrap'
        ];
        $filterInfo[] = "Status: " . ($statusLabels[$status] ?? $status);
    }
    if ($user !== '') {
        $filterInfo[] = "User: " . $user;
    }
    if ($start_date !== '') {
        $filterInfo[] = "Dari: " . date('d/m/Y', strtotime($start_date));
    }
    if ($end_date !== '') {
        $filterInfo[] = "Sampai: " . date('d/m/Y', strtotime($end_date));
    }

    if (!empty($filterInfo)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 6, 'Filter Terpasang:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 6, implode("; ", $filterInfo), 0, 'L');
        $pdf->Ln(2);
    } else {
        $pdf->Cell(0, 6, "Filter: Semua Data", 0, 1, 'L');
    }

    // Tanggal cetak
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . $_SESSION['UserName'], 0, 1, 'L');
    $pdf->Ln(3);

    // === TABLE HEADER ===
    $pdf->SetFillColor(60, 100, 150);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetLineWidth(0.3);
    
    // Header columns
    $headers = array('NO', 'TANGGAL & WAKTU', 'PADDER ID', 'NAMA PADDER', 'STATUS', 'USER', 'KETERANGAN');
    $widths = array(10, 25, 25, 35, 25, 25, 95);
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Reset colors and font
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);
    
    // === TABLE CONTENT ===
    $fill = false;
    $no = 1;
    
    foreach ($dataRows as $row) {
        // Check for page break
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            
            // Print header again
            $pdf->SetFillColor(60, 100, 150);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 8);
            
            for ($i = 0; $i < count($headers); $i++) {
                $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8);
            
            $fill = false;
        }
        
        // Alternate row color
        if ($fill) {
            $pdf->SetFillColor(240, 245, 255);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Row data
        $rowData = array(
            $no++,
            $row['changed_at_formatted'],
            $row['padder_id'],
            $row['padder_name'],
            $row['status'],
            $row['changed_by'],
            $row['remarks'] ?? ''
        );
        
        // Calculate row height
        $rowHeight = 6;
        for ($i = 0; $i < count($rowData); $i++) {
            $textHeight = $pdf->getStringHeight($widths[$i], $rowData[$i], true, true, '', 1);
            if ($textHeight > $rowHeight) {
                $rowHeight = $textHeight;
            }
        }
        
        // Print row
        for ($i = 0; $i < count($rowData); $i++) {
            $align = ($i == 0 || $i == 1 || $i == 2 || $i == 4 || $i == 5) ? 'C' : 'L';
            $pdf->MultiCell($widths[$i], $rowHeight, $rowData[$i], 1, $align, $fill, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        }
        $pdf->Ln();
        
        $fill = !$fill;
    }
    
    // Total data
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, 'Total Data: ' . count($dataRows), 0, 1, 'R');
    
    // Footer
    $pdf->SetY(-20);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Dokumen ini dicetak secara otomatis dari Sistem Monitoring Padder', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Halaman ' . $pdf->getAliasNumPage() . ' dari ' . $pdf->getAliasNbPages(), 0, 0, 'C');
    
    // Output PDF
    $pdf->Output('Laporan_Log_Status_Padder_' . date('Ymd_His') . '.pdf', 'I');
}

// === FUNGSI EXPORT EXCEL ===
function exportExcel($dataRows, $padder_id, $status, $user, $start_date, $end_date) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Log_Status_Padder_" . date("Ymd_His") . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10pt; }
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #4CAF50; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; text-align: center; }
            td { padding: 6px; border: 1px solid #ddd; }
            .header { text-align: center; margin-bottom: 20px; }
            .company { font-size: 16pt; font-weight: bold; color: #1e3a8a; }
            .title { font-size: 14pt; font-weight: bold; margin: 10px 0; }
            .filter-info { margin: 10px 0; padding: 8px; background-color: #f0f0f0; border-left: 4px solid #4CAF50; }
            .footer { margin-top: 20px; font-style: italic; color: #666; text-align: center; }
            
            /* Warna status */
            .status-DIBELI { background-color: #add8e6; }
            .status-READY { background-color: #90ee90; }
            .status-IN_USE { background-color: #87cefa; }
            .status-MAINTENANCE { background-color: #ffff99; }
            .status-REPAIR_VENDOR { background-color: #ffdab9; }
            .status-REPAIRED { background-color: #98fb98; }
            .status-SCRAP { background-color: #ffb6c1; }
        </style>
    </head>
    <body>";

    // Header
    echo "<div class='header'>
            <div class='company'>PT. SURYA USAHA MANDIRI</div>
            <div class='title'>LAPORAN LOG STATUS PADDER</div>
          </div>";

    // Informasi Filter
    $filterInfo = [];
    if ($padder_id !== '') $filterInfo[] = "Padder: " . $padder_id;
    if ($status !== '') {
        $statusLabels = [
            'DIBELI' => 'Dibeli', 'READY' => 'Ready', 'IN_USE' => 'In Use',
            'MAINTENANCE' => 'Maintenance', 'REPAIR_VENDOR' => 'Repair Vendor',
            'REPAIRED' => 'Repaired', 'SCRAP' => 'Scrap'
        ];
        $filterInfo[] = "Status: " . ($statusLabels[$status] ?? $status);
    }
    if ($user !== '') $filterInfo[] = "User: " . $user;
    if ($start_date !== '') $filterInfo[] = "Dari: " . date('d/m/Y', strtotime($start_date));
    if ($end_date !== '') $filterInfo[] = "Sampai: " . date('d/m/Y', strtotime($end_date));

    if (!empty($filterInfo)) {
        echo "<div class='filter-info'><strong>Filter Terpasang:</strong> " . implode("; ", $filterInfo) . "</div>";
    } else {
        echo "<div class='filter-info'><strong>Filter:</strong> Semua Data</div>";
    }

    echo "<div style='margin-bottom: 10px;'><strong>Dicetak pada:</strong> " . date('d/m/Y H:i:s') . " oleh: " . $_SESSION['UserName'] . "</div>";

    // Tabel Data
    echo "<table border='1'>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal & Waktu</th>
                    <th>Padder ID</th>
                    <th>Nama Padder</th>
                    <th>Status</th>
                    <th>User</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>";

    $no = 1;
    foreach ($dataRows as $row) {
        echo "<tr>";
        echo "<td style='text-align: center;'>" . $no++ . "</td>";
        echo "<td style='text-align: center;'>" . htmlspecialchars($row['changed_at_formatted']) . "</td>";
        echo "<td style='text-align: center;'>" . htmlspecialchars($row['padder_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['padder_name']) . "</td>";
        echo "<td style='text-align: center;' class='status-" . htmlspecialchars($row['status']) . "'>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td style='text-align: center;'>" . htmlspecialchars($row['changed_by']) . "</td>";
        echo "<td>" . htmlspecialchars($row['remarks'] ?? '') . "</td>";
        echo "</tr>";
    }

    echo "</tbody>
          </table>";

    // Footer
    echo "<div class='footer'>
            <div>Total Data: " . count($dataRows) . "</div>
            <div>Dokumen ini dicetak secara otomatis dari Sistem Monitoring Padder</div>
          </div>";

    echo "</body></html>";
}

// Tutup koneksi
sqlsrv_close($conn);
?>
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
$export_type = $_GET['export_type'] ?? 'current'; // current atau all

// Tentukan format export
$format = $_GET['format'] ?? 'pdf'; // pdf atau excel

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

// Jika format Excel
if ($format === 'excel') {
    exportExcel($dataRows, $padder_id, $status, $user, $start_date, $end_date);
} else {
    // Default PDF
    exportPDF($dataRows, $padder_id, $status, $user, $start_date, $end_date);
}

// === FUNGSI EXPORT PDF ===
function exportPDF($dataRows, $padder_id, $status, $user, $start_date, $end_date) {
    global $conn;
    
    // === KONFIGURASI PDF ===
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Padder Monitoring System');
    $pdf->SetAuthor('PT. Surya Usaha Mandiri');
    $pdf->SetTitle('Laporan Log Status Padder');
    $pdf->SetMargins(15, 25, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 20);

    // Header dan Footer
    $pdf->setHeaderFont(Array('helvetica', '', 9));
    $pdf->setFooterFont(Array('helvetica', '', 9));

    // Menambahkan halaman
    $pdf->AddPage();

    // Set font default
    $pdf->SetFont('helvetica', '', 9);

    // === HEADER ===
    // Nama Perusahaan
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(30, 30, 120);
    $pdf->Cell(0, 15, 'PT. SURYA USAHA MANDIRI', 0, 1, 'C');

    // Judul Laporan
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 15, 'LAPORAN LOG STATUS PADDER', 0, 1, 'C');
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
        $pdf->Cell(40, 7, 'Filter Terpasang:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 7, implode("; ", $filterInfo), 0, 'L', false);
        $pdf->Ln(2);
    } else {
        $pdf->Cell(0, 7, "Filter: Semua Data", 0, 1, 'L');
    }

    // Tanggal cetak
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s') . ' oleh: ' . $_SESSION['UserName'], 0, 1, 'L');
    $pdf->Ln(3);

    // === LEBAR KOLOM ===
    $maxWidths = [
        'no'        => 12,
        'timestamp' => 25,
        'padder_id' => 25,
        'padder_name' => 35,
        'status'    => 25,
        'user'      => 25,
        'remarks'   => 80
    ];

    // === FUNGSI CETAK TABEL ===
    function printTableHeader($pdf, $maxWidths) {
        $pdf->SetFillColor(60, 100, 150);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(80, 80, 80);

        $headers = [
            'no'        => 'NO',
            'timestamp' => 'TANGGAL & WAKTU',
            'padder_id' => 'PADDER ID',
            'padder_name' => 'NAMA PADDER',
            'status'    => 'STATUS',
            'user'      => 'USER',
            'remarks'   => 'KETERANGAN'
        ];

        foreach ($headers as $key => $label) {
            $pdf->MultiCell($maxWidths[$key], 8, $label, 1, 'C', true, 0, '', '', true);
        }
        $pdf->Ln();

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
    }

    function printTableRow($pdf, $row, $no, $maxWidths, $fill) {
        if ($fill) {
            $pdf->SetFillColor(240, 245, 255);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        // Warna status
        $statusColors = [
            'DIBELI' => [173, 216, 230],       // Light blue
            'READY' => [144, 238, 144],        // Light green
            'IN_USE' => [135, 206, 250],       // Light sky blue
            'MAINTENANCE' => [255, 255, 153],  // Light yellow
            'REPAIR_VENDOR' => [255, 218, 185], // Light peach
            'REPAIRED' => [152, 251, 152],     // Light green
            'SCRAP' => [255, 182, 193]         // Light red
        ];

        $cells = [
            'no'        => $no,
            'timestamp' => $row['changed_at_formatted'],
            'padder_id' => $row['padder_id'],
            'padder_name' => $row['padder_name'],
            'status'    => $row['status'],
            'user'      => $row['changed_by'],
            'remarks'   => $row['remarks'] ?? ''
        ];

        $rowHeights = [];
        foreach ($cells as $key => $val) {
            $rowHeights[] = $pdf->getStringHeight($maxWidths[$key], $val, false, true, '', 1);
        }
        $rowHeight = max($rowHeights);

        // Kolom NO
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 245 : 255, $fill ? 255 : 255);
        $pdf->MultiCell($maxWidths['no'], $rowHeight, $cells['no'], 1, 'C', true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Kolom TIMESTAMP
        $pdf->MultiCell($maxWidths['timestamp'], $rowHeight, $cells['timestamp'], 1, 'C', true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Kolom PADDER ID
        $pdf->MultiCell($maxWidths['padder_id'], $rowHeight, $cells['padder_id'], 1, 'C', true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Kolom PADDER NAME
        $pdf->MultiCell($maxWidths['padder_name'], $rowHeight, $cells['padder_name'], 1, 'L', true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Kolom STATUS dengan warna
        $statusColor = $statusColors[$cells['status']] ?? [255, 255, 255];
        $pdf->SetFillColor($statusColor[0], $statusColor[1], $statusColor[2]);
        $pdf->MultiCell($maxWidths['status'], $rowHeight, $cells['status'], 1, 'C', true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Kembalikan warna fill
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 245 : 255, $fill ? 255 : 255);
        
        // Kolom USER
        $pdf->MultiCell($maxWidths['user'], $rowHeight, $cells['user'], 1, 'C', true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Kolom REMARKS
        $pdf->MultiCell($maxWidths['remarks'], $rowHeight, $cells['remarks'], 1, 'L', true, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        $pdf->Ln();

        return !$fill;
    }

    // Cetak header tabel pertama
    printTableHeader($pdf, $maxWidths);

    // Isi tabel
    $fill = false;
    $no = 1;
    foreach ($dataRows as $row) {
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            printTableHeader($pdf, $maxWidths);
            $fill = false;
        }
        $fill = printTableRow($pdf, $row, $no++, $maxWidths, $fill);
    }

    // Jumlah data
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
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
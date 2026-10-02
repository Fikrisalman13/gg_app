<?php
// Simple, self-contained PDF exporter for IPAL
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');
$shiftFilter = isset($_REQUEST['shift']) ? (string)$_REQUEST['shift'] : '';

// Minimal SQL to fetch rows (Tanggal + Shift) to keep PDF meaningful
$sql = "SELECT COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal, ptco.Tanggal) AS Tanggal,
COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift, ptco.Shift) AS Shift
FROM dbo.PH_Air ph
FULL OUTER JOIN dbo.COD_air cod ON ph.Tanggal = cod.Tanggal AND ph.Shift = cod.Shift
FULL OUTER JOIN dbo.TSS_Air tss ON COALESCE(ph.Tanggal, cod.Tanggal) = tss.Tanggal AND COALESCE(ph.Shift, cod.Shift) = tss.Shift
FULL OUTER JOIN dbo.MLSS_Air mlss ON COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal) = mlss.Tanggal AND COALESCE(ph.Shift, cod.Shift, tss.Shift) = mlss.Shift
FULL OUTER JOIN dbo.PTCO_Air ptco ON COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal) = ptco.Tanggal AND COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift) = ptco.Shift
WHERE COALESCE(ph.Tanggal, cod.Tanggal, tss.Tanggal, mlss.Tanggal, ptco.Tanggal) BETWEEN ? AND ?";
$params = [$start, $end];
if ($shiftFilter !== '') { $sql .= "\nAND COALESCE(ph.Shift, cod.Shift, tss.Shift, mlss.Shift, ptco.Shift) = ?"; $params[] = $shiftFilter; }

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) { if (ob_get_length()) ob_end_clean(); echo "SQL error: " . print_r(sqlsrv_errors(), true); exit; }

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $r;

// Create PDF
$pdf = new FPDF('L','mm','A4');
$pdf->SetMargins(8,8,8);
$pdf->AddPage();
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,7,'HASIL CHECK AIR LIMBAH',0,1,'C');
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,'PERIODE '.date('d F Y',strtotime($start)).' s/d '.date('d F Y',strtotime($end)),0,1,'C');
$pdf->Ln(6);

if (empty($rows)) {
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,'Tidak ada data untuk periode ini.',0,1,'C');
} else {
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(60,7,'Tanggal',1,0,'C');
    $pdf->Cell(30,7,'Shift',1,0,'C');
    $pdf->Ln();
    $pdf->SetFont('Arial','',9);
    foreach ($rows as $row) {
        $tgl = ($row['Tanggal'] instanceof DateTime) ? $row['Tanggal']->format('Y-m-d') : ($row['Tanggal'] ?? '-');
        $pdf->Cell(60,6,$tgl,1,0,'C');
        $pdf->Cell(30,6,$row['Shift'] ?? '-',1,0,'C');
        $pdf->Ln();
    }
}

if (ob_get_length()) ob_end_clean();
$filename = 'Laporan_IPAL_'.date('Ymd_His').'.pdf';
$content = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
echo $content;

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
exit;


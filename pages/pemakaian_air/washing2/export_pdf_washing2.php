<?php
session_start();
ob_start();
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../libs/fpdf.php';

$start = $_REQUEST['start_date'] ?? date('Y-m-01');
$end = $_REQUEST['end_date'] ?? date('Y-m-d');

$sql = "
SELECT d.tanggal,
       w.WattAwal, w.WattAkhir, w.OperasionalMesin AS WattOperasional,
       s.SteamAwal, s.SteamAkhir, s.SteamPemakaian,
       m.WaterAwal, m.WaterAkhir, m.TotalPemakaian, m.OperasionalMesin AS WaterOperasional, m.PemakaianRataPerJam,
       m.Keterangan
FROM (
  SELECT Tanggal AS tanggal FROM dbo.washing2_watt_meter
  UNION
  SELECT Tanggal AS tanggal FROM dbo.washing2_steam_meter
  UNION
  SELECT Tanggal AS tanggal FROM dbo.washing2_water_meter
) d
LEFT JOIN dbo.washing2_watt_meter w ON w.Tanggal = d.tanggal
LEFT JOIN dbo.washing2_steam_meter s ON s.Tanggal = d.tanggal
LEFT JOIN dbo.washing2_water_meter m ON m.Tanggal = d.tanggal
WHERE d.tanggal BETWEEN ? AND ?
ORDER BY d.tanggal ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) { while (ob_get_level() > 0) ob_end_clean(); echo "SQL error: " . print_r(sqlsrv_errors(), true); exit; }
$rows=[]; while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){ if($r['SteamPemakaian']===null && $r['SteamAwal']!==null && $r['SteamAkhir']!==null){$r['SteamPemakaian']=round((float)$r['SteamAkhir']-(float)$r['SteamAwal'],2);} if($r['TotalPemakaian']===null && $r['WaterAwal']!==null && $r['WaterAkhir']!==null){$r['TotalPemakaian']=round((float)$r['WaterAkhir']-(float)$r['WaterAwal'],2);} if($r['PemakaianRataPerJam']===null && $r['TotalPemakaian']!==null && $r['WaterOperasional']!==null && (float)$r['WaterOperasional']!=0){$r['PemakaianRataPerJam']=round((float)$r['TotalPemakaian']/(float)$r['WaterOperasional'],2);} $rows[]=$r; }
if($stmt) sqlsrv_free_stmt($stmt);

$fmt = function($v){ if($v===null||$v==='') return ''; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; };
$day = function($v){ if($v instanceof DateTime) return $v->format('j'); return date('j',strtotime((string)$v)); };

$sumW=0;$sumS=0;$sumT=0;$sumWO=0;$cW=0;$cS=0;$cT=0;$cWO=0;
foreach($rows as $r){ if(is_numeric($r['WattOperasional'])){$sumW+=(float)$r['WattOperasional'];$cW++;} if(is_numeric($r['SteamPemakaian'])){$sumS+=(float)$r['SteamPemakaian'];$cS++;} if(is_numeric($r['TotalPemakaian'])){$sumT+=(float)$r['TotalPemakaian'];$cT++;} if(is_numeric($r['WaterOperasional'])){$sumWO+=(float)$r['WaterOperasional'];$cWO++;} }
$avgW=$cW?$sumW/$cW:null; $avgS=$cS?$sumS/$cS:null; $avgT=$cT?$sumT/$cT:null; $avgWO=$cWO?$sumWO/$cWO:null; $rate=$sumWO?($sumT/$sumWO):null; $avgRate=($avgWO&&$avgWO!=0&&$avgT!==null)?($avgT/$avgWO):null;

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'PEMAKAIAN AIR DI MESIN WASHING II', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, strtoupper(date('F Y', strtotime($start))), 0, 1, 'C');
$pdf->Ln(1);

$w=[14,24,24,20,22,22,18,22,22,20,20,20,30];
$headers=['TGL','WATT A','WATT B','W OP','STM A','STM B','TOTAL STM','WTR A','WTR B','TOTAL','OP M','RATE','KET'];
$pdf->SetFont('Arial','B',7);
foreach($headers as $i=>$h){ $pdf->Cell($w[$i],7,$h,1,0,'C'); }
$pdf->Ln();
$pdf->SetFont('Arial','',7);
foreach($rows as $r){ $off=stripos((string)($r['Keterangan']??''),'off')!==false; $pdf->Cell($w[0],6,$day($r['tanggal']),1,0,'C'); $pdf->Cell($w[1],6,$fmt($r['WattAwal']),1,0,'R'); $pdf->Cell($w[2],6,$fmt($r['WattAkhir']),1,0,'R'); $pdf->Cell($w[3],6,$fmt($r['WattOperasional']),1,0,'R'); $pdf->Cell($w[4],6,$fmt($r['SteamAwal']),1,0,'R'); $pdf->Cell($w[5],6,$fmt($r['SteamAkhir']),1,0,'R'); $pdf->Cell($w[6],6,$fmt($r['SteamPemakaian']),1,0,'R'); $pdf->Cell($w[7],6,$fmt($r['WaterAwal']),1,0,'R'); $pdf->Cell($w[8],6,$fmt($r['WaterAkhir']),1,0,'R'); $pdf->Cell($w[9],6,$fmt($r['TotalPemakaian']),1,0,'R'); $pdf->Cell($w[10],6,$fmt($r['WaterOperasional']),1,0,'R'); $pdf->Cell($w[11],6,$fmt($r['PemakaianRataPerJam']),1,0,'R'); $pdf->Cell($w[12],6,(string)($r['Keterangan']??''),1,0,'L',$off); $pdf->Ln(); }
$pdf->SetFont('Arial','B',7);
$pdf->Cell($w[0],6,'TOTAL',1,0,'C'); $pdf->Cell($w[1],6,'',1,0); $pdf->Cell($w[2],6,'',1,0); $pdf->Cell($w[3],6,$fmt($sumW),1,0,'R'); $pdf->Cell($w[4],6,'',1,0); $pdf->Cell($w[5],6,'',1,0); $pdf->Cell($w[6],6,$fmt($sumS),1,0,'R'); $pdf->Cell($w[7],6,'',1,0); $pdf->Cell($w[8],6,'',1,0); $pdf->Cell($w[9],6,$fmt($sumT),1,0,'R'); $pdf->Cell($w[10],6,$fmt($sumWO),1,0,'R'); $pdf->Cell($w[11],6,$fmt($rate),1,0,'R'); $pdf->Cell($w[12],6,'',1,1);
$pdf->Cell($w[0],6,'RATA2',1,0,'C'); $pdf->Cell($w[1],6,'',1,0); $pdf->Cell($w[2],6,'',1,0); $pdf->Cell($w[3],6,$fmt($avgW),1,0,'R'); $pdf->Cell($w[4],6,'',1,0); $pdf->Cell($w[5],6,'',1,0); $pdf->Cell($w[6],6,$fmt($avgS),1,0,'R'); $pdf->Cell($w[7],6,'',1,0); $pdf->Cell($w[8],6,'',1,0); $pdf->Cell($w[9],6,$fmt($avgT),1,0,'R'); $pdf->Cell($w[10],6,$fmt($avgWO),1,0,'R'); $pdf->Cell($w[11],6,$fmt($avgRate),1,0,'R'); $pdf->Cell($w[12],6,'',1,1);

while (ob_get_level() > 0) ob_end_clean();
$filename = 'Laporan_Washing2_' . date('Ymd_His') . '.pdf';
$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;

<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Washing2_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

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
if ($stmt === false) { echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true)); exit; }
$rows=[]; while($r=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){ if($r['SteamPemakaian']===null && $r['SteamAwal']!==null && $r['SteamAkhir']!==null){$r['SteamPemakaian']=round((float)$r['SteamAkhir']-(float)$r['SteamAwal'],2);} if($r['TotalPemakaian']===null && $r['WaterAwal']!==null && $r['WaterAkhir']!==null){$r['TotalPemakaian']=round((float)$r['WaterAkhir']-(float)$r['WaterAwal'],2);} if($r['PemakaianRataPerJam']===null && $r['TotalPemakaian']!==null && $r['WaterOperasional']!==null && (float)$r['WaterOperasional']!=0){$r['PemakaianRataPerJam']=round((float)$r['TotalPemakaian']/(float)$r['WaterOperasional'],2);} $rows[]=$r; }
if($stmt) sqlsrv_free_stmt($stmt);

$fmt = function($v){ if($v===null||$v==='') return ''; return is_numeric($v)?number_format((float)$v,2,'.',','):(string)$v; };
$day = function($v){ if($v instanceof DateTime) return $v->format('j'); return date('j',strtotime((string)$v)); };

$sumW=0;$sumS=0;$sumT=0;$sumWO=0;$cW=0;$cS=0;$cT=0;$cWO=0;
foreach($rows as $r){ if(is_numeric($r['WattOperasional'])){$sumW+=(float)$r['WattOperasional'];$cW++;} if(is_numeric($r['SteamPemakaian'])){$sumS+=(float)$r['SteamPemakaian'];$cS++;} if(is_numeric($r['TotalPemakaian'])){$sumT+=(float)$r['TotalPemakaian'];$cT++;} if(is_numeric($r['WaterOperasional'])){$sumWO+=(float)$r['WaterOperasional'];$cWO++;} }
$avgW=$cW?$sumW/$cW:null; $avgS=$cS?$sumS/$cS:null; $avgT=$cT?$sumT/$cT:null; $avgWO=$cWO?$sumWO/$cWO:null; $rate=$sumWO?($sumT/$sumWO):null; $avgRate=($avgWO&&$avgWO!=0&&$avgT!==null)?($avgT/$avgWO):null;
$monthMap=['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn=date('F', strtotime($start));
$monthLabel=($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

echo "<html><head><meta charset='UTF-8'><style>
table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px}th,td{border:1px solid #000;padding:4px;text-align:center;vertical-align:middle}.top{background:#afc0d6;font-weight:bold}.sub{background:#cfdeef;font-weight:bold}.watt{background:#cfdeef}.steam{background:#cfdeef}.water{background:#cfdeef}.yellow{background:#b7c9de}.sum{background:#d9d6c4;font-weight:bold}.left{text-align:left}.num2{mso-number-format:'0.00'}
</style></head><body><table>";
echo "<tr><th rowspan='4' class='top'></th><th colspan='11' class='top'>PEMAKAIAN AIR DI MESIN WASHING II</th><th rowspan='3' class='top'>KET</th></tr>";
echo "<tr><th colspan='11' class='top'>" . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr><th colspan='2' class='sub'>WATT PER HOUR METER</th><th rowspan='2' class='sub'>OPERASIONAL MESIN</th><th colspan='3' class='sub'>STEAM FLOW METER</th><th colspan='2' class='sub'>WATER FLOW METER</th><th rowspan='2' class='sub'>TOTAL PEMAKAIAN</th><th rowspan='2' class='sub'>OPERASIONAL MESIN</th><th rowspan='2' class='sub'>PEMAKAIAN RATA RATA PER JAM</th></tr>";
echo "<tr><th class='sub'>AWAL<br>KW</th><th class='sub'>AKHIR<br>KW</th><th class='sub'>AWAL<br>TON</th><th class='sub'>AKHIR<br>TON</th><th class='sub'>TOTAL PEMAKAIAN</th><th class='sub'>AWAL<br>M3</th><th class='sub'>AKHIR<br>M3</th><th class='sub'></th></tr>";
echo "<tr><th class='sub'>TANGGAL</th><th class='sub' colspan='12'></th></tr>";

if(empty($rows)){ echo "<tr><td colspan='13'>Tidak ada data</td></tr>"; }
else {
 foreach($rows as $r){ $off=stripos((string)($r['Keterangan']??''),'off')!==false; echo "<tr><td><b>".htmlspecialchars($day($r['tanggal']))."</b></td><td class='watt num2'><b>".htmlspecialchars($fmt($r['WattAwal']))."</b></td><td class='watt num2'><b>".htmlspecialchars($fmt($r['WattAkhir']))."</b></td><td class='watt num2'><b>".htmlspecialchars($fmt($r['WattOperasional']))."</b></td><td class='steam num2'><b>".htmlspecialchars($fmt($r['SteamAwal']))."</b></td><td class='steam num2'><b>".htmlspecialchars($fmt($r['SteamAkhir']))."</b></td><td class='num2'><b>".htmlspecialchars($fmt($r['SteamPemakaian']))."</b></td><td class='water num2'><b>".htmlspecialchars($fmt($r['WaterAwal']))."</b></td><td class='water num2'><b>".htmlspecialchars($fmt($r['WaterAkhir']))."</b></td><td class='yellow num2'><b>".htmlspecialchars($fmt($r['TotalPemakaian']))."</b></td><td class='num2'><b>".htmlspecialchars($fmt($r['WaterOperasional']))."</b></td><td class='num2'><b>".htmlspecialchars($fmt($r['PemakaianRataPerJam']))."</b></td><td class='left".($off?' yellow':'')."'><b>".htmlspecialchars((string)($r['Keterangan']??''))."</b></td></tr>"; }
 echo "<tr class='sum'><td>TOTAL</td><td></td><td></td><td class='num2'>".htmlspecialchars($fmt($sumW))."</td><td></td><td></td><td class='num2'>".htmlspecialchars($fmt($sumS))."</td><td></td><td></td><td class='num2'>".htmlspecialchars($fmt($sumT))."</td><td class='num2'>".htmlspecialchars($fmt($sumWO))."</td><td class='num2'>".htmlspecialchars($fmt($rate))."</td><td></td></tr>";
 echo "<tr class='sum'><td>RATA-RATA</td><td></td><td></td><td class='num2'>".htmlspecialchars($fmt($avgW))."</td><td></td><td></td><td class='num2'>".htmlspecialchars($fmt($avgS))."</td><td></td><td></td><td class='num2'>".htmlspecialchars($fmt($avgT))."</td><td class='num2'>".htmlspecialchars($fmt($avgWO))."</td><td class='num2'>".htmlspecialchars($fmt($avgRate))."</td><td></td></tr>";
}
echo "</table></body></html>";

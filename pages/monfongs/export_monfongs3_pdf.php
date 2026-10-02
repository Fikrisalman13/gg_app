<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
include '../../koneksi.php';
include '../../koneksi4.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

if ($start_date == '' || $end_date == '') {
    die("Parameter tanggal tidak valid!");
}

$start_date = str_replace("T", " ", $start_date) . ":00";
$end_date   = str_replace("T", " ", $end_date) . ":00";

// Query data Monfongs 3 (Paddry 3)
$sqlData = "
    SELECT
        b.Machine AS Mesin,
        a.LogTimeStamp AS Waktu,
        ROUND(a.Value01/10, 0) AS [Speed],
        a.Value27 AS [TD1],
        a.Value06 AS [EXT1],
        a.Value04 AS [EXT2],
        a.Value13 AS [NTA1],
        a.Value20 AS [NTA2],
        a.Value14 AS [NTB1],
        a.Value21 AS [NTB2],
        a.Value18 AS [HCT1],
        a.Value19 AS [HCT2]
    FROM dbo.logvaluefloat AS a
    LEFT JOIN dbo.logtype AS b ON b.ID = a.LogType_ID
    WHERE a.LogType_ID = '1311'
      AND a.LogTimeStamp BETWEEN ? AND ?
    ORDER BY a.LogTimeStamp ASC
";

$stmtData = sqlsrv_prepare($conn4, $sqlData, [$start_date, $end_date]);
$data = [];
if ($stmtData) {
    sqlsrv_execute($stmtData);
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $data[] = $row;
    }
} else {
    die(print_r(sqlsrv_errors(), true));
}

// ================= PDF Generator ==================
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GG App');
$pdf->SetAuthor('SUM IT Kabag');
$pdf->SetTitle('Data Mesin Monfongs 3 (Paddry 3)');
$pdf->SetHeaderData('', 0, 'Data Mesin Monfongs 3 (Paddry 3)', 
    'Periode: ' . $_GET['start_date'] . ' s/d ' . $_GET['end_date']);

// Header & Footer
$pdf->setHeaderFont(['helvetica', '', 10]);
$pdf->setFooterFont(['helvetica', '', 8]);
$pdf->SetMargins(10, 25, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 8);

// ================= Info Singkatan =================
$info = '
<div style="border:1px solid #17a2b8; background-color:#e9f7fd; padding:6px; margin-bottom:8px;">
    <strong>Penjelasan Singkatan:</strong>
    <ul style="margin:0; padding-left:15px;">
        <li><b>TD</b> = Temp Dryer</li>
        <li><b>EXT</b> = Exhaust Thermex</li>
        <li><b>NTA</b> = Nozzle Thermex Atas</li>
        <li><b>NTB</b> = Nozzle Thermex Bawah</li>
        <li><b>HCT</b> = Heating Chamber Thermex</li>
    </ul>
</div>';
$pdf->writeHTML($info, true, false, true, false, '');

// ================= Tabel Data =================
$html = '<table border="1" cellpadding="3">
<thead>
<tr style="background-color:#f2f2f2; font-weight:bold; text-align:center;">
    <th>Waktu</th>
    <th>Speed</th>
    <th>TD1</th>
    <th>EXT1</th>
    <th>EXT2</th>
    <th>NTA1</th>
    <th>NTA2</th>
    <th>NTB1</th>
    <th>NTB2</th>
    <th>HCT1</th>
    <th>HCT2</th>
</tr>
</thead>
<tbody>';

if (!empty($data)) {
    foreach ($data as $row) {
        $html .= '<tr style="text-align:center;">
            <td>'.($row['Waktu'] instanceof DateTime ? $row['Waktu']->format('H:i:s') : '').'</td>
            <td>'.$row['Speed'].'</td>
            <td>'.$row['TD1'].'</td>
            <td>'.$row['EXT1'].'</td>
            <td>'.$row['EXT2'].'</td>
            <td>'.$row['NTA1'].'</td>
            <td>'.$row['NTA2'].'</td>
            <td>'.$row['NTB1'].'</td>
            <td>'.$row['NTB2'].'</td>
            <td>'.$row['HCT1'].'</td>
            <td>'.$row['HCT2'].'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="11" align="center">Data tidak ditemukan</td></tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('Data_Monfongs3_' . date("Ymd_His") . '.pdf', 'I');

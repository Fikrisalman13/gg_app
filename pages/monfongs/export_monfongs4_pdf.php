<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
include '../../koneksi.php';
include '../../koneksi4.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// Ambil filter dari GET
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

if ($start_date == '' || $end_date == '') {
    die("Parameter tanggal tidak valid!");
}

$start_date = str_replace("T", " ", $start_date) . ":00";
$end_date   = str_replace("T", " ", $end_date) . ":00";

// Query data
$sqlData = "
    SELECT
        b.Machine AS Mesin, 
        a.LogTimeStamp AS Waktu, 
        ROUND(a.Value37/10, 0) AS [Speed], 
        a.Value49 AS [TD1], 
        a.Value50 AS [TD2], 
        a.Value09 AS [NTA1], 
        a.Value10 AS [NTA2], 
        a.Value11 AS [NTA3], 
        a.Value12 AS [NTB1], 
        a.Value13 AS [NTB2], 
        a.Value14 AS [NTB3], 
        a.Value15 AS [NTB4],
        c.Value07 AS [HCT1], 
        c.Value08 AS [HCT2], 
        a.Value30 AS [HCT3], 
        a.Value31 AS [HCT4]
    FROM dbo.logvaluefloat AS a
    LEFT JOIN dbo.logtype AS b ON b.ID = a.LogType_ID
    LEFT JOIN dbo.logvaluefloat AS c 
        ON c.LogTimeStamp = a.LogTimeStamp AND c.LogType_ID = '1411'
    LEFT JOIN dbo.logtype AS d ON d.ID = c.LogType_ID
    WHERE a.LogType_ID = '1401'
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
$pdf->SetTitle('Data Mesin Monfongs 4 (Paddry 5)');
$pdf->SetHeaderData('', 0, 'Data Mesin Monfongs 4 (Paddry 5)', 
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
        <li><b>TD</b> = Temperature Dryer</li>
        <li><b>NTA / NTB</b> = Nozzle Temperature A / B</li>
        <li><b>HCT</b> = Heat Chamber Temperature</li>
    </ul>
</div>';
$pdf->writeHTML($info, true, false, true, false, '');

// ================= Tabel Data =================
$html = '<table border="1" cellpadding="3">
<thead>
<tr style="background-color:#f2f2f2; font-weight:bold; text-align:center;">
    <th>Waktu</th>
    <th>Speed</th>
    <th>TD1</th><th>TD2</th>
    <th>NTA1</th><th>NTA2</th><th>NTA3</th>
    <th>NTB1</th><th>NTB2</th><th>NTB3</th><th>NTB4</th>
    <th>HCT1</th><th>HCT2</th><th>HCT3</th><th>HCT4</th>
</tr>
</thead>
<tbody>';

if (!empty($data)) {
    foreach ($data as $row) {
        $html .= '<tr style="text-align:center;">
            <td>'.($row['Waktu'] instanceof DateTime ? $row['Waktu']->format('H:i:s') : '').'</td>
            <td>'.$row['Speed'].'</td>
            <td>'.$row['TD1'].'</td>
            <td>'.$row['TD2'].'</td>
            <td>'.$row['NTA1'].'</td>
            <td>'.$row['NTA2'].'</td>
            <td>'.$row['NTA3'].'</td>
            <td>'.$row['NTB1'].'</td>
            <td>'.$row['NTB2'].'</td>
            <td>'.$row['NTB3'].'</td>
            <td>'.$row['NTB4'].'</td>
            <td>'.$row['HCT1'].'</td>
            <td>'.$row['HCT2'].'</td>
            <td>'.$row['HCT3'].'</td>
            <td>'.$row['HCT4'].'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="15" align="center">Data tidak ditemukan</td></tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('Data_Monfongs4_' . date("Ymd_His") . '.pdf', 'I');

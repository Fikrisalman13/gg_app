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
    ROUND(c.Value21/10, 0) AS [Speed],
    a.Value30 AS TC1,
    a.Value31 AS TC2,
    a.Value32 AS TC3,
    a.Value33 AS TC4,
    a.Value34 AS TC5,
    a.Value35 AS TC6,
    a.Value36 AS TC7,
    a.Value37 AS TC8,
    a.Value38 AS TC9,
    a.Value27 AS TC10,
    a.Value28 AS TC11,
    a.Value29 AS TC12,
    a.Value39 AS EX1,
    a.Value40 AS EX2,
    ROUND(c.Value03/10,0) AS ARM,
    c.Value31 AS AEAH,
    c.Value01 AS AFRM
FROM
    dbo.logvaluefloat AS a
    LEFT JOIN dbo.logtype AS b ON b.ID = a.LogType_ID
    LEFT JOIN dbo.logvaluefloat AS c ON a.LogTimeStamp = c.LogTimeStamp 
        AND c.LogType_ID = '1112'
    LEFT JOIN dbo.logtype AS d ON c.LogType_ID = d.ID 
WHERE
    a.LogType_ID = '1111' 
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
$pdf->SetTitle('Data Mesin Monfongs 1');
$pdf->SetHeaderData('', 0, 'Data Mesin Monfongs 1 (Stenter)', 
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
        <li><b>TC</b> = Temp Chamber</li>
        <li><b>EX</b> = Exhaust</li>
        <li><b>AEAH</b> = Actual exhaust air humidity</li>
        <li><b>AFRM</b> = Actual type of fibre residual moisture</li>
        <li><b>ARM</b> = Actual Residual Moisture</li>
    </ul>
</div>';
$pdf->writeHTML($info, true, false, true, false, '');

// ================= Tabel Data =================
$html = '<table border="1" cellpadding="3">
<thead>
<tr style="background-color:#f2f2f2; font-weight:bold; text-align:center;">
    <th>Waktu</th>
    <th>Speed</th>
    <th>TC1</th><th>TC2</th><th>TC3</th><th>TC4</th><th>TC5</th><th>TC6</th>
    <th>TC7</th><th>TC8</th><th>TC9</th><th>TC10</th><th>TC11</th><th>TC12</th>
    <th>EX1</th><th>EX2</th><th>AEAH(%)</th><th>AFRM(%)</th><th>ARM(%)</th>
</tr>
</thead>
<tbody>';

if (!empty($data)) {
    foreach ($data as $row) {
        $html .= '<tr style="text-align:center;">
            <td>'.($row['Waktu'] instanceof DateTime ? $row['Waktu']->format('H:i:s') : '').'</td>
            <td>'.$row['Speed'].'</td>
            <td>'.$row['TC1'].'</td>
            <td>'.$row['TC2'].'</td>
            <td>'.$row['TC3'].'</td>
            <td>'.$row['TC4'].'</td>
            <td>'.$row['TC5'].'</td>
            <td>'.$row['TC6'].'</td>
            <td>'.$row['TC7'].'</td>
            <td>'.$row['TC8'].'</td>
            <td>'.$row['TC9'].'</td>
            <td>'.$row['TC10'].'</td>
            <td>'.$row['TC11'].'</td>
            <td>'.$row['TC12'].'</td>
            <td>'.$row['EX1'].'</td>
            <td>'.$row['EX2'].'</td>
             <td>'.$row['AEAH'].'</td>
             <td>'.$row['AFRM'].'</td>
             <td>'.$row['ARM'].'</td>
          
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="17" align="center">Data tidak ditemukan</td></tr>';
}

$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('Data_Monfongs1_' . date("Ymd_His") . '.pdf', 'I');

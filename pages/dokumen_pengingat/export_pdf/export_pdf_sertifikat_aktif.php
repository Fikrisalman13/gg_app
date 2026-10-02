<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ============================
// AUTOLOAD & DB
// ============================
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';

use Mpdf\Mpdf;

// ============================
// QUERY: SERTIFIKAT AKTIF
// ============================
$sql = "
SELECT
    s.id,
    s.nama_lembaga,
    s.nama_sertifikat,
    s.no_sertifikat,
    s.expire_date,
    b.nama_bagian
FROM dbo.dr_sertifikat s
LEFT JOIN dbo.dr_bagian b
    ON s.bagian_id = b.id
WHERE s.expire_date >= CAST(GETDATE() AS DATE)
ORDER BY s.expire_date ASC
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// ============================
// HTML PDF
// ============================
$tgl_print = date('d-m-Y');

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 11px; }
    h1 { text-align: center; margin-bottom: 8px; }
    .print-date { text-align: right; margin-bottom: 8px; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 6px; }
    th { background-color: #f2f2f2; text-align: center; }
    td { vertical-align: top; }
    .center { text-align: center; }
</style>
</head>
<body>

<h1>Sertifikat Aktif</h1>
<div class="print-date">Tgl Print: ' . $tgl_print . '</div>

<table>
<thead>
<tr>
    <th>No</th>
    <th>Nama Lembaga</th>
    <th>Nama Sertifikat</th>
    <th>No Sertifikat</th>
    <th>Tgl Expired</th>
    <th>Bagian</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
';

$no = 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $expireDate = $row['expire_date'] instanceof DateTime
        ? $row['expire_date']->format('d-m-Y')
        : '';

    $html .= '
    <tr>
        <td class="center">' . $no++ . '</td>
        <td>' . htmlspecialchars($row['nama_lembaga']) . '</td>
        <td>' . htmlspecialchars($row['nama_sertifikat']) . '</td>
        <td>' . htmlspecialchars($row['no_sertifikat']) . '</td>
        <td class="center">' . $expireDate . '</td>
        <td>' . htmlspecialchars($row['nama_bagian']) . '</td>
        <td class="center">Aktif</td>
    </tr>';
}

$html .= '
</tbody>
</table>

</body>
</html>
';

// ============================
// MPDF
// ============================
$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4-L',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 12,
    'margin_bottom' => 12
]);

$mpdf->WriteHTML($html);
$mpdf->Output('Sertifikat_Aktif_' . date('Ymd') . '.pdf', 'I'); // I = inline, D = download
exit;

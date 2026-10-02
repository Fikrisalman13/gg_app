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
// QUERY SQL SERVER
// ============================
$sql = "
SELECT
    k.id,
    k.nama_vendor,
    k.nama_pekerjaan,
    k.no_kontrak,
    k.expire_date,
    k.file_path,
    k.keterangan,
    k.email_reminder,
    k.no_whatsapp,
    k.createdate,
    k.updatedate,
    k.bagian_id,
    b.nama_bagian,
    CASE
        WHEN k.expire_date < CAST(GETDATE() AS DATE) THEN 'Kadaluarsa'
        WHEN k.expire_date BETWEEN CAST(GETDATE() AS DATE)
             AND DATEADD(
                    DAY,
                    CAST(ri.nilai AS INT),
                    CAST(GETDATE() AS DATE)
                )
             THEN 'Reminder'
        ELSE 'Aktif'
    END AS status
FROM dbo.dr_kontrak k
LEFT JOIN dbo.dr_bagian b
    ON k.bagian_id = b.id
LEFT JOIN dbo.dr_reminder_interval ri
    ON ri.kunci = 'reminder_interval_kontrak'
ORDER BY k.expire_date ASC
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// ============================
// HTML CONTENT
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

<h1>Daftar Dokumen Kontrak</h1>
<div class="print-date">Tgl Print: ' . $tgl_print . '</div>

<table>
<thead>
<tr>
    <th>No</th>
    <th>Nama Vendor</th>
    <th>Nama Pekerjaan</th>
    <th>No Kontrak</th>
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
        <td>' . htmlspecialchars($row['nama_vendor']) . '</td>
        <td>' . htmlspecialchars($row['nama_pekerjaan']) . '</td>
        <td>' . htmlspecialchars($row['no_kontrak']) . '</td>
        <td class="center">' . $expireDate . '</td>
        <td>' . htmlspecialchars($row['nama_bagian']) . '</td>
        <td class="center">' . $row['status'] . '</td>
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
$mpdf->Output('Kontrak_' . date('Ymd') . '.pdf', 'I'); // I = inline, D = download
exit;

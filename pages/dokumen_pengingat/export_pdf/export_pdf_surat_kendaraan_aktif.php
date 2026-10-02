<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ============================
// AUTOLOAD & DB
// ============================
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';

use Dompdf\Dompdf;

// ============================
// QUERY DATA AKTIF
// ============================
$sql = "
SELECT
    sk.jenis_kendaraan,
    sk.nama_kendaraan,
    sk.nama_pemilik,
    sk.no_polisi,
    sk.expire_date,
    sk.keterangan,
    b.nama_bagian
FROM dbo.dr_surat_kendaraan sk
LEFT JOIN dbo.dr_bagian b
    ON sk.bagian_id = b.id
WHERE sk.expire_date >= CAST(GETDATE() AS DATE)
ORDER BY sk.expire_date ASC
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
<h2 style="text-align:center;">Daftar Surat Kendaraan Aktif</h2>
<p style="text-align:right;font-size:12px;">Tgl Print: ' . $tgl_print . '</p>

<table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:11px;">
<thead>
<tr style="background:#f2f2f2;">
    <th>No</th>
    <th>Jenis Kendaraan</th>
    <th>Nama Kendaraan</th>
    <th>Nama Pemilik</th>
    <th>Nomor Polisi</th>
    <th>Tgl Expired</th>
    <th>Bagian</th>
    <th>Keterangan</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
';

$no = 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $expireDate = $row['expire_date'] instanceof DateTime
        ? $row['expire_date']->format('Y-m-d')
        : '';

    $html .= '
    <tr>
        <td align="center">' . $no++ . '</td>
        <td>' . htmlspecialchars($row['jenis_kendaraan']) . '</td>
        <td>' . htmlspecialchars($row['nama_kendaraan']) . '</td>
        <td>' . htmlspecialchars($row['nama_pemilik']) . '</td>
        <td>' . htmlspecialchars($row['no_polisi']) . '</td>
        <td align="center">' . $expireDate . '</td>
        <td>' . htmlspecialchars($row['nama_bagian']) . '</td>
        <td>' . htmlspecialchars($row['keterangan']) . '</td>
        <td align="center"><b>Aktif</b></td>
    </tr>';
}

$html .= '
</tbody>
</table>
';

// ============================
// GENERATE PDF
// ============================
$dompdf = new Dompdf([
    'defaultFont' => 'DejaVu Sans'
]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Surat_Kendaraan_Aktif.pdf', ['Attachment' => true]);
exit;

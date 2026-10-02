<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require '../../../vendor/autoload.php';
use Dompdf\Dompdf;

require '../../../koneksi.php';
session_start();

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu!');
}

$filterKategori = $_GET['kategori'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';

$whereClauses = [];
$params = [];

if ($filterKategori !== '') {
    $whereClauses[] = 'kategori = ?';
    $params[] = $filterKategori;
}

if ($filterTanggal !== '') {
    $whereClauses[] = 'CAST(tanggal_serah_terima AS DATE) = ?';
    $params[] = $filterTanggal;
}

if ((int) ($_SESSION['GroupId'] ?? 0) !== 1) {
    $whereClauses[] = 'created_by = ?';
    $params[] = $_SESSION['NamaLengkap'] ?? '';
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
$sql = "SELECT TOP 200 *
        FROM dbo.Form_Serah_Terima_Aplikasi
        $whereSql
        ORDER BY tanggal_serah_terima DESC, created_at DESC";
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Serah Terima</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .center { text-align: center; }
        .empty { color: #555; font-style: italic; text-align: center; }
    </style>
</head>
<body>
    <h2>Laporan Daftar Serah Terima</h2>
    <p>
        <strong>Kategori:</strong> ' . htmlspecialchars($filterKategori ?: 'Semua') . '<br>
        <strong>Tanggal:</strong> ' . ($filterTanggal ? date('d-m-Y', strtotime($filterTanggal)) : 'Semua') . '
    </p>
    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">No Pengajuan</th>
                <th width="15%">Nama Aplikasi</th>
                <th width="15%">Pemohon</th>
                <th width="15%">Departemen</th>
                <th width="15%">Tanggal</th>
                <th width="20%">Status</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tanggal_serah_terima'] instanceof DateTime ? $row['tanggal_serah_terima']->format('d-m-Y') : '-';
    $html .= '
        <tr>
            <td class="center">' . $no++ . '</td>
            <td>' . htmlspecialchars($row['ticket']) . '</td>
            <td>' . htmlspecialchars($row['nama_aplikasi']) . '</td>
            <td>' . htmlspecialchars($row['nama_pemohon']) . '</td>
            <td>' . htmlspecialchars($row['departemen']) . '</td>
            <td class="center">' . $tgl . '</td>
            <td class="center">' . htmlspecialchars($row['status_ticket']) . '</td>
        </tr>';
}

if ($no === 1) {
    $html .= '<tr><td colspan="7" class="empty">Belum ada data serah terima.</td></tr>';
}

$html .= '
        </tbody>
    </table>
    <div style="margin-top: 20px; font-size: 9pt; color: #555;">
        Dicetak pada: ' . date('d-m-Y H:i:s') . ' oleh ' . htmlspecialchars($_SESSION['UserName']) . '
    </div>
</body>
</html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Laporan_Serah_Terima_' . date('YmdHis') . '.pdf', ['Attachment' => false]);

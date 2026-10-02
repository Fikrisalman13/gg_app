<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require '../../vendor/autoload.php';
use Dompdf\Dompdf;

require '../../koneksi.php';

session_start();

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// Filter Logic
$filterKategori = $_GET['kategori'] ?? '';
$filterTanggal = $_GET['tanggal'] ?? '';

$whereClauses = [];
$params = [];

if (!empty($filterKategori)) {
    $whereClauses[] = "x.kategori = ?";
    $params[] = $filterKategori;
}

if (!empty($filterTanggal)) {
    $whereClauses[] = "CAST(x.tgl_pengajuan AS DATE) = ?";
    $params[] = $filterTanggal;
}

$whereSql = "";
if (count($whereClauses) > 0) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Query Data
$sql = "SELECT TOP 200 * FROM (
            SELECT 
                ticket,
                nama_pemohon,
                departemen,
                tgl_pengajuan,
                pengajuan,
                peripheral,
                status_ticket,
                ISNULL(kategori,'Perangkat IT') AS kategori,
                created_at
            FROM Form_Pengajuan_Barang
            UNION ALL
            SELECT 
                ticket,
                nama_pemohon,
                departemen,
                tgl_pengajuan,
                'Rekaman CCTV' AS pengajuan,
                '' AS peripheral,
                status_ticket,
                ISNULL(kategori,'CCTV') AS kategori,
                created_at
            FROM Form_Pengajuan_CCTV
            UNION ALL
            SELECT 
                ticket,
                nama_pemohon,
                departemen,
                tgl_pengajuan,
                'Akses Internet' AS pengajuan,
                '' AS peripheral,
                status_ticket,
                ISNULL(kategori,'Akses Internet') AS kategori,
                created_at
            FROM Form_Pengajuan_Akses_Internet
        ) AS x
        $whereSql
        ORDER BY x.tgl_pengajuan DESC, x.created_at DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Build HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pengajuan IT</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .center { text-align: center; }
        .badge { padding: 2px 5px; border-radius: 3px; font-size: 8pt; color: #fff; display: inline-block; }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .badge-info { background-color: #17a2b8; }
        .badge-warning { background-color: #ffc107; color: #000; }
    </style>
</head>
<body>
    <h2>Laporan Daftar Pengajuan IT</h2>
    <p>
        <strong>Kategori:</strong> ' . ($filterKategori ?: 'Semua') . '<br>
        <strong>Tanggal:</strong> ' . ($filterTanggal ? date('d-m-Y', strtotime($filterTanggal)) : 'Semua') . '
    </p>
    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">No Pengajuan</th>
                <th width="15%">Kategori</th>
                <th width="20%">Pemohon</th>
                <th width="15%">Departemen</th>
                <th width="15%">Tanggal</th>
                <th width="15%">Status</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tgl = $row['tgl_pengajuan'] instanceof DateTime 
        ? $row['tgl_pengajuan']->format('d-m-Y') 
        : '-';
    
    $status = strtolower($row['status_ticket']);
    $statusLabel = 'Pending';
    $badgeClass = 'badge-warning';
    
    if ($status == 'approved') {
        $statusLabel = 'Approved';
        $badgeClass = 'badge-success';
    } elseif ($status == 'rejected' || $status == 'ditolak') {
        $statusLabel = 'Ditolak';
        $badgeClass = 'badge-danger';
    } elseif ($status == 'progress') {
        $statusLabel = 'On Progress';
        $badgeClass = 'badge-info';
    }

    $html .= '
            <tr>
                <td class="center">' . $no++ . '</td>
                <td>' . htmlspecialchars($row['ticket']) . '</td>
                <td>' . htmlspecialchars($row['kategori']) . '</td>
                <td>' . htmlspecialchars($row['nama_pemohon']) . '</td>
                <td>' . htmlspecialchars($row['departemen']) . '</td>
                <td class="center">' . $tgl . '</td>
                <td class="center"><span class="badge ' . $badgeClass . '">' . $statusLabel . '</span></td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
    <div style="margin-top: 20px; font-size: 9pt; color: #555;">
        Dicetak pada: ' . date('d-m-Y H:i:s') . ' oleh ' . htmlspecialchars($_SESSION['UserName']) . '
    </div>
</body>
</html>';

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // Landscape for better table view
$dompdf->render();

// Output PDF
$dompdf->stream("Laporan_Pengajuan_IT_" . date('YmdHis') . ".pdf", ["Attachment" => false]);
?>

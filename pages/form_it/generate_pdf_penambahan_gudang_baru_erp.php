<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../../vendor/autoload.php';
require '../../koneksi.php';

use Dompdf\Dompdf;

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu.');
}

$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
if ($ticket === '') {
    die('Ticket tidak diberikan');
}

$sql = "SELECT * FROM dbo.Form_Penambahan_Gudang_Baru_ERP WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    die('Data tidak ditemukan');
}

function pdfClean($value) {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function pdfDate($value) {
    if ($value instanceof DateTime) return $value->format('d-m-Y');
    if (is_string($value) && $value !== '') return date('d-m-Y', strtotime($value));
    return '-';
}

$ttd = [];
$ttdStmt = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName FROM dbo.Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket]);
if ($ttdStmt) {
    while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
        $ttd[$r['GroupRole']] = $r;
    }
    sqlsrv_free_stmt($ttdStmt);
}

function pdfSigCell($ttd, $role) {
    if (!isset($ttd[$role]) || empty($ttd[$role]['SignaturePath'])) return '&nbsp;';
    $path = $ttd[$role]['SignaturePath'];
    $signed = pdfClean($ttd[$role]['SignedByUserName'] ?? '');
    $abs = null;

    if (file_exists($path)) $abs = $path;
    elseif (isset($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) $abs = $_SERVER['DOCUMENT_ROOT'] . $path;
    elseif (file_exists(__DIR__ . '/../../' . ltrim($path, '/'))) $abs = __DIR__ . '/../../' . ltrim($path, '/');

    if ($abs) {
        $img = base64_encode(file_get_contents($abs));
        return "<img src='data:image/png;base64,$img' style='max-height:55px;display:block;margin:0 auto;'><br><small>$signed</small>";
    }

    return "<small>$signed</small>";
}

$logoPath = __DIR__ . "/../../dist/img/sumlogo.png";
$logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
$logoTag = $logoB64 ? "<img src='data:image/png;base64,$logoB64' width='60'>" : "";
$itSignature = pdfSigCell($ttd, 'Kabag IT');
if ($itSignature === '&nbsp;') {
    $itSignature = pdfSigCell($ttd, 'Kadept IT');
}

$html = "<html><head><style>
body{font-family:Arial,sans-serif;font-size:10pt;}
table{border-collapse:collapse;width:100%;}
td{vertical-align:top;padding:3px 6px;}
.border{border:1px solid #000;}
.center{text-align:center;}
.bold{font-weight:bold;}
.sign td{height:80px;vertical-align:middle;text-align:center;}
</style></head><body>";

$html .= "<table class='border' style='border-bottom:none;'><tr>";
$html .= "<td width='15%' style='border-right:1px solid #000;text-align:center;padding:10px;'>$logoTag</td>";
$html .= "<td style='line-height:1.3;font-size:9pt;'><b>PT. SURYA USAHA MANDIRI</b><br>Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>Banjaran - Kab. Bandung<br>40377 Telp. (022) 594-0313</td></tr>";
$html .= "<tr><td colspan='2' class='center bold' style='border-top:1px solid #000;border-bottom:1px solid #000;padding:8px;'>FORM PENAMBAHAN GUDANG BARU DI SYSTEM ERP</td></tr></table>";

$html .= "<table class='border' style='border-top:none;border-bottom:none;table-layout:fixed;'>";
$html .= "<tr><td width='25%'>No. Pengajuan</td><td width='2%'>:</td><td>" . pdfClean($row['ticket']) . "</td><td width='35%' style='text-align:right;'>Tgl Pengajuan : " . pdfDate($row['tgl_pengajuan']) . "</td></tr>";
$html .= "<tr><td>Nama Pemohon</td><td>:</td><td colspan='2'>" . pdfClean($row['nama_pemohon']) . "</td></tr>";
$html .= "<tr><td>Jabatan</td><td>:</td><td colspan='2'>" . pdfClean($row['jabatan']) . "</td></tr>";
$html .= "<tr><td>Departemen</td><td>:</td><td colspan='2'>" . pdfClean($row['departemen']) . "</td></tr>";
$html .= "<tr><td>Bagian</td><td>:</td><td colspan='2'>" . pdfClean($row['bagian']) . "</td></tr>";
$html .= "<tr><td>Nama Gudang Baru</td><td>:</td><td colspan='2'>" . pdfClean($row['nama_gudang_baru']) . "</td></tr>";
$html .= "<tr><td>User Akses Gudang</td><td>:</td><td colspan='2'>" . nl2br(pdfClean($row['user_akses_gudang'])) . "</td></tr>";
$html .= "<tr><td>Keterangan/Alasan</td><td>:</td><td colspan='2' style='padding-bottom:15px;'>" . nl2br(pdfClean($row['keterangan'])) . "</td></tr>";
$html .= "</table>";

$html .= "<table class='border center' style='table-layout:fixed;'>";
$html .= "<tr class='bold'><td>Diajukan oleh,</td><td>Diketahui oleh,</td><td>Mengetahui,</td><td>Disetujui oleh,</td></tr>";
$html .= "<tr class='sign'><td>" . pdfSigCell($ttd, 'Pemohon') . "</td><td>" . pdfSigCell($ttd, 'Atasan Pemohon') . "</td><td>" . $itSignature . "</td><td>" . pdfSigCell($ttd, 'Direksi') . "</td></tr>";
$html .= "<tr class='bold'><td>Pemohon</td><td>Atasan Pemohon</td><td>Kabag/Kadept IT</td><td>Direksi</td></tr>";
$html .= "</table>";
$html .= "<table style='border:1px solid #000;border-top:none;font-size:9pt;'><tr><td class='center bold' style='border-right:1px solid #000;'>SUM-FM-IT-019</td><td class='center bold'>ERP</td></tr></table>";

$html .= "</body></html>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

if (ob_get_length()) {
    ob_end_clean();
}
$dompdf->stream("Penambahan_Gudang_Baru_ERP_" . $ticket . ".pdf", ["Attachment" => false]);
exit;
?>

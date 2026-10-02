<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();
require '../../vendor/autoload.php';
use Dompdf\Dompdf;
require '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu.');
}
$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
if (!$ticket)
    die('Ticket tidak diberikan');

$sql = "SELECT * FROM Form_Buka_Tanggal_Closingan WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)))
    die('Data tidak ditemukan');

$isRejected = isset($row['status_ticket']) && strtolower(trim($row['status_ticket'])) === 'ditolak';

function fmtDate($d)
{
    if ($d instanceof DateTime)
        return $d->format('d-m-Y');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        $p = explode('-', $d);
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (is_string($d) && !empty($d))
        return date('d-m-Y', strtotime($d));
    return '-';
}
function clean($t, $len = 40)
{
    $t = trim($t ?? '');
    return $t === "" ? "<span style='display:inline-block;border-bottom:0.5px solid #000;width:{$len}ch;'>&nbsp;</span>" : htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}

$ttd = [];
$ttdStmt = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket]);
if ($ttdStmt) {
    while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
        $ttd[$r['GroupRole']] = $r;
    }
}

function sigCell($ttd, $role)
{
    if (!isset($ttd[$role]) || empty($ttd[$role]['SignaturePath']))
        return '&nbsp;';
    $path = $ttd[$role]['SignaturePath'];
    $signed = htmlspecialchars($ttd[$role]['SignedByUserName'] ?? '');
    $abs = null;
    if (file_exists($path))
        $abs = $path;
    elseif (isset($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $path))
        $abs = $_SERVER['DOCUMENT_ROOT'] . $path;
    elseif (file_exists(__DIR__ . '/../../' . ltrim($path, '/')))
        $abs = __DIR__ . '/../../' . ltrim($path, '/');

    if ($abs) {
        $img = base64_encode(file_get_contents($abs));
        return "<img src='data:image/png;base64,$img' style='max-height:55px;display:block;margin:0 auto;'><br><small>$signed</small>";
    }
    return "<img src='" . htmlspecialchars($path) . "' style='max-height:55px;display:block;margin:0 auto;'><br><small>$signed</small>";
}

$logoPath = "../../dist/img/sumlogo.png";
$logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
$logoTag = $logoB64 ? "<img src='data:image/png;base64,$logoB64' width='60'>" : "";

$html = "<html><head><style>
body{font-family:Arial,sans-serif;font-size:10pt;}
table{border-collapse:collapse;width:100%;}
td{vertical-align:top;padding:2px 6px;}
.border{border:1px solid #000;}
.center{text-align:center;}
.bold{font-weight:bold;}
.sign td{height:80px;vertical-align:middle;text-align:center;}
</style></head><body>";

// Header
$html .= "<table class='border' style='border-bottom:none;'><tr>";
$html .= "<td width='15%' style='border-right:1px solid #000;text-align:center;padding:10px;'>" . $logoTag . "</td>";
$html .= "<td style='padding-left:5px; line-height:1.3; font-size:9pt;'><b>PT. SURYA USAHA MANDIRI</b><br>Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>Banjaran – Kab. Bandung<br>40377 Telp. (022) 594-0313</td></tr>";
$html .= "<tr><td colspan='2' class='center bold' style='border-top:1px solid #000;border-bottom:1px solid #000;padding:8px;'>FORM KOMUNIKASI BUKA TANGGAL CLOSINGAN</td></tr></table>";

// Build selected-only text for options
$selectedOptions = [];
if (($row['request_gudang'] ?? 0) == 1)
    $selectedOptions[] = "Closingan Gudang";
if (($row['request_transaksi'] ?? 0) == 1)
    $selectedOptions[] = "Closingan Transaksi";
$requestText = empty($selectedOptions) ? '-' : implode(', ', $selectedOptions);

// Details
$html .= "<table class='border' style='border-top:none; border-bottom:none; table-layout:fixed; width:100%;'>";
$html .= "<tr><td width='25%'>Nama Pemohon</td><td width='2%'>:</td><td colspan='2'>" . clean($row['nama_pemohon']) . "</td></tr>";
$html .= "<tr><td>Jabatan</td><td>:</td><td width='35%'>" . clean($row['jabatan']) . "</td><td width='38%' style='text-align:right;'>Tgl Pengajuan &nbsp;&nbsp; : &nbsp; " . fmtDate($row['tgl_pengajuan']) . "</td></tr>";
$html .= "<tr><td>Departemen</td><td>:</td><td colspan='2'>" . clean($row['departemen']) . "</td></tr>";
$html .= "<tr><td>Mengajukan permintaan untuk</td><td>:</td><td colspan='2'>" . $requestText . "</td></tr>";
$html .= "<tr><td>Buka Tgl</td><td>:</td><td colspan='2'>" . fmtDate($row['buka_tgl']) . "</td></tr>";
$html .= "<tr><td>Gudang/Transaksi</td><td>:</td><td colspan='2'>" . clean($row['gudang_transaksi']) . "</td></tr>";
$html .= "<tr><td>Keterangan/Alasan</td><td>:</td><td colspan='2' style='padding-bottom:15px;'>" . nl2br(clean($row['keterangan'], 80)) . "</td></tr>";
$html .= "</table>";

// Signature table
$html .= "<table class='border center' style='width:100%; border-collapse:collapse; table-layout:fixed;'>";
$html .= "<colgroup><col style='width:25%;'/><col style='width:25%;'/><col style='width:25%;'/><col style='width:25%;'/></colgroup>";
$html .= "<tr class='bold' style='font-size:9pt;'>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Diajukan oleh,</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Diketahui oleh,</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Mengetahui,</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Disetujui oleh,</td></tr>";

$html .= "<tr class='sign'>";
$html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, 'Pemohon') . "</td>";
$html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, 'Atasan Pemohon') . "</td>";
$html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, 'Kabag IT') . "</td>";
$html .= "<td style='border:1px solid #000;'>" . sigCell($ttd, 'Direksi') . "</td>";
$html .= "</tr>";

$html .= "<tr class='bold' style='font-size:9pt;'>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Pemohon</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Atasan Pemohon</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Kabag IT</td>";
$html .= "<td style='border:1px solid #000; padding:4px;'>Direksi</td>";
$html .= "</tr></table>";

// Footer
$html .= "<table style='width:100%; border:1px solid #000; border-top:none; font-size:9pt; table-layout:fixed;'><tr><td width='75%' class='center bold' style='border-right:1px solid #000; padding:4px;'>SUM-FM-IT-019</td><td width='25%' class='center bold' style='padding:4px;'>ERP</td></tr></table>";

$html .= "</body></html>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf = $dompdf->output();

if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Form Closingan - ' . $ticket . '.pdf"');
    echo $pdf;
    exit;
}
ob_end_clean();
$b64 = base64_encode($pdf);
$ticketEsc = htmlspecialchars($ticket);
$namaEsc = htmlspecialchars($row['nama_pemohon']);
$deptEsc = htmlspecialchars($row['departemen']);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Preview PDF Buka Closingan</title>
<link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css'>
<link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css'>
<style>
    body{background:#f4f4f4;padding:20px;}
    .container{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1); max-width: 1200px; margin: 0 auto;}
    .btn{display:inline-block;padding:8px 16px;border-radius:4px;text-decoration:none;color:#fff;}
    .btn-dl{background:#28a745;}
    .btn-back{background:#6c757d;margin-left:8px;}
    object{width:100%;height:800px;border:1px solid #ccc;}
    .ticket-info { background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 10px 15px; margin-bottom: 20px; }
    .ticket-info p { margin: 5px 0; }
</style>
<script>function downloadPDF(){window.location='?ticket=$ticketEsc&download=1';}</script></head><body><div class='container'>
<h3><i class='fas fa-file-pdf text-danger'></i> Preview Form Buka Tanggal Closingan</h3>
<div class='ticket-info'>
    <p><strong>No. Pengajuan:</strong> $ticketEsc</p>
    <p><strong>Pemohon:</strong> $namaEsc</p>
    <p><strong>Departemen:</strong> $deptEsc</p>
</div>
<object type='application/pdf' data='data:application/pdf;base64,$b64'></object>
<div style='margin-top:15px;'><a href='javascript:downloadPDF()' class='btn btn-dl'><i class='fas fa-download'></i> Download PDF</a> <a href='list_form.php' class='btn btn-back'><i class='fas fa-arrow-left'></i> Kembali</a></div>
</div></body></html>";
?>
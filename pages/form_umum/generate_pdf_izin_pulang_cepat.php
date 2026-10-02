<?php
/**
 * PDF Generator – Form Izin Pulang Cepat (IPC)
 * Mengikuti pola generate_pdf_izin_keluar_pabrik.php.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();

require '../../vendor/autoload.php';
use Dompdf\Dompdf;

date_default_timezone_set('Asia/Jakarta');
require '../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    ob_end_clean();
    http_response_code(403);
    die('Silakan login terlebih dahulu.');
}

$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
if (empty($ticket)) {
    ob_end_clean();
    http_response_code(400);
    die('Parameter ticket diperlukan');
}

$sql = "SELECT * FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    ob_end_clean();
    http_response_code(404);
    die('Tiket tidak ditemukan');
}
if ($stmt)
    sqlsrv_free_stmt($stmt);

$ttd = [];
$ttdStmt = sqlsrv_query(
    $conn,
    "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Umum_TTD WHERE Ticket = ?",
    [$ticket]
);
if ($ttdStmt) {
    while ($r = sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)) {
        $ttd[$r['GroupRole']] = $r;
    }
    sqlsrv_free_stmt($ttdStmt);
}

function ipcPdfFmtDate($d)
{
    if ($d instanceof DateTime)
        return $d->format('d-m-Y');
    if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}/', $d)) {
        $p = explode('-', substr($d, 0, 10));
        return $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    return $d ?: '-';
}
function ipcPdfVal($v)
{
    $v = trim((string) ($v ?? ''));
    return ($v === '' || $v === '-') ? '-' : htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function ipcPdfSigCell($ttd, $role)
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
    elseif (isset($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/gg_app/' . ltrim($path, '/')))
        $abs = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/' . ltrim($path, '/');

    if ($abs) {
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        $img = base64_encode(file_get_contents($abs));
        return "<img src='data:{$mime};base64,{$img}' style='max-height:55px;display:block;margin:0 auto;' alt='TTD'><br><small>{$signed}</small>";
    }
    return "<small>{$signed}</small>";
}

$logoPath = __DIR__ . '/../../dist/img/sumlogo.png';
$logoTag = file_exists($logoPath) ? "<img src='data:image/png;base64," . base64_encode(file_get_contents($logoPath)) . "' width='60'>" : '';

$qrTag = '';
try {
    if (class_exists('\chillerlan\QRCode\QRCode')) {
        $qrUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . '/gg_app/pages/form_umum/scan_action.php?ticket=' . urlencode($ticket);
        $options = new \chillerlan\QRCode\QROptions;
        $options->outputType = \chillerlan\QRCode\Output\QRMarkupSVG::class;
        $options->imageBase64 = true;
        $svgDataUri = (new \chillerlan\QRCode\QRCode($options))->render($qrUrl);
        $qrTag = "<img src='{$svgDataUri}' style='width:70px;height:70px;display:block;margin:0 auto;'>";
    }
} catch (\Throwable $e) {
}

$alasanDisplay = ($row['alasan'] ?? '') === 'Lainnya' && !empty($row['alasan_lain'])
    ? 'Lainnya: ' . $row['alasan_lain']
    : ($row['alasan'] ?? '-');

$ttdCols = [
    ['role' => 'Pemohon', 'header' => 'Pemohon', 'label' => 'Pemohon'],
    ['role' => 'Atasan Pemohon', 'header' => 'Mengetahui', 'label' => 'Atasan Pemohon'],
    ['role' => 'HRD', 'header' => 'Menyetujui', 'label' => 'HRD'],
];

$html = "<html><head><style>
body{font-family:Arial,sans-serif;font-size:10pt;margin:0;padding:0;color:#000;}
table{border-collapse:collapse;width:100%;}
td{vertical-align:top;padding:2px 4px;}
.border{border:1px solid #000;}
.center{text-align:center;}
.bold{font-weight:bold;}
.data-row td{padding:2px 2px;line-height:1.4;}
.data-row td.label-cell{padding:2px 4px 2px 10px;}
.data-row td.data-cell{padding:2px 0;}
.sign td{height:80px;vertical-align:middle;text-align:center;}
</style></head><body>";

$html .= "<table class='border'><tr>";
$html .= "  <td style='width:90px;border-right:1px solid #000;text-align:center;padding:8px;vertical-align:middle;'>{$logoTag}</td>";
$html .= "  <td style='padding:6px 10px;vertical-align:middle;text-align:center;'>";
$html .= "    <div style='font-size:10pt;'>PT. SURYA USAHA MANDIRI</div>";
$html .= "    <div style='font-size:9pt;'>FORMULIR</div>";
$html .= "    <div style='font-size:13pt;font-weight:bold;line-height:1.3;margin-top:2px;'>FORM IZIN PULANG CEPAT</div>";
$html .= "  </td>";
$html .= "  <td style='width:50px;border-left:1px solid #000;padding:4px;text-align:center;vertical-align:middle;'>{$qrTag}</td>";
$html .= "</tr></table>";

$html .= "<table style='width:100%;border-collapse:collapse;border-left:1px solid #000;border-right:1px solid #000;'>";
$html .= "<tr><td style='padding:6px 10px;font-size:10pt;'><b>Nomor Pengajuan:</b> " . ipcPdfVal($row['ticket']) . "</td>";
$html .= "<td style='padding:6px 10px;text-align:right;font-size:10pt;'><b>Tanggal Pengajuan:</b> " . ipcPdfVal(ipcPdfFmtDate($row['tgl_pengajuan'] ?? '')) . "</td></tr>";
$html .= "</table>";

$html .= "<table style='width:100%;border-collapse:collapse;border-left:1px solid #000;border-right:1px solid #000;table-layout:fixed;'>";
$html .= "<tr><td colspan='4' style='padding:6px 10px;font-size:10pt;font-style:italic;'><u>Diberikan kepada tersebut di bawah ini:</u></td></tr>";

$html .= "<tr class='data-row'><td class='label-cell' style='width:20%;'><b>NIK</b></td><td class='data-cell' style='width:30%;'>: " . ipcPdfVal($row['nik']) . "</td><td class='label-cell' style='width:22%;'><b>Jam Pulang Normal</b></td><td class='data-cell' style='width:28%;'>: " . ipcPdfVal($row['jam_pulang_normal']) . "</td></tr>";
$html .= "<tr class='data-row'><td class='label-cell'><b>Nama</b></td><td class='data-cell'>: " . ipcPdfVal($row['nama_pemohon']) . "</td><td class='label-cell'><b>Jam Pulang Diminta</b></td><td class='data-cell'>: " . ipcPdfVal($row['jam_pulang_diminta']) . "</td></tr>";
$html .= "<tr class='data-row'><td class='label-cell'><b>Departemen</b></td><td class='data-cell'>: " . ipcPdfVal($row['departemen']) . "</td><td class='label-cell'><b>Alasan</b></td><td class='data-cell'>: " . ipcPdfVal($alasanDisplay) . "</td></tr>";
$html .= "<tr class='data-row'><td class='label-cell'><b>Bagian</b></td><td class='data-cell'>: " . ipcPdfVal($row['bagian']) . "</td><td class='label-cell'><b>No HP</b></td><td class='data-cell'>: " . ipcPdfVal($row['no_hp']) . "</td></tr>";
$html .= "<tr class='data-row'><td class='label-cell'><b>Jabatan</b></td><td class='data-cell'>: " . ipcPdfVal($row['jabatan']) . "</td><td class='label-cell'><b>Jam Keluar Aktual</b></td><td class='data-cell'>: " . ipcPdfVal($row['jam_keluar_real']) . "</td></tr>";
$html .= "<tr class='data-row'><td class='label-cell' style='padding-bottom:15px;'><b>Tanggal</b></td><td class='data-cell' style='padding-bottom:15px;'>: " . ipcPdfVal(ipcPdfFmtDate($row['tanggal'] ?? $row['tgl_pengajuan'] ?? '')) . "</td><td class='label-cell' style='padding-bottom:15px;'><b>Status Keluar</b></td><td class='data-cell' style='padding-bottom:15px;'>: " . (!empty($row['jam_keluar_real']) ? 'Sudah Keluar' : 'Belum Keluar') . "</td></tr>";
$html .= "</table>";

$html .= "<table class='border center' style='border-top:none;border-collapse:collapse;table-layout:fixed;width:100%;'><colgroup><col style='width:33.33%;'/><col style='width:33.33%;'/><col style='width:33.33%;'/></colgroup>";
$html .= "<tr class='bold' style='font-size:9pt;'>";
foreach ($ttdCols as $col)
    $html .= "<td style='border:1px solid #000;padding:4px;'>{$col['header']}</td>";
$html .= "</tr><tr class='sign'>";
foreach ($ttdCols as $col)
    $html .= "<td style='border:1px solid #000;'>" . ipcPdfSigCell($ttd, $col['role']) . "</td>";
$html .= "</tr><tr class='bold' style='font-size:9pt;'>";
foreach ($ttdCols as $col)
    $html .= "<td style='border:1px solid #000;padding:4px;'>{$col['label']}</td>";
$html .= "</tr></table>";

$html .= "<table cellspacing='0' cellpadding='0' style='width:100%;border-collapse:collapse;table-layout:fixed;font-size:9pt;'><tr><td style='border-left:1px solid #000;border-bottom:1px solid #000;border-right:1px solid #000;padding:4px;text-align:center;font-weight:bold;'>SUM-FM-HRD-011</td><td style='border-right:1px solid #000;border-bottom:1px solid #000;padding:4px;text-align:center;font-weight:bold;'>ERP</td></tr></table>";

if ($qrTag) {
    $html .= "<div style='position:fixed;bottom:20px;right:20px;text-align:center;font-size:7pt;color:#555;'>{$qrTag}<br>Scan Security</div>";
}
$html .= "</body></html>";

$dompdf = new Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf = $dompdf->output();

if (isset($_GET['download']) && $_GET['download'] == '1') {
    ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Form_Izin_Pulang_Cepat_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $ticket) . '.pdf"');
    echo $pdf;
    exit;
}

ob_end_clean();
$b64 = base64_encode($pdf);
$ticketEsc = htmlspecialchars($ticket);
$namaEsc = htmlspecialchars($row['nama_pemohon'] ?? '');
$deptEsc = htmlspecialchars($row['departemen'] ?? '');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Preview PDF – Form Izin Pulang Cepat</title><link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css'><link rel='stylesheet' href='/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css'><style>body{background:#f4f4f4;padding:20px}.container{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.1);max-width:1200px;margin:0 auto}.btn{display:inline-block;padding:8px 16px;border-radius:4px;text-decoration:none;color:#fff;cursor:pointer}.btn-dl{background:#28a745}.btn-back{background:#6c757d;margin-left:8px}object{width:100%;height:800px;border:1px solid #ccc}.ticket-info{background-color:#e7f3ff;border-left:4px solid #2196F3;padding:10px 15px;margin-bottom:20px}.ticket-info p{margin:5px 0}</style><script>function downloadPDF(){window.location='?ticket={$ticketEsc}&download=1';}</script></head><body><div class='container'><h3><i class='fas fa-file-pdf text-danger'></i> Preview Form Izin Pulang Cepat</h3><div class='ticket-info'><p><strong>No. Pengajuan:</strong> {$ticketEsc}</p><p><strong>Pemohon:</strong> {$namaEsc}</p><p><strong>Departemen:</strong> {$deptEsc}</p></div><object type='application/pdf' data='data:application/pdf;base64,{$b64}'></object><div style='margin-top:15px;'><a href='javascript:downloadPDF()' class='btn btn-dl'><i class='fas fa-download'></i> Download PDF</a><a href='list_form.php' class='btn btn-back'><i class='fas fa-arrow-left'></i> Kembali</a></div></div></body></html>";

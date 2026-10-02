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

$ticket = $_GET['ticket'] ?? '';
if ($ticket === '') {
    die('Ticket tidak ditemukan');
}

$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.Form_Serah_Terima_Aplikasi WHERE ticket = ?", [$ticket]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
    die('Data tidak ditemukan');
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

$stmtTtd = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName FROM dbo.Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket]);
$ttd = [];
if ($stmtTtd) {
    while ($row = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
        $ttd[$row['GroupRole']] = $row;
    }
}

function hpdf($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function fmtPdfDate($value)
{
    if ($value instanceof DateTime) return $value->format('d-m-Y');
    return $value ? date('d-m-Y', strtotime($value)) : '-';
}
function cbPdf($checked) { return $checked ? '&#9745;' : '&#9744;'; }
function imgToBase64($path)
{
    if (!$path) return '';
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . str_replace('/gg_app', '/gg_app', $path);
    if (!file_exists($fullPath)) return '';
    $type = pathinfo($fullPath, PATHINFO_EXTENSION);
    return 'data:image/' . $type . ';base64,' . base64_encode(file_get_contents($fullPath));
}
function ttdPdfCell($ttd, $role, $fallback)
{
    $row = $ttd[$role] ?? null;
    $src = $row ? imgToBase64($row['SignaturePath'] ?? '') : '';
    $name = $row['SignedByUserName'] ?? $fallback;
    $img = $src ? '<img src="' . $src . '" style="height:55px;max-width:150px;object-fit:contain;">' : '';
    return '<td style="height:105px;text-align:center;vertical-align:middle;">' . $img . '<br><b>' . hpdf($name) . '</b></td>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; color:#000; font-size:12px; }
table { border-collapse: collapse; width:100%; }
.sheet { border:1px solid #000; }
.section-title { font-weight:bold; margin:10px 0 6px; }
</style>
</head>
<body>
<div class="sheet">
<table>
<tr>
<td style="width:95px;border-right:1px solid #000;padding:8px;text-align:center;"></td>
<td style="padding:8px;line-height:1.35;">
<b style="font-size:15px;">PT. SURYA USAHA MANDIRI</b><br>
Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
Banjaran - Kab. Bandung<br>
40377 Telp. (022) 594-0313
</td>
</tr>
<tr><td colspan="2" style="border-top:1px solid #000;text-align:center;font-weight:bold;padding:8px;font-size:15px;">SERAH TERIMA HASIL PEMBUATAN/PENGEMBANGAN APLIKASI</td></tr>
</table>
<div style="padding:14px 18px;">
<div style="text-align:right;margin-bottom:12px;">' . fmtPdfDate($data['tanggal_serah_terima']) . '</div>
<div class="section-title">1. Informasi Umum</div>
<table>
<tr><td style="width:210px;">Nama Aplikasi</td><td>: ' . hpdf($data['nama_aplikasi']) . '</td></tr>
<tr><td>Nama Modul</td><td>: ' . nl2br(hpdf($data['nama_modul'])) . '</td></tr>
<tr><td>Tanggal Selesai</td><td>: ' . fmtPdfDate($data['tanggal_selesai']) . '</td></tr>
<tr><td>Diminta Oleh (User dan Departemen)</td><td>: ' . hpdf($data['diminta_oleh']) . '</td></tr>
</table>
<div class="section-title">2. Deskripsi Aplikasi</div>
<div style="white-space:pre-line;line-height:1.45;">' . hpdf($data['deskripsi_aplikasi']) . '</div>
<div class="section-title">3. Hasil Pengujian</div>
<div style="margin-left:18px;">
<b>a. Status Testing :</b>
<div>' . cbPdf((int)$data['status_testing_it'] === 1) . ' Sudah diuji oleh IT</div>
<div>' . cbPdf((int)$data['status_testing_user'] === 1) . ' Sudah diuji oleh User</div>
<b>b. Hasil Testing :</b>
<div>' . cbPdf($data['hasil_testing'] === 'sesuai') . ' Sesuai dengan permintaan</div>
<div>' . cbPdf($data['hasil_testing'] === 'revisi') . ' Perlu revisi (jelaskan) :</div>
<div style="min-height:42px;border-bottom:1px dotted #777;white-space:pre-line;">' . hpdf($data['catatan_revisi'] ?? '') . '</div>
</div>
<p style="margin-top:16px;">Dengan ini pihak IT menyerahkan aplikasi yang telah dibuat kepada pihak user, dan user menyatakan bahwa:</p>
<table>
<tr>
<td style="width:54%;vertical-align:top;">
<div>' . cbPdf($data['status_penerimaan'] === 'sesuai') . ' Aplikasi telah sesuai dengan kebutuhan</div>
<div>' . cbPdf($data['status_penerimaan'] === 'catatan') . ' Aplikasi diterima dengan catatan (terlampir)</div>
<div>' . cbPdf($data['status_penerimaan'] === 'perbaikan') . ' Aplikasi masih membutuhkan perbaikan</div>
<div style="margin-top:8px;white-space:pre-line;">' . hpdf($data['catatan_penerimaan'] ?? '') . '</div>
</td>
<td style="width:46%;vertical-align:top;">
<table border="1" style="table-layout:fixed;text-align:center;">
<tr style="font-weight:bold;"><td>Dibuat oleh,</td><td>Diterima oleh,</td><td>Disetujui oleh,</td></tr>
<tr>' . ttdPdfCell($ttd, 'Pemohon', 'IT') . ttdPdfCell($ttd, 'Atasan Pemohon', 'User') . ttdPdfCell($ttd, 'Kabag IT', 'Kabag/Kadept IT') . '</tr>
</table>
</td>
</tr>
</table>
<div style="text-align:center;margin-top:14px;font-size:11px;">SUM-FM-IT-037</div>
</div>
</div>
</body>
</html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('Serah_Terima_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $ticket) . '.pdf', ['Attachment' => false]);

<?php
ob_start();
error_reporting(E_ALL); ini_set('display_errors','0');
session_start();
require '../../vendor/autoload.php';
use Dompdf\Dompdf;
require '../../koneksi.php';

if (!isset($_SESSION['UserName'])) { die('Silakan login terlebih dahulu.'); }
$ticket = isset($_GET['ticket']) ? trim($_GET['ticket']) : '';
if (!$ticket) die('Ticket tidak diberikan');

$sql = "SELECT * FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) die('Data tidak ditemukan');

function fmtDate($d){ if ($d instanceof DateTime) return $d->format('d-m-Y'); if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)){ $p=explode('-',$d); return $p[2].'-'.$p[1].'-'.$p[0]; } return '-'; }
function clean($t,$len=40){ $t=trim($t??''); return $t===""?"<span style='display:inline-block;border-bottom:0.5px solid #000;width:${len}ch;'>&nbsp;</span>":htmlspecialchars($t,ENT_QUOTES,'UTF-8'); }

$ttd = [];
$ttdStmt = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket]);
if ($ttdStmt){ while($r=sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)){ $ttd[$r['GroupRole']]=$r; } }
function sigCell($ttd,$role){ if (!isset($ttd[$role])||empty($ttd[$role]['SignaturePath'])) return '&nbsp;'; $path=$ttd[$role]['SignaturePath']; $signed=htmlspecialchars($ttd[$role]['SignedByUserName']??''); $abs=null; if(file_exists($path)) $abs=$path; elseif(isset($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'].$path)) $abs=$_SERVER['DOCUMENT_ROOT'].$path; elseif(file_exists(__DIR__.'/../../'.ltrim($path,'/'))) $abs=__DIR__.'/../../'.ltrim($path,'/'); if($abs){ $img=base64_encode(file_get_contents($abs)); return "<img src='data:image/png;base64,$img' style='max-height:55px'><br><small>$signed</small>"; } return "<img src='".htmlspecialchars($path)."' style='max-height:55px'><br><small>$signed</small>"; }

$logoPath = "../../dist/img/sumlogo.png"; $logoB64 = file_exists($logoPath)?base64_encode(file_get_contents($logoPath)) : '';
$logoTag = $logoB64?"<img src='data:image/png;base64,$logoB64' width='60'>":"";

    $html = "<html><head><style>
body{font-family:Arial,sans-serif;font-size:10pt;}
table{border-collapse:collapse;width:100%;}
td{vertical-align:top;padding:2px 6px;}
.border{border:1px solid #000;}
.center{text-align:center;}
.bold{font-weight:bold;}
.sign td{height:80px;vertical-align:middle;}
.req-detail{margin-left:0;padding-left:0;}
</style></head><body>";

    // Header
    $html .= "<table class='border' style='border-bottom:none;'><tr>";
    $html .= "<td width='15%' style='border-right:1px solid #000;text-align:center;'>" . $logoTag . "</td>";
    $html .= "<td style='padding-left:5px; line-height:1.3; font-size:9pt;'><b>PT. SURYA USAHA MANDIRI</b><br>Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>Banjaran – Kab. Bandung<br>40377 Telp. (022) 594-0313</td></tr>";
    $html .= "<tr><td colspan='2' class='center bold' style='border-top:1px solid #000;border-bottom:1px solid #000;'>PENGAJUAN AKSES INTERNET</td></tr></table>";

    // Details table start
    $html .= "<table class='border' style='border-top:none; border-bottom:none;'>";
    $html .= "<tr><td width='20%'>Nama Pemohon</td><td colspan='3'>: " . clean($row['nama_pemohon']) . "</td></tr>";
    $html .= "<tr><td style='width:30%;'>Jabatan</td><td style='width:30%;'>: " . clean($row['jabatan']) . "</td><td style='width:20%; text-align:right;'>Tgl Pengajuan</td><td style='width:20%; text-align:right;'>: " . fmtDate($row['tgl_pengajuan']) . "</td></tr>";
    $html .= "<tr><td>Departemen</td><td colspan='3'>: " . clean($row['departemen']) . "</td></tr>";
    $html .= "<tr><td>Area</td><td colspan='3'>: " . clean($row['area']) . "</td></tr>";

    // Mengajukan permintaan untuk (Akses Internet / Penambahan Bandwidth)
    $html .= "<tr><td style='padding:4px 6px;vertical-align:top;'>Mengajukan permintaan untuk</td><td colspan='3' style='padding:4px 6px;vertical-align:top;'>";
    $parts = [];
    if (!empty($row['request_akses_internet'])) {
        $akses = ": <b>Akses Internet</b> — " . htmlspecialchars($row['akses_type'] ?? '-');
        if (!empty($row['akses_temporary_from']) || !empty($row['akses_temporary_to'])) {
            $akses .= "<br>&nbsp;&nbsp;Durasi: " . fmtDate($row['akses_temporary_from']) . " s/d " . fmtDate($row['akses_temporary_to']);
        } elseif (!empty($row['durasi_temporary'])) {
            $akses .= "<br>&nbsp;&nbsp;" . htmlspecialchars($row['durasi_temporary']);
        }
        $parts[] = $akses;
    }
    if (!empty($row['request_tambah_bandwidth']) || !empty($row['tambah_bandwidth'])) {
        $bw = ": <b>Penambahan Bandwidth</b> — " . htmlspecialchars($row['bandwidth_type'] ?? '-');
        if (!empty($row['bandwidth_temporary_from']) || !empty($row['bandwidth_temporary_to'])) {
            $bw .= "<br>&nbsp;&nbsp;Durasi: " . fmtDate($row['bandwidth_temporary_from']) . " s/d " . fmtDate($row['bandwidth_temporary_to']);
        }
        $bw .= "<br>&nbsp;&nbsp;Penambahan: " . htmlspecialchars($row['tambah_bandwidth'] ?? '-') . " " . htmlspecialchars($row['tambah_bandwidth_unit'] ?? '') . "";
        $parts[] = $bw;
    }
    if (empty($parts)) {
        $html .= ": <b>-</b>";
    } else {
        $html .= implode('<br><br>', $parts);
    }
    $html .= "</td></tr>";

    $html .= "<tr><td>Keterangan</td><td colspan='3'>: " . clean($row['keterangan'], 70) . "<br><br><br></td></tr>";
    $html .= "</table>";

    // Signature table
    $html .= "<table class='border center' style='width:100%; border-collapse:collapse;'><tr class='bold'><td width='25%' style='border:1px solid #000; padding:4px;'>Diajukan Oleh</td><td width='25%' style='border:1px solid #000; padding:4px;'>Diketahui Oleh</td><td width='25%' style='border:1px solid #000; padding:4px;'>Disetujui Oleh</td><td width='25%' style='border:1px solid #000; padding:4px;'>Mengetahui</td></tr>";
    $html .= "<tr class='sign'><td style='border:1px solid #000; height:80px; vertical-align:middle;'>" . sigCell($ttd,'Pemohon') . "</td><td style='border:1px solid #000; height:80px; vertical-align:middle;'>" . sigCell($ttd,'Petugas IT') . "</td><td style='border:1px solid #000; height:80px; vertical-align:middle;'>" . sigCell($ttd,'Kadept IT') . "</td><td style='border:1px solid #000; height:80px; vertical-align:middle;'>" . sigCell($ttd,'Kabag IT') . "</td></tr>";
    $html .= "<tr class='bold'><td style='border:1px solid #000; padding:4px;'>Pemohon</td><td style='border:1px solid #000; padding:4px;'>Petugas IT</td><td style='border:1px solid #000; padding:4px;'>Kadept IT</td><td style='border:1px solid #000; padding:4px;'>Kabag IT</td></tr></table>";

    $html .= "<div style='font-size:10pt; padding:10px; border:1px solid #000; border-top:none; border-bottom:none;'><b>Keterangan :</b><ol style='margin:4px 0 0 18px; padding-left:0;'><li>Akses Group seseorang menjadi tanggung jawab department yang bersangkutan dan tidak ada campur tangan dari IT.</li><li>Tidak diperkenankan user memakai browsing ke alamat situs-situs Pornografi, Games Online , sosial networking (streaming video/audio), atau situs-situs yang tidak ada hubungannya dengan pekerjaan sesuai dengan peraturan yang berlaku.</li><li>Semua transaksi log internet tercatat di firewall kami sebagai bahan pengecekan jawaban ke management bila di perlukan suatu saat.</li><li>IT berhak tidak melanjutkan aktivitas download file yang besar koordinasikan ke IT Departemen untuk penggunaan setiap saat.</li><li>Bila ingin melakukan aktivitas download file yang besar koordinasikan ke IT Departemen untuk penggunaan internet tetap dengan baik.</li></ol></div>";

    $html .= "<table style='width:100%; border:1px solid #000;'><tr><td width='70%' class='center bold' style='border-right:1px solid #000; padding:5px;'>SUM-FM-IT-004</td><td width='30%' class='center bold' style='padding:5px;'>NT</td></tr></table>";

    $html .= "</body></html>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf = $dompdf->output();

if (isset($_GET['download']) && $_GET['download'] == '1') {
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="PENGAJUAN AKSES INTERNET - '.$ticket.'.pdf"');
  echo $pdf; exit;
}
ob_end_clean();
$b64 = base64_encode($pdf);
$ticketEsc = htmlspecialchars($ticket);
$namaEsc = htmlspecialchars($row['nama_pemohon']);
$deptEsc = htmlspecialchars($row['departemen']);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Preview PDF Akses Internet</title>
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
<h3><i class='fas fa-file-pdf'></i> Preview Pengajuan Akses Internet</h3>
<div class='ticket-info'>
    <p><strong>No. Pengajuan:</strong> $ticketEsc</p>
    <p><strong>Pemohon:</strong> $namaEsc</p>
    <p><strong>Departemen:</strong> $deptEsc</p>
</div>
<object type='application/pdf' data='data:application/pdf;base64,$b64'></object>
<div style='margin-top:15px;'><a href='javascript:downloadPDF()' class='btn btn-dl'><i class='fas fa-download'></i> Download PDF</a> <a href='javascript:history.back()' class='btn btn-back'><i class='fas fa-arrow-left'></i> Kembali</a></div>
</div></body></html>";
?>

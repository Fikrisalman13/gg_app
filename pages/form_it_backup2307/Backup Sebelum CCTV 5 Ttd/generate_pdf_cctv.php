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

$sql = "SELECT * FROM Form_Pengajuan_CCTV WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) die('Data CCTV tidak ditemukan');

function fmtDate($d){ if ($d instanceof DateTime) return $d->format('d-m-Y'); if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)){ $p=explode('-',$d); return $p[2].'-'.$p[1].'-'.$p[0]; } return '-'; }
function clean($t,$len=40){ $t=trim($t??''); return $t==''?"<span style='display:inline-block;border-bottom:0.5px solid #000;width:${len}ch;'>&nbsp;</span>":htmlspecialchars($t,ENT_QUOTES,'UTF-8'); }

// Ambil tanda tangan (pemohon saja / general) dari tabel TTD generik
$ttd = [];
$ttdStmt = sqlsrv_query($conn, "SELECT GroupRole, SignaturePath, SignedByUserName FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?", [$ticket]);
if ($ttdStmt){ while($r=sqlsrv_fetch_array($ttdStmt, SQLSRV_FETCH_ASSOC)){ $ttd[$r['GroupRole']]=$r; } }
function sigCell($ttd,$role){ if (!isset($ttd[$role])||empty($ttd[$role]['SignaturePath'])) return '&nbsp;'; $path=$ttd[$role]['SignaturePath']; $signed=htmlspecialchars($ttd[$role]['SignedByUserName']??''); $abs=null; if(file_exists($path)) $abs=$path; elseif(isset($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'].$path)) $abs=$_SERVER['DOCUMENT_ROOT'].$path; elseif(file_exists(__DIR__.'/../../'.ltrim($path,'/'))) $abs=__DIR__.'/../../'.ltrim($path,'/'); if($abs){ $img=base64_encode(file_get_contents($abs)); return "<img src='data:image/png;base64,$img' style='max-height:55px'><br><small>$signed</small>"; } return "<img src='".htmlspecialchars($path)."' style='max-height:55px'><br><small>$signed</small>"; }

$logoPath = "../../dist/img/sumlogo.png"; $logoB64 = file_exists($logoPath)?base64_encode(file_get_contents($logoPath)) : '';
$logoTag = $logoB64?"<img src='data:image/png;base64,$logoB64' width='60'>":"";

// SVG Checkmark untuk PDF (menghindari masalah encoding font)
$checkSvg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" fill="none" stroke="black" stroke-width="2"/></svg>');
$checkImg = "<img src='data:image/svg+xml;base64,$checkSvg' width='10' style='vertical-align:middle'>";

$html = "<html><head><style>
body{font-family:Arial,sans-serif;font-size:10pt;}
table{border-collapse:collapse;width:100%;}
td{vertical-align:top;padding:2px 6px;}
.border{border:1px solid #000;}
.center{text-align:center;}
.bold{font-weight:bold;}
.sign td{height:80px;vertical-align:middle;}
.box-check { display:inline-block; width:12px; height:12px; border:1px solid #000; text-align:center; line-height:12px; font-size:10px; margin-right:4px; }
</style></head><body>

  <table class='border' style='border-bottom:none;'>
    <tr>
      <td width='15%' style='border-right:1px solid #000;text-align:center;'>$logoTag</td>
      <td style='padding-left:5px; line-height:1.3; font-size:9pt;'>
        <b>PT. SURYA USAHA MANDIRI</b><br>
        Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
        Banjaran – Kab. Bandung<br>
        40377 Telp. (022) 594-0313
      </td>
    </tr>
    <tr>
      <td colspan='2' class='center bold' style='border-top:1px solid #000;border-bottom:1px solid #000;'>PENGAJUAN PERMINTAAN REKAMAN CCTV</td>
    </tr>
  </table>

  <table class='border' style='border-top:none; border-bottom:none;'>
    <tr>
      <td width='25%'>Nama Pemohon</td>
      <td width='2%'>:</td>
      <td colspan='2'>".clean($row['nama_pemohon'])."</td>
    </tr>
    <tr>
      <td>Jabatan</td>
      <td>:</td>
      <td>".clean($row['jabatan'])."</td>
      <td style='text-align:right; white-space:nowrap;'>Tgl Pengajuan &nbsp;&nbsp; : &nbsp; ".fmtDate($row['tgl_pengajuan'])."</td>
    </tr>
    <tr>
      <td>Departemen</td>
      <td>:</td>
      <td colspan='2'>".clean($row['departemen'])."</td>
    </tr>
    <tr>
      <td>Area</td>
      <td>:</td>
      <td colspan='2'>".clean($row['area'])."</td>
    </tr>
    
   
  </table>

  <div style='border:1px solid #000; border-top:none; border-bottom:none; padding:10px 0;'>
    <div class='center bold' style='font-size:12pt; margin-bottom:5px;'>REKAMAN CCTV</div>
    <table style='width:80%; margin:0 auto;'>
      <tr>
        <td style='text-align:center;'>
          <span class='box-check'>".(($row['tanggal_1']) ? $checkImg : '&nbsp;')."</span>
          Tanggal <span style='border-bottom:1px solid #000; display:inline-block; width:100px;'>".(($row['tanggal_1']) ? fmtDate($row['tanggal_1']) : '&nbsp;')."</span>
          &nbsp; Jam <span style='border-bottom:1px solid #000; display:inline-block; width:60px;'>".clean($row['jam_mulai_1'])."</span>
          s/d <span style='border-bottom:1px solid #000; display:inline-block; width:60px;'>".clean($row['jam_selesai_1'])."</span>
        </td>
      </tr>
      <tr>
        <td style='text-align:center;'>
           <span class='box-check'>".(($row['tanggal_2']) ? $checkImg : '&nbsp;')."</span>
           Tanggal <span style='border-bottom:1px solid #000; display:inline-block; width:100px;'>".(($row['tanggal_2']) ? fmtDate($row['tanggal_2']) : '&nbsp;')."</span>
           &nbsp; Jam <span style='border-bottom:1px solid #000; display:inline-block; width:60px;'>".clean($row['jam_mulai_2'])."</span>
           s/d <span style='border-bottom:1px solid #000; display:inline-block; width:60px;'>".clean($row['jam_selesai_2'])."</span>
        </td>
      </tr>
    </table>
  </div>
  
  <table class='border' style='border-top:none; border-bottom:none;'>
    <tr>
      <td width='25%'>Keterangan</td>
      <td width='2%'>:</td>
      <td>".nl2br(clean($row['keterangan'], 0))."<br><br><br></td>
    </tr>
  </table>
  <table class='border center' style='width:100%; border-collapse:collapse;'>
    <tr class='bold'>
      <td width='25%' style='border:1px solid #000; padding:4px;'>Di Ajukan Oleh</td>
      <td width='25%' style='border:1px solid #000; padding:4px;'>Di Ketahui Oleh</td>
      <td width='25%' style='border:1px solid #000; padding:4px;'>Di Setujui Oleh</td>
      <td width='25%' style='border:1px solid #000; padding:4px;'>Mengetahui</td>
    </tr>
    <tr class='sign'>
      <td style='border:1px solid #000; height:80px; vertical-align:middle;'>".sigCell($ttd,'Pemohon')."</td>
      <td style='border:1px solid #000; height:80px; vertical-align:middle;'>".sigCell($ttd,'Petugas CCTV')."</td>
      <td style='border:1px solid #000; height:80px; vertical-align:middle;'>".sigCell($ttd,'Kabag IT')."</td>
      <td style='border:1px solid #000; height:80px; vertical-align:middle;'>".sigCell($ttd,'Kadept IT')."</td>
    </tr>
    <tr class='bold'>
      <td style='border:1px solid #000; padding:4px;'>Pemohon</td>
      <td style='border:1px solid #000; padding:4px;'>Petugas CCTV</td>
      <td style='border:1px solid #000; padding:4px;'>Kabag IT</td>
      <td style='border:1px solid #000; padding:4px;'>Kadept IT</td>
    </tr>
  </table>

  <div style='font-size:10pt; padding:10px; border:1px solid #000; border-top:none; border-bottom:none;'>
    <b>Perhatian :</b>
    <ol style='margin:4px 0 0 18px; padding-left:0;'>
      <li>Hasil rekaman ini merupakan data rahasia perusahaan tidak bisa di pakai dengan sembarangan apabila di ketahui tidak sesuai prosedur akan dikenakan sangsi sesuai dengan peraturan yang berlaku.</li>
      <li>Sistem penarikan rekaman CCTV ini mempunyai standar penyimpanan selama 14 hari secara nasional.</li>
      <li>Semua harus bertanggungjawab atas penyimpanan hasil rekaman yang di berikan dan hanya di perbolehkan di berikan ke pihak berwenang bila di butuhkan.</li>
      <li>Note : Mengenai penarikan rekaman sebisa mungkin di berikan periode dan jam yang di perlukan utk memepercepat penarikan rekaman CCTV.</li>
    </ol>
  </div>

  <table style='width:100%; border:1px solid #000;'>
    <tr>
      <td width='70%' class='center bold' style='border-right:1px solid #000; padding:5px;'>SUM-FM-IT-010</td>
      <td width='30%' class='center bold' style='padding:5px;'>CCTV</td>
    </tr>
  </table>

</body></html>";

$dompdf = new Dompdf();
$dompdf->loadHtml($html); $dompdf->setPaper('A4','portrait'); $dompdf->render();
$pdf = $dompdf->output();
if (isset($_GET['download']) && $_GET['download']=='1') {
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="PENGAJUAN CCTV - '.$ticket.'.pdf"');
  echo $pdf; exit;
}
ob_end_clean();
$b64 = base64_encode($pdf);
$ticketEsc = htmlspecialchars($ticket);
$namaEsc = htmlspecialchars($row['nama_pemohon']);
$deptEsc = htmlspecialchars($row['departemen']);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Preview PDF CCTV</title>
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
<h3><i class='fas fa-file-pdf'></i> Preview Pengajuan CCTV</h3>
<div class='ticket-info'>
    <p><strong>No. Pengajuan:</strong> $ticketEsc</p>
    <p><strong>Pemohon:</strong> $namaEsc</p>
    <p><strong>Departemen:</strong> $deptEsc</p>
</div>
<object type='application/pdf' data='data:application/pdf;base64,$b64'></object>
<div style='margin-top:15px;'><a href='javascript:downloadPDF()' class='btn btn-dl'><i class='fas fa-download'></i> Download PDF</a> <a href='list_form.php' class='btn btn-back'><i class='fas fa-arrow-left'></i> Kembali</a></div>
</div></body></html>";

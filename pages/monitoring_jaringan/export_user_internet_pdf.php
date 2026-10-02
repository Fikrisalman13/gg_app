<?php
require_once '../../vendor/autoload.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

use Dompdf\Dompdf;

date_default_timezone_set("Asia/Jakarta");

// --- Helper ---
function cleanTarget($t){
    return explode("/", $t)[0];
}

function formatSpeed($bps){
    if(!is_numeric($bps) || $bps <= 0) return '0 Kbps';
    $kb = $bps / 1024;
    if ($kb >= 1024){
        $mb = $kb / 1024;
        return ($mb >= 1024 ? round($mb/1024,2).' Gbps' : round($mb,2).' Mbps');
    }
    return round($kb,2).' Kbps';
}

function formatBytes($bytes){
    if(!is_numeric($bytes) || $bytes <= 0) return '0 KB';
    if ($bytes >= 1073741824) return round($bytes/1073741824,2).' GB';
    if ($bytes >= 1048576) return round($bytes/1048576,2).' MB';
    return round($bytes/1024,2).' KB';
}

// --- GET Params ---
$search = trim($_GET['search'] ?? '');
$perpage = $_GET['perpage'] ?? 'all';

// --- Fetch Mikrotik ---
$API = new RouterosAPI();
$data = [];

if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

    $queues = $API->comm('/queue/simple/print');
    $API->disconnect();

    foreach ($queues as $q) {

        if (isset($q['bytes']) && strpos($q['bytes'], '/') !== false) {
            [$u, $d] = explode('/', $q['bytes']);
        } else {
            $u = 0; $d = 0;
        }

        $q['_upload'] = (int)$u;
        $q['_download'] = (int)$d;
        $q['_total'] = $q['_upload'] + $q['_download'];

        $data[] = $q;
    }

    // Sorting by highest total
    usort($data, fn($a,$b) => ($b['_total'] ?? 0) <=> ($a['_total'] ?? 0));

} else {
    die("Gagal Koneksi Mikrotik");
}

// Filter
if ($search !== '') {
    $s = strtolower($search);
    $data = array_values(array_filter($data, function($q) use ($s) {
        return strpos(strtolower($q['name'] ?? ''), $s) !== false ||
               strpos(strtolower($q['target'] ?? ''), $s) !== false;
    }));
}

// -------------------------------------------
// LOGO Base64 (SAMA seperti file sebelumnya)
// -------------------------------------------
$logoPath = "../../dist/img/sumlogo.png";
$logoB64  = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
$logoTag  = $logoB64 ? "<img src='data:image/png;base64,$logoB64' width='90' height='90'>" : "";

// -------------------------------------------
// HEADER PDF
// -------------------------------------------

$html = '
<style>
    body { font-family: Arial, sans-serif; }
    table { font-size: 12px; }
    th { font-weight:bold; }
</style>

<table width="100%" border="1" cellspacing="0" cellpadding="8" style="border-collapse: collapse;">
<tr>
    <td width="130" align="center">
        '.$logoTag.'
    </td>

    <td>
        <div style="font-size:18px; font-weight:bold; text-transform:uppercase;">
            PT. SURYA USAHA MANDIRI
        </div>
        <div style="font-size:12px; line-height:1.5; margin-top:4px;">
            Jl. Tarajusari No. 8 Kp. Cipendeuy RT 001 RW 007<br>
            Banjaran - Kab. Bandung<br>
            40377 Telp. (022) 594-0313
        </div>
    </td>
</tr>
</table>

<table width="100%" border="1" cellspacing="0" cellpadding="6" 
style="border-collapse: collapse; margin-top:-1px;">
<tr>
    <td align="center" style="
        font-size:18px; 
        font-weight:bold; 
        letter-spacing:1px;
        text-transform:uppercase;
        padding:10px;
    ">
        USER INTERNET
    </td>
</tr>
</table>

<br>
';

// -------------------------------------------
// TABLE DATA
// -------------------------------------------
$html .= '
<table width="100%" border="1" cellspacing="0" cellpadding="5" 
style="border-collapse: collapse; font-size:12px;">
<thead>
<tr style="background:#eee;">
    <th>No</th>
    <th>Nama</th>
    <th>Target</th>
    <th>Upload Max</th>
    <th>Download Max</th>
    <th>Upload Avg</th>
    <th>Download Avg</th>
    <th>Upload</th>
    <th>Download</th>
</tr>
</thead>
<tbody>
';

$no = 1;
foreach ($data as $q){
    [$mu,$md] = explode('/', $q['max-limit'] ?? '0/0');
    [$au,$ad] = explode('/', $q['rate'] ?? '0/0');

    $html .= '
    <tr>
        <td>'.$no++.'</td>
        <td>'.htmlspecialchars($q['name'] ?? '-').'</td>
        <td>'.cleanTarget($q['target'] ?? '-').'</td>
        <td>'.formatSpeed($mu).'</td>
        <td>'.formatSpeed($md).'</td>
        <td>'.formatSpeed($au).'</td>
        <td>'.formatSpeed($ad).'</td>
        <td>'.formatBytes($q['_upload']).'</td>
        <td>'.formatBytes($q['_download']).'</td>
    </tr>';
}

$html .= '</tbody></table>';

// -------------------------------------------
// DOMPDF OUTPUT
// -------------------------------------------
$dompdf = new Dompdf(["isRemoteEnabled" => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("User_Internet_".date('Ymd_His').".pdf", ["Attachment" => true]);
exit;

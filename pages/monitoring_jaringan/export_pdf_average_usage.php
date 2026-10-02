<?php
require_once '../../vendor/autoload.php';
require_once '../../koneksi.php';

use Dompdf\Dompdf;

date_default_timezone_set("Asia/Jakarta");

// ===============================
// GET PARAMETER
// ===============================
$search = trim($_GET['search'] ?? '');
$start  = $_GET['start'] ?? '';
$end    = $_GET['end'] ?? '';

$params = [];
$whereParts = [];

// ===============================
// FILTER SEARCH
// ===============================
if ($search !== "") {
    $whereParts[] = "(LOWER(name) LIKE ? OR LOWER(target) LIKE ?)";
    $params[] = "%" . strtolower($search) . "%";
    $params[] = "%" . strtolower($search) . "%";
}

// ===============================
// FILTER TANGGAL (PATCHED)
// ===============================
$isValidDate = (!empty($start) && !empty($end));   // <-- FIXED

if ($isValidDate) {

    $whereParts[] = "CAST([date] AS DATE) BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;

    // HITUNG TOTAL HARI
    $totalHari = (strtotime($end) - strtotime($start)) / 86400 + 1;
    if ($totalHari < 1) $totalHari = 0;

} else {
    $totalHari = 0;
}

// Build where SQL
$whereSQL = "";
if (!empty($whereParts)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereParts);
}

// ===============================
// QUERY DATA
// ===============================

$sql = "
SELECT 
    name,
    target,
    SUM(upload) AS total_upload,
    SUM(download) AS total_download,
    SUM(total) AS total_total
FROM monitoring_jaringan
$whereSQL
GROUP BY name, target
ORDER BY total_total DESC
";

$stmt = sqlsrv_query($conn, $sql, $params);
$data = [];

if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = $r;
    }
}

// ===============================
// FORMAT BYTE
// ===============================
function fmtBytes($b){
    if (!is_numeric($b)) return "0";
    if ($b >= 1073741824) return round($b/1073741824,2)." GB";
    if ($b >= 1048576) return round($b/1048576,2)." MB";
    if ($b >= 1024) return round($b/1024,2)." KB";
    return $b . " B";
}

// ===============================
// LOGO BASE64
// ===============================
$logoPath = "../../dist/img/sumlogo.png";
$logoB64  = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
$logoTag  = $logoB64 ? "<img src='data:image/png;base64,$logoB64' width='90'>" : "";

// ===============================
// TANGGAL INDONESIA
// ===============================
function tanggalIndo($tgl){
    if (!$tgl) return "-";
    $bln = [
        1=>"Januari",2=>"Februari",3=>"Maret",4=>"April",5=>"Mei",6=>"Juni",
        7=>"Juli",8=>"Agustus",9=>"September",10=>"Oktober",11=>"November",12=>"Desember"
    ];
    $p = explode("-", $tgl);
    return intval($p[2])." ".$bln[intval($p[1])]." ".$p[0];
}

$periodeText = ($isValidDate)
    ? tanggalIndo($start)." s/d ".tanggalIndo($end)
    : "-";

// ===============================
// HTML PDF
// ===============================
$html = '
<style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; font-size: 12px; }
    th { background:#eee; font-weight:bold; text-align:center; }
    td,th { border:1px solid #444; padding:5px; }
</style>

<table width="100%" border="1" cellspacing="0" cellpadding="6">
<tr>
    <td width="120" align="center">'.$logoTag.'</td>
    <td>
        <div style="font-size:18px; font-weight:bold;">PT. SURYA USAHA MANDIRI</div>
        <div style="font-size:12px; line-height:1.4;">
            Jl. Tarajusari No. 8 Kp. Cipendeuy RT 001 RW 007<br>
            Banjaran - Kab. Bandung<br>
            40377 Telp. (022) 594-0313
        </div>
    </td>
</tr>
</table>

<table width="100%" border="1" cellspacing="0" cellpadding="6" style="margin-top:-1px;">
<tr>
    <td align="center" style="font-size:15px; font-weight:bold;">
        LAPORAN PEMAKAIAN USER INTERNET
    </td>
</tr>
</table>

<br>
<b>Periode :</b> '.$periodeText.'<br><br>
';

// ===============================
// TABLE DATA
// ===============================

$html .= '
<table width="100%">
<thead>
<tr>
    <th>No</th>
    <th>Nama</th>
    <th>Target</th>
    <th>Upload</th>
    <th>Download</th>
    <th>Total</th>
    <th>Total Hari</th>
</tr>
</thead>
<tbody>
';


$no = 1;
foreach ($data as $r){
    $html .= "
    <tr>
        <td align='center'>".$no++."</td>
        <td>".htmlspecialchars($r['name'])."</td>
        <td>".htmlspecialchars($r['target'])."</td>
        <td>".fmtBytes($r['total_upload'])."</td>
        <td>".fmtBytes($r['total_download'])."</td>
        <td>".fmtBytes($r['total_total'])."</td>
        <td align='center'>".intval($totalHari)." Hari</td>
    </tr>
    ";
}

$html .= "</tbody></table>";

// ===============================
// GENERATE PDF
// ===============================
$dompdf = new Dompdf(["isRemoteEnabled" => true]);
$dompdf->loadHtml($html);
$dompdf->setPaper("A4", "landscape");
$dompdf->render();
$dompdf->stream("Average_Usage_".date("Ymd_His").".pdf", ["Attachment"=>true]);

exit;

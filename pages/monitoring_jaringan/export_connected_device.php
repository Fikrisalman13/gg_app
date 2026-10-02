<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../routeros_api.class.php';

use Dompdf\Dompdf;

// ======================================================
// Helper: Device Normalization
// ======================================================
function normalizeDevice($name)
{
    $n = strtolower($name ?? '');

    if (str_contains($n, 'hp') || str_contains($n, 'android') || str_contains($n, 'iphone'))
        return "HP";

    if (str_contains($n, 'laptop') || str_contains($n, 'notebook'))
        return "Laptop";

    if (str_contains($n, 'pc') || str_contains($n, 'desktop'))
        return "PC";

    if (str_contains($n, 'tablet') || str_contains($n, 'ipad') || str_contains($n, 'tab'))
        return "Tablet";

    if (str_contains($n, 'server'))
        return "Server";

    if (str_contains($n, 'printer') || str_contains($n, 'print'))
        return "Printer";

    if (str_contains($n, 'mesin') || str_contains($n, 'scan') || str_contains($n, 'absen'))
        return "Mesin";

    if (str_contains($n, 'cctv') || str_contains($n, 'nvr') || str_contains($n, 'dvr'))
        return "CCTV";

    return "Unknown";
}

// ======================================================
// Fetch queue from Mikrotik
// ======================================================
$API = new RouterosAPI();
$dataGrouped = [];

if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
    $rows = $API->comm("/queue/simple/print", ["stats" => ""]);
    $API->disconnect();
} else {
    $rows = [];
}

// Group per device
foreach ($rows as $r) {

    $name = $r['name'] ?? '-';
    $device = normalizeDevice($name);
    $ip = explode("/", $r['target'] ?? '-')[0];

    // For online/offline
    $rate = $r['rate'] ?? "0/0";
    list($downBps, $upBps) = explode("/", $rate);

    $download = intval($downBps) * 8;
    $upload   = intval($upBps) * 8;

    $status = ($download > 0 || $upload > 0) ? "Online" : "Offline";

    $dataGrouped[$device][] = [
        "name" => $name,
        "ip" => $ip,
        "status" => $status
    ];
}

ksort($dataGrouped);

// ======================================================
// Build PDF HTML
// ======================================================

// Ambil logo FIX: dist/img/sumlogo.png
$logoPath = realpath(__DIR__ . '/../../dist/img/sumlogo.png');
$logoHtml = '';

if ($logoPath && file_exists($logoPath)) {
    $logoBase64 = base64_encode(file_get_contents($logoPath));
    $logoHtml = '<img src="data:image/png;base64,' . $logoBase64 . '" width="90">';
}

$html = '
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size:12px; }
h2, h3 { margin:2px 0; padding:0; }
.header-table { width:100%; border:1px solid #444; border-collapse: collapse; }
.header-table td { border:1px solid #444; padding:6px; }
.title-box { text-align:center; border:1px solid #444; padding:6px; font-weight:bold; margin-top:-5px; }
.device-title { background:#eaeaea; padding:6px; margin-top:12px; font-weight:bold; font-size:14px; }
table { width:100%; border-collapse: collapse; margin-top:5px; }
th, td { border:1px solid #444; padding:6px; }
th { background:#f2f2f2; font-weight:bold; }
.status-online { color:green; font-weight:bold; }
.status-offline { color:red; font-weight:bold; }
</style>
</head>

<body>

<table class="header-table">
<tr>
    <td style="width:100px; text-align:center;">' . $logoHtml . '</td>
    <td style="font-size:12px;">
        <strong>PT. SURYA USAHA MANDIRI</strong><br>
        Jl. Tarajusari No. 8 Kp. Cipendeuy RT 001 RW 007<br>
        Banjaran – Kab. Bandung<br>
        40377 Telp. (022) 594-0313
    </td>
</tr>
</table>

<div class="title-box">
    DAFTAR PERANGKAT TERHUBUNG INTERNET
</div>

';

// ======================================================
// Print grouping per device
// ======================================================
foreach ($dataGrouped as $device => $items) {
    $html .= '
    <div class="device-title">' . htmlspecialchars($device) . ' (' . count($items) . ')</div>

    <table>
        <thead>
        <tr>
            <th style="width:5%;">No</th>
            <th style="width:45%;">Nama</th>
            <th style="width:25%;">IP Address</th>
            <th style="width:25%;">Status</th>
        </tr>
        </thead>
        <tbody>
    ';

    $no = 1;
    foreach ($items as $row) {

        $statusClass = ($row['status'] === "Online") ? "status-online" : "status-offline";

        $html .= '
        <tr>
            <td style="text-align:center;">' . $no . '</td>
            <td>' . htmlspecialchars($row['name']) . '</td>
            <td style="text-align:center;">' . htmlspecialchars($row['ip']) . '</td>
            <td style="text-align:center;"><span class="' . $statusClass . '">' . $row['status'] . '</span></td>
        </tr>';

        $no++;
    }

    $html .= '</tbody></table>';
}

$html .= '</body></html>';

// ======================================================
// Generate PDF
// ======================================================
$dompdf = new Dompdf();
$dompdf->set_option("isRemoteEnabled", true);
$dompdf->loadHtml($html);
$dompdf->setPaper("A4", "portrait");
$dompdf->render();

// === FILE NAME FORMAT ===
// Connected_Device_ddmmyyyy.pdf
$today = date("dmY");
$fileName = "Connected_Device_" . $today . ".pdf";

$dompdf->stream($fileName, ["Attachment" => true]);
exit;

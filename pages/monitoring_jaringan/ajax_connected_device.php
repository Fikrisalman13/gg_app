<?php
// ======================================================
// ajax_connected_device.php
// AJAX handler untuk DataTables server-side processing
// ======================================================
session_start();

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');

// ======================================================
// REQUIRE FILES
// ======================================================
require_once '../../koneksi.php';
require_once '../../config.php';
require_once '../../routeros_api.class.php';

// ======================================================
// HELPER FUNCTIONS
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

function cleanName($name)
{
    return trim(preg_replace('/port[_ ]?\d+/i', '', $name));
}

function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ======================================================
// FETCH DATA FROM MIKROTIK
// ======================================================
$leases = [];
$errorMessage = null;

try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {

        $rows = $API->comm("/queue/simple/print", ["stats" => ""]);
        $API->disconnect();

        foreach ($rows as $r) {

            $rawName = $r['name'] ?? '-';
            $name = cleanName($rawName);

            $device = normalizeDevice($rawName);

            $target = explode("/", $r['target'] ?? "-")[0];

            // RATE format = "download/upload" (BYTE PER SECOND)
            $rate = $r['rate'] ?? "0/0";
            list($downRateBps, $upRateBps) = explode("/", $rate);

            // Convert to bps
            $downloadAvg = intval($downRateBps) * 8;
            $uploadAvg   = intval($upRateBps) * 8;

            $status = ($downloadAvg > 0 || $uploadAvg > 0) ? "Online" : "Offline";

            $leases[] = [
                'name' => $name,
                'device' => $device,
                'ip' => $target,
                'uploadAvg' => $uploadAvg,
                'downloadAvg' => $downloadAvg,
                'status' => $status
            ];
        }
    } else {
        $errorMessage = "Tidak dapat terhubung ke Mikrotik.";
    }
} catch (Exception $ex) {
    $errorMessage = "Error: " . $ex->getMessage();
}

// ======================================================
// GET DATATABLES PARAMETERS
// ======================================================
$draw = intval($_GET['draw'] ?? 1);
$start = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 25);

// Filter parameters
$deviceFilter = $_GET['device_filter'] ?? '';
$statusFilter = $_GET['status_filter'] ?? '';
$searchDevice = $_GET['search_device'] ?? '';

// ======================================================
// APPLY FILTERS
// ======================================================
$data = $leases;

// Filter by device
if ($deviceFilter !== '') {
    $data = array_filter($data, fn($x) => $x['device'] === $deviceFilter);
}

// Filter by status
if ($statusFilter !== '') {
    $data = array_filter($data, fn($x) => $x['status'] === $statusFilter);
}

// Search filter
if ($searchDevice !== '') {
    $s = strtolower($searchDevice);
    $data = array_filter($data, function($r) use ($s) {
        return str_contains(strtolower($r['name']), $s)
            || str_contains(strtolower($r['ip']), $s)
            || str_contains(strtolower($r['device']), $s);
    });
}

// Reset array keys after filtering
$data = array_values($data);
$recordsFiltered = count($data);
$recordsTotal = count($leases);

// ======================================================
// PAGINATION
// ======================================================
$displayData = array_slice($data, $start, $length);

// ======================================================
// BUILD RESPONSE
// ======================================================
$response = [
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => []
];

$no = $start + 1;
foreach ($displayData as $row) {
    // Determine status badge
    $statusBadge = $row['status'] === 'Online' 
        ? '<span class="status-online">Online</span>'
        : '<span class="status-offline">Offline</span>';

    $response['data'][] = [
        $no++,
        e($row['name']),
        e($row['device']),
        e($row['ip']),
        number_format($row['uploadAvg']) . ' bps',
        number_format($row['downloadAvg']) . ' bps',
        $statusBadge
    ];
}

// ======================================================
// SEND JSON RESPONSE
// ======================================================
header('Content-Type: application/json');
echo json_encode($response);
?>
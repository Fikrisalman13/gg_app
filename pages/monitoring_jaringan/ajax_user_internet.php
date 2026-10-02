<?php
// ======================================================
// ajax_user_internet.php
// AJAX handler untuk DataTables server-side processing
// ======================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 🔑 LEPASKAN SESSION AGAR AJAX TIDAK BENTROK
session_write_close();


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
function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function cleanTarget($target) {
    return explode('/', $target)[0];
}

function formatSpeed($bps) {
    if (!is_numeric($bps) || $bps <= 0) return '0 Kbps';
    $kb = $bps / 1024;
    if ($kb >= 1024) {
        $mb = $kb / 1024;
        return ($mb >= 1024) ? round($mb/1024, 2).' Gbps' : round($mb, 2).' Mbps';
    }
    return round($kb, 2).' Kbps';
}

function formatBytes($bytes) {
    if (!is_numeric($bytes) || $bytes <= 0) return '0 KB';
    if ($bytes >= 1073741824) return round($bytes/1073741824, 2).' GB';
    if ($bytes >= 1048576) return round($bytes/1048576, 2).' MB';
    return round($bytes/1024, 2).' KB';
}

// ======================================================
// FETCH DATA FROM MIKROTIK
// ======================================================
$queues = [];
$errorMessage = null;

try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
        $rawQueues = $API->comm('/queue/simple/print');
        $API->disconnect();

        foreach ($rawQueues as $q) {
            if (isset($q['bytes']) && strpos($q['bytes'], '/') !== false) {
                [$uBytes, $dBytes] = explode('/', $q['bytes']);
            } else {
                $uBytes = 0;
                $dBytes = 0;
            }

            $uVal = (int)$uBytes;
            $dVal = (int)$dBytes;

            $q['_upload'] = $uVal;
            $q['_download'] = $dVal;
            $q['_total'] = $uVal + $dVal;

            $queues[] = $q;
        }

        usort($queues, fn($a, $b) => ($b['_total'] ?? 0) <=> ($a['_total'] ?? 0));

    } else {
        $errorMessage = 'Gagal koneksi ke Mikrotik';
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

// If error, return error response
if ($errorMessage) {
    http_response_code(500);
    echo json_encode(['error' => $errorMessage]);
    exit;
}

// ======================================================
// GET DATATABLES PARAMETERS
// ======================================================
$draw = intval($_GET['draw'] ?? 1);
$start = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 25);

// Filter parameter
$searchUser = $_GET['search_user'] ?? '';

// ======================================================
// APPLY FILTERS
// ======================================================
$data = $queues;

// Search filter
if ($searchUser !== '') {
    $s = mb_strtolower($searchUser, 'UTF-8');
    $data = array_filter($data, function($q) use ($s) {
        $name = mb_strtolower($q['name'] ?? '-', 'UTF-8');
        $target = mb_strtolower($q['target'] ?? '-', 'UTF-8');
        return (mb_stripos($name, $s, 0, 'UTF-8') !== false)
            || (mb_stripos($target, $s, 0, 'UTF-8') !== false);
    });
}

// Reset array keys after filtering
$data = array_values($data);
$recordsFiltered = count($data);
$recordsTotal = count($queues);

// ======================================================
// PAGINATION
// ======================================================
$displayData = array_slice($data, $start, $length);

// ======================================================
// BUILD RESPONSE
// ======================================================

// Ambil update_by dari database user_internet
$updateByMap = [];
$sql = "SELECT target_ip, update_by FROM dbo.user_internet";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $updateByMap[trim($row['target_ip'])] = $row['update_by'] ?? '';
    }
    sqlsrv_free_stmt($stmt);
}

$response = [
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => []
];

$no = $start + 1;
foreach ($displayData as $q) {
    [$mu, $md] = explode('/', $q['max-limit'] ?? '0/0');
    [$au, $ad] = explode('/', $q['rate'] ?? '0/0');
    $targetIp = cleanTarget($q['target'] ?? '-');
    $updateBy = $updateByMap[$targetIp] ?? '';

    $response['data'][] = [
        $no++,
        e($q['name'] ?? '-'),
        e($targetIp),
        e(formatSpeed($mu)),
        e(formatSpeed($md)),
        e(formatBytes($q['_upload'])),
        e(formatBytes($q['_download'])),
        e($updateBy),
        ''
    ];
}

// ======================================================
// SEND JSON RESPONSE
// ======================================================
header('Content-Type: application/json');
echo json_encode($response);
?>

<?php
// ==========================================================
// user_internet_serverside.php
// DataTables Server-Side for user_internet.php
// ==========================================================

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../routeros_api.class.php';

header('Content-Type: application/json');

// ================================================
// CEK LOGIN
// ================================================
if (!isset($_SESSION['UserName'])) {
    echo json_encode([
        "draw" => 0,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

// ================================================
// HELPER FUNCTIONS
// ================================================
function convertRateToKbps($value) {
    if (!$value || $value === "0") return 0;
    $v = strtolower(trim($value));

    // Jika langsung angka besar (bps)
    if (is_numeric($v)) return round($v / 1024, 2);

    $num = floatval($v);
    if (str_ends_with($v, "k")) return $num;
    if (str_ends_with($v, "m")) return $num * 1024;
    if (str_ends_with($v, "g")) return $num * 1024 * 1024;

    return 0;
}

function parseBytes($s) {
    if (!str_contains($s, "/")) return [0, 0];
    list($u, $d) = explode("/", $s);
    return [(int)$u, (int)$d];
}

function parseRate($s) {
    if (!str_contains($s, "/")) return [0, 0];
    list($u, $d) = explode("/", $s);
    return [
        convertRateToKbps($u),
        convertRateToKbps($d)
    ];
}

function cleanTargetIP($target) {
    if (!$target) return '-';
    if (str_contains($target, "/")) {
        $parts = explode("/", $target);
        return trim($parts[0]);
    }
    return $target;
}

// ================================================
// DATATABLES PARAMETER
// ================================================
$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

$searchValue = trim($_POST['search']['value'] ?? "");

// FILTERS
$filter_name      = trim($_POST['filter_name'] ?? "");
$filter_target    = trim($_POST['filter_target'] ?? "");
$filter_min_up    = floatval($_POST['filter_min_up'] ?? 0);
$filter_min_down  = floatval($_POST['filter_min_down'] ?? 0);
$filter_min_bytes = floatval($_POST['filter_min_bytes'] ?? 0); // MB

// ================================================
// FETCH DATA FROM MIKROTIK
// ================================================
$data = [];
$error = null;

try {
    $API = new RouterosAPI();
    $API->debug = false;

    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Tidak dapat terhubung ke Mikrotik");
    }

    $queues = $API->comm('/queue/simple/print');
    $API->disconnect();

    foreach ($queues as $q) {

        // Bytes
        list($uBytes, $dBytes) = parseBytes($q['bytes'] ?? "0/0");
        $totalBytes = $uBytes + $dBytes;

        // Rate Avg
        list($avgUp, $avgDown) = parseRate($q['rate'] ?? "0/0");

        // Max-limit
        $maxLimit = $q['max-limit'] ?? "0/0";
        list($maxU, $maxD) = str_contains($maxLimit, "/")
            ? explode("/", $maxLimit)
            : [$maxLimit, "0"];

        $cleanTarget = cleanTargetIP($q['target'] ?? "-");

        $data[] = [
            "name" => $q['name'] ?? "-",
            "target" => $cleanTarget,
            "max_upload" => $maxU,
            "max_download" => $maxD,
            "avg_upload_kbps" => round($avgUp, 2),
            "avg_download_kbps" => round($avgDown, 2),
            "upload_bytes" => $uBytes,
            "download_bytes" => $dBytes,
            "total_bytes" => $totalBytes
        ];
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// ================================================
// JIKA ERROR
// ================================================
if ($error) {
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => $error
    ]);
    exit;
}

$recordsTotal = count($data);

// ================================================
// APPLY FILTERS
// ================================================

// FILTER GLOBAL SEARCH
if ($searchValue !== "") {
    $s = strtolower($searchValue);
    $data = array_filter($data, function($r) use ($s) {
        return str_contains(strtolower($r["name"]), $s)
            || str_contains(strtolower($r["target"]), $s)
            || str_contains((string)$r["upload_bytes"], $s)
            || str_contains((string)$r["download_bytes"], $s)
            || str_contains((string)$r["total_bytes"], $s);
    });
}

// FILTER NAMA
if ($filter_name !== "") {
    $f = strtolower($filter_name);
    $data = array_filter($data, fn($r) => str_contains(strtolower($r["name"]), $f));
}

// FILTER TARGET
if ($filter_target !== "") {
    $f = strtolower($filter_target);
    $data = array_filter($data, fn($r) => str_contains(strtolower($r["target"]), $f));
}

// MIN UPLOAD AVG
if ($filter_min_up > 0) {
    $data = array_filter($data, fn($r) => $r["avg_upload_kbps"] >= $filter_min_up);
}

// MIN DOWNLOAD AVG
if ($filter_min_down > 0) {
    $data = array_filter($data, fn($r) => $r["avg_download_kbps"] >= $filter_min_down);
}

// MIN TOTAL BYTES (MB)
if ($filter_min_bytes > 0) {
    $minBytes = $filter_min_bytes * 1024 * 1024;
    $data = array_filter($data, fn($r) => $r["total_bytes"] >= $minBytes);
}

$data = array_values($data);
$recordsFiltered = count($data);

// ================================================
// SORTING
// ================================================
$orderColumn = intval($_POST['order'][0]['column'] ?? 9);
$orderDir = $_POST['order'][0]['dir'] ?? "desc";

$colMap = [
    0 => null,
    1 => "name",
    2 => "target",
    3 => "max_upload",
    4 => "max_download",
    5 => "avg_upload_kbps",
    6 => "avg_download_kbps",
    7 => "upload_bytes",
    8 => "download_bytes",
    9 => "total_bytes"
];

$sortKey = $colMap[$orderColumn] ?? "total_bytes";

if ($sortKey !== null) {
    usort($data, function($a, $b) use ($sortKey, $orderDir) {
        if ($a[$sortKey] == $b[$sortKey]) return 0;
        if ($orderDir === "asc") return ($a[$sortKey] < $b[$sortKey]) ? -1 : 1;
        return ($a[$sortKey] > $b[$sortKey]) ? -1 : 1;
    });
}

// ================================================
// PAGING
// ================================================
$dataPage = array_slice($data, $start, $length);

// ================================================
// OUTPUT JSON
// ================================================
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => array_values($dataPage)
], JSON_UNESCAPED_SLASHES);

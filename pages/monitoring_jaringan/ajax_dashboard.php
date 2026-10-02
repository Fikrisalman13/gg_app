<?php
// ajax_dashboard.php
// Return Top 10 Pengguna Internet berdasarkan filter tanggal (periode) untuk dashboard.php (AJAX)

session_start();
if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../koneksi.php';

function formatBytes($bytes) {
    if (!is_numeric($bytes) || $bytes <= 0) return '0 B';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';
$whereSQL = "";
$params = [];
if ($start && $end) {
    $whereSQL = "WHERE CAST([date] AS DATE) BETWEEN ? AND ?";
    $params = [$start, $end];
} elseif ($start) {
    $whereSQL = "WHERE CAST([date] AS DATE) >= ?";
    $params = [$start];
} elseif ($end) {
    $whereSQL = "WHERE CAST([date] AS DATE) <= ?";
    $params = [$end];
}

$sql = "
SELECT TOP 10 name, SUM(upload+download) AS total_usage
FROM monitoring_jaringan
$whereSQL
GROUP BY name
ORDER BY total_usage DESC
";
$stmt = sqlsrv_query($conn, $sql, $params);

$data = [];
if ($stmt) {
    $i = 1;
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            'no' => $i++,
            'name' => $r['name'],
            'total' => formatBytes($r['total_usage'] ?? 0)
        ];
    }
}
header('Content-Type: application/json');
echo json_encode(['data' => $data]);

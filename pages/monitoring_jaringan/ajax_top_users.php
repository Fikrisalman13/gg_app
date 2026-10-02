<?php
// ajax_top_users.php
// Return Top 10 Pengguna Internet berdasarkan filter tanggal (periode)

session_start();
if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../koneksi.php';

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
            'total' => round(($r['total_usage'] ?? 0) / 1024 / 1024, 2)
        ];
    }
}
header('Content-Type: application/json');
echo json_encode(['data' => $data]);

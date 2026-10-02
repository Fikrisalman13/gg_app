<?php
// Server-side endpoint for DataTables for average_usage.php
// Returns JSON: draw, recordsTotal, recordsFiltered, data: [ [no, date, name, target, upload, download, total], ... ]

require_once '../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');

// helper functions
function formatBytes($bytes) {
    if (!is_numeric($bytes)) return "0 B";
    if ($bytes >= 1073741824) return number_format($bytes/1073741824, 2)." GB";
    if ($bytes >= 1048576) return number_format($bytes/1048576, 2)." MB";
    if ($bytes >= 1024) return number_format($bytes/1024, 2)." KB";
    return $bytes . " B";
}

function json_error($msg) {
    http_response_code(500);
    echo json_encode(['error'=>$msg]);
    exit;
}

$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 0;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 25;
$search = trim($_GET['search_average'] ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Jika filter tanggal belum dipilih, return data kosong
if (empty($start_date) || empty($end_date)) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

$params = [];
$whereParts = [];

if ($search !== '') {
    $whereParts[] = "(LOWER(name) LIKE ? OR LOWER(target) LIKE ?)";
    $params[] = "%".strtolower($search)."%";
    $params[] = "%".strtolower($search)."%";
}
if (!empty($start_date) && !empty($end_date)) {
    $whereParts[] = "(CAST([date] AS DATE) BETWEEN ? AND ?)";
    $params[] = $start_date;
    $params[] = $end_date;
}

$whereSQL = (!empty($whereParts)) ? "WHERE " . implode(" AND ", $whereParts) : "";

// Get the sum of all data for the selected period
$sum_sql = "
    SELECT 
        SUM(upload) AS up, 
        SUM(download) AS dn, 
        SUM(total) AS tot
    FROM monitoring_jaringan
    $whereSQL
";
$sum_stmt = sqlsrv_query($conn, $sum_sql, $params);
$sum = $sum_stmt ? sqlsrv_fetch_array($sum_stmt, SQLSRV_FETCH_ASSOC) : ['up'=>0,'dn'=>0,'tot'=>0];

// If summary-only request, return just summary for summary box
if (isset($_GET['summary']) && $_GET['summary'] == '1') {
    echo json_encode([
        'up' => formatBytes($sum['up']),
        'dn' => formatBytes($sum['dn']),
        'tot' => formatBytes($sum['tot'])
    ]);
    exit;
}

// Get the date range for display
$date_range = "";
if (!empty($start_date) && !empty($end_date)) {
    $date_range = date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
}

// Get unique user count for the filtered data
$count_sql = "SELECT COUNT(DISTINCT name) AS cnt FROM monitoring_jaringan $whereSQL";
$count_stmt = sqlsrv_query($conn, $count_sql, $params);
if ($count_stmt === false) json_error('Gagal menghitung total data');
$row = sqlsrv_fetch_array($count_stmt, SQLSRV_FETCH_ASSOC);
$recordsFiltered = $recordsTotal = $row ? intval($row['cnt']) : 0;

// Handle ordering (note: we aggregate per user, so use aggregate expressions)
$orderBy = $_GET['orderBy'] ?? 'total'; // Default sort by total
$orderDir = strtoupper($_GET['orderDir'] ?? 'DESC');

// Map logical column names from DataTables to SQL expressions used in SELECT
// For numeric columns we sort by SUM(...), for date we sort by MIN(date) via alias first_date
$validColumns = [
    'date'     => 'first_date',          // MIN([date]) AS first_date
    'name'     => 'name',
    'target'   => 'target',
    'upload'   => 'SUM(upload)',
    'download' => 'SUM(download)',
    'total'    => 'SUM(total)'
];

// Validate orderBy parameter and fall back to total sum
$orderBy = $validColumns[$orderBy] ?? 'SUM(total)';
$orderDir = in_array($orderDir, ['ASC', 'DESC']) ? $orderDir : 'DESC';

// Special handling for numeric columns (those using SUM(...)) to sort by numeric value
if (in_array($orderBy, ['SUM(upload)', 'SUM(download)', 'SUM(total)'])) {
    $orderClause = "ORDER BY CAST($orderBy AS BIGINT) $orderDir";
} else {
    // name, target, first_date (alias) etc.
    $orderClause = "ORDER BY $orderBy $orderDir";
}

// limit
$limitClause = "OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$paramsForData = array_merge($params, [$start, $length]);

// Get the sum of data grouped by user for the selected period
$data_sql = "
    SELECT 
        name, 
        target, 
        SUM(upload) AS upload, 
        SUM(download) AS download, 
        SUM(total) AS total,
        MIN([date]) AS first_date,
        MAX([date]) AS last_date,
        COUNT(*) AS days_count
    FROM monitoring_jaringan
    $whereSQL
    GROUP BY name, target
    $orderClause
    $limitClause
";

// Debug SQL (uncomment if needed)
// file_put_contents('debug_sql.txt', $data_sql . "\n" . print_r($paramsForData, true));

$data_stmt = sqlsrv_query($conn, $data_sql, $paramsForData);
if ($data_stmt === false) {
    $errors = sqlsrv_errors();
    $errorMsg = 'Gagal mengambil data: ';
    if ($errors) {
        foreach ($errors as $error) {
            $errorMsg .= 'SQLSTATE: '.$error['SQLSTATE'].', code: '.$error['code'].' - '.$error['message'];
        }
    }
    json_error($errorMsg);
}

$data = [];
$no = $start + 1;
while ($r = sqlsrv_fetch_array($data_stmt, SQLSRV_FETCH_ASSOC)) {
    // Format the date range for display
    $dateRange = '';
    if (isset($r['first_date']) && $r['first_date']) {
        $startDate = $r['first_date'] instanceof DateTime ? 
            $r['first_date']->format('d M Y') : 
            date('d M Y', strtotime($r['first_date']));
        
        $endDate = '';
        if (isset($r['last_date']) && $r['last_date']) {
            $endDate = $r['last_date'] instanceof DateTime ? 
                $r['last_date']->format('d M Y') : 
                date('d M Y', strtotime($r['last_date']));
        }
        
        $dateRange = $startDate . ($endDate && $startDate != $endDate ? ' - ' . $endDate : '');
    }
    $data[] = [
        $no,
        $dateRange, // Show date range instead of single date
        $r['name'] ?? '',
        $r['target'] ?? '',
        is_numeric($r['upload']) ? formatBytes($r['upload']) : ($r['upload'] ?? '0'),
        is_numeric($r['download']) ? formatBytes($r['download']) : ($r['download'] ?? '0'),
        is_numeric($r['total']) ? formatBytes($r['total']) : ($r['total'] ?? '0')
    ];
    $no++;
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
]);

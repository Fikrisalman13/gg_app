<?php
// rekap_pemadaman_serverside.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['UserId'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$conn) {
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// --- Read DataTables params ---
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

$searchValue = '';
if (!empty($_POST['search_value'])) {
    $searchValue = trim($_POST['search_value']);
} elseif (!empty($_POST['search']['value'])) {
    $searchValue = trim($_POST['search']['value']);
}

// Filters
$filter = $_POST['filter'] ?? 'all';
$month = $_POST['month'] ?? date('m');
$year = $_POST['year'] ?? date('Y');
$day = $_POST['day'] ?? date('d');

// Ordering mapping
$columnIndex = $_POST['order'][0]['column'] ?? 1;
$columnName = $_POST['columns'][$columnIndex]['data'] ?? 'waktu';
$columnDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

$validColumns = [
    "waktu"            => "r.waktu",
    "bagian"           => "r.bagian",
    "nama_pelapor"     => "e.nama_lengkap",
    "jumlah_dimatikan" => "r.jumlah_dimatikan",
    "jumlah_aktif"     => "r.jumlah_aktif",
    "keterangan"       => "r.keterangan"
];

$orderColumn = $validColumns[$columnName] ?? "r.waktu";

// --- Build SQL conditions ---
$whereClauses = [];
$params = [];

switch ($filter) {
    case 'daily':
        $whereClauses[] = "DAY(r.waktu) = ?";
        $whereClauses[] = "MONTH(r.waktu) = ?";
        $whereClauses[] = "YEAR(r.waktu) = ?";
        array_push($params, $day, $month, $year);
        break;
    case 'monthly':
        $whereClauses[] = "MONTH(r.waktu) = ?";
        $whereClauses[] = "YEAR(r.waktu) = ?";
        array_push($params, $month, $year);
        break;
    case 'yearly':
        $whereClauses[] = "YEAR(r.waktu) = ?";
        array_push($params, $year);
        break;
}

$searchClauses = [];
if ($searchValue !== '') {
    $searchCols = [
        "r.bagian",
        "e.nama_lengkap",
        "u.UserName",
        "r.keterangan"
    ];
    foreach ($searchCols as $col) {
        $searchClauses[] = "$col LIKE ?";
        $params[] = '%' . $searchValue . '%';
    }
    
    // Add exact numeric match if input is number
    if (is_numeric($searchValue)) {
        $searchClauses[] = "r.jumlah_dimatikan = ?";
        $params[] = intval($searchValue);
        
        $searchClauses[] = "r.jumlah_aktif = ?";
        $params[] = intval($searchValue);
    }
}

$whereSql = "WHERE 1=1";
if (!empty($whereClauses)) {
    $whereSql .= " AND " . implode(" AND ", $whereClauses);
}
if (!empty($searchClauses)) {
    $whereSql .= " AND (" . implode(" OR ", $searchClauses) . ")";
}

// --- recordsTotal (Unfiltered count) ---
$totalSql = "SELECT COUNT(*) AS total FROM dbo.report_pemadaman";
$totalStmt = sqlsrv_query($conn, $totalSql);
$recordsTotal = 0;
if ($totalStmt !== false) {
    $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC);
    $recordsTotal = intval($row['total'] ?? 0);
    sqlsrv_free_stmt($totalStmt);
}

// --- recordsFiltered (Filtered count) ---
$filteredCountSql = "
    SELECT COUNT(*) AS total
    FROM dbo.report_pemadaman r
    LEFT JOIN dbo.SMUserMs u ON r.user_id = u.UserId
    LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
    $whereSql
";
$filteredStmt = sqlsrv_query($conn, $filteredCountSql, $params);
$recordsFiltered = 0;
if ($filteredStmt !== false) {
    $row = sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC);
    $recordsFiltered = intval($row['total'] ?? 0);
    sqlsrv_free_stmt($filteredStmt);
}

// --- Data Query ---
$dataSql = "
    SELECT r.id, r.waktu, r.bagian, r.jumlah_dimatikan, r.jumlah_aktif, 
           r.foto, r.keterangan, u.UserName as pelapor,
           e.nama_lengkap as nama_pelapor
    FROM dbo.report_pemadaman r
    LEFT JOIN dbo.SMUserMs u ON r.user_id = u.UserId
    LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
    $whereSql
    ORDER BY $orderColumn $columnDir
";

if ($length != -1) {
    $dataSql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $dataParams = array_merge($params, [$start, $length]);
} else {
    $dataParams = $params;
}

$stmt = sqlsrv_query($conn, $dataSql, $dataParams);
if ($stmt === false) {
    echo json_encode(['error' => 'SQL Error data query', 'detail' => sqlsrv_errors()]);
    exit;
}

$data = [];
$photoUploadDir = __DIR__ . '/../../uploads/pemadaman/';

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format date
    $waktuStr = '';
    if (isset($row['waktu'])) {
        if ($row['waktu'] instanceof DateTime) {
            $waktuStr = $row['waktu']->format('d/m/Y H:i');
        } else {
            $waktuStr = date('d/m/Y H:i', strtotime($row['waktu']));
        }
    }
    
    // Check photo existence
    $fotoName = $row['foto'] ?? '';
    $fotoExists = false;
    if (!empty($fotoName)) {
        $photoPath = $photoUploadDir . $fotoName;
        $fotoExists = file_exists($photoPath);
    }
    
    $namaPelapor = $row['nama_pelapor'] ?? $row['pelapor'] ?? '';
    
    $data[] = [
        'id' => $row['id'],
        'waktu' => $waktuStr,
        'bagian' => $row['bagian'],
        'nama_pelapor' => $namaPelapor,
        'jumlah_dimatikan' => $row['jumlah_dimatikan'],
        'jumlah_aktif' => $row['jumlah_aktif'],
        'foto' => $fotoName,
        'foto_exists' => $fotoExists,
        'keterangan' => $row['keterangan'] ?? '',
        'aksi' => $row['id']
    ];
}

sqlsrv_free_stmt($stmt);

// --- Output JSON ---
$response = [
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $data
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

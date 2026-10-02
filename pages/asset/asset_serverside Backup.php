<?php
// asset_serverside.php - FULL FIX
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../koneksi.php';

// --- Basic checks ---
if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!$conn) {
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

// --- Read DataTables params (robust) ---
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

// Accept either default DataTables search or custom key 'search_value'
$searchValue = '';
if (!empty($_POST['search_value'])) {
    $searchValue = trim($_POST['search_value']);
} elseif (!empty($_POST['search']['value'])) {
    $searchValue = trim($_POST['search']['value']);
}

// Ordering: map DataTables column names to real DB columns
$columnIndex = $_POST['order'][0]['column'] ?? 0;
$columnName = $_POST['columns'][$columnIndex]['data'] ?? 'kode_asset_seq';
$columnDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';

$validColumns = [
    "kode_asset_seq" => "a.kode_asset_seq",
    "serial_number"  => "a.serial_number",
    "nama_kategori"  => "kat.nama_kategori",
    "nama_merk"      => "m.nama_merk",
    "nama_tipe"      => "t.nama_tipe",
    "merk_tipe"      => "m.nama_merk",   // merk_tipe maps to merk (we render merk + tipe client-side)
    "nama_lokasi"    => "l.nama_lokasi",
    "nama_status"    => "s.nama_status",
    "nama_lengkap"   => "e.nama_lengkap",
    "keterangan"     => "a.keterangan"
];

// Choose safe order column (fallback to kode_asset_seq)
$orderColumn = $validColumns[$columnName] ?? "a.kode_asset_seq";

// --- Filters from client ---
$filterKategori = $_POST['kategori'] ?? '';
$filterLokasi = $_POST['lokasi'] ?? '';
$filterStatus = $_POST['status'] ?? '';

// --- Build WHERE clauses and params safely ---
$whereClauses = [];
$params = [];

if ($filterKategori !== '') {
    $whereClauses[] = "a.id_kategori = ?";
    $params[] = $filterKategori;
}
if ($filterLokasi !== '') {
    $whereClauses[] = "a.id_lokasi = ?";
    $params[] = $filterLokasi;
}
if ($filterStatus !== '') {
    $whereClauses[] = "a.id_status = ?";
    $params[] = $filterStatus;
}

// Global search across multiple columns
$searchClauses = [];
if ($searchValue !== '') {
    // columns to search
    $searchCols = [
        "ka.kode_asset",
        "a.kode_asset_seq",
        "a.serial_number",
        "kat.nama_kategori",
        "m.nama_merk",
        "t.nama_tipe",
        "l.nama_lokasi",
        "s.nama_status",
        "e.nama_lengkap",
        "a.keterangan"
    ];
    foreach ($searchCols as $col) {
        $searchClauses[] = "$col LIKE ?";
        $params[] = '%' . $searchValue . '%';
    }
}

// Combine where
$whereSql = "WHERE 1=1";
if (!empty($whereClauses)) {
    $whereSql .= " AND " . implode(" AND ", $whereClauses);
}
if (!empty($searchClauses)) {
    $whereSql .= " AND (" . implode(" OR ", $searchClauses) . ")";
}

// --- recordsTotal (without any filters/search) ---
// For performance we count from main table only
$totalSql = "SELECT COUNT(*) AS total FROM dbo.m_asset a";
$totalStmt = sqlsrv_query($conn, $totalSql);
if ($totalStmt === false) {
    echo json_encode(['error' => 'SQL Error total', 'detail' => sqlsrv_errors()]);
    exit;
}
$totalRow = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC);
$recordsTotal = intval($totalRow['total'] ?? 0);
if ($totalStmt !== false) sqlsrv_free_stmt($totalStmt);

// --- recordsFiltered (with filters + search) ---
$filteredCountSql = "
    SELECT COUNT(*) AS total
    FROM dbo.m_asset a
    LEFT JOIN dbo.m_kode_asset ka ON a.id_kode = ka.id_kode
    LEFT JOIN dbo.m_kategori kat ON a.id_kategori = kat.id_kategori
    LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
    LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
    LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
    LEFT JOIN dbo.m_status s ON a.id_status = s.id_status
    LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
    $whereSql
";
$filteredStmt = sqlsrv_query($conn, $filteredCountSql, $params);
if ($filteredStmt === false) {
    echo json_encode(['error' => 'SQL Error filtered count', 'detail' => sqlsrv_errors()]);
    exit;
}
$filteredRow = sqlsrv_fetch_array($filteredStmt, SQLSRV_FETCH_ASSOC);
$recordsFiltered = intval($filteredRow['total'] ?? 0);
if ($filteredStmt !== false) sqlsrv_free_stmt($filteredStmt);

// --- Data query (apply joins, where, order, pagination) ---
$dataSql = "
    SELECT a.id_asset, a.kode_asset_seq, a.serial_number,
           ka.kode_asset AS kode_asset_prefix,
           kat.nama_kategori, m.nama_merk, t.nama_tipe,
           l.nama_lokasi, s.nama_status, e.nama_lengkap, a.keterangan
    FROM dbo.m_asset a
    LEFT JOIN dbo.m_kode_asset ka ON a.id_kode = ka.id_kode
    LEFT JOIN dbo.m_kategori kat ON a.id_kategori = kat.id_kategori
    LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
    LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
    LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
    LEFT JOIN dbo.m_status s ON a.id_status = s.id_status
    LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
    $whereSql
    ORDER BY $orderColumn $columnDir
";

// Pagination: handle length = -1 (show all)
if ($length != -1) {
    // OFFSET-FETCH requires ORDER BY (we have it)
    $dataSql .= " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    // For OFFSET-FETCH add pagination params at end
    $dataParams = array_merge($params, [$start, $length]);
} else {
    $dataParams = $params;
}

// Execute
$stmt = sqlsrv_query($conn, $dataSql, $dataParams);
if ($stmt === false) {
    echo json_encode(['error' => 'SQL Error data query', 'detail' => sqlsrv_errors()]);
    exit;
}

// Fetch rows
$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // merk_tipe rendered as "merk / tipe"
    $merk = $row['nama_merk'] ?? '';
    $tipe = $row['nama_tipe'] ?? '';
    $merkTipe = trim($merk . ($merk !== '' && $tipe !== '' ? ' / ' : '') . $tipe);

    $data[] = [
        'id_asset' => $row['id_asset'],
        'kode_asset_seq' => $row['kode_asset_seq'],
        'kode_asset_prefix' => $row['kode_asset_prefix'],
        'serial_number' => $row['serial_number'],
        'nama_kategori' => $row['nama_kategori'],
        'nama_merk' => $row['nama_merk'],
        'nama_tipe' => $row['nama_tipe'],
        'merk_tipe' => $merkTipe,
        'nama_lokasi' => $row['nama_lokasi'],
        'nama_status' => $row['nama_status'],
        'nama_lengkap' => $row['nama_lengkap'],
        'keterangan' => $row['keterangan'],
        // Return aksi as id (front-end will render action buttons; you can change to object {id,can_edit,can_delete} if you prefer)
        'aksi' => $row['id_asset']
    ];
}
if ($stmt !== false) sqlsrv_free_stmt($stmt);

// --- Output JSON ---
$response = [
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $data
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

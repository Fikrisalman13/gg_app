<?php
// ===================================================
// SERVER-SIDE DATATABLE FOR ISSUES
// ===================================================
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = [
    'draw' => intval($_POST['draw'] ?? $_GET['draw'] ?? 1),
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => []
];

if (!isset($_SESSION['UserName'])) {
    echo json_encode($response);
    exit;
}

$draw = $response['draw'];
$start = isset($_POST['start']) ? max(0, intval($_POST['start'])) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
if ($length === -1) {
    $length = 2147483647; // DataTables "show all"
} elseif ($length <= 0) {
    $length = 10;
}
$searchValue = trim((string)($_POST['search']['value'] ?? ''));

$scope = strtolower((string)($_POST['scope'] ?? $_GET['scope'] ?? 'active'));
$filterType = trim((string)($_POST['filterType'] ?? $_GET['filterType'] ?? ''));
$filterTanggalStart = trim((string)($_POST['filterTanggalStart'] ?? $_GET['filterTanggalStart'] ?? ''));
$filterTanggalEnd = trim((string)($_POST['filterTanggalEnd'] ?? $_GET['filterTanggalEnd'] ?? ''));

$columns = [
    null,           // No (client side only)
    'issue_name',   // Issue name
    'issue_type',   // Type
    'kategori',     // Category
    'created_by',   // Requester
    'status',       // Status
    'priority',     // Priority
    'due_date',     // Due date
    null            // Actions
];

// Get ordering parameters
// Check if order array is sent and not empty
$hasOrder = isset($_POST['order']) && is_array($_POST['order']) && count($_POST['order']) > 0;

if ($hasOrder) {
    $orderColumnIdx = intval($_POST['order'][0]['column']);
    $orderDir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
    $orderColumn = $columns[$orderColumnIdx] ?? null;
    
    // If column is null or invalid, default to created_at
    if ($orderColumn === null) {
        $orderColumn = 'created_at';
        $orderDir = 'DESC';
    }
} else {
    // No order specified from client, use default: newest first
    $orderColumn = 'created_at';
    $orderDir = 'DESC';
}

$orderClause = $orderColumn . ' ' . $orderDir;

$conditions = [];
$params = [];

if ($scope === 'done') {
    $conditions[] = "UPPER(LTRIM(RTRIM(status))) IN ('DONE','CLOSED')";
} elseif ($scope === 'active') {
    $conditions[] = "(status IS NULL OR UPPER(LTRIM(RTRIM(status))) NOT IN ('DONE','CLOSED'))";
}

if ($filterType !== '') {
    $conditions[] = 'UPPER(issue_type) = UPPER(?)';
    $params[] = $filterType;
}

$dateStart = $filterTanggalStart !== '' ? DateTime::createFromFormat('Y-m-d', $filterTanggalStart) : false;
$dateEnd = $filterTanggalEnd !== '' ? DateTime::createFromFormat('Y-m-d', $filterTanggalEnd) : false;

if ($dateStart && $dateEnd) {
    $conditions[] = 'CAST(created_at AS DATE) BETWEEN ? AND ?';
    $params[] = $dateStart->format('Y-m-d');
    $params[] = $dateEnd->format('Y-m-d');
} elseif ($dateStart) {
    $conditions[] = 'CAST(created_at AS DATE) >= ?';
    $params[] = $dateStart->format('Y-m-d');
} elseif ($dateEnd) {
    $conditions[] = 'CAST(created_at AS DATE) <= ?';
    $params[] = $dateEnd->format('Y-m-d');
}

$baseWhere = '';
if (!empty($conditions)) {
    $baseWhere = 'WHERE ' . implode(' AND ', $conditions);
}

$totalSql = "SELECT COUNT(*) AS cnt FROM dbo.issues $baseWhere";
$stmtTotal = sqlsrv_query($conn, $totalSql, $params);
if ($stmtTotal !== false && ($row = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC))) {
    $response['recordsTotal'] = intval($row['cnt']);
}
if ($stmtTotal !== false) {
    sqlsrv_free_stmt($stmtTotal);
}

$filteredWhere = $baseWhere;
$filteredParams = $params;
if ($searchValue !== '') {
    $like = '%' . $searchValue . '%';
    $searchClause = '(issue_name LIKE ? OR issue_type LIKE ? OR kategori LIKE ? OR sub_kategori LIKE ? OR created_by LIKE ? OR status LIKE ? OR priority LIKE ?)';
    $filteredWhere .= ($filteredWhere === '' ? ' WHERE ' : ' AND ') . $searchClause;
    $filteredParams = array_merge($filteredParams, array_fill(0, 7, $like));
}

$filteredSql = "SELECT COUNT(*) AS cnt FROM dbo.issues $filteredWhere";
$stmtFiltered = sqlsrv_query($conn, $filteredSql, $filteredParams);
if ($stmtFiltered !== false && ($row = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC))) {
    $response['recordsFiltered'] = intval($row['cnt']);
} else {
    $response['recordsFiltered'] = $response['recordsTotal'];
}
if ($stmtFiltered !== false) {
    sqlsrv_free_stmt($stmtFiltered);
}

$dataSql = "SELECT i.issue_id, i.issue_name, i.issue_type, i.kategori, i.sub_kategori, i.created_by, i.status, i.priority, i.due_date, i.description, i.asset_id, i.created_at,
                     i.client_id, i.tanggal_selesai, i.jabatan, i.departemen, i.bagian,
                     e.nama_lengkap AS client_name
            FROM dbo.issues i
            LEFT JOIN dbo.m_emp e ON i.client_id = e.id_emp
            $filteredWhere
            ORDER BY $orderClause
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = $filteredParams;
$dataParams[] = $start;
$dataParams[] = $length;

$stmtData = sqlsrv_query($conn, $dataSql, $dataParams);
$rows = [];
$counter = $start + 1;

if ($stmtData !== false) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $kategoriDisplay = $row['kategori'] ?? '';
        if (!empty($row['sub_kategori'])) {
            $kategoriDisplay .= ' > ' . $row['sub_kategori'];
        }

        $dueDate = '';
        if (!empty($row['due_date']) && $row['due_date'] instanceof DateTimeInterface) {
            $dueDate = $row['due_date']->format('Y-m-d');
        } elseif (!empty($row['due_date'])) {
            $dueDate = (string)$row['due_date'];
        }

        $rows[] = [
            'no' => $counter++,
            'issue_id' => $row['issue_id'],
            'issue_name' => htmlspecialchars($row['issue_name'] ?? ''),
            'type' => htmlspecialchars($row['issue_type'] ?? ''),
            'kategori' => htmlspecialchars($kategoriDisplay),
            'nama_pemohon' => htmlspecialchars($row['created_by'] ?? ''),
            'status' => htmlspecialchars($row['status'] ?? ''),
            'priority' => htmlspecialchars($row['priority'] ?? ''),
            'due_date' => $dueDate,
            'description' => $row['description'] ?? '',
            'asset_id' => $row['asset_id'] ?? null,
            'client_id' => $row['client_id'] ?? null,
            'client_name' => htmlspecialchars($row['client_name'] ?? ''),
            'tanggal_selesai' => $row['tanggal_selesai'] ?? null,
            'raw_kategori' => $row['kategori'] ?? '',
            'raw_sub_kategori' => $row['sub_kategori'] ?? '',
            'jabatan' => htmlspecialchars($row['jabatan'] ?? ''),
            'departemen' => htmlspecialchars($row['departemen'] ?? ''),
            'bagian' => htmlspecialchars($row['bagian'] ?? ''),
            'created_at_raw' => $row['created_at'] instanceof DateTimeInterface ? $row['created_at']->format('d-m-Y') : (string)$row['created_at']
        ];
    }
    sqlsrv_free_stmt($stmtData);
}

$response['data'] = $rows;

echo json_encode($response, JSON_UNESCAPED_UNICODE);
if (isset($conn)) {
    sqlsrv_close($conn);
}
?>

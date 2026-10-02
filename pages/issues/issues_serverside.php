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

$sqlEnsureProjectIssueLinks = "
IF OBJECT_ID('dbo.project_issue_links', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.project_issue_links (
        link_id INT IDENTITY(1,1) PRIMARY KEY,
        projectid INT NOT NULL,
        issue_id INT NOT NULL,
        issue_status_snapshot VARCHAR(50) NULL,
        created_by VARCHAR(150) NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE()
    );
    CREATE UNIQUE INDEX UX_project_issue_links_project_issue ON dbo.project_issue_links(projectid, issue_id);
    CREATE INDEX IX_project_issue_links_issue_id ON dbo.project_issue_links(issue_id);
END";
@sqlsrv_query($conn, $sqlEnsureProjectIssueLinks);

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

$filterKategori = trim((string)($_POST['filterKategori'] ?? $_GET['filterKategori'] ?? ''));
$filterSubKategori = trim((string)($_POST['filterSubKategori'] ?? $_GET['filterSubKategori'] ?? ''));

if ($filterType !== '') {
    $conditions[] = 'UPPER(issue_type) = UPPER(?)';
    $params[] = $filterType;
}

if ($filterKategori !== '') {
    $conditions[] = 'kategori = ?';
    $params[] = $filterKategori;
}

if ($filterSubKategori !== '') {
    $conditions[] = 'sub_kategori = ?';
    $params[] = $filterSubKategori;
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
                     i.client_id, i.tanggal_selesai, i.jabatan, i.departemen, i.bagian, i.attachment,
                     e.nama_lengkap AS client_name,
                     issue_project.projectid
            FROM dbo.issues i
            LEFT JOIN dbo.m_emp e ON i.client_id = e.id_emp
            OUTER APPLY (
                SELECT TOP 1 l.projectid
                FROM dbo.project_issue_links l
                WHERE l.issue_id = i.issue_id
                ORDER BY l.created_at DESC, l.link_id DESC
            ) issue_project
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
            'issue_name' => $row['issue_name'] ?? '',
            'type' => $row['issue_type'] ?? '',
            'kategori' => htmlspecialchars($kategoriDisplay),
            'nama_pemohon' => htmlspecialchars($row['created_by'] ?? ''),
            'status' => $row['status'] ?? '',
            'priority' => $row['priority'] ?? '',
            'due_date' => $dueDate,
            'description' => $row['description'] ?? '',
            'asset_id' => $row['asset_id'] ?? null,
            'client_id' => $row['client_id'] ?? null,
            'projectid' => $row['projectid'] ?? null,
            'client_name' => htmlspecialchars($row['client_name'] ?? ''),
            'tanggal_selesai' => $row['tanggal_selesai'] ?? null,
            'raw_kategori' => $row['kategori'] ?? '',
            'raw_sub_kategori' => $row['sub_kategori'] ?? '',
            'jabatan' => htmlspecialchars($row['jabatan'] ?? ''),
            'departemen' => htmlspecialchars($row['departemen'] ?? ''),
            'bagian' => htmlspecialchars($row['bagian'] ?? ''),
            'attachment' => $row['attachment'] ?? null,
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

<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
// permissions for IPAB
$menuId = 222;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
header('Content-Type: application/json');

$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');

$filterTanggal   = $_POST['filterTanggal'] ?? '';
$filterShift     = $_POST['filterShift'] ?? '';
$filterParameter = $_POST['filterParameter'] ?? '';

function buildWhere(&$params, $aliasTanggal = 'Tanggal', $aliasShift = 'Shift', $aliasCreatedBy = 'CreatedBy', $aliasParameter = null) {
    global $search, $filterTanggal, $filterShift;

    $where = [];

    if ($search !== '') {
        $searchClause = "(Pengecek LIKE ? OR $aliasShift LIKE ? OR $aliasCreatedBy LIKE ? OR CONVERT(VARCHAR(10), $aliasTanggal, 120) LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        if (!empty($aliasParameter)) {
            $searchClause .= " OR $aliasParameter LIKE ?";
            $params[] = "%$search%";
        }
        $where[] = $searchClause;
    }

    if ($filterTanggal !== '') {
        $where[] = "CAST($aliasTanggal AS DATE) = ?";
        $params[] = date('Y-m-d', strtotime($filterTanggal));
    }

    if ($filterShift !== '') {
        $where[] = "$aliasShift = ?";
        $params[] = $filterShift;
    }

    return $where ? 'WHERE ' . implode(' AND ', $where) : '';
}

// TOTAL DATA
$sqlTotal = "
SELECT COUNT(*) AS total FROM (
    SELECT Id FROM dbo.ph_ipab
    UNION ALL
    SELECT Id FROM dbo.dh_ipab
    UNION ALL
    SELECT Id FROM dbo.turbidity_ipab
) x
";
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalData = ($row = sqlsrv_fetch_array($stmtTotal)) ? $row['total'] : 0;

// FILTERED COUNT
$totalFiltered = 0;
$paramsFiltered = [];
$sqlFilteredParts = [];

if ($filterParameter === '' || strtolower($filterParameter) === 'ph') {
    $p = [];
    $where = buildWhere($p, 'Tanggal', 'Shift', 'CreatedBy', "'pH'");
    $sqlFilteredParts[] = "SELECT Id FROM dbo.ph_ipab $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}
if ($filterParameter === '' || strtolower($filterParameter) === 'dh') {
    $p = [];
    $where = buildWhere($p, 'Tanggal', 'Shift', 'CreatedBy', "'DH'");
    $sqlFilteredParts[] = "SELECT Id FROM dbo.dh_ipab $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}
if ($filterParameter === '' || strtolower($filterParameter) === 'turbidity') {
    $p = [];
    $where = buildWhere($p, 'Tanggal', 'Shift', 'CreatedBy', "'Turbidity'");
    $sqlFilteredParts[] = "SELECT Id FROM dbo.turbidity_ipab $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}

if (empty($sqlFilteredParts)) {
    $sqlFilteredParts[] = "SELECT Id FROM dbo.ph_ipab WHERE 1 = 0";
}

$sqlFiltered = "SELECT COUNT(*) AS total FROM (" . implode(" UNION ALL ", $sqlFilteredParts) . ") x";
$stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $paramsFiltered);
$totalFiltered = ($row = sqlsrv_fetch_array($stmtFiltered)) ? $row['total'] : 0;

// DATA QUERY
$dataParams = [];
$sqlDataParts = [];

if ($filterParameter === '' || strtolower($filterParameter) === 'ph') {
    $p = [];
    $where = buildWhere($p, 'Tanggal', 'Shift', 'CreatedBy', "'pH'");
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, CreatedAt, UpdatedAt, 'pH' AS Parameter
        FROM dbo.ph_ipab $where
    ";
    $dataParams = array_merge($dataParams, $p);
}
if ($filterParameter === '' || strtolower($filterParameter) === 'dh') {
    $p = [];
    $where = buildWhere($p, 'Tanggal', 'Shift', 'CreatedBy', "'DH'");
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, CreatedAt, UpdatedAt, 'DH' AS Parameter
        FROM dbo.dh_ipab $where
    ";
    $dataParams = array_merge($dataParams, $p);
}
if ($filterParameter === '' || strtolower($filterParameter) === 'turbidity') {
    $p = [];
    $where = buildWhere($p, 'Tanggal', 'Shift', 'CreatedBy', "'Turbidity'");
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, CreatedAt, UpdatedAt, 'Turbidity' AS Parameter
        FROM dbo.turbidity_ipab $where
    ";
    $dataParams = array_merge($dataParams, $p);
}

if (empty($sqlDataParts)) {
    $sqlDataParts[] = "SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, UpdatedAt, 'UNKNOWN' AS Parameter FROM dbo.ph_ipab WHERE 1 = 0";
}

$sqlData = "
" . implode(" UNION ALL ", $sqlDataParts) . "
ORDER BY Tanggal DESC
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";

$dataParams[] = $start;
$dataParams[] = $length;

$stmtData = sqlsrv_query($conn, $sqlData, $dataParams);

$data = [];
while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
    $createdAtRaw = $row['CreatedAt'] ?? null;
    if (is_object($createdAtRaw)) {
        $createdAtFormatted = $createdAtRaw->format('Y-m-d H:i:s');
    } elseif (!empty($createdAtRaw)) {
        $createdAtFormatted = date('Y-m-d H:i:s', strtotime($createdAtRaw));
    } else {
        $createdAtFormatted = '-';
    }

    $updateAtRaw = $row['UpdatedAt'] ?? null;
    if (is_object($updateAtRaw)) {
        $updateAtFormatted = $updateAtRaw->format('Y-m-d H:i:s');
    } elseif (!empty($updateAtRaw)) {
        $updateAtFormatted = date('Y-m-d H:i:s', strtotime($updateAtRaw));
    } else {
        $updateAtFormatted = '-';
    }

    $buttons = [];
    // View always available if user can view page
    $buttons[] = "<button class='btn btn-info btn-sm btn-detail mr-1'><i class='fas fa-eye'></i></button>";
    if (!empty($permissions['CanEdit'])) {
        $buttons[] = "<button class='btn btn-warning btn-sm btn-edit mr-1'><i class='fas fa-edit'></i></button>";
    }
    if (!empty($permissions['CanDelete'])) {
        $buttons[] = "<button class='btn btn-danger btn-sm btn-delete'><i class='fas fa-trash'></i></button>";
    }
    $aksi = $buttons ? "<div class='btn-group'>" . implode('', $buttons) . "</div>" : '';

    $data[] = [
        'id' => $row['Id'],
        'tanggal' => is_object($row['Tanggal']) ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'],
        'pengecek' => $row['Pengecek'],
        'shift' => $row['Shift'],
        'parameter' => $row['Parameter'],
        'created_by' => $row['CreatedBy'],
        'created_at' => $createdAtFormatted,
        'updated_at' => $updateAtFormatted,
        'aksi' => $aksi
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);

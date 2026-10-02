<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
// permissions for PH Air
$menuId = 190;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Ambil parameter DataTables
|--------------------------------------------------------------------------
*/
$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = trim($_POST['search']['value'] ?? '');

$filterTanggal   = $_POST['filterTanggal'] ?? '';
$filterShift     = $_POST['filterShift'] ?? '';
$filterParameter = $_POST['filterParameter'] ?? '';

/*
|--------------------------------------------------------------------------
| Helper WHERE builder
|--------------------------------------------------------------------------
*/
function buildWhere(&$params, $aliasTanggal = 'Tanggal', $aliasShift = 'Shift') {
    global $search, $filterTanggal, $filterShift;

    $where = [];

    if ($search !== '') {
        $where[] = "(Pengecek LIKE ? OR $aliasShift LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
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

/*
|--------------------------------------------------------------------------
| TOTAL DATA (tanpa filter)
|--------------------------------------------------------------------------
*/
$sqlTotal = "
SELECT COUNT(*) AS total FROM (
    SELECT Id FROM dbo.PH_Air
    UNION ALL
    SELECT Id FROM dbo.COD_air
    UNION ALL
    SELECT Id FROM dbo.TSS_Air
    UNION ALL
    SELECT Id FROM dbo.MLSS_Air
    UNION ALL
    SELECT Id FROM dbo.PTCO_Air
) x
";
$stmtTotal = sqlsrv_query($conn, $sqlTotal);
$totalData = ($row = sqlsrv_fetch_array($stmtTotal)) ? $row['total'] : 0;

/*
|--------------------------------------------------------------------------
| FILTERED COUNT
|--------------------------------------------------------------------------
*/
$totalFiltered = 0;
$paramsFiltered = [];
$sqlFilteredParts = [];

/* pH */
if ($filterParameter === '' || $filterParameter === 'pH Air') {
    $p = [];
    $where = buildWhere($p);
    $sqlFilteredParts[] = "SELECT Id FROM dbo.PH_Air $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}

/* COD */
if ($filterParameter === '' || $filterParameter === 'COD') {
    $p = [];
    $where = buildWhere($p);
    $sqlFilteredParts[] = "SELECT Id FROM dbo.COD_air $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}

/* TSS */
if ($filterParameter === '' || $filterParameter === 'TSS') {
    $p = [];
    $where = buildWhere($p, 'Tanggal', 'Shift');
    $sqlFilteredParts[] = "SELECT Id FROM dbo.TSS_Air $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}

/* MLSS */
if ($filterParameter === '' || $filterParameter === 'MLSS') {
    $p = [];
    $where = buildWhere($p);
    $sqlFilteredParts[] = "SELECT Id FROM dbo.MLSS_Air $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}

/* PTCO */
if ($filterParameter === '' || $filterParameter === 'PTCO') {
    $p = [];
    $where = buildWhere($p);
    $sqlFilteredParts[] = "SELECT Id FROM dbo.PTCO_Air $where";
    $paramsFiltered = array_merge($paramsFiltered, $p);
}

if (empty($sqlFilteredParts)) {
    $sqlFilteredParts[] = "SELECT Id FROM dbo.PH_Air WHERE 1 = 0";
}

$sqlFiltered = "SELECT COUNT(*) AS total FROM (" . implode(" UNION ALL ", $sqlFilteredParts) . ") x";
$stmtFiltered = sqlsrv_query($conn, $sqlFiltered, $paramsFiltered);
$totalFiltered = ($row = sqlsrv_fetch_array($stmtFiltered)) ? $row['total'] : 0;

/*
|--------------------------------------------------------------------------
| DATA QUERY
|--------------------------------------------------------------------------
*/
$dataParams = [];
$sqlDataParts = [];

/* pH */
if ($filterParameter === '' || $filterParameter === 'pH Air') {
    $p = [];
    $where = buildWhere($p);
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, UpdateAt, 'pH Air' AS Parameter
        FROM dbo.PH_Air $where
    ";
    $dataParams = array_merge($dataParams, $p);
}

/* COD */
if ($filterParameter === '' || $filterParameter === 'COD') {
    $p = [];
    $where = buildWhere($p);
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, UpdateAt, 'COD' AS Parameter
        FROM dbo.COD_air $where
    ";
    $dataParams = array_merge($dataParams, $p);
}

/* TSS */
if ($filterParameter === '' || $filterParameter === 'TSS') {
    $p = [];
    $where = buildWhere($p);
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, Created_By AS CreatedBy, UpdateAt, 'TSS' AS Parameter
        FROM dbo.TSS_Air $where
    ";
    $dataParams = array_merge($dataParams, $p);
}

/* MLSS */
if ($filterParameter === '' || $filterParameter === 'MLSS') {
    $p = [];
    $where = buildWhere($p);
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, UpdateAt, 'MLSS' AS Parameter
        FROM dbo.MLSS_Air $where
    ";
    $dataParams = array_merge($dataParams, $p);
}

/* PTCO */
if ($filterParameter === '' || $filterParameter === 'PTCO') {
    $p = [];
    $where = buildWhere($p);
    $sqlDataParts[] = "
        SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, UpdateAt, 'PTCO' AS Parameter
        FROM dbo.PTCO_Air $where
    ";
    $dataParams = array_merge($dataParams, $p);
}

if (empty($sqlDataParts)) {
    $sqlDataParts[] = "SELECT Id, Tanggal, Pengecek, Shift, CreatedBy, UpdateAt, 'UNKNOWN' AS Parameter FROM dbo.PH_Air WHERE 1 = 0";
}

$sqlData = "
" . implode(" UNION ALL ", $sqlDataParts) . "
ORDER BY Tanggal DESC
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";

$dataParams[] = $start;
$dataParams[] = $length;

$stmtData = sqlsrv_query($conn, $sqlData, $dataParams);

/*
|--------------------------------------------------------------------------
| BUILD OUTPUT
|--------------------------------------------------------------------------
*/
$data = [];
while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {

    $shiftLabel = match ($row['Shift']) {
        '1' => 'Pagi',
        '2' => 'Siang',
        '3' => 'Malam',
        default => $row['Shift']
    };

    $updateAtRaw = $row['UpdateAt'] ?? null;
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
        'shift' => $shiftLabel,
        'parameter' => $row['Parameter'],
        'created_by' => $row['CreatedBy'],
        'updated_at' => $updateAtFormatted,
        'aksi' => $aksi
    ];
}

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);

<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$conn) {
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

function getCurrentMenuId($conn, $fallbackMenuId = 134)
{
    $urls = [
        '/gg_app/pages/form_it/Serah_terima/list_serah_terima.php',
        '/gg_app/pages/form_it/serah_terima/list_serah_terima.php',
    ];
    $placeholders = implode(',', array_fill(0, count($urls), '?'));
    $stmt = sqlsrv_query($conn, "SELECT TOP 1 MenuId FROM dbo.SMMenu WHERE MenuUrl IN ($placeholders)", $urls);

    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        sqlsrv_free_stmt($stmt);
        return (int) $row['MenuId'];
    }

    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }

    return $fallbackMenuId;
}

function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }

    if ($stmt !== false) {
        sqlsrv_free_stmt($stmt);
    }

    return $permissions;
}

$menuId = getCurrentMenuId($conn);
$permissions = checkPermissions($conn, $_SESSION['GroupId'], $menuId);
if ((int) $permissions['CanView'] !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = '';
if (!empty($_POST['search_value'])) {
    $searchValue = trim($_POST['search_value']);
} elseif (!empty($_POST['search']['value'])) {
    $searchValue = trim($_POST['search']['value']);
}

$columnIndex = $_POST['order'][0]['column'] ?? 5;
$columnName = $_POST['columns'][$columnIndex]['data'] ?? 'tgl_pengajuan';
$columnDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

$validColumns = [
    'ticket' => 'x.ticket',
    'kategori' => 'x.kategori',
    'nama_pemohon' => 'x.nama_pemohon',
    'departemen' => 'x.departemen',
    'tgl_pengajuan' => 'x.tgl_pengajuan',
    'status_ticket' => 'x.status_ticket',
];

$orderColumn = $validColumns[$columnName] ?? 'x.tgl_pengajuan';
$orderSql = "ORDER BY $orderColumn $columnDir, x.created_at DESC";

$filterKategori = $_POST['kategori'] ?? '';
$filterTanggal = $_POST['tanggal'] ?? '';

$baseQuery = "
    SELECT
        ticket,
        nama_pemohon,
        departemen,
        tanggal_serah_terima AS tgl_pengajuan,
        status_ticket,
        ISNULL(kategori, 'Serah Terima Aplikasi') AS kategori,
        created_at,
        created_by,
        nama_aplikasi,
        nama_modul
    FROM dbo.Form_Serah_Terima_Aplikasi
";

$whereClauses = [];
$params = [];

if ((int) ($_SESSION['GroupId'] ?? 0) !== 1) {
    $whereClauses[] = "x.created_by = ?";
    $params[] = $_SESSION['NamaLengkap'] ?? '';
}

if ($filterKategori !== '') {
    $whereClauses[] = "x.kategori = ?";
    $params[] = $filterKategori;
}

if ($filterTanggal !== '') {
    $whereClauses[] = "CAST(x.tgl_pengajuan AS DATE) = ?";
    $params[] = $filterTanggal;
}

if ($searchValue !== '') {
    $searchCols = ['x.ticket', 'x.nama_pemohon', 'x.departemen', 'x.kategori', 'x.status_ticket', 'x.nama_aplikasi', 'x.nama_modul'];
    $searchClauses = [];
    foreach ($searchCols as $col) {
        $searchClauses[] = "$col LIKE ?";
        $params[] = '%' . $searchValue . '%';
    }
    $whereClauses[] = '(' . implode(' OR ', $searchClauses) . ')';
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$countTotalStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM ($baseQuery) AS x");
$totalRecords = 0;
if ($countTotalStmt !== false && $row = sqlsrv_fetch_array($countTotalStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = (int) $row['total'];
}
if ($countTotalStmt) {
    sqlsrv_free_stmt($countTotalStmt);
}

$countFilteredStmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM ($baseQuery) AS x $whereSql", $params);
$recordsFiltered = 0;
if ($countFilteredStmt !== false && $row = sqlsrv_fetch_array($countFilteredStmt, SQLSRV_FETCH_ASSOC)) {
    $recordsFiltered = (int) $row['total'];
}
if ($countFilteredStmt) {
    sqlsrv_free_stmt($countFilteredStmt);
}

$dataSql = "SELECT x.*,
            (SELECT COUNT(*) FROM dbo.Form_Pengajuan_Barang_TTD WHERE Ticket = x.ticket AND SignaturePath IS NOT NULL AND SignaturePath != '') AS ttd_count
            FROM ($baseQuery) AS x $whereSql $orderSql OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$stmt = sqlsrv_query($conn, $dataSql, array_merge($params, [$start, $length]));
if ($stmt === false) {
    echo json_encode(['error' => 'SQL Error', 'detail' => sqlsrv_errors()]);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $ticket = $row['ticket'];
    $tgl = $row['tgl_pengajuan'] instanceof DateTime ? $row['tgl_pengajuan']->format('d-m-Y') : '-';
    $st = strtolower(trim($row['status_ticket'] ?? ''));
    $ttdCount = (int) $row['ttd_count'];
    $required = 3;

    if ($st === 'ditolak' || $st === 'rejected') {
        $statusBadge = "<span class='badge badge-danger'>Ditolak</span>";
    } elseif ($ttdCount >= $required || $st === 'approved' || $st === 'selesai') {
        $statusBadge = "<span class='badge badge-success'>Selesai</span>";
    } elseif ($ttdCount > 0) {
        $statusBadge = "<span class='badge badge-info'>{$ttdCount}/{$required} Disetujui</span>";
    } else {
        $statusBadge = "<span class='badge badge-warning'>Menunggu Persetujuan</span>";
    }

    $kategori = htmlspecialchars($row['kategori'] ?? '', ENT_QUOTES, 'UTF-8');
    $ticketAttr = htmlspecialchars($ticket, ENT_QUOTES, 'UTF-8');
    $btns = "<div class='btn-group' role='group'>";
    $btns .= "<button type='button' class='btn btn-info btn-sm btn-detail action-btn' data-ticket='{$ticketAttr}' data-detail-url='detail_serah_terima.php?ticket={$ticketAttr}' title='Detail'><i class='fas fa-eye'></i></button>";
    $btns .= "<a href='generate_pdf_serah_terima.php?ticket={$ticketAttr}' class='btn btn-danger btn-sm action-btn' target='_blank' title='PDF'><i class='fas fa-file-pdf'></i></a>";

    if (!empty($permissions['CanDelete']) && (int) $permissions['CanDelete'] === 1) {
        $btns .= "<button type='button' class='btn btn-danger btn-sm btn-delete action-btn' data-ticket='{$ticketAttr}' title='Hapus'><i class='fas fa-trash'></i></button>";
    }
    $btns .= "</div>";

    $data[] = [
        'ticket' => $ticket,
        'kategori' => $kategori,
        'nama_pemohon' => $row['nama_pemohon'],
        'departemen' => $row['departemen'],
        'tgl_pengajuan' => $tgl,
        'status_ticket' => $statusBadge,
        'aksi' => $btns,
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
]);

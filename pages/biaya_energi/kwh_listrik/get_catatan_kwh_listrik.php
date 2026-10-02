<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak melihat catatan.']);
    exit;
}

if (!kwhl_table_exists($conn)) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}
$tableName = kwhl_table_full_name($conn);
if ($tableName === '') {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$tableCols = kwhl_get_table_columns($conn);
if (!isset($tableCols['note'])) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$start = kwhl_normalize_date($_POST['start_date'] ?? '');
$end = kwhl_normalize_date($_POST['end_date'] ?? '');
if ($start === '' || $end === '') {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}

$creatorCandidates = [];
foreach (['updateby', 'creatby', 'created_by', 'createby'] as $col) {
    if (isset($tableCols[$col])) {
        $creatorCandidates[] = "NULLIF(LTRIM(RTRIM(CAST([" . $col . "] AS NVARCHAR(MAX)))), '')";
    }
}
$creatorExpr = !empty($creatorCandidates) ? ("COALESCE(" . implode(', ', $creatorCandidates) . ", '')") : "''";

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal,
               MAX(note) AS note,
               MAX(" . $creatorExpr . ") AS creatby
        FROM {$tableName}
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
          AND note IS NOT NULL AND LTRIM(RTRIM(note)) <> ''
        GROUP BY CAST(tanggal AS DATE)
        ORDER BY CAST(tanggal AS DATE) DESC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil catatan.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateVal = $row['tanggal'] ?? null;
    if ($dateVal instanceof DateTime) {
        $tanggal = $dateVal->format('Y-m-d');
    } else {
        $tanggal = $dateVal ? date('Y-m-d', strtotime((string)$dateVal)) : '';
    }

    $data[] = [
        'tanggal' => $tanggal,
        'catatan' => (string)($row['note'] ?? ''),
        'creatby' => (string)($row['creatby'] ?? ''),
    ];
}
sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);

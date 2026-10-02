<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak melihat catatan.']);
    exit;
}

$start = $_POST['start_date'] ?? '';
$end = $_POST['end_date'] ?? '';
if ($start === '' || $end === '') {
    echo json_encode(['success' => false, 'message' => 'Rentang tanggal tidak valid.']);
    exit;
}

$sql = "SELECT id, tanggal, catatan, creatby
        FROM dbo.air_analog_actom
        WHERE CAST(tanggal AS date) BETWEEN ? AND ?
          AND catatan IS NOT NULL AND LTRIM(RTRIM(catatan)) <> ''
        ORDER BY tanggal DESC, id DESC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat catatan.']);
    exit;
}

$data = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $t = $r['tanggal'];
    if ($t instanceof DateTime) $t = $t->format('Y-m-d');
    $data[] = [
        'id' => $r['id'] ?? null,
        'tanggal' => $t,
        'catatan' => $r['catatan'] ?? '',
        'creatby' => $r['creatby'] ?? ''
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);


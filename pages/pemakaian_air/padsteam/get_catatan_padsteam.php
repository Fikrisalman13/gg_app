<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 231; // TODO: ganti dengan MenuId padsteam di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk melihat catatan.']);
    exit;
}

$normalizeDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return '';
};

$start = $normalizeDate($_POST['start_date'] ?? '');
$end = $normalizeDate($_POST['end_date'] ?? '');
if ($start === '' || $end === '') {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}

$sql = "SELECT Id, Tanggal, CreatAt, Catatan, Creatby
        FROM dbo.catatan_padsteam
        WHERE CAST(Tanggal AS date) BETWEEN ? AND ?
        ORDER BY Tanggal DESC, CreatAt DESC, Id DESC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil catatan.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tanggal = $row['Tanggal'];
    if ($tanggal instanceof DateTime) {
        $tanggal = $tanggal->format('Y-m-d');
    }
    $data[] = [
        'id' => $row['Id'] ?? null,
        'tanggal' => $tanggal,
        'catatan' => $row['Catatan'] ?? '',
        'creatby' => $row['Creatby'] ?? ''
    ];
}

if ($stmt) {
    sqlsrv_free_stmt($stmt);
}

echo json_encode(['success' => true, 'data' => $data]);

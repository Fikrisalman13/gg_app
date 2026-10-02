<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId air_bersih_limbah di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Tidak memiliki akses.']);
    exit;
}

$tanggal = $_GET['tanggal'] ?? '';
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$dt) {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
    exit;
}

$sql = "SELECT SUM(Total_Pemakaian) AS total_pemakaian
        FROM dbo.mkp_air
        WHERE CAST(Tanggal AS DATE) = ?";
$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data.']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

echo json_encode([
    'success' => true,
    'total_pemakaian' => $row['total_pemakaian'] ?? null
]);

<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak melihat histori harga.']);
    exit;
}

$masterId = intval($_GET['master_id'] ?? 0);
if ($masterId <= 0) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$sql = "SELECT TOP 100 CAST(h.tanggal AS DATE) AS tanggal, h.harga_rp
        FROM dbo.kimia_weaving_harian h
        WHERE h.master_id = ? AND h.harga_rp IS NOT NULL
        ORDER BY CAST(h.tanggal AS DATE) DESC, h.id DESC";
$stmt = sqlsrv_query($conn, $sql, [$masterId]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil histori harga.']);
    exit;
}

$data = [];
$seen = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $harga = is_numeric($row['harga_rp'] ?? null) ? (float)$row['harga_rp'] : null;
    if ($harga === null) continue;

    $k = number_format($harga, 2, '.', '');
    if (isset($seen[$k])) continue;
    $seen[$k] = true;

    $tanggal = $row['tanggal'] ?? null;
    if ($tanggal instanceof DateTime) {
        $tanggal = $tanggal->format('d-m-Y');
    } else {
        $tanggal = $tanggal ? date('d-m-Y', strtotime((string)$tanggal)) : '';
    }

    $data[] = [
        'harga' => $harga,
        'tanggal' => $tanggal,
    ];

    if (count($data) >= 10) break;
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);


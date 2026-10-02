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

$sql = "SELECT TOP 20
            h.harga_rp_per_kg,
            MAX(CAST(h.tanggal AS DATE)) AS tanggal_terakhir
        FROM dbo.bb_21t_actom_harian h
        WHERE h.harga_rp_per_kg IS NOT NULL
        GROUP BY h.harga_rp_per_kg
        ORDER BY MAX(CAST(h.tanggal AS DATE)) DESC, h.harga_rp_per_kg DESC";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil histori harga.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $harga = is_numeric($row['harga_rp_per_kg'] ?? null) ? (float)$row['harga_rp_per_kg'] : null;
    if ($harga === null) continue;

    $tanggal = $row['tanggal_terakhir'] ?? null;
    if ($tanggal instanceof DateTime) {
        $tanggal = $tanggal->format('d-m-Y');
    } else {
        $tanggal = $tanggal ? date('d-m-Y', strtotime((string)$tanggal)) : '';
    }

    $data[] = [
        'harga' => $harga,
        'tanggal' => $tanggal,
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);


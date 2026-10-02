<?php
session_start();
header('Content-Type: application/json');

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$menuId = 236;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak melihat histori tarif.']);
    exit;
}

$sql = "SELECT TOP 20
            h.rp_per_kwh,
            MAX(CAST(h.tanggal AS DATE)) AS tanggal_terakhir
        FROM dbo.listrik_gardu_induk_harian h
        WHERE h.rp_per_kwh IS NOT NULL
        GROUP BY h.rp_per_kwh
        ORDER BY MAX(CAST(h.tanggal AS DATE)) DESC, h.rp_per_kwh DESC";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil histori tarif.']);
    exit;
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $tarif = is_numeric($row['rp_per_kwh'] ?? null) ? (float)$row['rp_per_kwh'] : null;
    if ($tarif === null) continue;

    $tanggal = $row['tanggal_terakhir'] ?? null;
    if ($tanggal instanceof DateTime) {
        $tanggal = $tanggal->format('d-m-Y');
    } else {
        $tanggal = $tanggal ? date('d-m-Y', strtotime((string)$tanggal)) : '';
    }

    $data[] = [
        'tarif' => $tarif,
        'tanggal' => $tanggal,
    ];
}
if ($stmt) sqlsrv_free_stmt($stmt);

echo json_encode(['success' => true, 'data' => $data]);


<?php
session_start();
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada akses.']);
    exit;
}

$tanggal = trim($_GET['tanggal'] ?? '');
if ($tanggal === '') {
    echo json_encode(['success' => false, 'message' => 'Tanggal kosong.']);
    exit;
}

$sql = "WITH cte AS (
            SELECT CAST(x.tanggal AS DATE) AS tanggal, x.id, x.lvbp_kwh, x.vbp_kwh, x.faktor_kali,
                   LEAD(x.lvbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS lvbp_next,
                   LEAD(x.vbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS vbp_next
            FROM dbo.listrik_gardu_induk_harian x
        )
        SELECT TOP 1
            CASE WHEN lvbp_next IS NULL OR vbp_next IS NULL THEN 0
                 ELSE (((lvbp_next - lvbp_kwh) * faktor_kali) + ((vbp_next - vbp_kwh) * faktor_kali))
            END AS kwh_pln
        FROM cte
        WHERE tanggal = ?
        ORDER BY id ASC";

$stmt = sqlsrv_query($conn, $sql, [$tanggal]);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Query gagal.']);
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($stmt) sqlsrv_free_stmt($stmt);

$kwh = is_numeric($row['kwh_pln'] ?? null) ? (float)$row['kwh_pln'] : 0;
echo json_encode(['success' => true, 'kwh_pln' => $kwh]);

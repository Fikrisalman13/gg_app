<?php
// pages/resep_obat/verify_proint_item.php
// Check if ProInt Code exists in Local Master Obat
require_once __DIR__ . '/../../../koneksi.php';

function checkLocalItem($prointCode) {
    global $conn;
    $sql = "SELECT kode_obat, nama_obat, group_obat, uom FROM dbo.resep_master_obat WHERE codeprod_proint = ?";
    $stmt = sqlsrv_query($conn, $sql, [$prointCode]);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row;
    }
    return null;
}
?>

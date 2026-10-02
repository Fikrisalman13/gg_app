<?php
// pages/resep_obat/check_duplicate_cus_color.php
session_start();
require_once __DIR__ . '/../../../koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['duplicate' => false]);
    exit;
}

$cusColor  = trim($_POST['cus_color'] ?? '');
$resepId   = intval($_POST['resep_id'] ?? 0); // Current record ID (for edit mode, exclude itself)

if (empty($cusColor)) {
    echo json_encode(['duplicate' => false]);
    exit;
}

// Check if cus_color exists in another record
if ($resepId > 0) {
    // Edit mode: exclude current record
    $sql = "SELECT TOP 1 id, resep_no FROM dbo.resep_obat WHERE cus_color = ? AND id <> ?";
    $params = [$cusColor, $resepId];
} else {
    // Add mode: check all records
    $sql = "SELECT TOP 1 id, resep_no FROM dbo.resep_obat WHERE cus_color = ?";
    $params = [$cusColor];
}

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo json_encode([
        'duplicate' => true,
        'id'        => $row['id'] ?? '-',
        'resep_no'  => $row['resep_no'] ?? '-'
    ]);
} else {
    echo json_encode(['duplicate' => false]);
}

if ($stmt) sqlsrv_free_stmt($stmt);
exit;

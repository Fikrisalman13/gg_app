<?php
// pages/resep_obat/get_grey_items.php
require_once __DIR__ . '/../../koneksi.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

$sql = "SELECT id, kode_gray, nama_artikel, gramasi, pickup, padry FROM dbo.master_artikel_grey";
if ($q) {
    $sql .= " WHERE kode_gray LIKE '%" . str_replace("'", "''", $q) . "%' OR nama_artikel LIKE '%" . str_replace("'", "''", $q) . "%'";
}
$sql .= " ORDER BY kode_gray";

$stmt = sqlsrv_query($conn, $sql);
$results = [];

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'text' => $row['kode_gray'] . ' - ' . $row['nama_artikel'],
            'item_data' => $row
        ];
    }
}

echo json_encode(['results' => $results]);
?>

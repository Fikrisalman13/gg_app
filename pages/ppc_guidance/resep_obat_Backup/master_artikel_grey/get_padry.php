<?php
// pages/resep_obat/master_artikel_grey/get_padry.php
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

$sql = "SELECT DISTINCT pdr FROM dbo.fab_m_problem WHERE pdr IS NOT NULL AND pdr != ''";
if ($q) {
    $sql .= " AND pdr LIKE '%" . str_replace("'", "''", $q) . "%'";
}
$sql .= " ORDER BY pdr";

$stmt = sqlsrv_query($conn, $sql);
$results = [];

// Add default option "-"
if (!$q || stripos('-', $q) !== false) {
    $results[] = ['id' => '-', 'text' => '-'];
}

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['pdr'],
            'text' => $row['pdr']
        ];
    }
}

echo json_encode(['results' => $results]);
?>

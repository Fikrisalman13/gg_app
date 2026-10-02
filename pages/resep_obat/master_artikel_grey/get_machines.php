<?php
require_once __DIR__ . '/../../../koneksi.php';
header('Content-Type: application/json');

$q = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT TOP 30 kode_mesin, nama_mesin
        FROM dbo.master_mesin_lab
        WHERE status = 'Active'
          AND (? = '' OR kode_mesin LIKE ? OR nama_mesin LIKE ?)
        ORDER BY nama_mesin";
$search = "%{$q}%";
$stmt = sqlsrv_query($conn, $sql, [$q, $search, $search]);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['results' => [], 'message' => 'Gagal memuat mesin.']);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['kode_mesin'],
        'text' => $row['kode_mesin'] . ' - ' . $row['nama_mesin'],
    ];
}
echo json_encode(['results' => $results]);

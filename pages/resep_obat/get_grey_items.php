<?php
require_once __DIR__ . '/../../koneksi.php';
header('Content-Type: application/json');

$q = trim((string) ($_GET['q'] ?? ''));
$search = "%{$q}%";
$sql = "SELECT TOP 30 grey.id, grey.kode_gray, grey.nama_artikel, grey.gramasi, grey.pickup, grey.padry,
               machine.kode_mesin AS machine_code, CASE WHEN machine.kode_mesin LIKE 'PD [1-6]' THEN 'Paddry ' + RIGHT(machine.kode_mesin, 1) ELSE machine.nama_mesin END AS machine_name
        FROM dbo.master_artikel_grey grey
        LEFT JOIN dbo.master_mesin_lab machine
          ON machine.kode_mesin = CASE
            WHEN LTRIM(RTRIM(grey.padry)) IN ('1','2','3','4','5','6')
              THEN 'PD ' + LTRIM(RTRIM(grey.padry))
            ELSE LTRIM(RTRIM(grey.padry))
          END
        WHERE (? = '' OR grey.kode_gray LIKE ? OR grey.nama_artikel LIKE ? OR machine.nama_mesin LIKE ?)
        ORDER BY grey.kode_gray, grey.nama_artikel";
$stmt = sqlsrv_query($conn, $sql, [$q, $search, $search, $search]);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['results' => [], 'message' => 'Gagal memuat artikel Grey.']);
    exit;
}

$results = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['id'],
        'text' => $row['kode_gray'] . ' - ' . $row['nama_artikel'],
        'item_data' => $row,
    ];
}
echo json_encode(['results' => $results]);

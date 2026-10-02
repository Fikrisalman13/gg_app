<?php
require '../../koneksi.php';
header('Content-Type: application/json');

// Ambil parameter dari DataTables
$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$search = $_GET['search']['value'] ?? '';

// Hitung total data (tanpa filter)
$totalQuery = "SELECT COUNT(*) as total FROM dbo.SMCacatDetail";
$totalStmt = sqlsrv_query($conn, $totalQuery);
$totalRow = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC);
$totalData = $totalRow['total'] ?? 0;

// Hitung total data setelah filter (recordsFiltered)
$where = "";
$params = [];

if (!empty($search)) {
    $where = "WHERE 
        c.NoCP LIKE ? OR
        c.NoDetail LIKE ? OR
        d.CacatKode LIKE ? OR
        d.CacatName LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

// Hitung total filter
$totalFiltered = $totalData;
if (!empty($where)) {
    $countFilteredQuery = "SELECT COUNT(*) as total FROM dbo.SMCacatDetail AS c 
                           LEFT JOIN dbo.SMCacat AS d ON c.CacatId = d.CacatId
                           $where";
    $countStmt = sqlsrv_query($conn, $countFilteredQuery, $params);
    $filteredRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalFiltered = $filteredRow['total'] ?? 0;
}

// Query data utama
$sql = "SELECT 
            c.Id,
            c.NoCP,
            c.NoDetail,
            d.CacatKode,
            d.CacatName,
            c.MeterKe,
            c.SMeterKe,
            c.PointCacat,
            c.UpdDate,
            c.UpdUser
        FROM dbo.SMCacatDetail AS c WITH (NOLOCK)
        LEFT JOIN dbo.SMCacat AS d WITH (NOLOCK) ON c.CacatId = d.CacatId
        $where
        ORDER BY c.UpdDate DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params[] = (int)$start;
$params[] = (int)$length;

$stmt = sqlsrv_query($conn, $sql, $params);

// Cek error
if ($stmt === false) {
    die(json_encode(['error' => sqlsrv_errors()]));
}

// Proses hasil query
$data = [];
$no = $start + 1;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $id = $row['Id'];

    $aksi = "<div class='btn-group'>";
    $aksi .= "<a href='edit_data_cacat.php?id={$id}' class='btn btn-warning btn-sm me-1'><i class='fas fa-edit'></i></a> ";
    $aksi .= "<button class='btn btn-danger btn-sm btn-delete' data-id='{$id}'><i class='fas fa-trash'></i></button>";
    $aksi .= "</div>";

    $data[] = [
        'no' => $no++,
        'NoCP' => htmlspecialchars($row['NoCP']),
        'NoDetail' => htmlspecialchars($row['NoDetail']),
        'CacatKode' => htmlspecialchars($row['CacatKode']),
        'MeterKe' => htmlspecialchars($row['MeterKe']),
        'SMeterKe' => htmlspecialchars($row['SMeterKe']),
        'PointCacat' => htmlspecialchars($row['PointCacat']),
        'UpdDate' => $row['UpdDate'] ? $row['UpdDate']->format('Y-m-d H:i:s') : '-',
        'aksi' => $aksi
    ];
}

// JSON response ke DataTables
$response = [
    "draw" => intval($draw),
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
];

echo json_encode($response);

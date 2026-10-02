<?php
require '../../koneksi.php';
header('Content-Type: application/json');

$draw = $_GET['draw'];
$start = $_GET['start'];
$length = $_GET['length'];
$search = $_GET['search']['value'];

// Hitung total data
$totalQuery = "SELECT COUNT(*) as total FROM dbo.FormInspectHd";
$totalStmt = sqlsrv_query($conn, $totalQuery);
$totalRow = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC);
$totalData = $totalRow['total'];

// Query data utama
$sql = "SELECT 
            hd.NoCP, 
            hd.InspectDate, 
            emp.EmpName, 
            art.ArtikelKode, 
            hd.Lot, 
            hd.UpdUser
        FROM dbo.FormInspectHd AS hd WITH (NOLOCK)
        LEFT JOIN dbo.SMEmployeeInspector AS emp WITH (NOLOCK) ON hd.EmpId = emp.EmpId
        LEFT JOIN dbo.SMArtikel AS art WITH (NOLOCK) ON hd.ArtikelId = art.ArtikelId
        WHERE 
            hd.NoCP LIKE ? OR 
            emp.EmpName LIKE ? OR 
            art.ArtikelKode LIKE ?
        ORDER BY hd.InspectDate DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params = [
    "%$search%", "%$search%", "%$search%",
    (int)$start, (int)$length
];

$stmt = sqlsrv_query($conn, $sql, $params);

$data = [];
$no = $start + 1;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $noCP = urlencode($row['NoCP']);

    $aksiButtons = "
        <a href='view_inspecting_weaving.php?id={$noCP}' class='btn btn-info btn-sm'><i class='fas fa-eye'></i></a>
        <a href='edit_inspecting_weaving.php?id={$noCP}' class='btn btn-warning btn-sm' title='Edit'><i class='fas fa-edit'></i></a>
        <button class='btn btn-danger btn-sm btn-delete' data-id='{$row['NoCP']}'><i class='fas fa-trash'></i></button>
";

    $data[] = [
        'no' => $no++,
        'NoCP' => htmlspecialchars($row['NoCP']),
        'InspectDate' => $row['InspectDate'] ? $row['InspectDate']->format('d/m/Y') : '-',
        'EmpName' => htmlspecialchars($row['EmpName']),
        'ArtikelKode' => htmlspecialchars($row['ArtikelKode']),
        'Lot' => htmlspecialchars($row['Lot']),
        'UpdUser' => htmlspecialchars($row['UpdUser']),
        'aksi' => $aksiButtons
    ];
}

$response = [
    "draw" => intval($draw),
    "recordsTotal" => $totalData,
    "recordsFiltered" => $totalData, // jika pakai filter pencarian + tanggal, ini bisa disesuaikan
    "data" => $data
];

echo json_encode($response);

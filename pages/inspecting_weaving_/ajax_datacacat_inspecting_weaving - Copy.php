<?php
// ===================================================
// 1. INISIALISASI
// ===================================================
require '../../koneksi.php';
header('Content-Type: application/json');

// Validasi koneksi
if (!$conn) {
    echo json_encode([
        'draw' => 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Koneksi database gagal.'
    ]);
    exit;
}

// ===================================================
// 2. AMBIL PARAMETER DATATABLES
// ===================================================
$draw   = isset($_GET['draw'])   ? intval($_GET['draw'])   : 1;
$start  = isset($_GET['start'])  ? intval($_GET['start'])  : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
$search = $_GET['search']['value'] ?? '';


// ===================================================
// 3. HITUNG TOTAL (TANPA FILTER)
// ===================================================
$totalQuery = "SELECT COUNT(*) AS total FROM dbo.SMCacatDetail WITH (NOLOCK)";
$totalStmt  = sqlsrv_query($conn, $totalQuery);
$totalRow   = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC);
$totalData  = $totalRow['total'] ?? 0;


// ===================================================
// 4. KONSTRUKSI WHERE DYNAMIC
// ===================================================
$where = "";
$params = [];

if (!empty($search)) {
    $where = "
        WHERE 
            c.NoCP LIKE ? OR
            c.NoDetail LIKE ? OR
            d.CacatKode LIKE ? OR
            d.CacatName LIKE ?
    ";
    $params = [
        "%$search%",
        "%$search%",
        "%$search%",
        "%$search%"
    ];
}


// ===================================================
// 5. HITUNG TOTAL FILTER
// ===================================================
$totalFiltered = $totalData;

if (!empty($where)) {
    $countFilteredQuery = "
        SELECT COUNT(*) AS total
        FROM dbo.SMCacatDetail c WITH (NOLOCK)
        LEFT JOIN dbo.SMCacat d WITH (NOLOCK) ON c.CacatId = d.CacatId
        $where
    ";

    $countStmt = sqlsrv_query($conn, $countFilteredQuery, $params);
    $filteredRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalFiltered = $filteredRow['total'] ?? 0;
}


// ===================================================
// 6. QUERY DATA UTAMA
// ===================================================
$dataQuery = "
    SELECT 
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
    FROM dbo.SMCacatDetail c WITH (NOLOCK)
    LEFT JOIN dbo.SMCacat d WITH (NOLOCK) ON c.CacatId = d.CacatId
    $where
    ORDER BY c.UpdDate DESC
    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";

$paramsData = array_merge($params, [$start, $length]);

$stmt = sqlsrv_query($conn, $dataQuery, $paramsData);


// ===================================================
// 7. HANDLING ERROR QUERY
// ===================================================
if ($stmt === false) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => sqlsrv_errors()
    ]);
    exit;
}


// ===================================================
// 8. PROSES DATA
// ===================================================
$data = [];
$no = $start + 1;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $id = (int)$row['Id'];

    // Tombol aksi
    $aksi = "
        <div class='btn-group'>
            <a href='edit_data_cacat.php?id={$id}' class='btn btn-warning btn-sm me-1'>
                <i class='fas fa-edit'></i>
            </a>
            <button class='btn btn-danger btn-sm btn-delete' data-id='{$id}'>
                <i class='fas fa-trash'></i>
            </button>
        </div>
    ";

    $data[] = [
        'no'         => $no++,
        'NoCP'       => htmlspecialchars($row['NoCP']),
        'NoDetail'   => htmlspecialchars($row['NoDetail']),
        'CacatKode'  => htmlspecialchars($row['CacatKode']),
        'MeterKe'    => htmlspecialchars($row['MeterKe']),
        'SMeterKe'   => htmlspecialchars($row['SMeterKe']),
        'PointCacat' => htmlspecialchars($row['PointCacat']),
        'UpdDate'    => $row['UpdDate'] ? $row['UpdDate']->format('Y-m-d H:i:s') : '-',
        'aksi'       => $aksi
    ];
}


// ===================================================
// 9. KIRIM JSON KE DATATABLES
// ===================================================
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data
]);


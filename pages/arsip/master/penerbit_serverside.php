<?php
header('Content-Type: application/json');
include('../../../koneksi.php');

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Total Count
$stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM arsip_penerbit");
$totalRecords = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['total'];

// Filter search
$where = "";
$params = [];
if (!empty($searchValue)) {
    $where = " WHERE nama_penerbit LIKE ? OR keterangan LIKE ?";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
}

// Filtered Count
$stmtFiltered = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM arsip_penerbit" . $where, $params);
$recordsFiltered = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)['total'];

// Data Query
$orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';
$columns = ['id_penerbit', 'nama_penerbit', 'keterangan'];
$orderBy = $columns[$orderColumnIndex] ?? 'id_penerbit';

$dataQuery = "SELECT * FROM arsip_penerbit" . $where . 
             " ORDER BY $orderBy $orderDir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

$stmtData = sqlsrv_query($conn, $dataQuery, $params);
$data = [];
$no = $start + 1;

if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            "no" => $no++,
            "nama_penerbit" => htmlspecialchars($row['nama_penerbit']),
            "keterangan" => htmlspecialchars($row['keterangan'] ?? '-'),
            "aksi" => '<button class="btn btn-warning btn-sm btn-edit-penerbit" data-id="'.$row['id_penerbit'].'" data-nama="'.$row['nama_penerbit'].'" data-ket="'.$row['keterangan'].'" title="Edit"><i class="fas fa-edit"></i></button> <button class="btn btn-danger btn-sm btn-delete-penerbit" data-id="'.$row['id_penerbit'].'" title="Hapus"><i class="fas fa-trash"></i></button>'
        ];
    }
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $recordsFiltered,
    "data" => $data
]);
?>

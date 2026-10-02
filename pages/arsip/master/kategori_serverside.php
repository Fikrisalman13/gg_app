<?php
header('Content-Type: application/json');
include('../../../koneksi.php');

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Base Query
$countQuery = "SELECT COUNT(*) as total FROM arsip_kategori";
$stmtCount = sqlsrv_query($conn, $countQuery);
$totalRecords = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['total'];

// Filter search
$where = "";
$params = [];
if (!empty($searchValue)) {
    $where = " WHERE nama_kategori LIKE ?";
    $params[] = "%$searchValue%";
}

// Filtered Count
$filteredCountQuery = "SELECT COUNT(*) as total FROM arsip_kategori" . $where;
$stmtFiltered = sqlsrv_query($conn, $filteredCountQuery, $params);
$recordsFiltered = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)['total'];

// Data Query
$orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';
$columns = ['id_kategori', 'nama_kategori'];
$orderBy = $columns[$orderColumnIndex] ?? 'id_kategori';

$dataQuery = "SELECT * FROM arsip_kategori" . $where . 
             " ORDER BY $orderBy $orderDir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

$stmtData = sqlsrv_query($conn, $dataQuery, $params);
$data = [];
$no = $start + 1;

if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            "no" => $no++,
            "nama_kategori" => htmlspecialchars($row['nama_kategori']),
            "aksi" => '<button class="btn btn-warning btn-sm btn-edit-kategori" data-id="'.$row['id_kategori'].'" data-nama="'.$row['nama_kategori'].'" title="Edit"><i class="fas fa-edit"></i></button> <button class="btn btn-danger btn-sm btn-delete-kategori" data-id="'.$row['id_kategori'].'" title="Hapus"><i class="fas fa-trash"></i></button>'
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

<?php
header('Content-Type: application/json');
include('../../../koneksi.php');

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Base Query with Join
$baseSql = "FROM arsip_rak r LEFT JOIN arsip_kategori k ON r.id_kategori = k.id_kategori";

// Total Count
$stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM arsip_rak");
$totalRecords = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['total'];

// Filter search
$where = "";
$params = [];
if (!empty($searchValue)) {
    $where = " WHERE r.nama_rak LIKE ? OR k.nama_kategori LIKE ?";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
}

// Filtered Count
$stmtFiltered = sqlsrv_query($conn, "SELECT COUNT(*) as total $baseSql" . $where, $params);
$recordsFiltered = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)['total'];

// Data Query
$orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';
$columns = ['r.id_rak', 'r.nama_rak', 'k.nama_kategori'];
$orderBy = $columns[$orderColumnIndex] ?? 'r.id_rak';

$dataQuery = "SELECT r.*, k.nama_kategori $baseSql" . $where . 
             " ORDER BY $orderBy $orderDir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

$stmtData = sqlsrv_query($conn, $dataQuery, $params);
$data = [];
$no = $start + 1;

if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            "no" => $no++,
            "nama_rak" => htmlspecialchars($row['nama_rak']),
            "nama_kategori" => htmlspecialchars($row['nama_kategori'] ?? '-'),
            "aksi" => '<button class="btn btn-warning btn-sm btn-edit-rak" data-id="'.$row['id_rak'].'" data-nama="'.$row['nama_rak'].'" data-kategori="'.$row['id_kategori'].'" title="Edit"><i class="fas fa-edit"></i></button> <button class="btn btn-danger btn-sm btn-delete-rak" data-id="'.$row['id_rak'].'" title="Hapus"><i class="fas fa-trash"></i></button>'
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

<?php
header('Content-Type: application/json');
include('../../koneksi.php');

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Filters
$filterKategori = $_POST['kategori'] ?? '';
$filterRak = $_POST['rak'] ?? '';
$filterTahun = $_POST['tahun'] ?? '';

// Base Query with Joins
$baseSql = "FROM arsip_data a 
            LEFT JOIN arsip_kategori k ON a.id_kategori = k.id_kategori 
            LEFT JOIN arsip_rak r ON a.id_rak = r.id_rak 
            LEFT JOIN arsip_penerbit p ON a.id_penerbit = p.id_penerbit 
            LEFT JOIN arsip_penyusun s ON a.id_penyusun = s.id_penyusun";

// Total Count
$stmtTotal = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM arsip_data");
$totalRecords = sqlsrv_fetch_array($stmtTotal, SQLSRV_FETCH_ASSOC)['total'];

// Filter search and custom filters
$where = " WHERE 1=1";
$params = [];

if (!empty($searchValue)) {
    $where .= " AND (a.kode_arsip LIKE ? OR a.judul_arsip LIKE ? OR k.nama_kategori LIKE ? OR p.nama_penerbit LIKE ? OR s.nama_penyusun LIKE ?)";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
    $params[] = "%$searchValue%";
}

if (!empty($filterKategori)) {
    $where .= " AND a.id_kategori = ?";
    $params[] = $filterKategori;
}

if (!empty($filterRak)) {
    $where .= " AND a.id_rak = ?";
    $params[] = $filterRak;
}

if (!empty($filterTahun)) {
    $where .= " AND a.tahun_terbit = ?";
    $params[] = $filterTahun;
}

// Filtered Count
$stmtFiltered = sqlsrv_query($conn, "SELECT COUNT(*) as total $baseSql" . $where, $params);
$recordsFiltered = sqlsrv_fetch_array($stmtFiltered, SQLSRV_FETCH_ASSOC)['total'];

// Data Query
$orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
$orderDir = $_POST['order'][0]['dir'] ?? 'desc';
$columns = ['a.id_arsip', 'a.kode_arsip', 'a.judul_arsip', 'k.nama_kategori', 'p.nama_penerbit', 's.nama_penyusun', 'a.stok', 'r.nama_rak', 'a.tahun_terbit'];
$orderBy = $columns[$orderColumnIndex] ?? 'a.id_arsip';

$dataQuery = "SELECT a.*, k.nama_kategori, r.nama_rak, p.nama_penerbit, s.nama_penyusun $baseSql $where 
             ORDER BY $orderBy $orderDir OFFSET $start ROWS FETCH NEXT $length ROWS ONLY";

$stmtData = sqlsrv_query($conn, $dataQuery, $params);
$data = [];
$no = $start + 1;

if ($stmtData) {
    while ($row = sqlsrv_fetch_array($stmtData, SQLSRV_FETCH_ASSOC)) {
        $data[] = [
            "no" => '<div class="text-center">'.$no++.'</div>',
            "id_arsip" => $row['id_arsip'],
            "kode_arsip" => htmlspecialchars($row['kode_arsip']),
            "judul_arsip" => htmlspecialchars($row['judul_arsip']),
            "kategori" => htmlspecialchars($row['nama_kategori'] ?? '-'),
            "penerbit" => htmlspecialchars($row['nama_penerbit'] ?? '-'),
            "penyusun" => htmlspecialchars($row['nama_penyusun'] ?? '-'),
            "stok" => '<div class="text-center">'.$row['stok'] . " / " . $row['stok_total'].'</div>',
            "rak" => htmlspecialchars($row['nama_rak'] ?? '-'),
            "tahun_terbit" => $row['tahun_terbit'],
            "aksi" => '<a href="edit_arsip.php?id='.$row['id_arsip'].'" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a> <button class="btn btn-danger btn-sm btn-delete-arsip" data-id="'.$row['id_arsip'].'" title="Hapus"><i class="fas fa-trash"></i></button>'
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

<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

// Validasi akses
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$table = $_GET['table'] ?? '';
$id_col = $_GET['id_col'] ?? 'id';
$name_col = $_GET['name_col'] ?? 'name';
$where = $_GET['where'] ?? '';

// Validasi tabel yang diizinkan
$allowed_tables = ['m_kode_dok', 'm_kategori_dok', 'm_dept', 'm_bag', 'm_subbag'];
if (!in_array($table, $allowed_tables)) {
    echo json_encode(['error' => 'Invalid table']);
    exit;
}

// Kolom data tambahan untuk atribut
$extra_columns = [
    'm_dept' => ', sn_dept as data_attr',
    'm_bag' => ', sn_bagian as data_attr',
    'm_subbag' => ', sn_subbag as data_attr'
];

$query = "SELECT $id_col as id, $name_col as name" . ($extra_columns[$table] ?? '') . " FROM $table";
if (!empty($where)) {
    $query .= " WHERE $where";
}
$query .= " ORDER BY $name_col";

$stmt = sqlsrv_query($conn, $query);
if ($stmt === false) {
    echo json_encode(['error' => 'Database error: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

$options = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $option = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
    if (isset($row['data_attr'])) {
        $option['data_attr'] = $row['data_attr'];
    }
    $options[] = $option;
}

echo json_encode($options);
sqlsrv_free_stmt($stmt);
?>
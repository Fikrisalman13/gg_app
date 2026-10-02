<?php
session_start();
include '../../koneksi.php';

header('Content-Type: text/html; charset=utf-8');

// Debugging - log the request
error_log("GET Request: " . print_r($_GET, true));

if (!isset($_SESSION['UserName'])) {
    die("Unauthorized access");
}

$table = $_GET['table'] ?? '';
$id_col = $_GET['id_col'] ?? '';
$name_col = $_GET['name_col'] ?? '';
$where = $_GET['where'] ?? '';

if (empty($table) || empty($id_col) || empty($name_col)) {
    die("Invalid parameters");
}

// Debugging - log the query parameters
error_log("Table: $table, ID Column: $id_col, Name Column: $name_col, Where: $where");

$query = "SELECT $id_col, $name_col FROM $table";
if (!empty($where)) {
    $query .= " WHERE $where";
}
$query .= " ORDER BY $name_col";

// Debugging - log the final query
error_log("Executing query: $query");

$stmt = sqlsrv_query($conn, $query);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    error_log("Query error: " . print_r($errors, true));
    die("Error in query execution: " . print_r($errors, true));
}

$options = '<option value="">-- Pilih --</option>';
$count = 0;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $options .= "<option value='{$row[$id_col]}'>" . htmlspecialchars($row[$name_col]) . "</option>";
    $count++;
}

// Debugging - log the results
error_log("Found $count options for $table");

echo $options;
sqlsrv_free_stmt($stmt);
?>

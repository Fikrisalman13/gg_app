<?php
require_once '../../koneksi.php';
$sql = file_get_contents('config_table.sql');
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) die(print_r(sqlsrv_errors(), true));
echo "Table ensured.";
?>

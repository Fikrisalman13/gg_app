<?php
require_once '../../koneksi.php';
$sql = file_get_contents('create_master_obat.sql');
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) die(print_r(sqlsrv_errors(), true));
echo "Table master_obat ensured.";
?>

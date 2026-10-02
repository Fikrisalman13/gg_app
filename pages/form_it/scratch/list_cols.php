<?php
require_once 'c:/xampp/htdocs/gg_app/koneksi.php';
$stmt = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Buka_Tanggal_Closingan'");
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo $row['COLUMN_NAME'] . "\n";
}
?>

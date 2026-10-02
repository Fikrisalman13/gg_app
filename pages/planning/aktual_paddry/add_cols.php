<?php
include '../../../koneksi.php';
$sql = "ALTER TABLE dbo.cpp_paddry ADD no_roda NVARCHAR(50), ket_delay NVARCHAR(MAX)";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    echo "Success\n";
} else {
    print_r(sqlsrv_errors());
}
?>

<?php
include '../../../koneksi.php';
// Ensure server uses Jakarta timezone for issue timestamps
date_default_timezone_set('Asia/Jakarta');

$sql = "ALTER TABLE dbo.cpp_paddry ADD no_roda NVARCHAR(50), ket_delay NVARCHAR(MAX)";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    echo "Success\n";
} else {
    print_r(sqlsrv_errors());
}
?>
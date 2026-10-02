<?php
include '../../../koneksi.php';
$sql = "SELECT TOP 0 * FROM dbo.cpp_paddry";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    $metadata = sqlsrv_field_metadata($stmt);
    foreach ($metadata as $field) {
        echo $field['Name'] . "\n";
    }
} else {
    print_r(sqlsrv_errors());
}
?>

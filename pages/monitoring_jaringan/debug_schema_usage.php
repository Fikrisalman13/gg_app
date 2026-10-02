<?php
require_once '../../koneksi.php';

$sql = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'internet_usage_reports'
        ORDER BY ORDINAL_POSITION";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    print_r(sqlsrv_errors());
    exit(1);
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo $row['COLUMN_NAME'] . ' | ' . $row['DATA_TYPE'] . ' | ' . $row['IS_NULLABLE'] . PHP_EOL;
}

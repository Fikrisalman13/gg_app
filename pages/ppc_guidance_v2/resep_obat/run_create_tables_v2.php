<?php
require_once __DIR__ . '/../../../koneksi.php';

echo "<h2>Creating V2 Database Tables</h2>\n";

$sql = file_get_contents(__DIR__ . '/create_tables_v2.sql');

// Split queries by statements or execute
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit(1);
} else {
    echo "SQL executed successfully.\n";
    do {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // consume
        }
    } while (sqlsrv_next_result($stmt));
}

echo "Done.\n";

<?php
// pages/resep_obat/add_prod_info_columns.php
require_once __DIR__ . '/../../koneksi.php';

echo "Checking columns...\n";

// Function to check and add column
function addColumnIfNeeded($conn, $table, $column, $type) {
    // Check if column exists
    $sqlCheck = "SELECT COL_LENGTH(?, ?) AS ColLen";
    $paramsCheck = [$table, $column];
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);
    
    if ($stmtCheck === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    
    if ($row && $row['ColLen'] !== null) {
        echo "Column [$column] already exists in table [$table].\n";
    } else {
        echo "Adding column [$column] to table [$table]...\n";
        $sqlAdd = "ALTER TABLE $table ADD $column $type";
        $stmtAdd = sqlsrv_query($conn, $sqlAdd);
        
        if ($stmtAdd === false) {
            die("Error adding column: " . print_r(sqlsrv_errors(), true));
        }
        echo "Column [$column] added successfully.\n";
    }
}

addColumnIfNeeded($conn, 'resep_obat', 'resep_prod_code', 'VARCHAR(100)');
addColumnIfNeeded($conn, 'resep_obat', 'resep_prod_name', 'VARCHAR(255)');

echo "Migration completed.\n";
?>

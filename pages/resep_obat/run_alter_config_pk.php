<?php
// pages/resep_obat/run_alter_config_pk.php
require_once '../../koneksi.php';

try {
    // Check if column exists
    $sqlCheck = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'resep_config' AND COLUMN_NAME = 'panjang_kain'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck);
    
    if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
        echo "Column 'panjang_kain' already exists.<br>";
    } else {
        // Add column with default 3500
        $sqlAlter = "ALTER TABLE dbo.resep_config ADD panjang_kain DECIMAL(18,2) NULL DEFAULT 3500";
        $stmtAlter = sqlsrv_query($conn, $sqlAlter);
        
        if ($stmtAlter === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
        
        // Update existing row (assuming only 1 config row exists usually)
        $sqlUpdate = "UPDATE dbo.resep_config SET panjang_kain = 3500 WHERE panjang_kain IS NULL";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate);
        
        echo "Column 'panjang_kain' added successfully.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

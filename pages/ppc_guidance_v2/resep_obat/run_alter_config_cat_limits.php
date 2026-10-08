<?php
// pages/resep_obat/run_alter_config_cat_limits.php
require_once '../../../koneksi.php';

try {
    // Check if column exists
    $sqlCheck = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'resep_config' AND COLUMN_NAME = 'category_limits'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck);
    
    if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
        echo "Column 'category_limits' already exists.<br>";
    } else {
        // Add column NVARCHAR(MAX) to store JSON
        $sqlAlter = "ALTER TABLE dbo.resep_config ADD category_limits NVARCHAR(MAX) NULL";
        $stmtAlter = sqlsrv_query($conn, $sqlAlter);
        
        if ($stmtAlter === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
        
        // Init with empty JSON object if NULL
        $sqlUpdate = "UPDATE dbo.resep_config SET category_limits = '{}' WHERE category_limits IS NULL";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate);
        
        echo "Column 'category_limits' added successfully.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

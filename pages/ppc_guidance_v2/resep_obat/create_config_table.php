<?php
require_once '../../../koneksi.php';

// Safe check and create
$sqlCheck = "SELECT OBJECT_ID('dbo.resep_config', 'U') as id";
$stmtCheck = sqlsrv_query($conn, $sqlCheck);
$row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

if (!$row || is_null($row['id'])) {
    $sqlCreate = "
    CREATE TABLE dbo.resep_config (
        id INT PRIMARY KEY IDENTITY(1,1),
        min_cost DECIMAL(18,2) NULL,
        max_cost DECIMAL(18,2) NULL,
        updated_at DATETIME,
        updated_by VARCHAR(50)
    );
    INSERT INTO dbo.resep_config (min_cost, max_cost, updated_at, updated_by) VALUES (NULL, NULL, GETDATE(), 'SYSTEM');
    ";
    
    $stmtCreate = sqlsrv_query($conn, $sqlCreate);
    if ($stmtCreate === false) {
        die("Error creating table: " . print_r(sqlsrv_errors(), true));
    }
    echo "Table created.";
} else {
    echo "Table already exists.";
}
?>

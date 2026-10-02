<?php
require_once '../../koneksi.php';

$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='orderitem_gistex_dt' AND xtype='U')
CREATE TABLE orderitem_gistex_dt (
    id INT IDENTITY(1,1) PRIMARY KEY,
    uniqueid_parent NVARCHAR(100) NOT NULL,
    balenmbr NVARCHAR(100) NULL,
    prodname NVARCHAR(255) NULL,
    prodcode NVARCHAR(100) NULL,
    batchno NVARCHAR(100) NULL,
    qtym DECIMAL(18, 4) NULL,
    qtyyard DECIMAL(18, 4) NULL,
    stdqty DECIMAL(18, 4) NULL,
    lot NVARCHAR(100) NULL,
    create_date DATETIME2(0) NULL,
    created_by NVARCHAR(100) NULL
);
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    echo "Table orderitem_gistex_dt created/verified successfully.";
} else {
    echo "Error: " . print_r(sqlsrv_errors(), true);
}

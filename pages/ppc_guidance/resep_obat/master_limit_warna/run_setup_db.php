<?php
// pages/resep_obat/master_limit_warna/run_setup_db.php
require_once __DIR__ . '/../../../../koneksi.php';

$table = "resep_limit_color";
$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='$table' AND xtype='U')
BEGIN
    CREATE TABLE dbo.$table (
        id INT IDENTITY(1,1) PRIMARY KEY,
        kode_warna VARCHAR(50) NOT NULL UNIQUE,
        max_cost DECIMAL(18, 2) DEFAULT 0,
        max_cf_disperse DECIMAL(10, 2) DEFAULT 0,
        max_cf_reactive DECIMAL(10, 2) DEFAULT 0,
        max_cf_total DECIMAL(10, 2) DEFAULT 0,
        
        created_at DATETIME DEFAULT GETDATE(),
        created_by VARCHAR(50),
        updated_at DATETIME,
        updated_by VARCHAR(50)
    );
    PRINT 'Table $table Created Successfully.';
END
ELSE
BEGIN
    PRINT 'Table $table already exists.';
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Setup OK.";
}
?>

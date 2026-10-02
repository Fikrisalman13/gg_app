<?php
// pages/resep_obat/master_artikel_grey/run_setup.php
require_once __DIR__ . '/../../../koneksi.php';

echo "<h2>Setup Master Artikel Grey</h2>";

$sqlTable = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='master_artikel_grey' AND xtype='U')
BEGIN
    CREATE TABLE dbo.master_artikel_grey (
        id INT IDENTITY(1,1) PRIMARY KEY,
        kode_gray VARCHAR(50) NOT NULL UNIQUE,
        nama_artikel VARCHAR(255) NOT NULL,
        gramasi DECIMAL(10,2),
        pickup DECIMAL(10,2),
        padry VARCHAR(50),
        created_at DATETIME DEFAULT GETDATE(),
        created_by VARCHAR(50),
        updated_at DATETIME,
        updated_by VARCHAR(50)
    );
    PRINT 'Table master_artikel_grey created.';
END
ELSE
BEGIN
    PRINT 'Table master_artikel_grey already exists.';
END
";

$stmt = sqlsrv_query($conn, $sqlTable);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Check/Create Table Success.<br>";
}

// NOTE: Sidebar & Permissions are handled MANUALLY by User as requested.

echo "Setup Complete.";
?>

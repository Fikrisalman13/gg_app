<?php
require_once '../../koneksi.php';

$sql = "
IF OBJECT_ID('dbo.resep_obat', 'U') IS NULL
CREATE TABLE dbo.resep_obat (
    id INT IDENTITY(1,1) PRIMARY KEY,
    no_cp VARCHAR(50),
    kode_warna VARCHAR(50),
    lot_no VARCHAR(50),
    weight DECIMAL(18,2),
    plan_qty DECIMAL(18,2),
    created_at DATETIME DEFAULT GETDATE(),
    created_by VARCHAR(50),
    updated_at DATETIME,
    updated_by VARCHAR(50)
);

IF OBJECT_ID('dbo.resep_obat_detail', 'U') IS NULL
CREATE TABLE dbo.resep_obat_detail (
    id INT IDENTITY(1,1) PRIMARY KEY,
    id_resep INT,
    kode VARCHAR(50),
    name VARCHAR(100),
    receipe DECIMAL(18,3),
    uom VARCHAR(10),
    cc VARCHAR(50),
    std_price DECIMAL(18,2),
    total DECIMAL(18,2),
    created_at DATETIME DEFAULT GETDATE(),
    created_by VARCHAR(50),
    updated_at DATETIME,
    updated_by VARCHAR(50),
    FOREIGN KEY (id_resep) REFERENCES dbo.resep_obat(id) ON DELETE CASCADE
);
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Tables created successfully.";
}
?>

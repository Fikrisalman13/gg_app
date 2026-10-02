<?php
// pages/resep_obat/migrate_resep_machines.php
session_start();
require_once __DIR__ . '/../../../koneksi.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Database Migration: Multi-Machine Support</h2>";

// 1. Create table resep_obat_machines
$sqlCreate = "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'resep_obat_machines' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE dbo.resep_obat_machines (
        id INT IDENTITY(1,1) PRIMARY KEY,
        id_resep INT NOT NULL,
        machine_code VARCHAR(50),
        machine_name VARCHAR(255),
        speed DECIMAL(10,2),
        temperature DECIMAL(10,2),
        temperature_ch2 DECIMAL(10,2),
        temperature_ch3 DECIMAL(10,2),
        temperature_ch4 DECIMAL(10,2),
        temperature_ch5 DECIMAL(10,2),
        temperature_ch6 DECIMAL(10,2),
        temperature_ch7 DECIMAL(10,2),
        temperature_ch8 DECIMAL(10,2),
        temperature_ch9 DECIMAL(10,2),
        temperature_ch10 DECIMAL(10,2),
        temperature_ch11 DECIMAL(10,2),
        temperature_ch12 DECIMAL(10,2),
        lebar_kain DECIMAL(10,2),
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        created_by VARCHAR(50),
        update_at DATETIME NOT NULL DEFAULT GETDATE(),
        update_by VARCHAR(50),
        CONSTRAINT FK_resep_machines_header FOREIGN KEY (id_resep) REFERENCES dbo.resep_obat(id) ON DELETE CASCADE
    );
    PRINT 'Table resep_obat_machines created.';
END
ELSE
BEGIN
    PRINT 'Table resep_obat_machines already exists.';
END";

$stmtCreate = sqlsrv_query($conn, $sqlCreate);
if ($stmtCreate === false) {
    die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}
echo "Step 1: Create Table ... <span style='color:green'>SUCCESS</span><br>";

// 2. Migrate existing data
$sqlCheckData = "SELECT COUNT(1) as total FROM dbo.resep_obat_machines";
$stmtCheckData = sqlsrv_query($conn, $sqlCheckData);
$hasData = 0;
if ($stmtCheckData && $row = sqlsrv_fetch_array($stmtCheckData, SQLSRV_FETCH_ASSOC)) {
    $hasData = (int)$row['total'];
}

if ($hasData === 0) {
    echo "Step 2: Migrating existing data from resep_obat ... ";
    $sqlMigrate = "INSERT INTO dbo.resep_obat_machines 
                   (id_resep, machine_code, machine_name, speed, temperature, temperature_ch2, lebar_kain, created_at, created_by, update_at, update_by)
                   SELECT id, machine_code, machine_name, speed, temperature, temperature_ch2, lebar_kain, updated_at, updated_by, updated_at, updated_by
                   FROM dbo.resep_obat
                   WHERE machine_code IS NOT NULL AND machine_code <> ''";
    
    $stmtMigrate = sqlsrv_query($conn, $sqlMigrate);
    if ($stmtMigrate === false) {
        echo "<span style='color:red'>FAILED</span><br>";
        echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
    } else {
        $rows = sqlsrv_rows_affected($stmtMigrate);
        echo "<span style='color:green'>SUCCESS ($rows rows migrated)</span><br>";
    }
} else {
    echo "Step 2: Migration skipped (table already has data).<br>";
}

echo "<br><p><b>Selesai.</b> Anda sekarang bisa menggunakan fitur multi-mesin.</p>";

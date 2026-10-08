<?php
// pages/resep_obat/run_alter_resep_v2.php
require_once __DIR__ . '/../../../koneksi.php';

echo "<h2>Alter Resep Obat (Add Kode Grey & Vlot)</h2>";

$sql = "
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('dbo.resep_obat') AND name = 'kode_grey')
BEGIN
    ALTER TABLE dbo.resep_obat ADD kode_grey VARCHAR(50) NULL;
    PRINT 'Column kode_grey added.';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('dbo.resep_obat') AND name = 'vlot')
BEGIN
    ALTER TABLE dbo.resep_obat ADD vlot DECIMAL(10,2) NULL;
    PRINT 'Column vlot added.';
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Alter Table Success.<br>";
}
?>

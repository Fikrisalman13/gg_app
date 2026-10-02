<?php
// pages/resep_obat/master_obat/run_alter_uom.php
require_once __DIR__ . '/../../../koneksi.php';

echo "<h2>Alter Master Obat (Add UOM)</h2>";

$sql = "
IF NOT EXISTS (
    SELECT * 
    FROM sys.columns 
    WHERE object_id = OBJECT_ID('dbo.resep_master_obat') 
    AND name = 'uom'
)
BEGIN
    ALTER TABLE dbo.resep_master_obat ADD uom VARCHAR(20) NULL;
    PRINT 'Column uom added to resep_master_obat';
END
ELSE
BEGIN
    PRINT 'Column uom already exists';
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Alter Table Success. UOM added.<br>";
}
?>

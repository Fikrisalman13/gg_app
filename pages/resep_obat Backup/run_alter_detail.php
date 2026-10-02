<?php
// pages/resep_obat/run_alter_detail.php
require_once __DIR__ . '/../../koneksi.php';

echo "<h2>Alter Detail Resep (Add Category, CF, Uom CF)</h2>";

$sql = "
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('dbo.resep_obat_detail') AND name = 'category')
BEGIN
    ALTER TABLE dbo.resep_obat_detail ADD category VARCHAR(100) NULL;
    PRINT 'Column category added.';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('dbo.resep_obat_detail') AND name = 'cf')
BEGIN
    ALTER TABLE dbo.resep_obat_detail ADD cf DECIMAL(18,6) NULL;
    PRINT 'Column cf added.';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('dbo.resep_obat_detail') AND name = 'uom_cf')
BEGIN
    ALTER TABLE dbo.resep_obat_detail ADD uom_cf VARCHAR(20) NULL;
    PRINT 'Column uom_cf added.';
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Alter Detail Table Success.<br>";
}
?>

<?php
// pages/resep_obat/run_alter_detail_v3.php
require_once __DIR__ . '/../../../koneksi.php';

echo "<h2>Alter Detail Resep (Add Price Source)</h2>";

$sql = "
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('dbo.resep_obat_detail') AND name = 'price_source')
BEGIN
    ALTER TABLE dbo.resep_obat_detail ADD price_source VARCHAR(20) NULL;
    PRINT 'Column price_source added.';
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Alter Detail Table Success.<br>";
}
?>

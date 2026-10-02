<?php
require_once __DIR__ . '/../../koneksi.php';

$sql = "
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'resep_obat' AND COLUMN_NAME = 'is_manual')
    BEGIN
        ALTER TABLE resep_obat ADD is_manual INT DEFAULT 0;
        PRINT 'Column is_manual added successfully.';
    END
    ELSE
    BEGIN
        PRINT 'Column is_manual already exists.';
    END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Migration successful.";
}
?>

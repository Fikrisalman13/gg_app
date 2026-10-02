<?php
require_once __DIR__ . '/../../koneksi.php';

$sql = "
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'resep_obat' AND COLUMN_NAME = 'resep_no')
    BEGIN
        ALTER TABLE resep_obat ADD resep_no VARCHAR(50) NULL;
        ALTER TABLE resep_obat ADD resep_seq INT NULL;
        ALTER TABLE resep_obat ADD resep_date DATETIME NULL;
        ALTER TABLE resep_obat ADD resep_type VARCHAR(50) NULL;
        PRINT 'Columns added successfully.';
    END
    ELSE
    BEGIN
        PRINT 'Columns already exist.';
    END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Migration successful.";
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // consume results
    }
}
?>

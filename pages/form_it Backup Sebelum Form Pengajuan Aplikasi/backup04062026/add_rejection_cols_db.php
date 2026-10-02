<?php
require '../../koneksi.php';

$sql = "
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Form_Perubahan_Data_Database]') AND name = 'rejected_by')
BEGIN
    ALTER TABLE Form_Perubahan_Data_Database ADD rejected_by VARCHAR(50) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Form_Perubahan_Data_Database]') AND name = 'rejection_reason')
BEGIN
    ALTER TABLE Form_Perubahan_Data_Database ADD rejection_reason VARCHAR(MAX) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Form_Perubahan_Data_Database]') AND name = 'rejection_date')
BEGIN
    ALTER TABLE Form_Perubahan_Data_Database ADD rejection_date DATETIME NULL;
END
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "Columns added successfully.";
}
?>

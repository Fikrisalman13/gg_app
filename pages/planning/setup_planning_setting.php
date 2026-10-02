<?php
include '../../koneksi.php';

$sql = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='planning_setting' and xtype='U')
CREATE TABLE planning_setting (
    id INT IDENTITY(1,1) PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    rtg_name VARCHAR(200) NOT NULL,
    created_at DATETIME DEFAULT GETDATE(),
    created_by VARCHAR(100),
    update_at DATETIME,
    update_by VARCHAR(100)
);
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
echo "Table planning_setting created successfully or already exists.";
?>

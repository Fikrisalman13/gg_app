<?php
include '../../koneksi.php';

$sql = "
-- Drop old table if exists
IF EXISTS (SELECT * FROM sysobjects WHERE name='planning_setting' AND xtype='U')
    DROP TABLE planning_setting;

-- Tabel untuk Grup -> Routing
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='planning_group_rtg' AND xtype='U')
CREATE TABLE planning_group_rtg (
    id INT IDENTITY(1,1) PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    rtg_name VARCHAR(200) NOT NULL,
    created_at DATETIME DEFAULT GETDATE(),
    created_by VARCHAR(100),
    update_at DATETIME,
    update_by VARCHAR(100)
);

-- Tabel untuk User -> Grup
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='planning_user_group' AND xtype='U')
CREATE TABLE planning_user_group (
    id INT IDENTITY(1,1) PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    group_name VARCHAR(100) NOT NULL,
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
echo "Tabel planning_group_rtg dan planning_user_group berhasil dibuat.";
?>

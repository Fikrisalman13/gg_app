<?php
// migrate_resep_roles.php
// Jalankan sekali untuk migrasi database
session_start();
require_once __DIR__ . '/../../../koneksi.php';

$results = [];

function runSql($conn, $label, $sql) {
    global $results;
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        $err = print_r(sqlsrv_errors(), true);
        $results[] = ['label' => $label, 'status' => 'ERROR', 'msg' => $err];
    } else {
        $results[] = ['label' => $label, 'status' => 'OK', 'msg' => ''];
        sqlsrv_free_stmt($stmt);
    }
}

// =============================================
// 1. Tambah kolom baru ke dbo.resep_obat
// =============================================
$cols = [
    'machine_code'   => "ALTER TABLE dbo.resep_obat ADD machine_code VARCHAR(50) NULL",
    'machine_name'   => "ALTER TABLE dbo.resep_obat ADD machine_name VARCHAR(255) NULL",
    'temperature_ch2'=> "ALTER TABLE dbo.resep_obat ADD temperature_ch2 DECIMAL(10,2) NULL",
    'lebar_kain'     => "ALTER TABLE dbo.resep_obat ADD lebar_kain DECIMAL(10,2) NULL",
];

foreach ($cols as $col => $sql) {
    $check = sqlsrv_query($conn,
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='resep_obat' AND COLUMN_NAME=?",
        [$col]
    );
    if ($check && sqlsrv_fetch($check)) {
        $results[] = ['label' => "Kolom $col", 'status' => 'SKIP', 'msg' => 'Sudah ada'];
    } else {
        runSql($conn, "Tambah kolom $col", $sql);
    }
    if ($check) sqlsrv_free_stmt($check);
}

// =============================================
// 2. Buat tabel dbo.resep_obat_groups
// =============================================
$chkGrp = sqlsrv_query($conn,
    "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='resep_obat_groups'"
);
if ($chkGrp && sqlsrv_fetch($chkGrp)) {
    $results[] = ['label' => 'Tabel resep_obat_groups', 'status' => 'SKIP', 'msg' => 'Sudah ada'];
} else {
    $sql = "CREATE TABLE dbo.resep_obat_groups (
        id         INT IDENTITY(1,1) PRIMARY KEY,
        group_name VARCHAR(100) NOT NULL,
        role_type  VARCHAR(20)  NOT NULL CHECK (role_type IN ('LAB','PRODUCTION')),
        created_at DATETIME     NOT NULL DEFAULT GETDATE(),
        created_by VARCHAR(100) NOT NULL,
        update_at  DATETIME     NOT NULL DEFAULT GETDATE(),
        update_by  VARCHAR(100) NOT NULL
    )";
    runSql($conn, 'Buat tabel resep_obat_groups', $sql);
}
if ($chkGrp) sqlsrv_free_stmt($chkGrp);

// =============================================
// 3. Buat tabel dbo.resep_obat_group_members
// =============================================
$chkMem = sqlsrv_query($conn,
    "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='resep_obat_group_members'"
);
if ($chkMem && sqlsrv_fetch($chkMem)) {
    $results[] = ['label' => 'Tabel resep_obat_group_members', 'status' => 'SKIP', 'msg' => 'Sudah ada'];
} else {
    $sql = "CREATE TABLE dbo.resep_obat_group_members (
        id         INT IDENTITY(1,1) PRIMARY KEY,
        group_id   INT          NOT NULL,
        username   VARCHAR(100) NOT NULL,
        created_at DATETIME     NOT NULL DEFAULT GETDATE(),
        created_by VARCHAR(100) NOT NULL,
        update_at  DATETIME     NOT NULL DEFAULT GETDATE(),
        update_by  VARCHAR(100) NOT NULL,
        CONSTRAINT FK_resep_group_members_group
            FOREIGN KEY (group_id) REFERENCES dbo.resep_obat_groups(id)
    )";
    runSql($conn, 'Buat tabel resep_obat_group_members', $sql);
}
if ($chkMem) sqlsrv_free_stmt($chkMem);

// Unique constraint: satu user hanya boleh di satu grup
$chkUq = sqlsrv_query($conn,
    "SELECT 1 FROM sys.indexes WHERE name='UQ_resep_group_member_user' AND object_id=OBJECT_ID('dbo.resep_obat_group_members')"
);
if (!($chkUq && sqlsrv_fetch($chkUq))) {
    runSql($conn, 'Unique constraint username di group_members',
        "ALTER TABLE dbo.resep_obat_group_members ADD CONSTRAINT UQ_resep_group_member_user UNIQUE (username)"
    );
} else {
    $results[] = ['label' => 'Unique constraint username', 'status' => 'SKIP', 'msg' => 'Sudah ada'];
}
if ($chkUq) sqlsrv_free_stmt($chkUq);
?>
<!DOCTYPE html>
<html>
<head><title>Migrasi Resep Roles</title>
<style>body{font-family:monospace;padding:20px;} .OK{color:green;} .ERROR{color:red;font-weight:bold;} .SKIP{color:#888;}</style>
</head>
<body>
<h2>Hasil Migrasi Database Resep Roles</h2>
<table border="1" cellpadding="6" cellspacing="0">
    <tr><th>Proses</th><th>Status</th><th>Keterangan</th></tr>
    <?php foreach ($results as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['label']) ?></td>
        <td class="<?= $r['status'] ?>"><?= $r['status'] ?></td>
        <td><pre style="margin:0;font-size:12px;"><?= htmlspecialchars($r['msg']) ?></pre></td>
    </tr>
    <?php endforeach; ?>
</table>
<br><p><b>Selesai.</b> Silakan hapus file ini setelah migrasi berhasil.</p>
</body>
</html>

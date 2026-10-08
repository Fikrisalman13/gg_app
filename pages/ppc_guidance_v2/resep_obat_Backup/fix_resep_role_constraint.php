<?php
// pages/resep_obat/fix_resep_role_constraint.php
// Script untuk memperbaiki CHECK constraint yang membatasi role_type
session_start();
require_once __DIR__ . '/../../../koneksi.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Fixing Role Type Constraint</h2>";

// 1. Cari nama constraint yang ada pada kolom role_type di tabel resep_obat_groups
$sqlFind = "SELECT name 
            FROM sys.check_constraints 
            WHERE parent_object_id = OBJECT_ID('dbo.resep_obat_groups') 
            AND definition LIKE '%role_type%'";

$stmtFind = sqlsrv_query($conn, $sqlFind);
if ($stmtFind === false) {
    die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$constraints = [];
while ($row = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC)) {
    $constraints[] = $row['name'];
}

if (empty($constraints)) {
    echo "No constraint found for role_type. Adding new one...<br>";
} else {
    foreach ($constraints as $cName) {
        echo "Dropping constraint: <b>$cName</b>... ";
        $sqlDrop = "ALTER TABLE dbo.resep_obat_groups DROP CONSTRAINT $cName";
        $stmtDrop = sqlsrv_query($conn, $sqlDrop);
        if ($stmtDrop === false) {
            echo "<span style='color:red'>FAILED</span><br>";
            echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
        } else {
            echo "<span style='color:green'>SUCCESS</span><br>";
        }
    }
}

// 2. Tambahkan constraint baru yang mengizinkan 'BOTH'
echo "Adding new constraint (LAB, PRODUCTION, BOTH)... ";
$sqlAdd = "ALTER TABLE dbo.resep_obat_groups 
           ADD CONSTRAINT CK_resep_obat_role_type 
           CHECK (role_type IN ('LAB', 'PRODUCTION', 'BOTH'))";

$stmtAdd = sqlsrv_query($conn, $sqlAdd);
if ($stmtAdd === false) {
    echo "<span style='color:red'>FAILED</span><br>";
    echo "<pre>" . print_r(sqlsrv_errors(), true) . "</pre>";
} else {
    echo "<span style='color:green'>SUCCESS</span><br>";
}

echo "<br><p><b>Selesai.</b> Silakan coba simpan data kembali di Role Manager.</p>";

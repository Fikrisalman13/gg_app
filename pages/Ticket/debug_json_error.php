<?php
// Simulate session
session_start();
$_SESSION['UserId'] = 2; // Ganti dengan ID user teknisi jika tahu, atau kita coba ID sembarang yang bukan admin
$_SESSION['GroupId'] = 2; // Asumsi bukan admin

header('Content-Type: text/plain'); // Biar error kelihatan teks-nya
require_once __DIR__ . '/../../koneksi.php';

// Copy-paste logic dari ticket_serverside.php bagian atas
$userId = 2024; // Coba hardcode ID teknisi jika ada, atau gunakan logic yang sama
// Kita ambil salah satu user teknisi dari debug_roles.php tadi kalau ingat, atau query ulang satu user IT.

// Cari satu user IT untuk test
$sqlGetIT = "SELECT TOP 1 u.UserId FROM dbo.m_emp e 
             JOIN dbo.SMUserMs u ON e.id_emp = u.EmpId 
             JOIN dbo.m_dept d ON e.id_dept = d.id_dept
             WHERE d.dept LIKE '%Information Technology%' AND u.GroupId != 1";
$stmtIT = sqlsrv_query($conn, $sqlGetIT);
if($stmtIT && $rowIT = sqlsrv_fetch_array($stmtIT, SQLSRV_FETCH_ASSOC)){
    $userId = $rowIT['UserId'];
    echo "Testing with IT User ID: " . $userId . "\n";
} else {
    echo "Could not find IT user, defaulting to 2\n";
    $userId = 2;
}

$sqlRole = "SELECT u.EmpId, g.GroupId, g.GroupName, d.dept AS department_name
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
            LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
            LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
            WHERE u.UserId = ?";
echo "Query: $sqlRole\n";

$stmtRole = sqlsrv_query($conn, $sqlRole, [$userId]);

if ($stmtRole === false) {
    echo "SQL Error:\n";
    die(print_r(sqlsrv_errors(), true));
}

$roleRow = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC);
print_r($roleRow);
?>

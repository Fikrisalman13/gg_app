<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$nik     = isset($_GET['nik'])     ? trim($_GET['nik'])     : '';
$exclude = isset($_GET['exclude']) ? trim($_GET['exclude']) : '';

if ($nik === '') {
    echo json_encode(['duplicate' => false]);
    exit;
}

$sql    = "SELECT COUNT(*) as cnt FROM dbo.m_emp WHERE nik = ? AND nik <> ?";
$stmt   = sqlsrv_query($conn, $sql, [$nik, $exclude]);

if ($stmt === false) {
    echo json_encode(['error' => 'Query failed']);
    exit;
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

echo json_encode(['duplicate' => $row['cnt'] > 0]);

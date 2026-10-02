<?php
session_start();
require_once '../../koneksi.php';

if (!isset($_GET['start']) || !isset($_GET['end'])) {
    echo "Parameter tidak lengkap!";
    exit;
}

$start = $_GET['start'];
$end   = $_GET['end'];

$sql = "DELETE FROM monitoring_jaringan WHERE CAST(date AS DATE) BETWEEN ? AND ?";
$params = [$start, $end];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo "Berhasil menghapus data dari tanggal $start sampai $end.";
} else {
    echo "Gagal menghapus data!";
}

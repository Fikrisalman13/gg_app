<?php
// Start session and output buffering at the VERY BEGINNING
session_start();
ob_start();

// Load configuration and dependencies
require_once __DIR__ . '/../../koneksi.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Check if ID parameter is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID data packing tidak valid!";
    header('Location: data.php');
    exit;
}

$id = $_GET['id'];

// Delete data from database
$deleteQuery = "DELETE FROM packing_output WHERE id = ?";
$params = array($id);
$stmt = sqlsrv_query($conn, $deleteQuery, $params);

if ($stmt === false) {
    $_SESSION['error'] = "Gagal menghapus data packing!";
} else {
    $_SESSION['success'] = "Data packing berhasil dihapus!";
}

header('Location: data.php');
exit;
?>
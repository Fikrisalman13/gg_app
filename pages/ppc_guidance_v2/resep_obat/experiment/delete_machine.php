<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../../koneksi.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['UserName'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$id = $_POST['id'] ?? $_GET['id'] ?? '';
if (!$id) { echo json_encode(['status'=>'error','message'=>'ID tidak ditemukan']); exit; }

// Hard delete: hapus data mesin dari database.
$s = sqlsrv_query($conn, "DELETE FROM dbo.master_mesin_lab WHERE id=?", [$id]);
if (!$s) { echo json_encode(['status'=>'error','message'=>'Gagal hapus: '.print_r(sqlsrv_errors(),true)]); exit; }
echo json_encode(['status'=>'success','message'=>'Mesin berhasil dihapus.']);


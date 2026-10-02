<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
requireDelete($conn, 175);

$id = (int)$_POST['id'];

sqlsrv_query($conn, "DELETE FROM cl_cutting_header WHERE id_header=?", [$id]);

echo json_encode(['status' => 'ok']);

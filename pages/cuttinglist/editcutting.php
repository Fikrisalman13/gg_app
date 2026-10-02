<?php
require_once '../../koneksi.php';
require_once '../../includes/permissions.php';
requireEdit($conn, 175);

$id = (int)$_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sqlsrv_query($conn, "
        UPDATE cl_cutting_header
        SET cp_no = ?, type_counter = ?, updated_by = ?, updated_date = SYSDATETIME()
        WHERE id_header = ?
    ", [
        $_POST['cp_no'],
        $_POST['type_counter'],
        $_SESSION['UserName'],
        $id
    ]);

    header("Location: index.php");
    exit;
}

$data = sqlsrv_fetch_array(
    sqlsrv_query($conn, "SELECT * FROM cl_cutting_header WHERE id_header=?", [$id]),
    SQLSRV_FETCH_ASSOC
);

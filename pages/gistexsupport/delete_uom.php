<?php require_once '../../koneksi.php'; $id=(int)($_GET['id']??0); if($id>0) sqlsrv_query($conn,'DELETE FROM uom_gistex WHERE id=?',[$id]); header('Location: uom_gistex.php'); exit;

<?php require_once '../../koneksi.php'; $id=(int)($_GET['id']??0); if($id>0) sqlsrv_query($conn,'DELETE FROM master_gistex WHERE id=?',[$id]); header('Location: master_gistex.php'); exit;

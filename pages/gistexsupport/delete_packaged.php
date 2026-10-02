<?php require_once '../../koneksi.php'; $id=(int)($_GET['id']??0); if($id>0) sqlsrv_query($conn,'DELETE FROM packaged_gistex WHERE id=?',[$id]); header('Location: packaged_gistex.php'); exit;

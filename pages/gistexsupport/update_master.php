<?php
require_once '../../koneksi.php';
require_once 'helpers.php';
$id=(int)($_POST['id']??0); $item=trim($_POST['item']??''); $kode=trim($_POST['kode_produk']??''); $oldId=(int)($_POST['old_id']??0); $user=gistexCurrentUser();
if($id>0 && $item!=='' && $kode!==''){ sqlsrv_query($conn,'UPDATE master_gistex SET id=?, item=?, kode_produk=?, updatedate=GETDATE(), updateby=? WHERE id=?',[$id,$item,$kode,$user,$oldId]); }
header('Location: master_gistex.php'); exit;

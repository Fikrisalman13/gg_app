<?php
require_once '../../koneksi.php';
require_once 'helpers.php';
$id=(int)($_POST['id']??0); $item=trim($_POST['item']??''); $kode=trim($_POST['kode_produk']??''); $user=gistexCurrentUser();
if($id>0 && $item!=='' && $kode!==''){ sqlsrv_query($conn,'INSERT INTO master_gistex (id,item,kode_produk,create_date,created_by,updatedate,updateby) VALUES (?, ?, ?, GETDATE(), ?, GETDATE(), ?)',[$id,$item,$kode,$user,$user]); }
header('Location: master_gistex.php'); exit;

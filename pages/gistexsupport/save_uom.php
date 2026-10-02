<?php
require_once '../../koneksi.php';
require_once 'helpers.php';
$id=(int)($_POST['id']??0); $uomid=trim($_POST['uomid']??''); $uomname=trim($_POST['uomname']??''); $user=gistexCurrentUser();
if($uomid!=='' && $uomname!==''){ sqlsrv_query($conn,'INSERT INTO uom_gistex (uomid,uomname,create_date,created_by,updatedate,updateby) VALUES (?, ?, GETDATE(), ?, GETDATE(), ?)',[$uomid,$uomname,$user,$user]); }
header('Location: uom_gistex.php'); exit;

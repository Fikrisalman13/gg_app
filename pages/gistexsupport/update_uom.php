<?php
require_once '../../koneksi.php';
require_once 'helpers.php';
$id=(int)($_POST['id']??0); $uomid=trim($_POST['uomid']??''); $uomname=trim($_POST['uomname']??''); $user=gistexCurrentUser();
if($id>0 && $uomid!=='' && $uomname!==''){ sqlsrv_query($conn,'UPDATE uom_gistex SET uomid=?, uomname=?, updatedate=GETDATE(), updateby=? WHERE id=?',[$uomid,$uomname,$user,$id]); }
header('Location: uom_gistex.php'); exit;

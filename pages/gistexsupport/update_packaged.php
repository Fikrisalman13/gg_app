<?php
require_once '../../koneksi.php';
require_once 'helpers.php';
$id=(int)($_POST['id']??0); $packaging=trim($_POST['packaging']??''); $user=gistexCurrentUser();
if($id>0 && $packaging!==''){ sqlsrv_query($conn,'UPDATE packaged_gistex SET packaging=?, updatedate=GETDATE(), updateby=? WHERE id=?',[$packaging,$user,$id]); }
header('Location: packaged_gistex.php'); exit;

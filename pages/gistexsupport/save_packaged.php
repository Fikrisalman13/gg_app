<?php
require_once '../../koneksi.php';
require_once 'helpers.php';
$packaging=trim($_POST['packaging']??''); $user=gistexCurrentUser();
if($packaging!==''){ sqlsrv_query($conn,'INSERT INTO packaged_gistex (packaging,create_date,created_by,updatedate,updateby) VALUES (?, GETDATE(), ?, GETDATE(), ?)',[$packaging,$user,$user]); }
header('Location: packaged_gistex.php'); exit;

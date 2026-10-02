<?php
$_POST['action'] = 'add_peminjaman';
$_POST['nama_peminjam'] = 'Test User';
$_POST['tgl_pinjam'] = '2025-12-31';
$_POST['id_arsip'] = ['1', '2']; // Assuming these IDs exist
$_POST['keterangan'] = 'Test Multi Item';

$_SESSION['UserName'] = 'Admin'; // Mock session

include 'peminjaman_action.php';
?>

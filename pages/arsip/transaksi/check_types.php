<?php
include 'C:/xampp/htdocs/gg_app/koneksi.php';
$stmt = sqlsrv_query($conn, 'SELECT TOP 1 * FROM arsip_peminjaman');
if ($stmt) {
    $meta = sqlsrv_field_metadata($stmt);
    foreach($meta as $f) {
        echo $f['Name'] . ': ' . $f['Type'] . PHP_EOL;
    }
}
?>

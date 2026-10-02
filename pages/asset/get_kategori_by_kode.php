<?php 
session_start();
include '../../koneksi.php';

header('Content-Type: text/html');

if (!isset($_POST['kode'])) {
    die('<option value="">-- Pilih Kategori --</option>');
}

$kode = $_POST['kode'];

// Ambil nama kategori berdasarkan kode_asset
$sql = "
    SELECT 
        k.id_kategori, 
        k.nama_kategori 
    FROM 
        dbo.m_kode_asset ka
    LEFT JOIN 
        dbo.m_kategori k ON ka.id_kode = k.id_kode
    WHERE 
        ka.kode_asset = ?
";
$params = [$kode];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    die('<option value="">-- Pilih Kategori --</option>');
}

$idKategori = $row['id_kategori'];
$namaKategori = $row['nama_kategori'];

echo "<option value='{$idKategori}' selected>{$namaKategori}</option>";
?>

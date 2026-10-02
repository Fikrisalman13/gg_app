<?php
session_start();
include '../../koneksi.php';

header('Content-Type: text/plain');

if (!isset($_POST['id_kode'])) {
    die('');
}

$idKode = $_POST['id_kode'];

// Ambil prefix kode dari m_kode_asset
$sql = "SELECT kode_asset FROM dbo.m_kode_asset WHERE id_kode = ?";
$params = [$idKode];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    die('');
}

$prefix = $row['kode_asset'];

// Ambil semua nomor urut yang sudah dipakai untuk prefix ini
$sql = "SELECT CAST(SUBSTRING(kode_asset_seq, LEN(?) + 2, 3) AS INT) AS nomor 
        FROM dbo.m_asset 
        WHERE id_kode = ? AND kode_asset_seq LIKE ? + '-%' 
        ORDER BY nomor";
$params = [$prefix, $idKode, $prefix];
$stmt = sqlsrv_query($conn, $sql, $params);

$usedNumbers = [];
while ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $usedNumbers[] = (int)$row['nomor'];
}

// Cari nomor terkecil yang belum dipakai
$nextNumber = 1;
while (in_array($nextNumber, $usedNumbers)) {
    $nextNumber++;
}

// Buat kode baru
$newKodeAssetSeq = $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
echo $newKodeAssetSeq;
?>

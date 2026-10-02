<?php
// Sertakan file koneksi database
include 'db.php';

// Ambil filter status dari parameter URL
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Query berdasarkan filter status
$query = "SELECT packingno, no_do, custcode, custname, nobale, prodcode, prodname, prdnmbr, totalqty, uom, created_at, no_rak, deskripsi, nomor_surat_jalan, tanggal_kirim FROM whtrans_packing";
if ($status == "terkirim") {
    $query .= " WHERE deskripsi = 'Sudah Dikirim'";
} elseif ($status == "belum") {
    $query .= " WHERE deskripsi IS NULL OR deskripsi <> 'Sudah Dikirim'";
}

$result = mysqli_query($conn, $query);

// Set header untuk file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Packing.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Buat header tabel
echo "No\tPacking No\tNo DO\tCustomer Code\tCustomer Name\tNo Bale\tProduct Code\tProduct Name\tProduction Number\tTotal Quantity\tUOM\tCreated At\tNo Rak\tDeskripsi\tNomor Surat Jalan\tTanggal Kirim\n";

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    echo "$no\t{$row['packingno']}\t{$row['no_do']}\t{$row['custcode']}\t{$row['custname']}\t{$row['nobale']}\t{$row['prodcode']}\t{$row['prodname']}\t{$row['prdnmbr']}\t{$row['totalqty']}\t{$row['uom']}\t{$row['created_at']}\t{$row['no_rak']}\t{$row['deskripsi']}\t{$row['nomor_surat_jalan']}\t{$row['tanggal_kirim']}\n";
    $no++;
}
?>

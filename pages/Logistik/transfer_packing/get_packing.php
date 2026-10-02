<?php
include('db.php');

// Validasi input
if (!isset($_GET['packingno']) || empty($_GET['packingno'])) {
    echo json_encode(['error' => 'Nomor Packing tidak valid atau tidak dikirim']);
    exit;
}

// Memproses nomor packing yang dikirim
$packingNos = explode(",", $_GET['packingno']); // Memisahkan input menjadi array
$packingNos = array_map('trim', $packingNos); // Membersihkan spasi tambahan
$packingNos = array_filter($packingNos); // Menghapus elemen kosong

// Jika setelah filter nomor packing kosong
if (empty($packingNos)) {
    echo json_encode(['error' => 'Nomor Packing tidak valid setelah diproses']);
    exit;
}

// Membuat placeholder untuk query
$placeholders = implode(",", array_fill(0, count($packingNos), "?"));

try {
    // Query untuk mengambil data packing berdasarkan nomor packing
    $query = $conn->prepare("
        SELECT
            packingno,
            no_do,
            custcode, 
            custname, 
            nobale, 
            prodcode, 
            prodname,
            prdnmbr, 
            totalqty, 
            uom
        FROM
            whpacking
        WHERE
            packingno IN ($placeholders)
    ");

    // Bind parameter
    $query->bind_param(str_repeat("s", count($packingNos)), ...$packingNos);

    // Eksekusi query
    $query->execute();
    $result = $query->get_result();

    // Format data menjadi array
    $packingData = [];
    while ($row = $result->fetch_assoc()) {
        $packingData[] = $row;
    }

    // Jika tidak ada data yang ditemukan
    if (empty($packingData)) {
        echo json_encode(['error' => 'Data nomor packing tidak ditemukan']);
        exit;
    }

    // Return data dalam format JSON
    header('Content-Type: application/json');
    echo json_encode($packingData);

} catch (Exception $e) {
    // Error handling jika terjadi kesalahan pada database
    echo json_encode(['error' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
}
?>

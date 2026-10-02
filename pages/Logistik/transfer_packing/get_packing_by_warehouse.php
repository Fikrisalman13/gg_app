<?php
include('db.php');

// Validasi input
if (!isset($_GET['wrhsid']) || empty($_GET['wrhsid'])) {
    echo json_encode(['error' => 'Gudang tidak valid atau tidak dipilih']);
    exit;
}

$wrhsid = $_GET['wrhsid'];

try {
    // Query untuk mendapatkan nomor packing berdasarkan gudang
    $query = $conn->prepare("
        SELECT packingno 
        FROM whpacking 
        WHERE wrhsid = ? AND packingno NOT IN (
            SELECT DISTINCT packingno FROM whtrans_packing
        )
    ");
    $query->bind_param("s", $wrhsid);
    $query->execute();
    $result = $query->get_result();

    $packingData = [];
    while ($row = $result->fetch_assoc()) {
        $packingData[] = $row;
    }

    // Jika tidak ada data
    if (empty($packingData)) {
        echo json_encode(['error' => 'Tidak ada nomor packing yang tersedia untuk gudang ini']);
        exit;
    }

    // Return data dalam format JSON
    echo json_encode($packingData);

} catch (Exception $e) {
    echo json_encode(['error' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
}
?>

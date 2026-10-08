<?php
// pages/resep_obat/get_resep_prod_info.php
require_once '../../../koneksi3.php';

header('Content-Type: application/json');

$colormsid = $_GET['colormsid'] ?? '';

if (empty($colormsid)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid colormsid']);
    exit;
}

try {
    // Query to get latest recipe product info for this color
    $sql = "SELECT resepprodcode, resepprodname 
            FROM pdresephd 
            WHERE colormsid = :colormsid
            ORDER BY resepdate DESC
            LIMIT 1";
            
    $stmt = $conn3->prepare($sql);
    $stmt->bindParam(':colormsid', $colormsid);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'resepprodcode' => $row['resepprodcode'],
                'resepprodname' => $row['resepprodname']
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'not_found', 
            'message' => 'Data resep tidak ditemukan untuk warna ini'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

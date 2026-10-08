<?php
// pages/resep_obat/get_colors.php
require_once '../../../koneksi3.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$limit = 20;

if (strlen($q) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    // Search by Code or Name
    // Using PDO from koneksi3.php (PostgreSQL)
    $sql = "SELECT colormsid, colorcode, colorname, colordesc FROM pdcolorms 
            WHERE colorcode ILIKE :q OR colorname ILIKE :q 
            LIMIT :limit";
            
    $stmt = $conn3->prepare($sql);
    $term = "%$q%";
    $stmt->bindParam(':q', $term);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['colorcode'],
            'text' => $row['colorcode'] . ' - ' . $row['colorname'],
            'color_data' => [
                'colormsid' => $row['colormsid'], // ADDED
                'name' => $row['colorname'],
                'desc' => $row['colordesc']
            ]
        ];
    }
    
    echo json_encode(['results' => $results]);

} catch (PDOException $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
?>

<?php
// pages/resep_obat/get_proint_resep_headers.php
// Query 1: Get Headers by Color ID
require_once '../../koneksi3.php';

header('Content-Type: application/json');

$colormsid = $_GET['id'] ?? '';

if (empty($colormsid)) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $sql = "
    SELECT
        pdcolorms.colorcode, 
        pdcolorms.colorname, 
        pdresephd.resepno, 
        pdresephd.resepseq, 
        pdresephd.resepdate, 
        pdresephd.reseptype, 
        pdresephd.resepprodcode, 
        pdresephd.resepprodname, 
        pdresephd.prdnmbr, 
        pdresephd.resephdid
    FROM
        pdresephd
        LEFT JOIN
        pdcolorms
        ON 
            pdresephd.colormsid = pdcolorms.colormsid
    WHERE
        pdcolorms.colormsid = :id
    ORDER BY pdresephd.resepdate DESC
    ";

    $stmt = $conn3->prepare($sql);
    $stmt->bindParam(':id', $colormsid);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format Display
    // resepno, resepseq, reseptype, resepprodname
    $formatted = [];
    foreach($results as $row) {
        $row['display_text'] = "{$row['resepno']} (Seq: {$row['resepseq']}) - {$row['resepprodname']} [{$row['reseptype']}]";
        $formatted[] = $row;
    }

    echo json_encode(['results' => $formatted]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

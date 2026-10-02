<?php
// pages/resep_obat/get_item_price.php
require_once '../../../koneksi3.php';

header('Content-Type: application/json');

$prodcode = $_GET['prodcode'] ?? '';

if (empty($prodcode)) {
    echo json_encode(['price' => 0, 'source' => 'none']);
    exit;
}

try {
    $price = 0;
    $source = 'none';

    // QUERY 2: Check Last PO Price (Priority 1)
    // Using LIMIT 1 to get the very last one
    $sql2 = "WITH last_po AS (
                SELECT
                    h.pohdid 
                FROM
                    prpohd h
                    JOIN prpodt d ON h.pohdid = d.pohdid
                    JOIN smproduct P ON d.poprodid = P.prodid 
                WHERE
                    P.prodcode = :prodcode 
                ORDER BY
                    h.podate DESC,
                    h.pohdid DESC 
                LIMIT 1 
            ) 
            SELECT 
                (d.poprice * h.pocurrrate) AS price_po
            FROM
                last_po lp
                JOIN prpohd h ON lp.pohdid = h.pohdid
                JOIN prpodt d ON h.pohdid = d.pohdid
                JOIN smproduct P ON d.poprodid = P.prodid
            WHERE
                P.prodcode = :prodcode2";

    $stmt2 = $conn3->prepare($sql2);
    $stmt2->bindParam(':prodcode', $prodcode);
    $stmt2->bindParam(':prodcode2', $prodcode);
    $stmt2->execute();
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($row2 && isset($row2['price_po']) && $row2['price_po'] > 0) {
        $price = $row2['price_po'];
        $source = 'PO';
    } else {
        // QUERY 1: Check Template Price (Priority 2)
        $sql1 = "SELECT
                    whpricetemp.price AS price_template
                FROM
                    whpricetemp
                    JOIN smproduct ON whpricetemp.prodid = smproduct.prodid
                WHERE
                    whpricetemp.prodtypename = 'Raw Material' AND
                    smproduct.prodcode = :prodcode
                ORDER BY
                    whpricetemp.price DESC
                LIMIT 1";
        
        $stmt1 = $conn3->prepare($sql1);
        $stmt1->bindParam(':prodcode', $prodcode);
        $stmt1->execute();
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        
        if ($row1 && isset($row1['price_template'])) {
            $price = $row1['price_template'];
            $source = 'Template';
        }
    }
    
    // QUERY 3: Fetch UOM
    $sqlUom = "SELECT u.uomcode 
               FROM smproduct p
               LEFT JOIN smuom u ON p.uomid = u.uomid 
               WHERE p.prodcode = :prodcode";
    $stmtUom = $conn3->prepare($sqlUom);
    $stmtUom->bindParam(':prodcode', $prodcode);
    $stmtUom->execute();
    $rowUom = $stmtUom->fetch(PDO::FETCH_ASSOC);
    $prointSatuan = $rowUom['uomcode'] ?? '';
    $uom = $prointSatuan;

    // 4. MAP TO LOCAL & CHECK UOM CF (G/L) for Rename ONLY
    require_once 'verify_proint_item.php'; 
    $local = checkLocalItem($prodcode);
    
    if ($local) {
         $uomCf = strtoupper(trim($local['uom']));
         // Map G/L -> GR for display per User Request
         if ($uomCf === 'G/L') { // Check if local master says G/L
             $uom = 'GR'; // Display as GR
         } else {
             $uom = $local['uom'];
         }
         // NO PRICE CONVERSION HERE per user request (handled by label "Price Per PO")
    }

    echo json_encode([
        'price' => floatval($price), 
        'source' => $source, 
        'uom' => $uom,
        'satuan' => $prointSatuan // This is the raw ProInt UOM per user request
    ]);

} catch (PDOException $e) {
    echo json_encode(['price' => 0, 'error' => $e->getMessage()]);
}
?>

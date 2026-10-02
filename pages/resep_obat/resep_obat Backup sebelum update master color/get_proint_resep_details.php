<?php
// pages/resep_obat/get_proint_resep_details.php
// Query 2: Get Details by Header ID & Struct ID (Fixed Struct ID '39592' per request or parameter?)
// Request says: smprodstruct.prodstructid = '39592'. Is this constant or variable?
// User query sample: AND smprodstruct.prodstructid = '39592'
// Assumption: Constant for now based on request. Or maybe pass it? Let's use constant as per query sample unless context implies otherwise.

require_once '../../koneksi3.php'; // Postgres
require_once 'verify_proint_item.php'; // Local SQL Server Check

header('Content-Type: application/json');

$resephdid = $_GET['id'] ?? '';
// $structid = $_GET['struct'] ?? '39592'; // Optional if dynamic

if (empty($resephdid)) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    // 1. Fetch from ProInt
    // 1. Fetch from ProInt
    $sql = "
    SELECT
        pdresephd.resephdid, 
        pdresepdt.materialcode, 
        pdresepdt.materialname, 
        pdresepdt.qty, 
        pdresepdt.rtgdesc, 
        smprodstruct.structname,
        smproduct.prodcode, -- To link to codeprod_proint & Price Lookup
        smuom.uomcode  -- Fallback UOM
    FROM
        pdresephd
        LEFT JOIN
        pdresepdt
        ON 
            pdresephd.resephdid = pdresepdt.resephdid
        LEFT JOIN
        smproduct
        ON 
            pdresepdt.materialid = smproduct.prodid
        LEFT JOIN
        smprodstruct
        ON 
            smproduct.prodstructid = smprodstruct.prodstructid
        LEFT JOIN
        smuom
        ON
            smproduct.uomid = smuom.uomid
    WHERE
        pdresephd.resephdid = :id 
        AND smprodstruct.prodstructid = '39592'
    ";
    
    $stmt = $conn3->prepare($sql);
    $stmt->bindParam(':id', $resephdid);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    // Prepare Price Queries (Reusable Statements)
    // PO Price
    $sqlPO = "WITH last_po AS (
                SELECT h.pohdid 
                FROM prpohd h
                JOIN prpodt d ON h.pohdid = d.pohdid
                JOIN smproduct P ON d.poprodid = P.prodid 
                WHERE P.prodcode = :prodcode 
                ORDER BY h.podate DESC, h.pohdid DESC 
                LIMIT 1 
            ) 
            SELECT (d.poprice * h.pocurrrate) AS price_po
            FROM last_po lp
            JOIN prpohd h ON lp.pohdid = h.pohdid
            JOIN prpodt d ON h.pohdid = d.pohdid
            JOIN smproduct P ON d.poprodid = P.prodid
            WHERE P.prodcode = :prodcode2";
    $stmtPO = $conn3->prepare($sqlPO);

    // Template Price
    $sqlTmp = "SELECT whpricetemp.price AS price_template
                FROM whpricetemp
                JOIN smproduct ON whpricetemp.prodid = smproduct.prodid
                WHERE whpricetemp.prodtypename = 'Raw Material' AND smproduct.prodcode = :prodcode
                ORDER BY whpricetemp.price DESC LIMIT 1";
    $stmtTmp = $conn3->prepare($sqlTmp);

    // 2. Map to Local Data & Get Price
    foreach ($rows as $r) {
        $prointCode = $r['materialcode'];
        $prodCode = $r['prodcode'];
        
        // A. Local Mapping
        $local = checkLocalItem($prointCode);

        // B. Price Lookup
        $price = 0;
        $source = ''; // none

        // Check PO
        $stmtPO->bindParam(':prodcode', $prodCode);
        $stmtPO->bindParam(':prodcode2', $prodCode);
        $stmtPO->execute();
        $rowPO = $stmtPO->fetch(PDO::FETCH_ASSOC);
        
        if ($rowPO && isset($rowPO['price_po']) && $rowPO['price_po'] > 0) {
            $price = floatval($rowPO['price_po']);
            $source = 'PO'; // Will correspond to "Price PO" in frontend
        } else {
            // Check Template
            $stmtTmp->bindParam(':prodcode', $prodCode);
            $stmtTmp->execute();
            $rowTmp = $stmtTmp->fetch(PDO::FETCH_ASSOC);
            if ($rowTmp && isset($rowTmp['price_template'])) {
                $price = floatval($rowTmp['price_template']);
                $source = 'Template'; // "Std Price"
            }
        }

        $item = [
            'proint_code' => $prointCode,
            'proint_name' => $r['materialname'],
            'qty' => $r['qty'],
            'found' => false,
            'warning' => '',
            'kode' => '',
            'name' => '',
            'category' => '',
            'uom' => '',
            'cf' => floatval($r['qty']),
            'std_price' => $price,
            'price_source' => $source,
            'satuan' => $r['uomcode'] ?? '' // Raw ProInt UOM
        ];

        if ($local) {
            $item['found'] = true;
            $item['kode'] = $local['kode_obat'];
            $item['name'] = $local['nama_obat'];
            $item['category'] = $local['group_obat'];
            
            // Per User Request:
            // UOM -> FROM LOCAL (Keep 'G/L' -> Rename to 'GR' per request)
            $uomRaw = $local['uom'] ?? '';
            if (strtoupper(trim($uomRaw)) === 'G/L') {
                $uomRaw = 'GR';
            }
            $item['uom'] = $uomRaw;
            
            // UOM CF -> From Local Master
            $item['uom_cf'] = $local['uom'] ?? '';

        } else {
            $item['warning'] = "Item ProInt [$prointCode] belum terdaftar di Master Obat GG Support App!";
            $item['name'] = $r['materialname']; 
            // If unknown locally, use ProInt UOM for main UOM?
            $item['uom'] = $r['uomcode'] ?? '';
            $item['uom_cf'] = ''; // No local data
        }

        $results[] = $item;
    }

    echo json_encode(['results' => $results]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

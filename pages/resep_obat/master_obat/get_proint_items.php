<?php
// pages/resep_obat/master_obat/get_proint_items.php
// Uses koneksi3.php (PostgreSQL)

require_once __DIR__ . '/../../../koneksi3.php';
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

// PostgreSQL Query (Use LIMIT instead of TOP)
$sql = "
SELECT
	smproduct.prodcode,
	smproduct.prodname,
	smprodstruct.structname,
	smuom.uomcode 
FROM
	smproduct
	LEFT JOIN smprodstruct ON smproduct.prodstructid = smprodstruct.prodstructid
	LEFT JOIN smuom ON smproduct.uomid = smuom.uomid 
WHERE
	smproduct.prodtype IN ('RAW', 'GIP') 
	AND smproduct.fgstatus = 'A' 
	AND smprodstruct.structcode IN ( 'A300000030', 'A300000011', 'A300000012', 'A300000020', 'A300000040', 'A300000010', 'H300000020' )
";

$params = [];

if ($q) {
    // Parameterized query for PDO
    $sql .= " AND (smproduct.prodcode ILIKE :q OR smproduct.prodname ILIKE :q)";
    // ILIKE is case-insensitive LIKE in Postgres
    $params[':q'] = "%$q%";
}

$sql .= " ORDER BY smproduct.prodname LIMIT 20";

$results = [];

try {
    $stmt = $conn3->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $text = $row['prodcode'] . ' - ' . $row['prodname'];
        if ($row['uomcode']) $text .= ' (' . $row['uomcode'] . ')';
        
        $results[] = [
            'id' => $row['prodcode'],
            'text' => $text,
            'item_data' => $row 
        ];
    }
} catch (PDOException $e) {
    // file_put_contents('debug_proint.log', $e->getMessage());
}

echo json_encode(['results' => $results]);
?>

<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include __DIR__ . '/../../../../koneksi3.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode([]); exit; }

$q = trim($_GET['q'] ?? '');
$cus_color = trim($_GET['cus_color'] ?? '');

try {
    // Chain: insohd -> insodt (sohdid) -> smproduct (prodid) -> smprodtechdata (prodid) -> cuscolor
    // Standard: smprodtechdata.cuscolor
    $sql = "
        SELECT DISTINCT h.sonmbr, TRIM(td.cuscolor) AS cus_color
        FROM insohd h
        INNER JOIN insodt d ON d.sohdid = h.sohdid
        INNER JOIN smproduct sp ON sp.prodid = d.prodid
        INNER JOIN smprodtechdata td ON td.prodid = sp.prodid
        WHERE h.fgstatus = 'V' AND h.sotype = 'J'
    ";
    $params = [];

    if ($cus_color !== '') {
        $sql .= " AND TRIM(td.cuscolor) = TRIM(:cus_color)";
        $params[':cus_color'] = $cus_color;
    }

    if ($q !== '') {
        $sql .= " AND h.sonmbr ILIKE :q";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY h.sonmbr LIMIT 50";

    $stmt = $conn3->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $r) {
        $results[] = [
            'id'   => $r['sonmbr'],
            'text' => $r['sonmbr'],
            'cus_color' => $r['cus_color']
        ];
    }
    echo json_encode(['results' => $results]);
} catch (Exception $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}

<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include __DIR__ . '/../../../../koneksi3.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) { echo json_encode([]); exit; }

$soi = trim($_GET['soi'] ?? '');
$q = trim($_GET['q'] ?? '');
$cus_color = trim($_GET['cus_color'] ?? '');

if ($soi === '') {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $sql = "
        SELECT DISTINCT
            ph.prdnmbr AS no_cp,
            COALESCE(ph.prodname, '') AS prdname,
            COALESCE(ph.prodcode, '') AS prodcode,
            TRIM(td.cuscolor) AS cus_color
        FROM pdiso p
        INNER JOIN pdproductionhd ph ON ph.isoid = p.isoid
        INNER JOIN smproduct sp ON sp.prodcode = ph.prodcode
        INNER JOIN smprodtechdata td ON td.prodid = sp.prodid
        WHERE p.sonmbr = :soi
    ";
    $params = [':soi' => $soi];

    // Filter by cus_color if provided
    if ($cus_color !== '') {
        $sql .= " AND TRIM(td.cuscolor) = TRIM(:cus_color)";
        $params[':cus_color'] = $cus_color;
    }

    if ($q !== '') {
        $sql .= " AND ph.prdnmbr ILIKE :q";
        $params[':q'] = '%' . $q . '%';
    }

    $sql .= " ORDER BY ph.prdnmbr";

    $stmt = $conn3->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $r) {
        $results[] = [
            'id'        => $r['no_cp'],
            'text'      => $r['no_cp'],
            'prdname'  => $r['prdname'],
            'prodcode' => $r['prodcode'],
            'cus_color' => $r['cus_color']
        ];
    }
    echo json_encode(['results' => $results]);
} catch (Exception $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}

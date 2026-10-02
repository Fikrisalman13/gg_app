<?php
require_once __DIR__ . '/../../../koneksi3.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$cusColor = $_GET['cus_color'] ?? '';

// Minimum: harus ada q atau cus_color
if (strlen($q) < 1 && strlen($cusColor) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $conditions = [];
    $params = [];

    // Base join: pdcolorms -> smprodtechdata -> smproduct (FG only)
    // Ini STANDARD chain untuk cus_color consistency
    $baseJoin = "
        FROM pdcolorms c
        INNER JOIN smprodtechdata td ON td.colormsid = c.colormsid
        INNER JOIN smproduct sp ON sp.prodid = td.prodid
        WHERE LOWER(COALESCE(sp.prodtype, '')) = 'fg'
          AND COALESCE(sp.prodcode, '') <> ''
    ";

    // Filter 1: q (colorcode / colorname search)
    if (strlen($q) >= 1) {
        $conditions[] = "(c.colorcode ILIKE :q OR c.colorname ILIKE :q)";
        $params[':q'] = "%$q%";
    }

    // Filter 2: cus_color filter - STANDARD = smprodtechdata.cuscolor
    // Cari pdcolorms yang punya minimal 1 FG product dg cuscolor MATCH
    if (strlen($cusColor) >= 1) {
        $baseJoin .= " AND TRIM(td.cuscolor) = TRIM(:cus_color)";
        $params[':cus_color'] = $cusColor;
    }

    $where = count($conditions) > 0 ? 'AND ' . implode(' AND ', $conditions) : '';

    // Subquery: distinct pdcolorms that match criteria
    $sql = "
        SELECT DISTINCT c.colormsid, c.colorcode, c.colorname, c.colordesc, td.cuscolor
        $baseJoin $where
        ORDER BY c.colorcode
        LIMIT 30
    ";

    $stmt = $conn3->prepare($sql);
    $stmt->execute($params);

    $res = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Fallback: extract cus_color from colordesc if no explicit cuscolor found
        // (for pdcolorms without smprodtechdata FG link)
        $extractedFromDesc = '';
        if (!empty($r['colordesc'])) {
            $parts = explode('/', $r['colordesc']);
            $extractedFromDesc = $parts[0];
        }

        $res[] = [
            'id' => $r['colorcode'],
            'text' => $r['colorcode'] . ' - ' . $r['colorname'],
            'color_data' => [
                'colormsid' => $r['colormsid'],
                'name' => $r['colorname'],
                'desc' => $r['colordesc'],
                'cus_color' => $r['cuscolor'] ?? ($extractedFromDesc ?: '')
            ]
        ];
    }
    echo json_encode(['results' => $res]);
} catch (PDOException $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}

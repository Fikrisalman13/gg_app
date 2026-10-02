<?php
require_once __DIR__ . '/../../../koneksi3.php';
header('Content-Type: application/json; charset=utf-8');

$draw = (int)($_POST['draw'] ?? 1);
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$search = $_POST['search']['value'] ?? '';
$colormsid = $_POST['colormsid'] ?? '';

if ($colormsid === '') {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}

try {
    $base = "
        FROM pdcolorms c
        INNER JOIN smprodtechdata t ON t.colormsid = c.colormsid
        INNER JOIN smproduct p ON p.prodid = t.prodid
        WHERE c.colormsid = :colormsid
          AND LOWER(COALESCE(p.prodtype, '')) = 'fg'
          AND COALESCE(p.prodcode, '') <> ''
    ";

    $params = [':colormsid' => $colormsid];
    $whereSearch = '';
    if ($search !== '') {
        $whereSearch = " AND (p.prodcode ILIKE :search OR p.prodname ILIKE :search OR COALESCE(t.cuscolor, '') ILIKE :search)";
        $params[':search'] = "%$search%";
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT DISTINCT p.prodid, p.prodcode
            " . $base . "
        ) x
    ";
    $stmt = $conn3->prepare($countSql);
    $stmt->execute([':colormsid' => $colormsid]);
    $total = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $countFilteredSql = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT DISTINCT p.prodid, p.prodcode
            " . $base . $whereSearch . "
        ) x
    ";
    $stmt = $conn3->prepare($countFilteredSql);
    $stmt->execute($params);
    $filtered = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $sql = "
        SELECT
            p.prodid AS resephdid,
            p.prodcode AS resepprodcode,
            p.prodname AS resepprodname,
            COALESCE(MAX(t.cuscolor), '') AS cuscolor,
            p.prodtype AS prodtypecode
        " . $base . $whereSearch . "
        GROUP BY p.prodid, p.prodcode, p.prodname, p.prodtype
        ORDER BY p.prodcode
        OFFSET :start LIMIT :length
    ";
    $stmt = $conn3->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()]);
}

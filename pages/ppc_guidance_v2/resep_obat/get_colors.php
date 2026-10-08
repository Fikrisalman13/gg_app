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
    $sql = "SELECT c.colormsid, c.colorcode, c.colorname, c.colordesc,
                ARRAY_REMOVE(ARRAY_AGG(DISTINCT NULLIF(TRIM(td.cuscolor), '')
                    ORDER BY NULLIF(TRIM(td.cuscolor), '')), NULL) AS cuscolor_choices
            FROM pdcolorms c
            LEFT JOIN smprodtechdata td ON td.colormsid = c.colormsid
            WHERE c.colorcode ILIKE :q OR c.colorname ILIKE :q
            GROUP BY c.colormsid, c.colorcode, c.colorname, c.colordesc
            ORDER BY c.colorcode
            LIMIT :limit";
            
    $stmt = $conn3->prepare($sql);
    $term = "%$q%";
    $stmt->bindParam(':q', $term);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $choicesLiteral = trim((string)($row['cuscolor_choices'] ?? ''), '{}');
        $cusColorChoices = $choicesLiteral === '' ? [] : str_getcsv($choicesLiteral);
        $cusColorChoices = array_values(array_filter(array_map('trim', $cusColorChoices), fn($value) => $value !== ''));
        $cusColor = count($cusColorChoices) === 1 ? $cusColorChoices[0] : '';
        $cusColorSource = $cusColor !== '' ? 'smprodtechdata' : '';
        if (!$cusColorChoices && strpos((string)$row['colordesc'], '/') !== false) {
            $cusColor = trim(explode('/', (string)$row['colordesc'], 2)[0]);
            $cusColorSource = $cusColor !== '' ? 'colordesc' : '';
        }
        $results[] = [
            'id' => $row['colorcode'],
            'text' => $row['colorcode'] . ' - ' . $row['colorname'],
            'color_data' => [
                'colormsid' => $row['colormsid'],
                'name' => $row['colorname'],
                'desc' => $row['colordesc'],
                'cus_color' => $cusColor,
                'cus_color_source' => $cusColorSource,
                'cus_color_choices' => $cusColorChoices
            ]
        ];
    }
    
    echo json_encode(['results' => $results]);

} catch (PDOException $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
?>

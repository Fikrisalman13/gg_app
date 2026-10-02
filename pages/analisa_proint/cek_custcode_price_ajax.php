<?php
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(["error" => "Silakan login terlebih dahulu!"]);
    exit;
}

if (!$conn || !$conn3) {
    echo json_encode(["error" => "Koneksi ke database gagal."]);
    exit;
}

$custcodes_input = isset($_POST['custcodes']) ? trim($_POST['custcodes']) : '';
$codes = preg_split('/[\s,]+/', $custcodes_input, -1, PREG_SPLIT_NO_EMPTY);

if (count($codes) === 0) {
    echo json_encode([
        "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($codes), '?'));

// --- DataTables parameters ---
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
$orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 6; // Default to Effective Date
$orderDir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';

// Map column index to database column names
$columnsMap = [
    0 => 'c.CusCode',
    1 => 'c.CusName',
    2 => 'cg.CusGrpCode',
    3 => 'p.ProdCode',
    4 => 'p.ProdName',
    5 => 'p.Price',
    6 => 'p.effdate',
    7 => 'u.UOMCode',
    8 => 'curr.CurrCode',
    9 => 'ps.StructCode',
    10 => 'h.TempCode',
    11 => 'h.TempName'
];

$orderByColumn = isset($columnsMap[$orderColumnIndex]) ? $columnsMap[$orderColumnIndex] : 'p.effdate';

// --- Base FROM/JOIN clause ---
$baseFrom = "
    FROM SMCustomer c
    INNER JOIN INPriceTempMbr m ON c.cusid = m.cusid
    INNER JOIN INPriceTempHd h ON m.pricetemphdid = h.pricetemphdid
    INNER JOIN INPriceCurrent p ON h.pricetemphdid = p.pricetemphdid
    LEFT OUTER JOIN SMCustomerGrp cg ON c.cusgrpid = cg.cusgrpid
    LEFT OUTER JOIN SMUOM u ON p.uomid = u.uomid
    LEFT OUTER JOIN SMCurrency curr ON p.currid = curr.currid
    LEFT OUTER JOIN SMProdStruct ps ON p.prodstructid = ps.prodstructid
";

// --- Base WHERE clause ---
$baseWhere = " WHERE c.CusCode IN ($placeholders) ";
$params = $codes;

// --- Search Filter ---
if (!empty($searchValue)) {
    $baseWhere .= " AND (
        p.ProdCode ILIKE ? OR 
        p.ProdName ILIKE ? OR 
        c.CusCode ILIKE ? OR 
        c.CusName ILIKE ? OR
        h.TempCode ILIKE ? OR
        h.TempName ILIKE ?
    ) ";
    $searchWildcard = "%" . $searchValue . "%";
    // Add parameters for the search conditions
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchWildcard;
    }
}

// --- Query for Total Records (before search filter) ---
$queryTotal = "
    SELECT COUNT(*) as total 
    FROM (
        SELECT DISTINCT c.CusCode, p.ProdCode 
        $baseFrom 
        WHERE c.CusCode IN ($placeholders)
    ) as t
";
$stmtTotal = $conn3->prepare($queryTotal);
$stmtTotal->execute($codes);
$recordsTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// --- Query for Filtered Records (after search filter) ---
$queryFiltered = "
    SELECT COUNT(*) as total 
    FROM (
        SELECT DISTINCT c.CusCode, p.ProdCode 
        $baseFrom 
        $baseWhere
    ) as t
";
$stmtFiltered = $conn3->prepare($queryFiltered);
$stmtFiltered->execute($params);
$recordsFiltered = $stmtFiltered->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// --- Main Data Query ---
$queryData = "
    SELECT DISTINCT
        c.CusCode,
        c.CusName,
        cg.CusGrpCode AS Group_Cust,
        p.ProdCode,
        p.ProdName,
        p.Price AS Current_Price,
        p.effdate AS Effective_Date,
        u.UOMCode,
        curr.CurrCode,
        ps.StructCode,
        h.TempCode,
        h.TempName
    $baseFrom
    $baseWhere
    ORDER BY $orderByColumn $orderDir, c.CusCode, p.ProdCode
";

// Add pagination
if ($length != -1) { // -1 means show all
    $queryData .= " LIMIT $length OFFSET $start";
}

$stmtData = $conn3->prepare($queryData);
$stmtData->execute($params);
$results = $stmtData->fetchAll(PDO::FETCH_ASSOC);

// --- Format Output Data ---
$data = [];
foreach ($results as $res) {
    $row = array_change_key_case($res, CASE_LOWER);
    
    // Format Current Price
    $price = is_numeric($row['current_price']) ? number_format((float)$row['current_price'], 2, ',', '.') : $row['current_price'];
    
    // Format Effective Date - keep the string format, but DataTables sorting is handled by backend now
    $effDate = !empty($row['effective_date']) ? date('d-m-Y', strtotime($row['effective_date'])) : '';

    $data[] = [
        "cuscode" => htmlspecialchars($row['cuscode'] ?? ''),
        "cusname" => htmlspecialchars($row['cusname'] ?? ''),
        "group_cust" => htmlspecialchars($row['group_cust'] ?? ''),
        "prodcode" => htmlspecialchars($row['prodcode'] ?? ''),
        "prodname" => htmlspecialchars($row['prodname'] ?? ''),
        "current_price" => $price,
        "effective_date" => $effDate,
        "uomcode" => htmlspecialchars($row['uomcode'] ?? ''),
        "currcode" => htmlspecialchars($row['currcode'] ?? ''),
        "structcode" => htmlspecialchars($row['structcode'] ?? ''),
        "tempcode" => htmlspecialchars($row['tempcode'] ?? ''),
        "tempname" => htmlspecialchars($row['tempname'] ?? '')
    ];
}

$response = [
    "draw" => $draw,
    "recordsTotal" => intval($recordsTotal),
    "recordsFiltered" => intval($recordsFiltered),
    "data" => $data
];

echo json_encode($response);
?>

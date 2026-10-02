<?php
session_start();

include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

if (!$conn3) {
    die("Koneksi ke database gagal");
}

date_default_timezone_set('Asia/Jakarta');

$custcodeInput = isset($_GET['custcodes']) ? trim($_GET['custcodes']) : '';

function parseCustCodes(string $input): array
{
    if ($input === '') {
        return [];
    }

    $parts = preg_split('/\s*,\s*/', strtoupper($input), -1, PREG_SPLIT_NO_EMPTY);
    $codes = [];

    foreach ($parts as $part) {
        $code = trim($part);
        if ($code === '') {
            continue;
        }

        if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
            continue;
        }

        $codes[$code] = $code;
    }

    return array_values($codes);
}

function fetchProductPriceData(PDO $conn3, array $custCodes): array
{
    if (empty($custCodes)) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($custCodes as $index => $custCode) {
        $key = ':cust' . $index;
        $placeholders[] = $key;
        $params[$key] = $custCode;
    }

    $query = "
        WITH TempPrice AS (
            SELECT DISTINCT
                INPriceTempHd.TempCode,
                INPriceTempHd.TempName,
                INPriceCurrent.ProdCode,
                INPriceCurrent.ProdName,
                INPriceCurrent.Price AS Current_Price,
                INPriceCurrent.effdate AS Effective_Date,
                SMUOM.UOMCode,
                SMCurrency.CurrCode,
                SMProdStruct.StructCode
            FROM INPriceCurrent
            INNER JOIN INPriceTempHd
                ON INPriceTempHd.pricetemphdid = INPriceCurrent.pricetemphdid
            INNER JOIN INPriceTempMbr
                ON INPriceTempMbr.pricetemphdid = INPriceTempHd.pricetemphdid
            LEFT OUTER JOIN SMUOM
                ON SMUOM.uomid = INPriceCurrent.uomid
            LEFT OUTER JOIN SMCurrency
                ON SMCurrency.currid = INPriceCurrent.currid
            LEFT OUTER JOIN SMProdStruct
                ON SMProdStruct.prodstructid = INPriceCurrent.prodstructid
        ),
        TempCustomer AS (
            SELECT
                INPriceTempHd.TempCode,
                SMCustomer.CusCode,
                SMCustomer.CusName,
                SMCustomerGrp.CusGrpCode AS Group_Cust
            FROM INPriceTempHd
            INNER JOIN INPriceTempMbr
                ON INPriceTempMbr.pricetemphdid = INPriceTempHd.pricetemphdid
            LEFT OUTER JOIN SMCustomer
                ON SMCustomer.cusid = INPriceTempMbr.cusid
            LEFT OUTER JOIN SMCustomerGrp
                ON SMCustomerGrp.cusgrpid = SMCustomer.cusgrpid
            WHERE SMCustomer.CusCode IN (" . implode(', ', $placeholders) . ")
        )
        SELECT
            TempCustomer.CusCode,
            TempCustomer.CusName,
            TempCustomer.Group_Cust,
            TempPrice.ProdCode,
            TempPrice.ProdName,
            TempPrice.Current_Price,
            TempPrice.Effective_Date,
            TempPrice.UOMCode,
            TempPrice.CurrCode,
            TempPrice.StructCode,
            TempPrice.TempCode,
            TempPrice.TempName
        FROM TempCustomer
        INNER JOIN TempPrice
            ON TempCustomer.TempCode = TempPrice.TempCode
        ORDER BY TempCustomer.CusCode, TempPrice.ProdCode
    ";

    $stmt = $conn3->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$custCodes = parseCustCodes($custcodeInput);

if (empty($custCodes)) {
    die("Cust Code tidak valid atau kosong.");
}

$results = fetchProductPriceData($conn3, $custCodes);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Tarik_Data_Produk_Price_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<thead>";
echo "<tr>";
echo "<th>No</th>";
echo "<th>Cust Code</th>";
echo "<th>Cust Name</th>";
echo "<th>Group Cust</th>";
echo "<th>Prod Code</th>";
echo "<th>Prod Name</th>";
echo "<th>Current Price</th>";
echo "<th>Effective Date</th>";
echo "<th>UOM</th>";
echo "<th>Currency</th>";
echo "<th>Struct Code</th>";
echo "<th>Temp Code</th>";
echo "<th>Temp Name</th>";
echo "</tr>";
echo "</thead><tbody>";

$no = 1;
foreach ($results as $row) {
    $effectiveDate = '';
    if (!empty($row['effective_date'])) {
        $effectiveDate = date('d/m/Y', strtotime((string) $row['effective_date']));
    }

    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['cuscode']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['cusname']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['group_cust']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['prodcode']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['prodname']) . "</td>";
    echo "<td>" . (is_numeric($row['current_price']) ? number_format((float) $row['current_price'], 2, '.', '') : '') . "</td>";
    echo "<td>" . htmlspecialchars($effectiveDate) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['uomcode']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['currcode']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['structcode']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['tempcode']) . "</td>";
    echo "<td>" . htmlspecialchars((string) $row['tempname']) . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";

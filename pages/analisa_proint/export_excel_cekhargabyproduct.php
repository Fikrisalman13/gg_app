<?php
session_start();

include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

if (!$conn3) {
    die("Koneksi ke database gagal");
}

date_default_timezone_set('Asia/Jakarta');

$prodcodesInput = isset($_POST['prodcodes']) ? trim((string) $_POST['prodcodes']) : '';

function parseProductCodes(string $input): array
{
    if ($input === '') {
        return [];
    }

    $parts = preg_split('/[\s,]+/', strtoupper($input), -1, PREG_SPLIT_NO_EMPTY);
    $codes = [];

    foreach ($parts as $part) {
        $code = trim($part);
        if ($code === '') {
            continue;
        }
        $codes[$code] = $code;
    }

    return array_values($codes);
}

function fetchLastPoPricesByProduct(PDO $conn3, array $prodCodes): array
{
    if (empty($prodCodes)) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($prodCodes as $index => $prodCode) {
        $key = ':prod' . $index;
        $placeholders[] = $key;
        $params[$key] = $prodCode;
    }

    $query = "
        WITH selected_products AS (
            SELECT p.prodid, p.prodcode, p.prodname, p.prodstructid
            FROM smproduct p
            WHERE p.prodcode IN (" . implode(', ', $placeholders) . ")
        ),
        last_po AS (
            SELECT DISTINCT ON (p.prodcode)
                p.prodcode,
                h.pohdid
            FROM prpohd h
            JOIN prpodt d
                ON h.pohdid = d.pohdid
            JOIN selected_products p
                ON d.poprodid = p.prodid
            ORDER BY p.prodcode, h.podate DESC, h.pohdid DESC
        )
        SELECT
            p.prodcode AS kode_product,
            p.prodname AS product_name,
            s.structname,
            h.ponmbr AS no_po,
            h.createdate AS po_date,
            CAST(d.poprice * h.pocurrrate AS numeric(18,4)) AS price_po_rupiah,
            u.uomcode AS satuan
        FROM last_po AS lp
        JOIN prpohd AS h
            ON lp.pohdid = h.pohdid
        JOIN prpodt AS d
            ON h.pohdid = d.pohdid
        JOIN selected_products AS p
            ON d.poprodid = p.prodid
            AND lp.prodcode = p.prodcode
        JOIN smuom AS u
            ON d.pouomid = u.uomid
        LEFT JOIN smprodstruct AS s
            ON p.prodstructid = s.prodstructid
        ORDER BY p.prodcode
    ";

    $stmt = $conn3->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$prodCodes = parseProductCodes($prodcodesInput);

if (empty($prodCodes)) {
    die("Product Code tidak valid atau kosong.");
}

$results = fetchLastPoPricesByProduct($conn3, $prodCodes);

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Cek_Harga_By_Product_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr><td colspan='8' style='font-weight:bold; text-align:center; background-color:#e0e0e0;'>CEK HARGA BY PRODUCT</td></tr>";
echo "<tr><td colspan='8' style='font-weight:bold;'>Export Date: " . date("d/m/Y H:i") . "</td></tr>";
echo "<tr><td colspan='8' style='font-weight:bold;'>Product Code: " . htmlspecialchars(implode(', ', $prodCodes)) . "</td></tr>";
echo "<tr><td colspan='8'></td></tr>";
echo "<thead>";
echo "<tr style='background-color:#f0f0f0; font-weight:bold;'>";
echo "<th>No</th>";
echo "<th>Kode Product</th>";
echo "<th>Product Name</th>";
echo "<th>Struct Name</th>";
echo "<th>No PO</th>";
echo "<th>PO Date</th>";
echo "<th>Price PO (Rupiah)</th>";
echo "<th>Satuan</th>";
echo "</tr>";
echo "</thead><tbody>";

$no = 1;
foreach ($results as $row) {
    $row = array_change_key_case($row, CASE_LOWER);
    $poDate = !empty($row['po_date']) ? date('d/m/Y', strtotime((string) $row['po_date'])) : '';
    $price = is_numeric($row['price_po_rupiah'] ?? null) ? number_format((float) $row['price_po_rupiah'], 4, '.', '') : '';

    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td style=\"mso-number-format:'\\@';\">" . htmlspecialchars((string) ($row['kode_product'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['product_name'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['structname'] ?? '')) . "</td>";
    echo "<td style=\"mso-number-format:'\\@';\">" . htmlspecialchars((string) ($row['no_po'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars($poDate) . "</td>";
    echo "<td style='text-align:right;'>" . $price . "</td>";
    echo "<td>" . htmlspecialchars((string) ($row['satuan'] ?? '')) . "</td>";
    echo "</tr>";
}

echo "<tr><td colspan='8' style='font-weight:bold; background-color:#f0f0f0;'>Total Records: " . count($results) . "</td></tr>";
echo "</tbody></table>";

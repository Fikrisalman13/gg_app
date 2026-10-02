<?php
// Export Excel untuk Cek CustCode Price
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

$custcodes_input = isset($_POST['custcodes']) ? trim($_POST['custcodes']) : '';
$codes = preg_split('/[\s,]+/', $custcodes_input, -1, PREG_SPLIT_NO_EMPTY);
$results = [];

if (count($codes) > 0) {
    $placeholders = implode(',', array_fill(0, count($codes), '?'));

    $query = "SELECT DISTINCT
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
    FROM SMCustomer c
    INNER JOIN INPriceTempMbr m ON c.cusid = m.cusid
    INNER JOIN INPriceTempHd h ON m.pricetemphdid = h.pricetemphdid
    INNER JOIN INPriceCurrent p ON h.pricetemphdid = p.pricetemphdid
    LEFT OUTER JOIN SMCustomerGrp cg ON c.cusgrpid = cg.cusgrpid
    LEFT OUTER JOIN SMUOM u ON p.uomid = u.uomid
    LEFT OUTER JOIN SMCurrency curr ON p.currid = curr.currid
    LEFT OUTER JOIN SMProdStruct ps ON p.prodstructid = ps.prodstructid
    WHERE c.CusCode IN ($placeholders)
    ORDER BY c.CusCode, p.ProdCode";

    $stmt = $conn3->prepare($query);
    $stmt->execute($codes);

    // Set headers for Excel download FIRST
    $filename = 'Data_Cust_Price_' . date('Y-m-d_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Start HTML wrapper for Excel
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
    echo '<body>';
    echo '<table border="1" cellpadding="3" cellspacing="0" style="font-family: Arial, sans-serif; font-size: 10pt;">';
    echo '<thead>';
    echo '<tr style="background-color: #d9edf7; font-weight: bold; text-align: center;">';
    echo '<th>CusCode</th>';
    echo '<th>CusName</th>';
    echo '<th>Group Cust</th>';
    echo '<th>ProdCode</th>';
    echo '<th>ProdName</th>';
    echo '<th>Current Price</th>';
    echo '<th>Effective Date</th>';
    echo '<th>UOMCode</th>';
    echo '<th>CurrCode</th>';
    echo '<th>StructCode</th>';
    echo '<th>TempCode</th>';
    echo '<th>TempName</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    // Stream rows one by one to avoid memory exhaustion
    while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = array_change_key_case($res, CASE_LOWER);
        
        $cuscode = htmlspecialchars($row['cuscode'] ?? '');
        $cusname = htmlspecialchars($row['cusname'] ?? '');
        $group_cust = htmlspecialchars($row['group_cust'] ?? '');
        $prodcode = htmlspecialchars($row['prodcode'] ?? '');
        $prodname = htmlspecialchars($row['prodname'] ?? '');
        
        $price = $row['current_price'] ?? '';
        
        $eff_date = !empty($row['effective_date']) ? date('d/m/Y', strtotime($row['effective_date'])) : '';
        $uomcode = htmlspecialchars($row['uomcode'] ?? '');
        $currcode = htmlspecialchars($row['currcode'] ?? '');
        $structcode = htmlspecialchars($row['structcode'] ?? '');
        $tempcode = htmlspecialchars($row['tempcode'] ?? '');
        $tempname = htmlspecialchars($row['tempname'] ?? '');

        echo '<tr>';
        echo "<td style=\"mso-number-format:'\@'; text-align: center;\">{$cuscode}</td>";
        echo "<td>{$cusname}</td>";
        echo "<td style=\"text-align: center;\">{$group_cust}</td>";
        echo "<td style=\"mso-number-format:'\@'; text-align: center;\">{$prodcode}</td>";
        echo "<td>{$prodname}</td>";
        echo "<td style=\"text-align: right; mso-number-format:'\#\,\#\#0\.00';\">{$price}</td>";
        echo "<td style=\"text-align: center;\">{$eff_date}</td>";
        echo "<td style=\"text-align: center;\">{$uomcode}</td>";
        echo "<td style=\"text-align: center;\">{$currcode}</td>";
        echo "<td style=\"text-align: center;\">{$structcode}</td>";
        echo "<td style=\"text-align: center;\">{$tempcode}</td>";
        echo "<td>{$tempname}</td>";
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
}

exit;
?>

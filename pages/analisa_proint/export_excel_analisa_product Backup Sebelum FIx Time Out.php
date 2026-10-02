<?php
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

$prodcode  = isset($_GET['prodcode']) ? trim($_GET['prodcode']) : '';
$transtype = isset($_GET['transtype']) ? trim($_GET['transtype']) : '';
$token     = isset($_GET['downloadToken']) ? $_GET['downloadToken'] : '';

if ($prodcode === '') {
    die("Kode produk tidak ditemukan!");
}

if ($token) {
    setcookie("downloadToken", $token, time() + 3600, "/");
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Histori_Produk_" . $prodcode . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$whereClauses = ["b.prodcode = :prodcode"];
$params = [':prodcode' => $prodcode];

if ($transtype !== '') {
    $whereClauses[] = "a.transdesttype = :transtype";
    $params[':transtype'] = $transtype;
}

$whereSQL = implode(' AND ', $whereClauses);

$query = "
    SELECT
        a.transdestnmbr AS transno, 
        a.transdestdate AS transdate, 
        a.transdesttype AS transtype, 
        d.transname, 
        c.wrhscode, 
        c.wrhsname, 
        b.prodcode, 
        b.prodname, 
        b.transinqty AS qty_in, 
        b.transoutqty AS qty_out, 
        e.uomname, 
        b.upddate, 
        b.upduser
    FROM whtranshd AS a
    LEFT JOIN whtransdt AS b ON a.transhdid = b.transhdid
    LEFT JOIN whwrhs AS c ON a.transdestwrhsid = c.wrhsid
    LEFT JOIN whtransms AS d ON a.transdesttype = d.transcode
    LEFT JOIN smuom AS e ON b.transuomid = e.uomid
    WHERE $whereSQL
    ORDER BY b.upddate ASC, a.transdesttype DESC
";

$stmt = $conn3->prepare($query);
foreach ($params as $key => $val) {
    if (is_int($val)) {
        $stmt->bindValue($key, $val, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $val);
    }
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buat tabel HTML untuk diekspor
echo "<table border='1'>";
echo "<thead>
        <tr>
            <th>No</th>
            <th>Trans No</th>
            <th>Trans Name</th>
            <th>Tanggal</th>
            <th>Warehouse Code</th>
            <th>Warehouse Name</th>
            <th>Product Code</th>
            <th>Product Name</th>
            <th>Qty In</th>
            <th>Qty Out</th>
            <th>UOM</th>
            <th>Update Date</th>
            <th>Update User</th>
        </tr>
      </thead><tbody>";

$no = 1;
foreach ($results as $row) {
    $transdate = $row['transdate'] ? date("d/m/Y", strtotime($row['transdate'])) : '';
    $updDate   = $row['upddate'] ? date("d/m/Y H:i", strtotime($row['upddate'])) : '';

    echo "<tr>
            <td>".$no++."</td>
            <td>".htmlspecialchars($row['transno'])."</td>
            <td>".htmlspecialchars($row['transname'])."</td>
            <td>".$transdate."</td>
            <td>".htmlspecialchars($row['wrhscode'])."</td>
            <td>".htmlspecialchars($row['wrhsname'])."</td>
            <td>".htmlspecialchars($row['prodcode'])."</td>
            <td>".htmlspecialchars($row['prodname'])."</td>
            <td>".number_format($row['qty_in'],2)."</td>
            <td>".number_format($row['qty_out'],2)."</td>
            <td>".htmlspecialchars($row['uomname'])."</td>
            <td>".$updDate."</td>
            <td>".htmlspecialchars($row['upduser'])."</td>
          </tr>";
}

echo "</tbody></table>";

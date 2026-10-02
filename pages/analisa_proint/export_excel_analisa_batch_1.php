<?php
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

$batchno = isset($_GET['batchno']) ? trim($_GET['batchno']) : '';
if ($batchno === '') {
    die("Batch tidak valid.");
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Histori_Batch_" . $batchno . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

$query = "
   SELECT DISTINCT
        A.transdestnmbr, 
        A.transdesttype, 
        D.transname, 
        E.wrhscode, 
        E.wrhsname, 
        A.transdestdate, 
        B.prodcode, 
        B.prodname, 
        G.uomname, 
        C.batchno, 
        C.batchinqty, 
        C.batchoutqty, 
        B.processdate, 
        B.upddate, 
        B.upduser,
        'NEW' AS sumber_batch
    FROM
        whtranshd AS A
        INNER JOIN whtransdt AS B ON A.transhdid = B.transhdid
        INNER JOIN whtransdtbatch AS C ON B.transdtid = C.whtransdtid
        INNER JOIN whtransms AS D ON A.transdesttype = D.transcode
        LEFT JOIN whwrhs AS E ON A.transdestwrhsid = E.wrhsid
        INNER JOIN smproduct AS F ON B.transprodid = F.prodid
        INNER JOIN smuom AS G ON F.uomid = G.uomid
    WHERE
        C.batchno = :batchno

    UNION ALL

    SELECT DISTINCT
        A.transdestnmbr, 
        A.transdesttype, 
        D.transname, 
        E.wrhscode, 
        E.wrhsname, 
        A.transdestdate, 
        B.prodcode, 
        B.prodname, 
        G.uomname, 
        C.batchno, 
        C.batchinqty, 
        C.batchoutqty, 
        B.processdate, 
        B.upddate, 
        B.upduser,
        'OLD' AS sumber_batch
    FROM
        whtranshd AS A
        INNER JOIN whtransdt AS B ON A.transhdid = B.transhdid
        INNER JOIN whtransdtbatch2022 AS C ON B.transdtid = C.whtransdtid
        INNER JOIN whtransms AS D ON A.transdesttype = D.transcode
        LEFT JOIN whwrhs AS E ON A.transdestwrhsid = E.wrhsid
        INNER JOIN smproduct AS F ON B.transprodid = F.prodid
        INNER JOIN smuom AS G ON F.uomid = G.uomid
    WHERE
        C.batchno = :batchno

    ORDER BY
        upddate ASC, 
        transdesttype DESC;
";

$stmt = $conn3->prepare($query);
$stmt->bindValue(':batchno', $batchno);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output header kolom
echo "<table border='1'>";
echo "<thead><tr>
        <th>No</th>
        <th>Trans No</th>
        <th>Trans Name</th>
        <th>Warehouse</th>
        <th>Product</th>
        <th>Batch</th>        
        <th>In Qty</th>
        <th>Out Qty</th>
        <th>UOM</th>
        <th>Sumber</th>
        <th>Process Date</th>
        <th>Update Date</th>
        <th>Update User</th>
    </tr></thead><tbody>";

$no = 1;
foreach ($rows as $r) {
    echo "<tr>";
    echo "<td>".$no++."</td>";
    echo "<td>".htmlspecialchars($r['transdestnmbr'])."</td>";
    echo "<td>".htmlspecialchars($r['transname'])."</td>";
    echo "<td>".htmlspecialchars($r['wrhscode'])." - ".htmlspecialchars($r['wrhsname'])."</td>";
    echo "<td>".htmlspecialchars($r['prodcode'])." - ".htmlspecialchars($r['prodname'])."</td>";
    echo "<td>".htmlspecialchars($r['batchno'])."</td>";    
    echo "<td>".number_format($r['batchinqty'],2)."</td>";
    echo "<td>".number_format($r['batchoutqty'],2)."</td>";
    echo "<td>".htmlspecialchars($r['uomname'])."</td>";
    echo "<td>".htmlspecialchars($r['sumber_batch'])."</td>";
    echo "<td>".($r['processdate'] ? date("d/m/Y", strtotime($r['processdate'])) : '')."</td>";
    echo "<td>".($r['upddate'] ? date("d/m/Y H:i", strtotime($r['upddate'])) : '')."</td>";
    echo "<td>".htmlspecialchars($r['upduser'])."</td>";
    echo "</tr>";
}
echo "</tbody></table>";

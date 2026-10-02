<?php
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

// Ambil ketiga parameter
$batchno = isset($_GET['batchno']) ? trim($_GET['batchno']) : '';
$transno = isset($_GET['transno']) ? trim($_GET['transno']) : '';
$cpno = isset($_GET['cpno']) ? trim($_GET['cpno']) : '';

// Validasi minimal satu filter harus diisi
if ($batchno === '' && $transno === '' && $cpno === '') {
    die("Batch No, Transaction No, atau No CP harus diisi.");
}

// Generate nama file berdasarkan filter yang digunakan
$filename = "Histori_Batch";
if ($batchno !== '') {
    $filename .= "_" . $batchno;
}
if ($transno !== '') {
    $filename .= "_Trans_" . $transno;
}
if ($cpno !== '') {
    $filename .= "_CP_" . $cpno;
}
$filename .= ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=" . $filename);
header("Pragma: no-cache");
header("Expires: 0");

// Query yang sama dengan halaman utama
$query = "
    SELECT DISTINCT
        A.transdestnmbr, 
        J.prdnmbr,
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
        INNER JOIN pdresultdt AS H ON C.batchno = H.batchno
        INNER JOIN pdresulthd AS I ON H.resulthdid = I.resulthdid
        INNER JOIN pdproductionhd AS J ON I.productionhdid = J.productionhdid
    WHERE
        1=1";

$query_old = "
    SELECT DISTINCT
        A.transdestnmbr, 
        J.prdnmbr,
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
        INNER JOIN pdresultdt AS H ON C.batchno = H.batchno
        INNER JOIN pdresulthd AS I ON H.resulthdid = I.resulthdid
        INNER JOIN pdproductionhd AS J ON I.productionhdid = J.productionhdid
    WHERE
        1=1";

// Tambahkan kondisi WHERE berdasarkan filter
$conditions = [];
$params = [];

if ($batchno !== '') {
    $conditions[] = "C.batchno = :batchno";
    $params[':batchno'] = $batchno;
}

if ($transno !== '') {
    $conditions[] = "A.transdestnmbr LIKE :transno";
    $params[':transno'] = '%' . $transno . '%';
}

if ($cpno !== '') {
    $conditions[] = "J.prdnmbr LIKE :cpno";
    $params[':cpno'] = '%' . $cpno . '%';
}

// Gabungkan kondisi WHERE
if (!empty($conditions)) {
    $where_clause = " AND " . implode(" AND ", $conditions);
    $query .= $where_clause;
    $query_old .= $where_clause;
}

// Gabungkan kedua query dengan UNION ALL
$final_query = $query . " UNION ALL " . $query_old . " ORDER BY upddate ASC, transdesttype DESC";

$stmt = $conn3->prepare($final_query);

// Bind parameter
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output header kolom
echo "<table border='1'>";
echo "<tr><td colspan='17' style='font-weight:bold; text-align:center; background-color:#e0e0e0;'>HISTORY BATCH REPORT</td></tr>";

// Informasi filter yang digunakan
echo "<tr><td colspan='17' style='font-weight:bold;'>Filter: ";
$filters = [];
if ($batchno !== '') {
    $filters[] = "Batch No: " . htmlspecialchars($batchno);
}
if ($transno !== '') {
    $filters[] = "Transaction No: " . htmlspecialchars($transno);
}
if ($cpno !== '') {
    $filters[] = "No CP: " . htmlspecialchars($cpno);
}
echo implode(" | ", $filters);
echo "</td></tr>";

echo "<tr><td colspan='17' style='font-weight:bold;'>Export Date: " . date("d/m/Y H:i") . "</td></tr>";
echo "<tr><td colspan='17'></td></tr>"; // Spacer

echo "<thead><tr style='background-color:#f0f0f0; font-weight:bold;'>
        <th>No</th>
        <th>Trans No</th>
        <th>No CP</th>
        <th>Trans Type</th>
        <th>Warehouse Code</th>
        <th>Warehouse Name</th>
        <th>Product Code</th>
        <th>Product Name</th>
        <th>Batch No</th>
        <th>In Qty</th>
        <th>Out Qty</th>
        <th>UOM</th>
        <th>Sumber</th>
        <th>Transaction Date</th>
        <th>Process Date</th>
        <th>Update Date</th>
        <th>Update User</th>
    </tr></thead><tbody>";

$no = 1;
foreach ($rows as $r) {
    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars($r['transdestnmbr']) . "</td>";
    echo "<td>" . htmlspecialchars($r['prdnmbr']) . "</td>";
    echo "<td>" . htmlspecialchars($r['transname']) . "</td>";
    echo "<td>" . htmlspecialchars($r['wrhscode']) . "</td>";
    echo "<td>" . htmlspecialchars($r['wrhsname']) . "</td>";
    echo "<td>" . htmlspecialchars($r['prodcode']) . "</td>";
    echo "<td>" . htmlspecialchars($r['prodname']) . "</td>";
    echo "<td>" . htmlspecialchars($r['batchno']) . "</td>";
    echo "<td>" . number_format($r['batchinqty'], 2) . "</td>";
    echo "<td>" . number_format($r['batchoutqty'], 2) . "</td>";
    echo "<td>" . htmlspecialchars($r['uomname']) . "</td>";
    echo "<td>" . htmlspecialchars($r['sumber_batch']) . "</td>";
    echo "<td>" . ($r['transdestdate'] ? date("d/m/Y", strtotime($r['transdestdate'])) : '') . "</td>";
    echo "<td>" . ($r['processdate'] ? date("d/m/Y", strtotime($r['processdate'])) : '') . "</td>";
    echo "<td>" . ($r['upddate'] ? date("d/m/Y H:i", strtotime($r['upddate'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($r['upduser']) . "</td>";
    echo "</tr>";
}

// Footer dengan total records
echo "<tr><td colspan='17' style='font-weight:bold; background-color:#f0f0f0;'>Total Records: " . count($rows) . "</td></tr>";

echo "</tbody></table>";
<?php
// Export Excel untuk Bale Lookup
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';
if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}
$batchnos = isset($_GET['batchno']) ? trim($_GET['batchno']) : '';
$batchArr = array_filter(array_map('trim', preg_split('/\r?\n/', $batchnos)));
$results = [];
if (count($batchArr) > 0) {
    $inClause = implode(",", array_map(function($b) use ($conn) { return "'" . $conn->real_escape_string($b) . "'"; }, $batchArr));
    $sql = "WITH LatestUpdate AS (
        SELECT batchno, MAX(upddate) AS max_upddate
        FROM whbaledt
        GROUP BY batchno
    )
    SELECT DISTINCT
        wb.balehdid AS balehdid,
        wb.refnmbr AS baleno,
        wb.balenmbr AS packingno,
        rd.batchno AS batchno
    FROM pdresultdt rd
    INNER JOIN pdresulthd rh ON rd.resulthdid = rh.resulthdid
    LEFT JOIN smuom uom1 ON rd.resultstduomid = uom1.uomid
    LEFT JOIN smuom uom2 ON rd.resultuomid = uom2.uomid
    INNER JOIN pdproductionhd ph ON rh.productionhdid = ph.productionhdid
    LEFT JOIN smuom uom ON rd.resultuomid = uom.uomid
    LEFT JOIN whtransdtbatch tb ON rd.batchno = tb.batchno AND rd.whtranshdid = tb.transhdid
    INNER JOIN whbaledt wd ON rd.batchno = wd.batchno
    INNER JOIN LatestUpdate lu ON wd.batchno = lu.batchno AND wd.upddate = lu.max_upddate
    INNER JOIN whbalehd wb ON wd.balehdid = wb.balehdid
    INNER JOIN whbaleprod wp ON wd.balehdid = wp.balehdid AND wp.prodid = tb.prodid
    INNER JOIN whwrhs wh ON wp.wrhsid = wh.wrhsid
    WHERE wd.batchno IN ($inClause)";
    $query = $conn->query($sql);
    if ($query) {
        while ($row = $query->fetch_assoc()) {
            $results[] = $row;
        }
    }
}
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="bale_export_' . date('Ymd_His') . '.xls"');
echo "No\tbalehdid\tbaleno\tpackingno\tbatchno\n";
$no = 1;
foreach ($results as $row) {
    echo $no++ . "\t" . $row['balehdid'] . "\t" . $row['baleno'] . "\t" . $row['packingno'] . "\t" . $row['batchno'] . "\n";
}
exit;

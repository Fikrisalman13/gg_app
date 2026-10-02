<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$resultdate_start = isset($_GET['resultdate_start']) ? trim($_GET['resultdate_start']) : '';
$resultdate_end = isset($_GET['resultdate_end']) ? trim($_GET['resultdate_end']) : '';
$prodcode_input = isset($_GET['prodcode']) ? trim($_GET['prodcode']) : '';
$transdestnmbr = isset($_GET['transdestnmbr']) ? trim($_GET['transdestnmbr']) : '';
$results = [];

if ($resultdate_start !== '' && $resultdate_end !== '') {
    $params = [$resultdate_start, $resultdate_end];
    $where = "pdresultmat.resultdate BETWEEN ? AND ?";
    $query = "SELECT\n        whtranshd.transdestnmbr AS trans_No,\n        whtranshd.transdestdate AS trans_Date,\n        whwrhs.wrhsname AS Warehouse,\n        whtransms.transname AS Trans_Name,\n        pdproductionhd.prdnmbr AS PRDNMBR,\n        pdresultmat.prodcode AS Product_code,\n        pdresultmat.prodname AS Product_name,\n        pdresultmat.matstdqty AS STD_QTY,\n        smuom.uomname AS UOM\n    FROM\n        pdresultmat\n    JOIN whtranshd\n        ON pdresultmat.whtranshdid = whtranshd.transhdid\n    JOIN pdproductionhd\n        ON pdresultmat.productionhdid = pdproductionhd.productionhdid\n    JOIN whwrhs\n        ON whtranshd.transdestwrhsid = whwrhs.wrhsid\n    JOIN whtransms\n        ON whtranshd.transdesttype = whtransms.transcode\n    JOIN smuom\n        ON pdresultmat.matstduomid = smuom.uomid\n    WHERE ";
    if ($prodcode_input !== '') {
        $prodcode_arr = array_filter(array_map('trim', preg_split('/\r?\n|,/', $prodcode_input)));
        if (count($prodcode_arr) > 0) {
            $prodcode_placeholders = implode(',', array_fill(0, count($prodcode_arr), '?'));
            $where .= " AND pdresultmat.prodcode IN ($prodcode_placeholders)";
            $params = array_merge($params, $prodcode_arr);
        }
    }
    if ($transdestnmbr !== '') {
        $where .= " AND whtranshd.transdestnmbr = ?";
        $params[] = $transdestnmbr;
    }
    $where = preg_replace('/^ AND /', '', $where);
    $query .= $where;
    $stmt = $conn3->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$headers = [
    'No', 'Trans No', 'Trans Date', 'Warehouse', 'Trans Name', 'PRDNMBR', 'Product Code', 'Product Name', 'STD QTY', 'UOM'
];
$sheet->fromArray($headers, NULL, 'A1');
$rowNum = 2;
$no = 1;
foreach ($results as $row) {
    $sheet->fromArray([
        $no++,
        $row['trans_no'] ?? '',
        $row['trans_date'] ?? '',
        $row['warehouse'] ?? '',
        $row['trans_name'] ?? '',
        $row['prdnmbr'] ?? '',
        $row['product_code'] ?? '',
        $row['product_name'] ?? '',
        $row['std_qty'] ?? '',
        $row['uom'] ?? ''
    ], NULL, 'A'.$rowNum++);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="resultmat_export.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

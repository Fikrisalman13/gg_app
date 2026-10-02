<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die('Silakan login terlebih dahulu!');
}

$prodcode  = isset($_GET['prodcode']) ? trim($_GET['prodcode']) : '';
$transtype = isset($_GET['transtype']) ? trim($_GET['transtype']) : '';
$wrhsid    = isset($_GET['wrhsid']) && ctype_digit($_GET['wrhsid']) ? trim($_GET['wrhsid']) : '';
$token     = isset($_GET['downloadToken']) ? $_GET['downloadToken'] : '';

if ($prodcode === '') {
    die('Kode produk tidak ditemukan!');
}

if ($token) {
    setcookie('downloadToken', $token, time() + 3600, '/');
}

$safeProdcode = preg_replace('/[^A-Za-z0-9._-]+/', '_', $prodcode);
if ($safeProdcode === '') {
    $safeProdcode = 'export';
}

$whereClauses = ['b.prodcode = :prodcode'];
$params = [':prodcode' => $prodcode];

if ($transtype !== '') {
    $whereClauses[] = 'a.transdesttype = :transtype';
    $params[':transtype'] = $transtype;
}
if ($wrhsid !== '') {
    $whereClauses[] = 'a.transdestwrhsid = :wrhsid';
    $params[':wrhsid'] = (int) $wrhsid;
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

try {
    $stmt = $conn3->prepare($query);
    foreach ($params as $key => $val) {
        if (is_int($val)) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $val);
        }
    }
    $stmt->execute();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $filename = 'Histori_Produk_' . $safeProdcode . '.xlsx';
    $writer = new Writer();
    $writer->openToBrowser($filename);
    $writer->getCurrentSheet()->setName('Histori Produk');

    $headerStyle = new Style();
    $headerStyle->setFontBold();
    $headerStyle->setBackgroundColor(Color::toARGB(Color::rgb(217, 217, 217)));
    $headerStyle->setCellAlignment(CellAlignment::CENTER);

    $headers = [
        'No',
        'Trans No',
        'Trans Name',
        'Tanggal',
        'Warehouse Code',
        'Warehouse Name',
        'Product Code',
        'Product Name',
        'Qty In',
        'Qty Out',
        'UOM',
        'Update Date',
        'Update User',
    ];
    $writer->addRow(Row::fromValues($headers, $headerStyle));

    $qtyStyle = (new Style())->setFormat('0.00');
    $qtyColumnStyles = [
        8 => $qtyStyle,
        9 => $qtyStyle,
    ];

    $no = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $transdate = $row['transdate'] ? date('d/m/Y', strtotime($row['transdate'])) : '';
        $updDate   = $row['upddate'] ? date('d/m/Y H:i', strtotime($row['upddate'])) : '';

        $qtyIn = ($row['qty_in'] !== null && $row['qty_in'] !== '') ? (float) $row['qty_in'] : 0.0;
        $qtyOut = ($row['qty_out'] !== null && $row['qty_out'] !== '') ? (float) $row['qty_out'] : 0.0;

        $values = [
            $no++,
            (string) ($row['transno'] ?? ''),
            (string) ($row['transname'] ?? ''),
            $transdate,
            (string) ($row['wrhscode'] ?? ''),
            (string) ($row['wrhsname'] ?? ''),
            (string) ($row['prodcode'] ?? ''),
            (string) ($row['prodname'] ?? ''),
            $qtyIn,
            $qtyOut,
            (string) ($row['uomname'] ?? ''),
            $updDate,
            (string) ($row['upduser'] ?? ''),
        ];

        $writer->addRow(Row::fromValuesWithStyles($values, null, $qtyColumnStyles));
    }

    $writer->close();
    exit;
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

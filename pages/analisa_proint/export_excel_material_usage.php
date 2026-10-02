<?php
session_start();
date_default_timezone_set('Asia/Jakarta'); // ✅ Pastikan waktu mengikuti WIB
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

$prdnmbr = isset($_GET['prdnmbr']) ? trim($_GET['prdnmbr']) : '';
$startdate = isset($_GET['startdate']) ? trim($_GET['startdate']) : date('Ymd', strtotime('-1 month'));
$enddate = isset($_GET['enddate']) ? trim($_GET['enddate']) : date('Ymd');
$fgusedtype = isset($_GET['fgusedtype']) ? trim($_GET['fgusedtype']) : 'ALL';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Material_Usage_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    $query = "
    WITH mat_sum AS (
        SELECT
            productionhdid,
            SUM(actualqtyprice) AS total_cost
        FROM pdresultmat
        GROUP BY productionhdid
    )
    SELECT
        h.prdnmbr,
        h.prodcode,
        h.prodname,
        t.labeljual,
        t.cuscolor,
        c.colorcode,
        c.colorname,
        r.rtgcode,
        r.rtgname,
        m.prodcode AS material_code,
        m.prodname AS material_name,
        h.prdqty,
        rt.prdstdqty,
        rh.resultqty AS routing_qty,
        m.matqty,
        u.uomcode,
        m.actualprice AS unit_price,
        m.actualqtyprice AS subtotal,
        ms.total_cost / rt.prdstdqty AS cost_meter,
        m.prodcf,
        b.bonweight,
        bh.bomcode,
        b.vlot,
        rt.upddate,
        rt.upduser,
        m.fgusedtype,
        m.accmsid
    FROM pdproductionhd h
    JOIN smprodtechdata t ON t.prodid = h.prodid
    JOIN pdcolorms c ON c.colormsid = h.colorid
    JOIN pdresultmat m ON m.productionhdid = h.productionhdid
    JOIN smuom u ON u.uomid = m.matuomid
    JOIN pdproductionrtg rt ON m.productionrtgid = rt.productionrtgid
    JOIN pdrtgms r ON rt.rtgmsid = r.rtgmsid
    LEFT JOIN pdresulthd rh ON rh.productionhdid = h.productionhdid
    LEFT JOIN pdbonreq b 
        ON h.productionhdid = b.productionhdid
        AND rt.productionrtgid = b.productionrtgid
    LEFT JOIN pdbomhd bh 
        ON b.bomhdid = bh.bomhdid
        AND m.bomhdid = bh.bomhdid
    JOIN mat_sum ms ON ms.productionhdid = h.productionhdid
    WHERE
        h.fgstatus = 'X'
        AND h.prdcenterid = 53
        AND h.prddate BETWEEN :startdate AND :enddate
    ";

    // Tambahkan filter fgusedtype jika bukan "ALL"
    if ($fgusedtype !== 'ALL') {
        if ($fgusedtype == 'ACCESSORY') {
            $query .= " AND m.accmsid IS NOT NULL";
        } else {
            $query .= " AND m.fgusedtype = :fgusedtype";
        }
    }

    // Tambahkan filter prdnmbr jika diisi
    if ($prdnmbr !== '') {
        $query .= " AND h.prdnmbr = :prdnmbr";
    }

    $query .= " ORDER BY rt.upddate";

    $stmt = $conn3->prepare($query);
    $stmt->bindParam(':startdate', $startdate);
    $stmt->bindParam(':enddate', $enddate);
    
    if ($fgusedtype !== 'ALL' && $fgusedtype !== 'ACCESSORY') {
        $stmt->bindParam(':fgusedtype', $fgusedtype);
    }
    
    if ($prdnmbr !== '') {
        $stmt->bindParam(':prdnmbr', $prdnmbr);
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error executing query: " . $e->getMessage());
}

// Buat tabel HTML untuk diekspor
echo "<table border='1'>";
echo "<tr>
        <th colspan='27' style='background-color: #d9edf7; font-size: 16px; font-weight: bold;'>
            MATERIAL USAGE REPORT
        </th>
      </tr>";
      
// Informasi filter
echo "<tr>
        <th colspan='27' style='background-color: #f5f5f5;'>
            Periode: " . htmlspecialchars(date('d/m/Y', strtotime($startdate))) . " s/d " . htmlspecialchars(date('d/m/Y', strtotime($enddate))) . "
        </th>
      </tr>";

// Informasi jenis material
$jenis_material = '';
if ($fgusedtype == 'ALL') {
    $jenis_material = 'Semua Jenis';
} elseif ($fgusedtype == 'G') {
    $jenis_material = 'Obat';
} elseif ($fgusedtype == 'A') {
    $jenis_material = 'Kain Grey';
} elseif ($fgusedtype == 'ACCESSORY') {
    $jenis_material = 'Aksesoris';
}

echo "<tr>
        <th colspan='27' style='background-color: #f5f5f5;'>
            Jenis Material: " . htmlspecialchars($jenis_material) . 
            ($prdnmbr !== '' ? " | CP Number: " . htmlspecialchars($prdnmbr) : "") . "
        </th>
      </tr>";

// Header kolom
echo "<tr style='background-color: #f8f9fa; font-weight: bold;'>
        <th>No</th>
        <th>Update Date</th>
        <th>CP Number</th>
        <th>Product Code</th>
        <th>Product Name</th>
        <th>Label Jual</th>
        <th>Customer Color</th>
        <th>Color Code</th>
        <th>Color Name</th>
        <th>Routing Code</th>
        <th>Routing Name</th>
        <th>Material Code</th>
        <th>Material Name</th>
        <th>PRD Qty</th>
        <th>PRD Std Qty</th>
        <th>Routing Qty</th>
        <th>Material Qty</th>
        <th>UOM</th>
        <th>Unit Price</th>
        <th>Subtotal</th>
        <th>Cost/Meter</th>
        <th>PRODCF</th>
        <th>BON Weight</th>
        <th>BOM Code</th>
        <th>VLOT</th>
        <th>Update User</th>
        <th>Jenis Material</th>
      </tr>";

$no = 1;
$total_prdqty = 0;
$total_prdstdqty = 0;
$total_routingqty = 0;
$total_matqty = 0;
$total_subtotal = 0;
$total_bonweight = 0;
$type_counts = [
    'G' => 0,
    'A' => 0,
    'ACCESSORY' => 0
];

foreach ($results as $row) {
    $total_prdqty += $row['prdqty'];
    $total_prdstdqty += $row['prdstdqty'];
    $total_routingqty += $row['routing_qty'] ?? 0;
    $total_matqty += $row['matqty'];
    $total_subtotal += $row['subtotal'];
    $total_bonweight += $row['bonweight'] ?? 0;
    
    // Tentukan jenis material
    $material_type = 'Unknown';
    $type_label = '';
    
    if ($row['accmsid'] !== null) {
        $material_type = 'ACCESSORY';
        $type_label = 'Aksesoris';
        $type_counts['ACCESSORY']++;
    } elseif ($row['fgusedtype'] == 'G') {
        $material_type = 'G';
        $type_label = 'Obat';
        $type_counts['G']++;
    } elseif ($row['fgusedtype'] == 'A') {
        $material_type = 'A';
        $type_label = 'Kain Grey';
        $type_counts['A']++;
    }
    
    $update_date = $row['upddate'] ? date("d/m/Y", strtotime($row['upddate'])) : '';
    
    echo "<tr>
            <td style='text-align: center;'>".$no++."</td>
            <td style='text-align: center;'>".$update_date."</td>
            <td>".htmlspecialchars($row['prdnmbr'])."</td>
            <td>".htmlspecialchars($row['prodcode'])."</td>
            <td>".htmlspecialchars($row['prodname'])."</td>
            <td>".htmlspecialchars($row['labeljual'])."</td>
            <td>".htmlspecialchars($row['cuscolor'])."</td>
            <td>".htmlspecialchars($row['colorcode'])."</td>
            <td>".htmlspecialchars($row['colorname'])."</td>
            <td>".htmlspecialchars($row['rtgcode'])."</td>
            <td>".htmlspecialchars($row['rtgname'])."</td>
            <td>".htmlspecialchars($row['material_code'])."</td>
            <td>".htmlspecialchars($row['material_name'])."</td>
            <td style='text-align: right;'>".number_format($row['prdqty'], 2)."</td>
            <td style='text-align: right;'>".number_format($row['prdstdqty'], 2)."</td>
            <td style='text-align: right;'>".($row['routing_qty'] ? number_format($row['routing_qty'], 2) : '-')."</td>
            <td style='text-align: right;'>".number_format($row['matqty'], 4)."</td>
            <td style='text-align: center;'>".htmlspecialchars($row['uomcode'])."</td>
            <td style='text-align: right;'>".number_format($row['unit_price'], 2)."</td>
            <td style='text-align: right;'>".number_format($row['subtotal'], 2)."</td>
            <td style='text-align: right;'>".number_format($row['cost_meter'], 4)."</td>
            <td style='text-align: center;'>".htmlspecialchars($row['prodcf'])."</td>
            <td style='text-align: right;'>".($row['bonweight'] ? number_format($row['bonweight'], 4) : '-')."</td>
            <td>".htmlspecialchars($row['bomcode'])."</td>
            <td>".htmlspecialchars($row['vlot'])."</td>
            <td>".htmlspecialchars($row['upduser'])."</td>
            <td style='text-align: center;'>".htmlspecialchars($type_label)."</td>
          </tr>";
}

// Baris total
echo "<tr style='background-color: #e9ecef; font-weight: bold;'>
        <td colspan='13' style='text-align: center;'>TOTAL</td>
        <td style='text-align: right;'>".number_format($total_prdqty, 2)."</td>
        <td style='text-align: right;'>".number_format($total_prdstdqty, 2)."</td>
        <td style='text-align: right;'>".number_format($total_routingqty, 2)."</td>
        <td style='text-align: right;'>".number_format($total_matqty, 4)."</td>
        <td></td>
        <td></td>
        <td style='text-align: right;'>".number_format($total_subtotal, 2)."</td>
        <td></td>
        <td></td>
        <td style='text-align: right;'>".number_format($total_bonweight, 4)."</td>
        <td colspan='4'></td>
      </tr>";

// Baris jumlah per jenis (hanya jika filter ALL)
if ($fgusedtype == 'ALL') {
    echo "<tr style='background-color: #fff3cd; font-weight: bold;'>
            <td colspan='13' style='text-align: center;'>JUMLAH PER JENIS MATERIAL</td>
            <td colspan='4'>
                Obat: " . $type_counts['G'] . " | 
                Kain Grey: " . $type_counts['A'] . " | 
                Aksesoris: " . $type_counts['ACCESSORY'] . "
            </td>
            <td colspan='10'></td>
          </tr>";
}

echo "</table>";

// Tambahkan informasi footer
echo "<div style='margin-top: 20px; font-size: 12px; color: #6c757d;'>
        <p>Dicetak pada: " . date('d/m/Y H:i:s') . "</p>
        <p>Oleh: " . htmlspecialchars($_SESSION['UserName'] ?? 'Unknown') . "</p>
        <p>Total Records: " . count($results) . " data</p>
      </div>";

// Tambahkan styling untuk Excel
echo "<style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th {
            background-color: #4CAF50;
            color: white;
            padding: 8px;
            text-align: center;
            vertical-align: middle;
        }
        td {
            padding: 6px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background-color: #e8f5e9;
            font-weight: bold;
        }
    </style>";
<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
require_once __DIR__ . '/kwh_listrik2_common.php';

$start = kwhl2_normalize_date($_POST['start_date'] ?? date('Y-m-01'));
$end = kwhl2_normalize_date($_POST['end_date'] ?? date('Y-m-d'));
if ($start === '') $start = date('Y-m-01');
if ($end === '') $end = date('Y-m-d');

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="report_kwh_listrik2_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$rows = [];
if (kwhl2_table_exists($conn)) {
    $tableName = kwhl2_table_full_name($conn);
    if ($tableName !== '') {
        $sql = "SELECT * FROM {$tableName}
                WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
                ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
        $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = kwhl2_row_from_db($r);
            }
            sqlsrv_free_stmt($stmt);
        }
    }
}

$machines = kwhl2_machine_defs();
$totKwh = [];
$totBiaya = [];
foreach ($machines as $code => $m) {
    $totKwh['kwh_' . $code] = 0.0;
    $totBiaya['biaya_' . $code] = 0.0;
}
$totAllKwh = 0.0;
$totAllBiaya = 0.0;

foreach ($rows as $r) {
    $rowKwhSum = 0.0;
    $rowBiayaSum = 0.0;
    foreach ($machines as $code => $m) {
        $kwhVal = (float)($r[$m['kwh_key']] ?? 0);
        $biayaVal = (float)($r[$m['biaya_key']] ?? 0);
        $totKwh['kwh_' . $code] += $kwhVal;
        $totBiaya['biaya_' . $code] += $biayaVal;
        $rowKwhSum += $kwhVal;
        $rowBiayaSum += $biayaVal;
    }
    $totAllKwh += $rowKwhSum;
    $totAllBiaya += $rowBiayaSum;
}

$count = count($rows);
$avgKwh = [];
$avgBiaya = [];
foreach ($machines as $code => $m) {
    $avgKwh['kwh_' . $code] = $count > 0 ? ($totKwh['kwh_' . $code] / $count) : 0;
    $avgBiaya['biaya_' . $code] = $count > 0 ? ($totBiaya['biaya_' . $code] / $count) : 0;
}
$avgAllKwh = $count > 0 ? ($totAllKwh / $count) : 0;
$avgAllBiaya = $count > 0 ? ($totAllBiaya / $count) : 0;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER',
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
$totalColumns = 1 + count($machines) + count($machines) + 1 + 1; // 17 columns

echo "\xEF\xBB\xBF";
echo "<html><head><meta charset='UTF-8'><style>
table{border-collapse:collapse;font-size:11px;background:#fff;width:100%}
th,td{border:1px solid #000;text-align:center;vertical-align:middle;padding:3px 5px;white-space:nowrap}
.title{font-weight:700;font-size:24px;border:none !important}
.month{font-weight:700;font-size:13px;text-align:left;border:none !important}
.datecol{background:#d9d9d9;font-weight:700}
.grp-kwh{background:#ffc000;font-weight:700;color:#000}
.grp-biaya{background:#d9d9d9;font-weight:700;color:#000}
.hdr{background:#ffff00;font-weight:700}
.unit{background:#ffffff;font-weight:700}
.kwh-cell{background:#d9e2f3}
.biaya-cell{background:#ffffff}
.total-kwh-cell{background:#d9e2f3;font-weight:700}
.total-biaya-cell{background:#ffffff;font-weight:700}
.sum td{background:#ffc000 !important;font-weight:700;color:#000}
</style></head><body>";

echo "<table>";
echo "<tr><th class='month' colspan='{$totalColumns}'>BULAN : " . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr><th class='title' colspan='{$totalColumns}'>PENCATATAN AMPERE DAN KWH METER</th></tr>";
echo "<tr>";
echo "<th class='datecol' rowspan='3'>TANGGAL</th>";
echo "<th class='grp-kwh' colspan='" . count($machines) . "'>KWH / HARI</th>";
echo "<th class='grp-biaya' colspan='" . count($machines) . "'>TOTAL BIAYA</th>";
echo "<th class='grp-kwh' rowspan='2'>TOTAL KWH / HARI</th>";
echo "<th class='grp-biaya' rowspan='2'>TOTAL BIAYA</th>";
echo "</tr>";

echo "<tr>";
foreach ($machines as $m) echo "<th class='hdr'>" . htmlspecialchars($m['label']) . "</th>";
foreach ($machines as $m) echo "<th class='hdr'>" . htmlspecialchars($m['label']) . "</th>";
echo "</tr>";

echo "<tr>";
for ($i = 0; $i < count($machines); $i++) echo "<th class='unit'>kWh/hari</th>";
for ($i = 0; $i < count($machines); $i++) echo "<th class='unit'>Rp</th>";
echo "<th class='unit'>kWh/hari</th><th class='unit'>Rp</th>";
echo "</tr>";

if (empty($rows)) {
    echo "<tr><td colspan='{$totalColumns}'>Tidak ada data pada rentang tanggal ini.</td></tr>";
} else {
    foreach ($rows as $r) {
        $rowKwhSum = 0.0;
        $rowBiayaSum = 0.0;
        foreach ($machines as $code => $m) {
            $rowKwhSum += (float)($r[$m['kwh_key']] ?? 0);
            $rowBiayaSum += (float)($r[$m['biaya_key']] ?? 0);
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars(kwhl2_format_day($r['tanggal'])) . "</td>";
        foreach ($machines as $code => $m) {
            echo "<td class='kwh-cell'>" . htmlspecialchars(kwhl2_format_num($r[$m['kwh_key']] ?? 0, 2)) . "</td>";
        }
        foreach ($machines as $code => $m) {
            echo "<td class='biaya-cell'>" . htmlspecialchars(kwhl2_format_num($r[$m['biaya_key']] ?? 0, 0)) . "</td>";
        }
        echo "<td class='total-kwh-cell'>" . htmlspecialchars(kwhl2_format_num($rowKwhSum, 2)) . "</td>";
        echo "<td class='total-biaya-cell'>" . htmlspecialchars(kwhl2_format_num($rowBiayaSum, 0)) . "</td>";
        echo "</tr>";
    }

    echo "<tr class='sum'><td>TOTAL</td>";
    foreach ($machines as $code => $m) {
        echo "<td>" . htmlspecialchars(kwhl2_format_num($totKwh['kwh_' . $code], 2)) . "</td>";
    }
    foreach ($machines as $code => $m) {
        echo "<td>" . htmlspecialchars(kwhl2_format_num($totBiaya['biaya_' . $code], 0)) . "</td>";
    }
    echo "<td>" . htmlspecialchars(kwhl2_format_num($totAllKwh, 2)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl2_format_num($totAllBiaya, 0)) . "</td></tr>";

    echo "<tr class='sum'><td>RATA-RATA</td>";
    foreach ($machines as $code => $m) {
        echo "<td>" . htmlspecialchars(kwhl2_format_num($avgKwh['kwh_' . $code], 2)) . "</td>";
    }
    foreach ($machines as $code => $m) {
        echo "<td>" . htmlspecialchars(kwhl2_format_num($avgBiaya['biaya_' . $code], 0)) . "</td>";
    }
    echo "<td>" . htmlspecialchars(kwhl2_format_num($avgAllKwh, 2)) . "</td>";
    echo "<td>" . htmlspecialchars(kwhl2_format_num($avgAllBiaya, 0)) . "</td></tr>";
}

echo "</table></body></html>";

<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

$start = $_POST['start_date'] ?? date('Y-m-01');
$end = $_POST['end_date'] ?? date('Y-m-d');

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Biaya_kimia_boiler_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

$groupOrderExpr = "CASE m.grup_laporan
    WHEN 'IPAL' THEN 1
    WHEN 'PROSES' THEN 2
    WHEN 'DAF_LAMA' THEN 3
    WHEN 'DAF3_BARU' THEN 4
    WHEN 'DAF2_BARU' THEN 5
    ELSE 99 END";

$groupLabels = [
    'IPAL' => 'IPAL',
    'PROSES' => 'PROSES',
    'DAF_LAMA' => 'DAF LAMA',
    'DAF3_BARU' => 'DAF 3 BARU',
    'DAF2_BARU' => 'DAF 2 BARU',
];

$masters = [];
$masterById = [];
$groups = [];

$masterSql = "SELECT m.id, m.kode, m.nama_item, m.grup_laporan, m.satuan_pakai
              FROM dbo.kimia_boiler_master m
              WHERE m.aktif = 1
              ORDER BY $groupOrderExpr, m.id ASC";
$masterStmt = sqlsrv_query($conn, $masterSql);
if ($masterStmt === false) { echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true)); exit; }
while ($m = sqlsrv_fetch_array($masterStmt, SQLSRV_FETCH_ASSOC)) {
    $mid = (int)($m['id'] ?? 0);
    if ($mid <= 0) continue;
    $g = (string)($m['grup_laporan'] ?? 'LAINNYA');
    $item = [
        'id' => $mid,
        'kode' => (string)($m['kode'] ?? ''),
        'nama_item' => (string)($m['nama_item'] ?? ''),
        'grup_laporan' => $g,
        'satuan_pakai' => (string)($m['satuan_pakai'] ?? 'Kg'),
    ];
    $masters[] = $item;
    $masterById[$mid] = $item;
    if (!isset($groups[$g])) $groups[$g] = [];
    $groups[$g][] = $mid;
}
sqlsrv_free_stmt($masterStmt);

$dateRows = [];
$dataMap = [];
$sumPakaiByMaster = [];
$sumBiayaByMaster = [];
$sumHargaByMaster = [];
$countHargaByMaster = [];

$dataSql = "SELECT CAST(h.tanggal AS DATE) AS tanggal, h.master_id, h.pakai_kg, h.harga_rp, h.biaya_rp
            FROM dbo.kimia_boiler_harian h
            INNER JOIN dbo.kimia_boiler_master m ON m.id = h.master_id
            WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(h.tanggal AS DATE) ASC, $groupOrderExpr, h.master_id ASC";
$stmt = sqlsrv_query($conn, $dataSql, [$start, $end]);
if ($stmt === false) { echo '<b>SQL error</b>: ' . htmlspecialchars(print_r(sqlsrv_errors(), true)); exit; }
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dateObj = $r['tanggal'] ?? null;
    if ($dateObj instanceof DateTime) $dateKey = $dateObj->format('Y-m-d');
    else $dateKey = date('Y-m-d', strtotime((string)$dateObj));

    $mid = (int)($r['master_id'] ?? 0);
    if ($mid <= 0) continue;

    $pakai = is_numeric($r['pakai_kg']) ? (float)$r['pakai_kg'] : 0.0;
    $harga = is_numeric($r['harga_rp']) ? (float)$r['harga_rp'] : 0.0;
    $biaya = is_numeric($r['biaya_rp']) ? (float)$r['biaya_rp'] : ($pakai * $harga);

    if (!isset($dateRows[$dateKey])) $dateRows[$dateKey] = $dateKey;
    if (!isset($dataMap[$dateKey])) $dataMap[$dateKey] = [];
    $dataMap[$dateKey][$mid] = ['pakai' => $pakai, 'harga' => $harga, 'biaya' => $biaya];

    if (!isset($sumPakaiByMaster[$mid])) $sumPakaiByMaster[$mid] = 0.0;
    if (!isset($sumBiayaByMaster[$mid])) $sumBiayaByMaster[$mid] = 0.0;
    if (!isset($sumHargaByMaster[$mid])) $sumHargaByMaster[$mid] = 0.0;
    if (!isset($countHargaByMaster[$mid])) $countHargaByMaster[$mid] = 0;

    $sumPakaiByMaster[$mid] += $pakai;
    $sumBiayaByMaster[$mid] += $biaya;
    $sumHargaByMaster[$mid] += $harga;
    $countHargaByMaster[$mid]++;
}
if ($stmt) sqlsrv_free_stmt($stmt);

$dates = array_values($dateRows);
sort($dates);
$rowCount = count($dates);

$defaultHargaByMaster = [];
foreach ($masters as $m) {
    $mid = $m['id'];
    $defaultHargaByMaster[$mid] = ($countHargaByMaster[$mid] ?? 0) > 0
        ? ($sumHargaByMaster[$mid] / $countHargaByMaster[$mid])
        : 0.0;
}

$sumTotalBiaya = 0.0;
$totalBiayaPerDate = [];
foreach ($dates as $dateKey) {
    $t = 0.0;
    foreach ($masters as $m) {
        $mid = $m['id'];
        $t += $dataMap[$dateKey][$mid]['biaya'] ?? 0.0;
    }
    $totalBiayaPerDate[$dateKey] = $t;
    $sumTotalBiaya += $t;
}
$avgTotalBiaya = $rowCount > 0 ? ($sumTotalBiaya / $rowCount) : 0.0;

$fmt = function ($val, $dec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', ',');
};
$day = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };
$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
$totalColumns = (count($masters) * 3) + 2;

echo "<html><head><meta charset='UTF-8'><style>
body{font-family:Arial,sans-serif}
.ipal{border-collapse:collapse;font-size:11px}
.ipal th,.ipal td{border:1px solid #000;padding:3px 4px;text-align:center;vertical-align:middle;white-space:nowrap}
.title{background:#e6e6e6;font-weight:bold;font-size:18px}
.subtitle{background:#e6e6e6;font-weight:bold;font-size:16px}
.month{background:#f3f3f3;font-weight:bold}
.group{background:#d9d9d9;font-weight:bold}
.item{background:#fff200;font-weight:bold}
.sub{background:#fff200;font-weight:bold}
.data{background:#d8d5b8}
.datecol{background:#dfe7d3;font-weight:bold}
.sum{background:#e2e2d1;font-weight:bold}
.tot{background:#ffc000;font-weight:bold}
.num2{mso-number-format:'0.00'}
</style></head><body><table class='ipal'>";

echo "<tr><th class='title' colspan='{$totalColumns}'>PEMAKAIAN OBAT DI AREA BOILER</th></tr>";
echo "<tr><th class='subtitle' colspan='{$totalColumns}'>KIMIA BOILER</th></tr>";
echo "<tr><th class='month' colspan='2'>Bulan : " . htmlspecialchars($monthLabel) . "</th><th class='month' colspan='" . (count($masters)*3) . "'></th></tr>";

echo "<tr>";
echo "<th class='datecol' rowspan='3'>TANGGAL</th>";
foreach ($groups as $gKey => $itemIds) {
    $label = $groupLabels[$gKey] ?? $gKey;
    echo "<th class='group' colspan='" . (count($itemIds)*3) . "'>" . htmlspecialchars($label) . "</th>";
}
echo "<th class='tot' rowspan='3'>TOTAL BIAYA</th>";
echo "</tr>";

echo "<tr>";
foreach ($groups as $itemIds) {
    foreach ($itemIds as $mid) {
        echo "<th class='item' colspan='3'>" . htmlspecialchars($masterById[$mid]['nama_item'] ?? '-') . "</th>";
    }
}
echo "</tr>";

echo "<tr>";
foreach ($groups as $itemIds) {
    foreach ($itemIds as $mid) {
        $satuan = $masterById[$mid]['satuan_pakai'] ?? 'Kg';
        echo "<th class='sub'>PAKAI (" . htmlspecialchars($satuan) . ")</th><th class='sub'>HARGA (Rp)</th><th class='sub'>BIAYA (Rp)</th>";
    }
}
echo "</tr>";

if (empty($dates)) {
    echo "<tr><td colspan='{$totalColumns}'>Tidak ada data pada rentang tanggal ini.</td></tr>";
} else {
    foreach ($dates as $dateKey) {
        echo "<tr>";
        echo "<td class='datecol'>" . htmlspecialchars($day($dateKey)) . "</td>";
        foreach ($groups as $itemIds) {
            foreach ($itemIds as $mid) {
                $cell = $dataMap[$dateKey][$mid] ?? null;
                $pakai = $cell['pakai'] ?? 0.0;
                $harga = $cell['harga'] ?? ($defaultHargaByMaster[$mid] ?? 0.0);
                $biaya = $cell['biaya'] ?? ($pakai * $harga);
                echo "<td class='data num2'>" . htmlspecialchars($fmt($pakai,2)) . "</td>";
                echo "<td class='data num2'>" . htmlspecialchars($fmt($harga,2)) . "</td>";
                echo "<td class='data num2'>" . htmlspecialchars($fmt($biaya,2)) . "</td>";
            }
        }
        echo "<td class='tot num2'>" . htmlspecialchars($fmt($totalBiayaPerDate[$dateKey] ?? 0, 2)) . "</td>";
        echo "</tr>";
    }

    echo "<tr class='sum'>";
    echo "<td>TOTAL</td>";
    foreach ($groups as $itemIds) {
        foreach ($itemIds as $mid) {
            echo "<td class='num2'>" . htmlspecialchars($fmt($sumPakaiByMaster[$mid] ?? 0, 2)) . "</td>";
            echo "<td class='num2'>" . htmlspecialchars($fmt($defaultHargaByMaster[$mid] ?? 0, 2)) . "</td>";
            echo "<td class='num2'>" . htmlspecialchars($fmt($sumBiayaByMaster[$mid] ?? 0, 2)) . "</td>";
        }
    }
    echo "<td class='tot num2'>" . htmlspecialchars($fmt($sumTotalBiaya, 2)) . "</td>";
    echo "</tr>";

    echo "<tr class='sum'>";
    echo "<td>RATA-RATA</td>";
    foreach ($groups as $itemIds) {
        foreach ($itemIds as $mid) {
            $avgPakai = $rowCount > 0 ? (($sumPakaiByMaster[$mid] ?? 0) / $rowCount) : 0;
            $avgBiaya = $rowCount > 0 ? (($sumBiayaByMaster[$mid] ?? 0) / $rowCount) : 0;
            echo "<td class='num2'>" . htmlspecialchars($fmt($avgPakai, 2)) . "</td>";
            echo "<td class='sub'>Include</td>";
            echo "<td class='num2'>" . htmlspecialchars($fmt($avgBiaya, 2)) . "</td>";
        }
    }
    echo "<td class='tot num2'>" . htmlspecialchars($fmt($avgTotalBiaya, 2)) . "</td>";
    echo "</tr>";
}

echo "</table></body></html>";


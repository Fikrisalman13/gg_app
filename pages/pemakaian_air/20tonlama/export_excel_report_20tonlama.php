<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal, *
        FROM dbo.air_steam_20tonlama_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
$rows = [];
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateObj = $r['tanggal'] ?? null;
        $r['tanggal'] = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}

$airKeys = ['air_awal_m3','air_akhir_m3','air_total_pemakaian_m3','air_rata_rata_per_jam_m3'];
$steamKeys = ['steam_awal_ton','steam_akhir_ton','steam_total_pemakaian_ton','steam_rata_rata_per_jam_ton'];
$totAir = array_fill_keys($airKeys, 0);
$totSteam = array_fill_keys($steamKeys, 0);
foreach ($rows as $r) {
    foreach ($airKeys as $k) $totAir[$k] += (float)($r[$k] ?? 0);
    foreach ($steamKeys as $k) $totSteam[$k] += (float)($r[$k] ?? 0);
}
$rowCount = count($rows);
$avgAir = []; foreach ($airKeys as $k) $avgAir[$k] = $rowCount > 0 ? ($totAir[$k] / $rowCount) : 0;
$avgSteam = []; foreach ($steamKeys as $k) $avgSteam[$k] = $rowCount > 0 ? ($totSteam[$k] / $rowCount) : 0;

$fmtNum = function ($val, $dec = 2) { return number_format((float)$val, $dec, '.', ','); };
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$filename = 'report_20tonlama_' . date('Ymd_His') . '.xls';
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    table { border-collapse: collapse; font-size: 11px; }
    th, td { border: 1px solid #000; padding: 3px 5px; text-align: center; white-space: nowrap; }
    .head { background: #b7cae2; font-weight: 700; }
    .sub { background: #f3e2e2; font-weight: 700; }
    .date { background: #e6edd9; font-weight: 700; }
    .data { background: #c4d5e9; }
    .sum td { background: #ece9d6 !important; font-weight: 700; }
  </style>
</head>
<body>
<table>
  <thead>
    <tr>
      <th class="head" colspan="6">METER AIR BOILER STEAM 20 TON - <?= htmlspecialchars($monthLabel) ?></th>
      <th style="border:none;background:#fff"></th>
      <th class="head" colspan="6">METER STEAM BOILER STEAM 20 TON - <?= htmlspecialchars($monthLabel) ?></th>
    </tr>
    <tr>
      <th class="sub">Tanggal</th><th class="sub">Awal</th><th class="sub">Akhir</th><th class="sub">Total</th><th class="sub">Rata/Jam</th><th class="sub">KET</th>
      <th style="border:none;background:#fff"></th>
      <th class="sub">Tanggal</th><th class="sub">Awal</th><th class="sub">Akhir</th><th class="sub">Total</th><th class="sub">Rata/Jam</th><th class="sub">KET</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="13">Tidak ada data pada rentang tanggal ini.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td class="date"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['air_awal_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['air_akhir_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['air_total_pemakaian_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['air_rata_rata_per_jam_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars((string)($r['air_ket'] ?? '')) ?></td>
        <td style="border:none;background:#fff"></td>
        <td class="date"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['steam_awal_ton'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['steam_akhir_ton'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['steam_total_pemakaian_ton'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['steam_rata_rata_per_jam_ton'])) ?></td>
        <td class="data"><?= htmlspecialchars((string)($r['steam_ket'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
      <tr class="sum">
        <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totAir['air_awal_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($totAir['air_akhir_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($totAir['air_total_pemakaian_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($totAir['air_rata_rata_per_jam_m3'])) ?></td><td></td>
        <td style="border:none;background:#fff"></td>
        <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totSteam['steam_awal_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($totSteam['steam_akhir_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($totSteam['steam_total_pemakaian_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($totSteam['steam_rata_rata_per_jam_ton'])) ?></td><td></td>
      </tr>
      <tr class="sum">
        <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgAir['air_awal_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($avgAir['air_akhir_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($avgAir['air_total_pemakaian_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($avgAir['air_rata_rata_per_jam_m3'])) ?></td><td></td>
        <td style="border:none;background:#fff"></td>
        <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_awal_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_akhir_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_total_pemakaian_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_rata_rata_per_jam_ton'])) ?></td><td></td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>
</body>
</html>

<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal, *
        FROM dbo.perblerange1_harian
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

$keys = [
    'meter_awal_m3','meter_akhir_m3','total_pemakaian_m3',
    'meter_awal_debit_m3','meter_akhir_debit_m3','total_pemakaian_debit_m3',
    'operasional_mc_pbr1_jam','pemakaian_rata_per_jam_m3',
    'jumlah_debit_m3','operasional_mc_pbr1_debit_jam','pemakaian_rata_per_jam_debit_m3'
];
$tot = array_fill_keys($keys, 0);
foreach ($rows as $r) { foreach ($keys as $k) $tot[$k] += (float)($r[$k] ?? 0); }
$rowCount = count($rows);
$avg = []; foreach ($keys as $k) $avg[$k] = $rowCount > 0 ? ($tot[$k] / $rowCount) : 0;

$fmtNum = function ($val, $dec = 2) { return number_format((float)$val, $dec, '.', ','); };
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };
$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$filename = 'report_perblerange1_' . date('Ymd_His') . '.xls';
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
    .sub { background: #f1dddd; font-weight: 700; }
    .date { background: #e6edd9; font-weight: 700; }
    .data { background: #c4d5e9; }
    .sum td { background: #ece9d6 !important; font-weight: 700; }
  </style>
</head>
<body>
<table>
  <thead>
    <tr><th class="head" colspan="13">METER PERBLE RANGE 1 - <?= htmlspecialchars($monthLabel) ?></th></tr>
    <tr>
      <th class="sub">Tanggal</th>
      <th class="sub">Awal Meter</th>
      <th class="sub">Akhir Meter</th>
      <th class="sub">Total Meter</th>
      <th class="sub">Awal Debit</th>
      <th class="sub">Akhir Debit</th>
      <th class="sub">Total Debit Meter</th>
      <th class="sub">Operasional</th>
      <th class="sub">Rata/Jam</th>
      <th class="sub">Jumlah Debit</th>
      <th class="sub">Operasional Debit</th>
      <th class="sub">Rata Debit/Jam</th>
      <th class="sub">KET MC</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="13">Tidak ada data pada rentang tanggal ini.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td class="date"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['meter_awal_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['meter_akhir_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['total_pemakaian_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['meter_awal_debit_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['meter_akhir_debit_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['total_pemakaian_debit_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['operasional_mc_pbr1_jam'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['pemakaian_rata_per_jam_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['jumlah_debit_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['operasional_mc_pbr1_debit_jam'])) ?></td>
        <td class="data"><?= htmlspecialchars($fmtNum($r['pemakaian_rata_per_jam_debit_m3'])) ?></td>
        <td class="data"><?= htmlspecialchars((string)($r['ket_mc_yang_jalan'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
      <tr class="sum">
        <td>TOTAL</td>
        <?php foreach ($keys as $k): ?><td><?= htmlspecialchars($fmtNum($tot[$k])) ?></td><?php endforeach; ?>
        <td></td>
      </tr>
      <tr class="sum">
        <td>RATA-RATA</td>
        <?php foreach ($keys as $k): ?><td><?= htmlspecialchars($fmtNum($avg[$k])) ?></td><?php endforeach; ?>
        <td></td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>
</body>
</html>

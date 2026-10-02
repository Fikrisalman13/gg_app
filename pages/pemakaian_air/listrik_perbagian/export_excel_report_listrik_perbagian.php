<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT CAST(tanggal AS DATE) AS tanggal, *
        FROM dbo.listrik_perbagian_harian
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
    'kwh_pln','kwh_utility_amp_acb','kwh_utility_kwh_hari','df_amp_acb','df_kwh_hari',
    'weaving1_amp_acb','weaving1_kwh_hari','weaving2_amp_acb','weaving2_kwh_hari',
    'jumlah_kwh_hari','jumlah_ampere','kwh_per_jam','efisiensi_persen'
];
$tot = array_fill_keys($keys, 0);
foreach ($rows as $r) { foreach ($keys as $k) $tot[$k] += (float)($r[$k] ?? 0); }
$rowCount = count($rows);
$avg = [];
foreach ($keys as $k) $avg[$k] = $rowCount > 0 ? ($tot[$k] / $rowCount) : 0;

$fmtNum = function ($val, $dec = 2) { return number_format((float)$val, $dec, '.', ','); };
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$filename = 'report_listrik_perbagian_' . date('Ymd_His') . '.xls';
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
    .title { background: #b7c9de; font-weight: 700; font-size: 18px; }
    .datecol { background: #e3e3e3; font-weight: 700; }
    .kwh { background: #e7b07c; font-weight: 700; }
    .utility { background: #f0f0f0; font-weight: 700; }
    .df { background: #c9d6e6; font-weight: 700; }
    .w1 { background: #ead9d9; font-weight: 700; }
    .w2 { background: #ead9d9; font-weight: 700; }
    .jml { background: #f0dfdf; font-weight: 700; }
    .sub { background: #fafafa; font-weight: 700; }
    .data-blue { background: #c2d4e7; }
    .data-yellow { background: #ffff00; }
    .sum td { background: #efd2b6 !important; font-weight: 700; }
  </style>
</head>
<body>
<table>
  <thead>
    <tr><th class="title" colspan="14">PEMAKAIAN KWH METER PLN BULAN <?= htmlspecialchars($monthLabel) ?></th></tr>
    <tr>
      <th class="datecol" rowspan="2">TGL</th>
      <th class="kwh" rowspan="2">KWH PLN<br>(KWH)</th>
      <th class="utility" colspan="2">KWH UTILITY</th>
      <th class="df" colspan="2">DF</th>
      <th class="w1" colspan="2">WEAVING 1</th>
      <th class="w2" colspan="2">WEAVING 2</th>
      <th class="jml" colspan="1">JUMLAH</th>
      <th class="jml" colspan="1">JUMLAH AMPERE</th>
      <th class="jml" colspan="1">KWH/JAM</th>
      <th class="jml" colspan="1">EFESIENSI</th>
    </tr>
    <tr>
      <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
      <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
      <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
      <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
      <th class="sub">Kwh/hari</th>
      <th class="sub">AMPERE</th>
      <th class="sub">Kwh/JAM</th>
      <th class="sub">PERSEN</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="14">Tidak ada data pada rentang tanggal ini.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td class="datecol"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
        <td class="data-blue"><?= htmlspecialchars($fmtNum($r['kwh_pln'])) ?></td>
        <td class="data-blue"><?= htmlspecialchars($fmtNum($r['kwh_utility_amp_acb'])) ?></td>
        <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['kwh_utility_kwh_hari'])) ?></td>
        <td class="data-blue"><?= htmlspecialchars($fmtNum($r['df_amp_acb'])) ?></td>
        <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['df_kwh_hari'])) ?></td>
        <td class="data-blue"><?= htmlspecialchars($fmtNum($r['weaving1_amp_acb'])) ?></td>
        <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['weaving1_kwh_hari'])) ?></td>
        <td class="data-blue"><?= htmlspecialchars($fmtNum($r['weaving2_amp_acb'])) ?></td>
        <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['weaving2_kwh_hari'])) ?></td>
        <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['jumlah_kwh_hari'])) ?></td>
        <td class="data-blue"><?= htmlspecialchars($fmtNum($r['jumlah_ampere'])) ?></td>
        <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['kwh_per_jam'])) ?></td>
        <td class="data-blue"><?= htmlspecialchars($fmtNum($r['efisiensi_persen'])) ?></td>
      </tr>
    <?php endforeach; ?>
      <tr class="sum">
        <td>Total</td>
        <?php foreach ($keys as $k): ?><td><?= htmlspecialchars($fmtNum($tot[$k])) ?></td><?php endforeach; ?>
      </tr>
      <tr class="sum">
        <td>RATA-RATA</td>
        <?php foreach ($keys as $k): ?><td><?= htmlspecialchars($fmtNum($avg[$k])) ?></td><?php endforeach; ?>
      </tr>
    <?php endif; ?>
  </tbody>
</table>
</body>
</html>

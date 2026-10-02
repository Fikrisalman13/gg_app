<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 1239;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    exit('Anda tidak memiliki hak melihat data.');
}

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT id, tanggal, temp_umpan_c, dh_std_lt1, tds_umpan_ms,
               CASE WHEN tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_umpan_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_umpan_ppm,
               ph_boiler, temp_boiler_c, tds_boiler_ms,
               CASE WHEN tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_boiler_ms * 0.64, 2) AS DECIMAL(12,2)) END AS tds_boiler_ppm,
               blowdown_jumlah, keterangan,
               CASE WHEN tds_umpan_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_umpan_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_umpan_display_ms,
               CASE WHEN tds_boiler_ms IS NULL THEN NULL ELSE CAST(ROUND(tds_boiler_ms / 1000.0, 2) AS DECIMAL(12,2)) END AS tds_boiler_display_ms
        FROM dbo.air_boiler_alstom_harian
        WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
        ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);

$grouped = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateKey = '';
        if (($row['tanggal'] ?? null) instanceof DateTime) {
            $dateKey = $row['tanggal']->format('Y-m-d');
        } else {
            $dateKey = date('Y-m-d', strtotime((string)($row['tanggal'] ?? '')));
        }
        if (!isset($grouped[$dateKey])) $grouped[$dateKey] = [];
        $grouped[$dateKey][] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

$fmtDate = function ($val) {
    return $val ? date('d-M-y', strtotime($val)) : '';
};
$fmtNum = function ($val, $maxDec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    $s = number_format((float)$val, $maxDec, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
};

$filename = 'report_air_boiler_alstom_' . date('Ymd_His') . '.xls';
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
    th, td { border: 1px solid #000; padding: 3px 5px; text-align: center; vertical-align: middle; white-space: nowrap; }
    .head { background: #f2f2f2; font-weight: 700; }
    .text-cell { mso-number-format:"\@"; }
    .report-title { font-size: 16px; font-weight: 700; border: none !important; padding: 10px 0 8px; }
    .report-period { font-size: 11px; font-weight: 400; border: none !important; padding: 0 0 8px; }
  </style>
</head>
<body>
<table>
  <thead>
    <tr>
      <th class="report-title" colspan="13">MONITORING AIR BOILER - ALSTOM</th>
    </tr>
    <tr>
      <th class="report-period" colspan="13">Periode: <?= htmlspecialchars(date('d-m-Y', strtotime($start))) ?> s/d <?= htmlspecialchars(date('d-m-Y', strtotime($end))) ?></th>
    </tr>
    <tr>
      <th class="head" rowspan="3">Tanggal</th>
      <th class="head" colspan="4">AIR UMPAN</th>
      <th class="head" colspan="4">AIR BOILER</th>
      <th class="head" colspan="1">BLOWDOWN</th>
      <th class="head" rowspan="3">Keterangan</th>
      <th class="head" colspan="2">ms/cm (display alat)</th>
    </tr>
    <tr>
      <th class="head">Temp</th>
      <th class="head">DH</th>
      <th class="head" colspan="2">TDS</th>
      <th class="head" rowspan="2">pH</th>
      <th class="head">Temp</th>
      <th class="head" colspan="2">TDS</th>
      <th class="head">Jumlah blowdown</th>
      <th class="head">Air Umpan</th>
      <th class="head">Air Boiler</th>
    </tr>
    <tr>
      <th class="head">C</th>
      <th class="head">std &lt; 1</th>
      <th class="head">ms/cm</th>
      <th class="head">ppm</th>
      <th class="head">C</th>
      <th class="head">ms/cm</th>
      <th class="head">ppm</th>
      <th class="head"></th>
      <th class="head"></th>
      <th class="head"></th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($grouped)): ?>
      <tr><td colspan="13">Tidak ada data pada rentang tanggal ini.</td></tr>
    <?php else: ?>
      <?php foreach ($grouped as $dateKey => $rows): ?>
        <?php $rowspan = count($rows); ?>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <?php if ($i === 0): ?>
              <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($fmtDate($dateKey)) ?></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($fmtNum($r['temp_umpan_c'] ?? null)) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['dh_std_lt1'] ?? null)) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['tds_umpan_ms'] ?? null)) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['tds_umpan_ppm'] ?? null)) ?></td>
            <td class="text-cell"><?= htmlspecialchars((string)($r['ph_boiler'] ?? '')) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['temp_boiler_c'] ?? null)) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['tds_boiler_ms'] ?? null)) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['tds_boiler_ppm'] ?? null)) ?></td>
            <td><?= htmlspecialchars((string)($r['blowdown_jumlah'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($r['keterangan'] ?? '')) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['tds_umpan_display_ms'] ?? null)) ?></td>
            <td><?= htmlspecialchars($fmtNum($r['tds_boiler_display_ms'] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</body>
</html>

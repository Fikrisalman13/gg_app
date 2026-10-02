<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$menuId = 230;
requireView($conn, $menuId);

$start = trim($_POST['start_date'] ?? date('Y-m-01'));
$end = trim($_POST['end_date'] ?? date('Y-m-d'));

$sql = "SELECT CAST(h.periode AS DATE) AS tanggal,
               CAST(d.jam AS INT) AS jam,
               ISNULL(SUM(CASE WHEN d.jenis='AIR' THEN d.nilai_ton ELSE 0 END),0) AS jumlah_air_ton,
               ISNULL(SUM(CASE WHEN d.jenis='STEAM' THEN d.nilai_ton ELSE 0 END),0) AS jumlah_steam_ton
        FROM dbo.pmlonchuan_hdr h
        INNER JOIN dbo.pmlonchuan_dtl d ON d.hdr_id = h.id
        WHERE CAST(h.periode AS DATE) BETWEEN ? AND ?
        GROUP BY CAST(h.periode AS DATE), CAST(d.jam AS INT)
        ORDER BY CAST(h.periode AS DATE) ASC,
                 CASE
                   WHEN CAST(d.jam AS INT) >= 9 THEN CAST(d.jam AS INT) - 9
                   ELSE CAST(d.jam AS INT) + 15
                 END ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
$rows = [];
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dateObj = $r['tanggal'] ?? null;
        $r['tanggal'] = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
        $r['jam'] = isset($r['jam']) ? (int)$r['jam'] : null;
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}

$groupedRows = [];
foreach ($rows as $r) {
    $dateKey = (string)($r['tanggal'] ?? '');
    if ($dateKey === '') continue;
    if (!isset($groupedRows[$dateKey])) {
        $groupedRows[$dateKey] = [
            'tanggal' => $dateKey,
            'rows' => [],
            'tot_air' => 0,
            'tot_steam' => 0,
            'avg_air' => 0,
            'avg_steam' => 0,
        ];
    }
    $air = (float)($r['jumlah_air_ton'] ?? 0);
    $steam = (float)($r['jumlah_steam_ton'] ?? 0);
    $groupedRows[$dateKey]['rows'][] = [
        'jam' => $r['jam'] ?? null,
        'jumlah_air_ton' => $air,
        'jumlah_steam_ton' => $steam,
    ];
    $groupedRows[$dateKey]['tot_air'] += $air;
    $groupedRows[$dateKey]['tot_steam'] += $steam;
}
foreach ($groupedRows as &$g) {
    $dayCount = count($g['rows']);
    $g['avg_air'] = $dayCount > 0 ? ($g['tot_air'] / $dayCount) : 0;
    $g['avg_steam'] = $dayCount > 0 ? ($g['tot_steam'] / $dayCount) : 0;
}
unset($g);

$fmtNum = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '';
    $str = number_format((float)$val, $dec, '.', ',');
    return rtrim(rtrim($str, '0'), '.');
};
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };
$fmtHour = function ($jam) {
    if ($jam === null || $jam === '' || !is_numeric($jam)) return '';
    $j = (int)$jam;
    if ($j < 0 || $j > 23) return '';
    return str_pad((string)$j, 2, '0', STR_PAD_LEFT) . ':00';
};

$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$filename = 'report_20tlonchuan_' . date('Ymd_His') . '.xls';
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
    th, td { border: 2px solid #111; padding: 2px 6px; text-align: center; vertical-align: middle; white-space: nowrap; }
    .head { background: #b7cae2; font-weight: 700; }
    .sub { background: #f3e2e2; font-weight: 700; }
    .date { background: #dbe2cf; font-weight: 700; }
    .data { background: #b8c7d9; }
    .sum td { background: #ece9d6 !important; font-weight: 700; }
    .sum-label { text-align: left; padding-left: 8px; }
  </style>
</head>
<body>
<table>
  <thead>
    <tr>
      <th class="head" colspan="4">BERDASARKAN LAJU SESAAT BOILER LONCHUAN - <?= htmlspecialchars($monthLabel) ?></th>
    </tr>
    <tr>
      <th class="sub">Tanggal</th>
      <th class="sub">Jam</th>
      <th class="sub">Jumlah Air (ton)</th>
      <th class="sub">Jumlah Steam (ton)</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($groupedRows)): ?>
      <tr><td colspan="4">Tidak ada data pada rentang tanggal ini.</td></tr>
    <?php else: foreach ($groupedRows as $day): ?>
      <?php $dayRows = $day['rows']; $dayRowCount = count($dayRows); ?>
      <?php foreach ($dayRows as $idx => $r): ?>
        <tr>
          <?php if ($idx === 0): ?>
            <td class="date" rowspan="<?= (int)$dayRowCount ?>"><?= htmlspecialchars($fmtDay($day['tanggal'])) ?></td>
          <?php endif; ?>
          <td class="date"><?= htmlspecialchars($fmtHour($r['jam'] ?? null)) ?></td>
          <td class="data"><?= htmlspecialchars($fmtNum($r['jumlah_air_ton'])) ?></td>
          <td class="data"><?= htmlspecialchars($fmtNum($r['jumlah_steam_ton'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr class="sum">
        <td class="sum-label">TOTAL/HARI</td>
        <td></td>
        <td><?= htmlspecialchars($fmtNum($day['tot_air'])) ?></td>
        <td><?= htmlspecialchars($fmtNum($day['tot_steam'])) ?></td>
      </tr>
      <tr class="sum">
        <td class="sum-label">RATA-RATA/HARI</td>
        <td></td>
        <td><?= htmlspecialchars($fmtNum($day['avg_air'])) ?></td>
        <td><?= htmlspecialchars($fmtNum($day['avg_steam'])) ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
</body>
</html>

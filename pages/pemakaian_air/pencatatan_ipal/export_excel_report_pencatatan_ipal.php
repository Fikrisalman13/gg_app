<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
$shift = trim($_GET['shift'] ?? '');

$where = "WHERE CAST(x.tanggal AS DATE) BETWEEN ? AND ?";
$params = [$startDate, $endDate];
if ($shift !== '') { $where .= " AND x.shift_kode = ?"; $params[] = $shift; }

$sql = "SELECT x.tanggal, x.shift_kode,
               x.sv30_aerasi1_pct, x.sv30_aerasi2_pct, x.sv30_aerasi3_pct, x.sv30_aerasi4_pct,
               x.ph_equal, x.ph_akhir,
               x.dewatering_bawah_per_day, x.dewatering_atas_sinci1_per_day_ton, x.sinci2_per_day_ton
        FROM dbo.pencatatan_ipal_harian x
        $where
        ORDER BY CAST(x.tanggal AS DATE) ASC, CASE x.shift_kode WHEN 'P' THEN 1 WHEN 'S' THEN 2 WHEN 'M' THEN 3 ELSE 4 END ASC";
$stmt = sqlsrv_query($conn, $sql, $params);
$rows = [];
if ($stmt) { while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $r; sqlsrv_free_stmt($stmt); }

$fmtNum = function($v){ return number_format((float)$v,2,'.',','); };
$fmtInt = function($v){ return number_format((float)$v,0,'.',','); };
$fmtNumOrDash = function($v) use ($fmtNum){
    if ($v === null || $v === '') return '-';
    return $fmtNum($v);
};
$fmtIntOrDash = function($v) use ($fmtInt){
    if ($v === null || $v === '') return '-';
    return $fmtInt($v);
};

$sum = ['sv1'=>0,'sv2'=>0,'sv3'=>0,'sv4'=>0,'ph1'=>0,'ph2'=>0,'db1'=>0,'db2'=>0,'db3'=>0];
foreach ($rows as $r) {
    $sum['sv1'] += (float)$r['sv30_aerasi1_pct']; $sum['sv2'] += (float)$r['sv30_aerasi2_pct']; $sum['sv3'] += (float)$r['sv30_aerasi3_pct']; $sum['sv4'] += (float)$r['sv30_aerasi4_pct'];
    $sum['ph1'] += (float)$r['ph_equal']; $sum['ph2'] += (float)$r['ph_akhir'];
    $sum['db1'] += (float)$r['dewatering_bawah_per_day']; $sum['db2'] += (float)$r['dewatering_atas_sinci1_per_day_ton']; $sum['db3'] += (float)$r['sinci2_per_day_ton'];
}
$count = count($rows);

$grouped = [];
foreach ($rows as $r) {
    $dateKey = $r['tanggal'] instanceof DateTime ? $r['tanggal']->format('Y-m-d') : date('Y-m-d', strtotime((string)$r['tanggal']));
    if (!isset($grouped[$dateKey])) $grouped[$dateKey] = [];
    $grouped[$dateKey][] = $r;
}
$shiftOrder = ['P' => 1, 'S' => 2, 'M' => 3];
foreach ($grouped as $dateKey => $items) {
    usort($items, function($a, $b) use ($shiftOrder) {
        $sa = strtoupper((string)($a['shift_kode'] ?? ''));
        $sb = strtoupper((string)($b['shift_kode'] ?? ''));
        return ($shiftOrder[$sa] ?? 9) <=> ($shiftOrder[$sb] ?? 9);
    });
    $grouped[$dateKey] = $items;
}

$monthMap = [
    'January' => 'JANUARI',
    'February' => 'FEBRUARI',
    'March' => 'MARET',
    'April' => 'APRIL',
    'May' => 'MEI',
    'June' => 'JUNI',
    'July' => 'JULI',
    'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER',
    'October' => 'OKTOBER',
    'November' => 'NOVEMBER',
    'December' => 'DESEMBER',
];
$monthEn = date('F', strtotime($startDate));
$bulanLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($startDate));

$filename = "report_pencatatan_ipal_{$startDate}_sd_{$endDate}.xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

echo "<html><head><meta charset='UTF-8'><style>
table.ipal{border-collapse:collapse;font-family:Calibri,Arial,sans-serif;font-size:13px;min-width:1220px}
table.ipal th,table.ipal td{border:1px solid #1f1f1f;padding:3px 6px;text-align:center;vertical-align:middle}
.bulan-label{background:#ffffff;font-weight:700;font-size:28px;text-align:left;padding-left:10px}
.bulan-value{font-weight:700;font-size:28px;text-align:left;padding-left:10px;text-decoration:underline}
.ipal-title{background:#c7d2b0;font-size:22px;font-weight:700}
.hdr-main{background:#d9d5c2;font-weight:700}
.hdr-unit{background:#efe8dc;font-weight:700}
.col-tgl,.col-shift{background:#e4ead9;font-weight:700}
.col-sv{background:#f2e4d8}
.col-ph{background:#a8bad0}
.col-green{background:#93cf52}
.row-total{font-weight:700}
.row-rata{font-weight:700}
</style></head><body>";
?>
<table class="ipal">
  <tr>
    <th class="bulan-label">Bulan:</th>
    <th class="bulan-value" colspan="10"><?= htmlspecialchars($bulanLabel) ?></th>
  </tr>
  <tr>
    <th class="ipal-title" colspan="11">Pencatatan SV30, PH, Sludge IPAL</th>
  </tr>
  <tr>
    <th class="hdr-main" rowspan="3">TGL</th>
    <th class="hdr-main" rowspan="3">SHIFT</th>
    <th class="hdr-main">Rata-rata</th>
    <th class="hdr-main">Rata-rata</th>
    <th class="hdr-main">Rata-rata</th>
    <th class="hdr-main">Rata-rata</th>
    <th class="hdr-main">Rata-rata</th>
    <th class="hdr-main">Rata-rata</th>
    <th class="hdr-main">DEWATERING BAWAH</th>
    <th class="hdr-main">DEWATERING ATAS DAN SINCI 1</th>
    <th class="hdr-main">SINCI 2</th>
  </tr>
  <tr>
    <th class="hdr-unit">(%)</th>
    <th class="hdr-unit">(%)</th>
    <th class="hdr-unit">(%)</th>
    <th class="hdr-unit">(%)</th>
    <th class="hdr-unit">Equal</th>
    <th class="hdr-unit">Akhir</th>
    <th class="hdr-unit">Per Day</th>
    <th class="hdr-unit">Per Day/Ton</th>
    <th class="hdr-unit">Per Day/Ton</th>
  </tr>
  <tr>
    <th class="hdr-unit">AERASI 1</th>
    <th class="hdr-unit">AERASI 2</th>
    <th class="hdr-unit">AERASI 3</th>
    <th class="hdr-unit">AERASI 4</th>
    <th class="hdr-unit">-</th>
    <th class="hdr-unit">-</th>
    <th class="hdr-unit">&nbsp;</th>
    <th class="hdr-unit">&nbsp;</th>
    <th class="hdr-unit">&nbsp;</th>
  </tr>
  <?php if (empty($grouped)): ?>
    <tr><td colspan="11">Tidak ada data.</td></tr>
  <?php else: ?>
    <?php foreach ($grouped as $dateYmd => $items): ?>
      <?php $rowspan = count($items); $dayNum = (int)date('j', strtotime($dateYmd)); $i = 0; ?>
      <?php foreach ($items as $r): ?>
        <tr>
          <?php if ($i === 0): ?>
            <td class="col-tgl" rowspan="<?= (int)$rowspan ?>"><?= htmlspecialchars((string)$dayNum) ?></td>
          <?php endif; ?>
          <td class="col-shift"><?= htmlspecialchars((string)$r['shift_kode']) ?></td>
          <td class="col-sv"><?= htmlspecialchars($fmtIntOrDash($r['sv30_aerasi1_pct'])) ?></td>
          <td class="col-sv"><?= htmlspecialchars($fmtIntOrDash($r['sv30_aerasi2_pct'])) ?></td>
          <td class="col-sv"><?= htmlspecialchars($fmtIntOrDash($r['sv30_aerasi3_pct'])) ?></td>
          <td class="col-sv"><?= htmlspecialchars($fmtIntOrDash($r['sv30_aerasi4_pct'])) ?></td>
          <td class="col-ph"><?= htmlspecialchars($fmtNumOrDash($r['ph_equal'])) ?></td>
          <td class="col-ph"><?= htmlspecialchars($fmtNumOrDash($r['ph_akhir'])) ?></td>
          <td class="col-green"><?= htmlspecialchars($fmtIntOrDash($r['dewatering_bawah_per_day'])) ?></td>
          <td class="col-green"><?= htmlspecialchars($fmtIntOrDash($r['dewatering_atas_sinci1_per_day_ton'])) ?></td>
          <td class="col-green"><?= htmlspecialchars($fmtIntOrDash($r['sinci2_per_day_ton'])) ?></td>
        </tr>
        <?php $i++; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($count > 0): ?>
  <tr class="row-total">
    <td colspan="2" style="background:#fff200;font-weight:700">TOTAL</td>
    <td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv1'])) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv2'])) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv3'])) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv4'])) ?></td>
    <td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['ph1'])) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['ph2'])) ?></td>
    <td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['db1'])) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['db2'])) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['db3'])) ?></td>
  </tr>
  <tr class="row-rata">
    <td colspan="2" style="background:#fff200;font-weight:700">RATA-RATA</td>
    <td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv1']/$count)) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv2']/$count)) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv3']/$count)) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['sv4']/$count)) ?></td>
    <td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['ph1']/$count)) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['ph2']/$count)) ?></td>
    <td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['db1']/$count)) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['db2']/$count)) ?></td><td style="background:#fff200;font-weight:700"><?= htmlspecialchars($fmtNum($sum['db3']/$count)) ?></td>
  </tr>
  <?php endif; ?>
</table>
<?php echo "</body></html>"; ?>

<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230;
requireView($conn, $menuId);
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startInput = $normalizeDate($_POST['start_date'] ?? '');
    $endInput = $normalizeDate($_POST['end_date'] ?? '');
    if ($startInput === '' || $endInput === '') $errorMsg = 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    elseif (strtotime($startInput) > strtotime($endInput)) $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    else { $start = $startInput; $end = $endInput; }
}

$rows = [];
if ($errorMsg === '') {
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
    if ($stmt === false) $errorMsg = 'Gagal mengambil data report: ' . print_r(sqlsrv_errors(), true);
    else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateObj = $r['tanggal'] ?? null;
            $dateKey = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
            $r['tanggal'] = $dateKey;
            $r['jam'] = isset($r['jam']) ? (int)$r['jam'] : null;
            $rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
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
}
foreach ($groupedRows as &$g) {
    $dayCount = count($g['rows']);
    if ($dayCount > 0) {
        $firstRow = $g['rows'][0];
        $lastRow = $g['rows'][$dayCount - 1];
        $g['tot_air'] = ((float)($lastRow['jumlah_air_ton'] ?? 0)) - ((float)($firstRow['jumlah_air_ton'] ?? 0));
        $g['tot_steam'] = ((float)($lastRow['jumlah_steam_ton'] ?? 0)) - ((float)($firstRow['jumlah_steam_ton'] ?? 0));
    } else {
        $g['tot_air'] = 0;
        $g['tot_steam'] = 0;
    }
    $g['avg_air'] = $dayCount > 0 ? ($g['tot_air'] / $dayCount) : 0;
    $g['avg_steam'] = $dayCount > 0 ? ($g['tot_steam'] / $dayCount) : 0;
}
unset($g);

$fmtNum = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '';
    $str = number_format((float)$val, $dec, '.', ',');
    return rtrim(rtrim($str, '0'), '.');
};
$fmtDay = function ($ymd) { return $ymd ? date('d/m/Y', strtotime($ymd)) : ''; };
$fmtHour = function ($jam) {
    if ($jam === null || $jam === '' || !is_numeric($jam)) return '';
    $j = (int)$jam;
    if ($j < 0 || $j > 23) return '';
    return str_pad((string)$j, 2, '0', STR_PAD_LEFT) . ':00';
};

$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
?>

<div class="content-wrapper">
  <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">REPORT AIR dan STEAM 20 TON LONCHUAN</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/20tlonchuan/20tlonchuan.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      </div>
    </div>
  </div>
</section>
  <section class="content"><div class="container-fluid">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center" style="min-height:42px;"><h3 class="card-title m-0"><i class="fas fa-filter"></i> Filter Rentang Tanggal</h3></div>
      <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3"><label for="start_date" class="form-label fw-bold">Dari Tanggal</label><input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required></div>
            <div class="col-sm-6 col-lg-3"><label for="end_date" class="form-label fw-bold">Sampai Tanggal</label><input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required></div>
            <div class="col-sm-6 col-lg-3 d-flex align-items-end" style="gap:8px;">
              <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
              <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
            </div>
          </div>
        </form>
        <div class="d-flex mt-3" style="gap:8px;">
          <form method="post" action="export_excel_report_20tlonchuan.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
          </form>
          <form method="post" action="export_pdf_report_20tlonchuan.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-danger btn-sm" title="Export PDF"><i class="fas fa-file-pdf"></i></button>
          </form>
        </div>
      </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
      <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
    <?php else: ?>
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Detail Report</h3>
        </div>
        <style>
          .tlc-wrap{overflow-x:auto;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
          .tlc-report{border-collapse:collapse;border-spacing:0;font-size:13px;min-width:700px;background:#fff}
          .tlc-report th,.tlc-report td{border:2px solid #111;text-align:center;vertical-align:middle;padding:2px 6px;white-space:nowrap;line-height:1.1}
          .tlc-report .head{background:#b7cae2;font-weight:700}
          .tlc-report .sub{background:#f3e2e2;font-weight:700}
          .tlc-report .date{background:#dbe2cf;font-weight:700}
          .tlc-report .date-day{font-size:16px}
          .tlc-report .data{background:#b8c7d9}
          .tlc-report .sum td{background:#ece9d6 !important;font-weight:700}
          .tlc-report .sum-label{text-align:left;padding-left:10px}
        </style>
        <div class="card-body p-2">
          <div class="tlc-wrap">
            <table class="table table-sm tlc-report">
              <thead>
                <tr>
                  <th class="head" colspan="4">BERDASARKAN LAJU SESAAT BOILER LONCHUAN - <?= htmlspecialchars($monthLabel) ?></th>
                </tr>
                <tr>
                  <th class="sub">Tanggal</th><th class="sub">Jam</th><th class="sub">Jumlah Air (ton)</th><th class="sub">Jumlah Steam (ton)</th>
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
                        <td class="date date-day" rowspan="<?= (int)$dayRowCount ?>"><?= htmlspecialchars($fmtDay($day['tanggal'])) ?></td>
                      <?php endif; ?>
                      <td class="date"><?= htmlspecialchars($fmtHour($r['jam'] ?? null)) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['jumlah_air_ton'])) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['jumlah_steam_ton'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="sum">
                    <td class="sum-label" colspan="2">TOTAL/HARI</td>
                    <td><?= htmlspecialchars($fmtNum($day['tot_air'])) ?></td>
                    <td><?= htmlspecialchars($fmtNum($day['tot_steam'])) ?></td>
                  </tr>
                  <tr class="sum">
                    <td class="sum-label" colspan="2">RATA-RATA/HARI</td>
                    <td><?= htmlspecialchars($fmtNum($day['avg_air'])) ?></td>
                    <td><?= htmlspecialchars($fmtNum($day['avg_steam'])) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div></section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include_once __DIR__ . '/ac_weaving_helper.php';
weaving_require($conn, 'CanView');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';

$normalizeDate = function ($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
};

$start = $normalizeDate($_POST['start_date'] ?? date('Y-m-d')) ?: date('Y-m-d');
$end = $normalizeDate($_POST['end_date'] ?? $start) ?: $start;
$errorMsg = '';
if (strtotime($start) > strtotime($end)) {
    $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
}

function fmt_date_report($value)
{
    if ($value instanceof DateTime) return $value->format('d/m/Y');
    if (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        return $ts ? date('d/m/Y', $ts) : $value;
    }
    return '';
}

function fmt_time_report($value)
{
    if ($value instanceof DateTime) $time = $value->format('H:i');
    elseif (is_string($value) && $value !== '') {
        $ts = strtotime($value);
        $time = $ts ? date('H:i', $ts) : substr($value, 0, 5);
    } else {
        return '';
    }
    return $time;
}

function fmt_num_report($value, $decimals = 2)
{
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return (string)$value;
    return number_format((float)$value, $decimals, '.', '');
}

function format_indo_date_range($start, $end)
{
    $bulanIndo = [
        1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
        'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'
    ];
    $tsStart = strtotime($start);
    $tsEnd = strtotime($end);
    if (!$tsStart) return $start;
    $d1 = (int)date('d', $tsStart);
    $m1 = $bulanIndo[(int)date('n', $tsStart)] ?? strtoupper(date('F', $tsStart));
    $y1 = date('Y', $tsStart);
    if ($start === $end || !$tsEnd) {
        return "$d1 $m1 $y1";
    }
    $d2 = (int)date('d', $tsEnd);
    $m2 = $bulanIndo[(int)date('n', $tsEnd)] ?? strtoupper(date('F', $tsEnd));
    $y2 = date('Y', $tsEnd);
    if ($y1 === $y2 && $m1 === $m2) {
        return "$d1 - $d2 $m1 $y1";
    }
    return "$d1 $m1 $y1 - $d2 $m2 $y2";
}

$reportRows = [
    'AC WEAVING 1' => [],
    'AC WEAVING 2' => [],
];
$reportKeterangan = [
    'AC WEAVING 1' => [],
    'AC WEAVING 2' => [],
];

if ($errorMsg === '') {
    $res = ac_weaving_load_report_data($conn, $start, $end);
    if ($res === false) {
        $errorMsg = 'Gagal mengambil data report: ' . print_r(sqlsrv_errors(), true);
    } else {
        [$reportRows, $reportKeterangan] = $res;
    }
}

$maxRows = max(count($reportRows['AC WEAVING 1']), count($reportRows['AC WEAVING 2']));
?>

<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-8">
          <h1 class="m-0">Report Pengecekan AC Weaving</h1>
        </div>
        <div class="col-sm-4 text-right">
          <a href="ac_weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title m-0"><i class="fas fa-filter mr-1"></i> Filter Rentang Tanggal</h3>
        </div>
        <div class="card-body">
          <style>
            .ac-report-filter-row {
              display: flex;
              flex-wrap: nowrap;
              align-items: flex-end;
              gap: 10px;
            }
            .ac-report-date-field {
              flex: 0 0 270px;
              max-width: 270px;
            }
            .ac-report-actions {
              display: flex;
              flex-wrap: nowrap;
              align-items: center;
              gap: 6px;
              margin-left: 8px;
              padding-top: 24px;
              white-space: nowrap;
            }
            .ac-report-actions .btn {
              min-width: auto;
            }
            @media (max-width: 767.98px) {
              .ac-report-filter-row {
                flex-wrap: wrap;
              }
              .ac-report-date-field {
                flex-basis: 100%;
                max-width: none;
              }
              .ac-report-actions {
                width: 100%;
                margin-left: 0;
                padding-top: 0;
                flex-wrap: wrap;
              }
              .ac-report-actions .btn {
                flex: 1 1 46%;
              }
            }
          </style>
          <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="ac-report-filter-row">
              <div class="ac-report-date-field">
                <label for="start_date">Dari Tanggal</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
              </div>
              <div class="ac-report-date-field">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
              </div>
              <div class="ac-report-actions">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                <button type="submit" class="btn btn-success btn-sm" formaction="export_excel_report_ac_weaving.php"><i class="fas fa-file-excel"></i> Export Excel</button>
                <button type="submit" class="btn btn-danger btn-sm" formaction="export_pdf_report_ac_weaving.php" formtarget="_blank"><i class="fas fa-file-pdf"></i> Export PDF</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
      <?php else: ?>
        <div class="card print-card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
            <h3 class="card-title"><i class="fas fa-list mr-1"></i> Detail Report</h3>
          </div>
          <div class="card-body p-2">
            <style>
              .ac-report-wrap{overflow:auto;background:#f8fafc;padding:12px;border:1px solid #cbd5e1;border-radius:4px}
              .ac-report-grid{display:flex;flex-direction:column;gap:24px}
              .ac-report-panel{background:#fff;border:1px solid #64748b;box-shadow:0 1px 3px rgba(15,23,42,.08)}
              .ac-report{width:100%;min-width:760px;table-layout:fixed;border-collapse:collapse;border-spacing:0;background:#fff;font-size:11px}
              .ac-report th,.ac-report td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px;white-space:nowrap;height:22px;overflow:hidden;text-overflow:ellipsis}
              .ac-report .sheet-title{background:#fff;font-size:12px;font-weight:800;text-align:center}
              .ac-report .date-title{background:#fff;font-weight:700;text-align:left;font-size:11px}
              .ac-report .date-title.text-center{text-align:center}
              .ac-report .machine-title{background:#fff;text-align:left;font-weight:800;font-size:11px}
              .ac-report .head{background:#9dc3e6;font-weight:700;color:#000;font-size:10px;line-height:1.15}
              .ac-report tbody tr:nth-child(even) td{background:#fbfdff}
              .ac-report .text-left{text-align:left}
              .ac-report .empty{color:#64748b;background:#f8fafc}
              @media print{
                .main-header,.main-sidebar,.content-header,.card:not(.print-card),.main-footer{display:none!important}
                .content-wrapper{margin-left:0!important}
                .print-card{border:0!important;box-shadow:none!important}
                .print-card .card-header{display:none!important}
                .ac-report-wrap{overflow:visible;border:0;background:#fff;padding:0}
                .ac-report-grid{display:flex;flex-direction:column;gap:18px}
                .ac-report-panel{border:0;box-shadow:none}
                .ac-report{font-size:9px;min-width:0}
              }
            </style>
            <div class="ac-report-wrap">
              <div class="ac-report-grid">
                <?php foreach (['AC WEAVING 1', 'AC WEAVING 2'] as $machine): ?>
                <?php $mRows = $reportRows[$machine] ?? []; ?>
                <div class="ac-report-panel">
                  <table class="ac-report">
                    <colgroup>
                      <col style="width:12%">
                      <col style="width:8%">
                      <col style="width:11%">
                      <col style="width:10%">
                      <col style="width:8%">
                      <col style="width:13%">
                      <col style="width:28%">
                      <col style="width:10%">
                    </colgroup>
                    <thead>
                      <tr><th class="sheet-title" colspan="8">PENGECEKAN TEMPERATUR AREA WEAVING</th></tr>
                      <tr>
                        <th class="date-title" colspan="6" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars(format_indo_date_range($start, $end)) ?></th>
                        <th class="date-title text-center" colspan="2" style="white-space: nowrap;">SUM-FM-THK-WV-005</th>
                      </tr>
                      <tr><th class="machine-title" colspan="8"><?= htmlspecialchars($machine) ?></th></tr>
                      <tr>
                        <th class="head">TANGGAL</th>
                        <th class="head">JAM</th>
                        <th class="head">pB1 Dew<br>Point</th>
                        <th class="head">HUMIDITY</th>
                        <th class="head">AMPER</th>
                        <th class="head">DEFFERENTIAL<br>BEST AIR</th>
                        <th class="head">PETUGAS</th>
                        <th class="head">SHIFT</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($mRows)): ?>
                        <tr><td class="empty" colspan="8">Tidak ada data.</td></tr>
                      <?php else: ?>
                        <?php foreach ($mRows as $row): ?>
                          <tr>
                            <td><?= htmlspecialchars($row['tanggal'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['jam'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['dew_point'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['humidity'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['amper'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['differential'] ?? '') ?></td>
                            <td class="text-left" title="<?= htmlspecialchars($row['petugas'] ?? '') ?>"><?= htmlspecialchars($row['petugas'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['shift'] ?? '') ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                  <div class="ac-report-keterangan" style="padding: 6px 8px; font-size: 11px; text-align: left; background: #fff; border-top: 1px solid #111;">
                    <strong>Keterangan :</strong> <?= htmlspecialchars(!empty($reportKeterangan[$machine]) ? implode('; ', $reportKeterangan[$machine]) : '-') ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

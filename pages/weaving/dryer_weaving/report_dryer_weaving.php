<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');
weaving_require($conn, 'CanView');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$dryerNoFilter = trim($_POST['dryer_no'] ?? '');
$ctNoFilter = trim($_POST['ct_no'] ?? '');
$noOptions = dryer_weaving_no_options();
$errorMsg = '';
if (strtotime($start) > strtotime($end)) $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';

$items = dryer_weaving_items();
$hours = dryer_weaving_hours();
$sheets = [];

if ($errorMsg === '' && dryer_weaving_table_exists($conn)) {
    $whereExtra = '';
    $params = [$start, $end];
    if ($dryerNoFilter !== '') {
        $whereExtra .= ' AND Dryer_No = ?';
        $params[] = $dryerNoFilter;
    }
    if ($ctNoFilter !== '') {
        $whereExtra .= ' AND Ct_No = ?';
        $params[] = $ctNoFilter;
    }

    $sql = "SELECT MIN(Id) AS Id, CAST(Tanggal AS DATE) AS Tanggal, Dryer_No, Ct_No,
                MAX(Petugas) AS Petugas, MAX([Shift]) AS ShiftName
            FROM dbo.dryer_weaving
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            $whereExtra
            GROUP BY CAST(Tanggal AS DATE), Dryer_No, Ct_No
            ORDER BY CAST(Tanggal AS DATE) ASC, Dryer_No ASC, Ct_No ASC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data report.';
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tanggal = dryer_weaving_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
            $row['TanggalKey'] = $tanggal;
            $row['Cells'] = dryer_weaving_get_sheet_cells($conn, $tanggal, $row['Dryer_No'], $row['Ct_No']);
            $row['Shifts'] = dryer_weaving_shifts_for_sheet($row['Cells']);
            $row['PetugasByHour'] = dryer_weaving_petugas_by_hour_for_sheet($row['Cells']);
            $row['Keterangan'] = dryer_weaving_keterangan_for_sheet_query($conn, $tanggal, $row['Dryer_No'], $row['Ct_No']);
            $sheets[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}
?>

<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-8"><h1 class="m-0">Report Dryer Weaving</h1></div>
        <div class="col-sm-4 text-right"><a href="dryer_weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div>
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
          <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="dryer-report-filter-row">
              <div class="dryer-report-date-field">
                <label for="start_date">Dari Tanggal</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
              </div>
              <div class="dryer-report-date-field">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
              </div>
              <div class="dryer-report-no-field">
                <label for="dryer_no">Dryer No</label>
                <select id="dryer_no" name="dryer_no" class="form-control form-control-sm">
                  <option value="">Semua Dryer</option>
                  <?php foreach ($noOptions as $no): ?>
                    <option value="<?= htmlspecialchars($no) ?>" <?= $dryerNoFilter === $no ? 'selected' : '' ?>><?= htmlspecialchars($no) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="dryer-report-no-field">
                <label for="ct_no">CT No</label>
                <select id="ct_no" name="ct_no" class="form-control form-control-sm">
                  <option value="">Semua CT</option>
                  <?php foreach ($noOptions as $no): ?>
                    <option value="<?= htmlspecialchars($no) ?>" <?= $ctNoFilter === $no ? 'selected' : '' ?>><?= htmlspecialchars($no) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="dryer-report-actions">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                <button type="submit" class="btn btn-success btn-sm" formaction="export_excel_report_dryer_weaving.php"><i class="fas fa-file-excel"></i> Export Excel</button>
                <button type="submit" class="btn btn-danger btn-sm" formaction="export_pdf_report_dryer_weaving.php" formtarget="_blank"><i class="fas fa-file-pdf"></i> Export PDF</button>
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
            <?php if (count($sheets) === 0): ?>
              <div class="alert alert-info mb-0">Tidak ada data.</div>
            <?php else: ?>
              <div class="dryer-report-wrap">
                <?php foreach ($sheets as $sheet): ?>
                  <div class="dryer-report-panel">
                    <table class="dryer-report-table">
                      <thead>
                        <tr><th colspan="<?= 2 + count($hours) ?>" class="sheet-title">LOG SHEET PERSHIFT DRYER D IN - W DAN COOLING TOWER (CT) INGERSOLL RAND</th></tr>
                        <tr class="sheet-info">
                          <th colspan="2">TANGGAL : <?= htmlspecialchars(dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></th>
                          <th colspan="8" class="text-center">DRYER NO : <?= htmlspecialchars($sheet['Dryer_No'] ?? '') ?></th>
                          <th colspan="8" class="text-center">CT NO : <?= htmlspecialchars($sheet['Ct_No'] ?? '') ?></th>
                          <th colspan="8" class="text-center">SUM-FM-THK-WV-006</th>
                        </tr>
                        <tr>
                          <th class="head-blue" rowspan="2">Item Check</th>
                          <th class="head-blue" rowspan="2">Standard</th>
                          <th class="head-blue" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th>
                        </tr>
                        <tr>
                          <?php foreach ($hours as $hour): ?>
                            <th class="head-blue"><?= htmlspecialchars(dryer_weaving_display_hour($hour)) ?></th>
                          <?php endforeach; ?>
                        </tr>
                      </thead>
                      <tbody>
                        <?php $currentCategory = ''; ?>
                        <?php foreach ($items as $item): ?>
                          <?php if ($item['category'] !== $currentCategory): $currentCategory = $item['category']; ?>
                            <tr class="category-row"><td colspan="<?= 2 + count($hours) ?>"><?= htmlspecialchars($currentCategory) ?></td></tr>
                          <?php endif; ?>
                          <tr>
                            <td class="item-cell"><?= $item['item'] ?></td>
                            <td class="standard-cell"><?= htmlspecialchars($item['standard']) ?></td>
                            <?php foreach ($hours as $hour): ?>
                              <?php
                                $key = $item['key'] . '|' . $hour;
                                $nilai = $sheet['Cells'][$key]['nilai'] ?? '';
                                $isOutStandard = dryer_weaving_is_out_of_standard($nilai, $item['standard']);
                              ?>
                              <td class="<?= $isOutStandard ? 'out-standard-cell' : '' ?>"><?= htmlspecialchars($nilai) ?></td>
                            <?php endforeach; ?>
                          </tr>
                        <?php endforeach; ?>
                        <tr class="shift-row">
                          <td colspan="2" class="shift-label">SHIFT</td>
                          <?php foreach ($hours as $hour): ?>
                            <?php $hourShift = $sheet['Shifts'][$hour] ?? ''; ?>
                            <td class="shift-cell"><?= htmlspecialchars($hourShift) ?></td>
                          <?php endforeach; ?>
                        </tr>
                        <tr class="petugas-row">
                          <td colspan="2" class="shift-label">PETUGAS</td>
                          <?php foreach ($hours as $hour): ?>
                            <?php $hourPetugas = $sheet['PetugasByHour'][$hour] ?? ''; ?>
                            <td class="petugas-cell"><?= htmlspecialchars($hourPetugas) ?></td>
                          <?php endforeach; ?>
                        </tr>
                        <tr class="keterangan-row">
                          <td colspan="2" class="shift-label">KETERANGAN</td>
                          <td colspan="<?= count($hours) ?>" class="keterangan-cell"><?= htmlspecialchars(!empty($sheet['Keterangan']) ? $sheet['Keterangan'] : '-') ?></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
.dryer-report-filter-row{display:flex;flex-wrap:nowrap;align-items:flex-end;gap:10px}
.dryer-report-date-field{flex:0 0 270px;max-width:270px}
.dryer-report-no-field{flex:0 0 150px;max-width:150px}
.dryer-report-actions{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;margin-left:8px;padding-top:24px;white-space:nowrap}
.dryer-report-wrap{overflow:auto;background:#f8fafc;padding:12px;border:1px solid #cbd5e1;border-radius:4px}
.dryer-report-panel{background:#fff;border:1px solid #64748b;box-shadow:0 1px 3px rgba(15,23,42,.08);margin-bottom:16px;min-width:1320px}
.dryer-report-panel:last-child{margin-bottom:0}
.dryer-report-table{width:100%;table-layout:fixed;border-collapse:collapse;background:#fff;font-size:11px}
.dryer-report-table th,.dryer-report-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px;height:22px;overflow:hidden;text-overflow:ellipsis}
.dryer-report-table .sheet-title{font-size:14px;font-weight:800;background:#fff}
.dryer-report-table .sheet-info th{text-align:left;background:#fff;font-size:12px}
.dryer-report-table .sheet-info th.text-center{text-align:center}
.dryer-report-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;font-size:10px;line-height:1.15;text-transform:uppercase}
.dryer-report-table .category-row td{background:#b7b7b7;font-weight:700;text-align:left}
.dryer-report-table .item-cell{text-align:left;width:150px}
.dryer-report-table .standard-cell{font-weight:600;width:90px}
.dryer-report-table .shift-label{background:#b7b7b7;font-weight:700;text-align:center}
.dryer-report-table .shift-cell{font-weight:600;text-align:center;background:#fff}
.dryer-report-table .petugas-cell{font-weight:600;text-align:center;background:#fff;font-size:10px}
.dryer-report-table .keterangan-cell{text-align:left;padding:5px 8px;background:#fff;font-size:11px;font-weight:500;white-space:normal;word-break:break-word}
.dryer-report-table td.out-standard-cell{background:#f8d7da;color:#842029;font-weight:700}
@media (max-width:767.98px){
  .dryer-report-filter-row{flex-wrap:wrap}
  .dryer-report-date-field{flex-basis:100%;max-width:none}
  .dryer-report-no-field{flex:1 1 46%;max-width:none}
  .dryer-report-actions{width:100%;margin-left:0;padding-top:0;flex-wrap:wrap}
  .dryer-report-actions .btn{flex:1 1 46%;font-size:11px}
  .dryer-report-wrap{padding:8px}
  .dryer-report-panel{min-width:1100px}
}
</style>

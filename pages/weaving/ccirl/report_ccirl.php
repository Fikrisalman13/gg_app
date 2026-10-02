<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');
weaving_require($conn, 'CanView');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$compressorNo = trim($_POST['compressor_no'] ?? '');
$errorMsg = '';
if (strtotime($start) > strtotime($end)) $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';

$items = ccirl_items();
$hours = ccirl_hours();
$sheets = [];
if ($errorMsg === '' && ccirl_table_exists($conn)) {
    $whereExtra = '';
    $params = [$start, $end];
    if ($compressorNo !== '') {
        $whereExtra = ' AND Compressor_No = ?';
        $params[] = $compressorNo;
    }
    $sql = "SELECT MIN(Id) AS Id, CAST(Tanggal AS DATE) AS Tanggal, Compressor_No
            FROM dbo.ccirl
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ? $whereExtra
            GROUP BY CAST(Tanggal AS DATE), Compressor_No
            ORDER BY CAST(Tanggal AS DATE) ASC, Compressor_No ASC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data report.';
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateKey = ccirl_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
            $row['Cells'] = ccirl_get_cells($conn, $dateKey, $row['Compressor_No']);
            $row['Keterangan'] = ccirl_keterangan_for_sheet_query($conn, $dateKey, $row['Compressor_No']);
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
        <div class="col-sm-8"><h1 class="m-0">Report CCIRL</h1></div>
        <div class="col-sm-4 text-right"><a href="ccirl.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div>
      </div>
    </div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h3 class="card-title m-0"><i class="fas fa-filter mr-1"></i> Filter Report</h3></div>
        <div class="card-body">
          <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="cc-report-filter-row">
              <div class="cc-report-field"><label>Dari Tanggal</label><input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required></div>
              <div class="cc-report-field"><label>Sampai Tanggal</label><input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required></div>
              <div class="cc-report-no-field"><label>Compressor No</label><select name="compressor_no" class="form-control form-control-sm"><option value="">Semua</option><?php foreach (ccirl_no_options() as $no): ?><option value="<?= htmlspecialchars($no) ?>" <?= $compressorNo === $no ? 'selected' : '' ?>><?= htmlspecialchars($no) ?></option><?php endforeach; ?></select></div>
              <div class="cc-report-actions">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                <button type="submit" class="btn btn-success btn-sm" formaction="export_excel_report_ccirl.php"><i class="fas fa-file-excel"></i> Export Excel</button>
                <button type="submit" class="btn btn-danger btn-sm" formaction="export_pdf_report_ccirl.php" formtarget="_blank"><i class="fas fa-file-pdf"></i> Export PDF</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
      <?php else: ?>
        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h3 class="card-title"><i class="fas fa-list mr-1"></i> Detail Report</h3></div>
          <div class="card-body p-2">
            <?php if (count($sheets) === 0): ?>
              <div class="alert alert-info mb-0">Tidak ada data.</div>
            <?php else: ?>
              <div class="cc-report-wrap">
                <?php foreach ($sheets as $sheet): ?>
                  <div class="cc-report-panel">
                    <table class="cc-report-table">
                      <colgroup>
                        <col style="width: 250px;">
                        <?php foreach ($hours as $hour): ?>
                          <col style="width: 46px;">
                        <?php endforeach; ?>
                      </colgroup>
                      <thead>
                        <tr><th colspan="<?= 1 + count($hours) ?>" class="sheet-title">CENTAC COMPRESSOR INGERSOLL RAND LOG SHEET</th></tr>
                        <tr>
                          <th class="date-title" style="white-space: nowrap;">COMPRESSOR NO : <?= htmlspecialchars($sheet['Compressor_No'] ?? '') ?></th>
                          <th colspan="<?= count($hours) ?>" class="date-title text-right" style="white-space: nowrap;">SUM-FM-THK-WV-013</th>
                        </tr>
                        <tr><th colspan="<?= 1 + count($hours) ?>" class="date-title" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars(ccirl_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></th></tr>
                        <tr><th class="head-grey" rowspan="2" style="white-space: nowrap;">Status Message</th><th class="head-grey" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th></tr>
                        <tr><?php foreach ($hours as $hour): ?><th class="head-grey"><?= htmlspecialchars(ccirl_display_hour($hour)) ?></th><?php endforeach; ?></tr>
                      </thead>
                      <tbody>
                        <?php foreach ($items as $item): ?>
                          <tr><td class="status-cell"><?= $item['label'] ?></td><?php foreach ($hours as $hour): $key = $item['key'] . '|' . $hour; ?><td><?= htmlspecialchars($sheet['Cells'][$key]['nilai'] ?? '') ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                        <tr><td class="petugas-title">PETUGAS</td><?php foreach ($hours as $hour): $petugas = ccirl_petugas_for_hour($sheet['Cells'], $items, $hour); ?><td><?= htmlspecialchars($petugas) ?></td><?php endforeach; ?></tr>
                        <tr>
                          <td class="petugas-title" style="background:#d9d9d9; font-weight:700;">KETERANGAN</td>
                          <td colspan="<?= count($hours) ?>" style="text-align:left; padding:4px 8px; background:#fff; font-weight:500;"><?= htmlspecialchars(!empty($sheet['Keterangan']) ? $sheet['Keterangan'] : '-') ?></td>
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
.cc-report-filter-row{display:flex;flex-wrap:nowrap;align-items:flex-end;gap:10px}
.cc-report-field{flex:0 0 240px}.cc-report-no-field{flex:0 0 150px}
.cc-report-actions{display:flex;gap:6px;white-space:nowrap}
.cc-report-wrap{overflow:auto;background:#f8fafc;padding:12px;border:1px solid #cbd5e1;border-radius:4px}
.cc-report-panel{background:#fff;border:1px solid #64748b;margin-bottom:16px;min-width:1354px}
.cc-report-table{border-collapse:collapse;width:100%;table-layout:fixed;font-size:11px}
.cc-report-table th,.cc-report-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px;height:22px}
.cc-report-table .sheet-title{font-size:14px;font-weight:800;background:#fff}
.cc-report-table .date-title{text-align:left;background:#fff;font-weight:700;white-space:nowrap}
.cc-report-table .date-title.text-right{text-align:right}
.cc-report-table .head-grey,.cc-report-table .status-cell,.cc-report-table .petugas-title{background:#d9d9d9;font-weight:700}
.cc-report-table .status-cell{text-align:left;width:250px}
@media(max-width:767.98px){.cc-report-filter-row{flex-wrap:wrap}.cc-report-field,.cc-report-no-field{flex:1 1 100%}.cc-report-actions{width:100%;flex-wrap:wrap}.cc-report-actions .btn{flex:1 1 46%;font-size:11px}.cc-report-panel{min-width:1200px}}
</style>

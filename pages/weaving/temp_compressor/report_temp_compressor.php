<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_helper.php');
weaving_require($conn, 'CanView');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$errorMsg = '';
if (strtotime($start) > strtotime($end)) $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';

$rows = [];
$keteranganList = [];
if ($errorMsg === '' && temp_compressor_table_exists($conn)) {
    $sql = "SELECT Tanggal, Jam, Compressor1_In_C, Compressor1_Out_C, Compressor2_In_C, Compressor2_Out_C,
                   Amper, PressureBar_P1, PressureBar_P2,
                   Temperature_T1, Temperature_T2, Temperature_T3,
                   Dryer_C, TekananAir_In, TekananAir_Out,
                   Petugas, Keterangan
            FROM dbo.temp_compressor
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(Tanggal AS DATE) ASC, CAST(Jam AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data report.';
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
            $ket = trim((string)($row['Keterangan'] ?? ''));
            if ($ket !== '' && !in_array($ket, $keteranganList, true)) {
                $keteranganList[] = $ket;
            }
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
        <div class="col-sm-8"><h1 class="m-0">Report Check Sheet Kompressor Sullair</h1></div>
        <div class="col-sm-4 text-right"><a href="temp_compressor.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div>
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
            <div class="compressor-report-filter-row">
              <div class="compressor-report-date-field">
                <label for="start_date">Dari Tanggal</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
              </div>
              <div class="compressor-report-date-field">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
              </div>
              <div class="compressor-report-actions">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                <button type="submit" class="btn btn-success btn-sm" formaction="export_excel_report_temp_compressor.php"><i class="fas fa-file-excel"></i> Export Excel</button>
                <button type="submit" class="btn btn-danger btn-sm" formaction="export_pdf_report_temp_compressor.php" formtarget="_blank"><i class="fas fa-file-pdf"></i> Export PDF</button>
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
            <div class="compressor-report-wrap">
              <table class="compressor-report-table">
                <colgroup>
                  <col style="width: 60px;">
                  <col style="width: 55px;">
                  <col style="width: 55px;">
                  <col style="width: 55px;">
                  <col style="width: 55px;">
                  <col style="width: 55px;">
                  <col style="width: 50px;">
                  <col style="width: 50px;">
                  <col style="width: 50px;">
                  <col style="width: 50px;">
                  <col style="width: 50px;">
                  <col style="width: 60px;">
                  <col style="width: 55px;">
                  <col style="width: 55px;">
                  <col style="width: 180px;">
                </colgroup>
                <thead>
                  <tr><th colspan="15" class="sheet-title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>
                  <tr>
                    <th colspan="14" class="date-title" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars(date('d/m/Y', strtotime($start))) ?> - <?= htmlspecialchars(date('d/m/Y', strtotime($end))) ?></th>
                    <th class="date-title text-center" style="white-space: nowrap;">SUM-FM-THK-016</th>
                  </tr>
                  <tr>
                    <th class="head-blue" rowspan="2">Jam</th>
                    <th class="head-blue" colspan="2">Compressor 1</th>
                    <th class="head-blue" colspan="2">Compressor 2</th>
                    <th class="head-blue" rowspan="2">Amper</th>
                    <th class="head-blue" colspan="2">Pressure Bar</th>
                    <th class="head-blue" colspan="3">Temperature &deg;C</th>
                    <th class="head-blue" rowspan="2">Dryer &deg;C<br>(Celcius)</th>
                    <th class="head-blue" colspan="2">Tekanan Air</th>
                    <th class="head-blue" rowspan="2">Petugas</th>
                  </tr>
                  <tr>
                    <th class="head-blue">IN &deg;C</th>
                    <th class="head-blue">OUT &deg;C</th>
                    <th class="head-blue">IN &deg;C</th>
                    <th class="head-blue">OUT &deg;C</th>
                    <th class="head-blue">P1</th>
                    <th class="head-blue">P2</th>
                    <th class="head-blue">T1</th>
                    <th class="head-blue">T2</th>
                    <th class="head-blue">T3</th>
                    <th class="head-blue">IN</th>
                    <th class="head-blue">OUT</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($rows) === 0): ?>
                    <tr><td colspan="15" class="empty">Tidak ada data.</td></tr>
                  <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars(temp_compressor_display_hour(temp_compressor_fmt_time($row['Jam'] ?? null))) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Compressor1_In_C'] ?? null, 0)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Compressor1_Out_C'] ?? null, 0)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Compressor2_In_C'] ?? null, 0)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Compressor2_Out_C'] ?? null, 0)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Amper'] ?? null, 0)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['PressureBar_P1'] ?? null, 1)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['PressureBar_P2'] ?? null, 1)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Temperature_T1'] ?? null, 1)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Temperature_T2'] ?? null, 1)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Temperature_T3'] ?? null, 1)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['Dryer_C'] ?? null, 0)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['TekananAir_In'] ?? null, 1)) ?></td>
                        <td><?= htmlspecialchars(temp_compressor_fmt_num($row['TekananAir_Out'] ?? null, 1)) ?></td>
                        <td><?= htmlspecialchars($row['Petugas'] ?? '') ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <tr class="keterangan-row">
                      <td style="background:#b7b7b7; font-weight:700; text-align:center;">KETERANGAN</td>
                      <td colspan="14" class="keterangan-cell" style="text-align:left; padding:5px 8px; background:#fff; font-weight:500;"><?= htmlspecialchars(!empty($keteranganList) ? implode('; ', $keteranganList) : '-') ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
.compressor-report-filter-row{display:flex;flex-wrap:nowrap;align-items:flex-end;gap:10px}
.compressor-report-date-field{flex:0 0 270px;max-width:270px}
.compressor-report-actions{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;margin-left:8px;padding-top:24px;white-space:nowrap}
.compressor-report-wrap{overflow:auto;background:#f8fafc;padding:12px;border:1px solid #cbd5e1;border-radius:4px}
.compressor-report-table{border-collapse:collapse;background:#fff;width:1050px;table-layout:fixed;font-size:12px;box-shadow:0 1px 3px rgba(15,23,42,.08)}
.compressor-report-table th,.compressor-report-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:4px 6px;height:24px}
.compressor-report-table .sheet-title{font-size:14px;font-weight:800;background:#fff}
.compressor-report-table .date-title{text-align:left;background:#fff;font-weight:700}
.compressor-report-table .date-title.text-center{text-align:center}
.compressor-report-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;text-transform:uppercase;font-size:11px}
.compressor-report-table .empty{color:#64748b;background:#f8fafc}
@media (max-width:767.98px){
  .compressor-report-filter-row{flex-wrap:wrap}
  .compressor-report-date-field{flex-basis:100%;max-width:none}
  .compressor-report-actions{width:100%;margin-left:0;padding-top:0;flex-wrap:wrap}
  .compressor-report-actions .btn{flex:1 1 46%;font-size:11px}
  .compressor-report-table{width:1050px}
}
</style>

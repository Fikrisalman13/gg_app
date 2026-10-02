<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/air_dryer_helper.php');
weaving_require($conn, 'CanView');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$start = $_POST['start_date'] ?? date('Y-m-d');
$end = $_POST['end_date'] ?? $start;
$errorMsg = '';
if (strtotime($start) > strtotime($end)) $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';

$rowsByDate = [];
$keteranganByDate = [];
if ($errorMsg === '' && air_dryer_table_exists($conn)) {
    $sql = "SELECT Tanggal, Jam_Pengecekan, AirDryer1_Temp_In_C, AirDryer1_Temp_Out_C,
                AirDryer1_Tekanan_In_Bar, AirDryer1_Tekanan_Out_Bar,
                AirDryer2_Temp_In_C, AirDryer2_Temp_Out_C,
                AirDryer2_Tekanan_In_Bar, AirDryer2_Tekanan_Out_Bar, Petugas, CreatAt, Keterangan
            FROM dbo.air_dryer
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(Tanggal AS DATE) ASC, CAST(Jam_Pengecekan AS TIME) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data report.';
    } else {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateKey = air_dryer_fmt_date($row['Tanggal'] ?? null, 'Y-m-d');
            $hourKey = air_dryer_fmt_time($row['Jam_Pengecekan'] ?? null);
            if ($dateKey === '' || $hourKey === '') continue;
            if (!isset($rowsByDate[$dateKey])) $rowsByDate[$dateKey] = [];
            $rowsByDate[$dateKey][$hourKey] = $row;
            $ket = trim((string)($row['Keterangan'] ?? ''));
            if ($ket !== '') {
                if (!isset($keteranganByDate[$dateKey])) $keteranganByDate[$dateKey] = [];
                if (!in_array($ket, $keteranganByDate[$dateKey], true)) {
                    $keteranganByDate[$dateKey][] = $ket;
                }
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
        <div class="col-sm-8"><h1 class="m-0">Report Air Dryer Weaving</h1></div>
        <div class="col-sm-4 text-right"><a href="air_dryer.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div>
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
            <div class="air-report-filter-row">
              <div class="air-report-date-field">
                <label for="start_date">Dari Tanggal</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
              </div>
              <div class="air-report-date-field">
                <label for="end_date">Sampai Tanggal</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
              </div>
              <div class="air-report-actions">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                <button type="submit" class="btn btn-success btn-sm" formaction="export_excel_report_air_dryer.php"><i class="fas fa-file-excel"></i> Export Excel</button>
                <button type="submit" class="btn btn-danger btn-sm" formaction="export_pdf_report_air_dryer.php" formtarget="_blank"><i class="fas fa-file-pdf"></i> Export PDF</button>
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
            <?php if (count($rowsByDate) === 0): ?>
              <div class="alert alert-info mb-0">Tidak ada data.</div>
            <?php else: ?>
              <div class="air-report-wrap">
                <?php foreach ($rowsByDate as $dateKey => $dateRows): ?>
                  <div class="air-report-panel">
                    <table class="air-report-table">
                      <colgroup>
                        <col style="width: 8%;">
                        <col style="width: 9%;">
                        <col style="width: 9%;">
                        <col style="width: 9%;">
                        <col style="width: 9%;">
                        <col style="width: 9%;">
                        <col style="width: 9%;">
                        <col style="width: 9%;">
                        <col style="width: 9%;">
                        <col style="width: 20%;">
                      </colgroup>
                      <thead>
                        <tr><th colspan="10" class="sheet-title">PENCATATAN AIR DRYER WEAVING</th></tr>
                        <tr>
                          <th colspan="9" class="date-title" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars(date('d/m/Y', strtotime($dateKey))) ?></th>
                          <th class="date-title text-center" style="white-space: nowrap;">SUM-FM-THK-WV-017</th>
                        </tr>
                        <tr>
                          <th class="head-blue" rowspan="3">Jam<br>Pengecekan</th>
                          <th class="head-blue" colspan="4">Air Dryer 1</th>
                          <th class="head-blue" colspan="4">Air Dryer 2</th>
                          <th class="head-blue" rowspan="3">Petugas</th>
                        </tr>
                        <tr>
                          <th class="head-blue" colspan="2">Temperatur Air</th>
                          <th class="head-blue" colspan="2">Tekanan Air</th>
                          <th class="head-blue" colspan="2">Temperatur Air</th>
                          <th class="head-blue" colspan="2">Tekanan Air</th>
                        </tr>
                        <tr>
                          <th class="head-blue">IN (&deg;C)</th><th class="head-blue">OUT (&deg;C)</th>
                          <th class="head-blue">IN (BAR)</th><th class="head-blue">OUT (BAR)</th>
                          <th class="head-blue">IN (&deg;C)</th><th class="head-blue">OUT (&deg;C)</th>
                          <th class="head-blue">IN (BAR)</th><th class="head-blue">OUT (BAR)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach (air_dryer_hours() as $hour): $row = $dateRows[$hour] ?? []; ?>
                          <tr>
                            <td><?= htmlspecialchars($hour) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer1_Temp_In_C'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer1_Temp_Out_C'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer1_Tekanan_In_Bar'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer1_Tekanan_Out_Bar'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer2_Temp_In_C'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer2_Temp_Out_C'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer2_Tekanan_In_Bar'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars(air_dryer_fmt_num($row['AirDryer2_Tekanan_Out_Bar'] ?? null, 1)) ?></td>
                            <td><?= htmlspecialchars($row['Petugas'] ?? '') ?></td>
                          </tr>
                        <?php endforeach; ?>
                        <tr class="keterangan-row">
                          <td style="background:#b7b7b7; font-weight:700; text-align:center;">KETERANGAN</td>
                          <td colspan="9" style="text-align:left; padding:4px 8px; background:#fff; font-weight:500; font-size:11px;"><?= htmlspecialchars(!empty($keteranganByDate[$dateKey]) ? implode('; ', $keteranganByDate[$dateKey]) : '-') ?></td>
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
.air-report-filter-row{display:flex;flex-wrap:nowrap;align-items:flex-end;gap:10px}
.air-report-date-field{flex:0 0 270px;max-width:270px}
.air-report-actions{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;margin-left:8px;padding-top:24px;white-space:nowrap}
.air-report-wrap{overflow:auto;background:#f8fafc;padding:12px;border:1px solid #cbd5e1;border-radius:4px}
.air-report-panel{background:#fff;border:1px solid #64748b;box-shadow:0 1px 3px rgba(15,23,42,.08);margin-bottom:16px;min-width:1060px}
.air-report-panel:last-child{margin-bottom:0}
.air-report-table{border-collapse:collapse;background:#fff;width:100%;table-layout:fixed;font-size:11px}
.air-report-table th,.air-report-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px;height:23px}
.air-report-table .sheet-title{font-size:14px;font-weight:800;background:#fff}
.air-report-table .date-title{text-align:left;background:#fff;font-weight:700}
.air-report-table .date-title.text-center{text-align:center}
.air-report-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;text-transform:uppercase;font-size:10px;line-height:1.15}
@media (max-width:767.98px){
  .air-report-filter-row{flex-wrap:wrap}
  .air-report-date-field{flex-basis:100%;max-width:none}
  .air-report-actions{width:100%;margin-left:0;padding-top:0;flex-wrap:wrap}
  .air-report-actions .btn{flex:1 1 46%;font-size:11px}
  .air-report-wrap{padding:8px}
  .air-report-panel{min-width:980px}
}
</style>

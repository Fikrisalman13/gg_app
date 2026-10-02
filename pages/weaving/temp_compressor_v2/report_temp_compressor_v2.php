<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

$permissions = weaving_require($conn, 'CanView');
$canEdit = weaving_can($permissions, 'CanEdit');
$canDelete = weaving_can($permissions, 'CanDelete');
$canReport = $canEdit || $canDelete;

if (!$canReport) {
    header('Location: temp_compressor_v2.php');
    exit;
}

$start = trim($_POST['start_date'] ?? $_GET['start_date'] ?? date('Y-m-d'));
$end = trim($_POST['end_date'] ?? $_GET['end_date'] ?? date('Y-m-d'));
$weavingFilter = intval($_POST['weaving'] ?? $_GET['weaving'] ?? 0);
$compressorFilter = intval($_POST['compressor_no'] ?? $_GET['compressor_no'] ?? 0);

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
$hours = temp_compressor_v2_hours();

$errorMsg = '';
$sheetsData = [];

if (!temp_compressor_v2_table_exists($conn)) {
    $errorMsg = 'Tabel dbo.temp_compressor_v2 belum tersedia.';
} else {
    $whereParts = ['CAST(Tanggal AS DATE) >= ?', 'CAST(Tanggal AS DATE) <= ?'];
    $params = [$start, $end];

    if (in_array($weavingFilter, [1, 2], true)) {
        $whereParts[] = 'Weaving = ?';
        $params[] = $weavingFilter;
    }
    if (in_array($compressorFilter, [1, 2, 3], true)) {
        $whereParts[] = 'Compressor_No = ?';
        $params[] = $compressorFilter;
    }

    $whereClause = implode(' AND ', $whereParts);

    // Grouping by Tanggal, Weaving, Compressor_No
    $sheetListSql = "SELECT CAST(Tanggal AS DATE) AS Tanggal, Weaving, Compressor_No
                     FROM dbo.temp_compressor_v2
                     WHERE $whereClause
                     GROUP BY CAST(Tanggal AS DATE), Weaving, Compressor_No
                     ORDER BY CAST(Tanggal AS DATE) ASC, Weaving ASC, Compressor_No ASC";
    $sheetStmt = sqlsrv_query($conn, $sheetListSql, $params);

    if ($sheetStmt === false) {
        $errorMsg = 'Gagal mengambil data report.';
    } else {
        while ($sRow = sqlsrv_fetch_array($sheetStmt, SQLSRV_FETCH_ASSOC)) {
            $tglStr = temp_compressor_v2_fmt_date($sRow['Tanggal'] ?? null, 'Y-m-d');
            $w = (int)$sRow['Weaving'];
            $c = (int)$sRow['Compressor_No'];

            $cells = temp_compressor_v2_get_sheet_cells($conn, $tglStr, $w, $c);
            $ket = temp_compressor_v2_keterangan_for_sheet_query($conn, $tglStr, $w, $c);

            $sheetsData[] = [
                'tanggal'       => $tglStr,
                'tanggal_disp'  => temp_compressor_v2_fmt_date($sRow['Tanggal'] ?? null, 'd/m/Y'),
                'weaving'       => $w,
                'compressor_no' => $c,
                'cells'         => $cells,
                'keterangan'    => $ket,
            ];
        }
        sqlsrv_free_stmt($sheetStmt);
    }
}
?>

<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-8"><h1 class="m-0">Report Check Sheet Kompressor Sullair (V2)</h1></div>
        <div class="col-sm-4 text-right"><a href="temp_compressor_v2.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a></div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title m-0"><i class="fas fa-filter mr-1"></i> Filter Report</h3>
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
              <div class="compressor-report-date-field">
                <label for="weaving">Weaving</label>
                <select id="weaving" name="weaving" class="form-control form-control-sm">
                  <option value="">Semua Weaving</option>
                  <option value="1" <?= $weavingFilter === 1 ? 'selected' : '' ?>>Weaving 1</option>
                  <option value="2" <?= $weavingFilter === 2 ? 'selected' : '' ?>>Weaving 2</option>
                </select>
              </div>
              <div class="compressor-report-date-field">
                <label for="compressor_no">Compressor No</label>
                <select id="compressor_no" name="compressor_no" class="form-control form-control-sm">
                  <option value="">Semua Compressor</option>
                  <option value="1" <?= $compressorFilter === 1 ? 'selected' : '' ?>>Compressor 1</option>
                  <option value="2" <?= $compressorFilter === 2 ? 'selected' : '' ?>>Compressor 2</option>
                  <option value="3" <?= $compressorFilter === 3 ? 'selected' : '' ?>>Compressor 3</option>
                </select>
              </div>
              <div class="compressor-report-actions">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                <button type="submit" class="btn btn-success btn-sm" formaction="export_excel_report_temp_compressor_v2.php"><i class="fas fa-file-excel"></i> Export Excel</button>
                <button type="submit" class="btn btn-danger btn-sm" formaction="export_pdf_report_temp_compressor_v2.php" formtarget="_blank"><i class="fas fa-file-pdf"></i> Export PDF</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
      <?php elseif (count($sheetsData) === 0): ?>
        <div class="alert alert-info">Tidak ada data untuk rentang filter tanggal dan unit yang dipilih.</div>
      <?php else: ?>
        <?php foreach ($sheetsData as $sheetItem): ?>
          <div class="card print-card mb-4">
            <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex justify-content-between align-items-center">
              <h3 class="card-title m-0"><i class="fas fa-clipboard-check mr-1"></i> Tanggal: <?= htmlspecialchars($sheetItem['tanggal_disp']) ?> &mdash; Weaving <?= $sheetItem['weaving'] ?> &mdash; Compressor <?= $sheetItem['compressor_no'] ?></h3>
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
                    <col style="width: 60px;">
                    <col style="width: 70px;">
                    <col style="width: 60px;">
                    <col style="width: 60px;">
                    <col style="width: 60px;">
                    <col style="width: 60px;">
                    <col style="width: 180px;">
                  </colgroup>
                  <thead>
                    <tr><th colspan="13" class="sheet-title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>
                    <tr>
                      <th colspan="6" class="meta-title">
                        COMPRESSOR NO: <strong><?= $sheetItem['compressor_no'] ?></strong> (1 / 2 / 3) &nbsp;&nbsp;&nbsp;&nbsp; WEAVING: <strong><?= $sheetItem['weaving'] ?></strong> (1 / 2)
                      </th>
                      <th colspan="5" class="meta-title text-center">
                        TANGGAL: <strong><?= htmlspecialchars($sheetItem['tanggal_disp']) ?></strong>
                      </th>
                      <th colspan="2" class="meta-title text-center" style="white-space: nowrap;">
                        SUM-FM-THK-016
                      </th>
                    </tr>
                    <tr>
                      <th class="head-blue" rowspan="2">JAM</th>
                      <th class="head-blue" colspan="2">PRESSURE (BAR)</th>
                      <th class="head-blue" colspan="3">TEMPERATURE</th>
                      <th class="head-blue" rowspan="2">DRYER<br>(&deg;C)</th>
                      <th class="head-blue" rowspan="2">ARUS<br>LISTRIK (A)</th>
                      <th class="head-blue" colspan="4">AIR COOLING</th>
                      <th class="head-blue" rowspan="2">PELAKSANA</th>
                    </tr>
                    <tr>
                      <th class="head-blue">P1</th>
                      <th class="head-blue">P2</th>
                      <th class="head-blue">T1</th>
                      <th class="head-blue">T2</th>
                      <th class="head-blue">T3</th>
                      <th class="head-blue">PRESS. IN</th>
                      <th class="head-blue">PRESS. OUT</th>
                      <th class="head-blue">TEMP. IN</th>
                      <th class="head-blue">TEMP. OUT</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($hours as $hour): ?>
                      <?php $c = $sheetItem['cells'][$hour] ?? []; ?>
                      <tr>
                        <td><strong><?= htmlspecialchars(temp_compressor_v2_display_hour($hour)) ?></strong></td>
                        <td><?= htmlspecialchars($c['pressure_p1'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['pressure_p2'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['temp_t1'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['temp_t2'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['temp_t3'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['dryer_c'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['arus_a'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['press_in'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['press_out'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['temp_in'] ?? '') ?: '-' ?></td>
                        <td><?= htmlspecialchars($c['temp_out'] ?? '') ?: '-' ?></td>
                        <td class="text-left"><?= htmlspecialchars($c['pelaksana'] ?? '') ?: '-' ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <tr>
                      <td class="keterangan-cell" style="background:#b7b7b7; font-weight:700;">KETERANGAN</td>
                      <td colspan="12" style="text-align:left; padding:4px 8px;"><?= htmlspecialchars($sheetItem['keterangan'] ?: '-') ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
.compressor-report-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}
.compressor-report-date-field {
    min-width: 140px;
}
.compressor-report-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.compressor-report-wrap {
    overflow-x: auto;
    padding: 8px;
    background: #f8fafc;
    border-radius: 4px;
}
.compressor-report-table {
    border-collapse: collapse;
    background: #fff;
    width: 1050px;
    table-layout: fixed;
    font-size: 11px;
    margin: 0 auto;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.compressor-report-table th, .compressor-report-table td {
    border: 1px solid #111;
    text-align: center;
    vertical-align: middle;
    padding: 3px 4px;
    height: 24px;
}
.compressor-report-table .sheet-title {
    font-size: 14px;
    font-weight: 800;
    background: #fff;
    letter-spacing: .5px;
}
.compressor-report-table .meta-title {
    font-size: 11px;
    background: #fff;
    padding: 4px 6px;
    text-align: left;
}
.compressor-report-table .head-blue {
    background: #9dc3e6;
    font-weight: 700;
    color: #000;
    text-transform: uppercase;
    font-size: 9.5px;
    line-height: 1.15;
}
</style>

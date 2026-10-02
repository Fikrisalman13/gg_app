<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 1239;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}
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

$fmtDateCell = function ($val) {
    if ($val instanceof DateTime) return $val->format('d-M-y');
    if (is_string($val) && $val !== '') {
        $ts = strtotime($val);
        if ($ts) return date('d-M-y', $ts);
    }
    return '';
};

$fmtNum = function ($val, $maxDec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    $s = number_format((float)$val, $maxDec, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' ? '0' : $s;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startInput = $normalizeDate($_POST['start_date'] ?? '');
    $endInput = $normalizeDate($_POST['end_date'] ?? '');
    if ($startInput === '' || $endInput === '') $errorMsg = 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    elseif (strtotime($startInput) > strtotime($endInput)) $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    else {
        $start = $startInput;
        $end = $endInput;
    }
}

$grouped = [];
if ($errorMsg === '') {
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
    if ($stmt === false) {
        $errorMsg = 'Query gagal: ' . print_r(sqlsrv_errors(), true);
    } else {
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
}
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2 align-items-center">
        <div class="col-md-6">
          <h1 class="m-0">Laporan Monitoring Air Boiler - Alstom</h1>
        </div>
        <div class="col-md-6 text-right">
          <a href="air_boiler.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center" style="min-height:42px;">
          <h3 class="card-title m-0"><i class="fas fa-filter"></i> Filter Rentang Tanggal</h3>
        </div>
        <div class="card-body">
          <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="row g-3 align-items-end">
              <div class="col-sm-6 col-lg-3">
                <label for="start_date" class="form-label fw-bold">Tanggal Mulai</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
              </div>
              <div class="col-sm-6 col-lg-3">
                <label for="end_date" class="form-label fw-bold">Tanggal Selesai</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
              </div>
              <div class="col-sm-6 col-lg-3 d-flex align-items-end" style="gap:8px;">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
              </div>
            </div>
          </form>

          <div class="d-flex mt-3" style="gap:8px;">
            <form method="post" action="export_excel_report_air_boiler.php" class="m-0 p-0">
              <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
              <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
              <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
            </form>
          </div>
        </div>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
      <?php else: ?>
        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
            <h3 class="card-title"><i class="fas fa-list mr-1"></i> Detail Laporan Air Boiler</h3>
          </div>

          <style>
            .boiler-report-table { border-color: #000; }
            .boiler-report-table th, .boiler-report-table td {
              text-align: center;
              vertical-align: middle;
              border: 1px solid #000;
              white-space: nowrap;
            }
            .boiler-report-table thead th {
              background: #f2f2f2;
              font-weight: 700;
            }
            .boiler-table-container {
              max-height: 75vh;
              overflow-y: auto;
              overflow-x: auto;
              position: relative;
              border: 1px solid #000;
            }
            .boiler-table-title {
              text-align: center;
              font-weight: 700;
              font-size: 20px;
              letter-spacing: 0.3px;
              padding: 8px 0 10px;
            }
            .boiler-report-table thead th {
              position: sticky;
              z-index: 10;
              top: 0;
            }
            .boiler-report-table thead tr:nth-child(1) th { top: 0; z-index: 12; }
            .boiler-report-table thead tr:nth-child(2) th { top: 34px; z-index: 11; }
            .boiler-report-table thead tr:nth-child(3) th { top: 68px; z-index: 10; }
          </style>

          <div class="card-body p-2">
            <div class="table-responsive boiler-table-container">
              <div class="boiler-table-title">MONITORING AIR BOILER - ALSTOM</div>
              <table class="table table-bordered table-sm boiler-report-table mb-0">
                <thead>
                  <tr>
                    <th rowspan="3">Tanggal</th>
                    <th colspan="4">AIR UMPAN</th>
                    <th colspan="4">AIR BOILER</th>
                    <th colspan="1">BLOWDOWN</th>
                    <th rowspan="3">Keterangan</th>
                    <th colspan="2">ms/cm (display alat)</th>
                  </tr>
                  <tr>
                    <th>Temp</th>
                    <th>DH</th>
                    <th colspan="2">TDS</th>
                    <th rowspan="2">pH</th>
                    <th>Temp</th>
                    <th colspan="2">TDS</th>
                    <th>Jumlah blowdown</th>
                    <th>Air Umpan</th>
                    <th>Air Boiler</th>
                  </tr>
                  <tr>
                    <th>C</th>
                    <th>std &lt; 1</th>
                    <th>ms/cm</th>
                    <th>ppm</th>
                    <th>C</th>
                    <th>ms/cm</th>
                    <th>ppm</th>
                    <th></th>
                    <th></th>
                    <th></th>
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
                            <td rowspan="<?= $rowspan ?>"><?= htmlspecialchars($fmtDateCell($dateKey)) ?></td>
                          <?php endif; ?>
                          <td><?= htmlspecialchars($fmtNum($r['temp_umpan_c'] ?? null)) ?></td>
                          <td><?= htmlspecialchars($fmtNum($r['dh_std_lt1'] ?? null)) ?></td>
                          <td><?= htmlspecialchars($fmtNum($r['tds_umpan_ms'] ?? null)) ?></td>
                          <td><?= htmlspecialchars($fmtNum($r['tds_umpan_ppm'] ?? null)) ?></td>
                          <td><?= htmlspecialchars((string)($r['ph_boiler'] ?? '')) ?></td>
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
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

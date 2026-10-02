<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/sync_air_bersih_limbah.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230;
requireView($conn, $menuId);

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
    $sql = "SELECT CAST(tanggal AS DATE) AS tanggal, *
            FROM dbo.air_bersih_limbah_harian
            WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(tanggal AS DATE) ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) $errorMsg = 'Gagal mengambil data report: ' . print_r(sqlsrv_errors(), true);
    else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateObj = $r['tanggal'] ?? null;
            $dateKey = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
            $r['tanggal'] = $dateKey;
            $rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$sourceCache = [];
foreach ($rows as &$syncRow) {
    $tanggalSync = abl_normalize_date($syncRow['tanggal'] ?? '');
    if ($tanggalSync === '') continue;
    if (!array_key_exists($tanggalSync, $sourceCache)) {
        $sourceCache[$tanggalSync] = abl_collect_source_values($conn, $tanggalSync);
        abl_sync_date($conn, $tanggalSync, $sourceCache[$tanggalSync]);
    }
    $syncRow = abl_overlay_row_with_source_values($syncRow, $sourceCache[$tanggalSync]);
}
unset($syncRow);

$catatanRows = [];
foreach ($rows as $row) {
    $noteValue = trim((string)($row['note'] ?? ''));
    if ($noteValue !== '') {
        $catatanRows[] = $row;
    }
}

$keys = [
    'flow_meter_intake_ipab_m3_hari','flow_meter_bak_dua_ke_ipal_m3_hari','flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari',
    'washing_1_m3_hari','washing_2_m3_hari','washing_3_m3_hari','perble_range_1_m3_hari','perble_range_2_m3_hari',
    'pad_steam_m3_hari','jetdying_sizing_la_m3_hari','kantin_mes_pos_security_m3_hari','boiler_m3_hari',
    'air_mc_produksi_dan_lain_lain_m3_hari','weaving_dan_lain_lain_m3_hari','buangan_air_produk_ke_ipal_m3_hari','flowmeter_output_ipal_m3_hari'
];
$tot = array_fill_keys($keys, 0);
foreach ($rows as $r) { foreach ($keys as $k) $tot[$k] += (float)($r[$k] ?? 0); }
$rowCount = count($rows);
$avg = [];
foreach ($keys as $k) $avg[$k] = $rowCount > 0 ? ($tot[$k] / $rowCount) : 0;

$fmtNum = function ($val, $dec = 2) { if (!is_numeric($val)) return ''; return number_format((float)$val, $dec, '.', ','); };
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
?>

<div class="content-wrapper">
  <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">REPORT AIR BERSIH & LIMBAH</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/air_bersih_limbah/air_bersih_limbah.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
          <form method="post" action="export_excel_report_air_bersih_limbah.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
          </form>
          <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modalCatatanAirBersihLimbah">
            <i class="fas fa-sticky-note"></i> Lihat Catatan
          </button>
        </div>
      </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
      <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
    <?php else: ?>
      <style>
        .abl-wrap{overflow-x:auto;overflow-y:visible;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
        .abl-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:2100px;background:#fff}
        .abl-report th,.abl-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
        .abl-report .title{background:#e8dccb;font-weight:700;font-size:30px}
        .abl-report .month{background:#fff;font-weight:700;font-size:20px;text-align:left}
        .abl-report .datecol{background:#d9d9c9;font-weight:700;min-width:70px}
        .abl-report .flow{background:#a9bdd6;font-weight:700}
        .abl-report .flow2{background:#e0d0d0;font-weight:700}
        .abl-report .flow3{background:#a9bdd6;font-weight:700}
        .abl-report .ipal{background:#fff200;font-weight:700}
        .abl-report .out{background:#ffc000;font-weight:700}
        .abl-report .data1{background:#a9bdd6}
        .abl-report .data2{background:#e0d0d0}
        .abl-report .data3{background:#a9bdd6}
        .abl-report .data4{background:#fff200}
        .abl-report .data5{background:#ffc000}
        .abl-report .sum td{background:#ffc000 !important;font-weight:700}
      </style>
      <div class="card"><div class="card-body p-2"><div class="abl-wrap"><table class="table table-sm abl-report">
        <thead>
          <tr><th class="month" colspan="17">BULAN : <?= htmlspecialchars($monthLabel) ?></th></tr>
          <tr>
            <th class="title" colspan="17">PEMAKAIAN AIR BERSIH DARI IPAB</th>
          </tr>
          <tr>
            <th class="datecol" rowspan="2">TGL</th>
            <th class="flow">FLOW METER<br>DARI WATER<br>INTAKE KE<br>IPAB</th>
            <th class="flow">FLOW METER<br>BAK DUA KE<br>IPAL</th>
            <th class="flow">FLOW METER<br>DARI BAK 3<br>KE BAK 4<br>JASA TIRTA</th>
            <th class="flow2">WASHING 1</th><th class="flow2">WASHING 2</th><th class="flow2">WASHING 3</th><th class="flow2">PERBLE<br>RANGE 1</th><th class="flow2">PERBLE<br>RANGE 2</th><th class="flow2">PAD<br>STEAM</th>
            <th class="flow3">JETDYING,<br>SIZING,LA</th><th class="flow3">KANTIN,MES<br>DAN POS<br>SECURITY</th><th class="flow3">BOILER</th><th class="flow3">AIR MC<br>PRODUKSI,<br>Dan Lain lain</th><th class="flow3">WEAVING<br>DAN LAIN<br>LAIN</th>
            <th class="ipal">TOTAL PEMAKAIAN AIR<br>PRODUKSI DF</th>
            <th class="out">FLOWMETER<br>OUTPUT</th>
          </tr>
          <tr>
            <?php for ($i=0;$i<16;$i++): ?><th><?= $i===15 ? 'm³/hari' : 'm³/hari' ?></th><?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="17">Tidak ada data pada rentang tanggal ini.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td class="datecol"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
              <td class="data1"><?= htmlspecialchars($fmtNum($r['flow_meter_intake_ipab_m3_hari'])) ?></td>
              <td class="data1"><?= htmlspecialchars($fmtNum($r['flow_meter_bak_dua_ke_ipal_m3_hari'])) ?></td>
              <td class="data1"><?= htmlspecialchars($fmtNum($r['flow_meter_bak3_ke_bak4_jasa_tirta_m3_hari'])) ?></td>
              <td class="data2"><?= htmlspecialchars($fmtNum($r['washing_1_m3_hari'])) ?></td>
              <td class="data2"><?= htmlspecialchars($fmtNum($r['washing_2_m3_hari'])) ?></td>
              <td class="data2"><?= htmlspecialchars($fmtNum($r['washing_3_m3_hari'])) ?></td>
              <td class="data2"><?= htmlspecialchars($fmtNum($r['perble_range_1_m3_hari'])) ?></td>
              <td class="data2"><?= htmlspecialchars($fmtNum($r['perble_range_2_m3_hari'])) ?></td>
              <td class="data2"><?= htmlspecialchars($fmtNum($r['pad_steam_m3_hari'])) ?></td>
              <td class="data3"><?= htmlspecialchars($fmtNum($r['jetdying_sizing_la_m3_hari'])) ?></td>
              <td class="data3"><?= htmlspecialchars($fmtNum($r['kantin_mes_pos_security_m3_hari'])) ?></td>
              <td class="data3"><?= htmlspecialchars($fmtNum($r['boiler_m3_hari'])) ?></td>
              <td class="data3"><?= htmlspecialchars($fmtNum($r['air_mc_produksi_dan_lain_lain_m3_hari'])) ?></td>
              <td class="data3"><?= htmlspecialchars($fmtNum($r['weaving_dan_lain_lain_m3_hari'])) ?></td>
              <td class="data4"><?= htmlspecialchars($fmtNum($r['buangan_air_produk_ke_ipal_m3_hari'])) ?></td>
              <td class="data5"><?= htmlspecialchars($fmtNum($r['flowmeter_output_ipal_m3_hari'])) ?></td>
            </tr>
          <?php endforeach; ?>
            <tr class="sum">
              <td>Total</td>
              <?php foreach ($keys as $k): ?><td><?= htmlspecialchars($fmtNum($tot[$k])) ?></td><?php endforeach; ?>
            </tr>
            <tr class="sum">
              <td>RATA-RATA</td>
              <?php foreach ($keys as $k): ?><td><?= htmlspecialchars($fmtNum($avg[$k])) ?></td><?php endforeach; ?>
            </tr>
          <?php endif; ?>
        </tbody>
      </table></div></div></div>
    <?php endif; ?>
  </div></section>
</div>

<div class="modal fade" id="modalCatatanAirBersihLimbah" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title"><i class="fas fa-sticky-note mr-1"></i> Catatan Air Bersih & Limbah</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body p-2">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light text-center">
              <tr>
                <th style="width: 120px;">Tanggal</th>
                <th>Catatan</th>
                <th style="width: 140px;">Input By</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($catatanRows)): ?>
                <tr><td colspan="3" class="text-center">Tidak ada catatan pada rentang tanggal ini.</td></tr>
              <?php else: ?>
                <?php foreach ($catatanRows as $catRow): ?>
                  <tr>
                    <td class="text-center"><?= htmlspecialchars(date('d-m-Y', strtotime($catRow['tanggal'] ?? ''))) ?></td>
                    <td><?= nl2br(htmlspecialchars((string)($catRow['note'] ?? ''))) ?></td>
                    <td class="text-center"><?= htmlspecialchars((string)($catRow['creatby'] ?? $catRow['updateby'] ?? '-')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

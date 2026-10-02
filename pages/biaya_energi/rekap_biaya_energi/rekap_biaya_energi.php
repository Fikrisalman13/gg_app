<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
require_once __DIR__ . '/rekap_biaya_energi_data.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230;
requireView($conn, $menuId);

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startInput = normalizeDateInputRekapBiayaEnergi($_POST['start_date'] ?? '');
    $endInput = normalizeDateInputRekapBiayaEnergi($_POST['end_date'] ?? '');

    if ($startInput === '' || $endInput === '') {
        $errorMsg = 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    } elseif (strtotime($startInput) > strtotime($endInput)) {
        $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    } else {
        $start = $startInput;
        $end = $endInput;
    }
}

$data = [
    'rows' => [],
    'totals' => [
        'biaya_listrik' => 0,
        'biaya_kimia_ipab' => 0,
        'biaya_kimia_ipal' => 0,
        'biaya_kimia_boiler' => 0,
        'biaya_kimia_weaving' => 0,
        'biaya_bb_wuxi' => 0,
        'biaya_bb_oil_xineng' => 0,
        'biaya_bb_20t_lama' => 0,
        'biaya_bb_20t_baru_longchuan' => 0,
        'biaya_bb_21t_actom' => 0,
        'biaya_bb_jineng' => 0,
        'biaya_lpg_skid_tank' => 0,
        'total_biaya' => 0,
    ],
    'averages' => [
        'biaya_listrik' => 0,
        'biaya_kimia_ipab' => 0,
        'biaya_kimia_ipal' => 0,
        'biaya_kimia_boiler' => 0,
        'biaya_kimia_weaving' => 0,
        'biaya_bb_wuxi' => 0,
        'biaya_bb_oil_xineng' => 0,
        'biaya_bb_20t_lama' => 0,
        'biaya_bb_20t_baru_longchuan' => 0,
        'biaya_bb_21t_actom' => 0,
        'biaya_bb_jineng' => 0,
        'biaya_lpg_skid_tank' => 0,
        'total_biaya' => 0,
    ],
    'row_count' => 0,
];

if ($errorMsg === '') {
    $data = buildRekapBiayaEnergiData($conn, $start, $end, $errorMsg);
}

$monthLabel = monthLabelRekapBiayaEnergi($start);
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h1 class="m-0">REKAPAN BIAYA ENERGI</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/gg_app/pages/biaya_energi/biaya_energi.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                <label for="start_date" class="form-label fw-bold">Dari Tanggal</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
              </div>
              <div class="col-sm-6 col-lg-3">
                <label for="end_date" class="form-label fw-bold">Sampai Tanggal</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
              </div>
              <div class="col-sm-6 col-lg-3 d-flex align-items-end" style="gap:8px;">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
              </div>
            </div>
          </form>

          <div class="d-flex mt-3" style="gap:8px;">
            <form method="post" action="export_excel_rekap_biaya_energi.php" class="m-0 p-0">
              <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
              <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
              <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
            </form>
            <form method="post" action="export_pdf_rekap_biaya_energi.php" class="m-0 p-0" target="_blank">
              <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
              <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Export PDF"><i class="fas fa-file-pdf"></i></button>
            </form>
          </div>
        </div>
      </div>

      <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
      <?php else: ?>
        <style>
          .rekap-wrap{
            overflow:auto;
            max-height:72vh;
            background:#efefef;
            padding:8px;
            border:1px solid #cfcfcf
          }
          .rekap-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:1780px;background:#fff}
          .rekap-report th,.rekap-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
          .rekap-report .title{background:#fff200;font-weight:700;font-size:34px;line-height:1.05}
          .rekap-report .month{background:#efe8b8;font-weight:700;font-size:24px}
          .rekap-report .h-date{background:#d9d9c9;font-weight:700;min-width:80px}
          .rekap-report .h-listrik{background:#fff200;font-weight:700;min-width:110px}
          .rekap-report .h-ipab{background:#95b3d7;font-weight:700;min-width:110px}
          .rekap-report .h-kimia{background:#d9d9d9;font-weight:700;min-width:110px}
          .rekap-report .h-bb{background:#e6b8af;font-weight:700;min-width:118px}
          .rekap-report .h-lpg{background:#fff200;font-weight:700;min-width:112px}
          .rekap-report .h-total{background:#ffc000;font-weight:700;min-width:112px}
          .rekap-report .unit{background:#fce5cd;font-weight:700}
          .rekap-report .d-date{background:#f0f4e3;font-weight:700}
          .rekap-report .d-listrik{background:#fff200}
          .rekap-report .d-ipab{background:#b8cce4}
          .rekap-report .d-kimia{background:#d9d9d9}
          .rekap-report .d-bb{background:#c9c9c9}
          .rekap-report .d-lpg{background:#fff200}
          .rekap-report .d-total{background:#ffc000}
          .rekap-report .sum td{background:#fff200 !important;font-weight:700}
          .rekap-report .sticky-left{position:sticky;left:0;z-index:6}
          .rekap-report thead th{position:sticky}
          .rekap-report thead .sticky-left{z-index:60}
          .rekap-wrap.manual-fullscreen{
            position:fixed;
            inset:0;
            z-index:9999;
            background:#fff;
            padding:10px;
            overflow:auto;
          }
        </style>

        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
            <h3 class="card-title"><i class="fas fa-list mr-1"></i> Detail Report</h3>
            <button type="button" class="btn btn-light btn-sm ml-auto" id="btnModePenuh"><i class="fas fa-expand-arrows-alt"></i> Mode Penuh</button>
          </div>
          <div class="card-body p-2">
            <div class="rekap-wrap" id="rekapTableWrap">
              <table class="table table-sm rekap-report mb-0">
                <thead>
                  <tr><th class="title" colspan="14">REKAPAN BIAYA ENERGI</th></tr>
                  <tr><th class="month" colspan="14">Bulan: <?= htmlspecialchars($monthLabel) ?></th></tr>
                  <tr>
                    <th class="h-date" rowspan="2">Tanggal</th>
                    <th class="h-listrik" rowspan="2">Biaya Listrik</th>
                    <th class="h-ipab" rowspan="2">Biaya<br>Chemical Air<br>IPAB</th>
                    <th class="h-kimia" rowspan="2">Biaya Kimia<br>IPAL</th>
                    <th class="h-kimia" rowspan="2">BIAYA KIMIA<br>BOILER</th>
                    <th class="h-kimia" rowspan="2">BIAYA<br>KIMIA<br>WEAVING</th>
                    <th class="h-bb" rowspan="2">Biaya Batu Bara<br>Boiler Wuxi</th>
                    <th class="h-bb" rowspan="2">Biaya Batu<br>Bara Boiler<br>OIL XINENG</th>
                    <th class="h-bb" rowspan="2">Biaya Batu<br>Bara Boiler<br>STEAM 20TON<br>LAMA</th>
                    <th class="h-bb" rowspan="2">Biaya Batu<br>Bara Boiler<br>STEAM 20TON<br>BARU<br>LONGCHUAN</th>
                    <th class="h-bb" rowspan="2">Biaya Batu Bara<br>Boiler STEAM<br>21TON ACTOM</th>
                    <th class="h-bb" rowspan="2">Biaya Batu Bara<br>Boiler JINENG</th>
                    <th class="h-lpg" rowspan="2">Biaya Gas LPG<br>Skid Tank</th>
                    <th class="h-total" rowspan="2">Total Biaya</th>
                  </tr>
                  <tr></tr>
                  <tr>
                    <th class="unit"></th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                    <th class="unit">(Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($data['rows'])): ?>
                    <tr><td colspan="14">Tidak ada data pada rentang tanggal ini.</td></tr>
                  <?php else: ?>
                    <?php foreach ($data['rows'] as $r): ?>
                      <tr>
                        <td class="d-date"><?= htmlspecialchars(fmtDayRekapBiayaEnergi($r['tanggal'])) ?></td>
                        <td class="d-listrik"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_listrik'])) ?></td>
                        <td class="d-ipab"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_ipab'])) ?></td>
                        <td class="d-kimia"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_ipal'])) ?></td>
                        <td class="d-kimia"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_boiler'])) ?></td>
                        <td class="d-kimia"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_weaving'])) ?></td>
                        <td class="d-bb"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_wuxi'])) ?></td>
                        <td class="d-bb"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_oil_xineng'])) ?></td>
                        <td class="d-bb"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_20t_lama'])) ?></td>
                        <td class="d-bb"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_20t_baru_longchuan'])) ?></td>
                        <td class="d-bb"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_21t_actom'])) ?></td>
                        <td class="d-bb"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_jineng'])) ?></td>
                        <td class="d-lpg"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_lpg_skid_tank'])) ?></td>
                        <td class="d-total"><?= htmlspecialchars(fmtNumRekapBiayaEnergi($r['total_biaya'])) ?></td>
                      </tr>
                    <?php endforeach; ?>

                    <tr class="sum">
                      <td>TOTAL</td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_listrik'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_ipab'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_ipal'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_boiler'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_weaving'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_wuxi'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_oil_xineng'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_20t_lama'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_20t_baru_longchuan'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_21t_actom'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_jineng'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_lpg_skid_tank'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['total_biaya'])) ?></td>
                    </tr>

                    <tr class="sum">
                      <td>RATA-RATA</td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_listrik'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_ipab'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_ipal'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_boiler'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_weaving'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_wuxi'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_oil_xineng'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_20t_lama'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_20t_baru_longchuan'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_21t_actom'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_jineng'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_lpg_skid_tank'])) ?></td>
                      <td><?= htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['total_biaya'])) ?></td>
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

<script>
$(function(){
  var $btnModePenuh = $('#btnModePenuh');
  var tableWrap = document.getElementById('rekapTableWrap');
  var tableEl = tableWrap ? tableWrap.querySelector('.rekap-report') : null;

  function applyStickyHeaderOffsets() {
    if (!tableEl) return;
    var rows = tableEl.querySelectorAll('thead tr');
    var topOffset = 0;
    rows.forEach(function(row, idx){
      var rowHeight = row.getBoundingClientRect().height || row.offsetHeight || 0;
      row.querySelectorAll('th').forEach(function(th){
        th.style.top = topOffset + 'px';
        th.style.zIndex = String(40 - idx);
      });
      topOffset += rowHeight;
    });
    tableEl.querySelectorAll('thead .sticky-left').forEach(function(th){
      th.style.zIndex = '70';
    });
  }

  function updateModePenuhLabel() {
    var isNativeFullscreen = document.fullscreenElement === tableWrap;
    var isManualFullscreen = tableWrap && tableWrap.classList.contains('manual-fullscreen');
    if (isNativeFullscreen || isManualFullscreen) {
      $btnModePenuh.html('<i class="fas fa-compress-arrows-alt"></i> Keluar Mode Penuh');
    } else {
      $btnModePenuh.html('<i class="fas fa-expand-arrows-alt"></i> Mode Penuh');
    }
  }

  $btnModePenuh.on('click', async function(){
    if (!tableWrap) return;
    try {
      if (document.fullscreenElement === tableWrap) {
        await document.exitFullscreen();
      } else if (!document.fullscreenElement && tableWrap.requestFullscreen) {
        await tableWrap.requestFullscreen();
      } else {
        $(tableWrap).toggleClass('manual-fullscreen');
      }
    } catch (e) {
      $(tableWrap).toggleClass('manual-fullscreen');
    }
    updateModePenuhLabel();
    applyStickyHeaderOffsets();
  });

  window.addEventListener('resize', applyStickyHeaderOffsets);
  document.addEventListener('fullscreenchange', updateModePenuhLabel);
  document.addEventListener('fullscreenchange', applyStickyHeaderOffsets);
  updateModePenuhLabel();
  applyStickyHeaderOffsets();
});
</script>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

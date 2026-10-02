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
    $sql = "SELECT CAST(tanggal AS DATE) AS tanggal, *
            FROM dbo.air_steam_20tonlama_harian
            WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
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

$airKeys = ['air_awal_m3','air_akhir_m3','air_total_pemakaian_m3','air_rata_rata_per_jam_m3'];
$steamKeys = ['steam_awal_ton','steam_akhir_ton','steam_total_pemakaian_ton','steam_rata_rata_per_jam_ton'];
$totAir = array_fill_keys($airKeys, 0);
$totSteam = array_fill_keys($steamKeys, 0);
foreach ($rows as $r) {
    foreach ($airKeys as $k) $totAir[$k] += (float)($r[$k] ?? 0);
    foreach ($steamKeys as $k) $totSteam[$k] += (float)($r[$k] ?? 0);
}
$rowCount = count($rows);
$avgAir = []; foreach ($airKeys as $k) $avgAir[$k] = $rowCount > 0 ? ($totAir[$k] / $rowCount) : 0;
$avgSteam = []; foreach ($steamKeys as $k) $avgSteam[$k] = $rowCount > 0 ? ($totSteam[$k] / $rowCount) : 0;

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
        <h1 class="m-0">REPORT AIR DAN STEAM 20 TON LAMA</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/20tonlama/20tonlama.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
          <form method="post" action="export_excel_report_20tonlama.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
          </form>
          <form method="post" action="export_pdf_report_20tonlama.php" class="m-0 p-0">
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
          <button type="button" class="btn btn-light btn-sm ml-auto" id="btnLihatCatatan"><i class="fas fa-sticky-note"></i> Lihat Catatan</button>
        </div>
        <style>
          .t20-wrap{overflow-x:auto;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
          .t20-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:1500px;background:#fff}
          .t20-report th,.t20-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
          .t20-report .head{background:#b7cae2;font-weight:700}
          .t20-report .sub{background:#f3e2e2;font-weight:700}
          .t20-report .date{background:#e6edd9;font-weight:700}
          .t20-report .data{background:#c4d5e9}
          .t20-report .sum td{background:#ece9d6 !important;font-weight:700}
        </style>
        <div class="card-body p-2">
          <div class="t20-wrap">
            <table class="table table-sm t20-report">
              <thead>
                <tr>
                  <th class="head" colspan="6">METER AIR BOILER STEAM 20 TON - <?= htmlspecialchars($monthLabel) ?></th>
                  <th style="border:none;background:#f0f0f0"></th>
                  <th class="head" colspan="6">METER STEAM BOILER STEAM 20 TON - <?= htmlspecialchars($monthLabel) ?></th>
                </tr>
                <tr>
                  <th class="sub">Tanggal</th><th class="sub">Awal</th><th class="sub">Akhir</th><th class="sub">Total</th><th class="sub">Rata/Jam</th><th class="sub">KET</th>
                  <th style="border:none;background:#f0f0f0"></th>
                  <th class="sub">Tanggal</th><th class="sub">Awal</th><th class="sub">Akhir</th><th class="sub">Total</th><th class="sub">Rata/Jam</th><th class="sub">KET</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="13">Tidak ada data pada rentang tanggal ini.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr>
                    <td class="date"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['air_awal_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['air_akhir_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['air_total_pemakaian_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['air_rata_rata_per_jam_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars((string)($r['air_ket'] ?? '')) ?></td>
                    <td style="border:none;background:#f0f0f0"></td>
                    <td class="date"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['steam_awal_ton'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['steam_akhir_ton'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['steam_total_pemakaian_ton'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['steam_rata_rata_per_jam_ton'])) ?></td>
                    <td class="data"><?= htmlspecialchars((string)($r['steam_ket'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                  <tr class="sum">
                    <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totAir['air_awal_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($totAir['air_akhir_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($totAir['air_total_pemakaian_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($totAir['air_rata_rata_per_jam_m3'])) ?></td><td></td>
                    <td style="border:none;background:#f0f0f0"></td>
                    <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totSteam['steam_awal_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($totSteam['steam_akhir_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($totSteam['steam_total_pemakaian_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($totSteam['steam_rata_rata_per_jam_ton'])) ?></td><td></td>
                  </tr>
                  <tr class="sum">
                    <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgAir['air_awal_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($avgAir['air_akhir_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($avgAir['air_total_pemakaian_m3'])) ?></td><td><?= htmlspecialchars($fmtNum($avgAir['air_rata_rata_per_jam_m3'])) ?></td><td></td>
                    <td style="border:none;background:#f0f0f0"></td>
                    <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_awal_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_akhir_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_total_pemakaian_ton'])) ?></td><td><?= htmlspecialchars($fmtNum($avgSteam['steam_rata_rata_per_jam_ton'])) ?></td><td></td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div></section>
</div>

<div class="modal fade" id="modalCatatan20TonLama" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan 20 Ton Lama</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body"><div class="mb-2 text-muted" id="catatanRangeInfo"></div>
      <div class="table-responsive"><table class="table table-sm table-bordered"><thead class="thead-light"><tr><th style="width:130px;">Tanggal</th><th>Catatan</th><th style="width:120px;">Created By</th></tr></thead><tbody id="catatanBody"><tr><td colspan="3">Memuat catatan...</td></tr></tbody></table></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
  </div></div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function(){
  $('#btnLihatCatatan').on('click', function(){
    var s=$('#start_date').val(), e=$('#end_date').val();
    if(!s||!e){ Swal.fire({icon:'warning',title:'Perhatian',text:'Pilih rentang tanggal terlebih dahulu.'}); return; }
    $('#catatanRangeInfo').text('Rentang: '+s+' s/d '+e);
    $('#catatanBody').html('<tr><td colspan="3">Memuat catatan...</td></tr>');
    $('#modalCatatan20TonLama').modal('show');
    $.post('get_catatan_20tonlama.php',{start_date:s,end_date:e},function(resp){
      if(!resp||!resp.success){ $('#catatanBody').html('<tr><td colspan="3">'+((resp&&resp.message)?resp.message:'Gagal memuat catatan.')+'</td></tr>'); return; }
      if(!resp.data||resp.data.length===0){ $('#catatanBody').html('<tr><td colspan="3">Tidak ada catatan.</td></tr>'); return; }
      var rows='';
      resp.data.forEach(function(i){
        var safe=$('<div>').text(i.catatan||'').html().replace(/\n/g,'<br>');
        rows += '<tr><td>'+ (i.tanggal||'-') +'</td><td>'+safe+'</td><td>'+(i.creatby||'-')+'</td></tr>';
      });
      $('#catatanBody').html(rows);
    },'json').fail(function(){ $('#catatanBody').html('<tr><td colspan="3">Terjadi kesalahan saat memuat catatan.</td></tr>'); });
  });
});
</script>

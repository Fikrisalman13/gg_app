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
            FROM dbo.perblerange1_harian
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

$keys = [
    'meter_awal_m3','meter_akhir_m3','total_pemakaian_m3',
    'meter_awal_debit_m3','meter_akhir_debit_m3','total_pemakaian_debit_m3',
    'operasional_mc_pbr1_jam','pemakaian_rata_per_jam_m3',
    'jumlah_debit_m3','operasional_mc_pbr1_debit_jam','pemakaian_rata_per_jam_debit_m3'
];
$tot = array_fill_keys($keys, 0);
foreach ($rows as $r) { foreach ($keys as $k) $tot[$k] += (float)($r[$k] ?? 0); }
$rowCount = count($rows);
$avg = []; foreach ($keys as $k) $avg[$k] = $rowCount > 0 ? ($tot[$k] / $rowCount) : 0;
$hasRows = $rowCount > 0;

$fmtNum = function ($val, $dec = 2) { if (!is_numeric($val)) return ''; return number_format((float)$val, $dec, '.', ','); };
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$leftKeys = ['meter_awal_m3','meter_akhir_m3','total_pemakaian_m3','operasional_mc_pbr1_jam','pemakaian_rata_per_jam_m3'];
$rightKeys = ['meter_awal_debit_m3','meter_akhir_debit_m3','total_pemakaian_debit_m3','jumlah_debit_m3','operasional_mc_pbr1_debit_jam','pemakaian_rata_per_jam_debit_m3'];
?>

<div class="content-wrapper">
  <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">REPORT METER PERBLE RANGE 1</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/perblerange1/perblerange1.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
          <form method="post" action="export_excel_report_perblerange1.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
          </form>
          <form method="post" action="export_pdf_report_perblerange1.php" class="m-0 p-0">
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
          .pbr1-wrap{overflow-x:auto;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
          .pbr1-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:760px;background:#fff;width:100%}
          .pbr1-report th,.pbr1-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:3px 6px;white-space:nowrap}
          .pbr1-report .head{background:#b7cae2;font-weight:700}
          .pbr1-report .sub{background:#f1dddd;font-weight:700}
          .pbr1-report .unit{background:#f8f8f8;font-size:10px;font-weight:700}
          .pbr1-report .date{background:#e6edd9;font-weight:700}
          .pbr1-report .data{background:#c4d5e9}
          .pbr1-report .sum td{background:#ece9d6 !important;font-weight:700}
        </style>
        <div class="card-body p-2">
          <div class="pbr1-wrap">
            <table class="table table-sm pbr1-report">
              <thead>
                <tr><th class="head" colspan="8">METER PERBLE RANGE 1</th></tr>
                <tr><th class="head" colspan="8"><?= htmlspecialchars($monthLabel) ?></th></tr>
                <tr>
                  <th class="sub">Tanggal</th>
                  <th class="sub">Awal</th>
                  <th class="sub">Akhir</th>
                  <th class="sub">Total Pemakaian</th>
                  <th class="sub">Jumlah Debit</th>
                  <th class="sub">Operasional MC PBR1</th>
                  <th class="sub">Pemakaian Rata/Jam</th>
                  <th class="sub">KET MC Yang Jalan</th>
                </tr>
                <tr>
                  <th class="unit"></th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">m3</th>
                  <th class="unit">jam</th>
                  <th class="unit">m3</th>
                  <th class="unit">PBR1/PBR2</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$hasRows): ?>
                  <tr><td colspan="8">Tidak ada data pada rentang tanggal ini.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr>
                    <td class="date"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['meter_awal_debit_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['meter_akhir_debit_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['total_pemakaian_debit_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['jumlah_debit_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['operasional_mc_pbr1_debit_jam'])) ?></td>
                    <td class="data"><?= htmlspecialchars($fmtNum($r['pemakaian_rata_per_jam_debit_m3'])) ?></td>
                    <td class="data"><?= htmlspecialchars((string)($r['ket_mc_yang_jalan'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                  <tr class="sum">
                    <td>TOTAL</td>
                    <?php foreach ($rightKeys as $k): ?><td><?= htmlspecialchars($fmtNum($tot[$k])) ?></td><?php endforeach; ?>
                    <td></td>
                  </tr>
                  <tr class="sum">
                    <td>RATA-RATA</td>
                    <?php foreach ($rightKeys as $k): ?><td><?= htmlspecialchars($fmtNum($avg[$k])) ?></td><?php endforeach; ?>
                    <td></td>
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

<div class="modal fade" id="modalCatatanPbr1" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan Perble Range 1</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
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
    $('#modalCatatanPbr1').modal('show');
    $.post('get_catatan_perblerange1.php',{start_date:s,end_date:e},function(resp){
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

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
            FROM dbo.listrik_perbagian_harian
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
    'kwh_pln','kwh_utility_amp_acb','kwh_utility_kwh_hari','df_amp_acb','df_kwh_hari',
    'weaving1_amp_acb','weaving1_kwh_hari','weaving2_amp_acb','weaving2_kwh_hari',
    'jumlah_kwh_hari','jumlah_ampere','kwh_per_jam','efisiensi_persen'
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
        <h1 class="m-0">REPORT LISTRIK PER BAGIAN</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/listrik_perbagian/listrik_perbagian.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
          <form method="post" action="export_excel_report_listrik_perbagian.php" class="m-0 p-0">
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
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-list mr-1"></i> Detail Report</h3>
          <button type="button" class="btn btn-light btn-sm ml-auto" id="btnLihatCatatan"><i class="fas fa-sticky-note"></i> Lihat Catatan</button>
        </div>
        <style>
          .lpb-wrap{overflow-x:auto;overflow-y:visible;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
          .lpb-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:1600px;background:#fff}
          .lpb-report th,.lpb-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
          .lpb-report .title{background:#b7c9de;font-weight:700;font-size:34px;color:#000}
          .lpb-report .datecol{background:#e3e3e3;font-weight:700}
          .lpb-report .kwh{background:#e7b07c;font-weight:700}
          .lpb-report .utility{background:#f0f0f0;font-weight:700}
          .lpb-report .df{background:#c9d6e6;font-weight:700}
          .lpb-report .w1{background:#ead9d9;font-weight:700}
          .lpb-report .w2{background:#ead9d9;font-weight:700}
          .lpb-report .jml{background:#f0dfdf;font-weight:700}
          .lpb-report .sub{background:#fafafa;font-weight:700}
          .lpb-report .data-blue{background:#c2d4e7}
          .lpb-report .data-yellow{background:#ffff00}
          .lpb-report .sum td{background:#efd2b6 !important;font-weight:700}
        </style>
        <div class="card-body p-2">
          <div class="lpb-wrap"><table class="table table-sm lpb-report">
            <thead>
              <tr><th class="title" colspan="14">PEMAKAIAN KWH METER PLN BULAN <?= htmlspecialchars($monthLabel) ?></th></tr>
              <tr>
                <th class="datecol" rowspan="2">TGL</th>
                <th class="kwh" rowspan="2">KWH PLN<br>(KWH)</th>
                <th class="utility" colspan="2">KWH UTILITY</th>
                <th class="df" colspan="2">DF</th>
                <th class="w1" colspan="2">WEAVING 1</th>
                <th class="w2" colspan="2">WEAVING 2</th>
                <th class="jml" colspan="1">JUMLAH</th>
                <th class="jml" colspan="1">JUMLAH AMPERE</th>
                <th class="jml" colspan="1">KWH/JAM</th>
                <th class="jml" colspan="1">EFESIENSI</th>
              </tr>
              <tr>
                <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                <th class="sub">AMP ACB</th><th class="sub">Kwh/hari</th>
                <th class="sub">Kwh/hari</th>
                <th class="sub">AMPERE</th>
                <th class="sub">Kwh/JAM</th>
                <th class="sub">PERSEN</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="14">Tidak ada data pada rentang tanggal ini.</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td class="datecol"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
                  <td class="data-blue"><?= htmlspecialchars($fmtNum($r['kwh_pln'])) ?></td>
                  <td class="data-blue"><?= htmlspecialchars($fmtNum($r['kwh_utility_amp_acb'])) ?></td>
                  <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['kwh_utility_kwh_hari'])) ?></td>
                  <td class="data-blue"><?= htmlspecialchars($fmtNum($r['df_amp_acb'])) ?></td>
                  <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['df_kwh_hari'])) ?></td>
                  <td class="data-blue"><?= htmlspecialchars($fmtNum($r['weaving1_amp_acb'])) ?></td>
                  <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['weaving1_kwh_hari'])) ?></td>
                  <td class="data-blue"><?= htmlspecialchars($fmtNum($r['weaving2_amp_acb'])) ?></td>
                  <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['weaving2_kwh_hari'])) ?></td>
                  <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['jumlah_kwh_hari'])) ?></td>
                  <td class="data-blue"><?= htmlspecialchars($fmtNum($r['jumlah_ampere'])) ?></td>
                  <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['kwh_per_jam'])) ?></td>
                  <td class="data-blue"><?= htmlspecialchars($fmtNum($r['efisiensi_persen'])) ?></td>
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
          </table></div>
        </div>
      </div>
    <?php endif; ?>
  </div></section>
</div>

<div class="modal fade" id="modalCatatanListrikPerBagian" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan Listrik Per Bagian</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
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
    $('#modalCatatanListrikPerBagian').modal('show');
    $.post('get_catatan_listrik_perbagian.php',{start_date:s,end_date:e},function(resp){
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

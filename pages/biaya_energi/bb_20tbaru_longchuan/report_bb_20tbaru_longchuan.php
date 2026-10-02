<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
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
    if ($startInput === '' || $endInput === '') {
        $errorMsg = 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.';
    } elseif (strtotime($startInput) > strtotime($endInput)) {
        $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    } else {
        $start = $startInput;
        $end = $endInput;
    }
}

$rows = [];
if ($errorMsg === '') {
    $sql = "SELECT CAST(tanggal AS DATE) AS tanggal,
                   pemakaian_kg,
                   harga_rp_per_kg,
                   biaya_rp,
                   total_biaya_boiler_rp,
                   extractor_kg,
                   cgrate_kg,
                   fly_ash_kg,
                   total_kg,
                   ket
            FROM dbo.bb_20tbaru_longchuan_harian
            WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(tanggal AS DATE) ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data report: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateObj = $r['tanggal'] ?? null;
            if ($dateObj instanceof DateTime) $dateKey = $dateObj->format('Y-m-d');
            else $dateKey = date('Y-m-d', strtotime((string)$dateObj));

            $rows[] = [
                'tanggal' => $dateKey,
                'pemakaian_kg' => is_numeric($r['pemakaian_kg']) ? (float)$r['pemakaian_kg'] : 0,
                'harga_rp_per_kg' => is_numeric($r['harga_rp_per_kg']) ? (float)$r['harga_rp_per_kg'] : 0,
                'biaya_rp' => is_numeric($r['biaya_rp']) ? (float)$r['biaya_rp'] : 0,
                'total_biaya_boiler_rp' => is_numeric($r['total_biaya_boiler_rp']) ? (float)$r['total_biaya_boiler_rp'] : 0,
                'extractor_kg' => is_numeric($r['extractor_kg']) ? (float)$r['extractor_kg'] : 0,
                'cgrate_kg' => is_numeric($r['cgrate_kg']) ? (float)$r['cgrate_kg'] : 0,
                'fly_ash_kg' => is_numeric($r['fly_ash_kg']) ? (float)$r['fly_ash_kg'] : 0,
                'total_kg' => is_numeric($r['total_kg']) ? (float)$r['total_kg'] : 0,
                'ket' => (string)($r['ket'] ?? ''),
            ];
        }
        sqlsrv_free_stmt($stmt);
    }
}

$fmtNum = function ($val, $dec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$tot = [
    'pemakaian_kg' => 0,
    'biaya_rp' => 0,
    'total_biaya_boiler_rp' => 0,
    'extractor_kg' => 0,
    'cgrate_kg' => 0,
    'fly_ash_kg' => 0,
    'total_kg' => 0,
];
foreach ($rows as $r) {
    foreach ($tot as $k => $_) {
        $tot[$k] += (float)($r[$k] ?? 0);
    }
}
$rowCount = count($rows);
$avg = [
    'pemakaian_kg' => $rowCount > 0 ? ($tot['pemakaian_kg'] / $rowCount) : 0,
    'biaya_rp' => $rowCount > 0 ? ($tot['biaya_rp'] / $rowCount) : 0,
    'total_biaya_boiler_rp' => $rowCount > 0 ? ($tot['total_biaya_boiler_rp'] / $rowCount) : 0,
    'extractor_kg' => $rowCount > 0 ? ($tot['extractor_kg'] / $rowCount) : 0,
    'cgrate_kg' => $rowCount > 0 ? ($tot['cgrate_kg'] / $rowCount) : 0,
    'fly_ash_kg' => $rowCount > 0 ? ($tot['fly_ash_kg'] / $rowCount) : 0,
    'total_kg' => $rowCount > 0 ? ($tot['total_kg'] / $rowCount) : 0,
];

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
?>

<div class="content-wrapper">
  <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">REPORT PEMAKAIAN BATU BARA STEAM 20TON BARU, PEMBUANGAN BOTTOM ASH DAN FLY ASH</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/biaya_energi/bb_20tbaru_longchuan/bb_20tbaru_longchuan.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
          <form method="post" action="export_excel_report_bb_20tbaru_longchuan.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
          </form>
          <form method="post" action="export_pdf_report_bb_20tbaru_longchuan.php" class="m-0 p-0" target="_blank">
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
          <button type="button" class="btn btn-light btn-sm ml-auto" id="btnLihatCatatan" style="margin-left:auto;"><i class="fas fa-sticky-note"></i> Lihat Catatan</button>
        </div>

        <style>
          .xin-wrap{overflow-x:auto;overflow-y:visible;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
          .xin-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:1200px;background:#fff}
          .xin-report th,.xin-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
          .xin-report .title{background:#76923c;font-weight:700;font-size:28px;line-height:1.05;color:#000}
          .xin-report .month{background:#ffffff;font-weight:700;font-size:24px;text-align:left}
          .xin-report .monthLabel{background:#ffffff;font-weight:700;font-size:24px;text-align:left}
          .xin-report .datecol{background:#e6efd5;font-weight:700;min-width:80px}
          .xin-report .sec{background:#9bc2d1;font-weight:700}
          .xin-report .sec-yellow{background:#ffc000;font-weight:700}
          .xin-report .sub{background:#9bc2d1;font-weight:700}
          .xin-report .unit{background:#cfeaf6;font-weight:700}
          .xin-report .data{background:#d8d5b8}
          .xin-report .boil{background:#ffc000}
          .xin-report .tot{background:#ffc000;font-weight:700}
          .xin-report .sum{background:#ffd24d;font-weight:700}
          .xin-report .include{background:#fff200;font-weight:700}
          .xin-report .ket{font-weight:700;font-size:11px}
        </style>

        <div class="card-body p-2">
          <div class="xin-wrap">
            <table class="table table-sm xin-report">
              <thead>
                <tr><th class="title" colspan="10">PEMAKAIAN BATU BARA STEAM 20TON BARU, PEMBUANGAN BOTTOM ASH DAN FLY ASH</th></tr>
                <tr>
                  <th class="month">Bulan :</th>
                  <th class="monthLabel" colspan="9"><?= htmlspecialchars($monthLabel) ?></th>
                </tr>
                <tr>
                  <th class="datecol" rowspan="3">Tanggal</th>
                  <th class="sec" colspan="3">BATU BARA ADB 5600-5800 0-200 MM ASALAN</th>
                  <th class="sec-yellow" rowspan="3">TOTAL BIAYA<br>BOILER (Rp)</th>
                  <th class="sec" colspan="3">BOTTOM ASH</th>
                  <th class="sec" rowspan="2">TOTAL</th>
                  <th class="sec" rowspan="2">KET</th>
                </tr>
                <tr>
                  <th class="sub">PEMAKAIAN</th>
                  <th class="sub">HARGA/KG</th>
                  <th class="sub">BIAYA</th>
                  <th class="sub">EXTRACTOR</th>
                  <th class="sub">C/GRATE</th>
                  <th class="sub">FLY ASH</th>
                </tr>
                <tr>
                  <th class="unit">(KG)</th>
                  <th class="unit">(Rp)</th>
                  <th class="unit">(Rp)</th>
                  <th class="unit">KG</th>
                  <th class="unit">KG</th>
                  <th class="unit">KG</th>
                  <th class="unit">KG</th>
                  <th class="unit"></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="10">Tidak ada data pada rentang tanggal ini.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td class="datecol"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['pemakaian_kg'], 2)) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['harga_rp_per_kg'], 2)) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['biaya_rp'], 2)) ?></td>
                      <td class="boil"><?= htmlspecialchars($fmtNum($r['total_biaya_boiler_rp'], 2)) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['extractor_kg'], 2)) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['cgrate_kg'], 2)) ?></td>
                      <td class="data"><?= htmlspecialchars($fmtNum($r['fly_ash_kg'], 2)) ?></td>
                      <td class="tot"><?= htmlspecialchars($fmtNum($r['total_kg'], 2)) ?></td>
                      <td class="ket"><?= htmlspecialchars($r['ket']) ?></td>
                    </tr>
                  <?php endforeach; ?>

                  <tr class="sum">
                    <td>TOTAL</td>
                    <td><?= htmlspecialchars($fmtNum($tot['pemakaian_kg'], 2)) ?></td>
                    <td></td>
                    <td><?= htmlspecialchars($fmtNum($tot['biaya_rp'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['total_biaya_boiler_rp'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['extractor_kg'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['cgrate_kg'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['fly_ash_kg'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['total_kg'], 2)) ?></td>
                    <td></td>
                  </tr>

                  <tr class="sum">
                    <td>RATA-RATA</td>
                    <td><?= htmlspecialchars($fmtNum($avg['pemakaian_kg'], 2)) ?></td>
                    <td></td>
                    <td><?= htmlspecialchars($fmtNum($avg['biaya_rp'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['total_biaya_boiler_rp'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['extractor_kg'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['cgrate_kg'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['fly_ash_kg'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['total_kg'], 2)) ?></td>
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

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<div class="modal fade" id="modalCatatanBbLongchuan" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan BB Boiler 20T Baru Longchuan</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body"><div class="mb-2 text-muted" id="catatanRangeInfo"></div>
      <div class="table-responsive"><table class="table table-sm table-bordered"><thead class="thead-light"><tr><th style="width:130px;">Tanggal</th><th>Catatan</th><th style="width:120px;">Created By</th></tr></thead><tbody id="catatanBody"><tr><td colspan="3">Memuat catatan...</td></tr></tbody></table></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
  </div></div>
</div>

<script>
$(function(){
  $('#btnLihatCatatan').on('click', function(){
    var s=$('#start_date').val(), e=$('#end_date').val();
    if(!s||!e){ Swal.fire({icon:'warning',title:'Perhatian',text:'Pilih rentang tanggal terlebih dahulu.'}); return; }
    $('#catatanRangeInfo').text('Rentang: '+s+' s/d '+e);
    $('#catatanBody').html('<tr><td colspan="3">Memuat catatan...</td></tr>');
    $('#modalCatatanBbLongchuan').modal('show');
    $.post('get_catatan_bb_20tbaru_longchuan.php',{start_date:s,end_date:e},function(resp){
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





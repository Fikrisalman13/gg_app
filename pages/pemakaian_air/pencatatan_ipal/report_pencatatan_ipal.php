<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
$shift = trim($_GET['shift'] ?? '');

$where = "WHERE CAST(x.tanggal AS DATE) BETWEEN ? AND ?";
$params = [$startDate, $endDate];
if ($shift !== '') { $where .= " AND x.shift_kode = ?"; $params[] = $shift; }

$sql = "SELECT x.tanggal, x.shift_kode,
               x.sv30_aerasi1_pct, x.sv30_aerasi2_pct, x.sv30_aerasi3_pct, x.sv30_aerasi4_pct,
               x.ph_equal, x.ph_akhir,
               x.dewatering_bawah_per_day, x.dewatering_atas_sinci1_per_day_ton, x.sinci2_per_day_ton
        FROM dbo.pencatatan_ipal_harian x
        $where
        ORDER BY CAST(x.tanggal AS DATE) ASC,
                 CASE x.shift_kode WHEN 'P' THEN 1 WHEN 'S' THEN 2 WHEN 'M' THEN 3 ELSE 4 END ASC";
$stmt = sqlsrv_query($conn, $sql, $params);
$rows = [];
if ($stmt) { while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $rows[] = $r; sqlsrv_free_stmt($stmt); }

$fmtNum = function($v){ return number_format((float)$v,2,'.',','); };
$sum = ['sv1'=>0,'sv2'=>0,'sv3'=>0,'sv4'=>0,'ph1'=>0,'ph2'=>0,'db1'=>0,'db2'=>0,'db3'=>0];
foreach ($rows as $r) {
    $sum['sv1'] += (float)$r['sv30_aerasi1_pct']; $sum['sv2'] += (float)$r['sv30_aerasi2_pct']; $sum['sv3'] += (float)$r['sv30_aerasi3_pct']; $sum['sv4'] += (float)$r['sv30_aerasi4_pct'];
    $sum['ph1'] += (float)$r['ph_equal']; $sum['ph2'] += (float)$r['ph_akhir'];
    $sum['db1'] += (float)$r['dewatering_bawah_per_day']; $sum['db2'] += (float)$r['dewatering_atas_sinci1_per_day_ton']; $sum['db3'] += (float)$r['sinci2_per_day_ton'];
}
$count = count($rows);

$grouped = [];
foreach ($rows as $r) {
    $d = $r['tanggal'] instanceof DateTime ? $r['tanggal']->format('Y-m-d') : date('Y-m-d', strtotime((string)$r['tanggal']));
    if (!isset($grouped[$d])) $grouped[$d] = [];
    $grouped[$d][] = $r;
}
$shiftOrder = ['P' => 1, 'S' => 2, 'M' => 3];
foreach ($grouped as $d => $items) {
    usort($items, function($a, $b) use ($shiftOrder){
        $sa = strtoupper((string)($a['shift_kode'] ?? ''));
        $sb = strtoupper((string)($b['shift_kode'] ?? ''));
        return ($shiftOrder[$sa] ?? 9) <=> ($shiftOrder[$sb] ?? 9);
    });
    $grouped[$d] = $items;
}

$bulanLabel = strtoupper(strftime('%B %Y', strtotime($startDate)));
?>
<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">REPORT PENCATATAN IPAL</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/pencatatan_ipal/pencatatan_ipal.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      </div>
    </div>
  </div>
</section>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-<?= htmlspecialchars($themeColor) ?>">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title"><i class="fas fa-filter"></i> Filter Rentang Tanggal</h3>
        </div>
        <div class="card-body">
          <form method="GET" class="form-inline">
            <label class="mr-2">Dari Tanggal</label>
            <input type="date" name="start_date" class="form-control mr-3" value="<?= htmlspecialchars($startDate) ?>">
            <label class="mr-2">Sampai Tanggal</label>
            <input type="date" name="end_date" class="form-control mr-3" value="<?= htmlspecialchars($endDate) ?>">
            <label class="mr-2">Shift</label>
            <select name="shift" class="form-control mr-3">
              <option value="">Semua</option>
              <option value="P" <?= $shift==='P'?'selected':'' ?>>P</option>
              <option value="S" <?= $shift==='S'?'selected':'' ?>>S</option>
              <option value="M" <?= $shift==='M'?'selected':'' ?>>M</option>
            </select>
            <button class="btn btn-success btn-sm mr-2"><i class="fas fa-search"></i> Proses</button>
            <a href="report_pencatatan_ipal.php" class="btn btn-secondary btn-sm mr-2"><i class="fas fa-undo"></i> Reset</a>
            <a href="export_excel_report_pencatatan_ipal.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&shift=<?= urlencode($shift) ?>" class="btn btn-primary btn-sm mr-2"><i class="fas fa-file-excel"></i> Export Excel</a>
            <a href="export_pdf_report_pencatatan_ipal.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&shift=<?= urlencode($shift) ?>" class="btn btn-danger btn-sm mr-2"><i class="fas fa-file-pdf"></i> Export PDF</a>
            <button type="button" class="btn btn-info btn-sm" id="btnLihatCatatan"><i class="fas fa-sticky-note"></i> Lihat Catatan</button>
          </form>
        </div>
      </div>

      <div class="card card-<?= htmlspecialchars($themeColor) ?>">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0"><i class="fas fa-th-list mr-1"></i> Detail Report</h3>
        </div>
        <div class="card-body table-responsive p-2">
          <style>
            .ipal-report{border-collapse:collapse;width:100%;min-width:1200px}
            .ipal-report th,.ipal-report td{border:1px solid #1f1f1f;padding:3px 6px;text-align:center;vertical-align:middle}
            .ipal-title{background:#d6e1bf;font-size:24px;font-weight:700}
            .ipal-subtitle{background:#dfe8d2;font-size:18px;font-weight:700}
            .ipal-hdr{background:#d9d5c2;font-weight:700}
            .ipal-hdr-sv{background:#d9d5c2;font-weight:700}
            .ipal-hdr-ph{background:#d9d5c2;font-weight:700}
            .ipal-hdr-green{background:#d9d5c2;font-weight:700}
            .ipal-unit{background:#efe8dc;font-size:11px;font-weight:700}
            .col-tgl{background:#e4ead9;font-weight:700}
            .col-shift{background:#e4ead9;font-weight:700}
            .col-sv{background:#f2e4d8}
            .col-ph{background:#c8d9ec}
            .col-green{background:#95cf52}
            .row-total{background:#f4d85e;font-weight:700}
            .row-rata{background:#ece5c4;font-weight:700}
            .left-label{text-align:left;font-weight:700}
          </style>

          <table class="ipal-report">
            <thead>
              <tr><th class="ipal-title" colspan="11">PENCATATAN SV30, PH, SLUDGE IPAL</th></tr>
              <tr><th class="ipal-subtitle left-label" colspan="11">Bulan : <?= htmlspecialchars($bulanLabel) ?></th></tr>
              <tr>
                <th rowspan="3" class="ipal-hdr">TGL</th>
                <th rowspan="3" class="ipal-hdr">SHIFT</th>
                <th class="ipal-hdr-sv">SV30</th>
                <th class="ipal-hdr-sv">SV30</th>
                <th class="ipal-hdr-sv">SV30</th>
                <th class="ipal-hdr-sv">SV30</th>
                <th class="ipal-hdr-ph">PH</th>
                <th class="ipal-hdr-ph">PH</th>
                <th class="ipal-hdr-green">DEWATERING BAWAH</th>
                <th class="ipal-hdr-green">DEWATERING ATAS DAN SINCI 1</th>
                <th class="ipal-hdr-green">SINCI 2</th>
              </tr>
              <tr>
                <th class="ipal-unit">(%)</th>
                <th class="ipal-unit">(%)</th>
                <th class="ipal-unit">(%)</th>
                <th class="ipal-unit">(%)</th>
                <th class="ipal-unit">Equal</th>
                <th class="ipal-unit">Akhir</th>
                <th class="ipal-unit">Per Day</th>
                <th class="ipal-unit">Per Day/Ton</th>
                <th class="ipal-unit">Per Day/Ton</th>
              </tr>
              <tr>
                <th class="ipal-unit">AERASI 1</th>
                <th class="ipal-unit">AERASI 2</th>
                <th class="ipal-unit">AERASI 3</th>
                <th class="ipal-unit">AERASI 4</th>
                <th class="ipal-unit">-</th>
                <th class="ipal-unit">-</th>
                <th class="ipal-unit">&nbsp;</th>
                <th class="ipal-unit">&nbsp;</th>
                <th class="ipal-unit">&nbsp;</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($grouped)): ?>
                <tr><td colspan="11">Tidak ada data.</td></tr>
              <?php else: ?>
                <?php foreach ($grouped as $dateYmd => $items): ?>
                  <?php $rowspan = count($items); $dayNum = (int)date('j', strtotime($dateYmd)); $i = 0; ?>
                  <?php foreach ($items as $r): ?>
                    <tr>
                      <?php if ($i === 0): ?>
                        <td class="col-tgl" rowspan="<?= $rowspan ?>"><?= htmlspecialchars((string)$dayNum) ?></td>
                      <?php endif; ?>
                      <td class="col-shift"><?= htmlspecialchars((string)$r['shift_kode']) ?></td>
                      <td class="col-sv"><?= htmlspecialchars(number_format((float)$r['sv30_aerasi1_pct'],0,'.',',')) ?></td>
                      <td class="col-sv"><?= htmlspecialchars(number_format((float)$r['sv30_aerasi2_pct'],0,'.',',')) ?></td>
                      <td class="col-sv"><?= htmlspecialchars(number_format((float)$r['sv30_aerasi3_pct'],0,'.',',')) ?></td>
                      <td class="col-sv"><?= htmlspecialchars(number_format((float)$r['sv30_aerasi4_pct'],0,'.',',')) ?></td>
                      <td class="col-ph"><?= htmlspecialchars($fmtNum($r['ph_equal'])) ?></td>
                      <td class="col-ph"><?= htmlspecialchars($fmtNum($r['ph_akhir'])) ?></td>
                      <td class="col-green"><?= htmlspecialchars(number_format((float)$r['dewatering_bawah_per_day'],0,'.',',')) ?></td>
                      <td class="col-green"><?= htmlspecialchars(number_format((float)$r['dewatering_atas_sinci1_per_day_ton'],0,'.',',')) ?></td>
                      <td class="col-green"><?= htmlspecialchars(number_format((float)$r['sinci2_per_day_ton'],0,'.',',')) ?></td>
                    </tr>
                    <?php $i++; ?>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              <?php endif; ?>

              <?php if ($count > 0): ?>
                <tr class="row-total">
                  <td colspan="2">TOTAL</td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv1'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv2'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv3'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv4'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['ph1'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['ph2'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['db1'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['db2'])) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['db3'])) ?></td>
                </tr>
                <tr class="row-rata">
                  <td colspan="2">RATA-RATA</td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv1']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv2']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv3']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['sv4']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['ph1']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['ph2']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['db1']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['db2']/$count)) ?></td>
                  <td><?= htmlspecialchars($fmtNum($sum['db3']/$count)) ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="modalCatatanIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Catatan Pencatatan IPAL</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body table-responsive">
        <table class="table table-bordered table-sm mb-0">
          <thead><tr><th width="120">Tanggal</th><th>Catatan</th><th width="140">By</th></tr></thead>
          <tbody id="catatanBody"><tr><td colspan="3" class="text-center text-muted">Belum ada data.</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function(){
  $('#btnLihatCatatan').on('click', function(){
    var s = $('input[name="start_date"]').val() || '';
    var e = $('input[name="end_date"]').val() || '';
    $.post('get_catatan_pencatatan_ipal.php', {start_date:s, end_date:e}, function(resp){
      if(!(resp && resp.success)){ Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal mengambil catatan.'}); return; }
      var rows = resp.data || [];
      if(rows.length===0){ $('#catatanBody').html('<tr><td colspan="3" class="text-center text-muted">Belum ada catatan pada rentang ini.</td></tr>'); }
      else {
        var html = '';
        rows.forEach(function(r){
          html += '<tr><td>'+ (r.tanggal||'-') +'</td><td>'+ $('<div>').text(r.note||'').html() +'</td><td>'+ (r.by||'-') +'</td></tr>';
        });
        $('#catatanBody').html(html);
      }
      $('#modalCatatanIpal').modal('show');
    }, 'json').fail(function(){
      Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat mengambil catatan.'});
    });
  });
});
</script>

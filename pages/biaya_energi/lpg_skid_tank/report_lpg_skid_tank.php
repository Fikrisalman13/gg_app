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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = trim($_POST['start_date'] ?? $start);
    $end = trim($_POST['end_date'] ?? $end);
    if ($start === '' || $end === '') {
        $errorMsg = 'Tanggal wajib diisi.';
    } elseif (strtotime($start) > strtotime($end)) {
        $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    }
}

$tanks = [];
$stTank = sqlsrv_query($conn, "SELECT kode,nama,urutan FROM dbo.lpg_skid_tank_master WHERE is_active=1 ORDER BY urutan,kode");
if ($stTank) {
    $idx = 1;
    while ($t = sqlsrv_fetch_array($stTank, SQLSRV_FETCH_ASSOC)) {
        $urutan = isset($t['urutan']) ? (int)$t['urutan'] : $idx;
        if ($urutan <= 0) $urutan = $idx;
        $t['urutan'] = $urutan;
        $t['display_label'] = 'Pencatatan Pemakaian ' . $urutan;
        $tanks[] = $t;
        $idx++;
    }
    sqlsrv_free_stmt($stTank);
}

$rows = [];
if ($errorMsg === '') {
    $sql = "SELECT CAST(h.tanggal AS DATE) AS tanggal,h.tank_kode,m.nama AS tank_nama,m.urutan,h.pemakaian_kg,h.harga_rp,h.biaya_rp,h.ket
            FROM dbo.lpg_skid_tank_harian h
            INNER JOIN dbo.lpg_skid_tank_master m ON m.kode=h.tank_kode
            WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(h.tanggal AS DATE) ASC, m.urutan ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data report.';
    } else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateObj = $r['tanggal'] ?? null;
            $dateKey = ($dateObj instanceof DateTime) ? $dateObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$dateObj));
            $rows[] = [
                'tanggal' => $dateKey,
                'tank_kode' => (string)($r['tank_kode'] ?? ''),
                'tank_nama' => (string)($r['tank_nama'] ?? ''),
                'urutan' => (int)($r['urutan'] ?? 0),
                'pemakaian_kg' => (float)($r['pemakaian_kg'] ?? 0),
                'harga_rp' => (float)($r['harga_rp'] ?? 0),
                'biaya_rp' => (float)($r['biaya_rp'] ?? 0),
                'ket' => (string)($r['ket'] ?? ''),
            ];
        }
        sqlsrv_free_stmt($stmt);
    }
}

$panelByUrutan = [];
foreach ($tanks as $t) {
    $u = (int)$t['urutan'];
    if ($u < 1 || $u > 4) continue;
    $panelByUrutan[$u] = $t;
}
for ($i = 1; $i <= 4; $i++) {
    if (!isset($panelByUrutan[$i])) {
        $panelByUrutan[$i] = ['kode' => '', 'nama' => '-', 'urutan' => $i, 'display_label' => 'Pencatatan Pemakaian ' . $i];
    }
}
ksort($panelByUrutan);

$map = [];
$dates = [];
$dailyTotal = [];
$totPem = [1=>0,2=>0,3=>0,4=>0];
$totBiaya = [1=>0,2=>0,3=>0,4=>0];
$countPerPanel = [1=>0,2=>0,3=>0,4=>0];

foreach ($rows as $r) {
    $u = (int)$r['urutan'];
    if ($u < 1 || $u > 4) continue;
    $d = $r['tanggal'];
    if (!isset($map[$d])) $map[$d] = [];
    $map[$d][$u] = $r;
    $dates[$d] = true;
    if (!isset($dailyTotal[$d])) $dailyTotal[$d] = 0;
    $dailyTotal[$d] += $r['biaya_rp'];

    $totPem[$u] += $r['pemakaian_kg'];
    $totBiaya[$u] += $r['biaya_rp'];
    $countPerPanel[$u]++;
}

$dates = array_keys($dates);
sort($dates);

$avgPem = [];
$avgBiaya = [];
for ($i = 1; $i <= 4; $i++) {
    $avgPem[$i] = $countPerPanel[$i] > 0 ? ($totPem[$i] / $countPerPanel[$i]) : 0;
    $avgBiaya[$i] = $countPerPanel[$i] > 0 ? ($totBiaya[$i] / $countPerPanel[$i]) : 0;
}
$grandTotal = array_sum($dailyTotal);
$grandAvg = count($dates) > 0 ? ($grandTotal / count($dates)) : 0;

$fmtNum = function ($val, $dec = 2) {
    if (!is_numeric($val)) return '';
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

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
        <h1 class="m-0">REPORT LPG SKID TANK</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/biaya_energi/lpg_skid_tank/lpg_skid_tank.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      </div>
    </div>
  </div>
</section>
  <section class="content"><div class="container-fluid">

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center"><h3 class="card-title m-0"><i class="fas fa-filter"></i> Filter Rentang Tanggal</h3></div>
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
          <form method="post" action="export_excel_report_lpg_skid_tank.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
          </form>
          <form method="post" action="export_pdf_report_lpg_skid_tank.php" class="m-0 p-0" target="_blank">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-danger btn-sm" title="Export PDF"><i class="fas fa-file-pdf"></i></button>
          </form>
          <button type="button" class="btn btn-warning btn-sm" id="btnLihatCatatan"><i class="fas fa-sticky-note"></i> Lihat Catatan</button>
        </div>
      </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
      <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
    <?php else: ?>
      <style>
        .lpg-wrap{overflow-x:auto;overflow-y:visible;background:#efefef;padding:8px;border:1px solid #cfcfcf}
        .lpg-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:2300px;background:#fff}
        .lpg-report th,.lpg-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
        .lpg-report .title{background:#f4b400;font-weight:700;font-size:22px;line-height:1.1;color:#000}
        .lpg-report .month{background:#f4b400;font-weight:700;font-size:18px;text-align:left}
        .lpg-report .sec{background:#f4b400;font-weight:700;font-size:18px}
        .lpg-report .subname{background:#f4b400;font-weight:700;font-size:14px}
        .lpg-report .head{background:#fff200;font-weight:700}
        .lpg-report .data-pakai{background:#ffc000}
        .lpg-report .data-harga{background:#fff200}
        .lpg-report .data-biaya{background:#d9d9d9}
        .lpg-report .data-ket{background:#d9d9d9}
        .lpg-report .total-col{background:#fff200;font-weight:700}
        .lpg-report .sum{background:#dce6f1;font-weight:700}
        .lpg-report .gap{background:#efefef;border:none;min-width:16px}
      </style>

      <div class="card">
        <div class="card-body p-2">
          <div class="lpg-wrap">
            <table class="table table-sm lpg-report mb-0">
              <thead>
                <tr>
                  <th class="title" colspan="5"><?= htmlspecialchars($panelByUrutan[1]['display_label']) ?></th>
                  <th class="gap"></th>
                  <th class="title" colspan="5"><?= htmlspecialchars($panelByUrutan[2]['display_label']) ?></th>
                  <th class="gap"></th>
                  <th class="title" colspan="5"><?= htmlspecialchars($panelByUrutan[3]['display_label']) ?></th>
                  <th class="gap"></th>
                  <th class="title" colspan="5"><?= htmlspecialchars($panelByUrutan[4]['display_label']) ?></th>
                  <th class="gap"></th>
                  <th class="title" colspan="1">TOTAL</th>
                </tr>
                <tr>
                  <th class="subname" colspan="5"><?= htmlspecialchars($panelByUrutan[1]['nama']) ?></th>
                  <th class="gap"></th>
                  <th class="subname" colspan="5"><?= htmlspecialchars($panelByUrutan[2]['nama']) ?></th>
                  <th class="gap"></th>
                  <th class="subname" colspan="5"><?= htmlspecialchars($panelByUrutan[3]['nama']) ?></th>
                  <th class="gap"></th>
                  <th class="subname" colspan="5"><?= htmlspecialchars($panelByUrutan[4]['nama']) ?></th>
                  <th class="gap"></th>
                  <th class="subname" colspan="1"></th>
                </tr>
                <tr>
                  <th class="month" colspan="5">Bulan: <?= htmlspecialchars($monthLabel) ?></th>
                  <th class="gap"></th>
                  <th class="month" colspan="5">Bulan: <?= htmlspecialchars($monthLabel) ?></th>
                  <th class="gap"></th>
                  <th class="month" colspan="5">Bulan: <?= htmlspecialchars($monthLabel) ?></th>
                  <th class="gap"></th>
                  <th class="month" colspan="5">Bulan: <?= htmlspecialchars($monthLabel) ?></th>
                  <th class="gap"></th>
                  <th class="month" colspan="1"></th>
                </tr>
                <tr>
                  <th class="head">TGL</th><th class="head">Pemakaian<br>(KG)</th><th class="head">Harga<br>(Rp)</th><th class="head">Biaya<br>(Rp/hari)</th><th class="head">KET</th>
                  <th class="gap"></th>
                  <th class="head">TGL</th><th class="head">Pemakaian<br>(KG)</th><th class="head">Harga<br>(Rp)</th><th class="head">Biaya<br>(Rp/hari)</th><th class="head">KET</th>
                  <th class="gap"></th>
                  <th class="head">TGL</th><th class="head">Pemakaian<br>(KG)</th><th class="head">Harga<br>(Rp)</th><th class="head">Biaya<br>(Rp/hari)</th><th class="head">KET</th>
                  <th class="gap"></th>
                  <th class="head">TGL</th><th class="head">Pemakaian<br>(KG)</th><th class="head">Harga<br>(Rp)</th><th class="head">Biaya<br>(Rp/hari)</th><th class="head">KET</th>
                  <th class="gap"></th>
                  <th class="head">Biaya<br>(Rp/hari)</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($dates)): ?>
                  <tr><td colspan="29">Tidak ada data pada rentang tanggal ini.</td></tr>
                <?php else: ?>
                  <?php foreach ($dates as $d):
                    $r1 = $map[$d][1] ?? null; $r2 = $map[$d][2] ?? null; $r3 = $map[$d][3] ?? null; $r4 = $map[$d][4] ?? null;
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($fmtDay($d)) ?></td><td class="data-pakai"><?= htmlspecialchars($r1 ? $fmtNum($r1['pemakaian_kg']) : '') ?></td><td class="data-harga"><?= htmlspecialchars($r1 ? $fmtNum($r1['harga_rp']) : '') ?></td><td class="data-biaya"><?= htmlspecialchars($r1 ? $fmtNum($r1['biaya_rp']) : '') ?></td><td class="data-ket"><?= htmlspecialchars($r1['ket'] ?? '') ?></td>
                      <td class="gap"></td>
                      <td><?= htmlspecialchars($fmtDay($d)) ?></td><td class="data-pakai"><?= htmlspecialchars($r2 ? $fmtNum($r2['pemakaian_kg']) : '') ?></td><td class="data-harga"><?= htmlspecialchars($r2 ? $fmtNum($r2['harga_rp']) : '') ?></td><td class="data-biaya"><?= htmlspecialchars($r2 ? $fmtNum($r2['biaya_rp']) : '') ?></td><td class="data-ket"><?= htmlspecialchars($r2['ket'] ?? '') ?></td>
                      <td class="gap"></td>
                      <td><?= htmlspecialchars($fmtDay($d)) ?></td><td class="data-pakai"><?= htmlspecialchars($r3 ? $fmtNum($r3['pemakaian_kg']) : '') ?></td><td class="data-harga"><?= htmlspecialchars($r3 ? $fmtNum($r3['harga_rp']) : '') ?></td><td class="data-biaya"><?= htmlspecialchars($r3 ? $fmtNum($r3['biaya_rp']) : '') ?></td><td class="data-ket"><?= htmlspecialchars($r3['ket'] ?? '') ?></td>
                      <td class="gap"></td>
                      <td><?= htmlspecialchars($fmtDay($d)) ?></td><td class="data-pakai"><?= htmlspecialchars($r4 ? $fmtNum($r4['pemakaian_kg']) : '') ?></td><td class="data-harga"><?= htmlspecialchars($r4 ? $fmtNum($r4['harga_rp']) : '') ?></td><td class="data-biaya"><?= htmlspecialchars($r4 ? $fmtNum($r4['biaya_rp']) : '') ?></td><td class="data-ket"><?= htmlspecialchars($r4['ket'] ?? '') ?></td>
                      <td class="gap"></td>
                      <td class="total-col"><?= htmlspecialchars($fmtNum($dailyTotal[$d] ?? 0)) ?></td>
                    </tr>
                  <?php endforeach; ?>

                  <tr class="sum">
                    <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totPem[1])) ?></td><td></td><td><?= htmlspecialchars($fmtNum($totBiaya[1])) ?></td><td></td>
                    <td class="gap"></td>
                    <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totPem[2])) ?></td><td></td><td><?= htmlspecialchars($fmtNum($totBiaya[2])) ?></td><td></td>
                    <td class="gap"></td>
                    <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totPem[3])) ?></td><td></td><td><?= htmlspecialchars($fmtNum($totBiaya[3])) ?></td><td></td>
                    <td class="gap"></td>
                    <td>TOTAL</td><td><?= htmlspecialchars($fmtNum($totPem[4])) ?></td><td></td><td><?= htmlspecialchars($fmtNum($totBiaya[4])) ?></td><td></td>
                    <td class="gap"></td>
                    <td><?= htmlspecialchars($fmtNum($grandTotal)) ?></td>
                  </tr>

                  <tr class="sum">
                    <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgPem[1])) ?></td><td>INCLUDE</td><td><?= htmlspecialchars($fmtNum($avgBiaya[1])) ?></td><td></td>
                    <td class="gap"></td>
                    <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgPem[2])) ?></td><td>INCLUDE</td><td><?= htmlspecialchars($fmtNum($avgBiaya[2])) ?></td><td></td>
                    <td class="gap"></td>
                    <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgPem[3])) ?></td><td>INCLUDE</td><td><?= htmlspecialchars($fmtNum($avgBiaya[3])) ?></td><td></td>
                    <td class="gap"></td>
                    <td>RATA-RATA</td><td><?= htmlspecialchars($fmtNum($avgPem[4])) ?></td><td>INCLUDE</td><td><?= htmlspecialchars($fmtNum($avgBiaya[4])) ?></td><td></td>
                    <td class="gap"></td>
                    <td><?= htmlspecialchars($fmtNum($grandAvg)) ?></td>
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
<div class="modal fade" id="modalCatatanLpg" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan LPG Skid Tank</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body"><div class="mb-2 text-muted" id="catatanRangeInfo"></div>
      <div class="table-responsive"><table class="table table-sm table-bordered"><thead class="thead-light"><tr><th style="width:110px;">Tanggal</th><th style="width:260px;">Tank</th><th>Catatan</th><th style="width:120px;">Created By</th></tr></thead><tbody id="catatanBody"><tr><td colspan="4">Memuat catatan...</td></tr></tbody></table></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
  </div></div>
</div>

<script>
$(function(){
  $('#btnLihatCatatan').on('click', function(){
    var s=$('#start_date').val(), e=$('#end_date').val();
    if(!s||!e){ Swal.fire({icon:'warning',title:'Perhatian',text:'Pilih rentang tanggal terlebih dahulu.'}); return; }
    $('#catatanRangeInfo').text('Rentang: '+s+' s/d '+e);
    $('#catatanBody').html('<tr><td colspan="4">Memuat catatan...</td></tr>');
    $('#modalCatatanLpg').modal('show');
    $.post('get_catatan_lpg_skid_tank.php',{start_date:s,end_date:e},function(resp){
      if(!resp||!resp.success){ $('#catatanBody').html('<tr><td colspan="4">'+((resp&&resp.message)?resp.message:'Gagal memuat catatan.')+'</td></tr>'); return; }
      if(!resp.data||resp.data.length===0){ $('#catatanBody').html('<tr><td colspan="4">Tidak ada catatan.</td></tr>'); return; }
      var rows='';
      resp.data.forEach(function(i){
        var safe=$('<div>').text(i.catatan||'').html().replace(/\n/g,'<br>');
        rows += '<tr><td>'+ (i.tanggal||'-') +'</td><td>'+(i.tank_nama||'-')+'</td><td>'+safe+'</td><td>'+(i.creatby||'-')+'</td></tr>';
      });
      $('#catatanBody').html(rows);
    },'json').fail(function(){ $('#catatanBody').html('<tr><td colspan="4">Terjadi kesalahan saat memuat catatan.</td></tr>'); });
  });
});
</script>

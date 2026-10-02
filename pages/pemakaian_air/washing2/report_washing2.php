<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230;
requireView($conn, $menuId);
$reportPermissions = userPermissions($conn, $menuId);
$canEdit = !empty($reportPermissions['CanEdit']) && (int)$reportPermissions['CanEdit'] === 1;
$canDelete = !empty($reportPermissions['CanDelete']) && (int)$reportPermissions['CanDelete'] === 1;

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

$normalizeDate = function ($value) {
    $value = trim((string) $value);
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
    } else {
        $start = $startInput;
        $end = $endInput;
    }
}

$sql = "
SELECT d.tanggal,
       w.WattAwal, w.WattAkhir, w.OperasionalMesin AS WattOperasional,
       s.SteamAwal, s.SteamAkhir, s.SteamPemakaian,
       m.WaterAwal, m.WaterAkhir, m.TotalPemakaian, m.OperasionalMesin AS WaterOperasional, m.PemakaianRataPerJam,
       m.Keterangan
FROM (
  SELECT Tanggal AS tanggal FROM dbo.washing2_watt_meter
  UNION
  SELECT Tanggal AS tanggal FROM dbo.washing2_steam_meter
  UNION
  SELECT Tanggal AS tanggal FROM dbo.washing2_water_meter
) d
LEFT JOIN dbo.washing2_watt_meter w ON w.Tanggal = d.tanggal
LEFT JOIN dbo.washing2_steam_meter s ON s.Tanggal = d.tanggal
LEFT JOIN dbo.washing2_water_meter m ON m.Tanggal = d.tanggal
WHERE d.tanggal BETWEEN ? AND ?
ORDER BY d.tanggal ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    $errorMsg = 'Query gagal: ' . print_r(sqlsrv_errors(), true);
}

$rows = [];
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (($r['WattAwal']===null && $r['WattAkhir']===null && $r['WattOperasional']===null
            && $r['SteamAwal']===null && $r['SteamAkhir']===null && $r['SteamPemakaian']===null
            && $r['WaterAwal']===null && $r['WaterAkhir']===null && $r['TotalPemakaian']===null
            && $r['WaterOperasional']===null && $r['PemakaianRataPerJam']===null)) {
            continue;
        }
        if ($r['SteamPemakaian'] === null && $r['SteamAwal'] !== null && $r['SteamAkhir'] !== null) {
            $r['SteamPemakaian'] = round((float)$r['SteamAkhir'] - (float)$r['SteamAwal'], 2);
        }
        if ($r['TotalPemakaian'] === null && $r['WaterAwal'] !== null && $r['WaterAkhir'] !== null) {
            $r['TotalPemakaian'] = round((float)$r['WaterAkhir'] - (float)$r['WaterAwal'], 2);
        }
        if ($r['PemakaianRataPerJam'] === null && $r['TotalPemakaian'] !== null && $r['WaterOperasional'] !== null && (float)$r['WaterOperasional'] != 0.0) {
            $r['PemakaianRataPerJam'] = round((float)$r['TotalPemakaian'] / (float)$r['WaterOperasional'], 2);
        }
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}

$sumWattOp = 0.0; $sumSteamPem = 0.0; $sumTotalPem = 0.0; $sumWaterOp = 0.0;
$cntWattOp=0; $cntSteamPem=0; $cntTotalPem=0; $cntWaterOp=0;
foreach ($rows as $r) {
    if (is_numeric($r['WattOperasional'])) { $sumWattOp += (float)$r['WattOperasional']; $cntWattOp++; }
    if (is_numeric($r['SteamPemakaian'])) { $sumSteamPem += (float)$r['SteamPemakaian']; $cntSteamPem++; }
    if (is_numeric($r['TotalPemakaian'])) { $sumTotalPem += (float)$r['TotalPemakaian']; $cntTotalPem++; }
    if (is_numeric($r['WaterOperasional'])) { $sumWaterOp += (float)$r['WaterOperasional']; $cntWaterOp++; }
}

$avgWattOp = $cntWattOp ? $sumWattOp / $cntWattOp : null;
$avgSteamPem = $cntSteamPem ? $sumSteamPem / $cntSteamPem : null;
$avgTotalPem = $cntTotalPem ? $sumTotalPem / $cntTotalPem : null;
$avgWaterOp = $cntWaterOp ? $sumWaterOp / $cntWaterOp : null;

$totalRate = ($sumWaterOp != 0.0) ? ($sumTotalPem / $sumWaterOp) : null;
$avgRate = ($avgWaterOp && $avgWaterOp != 0.0 && $avgTotalPem !== null) ? ($avgTotalPem / $avgWaterOp) : null;

$fmtNum = function($val, $dec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDate = function($val) {
    if ($val instanceof DateTime) return $val->format('d-M-y');
    if (is_string($val) && $val !== '') return date('d-M-y', strtotime($val));
    return '';
};
$fmtDay = function($val) {
    if ($val instanceof DateTime) return $val->format('j');
    if (is_string($val) && $val !== '') return date('j', strtotime($val));
    return '';
};

$monthMap = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET','April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI','August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER','November'=>'NOVEMBER','December'=>'DESEMBER'];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
?>

<div class="content-wrapper">
    <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">Report Pemakaian Air Washing 2</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/washing2/washing2.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                    <form method="post" action="export_excel_washing2.php" class="m-0 p-0"><input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>"><input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>"><button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button></form>
                    <form method="post" action="export_pdf_washing2.php" class="m-0 p-0"><input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>"><input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>"><button type="submit" class="btn btn-danger btn-sm" title="Export PDF"><i class="fas fa-file-pdf"></i></button></form>
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
                  .w2-report{border-color:#000;font-size:12px}
                  .w2-report th,.w2-report td{text-align:center;vertical-align:middle;border:1px solid #000;padding:3px 4px}
                  .w2-report .top{background:#afc0d6;font-weight:700}
                  .w2-report .sub{background:#cfdeef;font-weight:700}
                  .w2-report .yellow{background:#fff200;font-weight:700}
                  .w2-report .watt{background:#cfdeef}
                  .w2-report .steam{background:#cfdeef}
                  .w2-report .water{background:#cfdeef}
                  .w2-report .sum{background:#d9d6c4;font-weight:700}
                  .w2-report .left{text-align:left}
                </style>

                <div class="table-responsive">
                <table class="table table-sm w2-report">
                  <thead>
                    <tr>
                      <th rowspan="4" class="top" style="width:72px;"></th>
                      <th colspan="11" class="top" style="font-size:30px;line-height:1.05;font-weight:800;">PEMAKAIAN AIR DI MESIN WASHING II</th>
                      <th rowspan="3" class="top" style="min-width:140px;">KET</th>
                    </tr>
                    <tr><th colspan="11" class="top" style="font-size:28px;font-weight:800;line-height:1;"><?= htmlspecialchars($monthLabel) ?></th></tr>
                    <tr>
                      <th colspan="2" class="sub">WATT PER HOUR METER</th>
                      <th rowspan="2" class="sub">OPERASIONAL MESIN</th>
                      <th colspan="2" class="sub">STEAM FLOW METER</th>
                      <th rowspan="2" class="sub">TOTAL PEMAKAIAN</th>
                      <th colspan="2" class="sub">WATER FLOW METER</th>
                      <th rowspan="2" class="sub">TOTAL PEMAKAIAN</th>
                      <th rowspan="2" class="sub">OPERASIONAL MESIN</th>
                      <th rowspan="2" class="sub">PEMAKAIAN RATA RATA PER JAM</th>
                    </tr>
                    <tr>
                      <th class="sub">AWAL<br>KW</th><th class="sub">AKHIR<br>KW</th>
                      <th class="sub">AWAL<br>TON</th><th class="sub">AKHIR<br>TON</th>
                      <th class="sub">AWAL<br>M<sup>3</sup></th><th class="sub">AKHIR<br>M<sup>3</sup></th>
                    </tr>
                    <tr><th class="sub">TANGGAL</th><th class="sub" colspan="12"></th></tr>
                  </thead>
                  <tbody>
                    <?php if (empty($rows)): ?>
                      <tr><td colspan="13">Tidak ada data</td></tr>
                    <?php else: foreach($rows as $r): $isOff = stripos((string)($r['Keterangan'] ?? ''), 'off') !== false; ?>
                      <tr>
                        <td><b><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></b></td>
                        <td class="watt"><b><?= htmlspecialchars($fmtNum($r['WattAwal'],2)) ?></b></td>
                        <td class="watt"><b><?= htmlspecialchars($fmtNum($r['WattAkhir'],2)) ?></b></td>
                        <td class="watt"><b><?= htmlspecialchars($fmtNum($r['WattOperasional'],2)) ?></b></td>
                        <td class="steam"><b><?= htmlspecialchars($fmtNum($r['SteamAwal'],2)) ?></b></td>
                        <td class="steam"><b><?= htmlspecialchars($fmtNum($r['SteamAkhir'],2)) ?></b></td>
                        <td><b><?= htmlspecialchars($fmtNum($r['SteamPemakaian'],2)) ?></b></td>
                        <td class="water"><b><?= htmlspecialchars($fmtNum($r['WaterAwal'],2)) ?></b></td>
                        <td class="water"><b><?= htmlspecialchars($fmtNum($r['WaterAkhir'],2)) ?></b></td>
                        <td class="yellow"><b><?= htmlspecialchars($fmtNum($r['TotalPemakaian'],2)) ?></b></td>
                        <td><b><?= htmlspecialchars($fmtNum($r['WaterOperasional'],2)) ?></b></td>
                        <td><b><?= htmlspecialchars($fmtNum($r['PemakaianRataPerJam'],2)) ?></b></td>
                        <td class="left <?= $isOff ? 'yellow' : '' ?>"><b><?= htmlspecialchars($r['Keterangan'] ?? '') ?></b></td>
                      </tr>
                    <?php endforeach; ?>
                    <tr class="sum"><td>TOTAL</td><td></td><td></td><td><?= htmlspecialchars($fmtNum($sumWattOp,2)) ?></td><td></td><td></td><td><?= htmlspecialchars($fmtNum($sumSteamPem,2)) ?></td><td></td><td></td><td><?= htmlspecialchars($fmtNum($sumTotalPem,2)) ?></td><td><?= htmlspecialchars($fmtNum($sumWaterOp,2)) ?></td><td><?= htmlspecialchars($fmtNum($totalRate,2)) ?></td><td></td></tr>
                    <tr class="sum"><td>RATA-RATA</td><td></td><td></td><td><?= htmlspecialchars($fmtNum($avgWattOp,2)) ?></td><td></td><td></td><td><?= htmlspecialchars($fmtNum($avgSteamPem,2)) ?></td><td></td><td></td><td><?= htmlspecialchars($fmtNum($avgTotalPem,2)) ?></td><td><?= htmlspecialchars($fmtNum($avgWaterOp,2)) ?></td><td><?= htmlspecialchars($fmtNum($avgRate,2)) ?></td><td></td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>

    </div></section>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<div class="modal fade" id="modalCatatanWashing2" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan Washing 2</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body"><div class="mb-2 text-muted" id="catatanRangeInfo"></div>
      <div class="table-responsive"><table class="table table-sm table-bordered"><thead class="thead-light"><tr><th style="width:170px;">Tanggal</th><th>Catatan</th><th style="width:120px;">Created By</th><th style="width:90px;">Aksi</th></tr></thead><tbody id="catatanBody"><tr><td colspan="4">Memuat catatan...</td></tr></tbody></table></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
  </div></div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script>
$(function(){
  var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  var canDelete = <?= $canDelete ? 'true' : 'false' ?>;

  $('#btnLihatCatatan').on('click', function(){
    var s=$('#start_date').val(), e=$('#end_date').val();
    if(!s||!e){ Swal.fire({icon:'warning',title:'Perhatian',text:'Pilih rentang tanggal terlebih dahulu.'}); return; }
    $('#catatanRangeInfo').text('Rentang: '+s+' s/d '+e);
    $('#catatanBody').html('<tr><td colspan="4">Memuat catatan...</td></tr>');
    $('#modalCatatanWashing2').modal('show');
    $.post('get_catatan_washing2.php',{start_date:s,end_date:e},function(resp){
      if(!resp||!resp.success){ $('#catatanBody').html('<tr><td colspan="4">'+((resp&&resp.message)?resp.message:'Gagal memuat catatan.')+'</td></tr>'); return; }
      if(!resp.data||resp.data.length===0){ $('#catatanBody').html('<tr><td colspan="4">Tidak ada catatan.</td></tr>'); return; }
      var rows='';
      resp.data.forEach(function(i){
        var safe = $('<div>').text(i.catatan || '').html().replace(/\n/g, '<br>');
        var catatanAction = '-';
        if (canEdit || canDelete) {
          catatanAction = '<div class="btn-group btn-group-sm">';
          if (canEdit) {
            catatanAction += '<button class="btn btn-warning btn-edit-catatan" data-id="' + i.id + '" data-catatan="' + $('<div>').text(i.catatan || '').html() + '"><i class="fas fa-edit"></i></button>';
          }
          if (canDelete) {
            catatanAction += '<button class="btn btn-danger btn-delete-catatan" data-id="' + i.id + '"><i class="fas fa-trash"></i></button>';
          }
          catatanAction += '</div>';
        }
        rows += '<tr><td>' + (i.tanggal || '-') + '</td><td>' + safe + '</td><td>' + (i.creatby || '-') + '</td><td>' + catatanAction + '</td></tr>';
      });
      $('#catatanBody').html(rows);
    },'json').fail(function(){ $('#catatanBody').html('<tr><td colspan="4">Terjadi kesalahan saat memuat catatan.</td></tr>'); });
  });

  $(document).on('click','.btn-edit-catatan',function(){
    var id=$(this).data('id'), current=$(this).data('catatan')||'';
    Swal.fire({title:'Edit Catatan',input:'textarea',inputValue:$('<div>').html(current).text(),showCancelButton:true,confirmButtonText:'Simpan',cancelButtonText:'Batal'}).then(function(r){
      if(!r.isConfirmed) return; var c=(r.value||'').trim(); if(c===''){ Swal.fire({icon:'warning',title:'Catatan kosong',text:'Catatan wajib diisi.'}); return; }
      $.post('update_catatan_washing2.php',{id:id,catatan:c},function(resp){ if(resp&&resp.success){ Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan diperbarui.'}); $('#btnLihatCatatan').trigger('click'); } else { Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal memperbarui catatan.'}); }},'json').fail(function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat memperbarui catatan.'}); });
    });
  });

  $(document).on('click','.btn-delete-catatan',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus Catatan?',text:'Catatan akan dikosongkan.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal'}).then(function(r){
      if(!r.isConfirmed) return;
      $.post('delete_catatan_washing2.php',{id:id},function(resp){ if(resp&&resp.success){ Swal.fire({icon:'success',title:'Terhapus',text:resp.message||'Catatan dihapus.'}); $('#btnLihatCatatan').trigger('click'); } else { Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menghapus catatan.'}); }},'json').fail(function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menghapus catatan.'}); });
    });
  });
});
</script>

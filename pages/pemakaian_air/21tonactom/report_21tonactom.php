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

function fetchRowsByType($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return [false, []];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return [true, $rows];
}

list($okAirBoiler, $airBoilerRows) = fetchRowsByType(
    $conn,
    "SELECT
        Tanggal,
        awal AS Awal,
        ahir AS Akhir,
        total_pemakaian AS TotalPemakaian,
        pemakaianrata2perjam AS RataPerJam
     FROM dbo.air_boiler_actom
     WHERE Tanggal BETWEEN ? AND ?
     ORDER BY Tanggal ASC",
    [$start, $end]
);

list($okSteam, $steamRows) = fetchRowsByType(
    $conn,
    "SELECT
        Tanggal,
        awal AS Awal,
        ahir AS Akhir,
        total_pemakaian AS TotalPemakaian,
        pemakaianrata2perjam AS RataPerJam
     FROM dbo.steam_boiler_actom
     WHERE Tanggal BETWEEN ? AND ?
     ORDER BY Tanggal ASC",
    [$start, $end]
);

list($okAirAnalog, $airAnalogRows) = fetchRowsByType(
    $conn,
    "SELECT
        Tanggal,
        awal AS Awal,
        ahir AS Akhir,
        total_pemakaian AS TotalPemakaian,
        pemakaianrata2perjam AS RataPerJam
     FROM dbo.air_analog_actom
     WHERE Tanggal BETWEEN ? AND ?
     ORDER BY Tanggal ASC",
    [$start, $end]
);

list($okAnalogSteam, $analogSteamRows) = fetchRowsByType(
    $conn,
    "SELECT
        Tanggal,
        awal AS Awal,
        ahir AS Akhir,
        total_pemakaian AS TotalPemakaian,
        pemakaianrata2perjam AS RataPerJam
     FROM dbo.analog_steam_actom
     WHERE Tanggal BETWEEN ? AND ?
     ORDER BY Tanggal ASC",
    [$start, $end]
);

if (!$okAirBoiler || !$okSteam || !$okAirAnalog || !$okAnalogSteam) {
    $errorMsg = 'Query gagal: ' . print_r(sqlsrv_errors(), true);
}

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$toDay = function ($tanggal) {
    if ($tanggal instanceof DateTime) return (int)$tanggal->format('j');
    $str = (string)$tanggal;
    return $str !== '' ? (int)date('j', strtotime($str)) : '';
};
$toNum = function ($value) {
    return is_numeric($value) ? (float)$value : null;
};
$fmt = function ($value, $dec = 2) {
    if ($value === null || $value === '') return '';
    if (!is_numeric($value)) return (string)$value;
    return number_format((float)$value, $dec, '.', ',');
};

function prepareTableRows($rows, $toNum) {
    $out = [];
    foreach ($rows as $row) {
        $total = $toNum($row['TotalPemakaian'] ?? null);
        $rata = $toNum($row['RataPerJam'] ?? null);
        $out[] = [
            'Tanggal' => $row['Tanggal'] ?? '',
            'Awal' => $toNum($row['Awal'] ?? null),
            'Akhir' => $toNum($row['Akhir'] ?? null),
            'TotalPemakaian' => $total,
            'RataPerJam' => $rata !== null ? $rata : ($total === null ? null : round($total / 24, 2))
        ];
    }
    return $out;
}

$airBoilerRows = prepareTableRows($airBoilerRows, $toNum);
$steamRows = prepareTableRows($steamRows, $toNum);
$airAnalogRows = prepareTableRows($airAnalogRows, $toNum);
$analogSteamRows = prepareTableRows($analogSteamRows, $toNum);

function calcSummary($rows) {
    $sumTotal = 0.0;
    $sumRata = 0.0;
    $cntTotal = 0;
    $cntRata = 0;
    foreach ($rows as $row) {
        if (is_numeric($row['TotalPemakaian'])) {
            $sumTotal += (float)$row['TotalPemakaian'];
            $cntTotal++;
        }
        if (is_numeric($row['RataPerJam'])) {
            $sumRata += (float)$row['RataPerJam'];
            $cntRata++;
        }
    }
    return [
        'sumTotal' => $sumTotal,
        'sumRata' => $sumRata,
        'avgTotal' => $cntTotal > 0 ? ($sumTotal / $cntTotal) : null,
        'avgRata' => $cntRata > 0 ? ($sumRata / $cntRata) : null
    ];
}

$sumAirBoiler = calcSummary($airBoilerRows);
$sumSteam = calcSummary($steamRows);
$sumAirAnalog = calcSummary($airAnalogRows);
$sumAnalogSteam = calcSummary($analogSteamRows);

function renderTable($title, $unit, $rows, $summary, $monthLabel, $toDay, $fmt) {
    ?>
    <table class="table table-sm actom-report">
        <thead>
            <tr>
                <th colspan="5" class="top-head title-head"><?= htmlspecialchars($title) ?></th>
            </tr>
            <tr>
                <th colspan="4" class="top-head month-head"><?= htmlspecialchars($monthLabel) ?></th>
                <th class="top-head logo-cell">
                    <img src="/gg_app/dist/img/sumlogo.png" alt="logo" style="height:38px;object-fit:contain;">
                </th>
            </tr>
            <tr>
                <th class="sub-head">TANGGAL</th>
                <th class="sub-head">AWAL</th>
                <th class="sub-head">AKHIR</th>
                <th class="sub-head">TOTAL PEMAKAIAN</th>
                <th class="sub-head">PEMAKAIAN RATA RATA PER JAM</th>
            </tr>
            <tr>
                <th class="unit-head"></th>
                <th class="unit-head"><?= htmlspecialchars($unit) ?></th>
                <th class="unit-head"><?= htmlspecialchars($unit) ?></th>
                <th class="unit-head"><?= htmlspecialchars($unit) ?></th>
                <th class="unit-head"><?= htmlspecialchars($unit) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5">Tidak ada data</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($toDay($row['Tanggal'])) ?></b></td>
                        <td><b><?= htmlspecialchars($fmt($row['Awal'], 2)) ?></b></td>
                        <td><b><?= htmlspecialchars($fmt($row['Akhir'], 2)) ?></b></td>
                        <td class="total-bg"><b><?= htmlspecialchars($fmt($row['TotalPemakaian'], 2)) ?></b></td>
                        <td><b><?= htmlspecialchars($fmt($row['RataPerJam'], 2)) ?></b></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="sum-row">
                    <td>TOTAL</td>
                    <td></td>
                    <td></td>
                    <td><?= htmlspecialchars($fmt($summary['sumTotal'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmt($summary['sumRata'], 2)) ?></td>
                </tr>
                <tr class="sum-row">
                    <td>RATA-RATA</td>
                    <td></td>
                    <td></td>
                    <td><?= htmlspecialchars($fmt($summary['avgTotal'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmt($summary['avgRata'], 2)) ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}
?>

<div class="content-wrapper">
    <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">Report 21 Ton Actom</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/21tonactom/21tonactom.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                        <form method="post" action="export_excel_21tonactom.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Export Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                        </form>
                        <form method="post" action="export_pdf_21tonactom.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Export PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
            <?php else: ?>
                <style>
                    .actom-report-card .card-body { overflow-x:auto; }
                    .actom-grid { display:flex; flex-wrap:nowrap; gap:14px; align-items:flex-start; min-width:max-content; }
                    .actom-col { flex:0 0 360px; width:360px; min-width:360px; }
                    .actom-report { border-color:#000; font-size:12px; margin-bottom:0; background:#fff; }
                    .actom-report th, .actom-report td { border:1px solid #000; text-align:center; vertical-align:middle; padding:3px 4px; }
                    .actom-report .top-head { background:#afc0d6; font-weight:700; }
                    .actom-report .title-head { font-size:26px; line-height:1.05; font-weight:800; }
                    .actom-report .month-head { font-size:32px; line-height:1.05; font-weight:800; }
                    .actom-report .logo-cell { width:82px; }
                    .actom-report .sub-head { background:#cfdeef; font-weight:700; }
                    .actom-report .unit-head { background:#f3e6e6; font-weight:700; }
                    .actom-report .total-bg { background:#b7c9de; }
                    .actom-report .sum-row td { background:#d9d6c4; font-weight:700; }
                </style>

                <div class="card actom-report-card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
                        <h3 class="card-title"><i class="fas fa-table mr-1"></i> Output 4 Tabel 21 Ton Actom</h3>
                        <button type="button" class="btn btn-light btn-sm ml-auto" id="btnLihatCatatan" style="margin-left:auto;">
                            <i class="fas fa-sticky-note"></i> Lihat Catatan
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="actom-grid">
                            <div class="actom-col">
                                <?php renderTable('AIR BOILER 21TON ACTOM', 'M3', $airBoilerRows, $sumAirBoiler, $monthLabel, $toDay, $fmt); ?>
                            </div>
                            <div class="actom-col">
                                <?php renderTable('STEAM BOILER 21TON ACTOM', 'TON', $steamRows, $sumSteam, $monthLabel, $toDay, $fmt); ?>
                            </div>
                            <div class="actom-col">
                                <?php renderTable('AIR ANALOG 21TON ACTOM', 'M3', $airAnalogRows, $sumAirAnalog, $monthLabel, $toDay, $fmt); ?>
                            </div>
                            <div class="actom-col">
                                <?php renderTable('ANALOG STEAM 21TON ACTOM', 'Ton', $analogSteamRows, $sumAnalogSteam, $monthLabel, $toDay, $fmt); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<div class="modal fade" id="modalCatatan21TonActom" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
      <h5 class="modal-title">Catatan 21 Ton Actom</h5>
      <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
      <div class="mb-2 text-muted" id="catatanRangeInfo"></div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="thead-light">
            <tr>
              <th style="width:170px;">Tanggal</th>
              <th>Catatan</th>
              <th style="width:120px;">Created By</th>
              <th style="width:90px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="catatanBody"><tr><td colspan="4">Memuat catatan...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
    </div>
  </div></div>
</div>

<script>
$(function(){
  var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  var canDelete = <?= $canDelete ? 'true' : 'false' ?>;

  $('#btnLihatCatatan').on('click', function(){
    var s=$('#start_date').val(), e=$('#end_date').val();
    if(!s||!e){ Swal.fire({icon:'warning',title:'Perhatian',text:'Pilih rentang tanggal terlebih dahulu.'}); return; }
    $('#catatanRangeInfo').text('Rentang: '+s+' s/d '+e);
    $('#catatanBody').html('<tr><td colspan="4">Memuat catatan...</td></tr>');
    $('#modalCatatan21TonActom').modal('show');
    $.post('get_catatan_21tonactom.php',{start_date:s,end_date:e},function(resp){
      if(!resp||!resp.success){ $('#catatanBody').html('<tr><td colspan="4">'+((resp&&resp.message)?resp.message:'Gagal memuat catatan.')+'</td></tr>'); return; }
      if(!resp.data||resp.data.length===0){ $('#catatanBody').html('<tr><td colspan="4">Tidak ada catatan.</td></tr>'); return; }
      var rows='';
      resp.data.forEach(function(i){
        var safe=$('<div>').text(i.catatan||'').html().replace(/\n/g,'<br>');
        var catatanAction = '-';
        if (canEdit || canDelete) {
          catatanAction = '<div class="btn-group btn-group-sm">';
          if (canEdit) catatanAction += '<button class="btn btn-warning btn-edit-catatan" data-id="'+i.id+'" data-catatan="'+$('<div>').text(i.catatan||'').html()+'"><i class="fas fa-edit"></i></button>';
          if (canDelete) catatanAction += '<button class="btn btn-danger btn-delete-catatan" data-id="'+i.id+'"><i class="fas fa-trash"></i></button>';
          catatanAction += '</div>';
        }
        rows += '<tr><td>'+ (i.tanggal||'-') +'</td><td>'+safe+'</td><td>'+(i.creatby||'-')+'</td><td>'+catatanAction+'</td></tr>';
      });
      $('#catatanBody').html(rows);
    },'json').fail(function(){ $('#catatanBody').html('<tr><td colspan="4">Terjadi kesalahan saat memuat catatan.</td></tr>'); });
  });

  $(document).on('click','.btn-edit-catatan',function(){
    var id=$(this).data('id'), current=$(this).data('catatan')||'';
    Swal.fire({title:'Edit Catatan',input:'textarea',inputValue:$('<div>').html(current).text(),showCancelButton:true,confirmButtonText:'Simpan',cancelButtonText:'Batal'}).then(function(r){
      if(!r.isConfirmed) return;
      var c=(r.value||'').trim();
      if(c===''){ Swal.fire({icon:'warning',title:'Catatan kosong',text:'Catatan wajib diisi.'}); return; }
      $.post('update_catatan_21tonactom.php',{id:id,catatan:c},function(resp){
        if(resp&&resp.success){ Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan diperbarui.'}); $('#btnLihatCatatan').trigger('click'); }
        else { Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal memperbarui catatan.'}); }
      },'json').fail(function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat memperbarui catatan.'}); });
    });
  });

  $(document).on('click','.btn-delete-catatan',function(){
    var id=$(this).data('id');
    Swal.fire({title:'Hapus Catatan?',text:'Catatan akan dihapus permanen.',icon:'warning',showCancelButton:true,confirmButtonColor:'#d33',cancelButtonColor:'#3085d6',confirmButtonText:'Ya, Hapus!',cancelButtonText:'Batal'})
      .then(function(r){
        if(!r.isConfirmed) return;
        $.post('delete_catatan_21tonactom.php',{id:id},function(resp){
          if(resp&&resp.success){ Swal.fire({icon:'success',title:'Sukses',text:resp.message||'Catatan dihapus.'}); $('#btnLihatCatatan').trigger('click'); }
          else { Swal.fire({icon:'error',title:'Gagal',text:(resp&&resp.message)?resp.message:'Gagal menghapus catatan.'}); }
        },'json').fail(function(){ Swal.fire({icon:'error',title:'Error',text:'Terjadi kesalahan saat menghapus catatan.'}); });
      });
  });
});
</script>

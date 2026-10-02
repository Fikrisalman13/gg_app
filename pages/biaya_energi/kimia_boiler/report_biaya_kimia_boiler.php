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
    } elseif (strtotime($startInput) > strtotime($endInput)) {
        $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    } else {
        $start = $startInput;
        $end = $endInput;
    }
}

$groupOrderExpr = "CASE m.grup_laporan
    WHEN 'BOILER' THEN 1
    ELSE 99 END";

$groupLabels = [
    'BOILER' => 'BOILER',
];

$masters = [];
$masterById = [];
$groups = [];

$masterSql = "SELECT m.id, m.kode, m.nama_item, m.grup_laporan, m.satuan_pakai
              FROM dbo.kimia_boiler_master m
              WHERE m.aktif = 1
              ORDER BY $groupOrderExpr, m.id ASC";
$masterStmt = sqlsrv_query($conn, $masterSql);
if ($masterStmt === false) {
    $errorMsg = 'Gagal mengambil master kimia: ' . print_r(sqlsrv_errors(), true);
} else {
    while ($m = sqlsrv_fetch_array($masterStmt, SQLSRV_FETCH_ASSOC)) {
        $mid = (int)($m['id'] ?? 0);
        if ($mid <= 0) continue;
        $g = (string)($m['grup_laporan'] ?? 'LAINNYA');
        $item = [
            'id' => $mid,
            'kode' => (string)($m['kode'] ?? ''),
            'nama_item' => (string)($m['nama_item'] ?? ''),
            'grup_laporan' => $g,
            'satuan_pakai' => (string)($m['satuan_pakai'] ?? 'Kg'),
        ];
        $masters[] = $item;
        $masterById[$mid] = $item;
        if (!isset($groups[$g])) $groups[$g] = [];
        $groups[$g][] = $mid;
    }
    sqlsrv_free_stmt($masterStmt);
}

$dateRows = [];
$dataMap = [];
$sumPakaiByMaster = [];
$sumBiayaByMaster = [];
$sumHargaByMaster = [];
$countHargaByMaster = [];

if ($errorMsg === '') {
    $dataSql = "SELECT CAST(h.tanggal AS DATE) AS tanggal, h.master_id, h.pakai_kg, h.harga_rp, h.biaya_rp
                FROM dbo.kimia_boiler_harian h
                INNER JOIN dbo.kimia_boiler_master m ON m.id = h.master_id
                WHERE CAST(h.tanggal AS DATE) BETWEEN ? AND ?
                ORDER BY CAST(h.tanggal AS DATE) ASC, $groupOrderExpr, h.master_id ASC";
    $stmt = sqlsrv_query($conn, $dataSql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data kimia harian: ' . print_r(sqlsrv_errors(), true);
    } else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateObj = $r['tanggal'] ?? null;
            if ($dateObj instanceof DateTime) {
                $dateKey = $dateObj->format('Y-m-d');
            } else {
                $dateKey = date('Y-m-d', strtotime((string)$dateObj));
            }

            $mid = (int)($r['master_id'] ?? 0);
            if ($mid <= 0) continue;

            $pakai = is_numeric($r['pakai_kg']) ? (float)$r['pakai_kg'] : 0.0;
            $harga = is_numeric($r['harga_rp']) ? (float)$r['harga_rp'] : 0.0;
            $biaya = is_numeric($r['biaya_rp']) ? (float)$r['biaya_rp'] : ($pakai * $harga);

            if (!isset($dateRows[$dateKey])) $dateRows[$dateKey] = $dateKey;
            if (!isset($dataMap[$dateKey])) $dataMap[$dateKey] = [];
            $dataMap[$dateKey][$mid] = ['pakai' => $pakai, 'harga' => $harga, 'biaya' => $biaya];

            if (!isset($sumPakaiByMaster[$mid])) $sumPakaiByMaster[$mid] = 0.0;
            if (!isset($sumBiayaByMaster[$mid])) $sumBiayaByMaster[$mid] = 0.0;
            if (!isset($sumHargaByMaster[$mid])) $sumHargaByMaster[$mid] = 0.0;
            if (!isset($countHargaByMaster[$mid])) $countHargaByMaster[$mid] = 0;

            $sumPakaiByMaster[$mid] += $pakai;
            $sumBiayaByMaster[$mid] += $biaya;
            $sumHargaByMaster[$mid] += $harga;
            $countHargaByMaster[$mid]++;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$dates = array_values($dateRows);
sort($dates);
$rowCount = count($dates);

$defaultHargaByMaster = [];
foreach ($masters as $m) {
    $mid = $m['id'];
    $defaultHargaByMaster[$mid] = ($countHargaByMaster[$mid] ?? 0) > 0
        ? ($sumHargaByMaster[$mid] / $countHargaByMaster[$mid])
        : 0.0;
}

$sumTotalBiaya = 0.0;
$totalBiayaPerDate = [];
foreach ($dates as $dateKey) {
    $t = 0.0;
    foreach ($masters as $m) {
        $mid = $m['id'];
        $t += $dataMap[$dateKey][$mid]['biaya'] ?? 0.0;
    }
    $totalBiayaPerDate[$dateKey] = $t;
    $sumTotalBiaya += $t;
}
$avgTotalBiaya = $rowCount > 0 ? ($sumTotalBiaya / $rowCount) : 0.0;

$fmtNum = function ($val, $dec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', ',');
};

$fmtDay = function ($ymd) {
    if (!$ymd) return '';
    return date('j', strtotime($ymd));
};

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));

$masterCount = count($masters);
$totalColumns = ($masterCount * 3) + 2;
?>

<div class="content-wrapper">
    <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">Report Biaya Kimia Boiler</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/biaya_energi/kimia_boiler/kimia_boiler.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                    <form method="post" action="export_excel_biaya_kimia_boiler.php" class="m-0 p-0">
                        <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                        <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
                    </form>
                    <form method="post" action="export_pdf_biaya_kimia_boiler.php" class="m-0 p-0">
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
                    .ipal-wrap{overflow-x:auto;overflow-y:visible;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
                    .ipal-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:100%;width:max-content;background:#fff}
                    .ipal-report th,.ipal-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
                    .ipal-report .title{background:#e6e6e6;font-weight:700;font-size:20px;line-height:1.2}
                    .ipal-report .subtitle{background:#e6e6e6;font-weight:700;font-size:16px;line-height:1.2}
                    .ipal-report .month{background:#f3f3f3;font-weight:700;font-size:14px}
                    .ipal-report .group{background:#d9d9d9;font-weight:700}
                    .ipal-report .item{background:#fff200;font-weight:700}
                    .ipal-report .sub{background:#fff200;font-weight:700}
                    .ipal-report .data{background:#d8d5b8}
                    .ipal-report .datecol{background:#dfe7d3;font-weight:700;min-width:80px}
                    .ipal-report .sum{background:#e2e2d1;font-weight:700}
                    .ipal-report .include{background:#fff200;font-weight:700}
                    .ipal-report .tot-head,.ipal-report .tot-cell{background:#ffc000;font-weight:700}
                    .ipal-report .sticky-left{position:sticky;left:0;z-index:4}
                </style>

                <div class="card-body p-2">
                    <?php if (empty($masters)): ?>
                        <div class="alert alert-warning mb-0">Master Kimia Boiler belum tersedia atau belum aktif.</div>
                    <?php else: ?>
                        <div class="ipal-wrap">
                            <table class="table table-sm ipal-report">
                                <thead>
                                    <tr><th class="title" colspan="<?= $totalColumns ?>">PEMAKAIAN OBAT DI AREA BOILER</th></tr>
                                    <tr><th class="subtitle" colspan="<?= $totalColumns ?>">KIMIA BOILER</th></tr>
                                    <tr>
                                        <th class="month sticky-left" colspan="2">Bulan : <?= htmlspecialchars($monthLabel) ?></th>
                                        <th class="month" colspan="<?= $masterCount * 3 ?>"></th>
                                    </tr>
                                    <tr>
                                        <th class="datecol sticky-left" rowspan="3">TANGGAL</th>
                                        <?php foreach ($groups as $gKey => $itemIds): ?>
                                            <th class="group" colspan="<?= count($itemIds) * 3 ?>"><?= htmlspecialchars($groupLabels[$gKey] ?? $gKey) ?></th>
                                        <?php endforeach; ?>
                                        <th class="tot-head" rowspan="3">TOTAL BIAYA</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($groups as $itemIds): ?>
                                            <?php foreach ($itemIds as $mid): ?>
                                                <th class="item" colspan="3"><?= htmlspecialchars($masterById[$mid]['nama_item'] ?? '-') ?></th>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <?php foreach ($groups as $itemIds): ?>
                                            <?php foreach ($itemIds as $mid): ?>
                                                <th class="sub">PAKAI (<?= htmlspecialchars($masterById[$mid]['satuan_pakai'] ?? 'Kg') ?>)</th>
                                                <th class="sub">HARGA (Rp)</th>
                                                <th class="sub">BIAYA (Rp)</th>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dates)): ?>
                                        <tr><td colspan="<?= $totalColumns ?>">Tidak ada data pada rentang tanggal ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($dates as $dateKey): ?>
                                            <tr>
                                                <td class="datecol sticky-left"><?= htmlspecialchars($fmtDay($dateKey)) ?></td>
                                                <?php foreach ($groups as $itemIds): ?>
                                                    <?php foreach ($itemIds as $mid):
                                                        $cell = $dataMap[$dateKey][$mid] ?? null;
                                                        $pakai = $cell['pakai'] ?? 0.0;
                                                        $harga = $cell['harga'] ?? ($defaultHargaByMaster[$mid] ?? 0.0);
                                                        $biaya = $cell['biaya'] ?? ($pakai * $harga);
                                                    ?>
                                                        <td class="data"><?= htmlspecialchars($fmtNum($pakai, 2)) ?></td>
                                                        <td class="data"><?= htmlspecialchars($fmtNum($harga, 2)) ?></td>
                                                        <td class="data"><?= htmlspecialchars($fmtNum($biaya, 2)) ?></td>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                                <td class="tot-cell"><?= htmlspecialchars($fmtNum($totalBiayaPerDate[$dateKey] ?? 0, 2)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <tr class="sum">
                                            <td class="sticky-left">TOTAL</td>
                                            <?php foreach ($groups as $itemIds): ?>
                                                <?php foreach ($itemIds as $mid): ?>
                                                    <td><?= htmlspecialchars($fmtNum($sumPakaiByMaster[$mid] ?? 0, 2)) ?></td>
                                                    <td><?= htmlspecialchars($fmtNum($defaultHargaByMaster[$mid] ?? 0, 2)) ?></td>
                                                    <td><?= htmlspecialchars($fmtNum($sumBiayaByMaster[$mid] ?? 0, 2)) ?></td>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                            <td class="tot-cell"><?= htmlspecialchars($fmtNum($sumTotalBiaya, 2)) ?></td>
                                        </tr>

                                        <tr class="sum">
                                            <td class="sticky-left">RATA-RATA</td>
                                            <?php foreach ($groups as $itemIds): ?>
                                                <?php foreach ($itemIds as $mid):
                                                    $avgPakai = $rowCount > 0 ? (($sumPakaiByMaster[$mid] ?? 0) / $rowCount) : 0;
                                                    $avgBiaya = $rowCount > 0 ? (($sumBiayaByMaster[$mid] ?? 0) / $rowCount) : 0;
                                                ?>
                                                    <td><?= htmlspecialchars($fmtNum($avgPakai, 2)) ?></td>
                                                    <td class="include">Include</td>
                                                    <td><?= htmlspecialchars($fmtNum($avgBiaya, 2)) ?></td>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                            <td class="tot-cell"><?= htmlspecialchars($fmtNum($avgTotalBiaya, 2)) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div></section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<div class="modal fade" id="modalCatatanKimiaIpal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan Kimia Boiler</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
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
    $('#modalCatatanKimiaIpal').modal('show');
    $.post('get_catatan_kimia_boiler.php',{start_date:s,end_date:e},function(resp){
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


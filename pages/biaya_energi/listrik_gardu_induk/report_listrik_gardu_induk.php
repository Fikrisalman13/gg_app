<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 236;
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
    $sql = "WITH cte AS (
                SELECT x.id, x.tanggal, x.lvbp_kwh, x.vbp_kwh, x.kvarh, x.cos_phi, x.faktor_kali, x.rp_per_kwh, x.pf_standar, x.kapasitas_kva, x.ket,
                       LEAD(x.lvbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS lvbp_next,
                       LEAD(x.vbp_kwh) OVER (ORDER BY CAST(x.tanggal AS DATE), x.id) AS vbp_next
                FROM dbo.listrik_gardu_induk_harian x
            )
            SELECT CAST(cte.tanggal AS DATE) AS tanggal,
                   cte.lvbp_kwh,
                   cte.vbp_kwh,
                   cte.kvarh,
                   cte.cos_phi,
                   cte.rp_per_kwh,
                   cte.ket,
                   CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                        ELSE (((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) END AS total_daya_perday_kw,
                   CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                        ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) END AS total_daya_perjam_kwh,
                   CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL THEN NULL
                        ELSE ((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) * cte.rp_per_kwh) END AS biaya_perday_rp,
                   CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.pf_standar,0)=0 THEN NULL
                        ELSE (((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.pf_standar) END AS kva_pln_perjam,
                   CASE WHEN cte.lvbp_next IS NULL OR cte.vbp_next IS NULL OR ISNULL(cte.kapasitas_kva,0)=0 THEN NULL
                        ELSE ((((((cte.lvbp_next - cte.lvbp_kwh) * cte.faktor_kali) + ((cte.vbp_next - cte.vbp_kwh) * cte.faktor_kali)) / 24.0) / cte.kapasitas_kva) * 100.0) END AS efisiensi_persen
            FROM cte
            WHERE CAST(cte.tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(cte.tanggal AS DATE) ASC, cte.id ASC";
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
                'lvbp_kwh' => is_numeric($r['lvbp_kwh']) ? (float)$r['lvbp_kwh'] : 0,
                'vbp_kwh' => is_numeric($r['vbp_kwh']) ? (float)$r['vbp_kwh'] : 0,
                'kvarh' => is_numeric($r['kvarh']) ? (float)$r['kvarh'] : 0,
                'cos_phi' => is_numeric($r['cos_phi']) ? (float)$r['cos_phi'] : 0,
                'rp_per_kwh' => is_numeric($r['rp_per_kwh']) ? (float)$r['rp_per_kwh'] : 0,
                'ket' => (string)($r['ket'] ?? ''),
                'total_daya_perday_kw' => is_numeric($r['total_daya_perday_kw']) ? (float)$r['total_daya_perday_kw'] : null,
                'total_daya_perjam_kwh' => is_numeric($r['total_daya_perjam_kwh']) ? (float)$r['total_daya_perjam_kwh'] : null,
                'biaya_perday_rp' => is_numeric($r['biaya_perday_rp']) ? (float)$r['biaya_perday_rp'] : null,
                'efisiensi_persen' => is_numeric($r['efisiensi_persen']) ? (float)$r['efisiensi_persen'] : null,
                'kva_pln_perjam' => is_numeric($r['kva_pln_perjam']) ? (float)$r['kva_pln_perjam'] : null,
            ];
        }
        sqlsrv_free_stmt($stmt);
    }
}

$fmtNum = function ($val, $dec = 2) {
    if ($val === null || $val === '') return '-';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', ',');
};
$fmtDay = function ($ymd) { return $ymd ? date('j', strtotime($ymd)) : ''; };

$tot = [
    'total_daya_perday_kw' => 0,
    'total_daya_perjam_kwh' => 0,
    'biaya_perday_rp' => 0,
    'efisiensi_persen' => 0,
    'kva_pln_perjam' => 0,
];
$validCount = 0;
foreach ($rows as $r) {
    if ($r['total_daya_perday_kw'] !== null) {
        $validCount++;
        foreach ($tot as $k => $_) {
            $tot[$k] += (float)($r[$k] ?? 0);
        }
    }
}
$avg = [
    'total_daya_perday_kw' => $validCount > 0 ? ($tot['total_daya_perday_kw'] / $validCount) : 0,
    'total_daya_perjam_kwh' => $validCount > 0 ? ($tot['total_daya_perjam_kwh'] / $validCount) : 0,
    'biaya_perday_rp' => $validCount > 0 ? ($tot['biaya_perday_rp'] / $validCount) : 0,
    'efisiensi_persen' => $validCount > 0 ? ($tot['efisiensi_persen'] / $validCount) : 0,
    'kva_pln_perjam' => $validCount > 0 ? ($tot['kva_pln_perjam'] / $validCount) : 0,
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
        <h1 class="m-0">REPORT PEMAKAIAN ENERGI LISTRIK PLN - GARDU INDUK</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/biaya_energi/listrik_gardu_induk/listrik_gardu_induk.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
          <form method="post" action="export_excel_report_listrik_gardu_induk.php" class="m-0 p-0">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
          </form>
          <form method="post" action="export_pdf_report_listrik_gardu_induk.php" class="m-0 p-0" target="_blank">
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
          .pln-wrap{overflow-x:auto;overflow-y:visible;background:#f0f0f0;padding:8px;border:1px solid #cfcfcf}
          .pln-report{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:1500px;background:#fff}
          .pln-report th,.pln-report td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
          .pln-report .title{background:#fff200;font-weight:700;font-size:18px;line-height:1.05;color:#000}
          .pln-report .month{background:#ffffff;font-weight:700;font-size:14px;text-align:left}
          .pln-report .monthLabel{background:#ffffff;font-weight:700;font-size:14px;text-align:left}
          .pln-report .unit{background:#ead1dc;font-weight:700;font-size:24px;letter-spacing:.5px}
          .pln-report .blank{background:#ffffff}
          .pln-report .datecol{background:#e6efd5;font-weight:700;min-width:80px}
          .pln-report .sec-meter{background:#ead1dc;font-weight:700}
          .pln-report .sec-yellow{background:#fff200;font-weight:700}
          .pln-report .sub-meter{background:#cfe2f3;font-weight:700}
          .pln-report .sub-yellow{background:#fff200;font-weight:700}
          .pln-report .data-blue{background:#cfe2f3}
          .pln-report .data-yellow{background:#fff200}
          .pln-report .data-cost{background:#d9d2b6}
          .pln-report .sum{background:#cfe2f3;font-weight:700}
          .pln-report .sum-yellow{background:#d9d2b6;font-weight:700}
        </style>

        <div class="card-body p-2">
          <div class="pln-wrap">
            <table class="table table-sm pln-report">
              <thead>
                <tr><th class="title" colspan="11">PENCATATAN DAN PERHITUNGAN HARIAN<br>PEMAKAIAN ENERGI LISTRIK PLN</th></tr>
                <tr>
                  <th class="month">Bulan :</th>
                  <th class="monthLabel" colspan="10"><?= htmlspecialchars($monthLabel) ?></th>
                </tr>
                <tr>
                  <th class="blank"></th>
                  <th class="unit" colspan="4">GARDU 1</th>
                  <th class="blank" colspan="6"></th>
                </tr>
                <tr>
                  <th class="sec-meter" rowspan="3">TGL</th>
                  <th class="sec-meter" colspan="4">METER TERCATAT</th>
                  <th class="sec-yellow" rowspan="2">TOTAL DAYA TERPAKAI PERDAY (kW)</th>
                  <th class="sec-yellow" rowspan="2">TOTAL DAYA TERPAKAI PERJAM (kWh)</th>
                  <th class="sec-yellow" rowspan="2">Rp PER kWh</th>
                  <th class="sec-yellow" rowspan="2">BIAYA PEMAKAIAN LISTRIK PER DAY (Rp)</th>
                  <th class="sec-yellow" rowspan="2">EFISIENSI (%)</th>
                  <th class="sec-yellow" rowspan="2">KVA PLN PER JAM</th>
                </tr>
                <tr>
                  <th class="sub-meter">LVBP (kWh)</th>
                  <th class="sub-meter">VBP (kWh)</th>
                  <th class="sub-meter">KVARH</th>
                  <th class="sub-meter">Cos &Phi;</th>
                </tr>
                <tr>
                  <th class="sub-meter">EA.EXP 2</th>
                  <th class="sub-meter">EA.EXP 1</th>
                  <th class="sub-meter">ER.EXP</th>
                  <th class="sub-meter"></th>
                  <th class="sub-yellow"></th>
                  <th class="sub-yellow"></th>
                  <th class="sub-yellow"></th>
                  <th class="sub-yellow"></th>
                  <th class="sub-yellow"></th>
                  <th class="sub-yellow"></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="11">Tidak ada data pada rentang tanggal ini.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td class="datecol"><?= htmlspecialchars($fmtDay($r['tanggal'])) ?></td>
                      <td class="data-blue"><?= htmlspecialchars($fmtNum($r['lvbp_kwh'], 3)) ?></td>
                      <td class="data-blue"><?= htmlspecialchars($fmtNum($r['vbp_kwh'], 3)) ?></td>
                      <td class="data-blue"><?= htmlspecialchars($fmtNum($r['kvarh'], 3)) ?></td>
                      <td class="data-blue"><?= htmlspecialchars($fmtNum($r['cos_phi'], 3)) ?></td>
                      <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['total_daya_perday_kw'], 2)) ?></td>
                      <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['total_daya_perjam_kwh'], 2)) ?></td>
                      <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['rp_per_kwh'], 3)) ?></td>
                      <td class="data-cost"><?= htmlspecialchars($fmtNum($r['biaya_perday_rp'], 2)) ?></td>
                      <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['efisiensi_persen'], 2)) ?></td>
                      <td class="data-yellow"><?= htmlspecialchars($fmtNum($r['kva_pln_perjam'], 2)) ?></td>
                    </tr>
                  <?php endforeach; ?>

                  <tr class="sum">
                    <td>TOTAL</td>
                    <td></td><td></td><td></td><td></td>
                    <td><?= htmlspecialchars($fmtNum($tot['total_daya_perday_kw'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['total_daya_perjam_kwh'], 2)) ?></td>
                    <td></td>
                    <td class="sum-yellow"><?= htmlspecialchars($fmtNum($tot['biaya_perday_rp'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['efisiensi_persen'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($tot['kva_pln_perjam'], 2)) ?></td>
                  </tr>

                  <tr class="sum">
                    <td>RATA-RATA</td>
                    <td></td><td></td><td></td><td></td>
                    <td><?= htmlspecialchars($fmtNum($avg['total_daya_perday_kw'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['total_daya_perjam_kwh'], 2)) ?></td>
                    <td></td>
                    <td class="sum-yellow"><?= htmlspecialchars($fmtNum($avg['biaya_perday_rp'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['efisiensi_persen'], 2)) ?></td>
                    <td><?= htmlspecialchars($fmtNum($avg['kva_pln_perjam'], 2)) ?></td>
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
<div class="modal fade" id="modalCatatanListrikGardu" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Catatan Listrik Gardu Induk</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
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
    $('#modalCatatanListrikGardu').modal('show');
    $.post('get_catatan_listrik_gardu_induk.php',{start_date:s,end_date:e},function(resp){
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

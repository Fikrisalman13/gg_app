<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik_common.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik jika sudah tersedia.
requireView($conn, $menuId);

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startInput = kwhl_normalize_date($_POST['start_date'] ?? '');
    $endInput = kwhl_normalize_date($_POST['end_date'] ?? '');
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
if ($errorMsg === '' && kwhl_table_exists($conn)) {
    $tableName = kwhl_table_full_name($conn);
    if ($tableName === '') {
        $errorMsg = 'Tabel kwh_listrik belum tersedia.';
    } else {
        $sql = "SELECT * FROM {$tableName}
            WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
        $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
        if ($stmt === false) {
            $errorMsg = 'Gagal mengambil data report.';
        } else {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = kwhl_row_from_db($r);
            }
            sqlsrv_free_stmt($stmt);
        }
    }
}

$machines = kwhl_machine_defs();
$totAmp = [];
$totKwh = [];
$totBiaya = [];
foreach ($machines as $code => $m) {
    $totAmp[$m['amp_key']] = 0.0;
    $totKwh['kwh_' . $code] = 0.0;
    $totBiaya['biaya_' . $code] = 0.0;
}
$totAllKwh = 0.0;
$totAllBiaya = 0.0;
$tot21TKwh = 0.0;
$tot21TBiaya = 0.0;

foreach ($rows as $r) {
    foreach ($machines as $code => $m) {
        $totAmp[$m['amp_key']] += (float)($r[$m['amp_key']] ?? 0);
        $totKwh['kwh_' . $code] += (float)($r['kwh_' . $code] ?? 0);
        $totBiaya['biaya_' . $code] += (float)($r['biaya_' . $code] ?? 0);
    }
    $totAllKwh += (float)($r['total_kwh'] ?? 0);
    $totAllBiaya += (float)($r['total_biaya'] ?? 0);
    $tot21TKwh += (float)($r['kwh_21ton_actom'] ?? 0);
    $tot21TBiaya += (float)($r['biaya_21ton_actom'] ?? 0);
}

$count = count($rows);
$avgAmp = [];
$avgKwh = [];
$avgBiaya = [];
foreach ($machines as $code => $m) {
    $avgAmp[$m['amp_key']] = $count > 0 ? ($totAmp[$m['amp_key']] / $count) : 0;
    $avgKwh['kwh_' . $code] = $count > 0 ? ($totKwh['kwh_' . $code] / $count) : 0;
    $avgBiaya['biaya_' . $code] = $count > 0 ? ($totBiaya['biaya_' . $code] / $count) : 0;
}
$avgAllKwh = $count > 0 ? ($totAllKwh / $count) : 0;
$avgAllBiaya = $count > 0 ? ($totAllBiaya / $count) : 0;
$avg21TKwh = $count > 0 ? ($tot21TKwh / $count) : 0;
$avg21TBiaya = $count > 0 ? ($tot21TBiaya / $count) : 0;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER',
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h1 class="m-0">REPORT KWH LISTRIK</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/gg_app/pages/biaya_energi/kwh_listrik/kwh_listrik.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
            <form method="post" action="export_excel_report_kwh_listrik.php" class="m-0 p-0">
              <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
              <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
              <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
            </form>
            <button type="button" class="btn btn-info btn-sm" id="btnLihatCatatan"><i class="fas fa-sticky-note"></i> Lihat Catatan</button>
          </div>
        </div>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
      <?php else: ?>
        <style>
          .kwh-wrap { overflow-x: auto; overflow-y: visible; background: #f0f0f0; padding: 8px; border: 1px solid #cfcfcf; }
          .kwh-report { border-collapse: collapse; border-spacing: 0; font-size: 11px; min-width: 2400px; background: #fff; }
          .kwh-report th, .kwh-report td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 2px 4px; white-space: nowrap; }
          .kwh-report .title { background: #ffffff; font-weight: 700; font-size: 34px; }
          .kwh-report .month { background: #ffffff; font-weight: 700; font-size: 16px; text-align: left; }
          .kwh-report .datecol { background: #d9d9d9; font-weight: 700; min-width: 58px; }
          .kwh-report .grp-amp { background: #d9d9d9; font-weight: 700; }
          .kwh-report .grp-kwh { background: #ffc000; font-weight: 700; }
          .kwh-report .grp-biaya { background: #efefef; font-weight: 700; }
          .kwh-report .hdr { background: #fff200; font-weight: 700; }
          .kwh-report .unit { background: #fff; font-weight: 700; }
          .kwh-report .amp { background: #f5f5f5; }
          .kwh-report .kwh { background: #d9e2f3; }
          .kwh-report .biaya { background: #ece6db; }
          .kwh-report .sum td { background: #ffc000 !important; font-weight: 700; }
        </style>

        <div class="card">
          <div class="card-body p-2">
            <div class="kwh-wrap">
              <table class="table table-sm kwh-report">
                <thead>
                  <tr><th class="month" colspan="<?= 1 + (count($machines) * 3) + 2 + 2 ?>">BULAN : <?= htmlspecialchars($monthLabel) ?></th></tr>
                  <tr><th class="title" colspan="<?= 1 + (count($machines) * 3) + 2 + 2 ?>">PENCATATAN AMPERE DAN KWH METER</th></tr>
                  <tr>
                    <th class="datecol" rowspan="3">TANGGAL</th>
                    <th class="grp-amp" colspan="<?= count($machines) ?>">AMPERE</th>
                    <th class="grp-kwh" colspan="<?= count($machines) + 1 ?>">KWH</th>
                    <th class="grp-biaya" colspan="<?= count($machines) + 1 ?>">TOTAL BIAYA</th>
                    <th class="grp-kwh" rowspan="2">TOTAL KWH</th>
                    <th class="grp-biaya" rowspan="2">TOTAL BIAYA</th>
                  </tr>
                  <tr>
                    <?php foreach ($machines as $m): ?><th class="hdr"><?= htmlspecialchars($m['label']) ?></th><?php endforeach; ?>
                    <?php foreach ($machines as $m): ?><th class="hdr"><?= htmlspecialchars($m['label']) ?></th><?php endforeach; ?>
                    <th class="hdr">21TON ACTOM</th>
                    <?php foreach ($machines as $m): ?><th class="hdr"><?= htmlspecialchars($m['label']) ?></th><?php endforeach; ?>
                    <th class="hdr">21TON ACTOM</th>
                  </tr>
                  <tr>
                    <?php for ($i = 0; $i < count($machines); $i++): ?><th class="unit">A</th><?php endfor; ?>
                    <?php for ($i = 0; $i < count($machines); $i++): ?><th class="unit">kWh</th><?php endfor; ?>
                    <th class="unit">kWh</th>
                    <?php for ($i = 0; $i < count($machines); $i++): ?><th class="unit">Rp</th><?php endfor; ?>
                    <th class="unit">Rp</th>
                    <th class="unit">kWh</th>
                    <th class="unit">Rp</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= 1 + (count($machines) * 3) + 2 + 2 ?>">Tidak ada data pada rentang tanggal ini.</td></tr>
                  <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                      <?php
                        $rowTotalKwh = (float)($r['total_kwh'] ?? 0) + (float)($r['kwh_21ton_actom'] ?? 0);
                        $rowTotalBiaya = (float)($r['total_biaya'] ?? 0) + (float)($r['biaya_21ton_actom'] ?? 0);
                      ?>
                      <tr>
                        <td class="datecol"><?= htmlspecialchars(kwhl_format_day($r['tanggal'])) ?></td>
                        <?php foreach ($machines as $m): ?>
                          <td class="amp"><?= htmlspecialchars(kwhl_format_num($r[$m['amp_key']] ?? 0, 2)) ?></td>
                        <?php endforeach; ?>
                        <?php foreach ($machines as $code => $m): ?>
                          <td class="kwh"><?= htmlspecialchars(kwhl_format_num($r['kwh_' . $code] ?? 0, 3)) ?></td>
                        <?php endforeach; ?>
                        <td class="kwh"><?= htmlspecialchars(kwhl_format_num($r['kwh_21ton_actom'] ?? 0, 2)) ?></td>
                        <?php foreach ($machines as $code => $m): ?>
                          <td class="biaya"><?= htmlspecialchars(kwhl_format_num($r['biaya_' . $code] ?? 0, 0)) ?></td>
                        <?php endforeach; ?>
                        <td class="biaya"><?= htmlspecialchars(kwhl_format_num($r['biaya_21ton_actom'] ?? 0, 0)) ?></td>
                        <td class="kwh"><?= htmlspecialchars(kwhl_format_num($rowTotalKwh, 3)) ?></td>
                        <td class="biaya"><?= htmlspecialchars(kwhl_format_num($rowTotalBiaya, 0)) ?></td>
                      </tr>
                    <?php endforeach; ?>

                    <tr class="sum">
                      <td>TOTAL</td>
                      <?php foreach ($machines as $m): ?><td><?= htmlspecialchars(kwhl_format_num($totAmp[$m['amp_key']], 2)) ?></td><?php endforeach; ?>
                      <?php foreach ($machines as $code => $m): ?><td><?= htmlspecialchars(kwhl_format_num($totKwh['kwh_' . $code], 3)) ?></td><?php endforeach; ?>
                      <td><?= htmlspecialchars(kwhl_format_num($tot21TKwh, 2)) ?></td>
                      <?php foreach ($machines as $code => $m): ?><td><?= htmlspecialchars(kwhl_format_num($totBiaya['biaya_' . $code], 0)) ?></td><?php endforeach; ?>
                      <td><?= htmlspecialchars(kwhl_format_num($tot21TBiaya, 0)) ?></td>
                      <td><?= htmlspecialchars(kwhl_format_num($totAllKwh + $tot21TKwh, 3)) ?></td>
                      <td><?= htmlspecialchars(kwhl_format_num($totAllBiaya + $tot21TBiaya, 0)) ?></td>
                    </tr>

                    <tr class="sum">
                      <td>RATA-RATA</td>
                      <?php foreach ($machines as $m): ?><td><?= htmlspecialchars(kwhl_format_num($avgAmp[$m['amp_key']], 2)) ?></td><?php endforeach; ?>
                      <?php foreach ($machines as $code => $m): ?><td><?= htmlspecialchars(kwhl_format_num($avgKwh['kwh_' . $code], 3)) ?></td><?php endforeach; ?>
                      <td><?= htmlspecialchars(kwhl_format_num($avg21TKwh, 2)) ?></td>
                      <?php foreach ($machines as $code => $m): ?><td><?= htmlspecialchars(kwhl_format_num($avgBiaya['biaya_' . $code], 0)) ?></td><?php endforeach; ?>
                      <td><?= htmlspecialchars(kwhl_format_num($avg21TBiaya, 0)) ?></td>
                      <td><?= htmlspecialchars(kwhl_format_num($avgAllKwh + $avg21TKwh, 3)) ?></td>
                      <td><?= htmlspecialchars(kwhl_format_num($avgAllBiaya + $avg21TBiaya, 0)) ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="modal fade" id="modalCatatanKwhListrik" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Catatan KWH Listrik</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 text-muted" id="catatanRangeInfo"></div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="thead-light">
              <tr>
                <th style="width:130px;">Tanggal</th>
                <th>Catatan</th>
                <th style="width:120px;">Created By</th>
              </tr>
            </thead>
            <tbody id="catatanBody"><tr><td colspan="3">Memuat catatan...</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function () {
  $('#btnLihatCatatan').on('click', function () {
    var s = $('#start_date').val();
    var e = $('#end_date').val();
    if (!s || !e) {
      Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Pilih rentang tanggal terlebih dahulu.' });
      return;
    }
    $('#catatanRangeInfo').text('Rentang: ' + s + ' s/d ' + e);
    $('#catatanBody').html('<tr><td colspan="3">Memuat catatan...</td></tr>');
    $('#modalCatatanKwhListrik').modal('show');
    $.post('get_catatan_kwh_listrik.php', { start_date: s, end_date: e }, function (resp) {
      if (!resp || !resp.success) {
        $('#catatanBody').html('<tr><td colspan="3">' + ((resp && resp.message) ? resp.message : 'Gagal memuat catatan.') + '</td></tr>');
        return;
      }
      if (!resp.data || resp.data.length === 0) {
        $('#catatanBody').html('<tr><td colspan="3">Tidak ada catatan.</td></tr>');
        return;
      }
      var html = '';
      resp.data.forEach(function (it) {
        var safe = $('<div>').text(it.catatan || '').html().replace(/\n/g, '<br>');
        html += '<tr><td>' + (it.tanggal || '-') + '</td><td>' + safe + '</td><td>' + (it.creatby || '-') + '</td></tr>';
      });
      $('#catatanBody').html(html);
    }, 'json').fail(function () {
      $('#catatanBody').html('<tr><td colspan="3">Terjadi kesalahan saat memuat catatan.</td></tr>');
    });
  });
});
</script>

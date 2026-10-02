<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
include(__DIR__ . '/kwh_listrik2_common.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 236; // TODO: ganti ke MenuId khusus KWH Listrik 2 jika sudah tersedia
requireView($conn, $menuId);

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startInput = kwhl2_normalize_date($_POST['start_date'] ?? '');
    $endInput = kwhl2_normalize_date($_POST['end_date'] ?? '');
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
if ($errorMsg === '' && kwhl2_table_exists($conn)) {
    $tableName = kwhl2_table_full_name($conn);
    if ($tableName === '') {
        $errorMsg = 'Tabel kwh_listrik2 belum tersedia.';
    } else {
        $sql = "SELECT * FROM {$tableName}
            WHERE CAST(tanggal AS DATE) BETWEEN ? AND ?
            ORDER BY CAST(tanggal AS DATE) ASC, id ASC";
        $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
        if ($stmt === false) {
            $errorMsg = 'Gagal mengambil data report.';
        } else {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = kwhl2_row_from_db($r);
            }
            sqlsrv_free_stmt($stmt);
        }
    }
}

$machines = kwhl2_machine_defs();
$totKwh = [];
$totBiaya = [];
foreach ($machines as $code => $m) {
    $totKwh['kwh_' . $code] = 0.0;
    $totBiaya['biaya_' . $code] = 0.0;
}
$totAllKwh = 0.0;
$totAllBiaya = 0.0;

foreach ($rows as $r) {
    $rowKwhSum = 0.0;
    $rowBiayaSum = 0.0;
    foreach ($machines as $code => $m) {
        $kwhVal = (float)($r[$m['kwh_key']] ?? 0);
        $biayaVal = (float)($r[$m['biaya_key']] ?? 0);
        $totKwh['kwh_' . $code] += $kwhVal;
        $totBiaya['biaya_' . $code] += $biayaVal;
        $rowKwhSum += $kwhVal;
        $rowBiayaSum += $biayaVal;
    }
    $totAllKwh += $rowKwhSum;
    $totAllBiaya += $rowBiayaSum;
}

$count = count($rows);
$avgKwh = [];
$avgBiaya = [];
foreach ($machines as $code => $m) {
    $avgKwh['kwh_' . $code] = $count > 0 ? ($totKwh['kwh_' . $code] / $count) : 0;
    $avgBiaya['biaya_' . $code] = $count > 0 ? ($totBiaya['biaya_' . $code] / $count) : 0;
}
$avgAllKwh = $count > 0 ? ($totAllKwh / $count) : 0;
$avgAllBiaya = $count > 0 ? ($totAllBiaya / $count) : 0;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER',
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
$totalColumns = 1 + count($machines) + count($machines) + 1 + 1; // 1 (Tgl) + 7 (Kwh) + 7 (Biaya) + 1 (Total Kwh) + 1 (Total Biaya) = 17
?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h1 class="m-0">REPORT KWH LISTRIK 2</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="/gg_app/pages/biaya_energi/kwh_listrik2/kwh_listrik2.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center" style="min-height:42px;">
          <h3 class="card-title m-0"><i class="fas fa-filter mr-1"></i> Filter Rentang Tanggal</h3>
        </div>
        <div class="card-body">
          <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="row g-3 align-items-end">
              <div class="col-sm-6 col-lg-3">
                <label for="start_date" class="form-label font-weight-bold">Dari Tanggal</label>
                <input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start) ?>" required>
              </div>
              <div class="col-sm-6 col-lg-3">
                <label for="end_date" class="form-label font-weight-bold">Sampai Tanggal</label>
                <input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end) ?>" required>
              </div>
              <div class="col-sm-6 col-lg-3 d-flex align-items-end" style="gap:8px;">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm"><i class="fas fa-search"></i> Proses</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
              </div>
            </div>
          </form>
          <div class="d-flex mt-3" style="gap:8px;">
            <form method="post" action="export_excel_report_kwh_listrik2.php" class="m-0 p-0">
              <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
              <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
              <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel mr-1"></i> Export Excel</button>
            </form>
            <button type="button" class="btn btn-info btn-sm" id="btnLihatCatatan"><i class="fas fa-sticky-note mr-1"></i> Lihat Catatan</button>
          </div>
        </div>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger">Terjadi kesalahan: <?= htmlspecialchars($errorMsg) ?></div>
      <?php else: ?>
        <style>
          .kwh-wrap { overflow-x: auto; overflow-y: visible; background: #f0f0f0; padding: 8px; border: 1px solid #cfcfcf; }
          .kwh-report { border-collapse: collapse; border-spacing: 0; font-size: 11px; min-width: 1500px; background: #fff; width: 100%; }
          .kwh-report th, .kwh-report td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 3px 5px; white-space: nowrap; }
          .kwh-report .title { background: #ffffff; font-weight: 700; font-size: 26px; border: none !important; text-align: center; padding: 8px 0; }
          .kwh-report .month { background: #ffffff; font-weight: 700; font-size: 14px; text-align: left; border: none !important; padding: 6px 0; }
          .kwh-report .datecol { background: #d9d9d9; font-weight: 700; min-width: 65px; }
          .kwh-report .grp-kwh { background: #ffc000; font-weight: 700; color: #000; }
          .kwh-report .grp-biaya { background: #d9d9d9; font-weight: 700; color: #000; }
          .kwh-report .hdr { background: #ffff00; font-weight: 700; }
          .kwh-report .unit { background: #ffffff; font-weight: 700; }
          .kwh-report .kwh-cell { background: #d9e2f3; }
          .kwh-report .biaya-cell { background: #ffffff; }
          .kwh-report .total-kwh-cell { background: #d9e2f3; font-weight: 700; }
          .kwh-report .total-biaya-cell { background: #ffffff; font-weight: 700; }
          .kwh-report .sum td { background: #ffc000 !important; font-weight: 700; color: #000; }
        </style>

        <div class="card">
          <div class="card-body p-2">
            <div class="kwh-wrap">
              <table class="table table-sm kwh-report">
                <thead>
                  <tr>
                    <th class="month" colspan="<?= $totalColumns ?>">BULAN : <?= htmlspecialchars($monthLabel) ?></th>
                  </tr>
                  <tr>
                    <th class="title" colspan="<?= $totalColumns ?>">PENCATATAN AMPERE DAN KWH METER</th>
                  </tr>
                  <tr>
                    <th class="datecol" rowspan="3">TANGGAL</th>
                    <th class="grp-kwh" colspan="<?= count($machines) ?>">KWH / HARI</th>
                    <th class="grp-biaya" colspan="<?= count($machines) ?>">TOTAL BIAYA</th>
                    <th class="grp-kwh" rowspan="2">TOTAL KWH / HARI</th>
                    <th class="grp-biaya" rowspan="2">TOTAL BIAYA</th>
                  </tr>
                  <tr>
                    <?php foreach ($machines as $m): ?>
                      <th class="hdr"><?= htmlspecialchars($m['label']) ?></th>
                    <?php endforeach; ?>
                    <?php foreach ($machines as $m): ?>
                      <th class="hdr"><?= htmlspecialchars($m['label']) ?></th>
                    <?php endforeach; ?>
                  </tr>
                  <tr>
                    <?php for ($i = 0; $i < count($machines); $i++): ?>
                      <th class="unit">kWh/hari</th>
                    <?php endfor; ?>
                    <?php for ($i = 0; $i < count($machines); $i++): ?>
                      <th class="unit">Rp</th>
                    <?php endfor; ?>
                    <th class="unit">kWh/hari</th>
                    <th class="unit">Rp</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($rows)): ?>
                    <tr>
                      <td colspan="<?= $totalColumns ?>">Tidak ada data pada rentang tanggal ini.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                      <?php
                        $rowKwhSum = 0.0;
                        $rowBiayaSum = 0.0;
                        foreach ($machines as $code => $m) {
                            $rowKwhSum += (float)($r[$m['kwh_key']] ?? 0);
                            $rowBiayaSum += (float)($r[$m['biaya_key']] ?? 0);
                        }
                      ?>
                      <tr>
                        <td><?= htmlspecialchars(kwhl2_format_day($r['tanggal'])) ?></td>
                        <?php foreach ($machines as $code => $m): ?>
                          <td class="kwh-cell"><?= htmlspecialchars(kwhl2_format_num($r[$m['kwh_key']] ?? 0, 2)) ?></td>
                        <?php endforeach; ?>
                        <?php foreach ($machines as $code => $m): ?>
                          <td class="biaya-cell"><?= htmlspecialchars(kwhl2_format_num($r[$m['biaya_key']] ?? 0, 0)) ?></td>
                        <?php endforeach; ?>
                        <td class="total-kwh-cell"><?= htmlspecialchars(kwhl2_format_num($rowKwhSum, 2)) ?></td>
                        <td class="total-biaya-cell"><?= htmlspecialchars(kwhl2_format_num($rowBiayaSum, 0)) ?></td>
                      </tr>
                    <?php endforeach; ?>

                    <tr class="sum">
                      <td>TOTAL</td>
                      <?php foreach ($machines as $code => $m): ?>
                        <td><?= htmlspecialchars(kwhl2_format_num($totKwh['kwh_' . $code], 2)) ?></td>
                      <?php endforeach; ?>
                      <?php foreach ($machines as $code => $m): ?>
                        <td><?= htmlspecialchars(kwhl2_format_num($totBiaya['biaya_' . $code], 0)) ?></td>
                      <?php endforeach; ?>
                      <td><?= htmlspecialchars(kwhl2_format_num($totAllKwh, 2)) ?></td>
                      <td><?= htmlspecialchars(kwhl2_format_num($totAllBiaya, 0)) ?></td>
                    </tr>

                    <tr class="sum">
                      <td>RATA-RATA</td>
                      <?php foreach ($machines as $code => $m): ?>
                        <td><?= htmlspecialchars(kwhl2_format_num($avgKwh['kwh_' . $code], 2)) ?></td>
                      <?php endforeach; ?>
                      <?php foreach ($machines as $code => $m): ?>
                        <td><?= htmlspecialchars(kwhl2_format_num($avgBiaya['biaya_' . $code], 0)) ?></td>
                      <?php endforeach; ?>
                      <td><?= htmlspecialchars(kwhl2_format_num($avgAllKwh, 2)) ?></td>
                      <td><?= htmlspecialchars(kwhl2_format_num($avgAllBiaya, 0)) ?></td>
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

<div class="modal fade" id="modalCatatanKwhListrik2" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h5 class="modal-title">Catatan KWH Listrik 2</h5>
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
    $('#modalCatatanKwhListrik2').modal('show');
    $.post('get_catatan_kwh_listrik2.php', { start_date: s, end_date: e }, function (resp) {
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

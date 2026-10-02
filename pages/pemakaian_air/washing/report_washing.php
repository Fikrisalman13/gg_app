<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230; // TODO: ganti dengan MenuId washing di database
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
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
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

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Ahir, Total_Pemakaian, Operasional_Mesin, Pemakaian_rata2perjam, Keterangan, Catatan
        FROM dbo.washing_air
        WHERE Tanggal BETWEEN ? AND ?
          AND (
              Meter_Awal IS NOT NULL
              OR Meter_Ahir IS NOT NULL
              OR Total_Pemakaian IS NOT NULL
              OR Operasional_Mesin IS NOT NULL
              OR Pemakaian_rata2perjam IS NOT NULL
          )
        ORDER BY Tanggal ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$start, $end]);
if ($stmt === false) {
    $errorMsg = 'Query gagal: ' . print_r(sqlsrv_errors(), true);
}

$rows = [];
if ($stmt) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $r;
    }
    sqlsrv_free_stmt($stmt);
}

$grouped = [];
foreach ($rows as $r) {
    $key = ($r['Tanggal'] instanceof DateTime) ? $r['Tanggal']->format('Y-m-d') : (string)$r['Tanggal'];
    if (!isset($grouped[$key])) $grouped[$key] = [];
    $grouped[$key][] = $r;
}

$fmtDate = function ($val) {
    if ($val instanceof DateTime) return $val->format('d-M-y');
    if (is_string($val) && $val !== '') {
        $ts = strtotime($val);
        if ($ts) return date('d-M-y', $ts);
    }
    return '';
};
$fmtNum = function ($val, $dec = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $dec, '.', '');
};

$sumTotal = 0.0;
$sumOperasional = 0.0;
$sumRata = 0.0;
$cntTotal = 0;
$cntOperasional = 0;
$cntRata = 0;

foreach ($rows as $r) {
    $isOff = preg_match('/^\s*off\s*$/i', (string)($r['Keterangan'] ?? '')) === 1;
    if ($isOff) {
        continue;
    }

    if (is_numeric($r['Total_Pemakaian'])) {
        $sumTotal += (float)$r['Total_Pemakaian'];
        $cntTotal++;
    }
    if (is_numeric($r['Operasional_Mesin'])) {
        $sumOperasional += (float)$r['Operasional_Mesin'];
        $cntOperasional++;
    }
    if (is_numeric($r['Pemakaian_rata2perjam'])) {
        $sumRata += (float)$r['Pemakaian_rata2perjam'];
        $cntRata++;
    }
}

$avgTotal = $cntTotal ? ($sumTotal / $cntTotal) : null;
$avgOperasional = $cntOperasional ? ($sumOperasional / $cntOperasional) : null;
$avgRata = $cntRata ? ($sumRata / $cntRata) : null;

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
        <h1 class="m-0">Report Pemakaian Air Washing</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/washing/washing.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                            <div class="col-sm-6 col-lg-3 d-flex align-items-end" style="gap: 8px;">
                                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                    <i class="fas fa-search"></i> Proses
                                </button>
                                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                    <div class="d-flex mt-3" style="gap: 8px;">
                        <form method="post" action="export_excel_washing.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
                        </form>
                        <form method="post" action="export_pdf_washing.php" class="m-0 p-0">
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
                        <button type="button" class="btn btn-light btn-sm ml-auto" id="btnLihatCatatan" style="margin-left:auto;">
                            <i class="fas fa-sticky-note"></i> Lihat Catatan
                        </button>
                    </div>

                    <style>
                        .w3-report { border-color: #000; font-size: 12px; }
                        .w3-report th, .w3-report td { text-align: center; vertical-align: middle; border: 1px solid #000; padding: 3px 4px; }
                        .w3-report .top-head { background: #afc0d6; font-weight: 700; }
                        .w3-report .main-title { font-size: 24px; line-height: 1.05; font-weight: 800; letter-spacing: 0.3px; }
                        .w3-report .month-title { font-size: 22px; line-height: 1; font-weight: 800; letter-spacing: 0.4px; }
                        .w3-report .sub-head { background: #cfdeef; font-weight: 700; }
                        .w3-report .unit-head { background: #efe9e9; font-weight: 700; }
                        .w3-report .col-awal,
                        .w3-report .col-akhir { font-weight: 700; }
                        .w3-report .col-total { background: #b7c9de; font-weight: 700; }
                        .w3-report .left { text-align: left; }
                        .w3-report .total-row td, .w3-report .avg-row td { background: #d9d6c4; font-weight: 700; }
                    </style>

                    <div class="table-responsive">
                        <table class="table table-sm w3-report">
                            <thead>
                                <tr>
                                    <th rowspan="3" class="top-head" style="width:80px;">
                                        <img src="/gg_app/dist/img/sumlogo.png" alt="logo" style="width:48px;height:48px;object-fit:contain;display:block;margin:0 auto 4px auto;">
                                    </th>
                                    <th colspan="5" class="top-head main-title">PEMAKAIAN AIR DI MESIN WASHING</th>
                                    <th rowspan="2" class="top-head">KET</th>
                                </tr>
                                <tr>
                                    <th colspan="5" class="top-head month-title"><?= htmlspecialchars($monthLabel) ?></th>
                                </tr>
                                <tr>
                                    <th class="sub-head">AWAL</th>
                                    <th class="sub-head">AKHIR</th>
                                    <th class="sub-head">TOTAL PEMAKAIAN</th>
                                    <th class="sub-head">OPERASIONAL MESIN</th>
                                    <th class="sub-head">PEMAKAIAN RATA RATA PER JAM</th>
                                    <th rowspan="2" class="sub-head">CUT OFF JAM 09.00</th>
                                </tr>
                                <tr>
                                    <th class="unit-head">TANGGAL</th>
                                    <th class="unit-head">M<sup>3</sup></th>
                                    <th class="unit-head">M<sup>3</sup></th>
                                    <th class="unit-head">M<sup>3</sup></th>
                                    <th class="unit-head">PER JAM</th>
                                    <th class="unit-head">M<sup>3</sup></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($grouped)): ?>
                                    <tr><td colspan="7">Tidak ada data</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r): ?>
                                        <?php
                                            $tglObj = $r['Tanggal'] ?? null;
                                            $tanggal = ($tglObj instanceof DateTime) ? $tglObj->format('j') : date('j', strtotime((string)$tglObj));
                                            $isOff = preg_match('/^\s*off\s*$/i', (string)($r['Keterangan'] ?? '')) === 1;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)$tanggal) ?></td>
                                            <td class="col-awal"><?= htmlspecialchars($fmtNum($r['Meter_Awal'] ?? null, 2)) ?></td>
                                            <td class="col-akhir"><?= htmlspecialchars($fmtNum($r['Meter_Ahir'] ?? null, 2)) ?></td>
                                            <td class="col-total"><?= $isOff ? '' : htmlspecialchars($fmtNum($r['Total_Pemakaian'] ?? null, 2)) ?></td>
                                            <td><?= $isOff ? '' : htmlspecialchars($fmtNum($r['Operasional_Mesin'] ?? null, 2)) ?></td>
                                            <td><?= $isOff ? '' : htmlspecialchars($fmtNum($r['Pemakaian_rata2perjam'] ?? null, 2)) ?></td>
                                            <td class="left"><?= htmlspecialchars($r['Keterangan'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td>TOTAL</td>
                                        <td></td>
                                        <td></td>
                                        <td><?= htmlspecialchars($fmtNum($sumTotal, 2)) ?></td>
                                        <td><?= htmlspecialchars($fmtNum($sumOperasional, 2)) ?></td>
                                        <td><?= htmlspecialchars($fmtNum($sumRata, 2)) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr class="avg-row">
                                        <td>RATA-RATA</td>
                                        <td></td>
                                        <td></td>
                                        <td><?= htmlspecialchars($fmtNum($avgTotal, 2)) ?></td>
                                        <td><?= htmlspecialchars($fmtNum($avgOperasional, 2)) ?></td>
                                        <td><?= htmlspecialchars($fmtNum($avgRata, 2)) ?></td>
                                        <td></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<div class="modal fade" id="modalCatatanwashing" tabindex="-1" role="dialog" aria-labelledby="modalCatatanwashingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalCatatanwashingLabel">Catatan Washing</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-2 text-muted" id="catatanRangeInfo"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 170px;">Tanggal</th>
                                <th>Catatan</th>
                                <th style="width: 120px;">Created By</th>
                                <th style="width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="catatanBody">
                            <tr><td colspan="4">Memuat catatan...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<script>
$(document).ready(function() {
    var canEdit = <?= $canEdit ? 'true' : 'false' ?>;
    var canDelete = <?= $canDelete ? 'true' : 'false' ?>;

    $('#btnLihatCatatan').on('click', function() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        if (!startDate || !endDate) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Pilih rentang tanggal terlebih dahulu.' });
            return;
        }

        $('#catatanRangeInfo').text('Rentang: ' + startDate + ' s/d ' + endDate);
        $('#catatanBody').html('<tr><td colspan="4">Memuat catatan...</td></tr>');
        $('#modalCatatanwashing').modal('show');

        $.ajax({
            url: 'get_catatan_washing.php',
            type: 'POST',
            dataType: 'json',
            data: { start_date: startDate, end_date: endDate },
            success: function(resp) {
                if (!resp || !resp.success) {
                    var msg = (resp && resp.message) ? resp.message : 'Gagal memuat catatan.';
                    $('#catatanBody').html('<tr><td colspan="4">' + msg + '</td></tr>');
                    return;
                }
                if (!resp.data || resp.data.length === 0) {
                    $('#catatanBody').html('<tr><td colspan="4">Tidak ada catatan pada rentang ini.</td></tr>');
                    return;
                }
                var rows = '';
                resp.data.forEach(function(item) {
                    var safeCatatan = $('<div>').text(item.catatan || '').html().replace(/\n/g, '<br>');
                    var catatanAction = '-';
                    if (canEdit || canDelete) {
                        catatanAction = '<div class="btn-group btn-group-sm">';
                        if (canEdit) {
                            catatanAction += '<button class="btn btn-warning btn-edit-catatan" data-id="' + item.id + '" data-catatan="' + $('<div>').text(item.catatan || '').html() + '" title="Edit">' +
                                '<i class="fas fa-edit"></i>' +
                            '</button>';
                        }
                        if (canDelete) {
                            catatanAction += '<button class="btn btn-danger btn-delete-catatan" data-id="' + item.id + '" title="Hapus">' +
                                '<i class="fas fa-trash"></i>' +
                            '</button>';
                        }
                        catatanAction += '</div>';
                    }
                    rows += '<tr>' +
                        '<td>' + (item.tanggal || '-') + '</td>' +
                        '<td>' + safeCatatan + '</td>' +
                        '<td>' + (item.creatby || '-') + '</td>' +
                        '<td>' + catatanAction + '</td>' +
                        '</tr>';
                });
                $('#catatanBody').html(rows);
            },
            error: function() {
                $('#catatanBody').html('<tr><td colspan="4">Terjadi kesalahan saat memuat catatan.</td></tr>');
            }
        });
    });

    $(document).on('click', '.btn-edit-catatan', function() {
        var id = $(this).data('id');
        var current = $(this).data('catatan') || '';
        Swal.fire({
            title: 'Edit Catatan',
            input: 'textarea',
            inputValue: $('<div>').html(current).text(),
            inputPlaceholder: 'Tulis catatan...',
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            var newCatatan = (result.value || '').trim();
            if (newCatatan === '') {
                Swal.fire({ icon: 'warning', title: 'Catatan kosong', text: 'Catatan wajib diisi.' });
                return;
            }
            $.ajax({
                url: 'update_catatan_washing.php',
                type: 'POST',
                dataType: 'json',
                data: { id: id, catatan: newCatatan },
                success: function(resp) {
                    if (resp && resp.success) {
                        Swal.fire({ icon: 'success', title: 'Sukses', text: resp.message || 'Catatan diperbarui.' });
                        $('#btnLihatCatatan').trigger('click');
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal memperbarui catatan.' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat memperbarui catatan.' });
                }
            });
        });
    });

    $(document).on('click', '.btn-delete-catatan', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Catatan?',
            text: 'Catatan yang dihapus tidak dapat dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: 'delete_catatan_washing.php',
                type: 'POST',
                dataType: 'json',
                data: { id: id },
                success: function(resp) {
                    if (resp && resp.success) {
                        Swal.fire({ icon: 'success', title: 'Terhapus', text: resp.message || 'Catatan dihapus.' });
                        $('#btnLihatCatatan').trigger('click');
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: (resp && resp.message) ? resp.message : 'Gagal menghapus catatan.' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat menghapus catatan.' });
                }
            });
        });
    });
});
</script>


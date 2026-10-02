<?php
// report_jetdyeing.php - Laporan Pemakaian Air jetdyeing
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230; // TODO: ganti dengan MenuId jetdyeing di database
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
} else {
    // Default: pakai min/max tanggal di data agar langsung muncul
    $minMaxSql = "SELECT MIN(Tanggal) AS min_date, MAX(Tanggal) AS max_date FROM dbo.jetdyeing_air";
    $minMaxStmt = sqlsrv_query($conn, $minMaxSql);
    if ($minMaxStmt && ($mm = sqlsrv_fetch_array($minMaxStmt, SQLSRV_FETCH_ASSOC))) {
        if (!empty($mm['min_date'])) {
            $start = ($mm['min_date'] instanceof DateTime) ? $mm['min_date']->format('Y-m-d') : $mm['min_date'];
        }
        if (!empty($mm['max_date'])) {
            $end = ($mm['max_date'] instanceof DateTime) ? $mm['max_date']->format('Y-m-d') : $mm['max_date'];
        }
    }
    if ($minMaxStmt) {
        sqlsrv_free_stmt($minMaxStmt);
    }
}

$sql = "SELECT Tanggal, Meter_Awal, Meter_Ahir, Total_Pemakaian, Pemakaian_rata2perjam, Keterangan
        FROM dbo.jetdyeing_air
        WHERE Tanggal BETWEEN ? AND ?
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

$fmtDate = function ($val) {
    if ($val instanceof DateTime) return $val->format('Y-m-d');
    return $val ?: '';
};
$fmtNum = function ($val, $decimals) {
    if ($val === null || $val === '') return '-';
    if (!is_numeric($val)) return $val;
    return number_format((float)$val, $decimals, '.', ',');
};

$sumTotal = 0;
$sumRata = 0;
$cntTotal = 0;
$cntRata = 0;
foreach ($rows as $r) {
    if (is_numeric($r['Total_Pemakaian'])) {
        $sumTotal += (float)$r['Total_Pemakaian'];
        $cntTotal++;
    }
    if (is_numeric($r['Pemakaian_rata2perjam'])) {
        $sumRata += (float)$r['Pemakaian_rata2perjam'];
        $cntRata++;
    }
}
$avgTotal = $cntTotal ? $sumTotal / $cntTotal : null;
$avgRata = $cntRata ? $sumRata / $cntRata : null;

$monthMap = [
    'January' => 'JANUARI', 'February' => 'FEBRUARI', 'March' => 'MARET', 'April' => 'APRIL',
    'May' => 'MEI', 'June' => 'JUNI', 'July' => 'JULI', 'August' => 'AGUSTUS',
    'September' => 'SEPTEMBER', 'October' => 'OKTOBER', 'November' => 'NOVEMBER', 'December' => 'DESEMBER'
];
$monthEn = date('F', strtotime($start));
$monthLabel = ($monthMap[$monthEn] ?? strtoupper($monthEn)) . ' ' . date('Y', strtotime($start));
if (date('Y-m', strtotime($start)) !== date('Y-m', strtotime($end))) {
    $monthLabel = strtoupper(date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end)));
}
?>

<div class="content-wrapper">
    <section class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h1 class="m-0">Report Data Meter Air Jet Dyeing, Sizing dan LA</h1>
      </div>
      <div class="col-sm-6 text-right">
        <a href="/gg_app/pages/pemakaian_air/jetdyeing/jetdyeing.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                        <form method="post" action="export_excel_jetdyeing.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Export Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                        </form>
                        <form method="post" action="export_pdf_jetdyeing.php" class="m-0 p-0">
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
                <div class="card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
                        <h3 class="card-title m-0"><i class="fas fa-list mr-1"></i> Detail Report Jet Dyeing</h3>
                        <button type="button" class="btn btn-light btn-sm ml-auto" id="btnLihatCatatan" style="margin-left:auto;">
                            <i class="fas fa-sticky-note"></i> Lihat Catatan
                        </button>
                    </div>

                    <style>
                        .jetdyeing-report { border-color: #000; font-size: 12px; }
                        .jetdyeing-report th, .jetdyeing-report td { text-align: center; vertical-align: middle; border: 1px solid #000; padding: 3px 4px; }
                        .jetdyeing-report .title-row th,
                        .jetdyeing-report .month-row th { background: #afc0d6; font-weight: 700; }
                        .jetdyeing-report thead th { font-weight: 700; }
                        .jetdyeing-report .subhead-green { background: #cfdeef; color: #000; }
                        .jetdyeing-report .subhead-green-light { background: #cfdeef; color: #000; }
                        .jetdyeing-report .subhead-pink { background: #cfdeef; color: #000; }
                        .jetdyeing-report .subhead-blue { background: #cfdeef; color: #000; }
                        .jetdyeing-report .uom { background: #efe9e9 !important; color: #000; }
                        .jetdyeing-report .total-row td,
                        .jetdyeing-report .avg-row td { background: #d9d6c4; font-weight: 700; }
                        .jetdyeing-report .left { text-align: left; }
                        .jetdyeing-report .title-cell { position: relative; text-align: center !important; }
                        .jetdyeing-report .title-text { line-height: 1.1; }
                        .jetdyeing-report .ket-head { background: #afc0d6; }
                        .jetdyeing-report .cutoff { font-size: 11px; font-weight: 700; }
                        .jetdyeing-report .uom { font-size: 10px; font-weight: 700; }
                        .jetdyeing-report .col-blue { background: #b7c9de; }
                    </style>

                    <div class="table-responsive">
                        <table class="table table-sm jetdyeing-report">
                            <thead>
                                <tr class="title-row">
                                    <th colspan="5" class="title-cell">
                                        <span class="title-text">METER JET DYEING, SIZING DAN LA</span>
                                    </th>
                                    <th rowspan="4" class="ket-head">KET<br><span class="cutoff">CUT OFF JAM 09.00</span></th>
                                </tr>
                                <tr class="month-row">
                                    <th colspan="5"><?= htmlspecialchars($monthLabel) ?></th>
                                </tr>
                                <tr>
                                    <th class="subhead-green-light">TANGGAL</th>
                                    <th class="subhead-green">AWAL</th>
                                    <th class="subhead-green">AKHIR</th>
                                    <th class="subhead-green">TOTAL PEMAKAIAN</th>
                                    <th class="subhead-pink">PEMAKAIAN RATA RATA<br>PER JAM</th>
                                </tr>
                                <tr>
                                    <th class="uom subhead-green-light"></th>
                                    <th class="uom subhead-green">M<sup>3</sup></th>
                                    <th class="uom subhead-green">M<sup>3</sup></th>
                                    <th class="uom subhead-green">M<sup>3</sup></th>
                                    <th class="uom subhead-pink">M<sup>3</sup></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="6">Tidak ada data</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td class="subhead-green-light"><?= htmlspecialchars($fmtDate($r['Tanggal'])) ?></td>
                                            <td><?= htmlspecialchars($fmtNum($r['Meter_Awal'], 2)) ?></td>
                                            <td><?= htmlspecialchars($fmtNum($r['Meter_Ahir'], 2)) ?></td>
                                            <td class="col-blue"><?= htmlspecialchars($fmtNum($r['Total_Pemakaian'], 2)) ?></td>
                                            <td><?= htmlspecialchars($fmtNum($r['Pemakaian_rata2perjam'], 2)) ?></td>
                                            <td class="left"><?= htmlspecialchars($r['Keterangan'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="3">TOTAL</td>
                                        <td class="col-blue"><?= htmlspecialchars($fmtNum($sumTotal, 2)) ?></td>
                                        <td><?= htmlspecialchars($fmtNum($sumRata, 2)) ?></td>
                                        <td></td>
                                    </tr>
                                    <tr class="avg-row">
                                        <td colspan="3">RATA - RATA</td>
                                        <td class="col-blue"><?= htmlspecialchars($fmtNum($avgTotal, 2)) ?></td>
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

<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<!-- Modal Lihat Catatan jetdyeing -->
<div class="modal fade" id="modalCatatanjetdyeing" tabindex="-1" role="dialog" aria-labelledby="modalCatatanjetdyeingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalCatatanjetdyeingLabel">Catatan Jet Dyeing</h5>
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

    function formatDateTime(value) {
        if (!value) return '-';
        return value;
    }

    $('#btnLihatCatatan').on('click', function() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        if (!startDate || !endDate) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Pilih rentang tanggal terlebih dahulu.' });
            return;
        }

        $('#catatanRangeInfo').text('Rentang: ' + startDate + ' s/d ' + endDate);
        $('#catatanBody').html('<tr><td colspan="4">Memuat catatan...</td></tr>');
        $('#modalCatatanjetdyeing').modal('show');

        $.ajax({
            url: 'get_catatan_jetdyeing.php',
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
                        '<td>' + formatDateTime(item.tanggal) + '</td>' +
                        '<td>' + safeCatatan + '</td>' +
                        '<td>' + (item.creatby || '-') + '</td>' +
                        '<td>' + catatanAction + '</td>' +
                        '</tr>';
                });
                $('#catatanBody').html(rows);
            },
            error: function(xhr) {
                var msg = 'Terjadi kesalahan saat memuat catatan.';
                if (xhr && xhr.responseText) msg = xhr.responseText;
                $('#catatanBody').html('<tr><td colspan="4">' + msg + '</td></tr>');
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
                url: 'update_catatan_jetdyeing.php',
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
                error: function(xhr) {
                    var msg = 'Terjadi kesalahan saat memperbarui catatan.';
                    if (xhr && xhr.responseText) msg = xhr.responseText;
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });
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
                url: 'delete_catatan_jetdyeing.php',
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
                error: function(xhr) {
                    var msg = 'Terjadi kesalahan saat menghapus catatan.';
                    if (xhr && xhr.responseText) msg = xhr.responseText;
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });
                }
            });
        });
    });
});
</script>




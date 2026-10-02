<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$menuId = 230; // TODO: ganti dengan MenuId LAB di database
requireView($conn, $menuId);
$reportPermissions = userPermissions($conn, $menuId);
$canEdit = !empty($reportPermissions['CanEdit']) && (int)$reportPermissions['CanEdit'] === 1;
$canDelete = !empty($reportPermissions['CanDelete']) && (int)$reportPermissions['CanDelete'] === 1;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$start = date('Y-m-01');
$end = date('Y-m-d');
$errorMsg = '';

$normalizeDate = function ($value) {
    $value = trim((string)$value);
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
    } elseif (strtotime($startInput) > strtotime($endInput)) {
        $errorMsg = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
    } else {
        $start = $startInput;
        $end = $endInput;
    }
}

$rows = [];
if ($errorMsg === '') {
    $sql = "SELECT Id, CAST(Tanggal AS DATE) AS tanggal, Meter_Awal, Meter_Akhir, Total_Pemakaian, Keterangan
            FROM dbo.lab_air
            WHERE CAST(Tanggal AS DATE) BETWEEN ? AND ?
              AND (
                    Meter_Awal IS NOT NULL
                    OR Meter_Akhir IS NOT NULL
                    OR Total_Pemakaian IS NOT NULL
                    OR (Keterangan IS NOT NULL AND LTRIM(RTRIM(Keterangan)) <> '')
                  )
            ORDER BY CAST(Tanggal AS DATE) ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$start, $end]);
    if ($stmt === false) {
        $errorMsg = 'Gagal mengambil data report.';
    } else {
        while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dateObj = $r['tanggal'] ?? null;
            if ($dateObj instanceof DateTime) {
                $r['tanggal'] = $dateObj->format('Y-m-d');
            } elseif (is_string($dateObj) && $dateObj !== '') {
                $ts = strtotime($dateObj);
                $r['tanggal'] = $ts ? date('Y-m-d', $ts) : $dateObj;
            } else {
                $r['tanggal'] = '';
            }
            $rows[] = $r;
        }
        sqlsrv_free_stmt($stmt);
    }
}

$sumPemakaian = 0.0;
$countPemakaian = 0;
foreach ($rows as $r) {
    $nilai = $r['Total_Pemakaian'] ?? null;
    if ($nilai === null && is_numeric($r['Meter_Awal'] ?? null) && is_numeric($r['Meter_Akhir'] ?? null)) {
        $nilai = (float)$r['Meter_Akhir'] - (float)$r['Meter_Awal'];
    }
    if (is_numeric($nilai)) {
        $sumPemakaian += (float)$nilai;
        $countPemakaian++;
    }
}
$avgPemakaian = $countPemakaian > 0 ? ($sumPemakaian / $countPemakaian) : 0;

$fmtNum = function ($val, $dec = 2) {
    if ($val === null || $val === '' || !is_numeric($val)) return '';
    return number_format((float)$val, $dec, '.', ',');
};

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
                    <h1 class="m-0">REPORT METER AIR LAB</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="/gg_app/pages/pemakaian_air/lab/lab.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm">
                                    <i class="fas fa-search"></i> Proses
                                </button>
                                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                    <div class="d-flex mt-3" style="gap:8px;">
                        <form method="post" action="export_excel_lab.php" class="m-0 p-0">
                            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end) ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Export Excel"><i class="fas fa-file-excel"></i></button>
                        </form>
                        <form method="post" action="export_pdf_lab.php" class="m-0 p-0">
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
                        <button type="button" class="btn btn-light btn-sm ml-auto" id="btnLihatCatatan">
                            <i class="fas fa-sticky-note"></i> Lihat Catatan
                        </button>
                    </div>

                    <style>
                        .lab-report-wrap { overflow-x: auto; background: #efefef; padding: 10px; border: 1px solid #cfcfcf; }
                        .lab-report { border-collapse: collapse; border-spacing: 0; font-size: 12px; min-width: 560px; background: #fff; }
                        .lab-report th, .lab-report td { border: 1px solid #000; text-align: center; vertical-align: middle; padding: 3px 5px; white-space: nowrap; }
                        .lab-report .head { background: #bfb798; font-weight: 700; }
                        .lab-report .sub { background: #9ec3f0; font-weight: 700; }
                        .lab-report .unit { background: #d7ebff; font-weight: 700; }
                        .lab-report .no-col { background: #ffff00; font-weight: 700; }
                        .lab-report .data-gray { background: #efefef; }
                        .lab-report .data-blue { background: #d8e7f7; }
                        .lab-report .sum td { background: #ece9d6; font-weight: 700; }
                        .lab-month { font-size: 14px; font-weight: 700; margin-bottom: 6px; }
                    </style>

                    <div class="card-body">
                        <div class="lab-month"><?= htmlspecialchars($monthLabel) ?></div>
                        <div class="lab-report-wrap">
                            <table class="table table-sm lab-report mb-0">
                                <thead>
                                    <tr>
                                        <th class="no-col" rowspan="3" style="width:75px;">NO</th>
                                        <th class="head" colspan="3">LAB</th>
                                    </tr>
                                    <tr>
                                        <th class="sub">Awal</th>
                                        <th class="sub">Akhir</th>
                                        <th class="sub">Pemakaian</th>
                                    </tr>
                                    <tr>
                                        <th class="unit">(M<sup>3</sup>)</th>
                                        <th class="unit">(M<sup>3</sup>)</th>
                                        <th class="unit">(M<sup>3</sup>)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="4">Tidak ada data pada rentang tanggal ini.</td></tr>
                                    <?php else: ?>
                                        <?php $no = 1; ?>
                                        <?php foreach ($rows as $r): ?>
                                            <?php
                                                $pemakaian = $r['Total_Pemakaian'] ?? null;
                                                if ($pemakaian === null && is_numeric($r['Meter_Awal'] ?? null) && is_numeric($r['Meter_Akhir'] ?? null)) {
                                                    $pemakaian = (float)$r['Meter_Akhir'] - (float)$r['Meter_Awal'];
                                                }
                                            ?>
                                            <tr>
                                                <td class="no-col"><?= $no++ ?></td>
                                                <td class="data-gray"><?= htmlspecialchars($fmtNum($r['Meter_Awal'] ?? null, 3)) ?></td>
                                                <td class="data-gray"><?= htmlspecialchars($fmtNum($r['Meter_Akhir'] ?? null, 3)) ?></td>
                                                <td class="data-blue"><?= htmlspecialchars($fmtNum($pemakaian, 2)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="sum">
                                            <td class="no-col">Total</td>
                                            <td></td>
                                            <td></td>
                                            <td><?= htmlspecialchars($fmtNum($sumPemakaian, 2)) ?></td>
                                        </tr>
                                        <tr class="sum">
                                            <td class="no-col">Rata-Rata</td>
                                            <td></td>
                                            <td></td>
                                            <td><?= htmlspecialchars($fmtNum($avgPemakaian, 2)) ?></td>
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

<div class="modal fade" id="modalCatatanLab" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Catatan LAB</h5>
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
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
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
        $('#modalCatatanLab').modal('show');

        $.ajax({
            url: 'get_catatan_lab.php',
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
                url: 'update_catatan_lab.php',
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
                url: 'delete_catatan_lab.php',
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

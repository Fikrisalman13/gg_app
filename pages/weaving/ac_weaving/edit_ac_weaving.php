<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ac_weaving_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
weaving_require($conn, 'CanEdit');

$id = $_GET['id'] ?? '';
$tanggalParam = trim($_GET['tanggal'] ?? '');
$mesinParam = trim($_GET['mesin'] ?? '');

if (!ac_weaving_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel belum tersedia.';
    header('Location: ac_weaving.php');
    exit;
}

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = ac_weaving_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $mesinParam !== '') {
    $sheet = ac_weaving_resolve_sheet_by_keys($conn, $tanggalParam, $mesinParam);
}

if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: ac_weaving.php');
    exit;
}

$tanggal = ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$mesin = $sheet['Mesin'] ?? '';
$cells = ac_weaving_get_sheet_cells($conn, $tanggal, $mesin);
$hours = ac_weaving_hours();
$petugasOptions = ac_weaving_get_petugas_options($conn);

function format_indo_date_edit($value)
{
    $bulanIndo = [
        1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
        'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'
    ];
    $ts = strtotime($value);
    if (!$ts) return $value;
    $d = (int)date('d', $ts);
    $m = $bulanIndo[(int)date('n', $ts)] ?? strtoupper(date('F', $ts));
    $y = date('Y', $ts);
    return "$d $m $y";
}

$dateLabel = format_indo_date_edit($tanggal);

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h1 class="m-0">Edit Pengecekan AC Weaving</h1>
                </div>
                <div class="col-sm-4 text-right">
                    <a href="view_ac_weaving.php?id=<?= urlencode((string)$id) ?>" class="btn btn-info btn-sm mr-1"><i class="fas fa-eye"></i> View</a>
                    <a href="ac_weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center justify-content-between">
                    <h3 class="card-title m-0"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                    <div>
                        <a href="view_ac_weaving.php?id=<?= urlencode((string)$id) ?>" class="btn btn-info btn-sm mr-1"><i class="fas fa-eye"></i> View</a>
                        <a href="ac_weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <form id="formEditAcWeaving" autocomplete="off">
                    <input type="hidden" name="id" id="ac_sheet_id" value="<?= htmlspecialchars((string)($sheet['Id'] ?? $id)) ?>">
                    <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                    <input type="hidden" name="mesin" value="<?= htmlspecialchars($mesin) ?>">
                    <div class="card-body p-3">
                        <div class="edit-meta mb-3">
                            <div class="form-group mb-2">
                                <label>Tanggal</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars(ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?>" readonly>
                            </div>
                            <div class="form-group mb-2">
                                <label>AC</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($mesin) ?>" readonly>
                            </div>
                            <div class="form-group mb-2">
                                <label for="ac_petugas_default">Petugas Default</label>
                                <select class="form-control select2bs4" id="ac_petugas_default" name="petugas_default" required>
                                    <option value="">Pilih Petugas</option>
                                    <?php foreach ($petugasOptions as $petugasName): ?>
                                        <option value="<?= htmlspecialchars($petugasName) ?>" <?= ($sheet['Petugas'] ?? '') === $petugasName ? 'selected' : '' ?>><?= htmlspecialchars($petugasName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-2">
                                <label for="ac_shift_default">Shift Default</label>
                                <select class="form-control" id="ac_shift_default" name="shift_default">
                                    <option value="">Pilih Shift</option>
                                    <?php foreach (ac_weaving_shift_options() as $shiftName): ?>
                                        <option value="<?= htmlspecialchars($shiftName) ?>" <?= ($sheet['ShiftName'] ?? '') === $shiftName ? 'selected' : '' ?>><?= htmlspecialchars($shiftName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="ac-report-wrap mb-3">
                            <div class="ac-report-panel">
                                <table class="ac-report">
                                    <colgroup>
                                        <col style="width:11%">
                                        <col style="width:8%">
                                        <col style="width:11%">
                                        <col style="width:10%">
                                        <col style="width:9%">
                                        <col style="width:13%">
                                        <col style="width:26%">
                                        <col style="width:12%">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="sheet-title" colspan="8">PENGECEKAN TEMPERATUR AREA WEAVING</th>
                                        </tr>
                                        <tr>
                                            <th class="date-title" colspan="6" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars($dateLabel) ?></th>
                                            <th class="date-title text-center" colspan="2" style="white-space: nowrap;">SUM-FM-THK-WV-005</th>
                                        </tr>
                                        <tr>
                                            <th class="machine-title" colspan="8"><?= htmlspecialchars($mesin) ?></th>
                                        </tr>
                                        <tr>
                                            <th class="head">TANGGAL</th>
                                            <th class="head">JAM</th>
                                            <th class="head">pB1 Dew<br>Point</th>
                                            <th class="head">HUMIDITY</th>
                                            <th class="head">AMPER</th>
                                            <th class="head">DEFFERENTIAL<br>BEST AIR</th>
                                            <th class="head">PETUGAS</th>
                                            <th class="head">SHIFT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($hours as $hour): ?>
                                            <?php $cell = $cells[$hour] ?? []; ?>
                                            <tr>
                                                <td><?= htmlspecialchars(ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></td>
                                                <td><strong><?= htmlspecialchars(ac_weaving_display_hour($hour)) ?></strong></td>
                                                <td>
                                                    <input type="text"
                                                           class="ac-edit-cell ac-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][dew_point]"
                                                           value="<?= htmlspecialchars($cell['dew_point'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="ac-edit-cell ac-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][humidity]"
                                                           value="<?= htmlspecialchars($cell['humidity'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="ac-edit-cell ac-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][amper]"
                                                           value="<?= htmlspecialchars($cell['amper'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="ac-edit-cell ac-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][differential]"
                                                           value="<?= htmlspecialchars($cell['differential'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="ac-edit-cell text-left row-petugas"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][petugas]"
                                                           value="<?= htmlspecialchars($cell['petugas'] ?? '') ?>"
                                                           placeholder="Sesuai default">
                                                </td>
                                                <td>
                                                    <select class="ac-edit-cell row-shift text-center" name="rows[<?= htmlspecialchars($hour) ?>][shift]">
                                                        <option value="">Default</option>
                                                        <?php foreach (ac_weaving_shift_options() as $s): ?>
                                                            <option value="<?= htmlspecialchars($s) ?>" <?= ($cell['shift'] ?? '') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label for="ac_keterangan">Keterangan</label>
                            <textarea class="form-control" id="ac_keterangan" name="keterangan" rows="2" placeholder="Keterangan opsional..."><?= htmlspecialchars($sheet['Keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="ac_weaving.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
.edit-meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(160px, 1fr));
    gap: 12px;
}
.ac-report-wrap {
    overflow: auto;
    background: #f8fafc;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}
.ac-report-panel {
    background: #fff;
    border: 1px solid #64748b;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.ac-report {
    width: 100%;
    min-width: 820px;
    table-layout: fixed;
    border-collapse: collapse;
    border-spacing: 0;
    background: #fff;
    font-size: 11px;
}
.ac-report th,
.ac-report td {
    border: 1px solid #111;
    text-align: center;
    vertical-align: middle;
    padding: 3px 4px;
    white-space: nowrap;
    height: 24px;
}
.ac-report .sheet-title {
    background: #fff;
    font-size: 12px;
    font-weight: 800;
    text-align: center;
    padding: 5px;
}
.ac-report .date-title {
    background: #fff;
    font-weight: 700;
    text-align: left;
    font-size: 11px;
}
.ac-report .date-title.text-center {
    text-align: center;
}
.ac-report .machine-title {
    background: #fff;
    text-align: left;
    font-weight: 800;
    font-size: 11px;
}
.ac-report .head {
    background: #9dc3e6;
    font-weight: 700;
    color: #000;
    font-size: 10px;
    line-height: 1.15;
}
.ac-report tbody tr:nth-child(even) td {
    background: #fbfdff;
}
.ac-report .ac-edit-cell {
    width: 100%;
    height: 26px;
    padding: 1px 4px;
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    font-size: 11px;
    background: #fff;
}
.ac-report .ac-edit-cell:focus {
    border-color: #3b82f6;
    outline: none;
    background: #eff6ff;
}
@media (max-width: 767.98px) {
    .edit-meta {
        display: block;
    }
    .ac-report {
        font-size: 10px;
    }
}
</style>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('#ac_petugas_default').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Cari / pilih petugas',
            allowClear: true
        });
    }

    $(document).on('input', '.ac-num-input', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
    });

    $('#formEditAcWeaving').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
        $.ajax({
            url: 'update_ac_weaving.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire('Berhasil', res.message, 'success').then(function() {
                        window.location.href = 'ac_weaving.php';
                    });
                } else {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                    Swal.fire('Gagal', res.message || 'Data gagal diperbarui.', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                Swal.fire('Gagal', 'Terjadi kesalahan saat memperbarui data.', 'error');
            }
        });
    });
});
</script>

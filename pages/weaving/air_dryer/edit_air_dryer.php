<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/air_dryer_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
weaving_require($conn, 'CanEdit');

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id) || !air_dryer_table_exists($conn)) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: air_dryer.php');
    exit;
}

$sheet = air_dryer_resolve_sheet($conn, (int)$id);
if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: air_dryer.php');
    exit;
}

$tanggal = air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$tanggalDisplay = air_dryer_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y');
$hours = air_dryer_hours();
$cells = air_dryer_get_sheet_cells($conn, $tanggal);
$petugasOptions = air_dryer_get_petugas_options($conn);

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
                    <h1 class="m-0">Edit Pencatatan Air Dryer Weaving</h1>
                </div>
                <div class="col-sm-4 text-right">
                    <a href="view_air_dryer.php?id=<?= urlencode((string)$id) ?>" class="btn btn-info btn-sm mr-1"><i class="fas fa-eye"></i> View</a>
                    <a href="air_dryer.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                        <a href="view_air_dryer.php?id=<?= urlencode((string)$id) ?>" class="btn btn-info btn-sm mr-1"><i class="fas fa-eye"></i> View</a>
                        <a href="air_dryer.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <form id="formEditAirDryer" autocomplete="off">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                    <div class="card-body p-3">
                        <div class="edit-meta mb-3">
                            <div class="form-group mb-2">
                                <label>Tanggal</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($tanggalDisplay) ?>" readonly>
                            </div>
                            <div class="form-group mb-2">
                                <label for="air_petugas_default">Petugas Default</label>
                                <select class="form-control select2bs4" id="air_petugas_default" name="petugas_default" required>
                                    <option value="">Pilih Petugas</option>
                                    <?php foreach ($petugasOptions as $petugasName): ?>
                                        <option value="<?= htmlspecialchars($petugasName) ?>" <?= ($sheet['Petugas'] ?? '') === $petugasName ? 'selected' : '' ?>><?= htmlspecialchars($petugasName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="air-report-wrap mb-3">
                            <div class="air-report-panel">
                                <table class="air-report-table">
                                    <colgroup>
                                        <col style="width: 8%;">
                                        <col style="width: 9%;">
                                        <col style="width: 9%;">
                                        <col style="width: 9%;">
                                        <col style="width: 9%;">
                                        <col style="width: 9%;">
                                        <col style="width: 9%;">
                                        <col style="width: 9%;">
                                        <col style="width: 9%;">
                                        <col style="width: 20%;">
                                    </colgroup>
                                    <thead>
                                        <tr><th colspan="10" class="sheet-title">PENCATATAN AIR DRYER WEAVING</th></tr>
                                        <tr>
                                            <th colspan="9" class="date-title" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars($tanggalDisplay) ?></th>
                                            <th class="date-title text-center" style="white-space: nowrap;">SUM-FM-THK-WV-017</th>
                                        </tr>
                                        <tr>
                                            <th class="head-blue" rowspan="3">Jam<br>Pengecekan</th>
                                            <th class="head-blue" colspan="4">Air Dryer 1</th>
                                            <th class="head-blue" colspan="4">Air Dryer 2</th>
                                            <th class="head-blue" rowspan="3">Petugas</th>
                                        </tr>
                                        <tr>
                                            <th class="head-blue" colspan="2">Temperatur Air</th>
                                            <th class="head-blue" colspan="2">Tekanan Air</th>
                                            <th class="head-blue" colspan="2">Temperatur Air</th>
                                            <th class="head-blue" colspan="2">Tekanan Air</th>
                                        </tr>
                                        <tr>
                                            <th class="head-blue">IN (&deg;C)</th><th class="head-blue">OUT (&deg;C)</th>
                                            <th class="head-blue">IN (BAR)</th><th class="head-blue">OUT (BAR)</th>
                                            <th class="head-blue">IN (&deg;C)</th><th class="head-blue">OUT (&deg;C)</th>
                                            <th class="head-blue">IN (BAR)</th><th class="head-blue">OUT (BAR)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($hours as $hour): ?>
                                            <?php $cell = $cells[$hour] ?? []; ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($hour) ?></strong>
                                                    <input type="hidden" name="rows[<?= htmlspecialchars($hour) ?>][jam]" value="<?= htmlspecialchars($hour) ?>">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad1_temp_in]"
                                                           value="<?= htmlspecialchars($cell['ad1_temp_in'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad1_temp_out]"
                                                           value="<?= htmlspecialchars($cell['ad1_temp_out'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad1_press_in]"
                                                           value="<?= htmlspecialchars($cell['ad1_press_in'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad1_press_out]"
                                                           value="<?= htmlspecialchars($cell['ad1_press_out'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad2_temp_in]"
                                                           value="<?= htmlspecialchars($cell['ad2_temp_in'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad2_temp_out]"
                                                           value="<?= htmlspecialchars($cell['ad2_temp_out'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad2_press_in]"
                                                           value="<?= htmlspecialchars($cell['ad2_press_in'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell air-num-input text-center"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][ad2_press_out]"
                                                           value="<?= htmlspecialchars($cell['ad2_press_out'] ?? '') ?>"
                                                           placeholder="-">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="air-edit-cell text-left row-petugas"
                                                           name="rows[<?= htmlspecialchars($hour) ?>][petugas]"
                                                           value="<?= htmlspecialchars($cell['petugas'] ?? '') ?>"
                                                           placeholder="Sesuai default">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label for="air_keterangan">Keterangan</label>
                            <textarea class="form-control" id="air_keterangan" name="keterangan" rows="2" placeholder="Keterangan opsional..."><?= htmlspecialchars($sheet['Keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="air_dryer.php" class="btn btn-secondary">Batal</a>
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
    grid-template-columns: repeat(2, minmax(200px, 1fr));
    gap: 16px;
    max-width: 600px;
}
.air-report-wrap {
    overflow: auto;
    background: #f8fafc;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}
.air-report-panel {
    background: #fff;
    border: 1px solid #64748b;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
    min-width: 1060px;
}
.air-report-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    table-layout: fixed;
    font-size: 11px;
}
.air-report-table th, .air-report-table td {
    border: 1px solid #111;
    text-align: center;
    vertical-align: middle;
    padding: 3px 4px;
    height: 26px;
}
.air-report-table .sheet-title {
    font-size: 14px;
    font-weight: 800;
    background: #fff;
}
.air-report-table .date-title {
    text-align: left;
    background: #fff;
    font-weight: 700;
}
.air-report-table .date-title.text-center {
    text-align: center;
}
.air-report-table .head-blue {
    background: #9dc3e6;
    font-weight: 700;
    color: #000;
    text-transform: uppercase;
    font-size: 10px;
    line-height: 1.15;
}
.air-edit-cell {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    padding: 2px 4px;
    font-size: 11px;
    height: 24px;
}
.air-edit-cell:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 1px #3b82f6;
    background: #eff6ff;
}
@media (max-width: 767.98px) {
    .edit-meta {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2bs4').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    function sanitizeDecimalInput(value) {
        value = String(value || '').replace(/,/g, '.').replace(/[^0-9.-]/g, '');
        var negative = value.charAt(0) === '-';
        value = value.replace(/-/g, '');
        var hasDecimal = value.indexOf('.') !== -1;
        var parts = value.split('.');
        var integerPart = parts.shift().replace(/\D/g, '').slice(0, 8);
        var decimalPart = parts.join('').replace(/\D/g, '').slice(0, 2);
        return (negative ? '-' : '') + integerPart + (hasDecimal ? '.' + decimalPart : '');
    }

    $(document).on('keypress', '.air-num-input', function(e) {
        if (e.ctrlKey || e.metaKey || e.altKey || e.which < 32) return;
        if (!/[0-9.,-]/.test(String.fromCharCode(e.which))) e.preventDefault();
    });

    $(document).on('input change', '.air-num-input', function() {
        var sanitized = sanitizeDecimalInput(this.value);
        if (this.value !== sanitized) this.value = sanitized;
    });

    $('#formEditAirDryer').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'update_air_dryer.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.href = 'view_air_dryer.php?id=<?= urlencode((string)$id) ?>';
                    });
                } else {
                    Swal.fire('Gagal', res.message || 'Gagal menyimpan perubahan.', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                }
            },
            error: function() {
                Swal.fire('Gagal', 'Terjadi kesalahan saat memproses data.', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
            }
        });
    });
});
</script>

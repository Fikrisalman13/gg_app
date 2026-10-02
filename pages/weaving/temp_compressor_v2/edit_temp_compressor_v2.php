<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/temp_compressor_v2_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
weaving_require($conn, 'CanEdit');

$id = $_GET['id'] ?? '';
$tanggalParam = trim($_GET['tanggal'] ?? '');
$weavingParam = intval($_GET['weaving'] ?? 0);
$compressorParam = intval($_GET['compressor_no'] ?? 0);

if (!temp_compressor_v2_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel dbo.temp_compressor_v2 belum tersedia.';
    header('Location: temp_compressor_v2.php');
    exit;
}

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = temp_compressor_v2_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $weavingParam > 0 && $compressorParam > 0) {
    $sheet = temp_compressor_v2_resolve_sheet_by_params($conn, $tanggalParam, $weavingParam, $compressorParam);
}

if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: temp_compressor_v2.php');
    exit;
}

$resolvedId = (int)($sheet['Id'] ?? $id);
$tanggal = temp_compressor_v2_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$tanggalDisplay = temp_compressor_v2_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y');
$weaving = (int)($sheet['Weaving'] ?? 1);
$compressorNo = (int)($sheet['Compressor_No'] ?? 1);
$hours = temp_compressor_v2_hours();
$cells = temp_compressor_v2_get_sheet_cells($conn, $tanggal, $weaving, $compressorNo);
$pelaksanaOptions = temp_compressor_v2_get_pelaksana_options($conn);

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
                    <h1 class="m-0">Edit Check Sheet Kompressor Sullair (V2)</h1>
                </div>
                <div class="col-sm-4 text-right">
                    <a href="view_temp_compressor_v2.php?id=<?= urlencode((string)$resolvedId) ?>&tanggal=<?= urlencode($tanggal) ?>&weaving=<?= $weaving ?>&compressor_no=<?= $compressorNo ?>" class="btn btn-info btn-sm mr-1"><i class="fas fa-eye"></i> View</a>
                    <a href="temp_compressor_v2.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                        <a href="view_temp_compressor_v2.php?id=<?= urlencode((string)$resolvedId) ?>&tanggal=<?= urlencode($tanggal) ?>&weaving=<?= $weaving ?>&compressor_no=<?= $compressorNo ?>" class="btn btn-info btn-sm mr-1"><i class="fas fa-eye"></i> View</a>
                        <a href="temp_compressor_v2.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <form id="formEditTempCompressor" autocomplete="off">
                    <input type="hidden" name="id" id="compressor_sheet_id" value="<?= htmlspecialchars((string)$resolvedId) ?>">
                    <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                    <input type="hidden" name="weaving" value="<?= $weaving ?>">
                    <input type="hidden" name="compressor_no" value="<?= $compressorNo ?>">

                    <div class="card-body p-3">
                        <div class="edit-meta mb-3">
                            <div class="form-group mb-2">
                                <label>Tanggal</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($tanggalDisplay) ?>" readonly>
                            </div>
                            <div class="form-group mb-2">
                                <label>Weaving</label>
                                <input type="text" class="form-control" value="Weaving <?= $weaving ?>" readonly>
                            </div>
                            <div class="form-group mb-2">
                                <label>Compressor No</label>
                                <input type="text" class="form-control" value="Compressor <?= $compressorNo ?>" readonly>
                            </div>
                            <div class="form-group mb-2">
                                <label for="compressor_pelaksana_default">Pelaksana Default</label>
                                <select class="form-control select2bs4" id="compressor_pelaksana_default" name="pelaksana_default" required>
                                    <option value="">Pilih Pelaksana</option>
                                    <?php foreach ($pelaksanaOptions as $pName): ?>
                                        <option value="<?= htmlspecialchars($pName) ?>" <?= ($sheet['Pelaksana'] ?? '') === $pName ? 'selected' : '' ?>><?= htmlspecialchars($pName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="compressor-report-wrap mb-3">
                            <table class="compressor-report-table">
                                <colgroup>
                                    <col style="width: 60px;">
                                    <col style="width: 55px;">
                                    <col style="width: 55px;">
                                    <col style="width: 55px;">
                                    <col style="width: 55px;">
                                    <col style="width: 55px;">
                                    <col style="width: 60px;">
                                    <col style="width: 70px;">
                                    <col style="width: 60px;">
                                    <col style="width: 60px;">
                                    <col style="width: 60px;">
                                    <col style="width: 60px;">
                                    <col style="width: 180px;">
                                </colgroup>
                                <thead>
                                    <tr><th colspan="13" class="sheet-title">CHECK SHEET KOMPRESSOR SULLAIR</th></tr>
                                    <tr>
                                        <th colspan="6" class="meta-title">
                                            COMPRESSOR NO: <strong><?= $compressorNo ?></strong> (1 / 2 / 3) &nbsp;&nbsp;&nbsp;&nbsp; WEAVING: <strong><?= $weaving ?></strong> (1 / 2)
                                        </th>
                                        <th colspan="5" class="meta-title text-center">
                                            TANGGAL: <strong><?= htmlspecialchars($tanggalDisplay) ?></strong>
                                        </th>
                                        <th colspan="2" class="meta-title text-center" style="white-space: nowrap;">
                                            SUM-FM-THK-016
                                        </th>
                                    </tr>
                                    <tr>
                                        <th class="head-blue" rowspan="2">JAM</th>
                                        <th class="head-blue" colspan="2">PRESSURE (BAR)</th>
                                        <th class="head-blue" colspan="3">TEMPERATURE</th>
                                        <th class="head-blue" rowspan="2">DRYER<br>(&deg;C)</th>
                                        <th class="head-blue" rowspan="2">ARUS<br>LISTRIK (A)</th>
                                        <th class="head-blue" colspan="4">AIR COOLING</th>
                                        <th class="head-blue" rowspan="2">PELAKSANA</th>
                                    </tr>
                                    <tr>
                                        <th class="head-blue">P1</th>
                                        <th class="head-blue">P2</th>
                                        <th class="head-blue">T1</th>
                                        <th class="head-blue">T2</th>
                                        <th class="head-blue">T3</th>
                                        <th class="head-blue">PRESS. IN</th>
                                        <th class="head-blue">PRESS. OUT</th>
                                        <th class="head-blue">TEMP. IN</th>
                                        <th class="head-blue">TEMP. OUT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hours as $hour): ?>
                                        <?php $cell = $cells[$hour] ?? []; ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars(temp_compressor_v2_display_hour($hour)) ?></strong>
                                                <input type="hidden" name="rows[<?= htmlspecialchars($hour) ?>][jam]" value="<?= htmlspecialchars($hour) ?>">
                                            </td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][pressure_p1]" value="<?= htmlspecialchars($cell['pressure_p1'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][pressure_p2]" value="<?= htmlspecialchars($cell['pressure_p2'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][temp_t1]" value="<?= htmlspecialchars($cell['temp_t1'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][temp_t2]" value="<?= htmlspecialchars($cell['temp_t2'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][temp_t3]" value="<?= htmlspecialchars($cell['temp_t3'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][dryer_c]" value="<?= htmlspecialchars($cell['dryer_c'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][arus_a]" value="<?= htmlspecialchars($cell['arus_a'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][press_in]" value="<?= htmlspecialchars($cell['press_in'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][press_out]" value="<?= htmlspecialchars($cell['press_out'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][temp_in]" value="<?= htmlspecialchars($cell['temp_in'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell compressor-num-input text-center" name="rows[<?= htmlspecialchars($hour) ?>][temp_out]" value="<?= htmlspecialchars($cell['temp_out'] ?? '') ?>" placeholder="-"></td>
                                            <td><input type="text" class="compressor-edit-cell text-left row-pelaksana" name="rows[<?= htmlspecialchars($hour) ?>][pelaksana]" value="<?= htmlspecialchars($cell['pelaksana'] ?? '') ?>" placeholder="Sesuai default"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group mb-0">
                            <label for="compressor_keterangan">Keterangan</label>
                            <textarea class="form-control" id="compressor_keterangan" name="keterangan" rows="2" placeholder="Keterangan opsional..."><?= htmlspecialchars($sheet['Keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="temp_compressor_v2.php" class="btn btn-secondary">Batal</a>
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
    grid-template-columns: repeat(4, minmax(140px, 1fr));
    gap: 16px;
    max-width: 900px;
}
.compressor-report-wrap {
    overflow: auto;
    background: #f8fafc;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}
.compressor-report-table {
    border-collapse: collapse;
    background: #fff;
    width: 1050px;
    table-layout: fixed;
    font-size: 12px;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.compressor-report-table th, .compressor-report-table td {
    border: 1px solid #111;
    text-align: center;
    vertical-align: middle;
    padding: 3px 4px;
    height: 26px;
}
.compressor-report-table .sheet-title {
    font-size: 14px;
    font-weight: 800;
    background: #fff;
}
.compressor-report-table .meta-title {
    font-size: 11px;
    background: #fff;
    padding: 4px 6px;
    text-align: left;
}
.compressor-report-table .head-blue {
    background: #9dc3e6;
    font-weight: 700;
    color: #000;
    text-transform: uppercase;
    font-size: 10px;
    line-height: 1.15;
}
.compressor-edit-cell {
    width: 100%;
    height: 24px;
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    padding: 1px 3px;
    font-size: 11px;
    background: #fff;
}
.compressor-edit-cell:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 1px #3b82f6;
}
.compressor-edit-cell.row-pelaksana {
    font-size: 11px;
}
</style>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('#compressor_pelaksana_default').select2({theme: 'bootstrap4', width: '100%'});
    }

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

    $('.compressor-report-table').on('keypress', '.compressor-num-input', function(e) {
        if (e.ctrlKey || e.metaKey || e.altKey || e.which < 32) return;
        if (!/[0-9.,-]/.test(String.fromCharCode(e.which))) e.preventDefault();
    });

    $('.compressor-report-table').on('input change', '.compressor-num-input', function() {
        var s = sanitizeDecimalInput(this.value);
        if (this.value !== s) this.value = s;
    });

    $('#formEditTempCompressor').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'update_temp_compressor_v2.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function() {
                        window.location.href = 'temp_compressor_v2.php';
                    });
                } else {
                    Swal.fire('Gagal', res.message || 'Terjadi kesalahan saat menyimpan.', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                Swal.fire('Gagal', 'Terjadi kesalahan sistem.', 'error');
            }
        });
    });
});
</script>

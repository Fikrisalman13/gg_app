<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/dryer_weaving_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
weaving_require($conn, 'CanEdit');

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id) || !dryer_weaving_table_exists($conn)) {
    $_SESSION['error'] = 'Data tidak valid.';
    header('Location: dryer_weaving.php');
    exit;
}

$sheet = dryer_weaving_resolve_sheet($conn, (int)$id);
if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: dryer_weaving.php');
    exit;
}

$tanggal = dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$cells = dryer_weaving_get_sheet_cells($conn, $tanggal, $sheet['Dryer_No'], $sheet['Ct_No']);
$shifts = dryer_weaving_shifts_for_sheet($cells);
$petugasByHour = dryer_weaving_petugas_by_hour_for_sheet($cells);
$items = dryer_weaving_items();
$hours = dryer_weaving_hours();
$petugasOptions = dryer_weaving_get_petugas_options($conn);

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Edit Dryer Weaving</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                    <a href="dryer_weaving.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <form id="formEditDryerWeaving" autocomplete="off">
                    <input type="hidden" name="id" id="dryer_sheet_id" value="<?= htmlspecialchars((string)$id) ?>">
                    <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                    <input type="hidden" name="dryer_no" value="<?= htmlspecialchars($sheet['Dryer_No'] ?? '') ?>">
                    <input type="hidden" name="ct_no" value="<?= htmlspecialchars($sheet['Ct_No'] ?? '') ?>">
                    <div class="card-body">
                        <div class="edit-meta">
                            <div class="form-group">
                                <label>Tanggal</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars(dryer_weaving_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Dryer No</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($sheet['Dryer_No'] ?? '') ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>CT No</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($sheet['Ct_No'] ?? '') ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="dryer_petugas_default">Petugas</label>
                                <select class="form-control select2bs4" id="dryer_petugas_default" name="petugas_default">
                                    <option value="">Pilih Petugas</option>
                                    <?php foreach ($petugasOptions as $petugasName): ?>
                                        <option value="<?= htmlspecialchars($petugasName) ?>" <?= ($sheet['Petugas'] ?? '') === $petugasName ? 'selected' : '' ?>><?= htmlspecialchars($petugasName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="dryer_shift_default">Shift</label>
                                <select class="form-control" id="dryer_shift_default" name="shift_default">
                                    <option value="">Pilih Shift</option>
                                    <?php foreach (dryer_weaving_shift_options() as $shiftName): ?>
                                        <option value="<?= htmlspecialchars($shiftName) ?>" <?= ($sheet['ShiftName'] ?? '') === $shiftName ? 'selected' : '' ?>><?= htmlspecialchars($shiftName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="dryer-mobile-hint mb-2">Geser tabel ke kiri/kanan untuk mengubah jam pemeriksaan.</div>
                        <div class="dryer-edit-wrap">
                            <table class="dryer-edit-table">
                                <thead>
                                    <tr><th colspan="<?= 2 + count($hours) ?>" class="sheet-title">LOG SHEET PERSHIFT DRYER D IN - W DAN COOLING TOWER (CT) INGERSOLL RAND</th></tr>
                                    <tr>
                                        <th class="head-blue" rowspan="2">Item Check</th>
                                        <th class="head-blue" rowspan="2">Standard</th>
                                        <th class="head-blue" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($hours as $hour): ?>
                                            <th class="head-blue"><?= htmlspecialchars(dryer_weaving_display_hour($hour)) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $currentCategory = ''; ?>
                                    <?php foreach ($items as $item): ?>
                                        <?php if ($item['category'] !== $currentCategory): $currentCategory = $item['category']; ?>
                                            <tr class="category-row"><td colspan="<?= 2 + count($hours) ?>"><?= htmlspecialchars($currentCategory) ?></td></tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td class="item-cell"><?= $item['item'] ?></td>
                                            <td class="standard-cell"><?= htmlspecialchars($item['standard']) ?></td>
                                            <?php foreach ($hours as $hour): $key = $item['key'] . '|' . $hour; ?>
                                                <td><input type="text" name="rows[<?= htmlspecialchars($item['key']) ?>][<?= htmlspecialchars($hour) ?>]" value="<?= htmlspecialchars($cells[$key]['nilai'] ?? '') ?>"></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="2" class="category-row" style="text-align:center; font-weight:700;">SHIFT</td>
                                        <?php foreach ($hours as $hour): ?>
                                            <td style="font-weight:600; font-size:11px;"><?= htmlspecialchars($shifts[$hour] ?? '-') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="category-row" style="text-align:center; font-weight:700;">PETUGAS</td>
                                        <?php foreach ($hours as $hour): ?>
                                            <td style="font-size:11px;"><?= htmlspecialchars($petugasByHour[$hour] ?? '-') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group mt-3 mb-0">
                            <label for="dryer_keterangan">Keterangan</label>
                            <textarea class="form-control" id="dryer_keterangan" name="keterangan" rows="2"><?= htmlspecialchars($sheet['Keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="dryer_weaving.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
.edit-meta{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:10px}
.dryer-edit-wrap{overflow:auto;border:1px solid #111;background:#fff}
.dryer-edit-table{border-collapse:collapse;width:100%;min-width:1320px;font-size:12px}
.dryer-edit-table th,.dryer-edit-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px}
.dryer-edit-table .sheet-title{font-size:16px;font-weight:800;background:#fff}
.dryer-edit-table .head-blue{background:#9dc3e6;font-weight:700;color:#000;text-transform:uppercase}
.dryer-edit-table .category-row td{background:#b7b7b7;font-weight:700;text-align:left}
.dryer-edit-table .item-cell{text-align:left;min-width:150px}
.dryer-edit-table .standard-cell{font-weight:600;min-width:90px}
.dryer-edit-table input{width:44px;height:30px;border:1px solid #cbd5e1;text-align:center;padding:2px 3px}
.dryer-mobile-hint{display:none;color:#6c757d;font-size:12px}
@media (max-width:767.98px){
    .edit-meta{display:block}
    .dryer-mobile-hint{display:block}
    .dryer-edit-wrap{max-height:55vh;-webkit-overflow-scrolling:touch}
    .dryer-edit-table{min-width:1100px;font-size:11px}
    .dryer-edit-table .sheet-title{font-size:12px}
    .dryer-edit-table .head-blue{font-size:9px;line-height:1.15}
    .dryer-edit-table th,.dryer-edit-table td{padding:2px}
    .dryer-edit-table input{width:40px;height:28px;font-size:11px}
}
</style>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('#dryer_petugas_default').select2({theme: 'bootstrap4', width: '100%', placeholder: 'Cari / pilih petugas', allowClear: true});
    }
    $('#formEditDryerWeaving').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: 'update_dryer_weaving.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    if (res.new_id) {
                        $('#dryer_sheet_id').val(res.new_id);
                    }
                    Swal.fire('Berhasil', res.message, 'success').then(function() {
                        window.location.href = 'dryer_weaving.php';
                    });
                } else {
                    $submitBtn.prop('disabled', false).text('Simpan');
                    Swal.fire('Gagal', res.message || 'Data gagal diperbarui.', 'error');
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false).text('Simpan');
                Swal.fire('Gagal', 'Terjadi kesalahan saat memperbarui data.', 'error');
            }
        });
    });
});
</script>


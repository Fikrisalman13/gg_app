<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ccirl_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
weaving_require($conn, 'CanEdit');

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id) || !ccirl_table_exists($conn)) {
    $_SESSION['error'] = 'ID tidak valid.';
    header('Location: ccirl.php');
    exit;
}
$sheet = ccirl_get_sheet($conn, (int)$id);
if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: ccirl.php');
    exit;
}

$tanggal = ccirl_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$cells = ccirl_get_cells($conn, $tanggal, $sheet['Compressor_No']);
$items = ccirl_items();
$hours = ccirl_hours();
$petugasOptions = ccirl_get_petugas_options($conn);

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1>Edit CCIRL</h1></div></section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                    <a href="ccirl.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <form id="formEditCcirl" autocomplete="off">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                    <div class="card-body">
                        <div class="edit-meta">
                            <div class="form-group">
                                <label>Tanggal</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars(ccirl_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Compressor No</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($sheet['Compressor_No'] ?? '') ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="cc_petugas_default">Petugas</label>
                                <select class="form-control select2bs4" id="cc_petugas_default" name="petugas_default">
                                    <option value="">Pilih Petugas</option>
                                    <?php foreach ($petugasOptions as $petugasName): ?>
                                        <option value="<?= htmlspecialchars($petugasName) ?>" <?= ($sheet['Petugas'] ?? '') === $petugasName ? 'selected' : '' ?>><?= htmlspecialchars($petugasName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="cc-edit-wrap">
                            <table class="cc-edit-table">
                                <thead>
                                    <tr><th colspan="<?= 1 + count($hours) ?>" class="sheet-title">CENTAC COMPRESSOR INGERSOLL RAND LOG SHEET</th></tr>
                                    <tr><th colspan="<?= 1 + count($hours) ?>" class="date-title">COMPRESSOR NO : <?= htmlspecialchars($sheet['Compressor_No'] ?? '') ?></th></tr>
                                    <tr><th colspan="<?= 1 + count($hours) ?>" class="date-title">TANGGAL : <?= htmlspecialchars(ccirl_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></th></tr>
                                    <tr><th class="head-grey" rowspan="2">Status Message</th><th class="head-grey" colspan="<?= count($hours) ?>">Jam Pemeriksaan</th></tr>
                                    <tr><?php foreach ($hours as $hour): ?><th class="head-grey"><?= htmlspecialchars(ccirl_display_hour($hour)) ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="status-cell"><?= $item['label'] ?></td>
                                            <?php foreach ($hours as $hour): $key = $item['key'] . '|' . $hour; ?>
                                                <td><input type="text" name="rows[<?= htmlspecialchars($item['key']) ?>][<?= htmlspecialchars($hour) ?>]" value="<?= htmlspecialchars($cells[$key]['nilai'] ?? '') ?>"></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-group mt-3">
                            <label for="cc_keterangan">Keterangan</label>
                            <textarea class="form-control" id="cc_keterangan" name="keterangan" rows="2"><?= htmlspecialchars($sheet['Keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <a href="ccirl.php" class="btn btn-secondary">Batal</a>
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
.edit-meta{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:10px}
.cc-edit-wrap{overflow:auto;border:1px solid #111;background:#fff}
.cc-edit-table{border-collapse:collapse;width:100%;min-width:1350px;font-size:11px}
.cc-edit-table th,.cc-edit-table td{border:1px solid #111;text-align:center;vertical-align:middle;padding:3px 4px}
.cc-edit-table .sheet-title{font-size:15px;font-weight:800;background:#fff}
.cc-edit-table .date-title{text-align:left;background:#fff;font-weight:700}
.cc-edit-table .head-grey,.cc-edit-table .status-cell{background:#d9d9d9;font-weight:700}
.cc-edit-table .status-cell{text-align:left}
.cc-edit-table input{width:44px;height:28px;border:1px solid #cbd5e1;text-align:center;padding:2px 3px}
@media (max-width:767.98px){.edit-meta{display:block}.cc-edit-wrap{max-height:55vh}.cc-edit-table{min-width:1200px;font-size:11px}}
</style>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function() {
    if ($.fn.select2) $('#cc_petugas_default').select2({theme: 'bootstrap4', width: '100%', placeholder: 'Cari / pilih petugas', allowClear: true});
    $('#formEditCcirl').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'update_ccirl.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire('Berhasil', res.message, 'success').then(function() { window.location.href = 'ccirl.php'; });
                } else {
                    Swal.fire('Gagal', res.message || 'Data gagal diperbarui.', 'error');
                }
            },
            error: function() { Swal.fire('Gagal', 'Terjadi kesalahan saat memperbarui data.', 'error'); }
        });
    });
});
</script>

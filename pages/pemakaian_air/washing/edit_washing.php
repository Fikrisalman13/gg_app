<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId washing di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: washing.php');
    exit;
}

function fmt_num_input($val, $decimals = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', ',');
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: washing.php');
    exit;
}

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Ahir, Total_Pemakaian, Operasional_Mesin, Pemakaian_rata2perjam, Keterangan, Catatan
        FROM dbo.washing_air
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data.";
    header('Location: washing.php');
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if ($stmt) sqlsrv_free_stmt($stmt);
if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: washing.php');
    exit;
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Edit Pemakaian Air Washing</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                </div>
                <form method="post" action="update_washing.php" autocomplete="off">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="meter_awal">Meter Awal</label>
                            <input type="text" class="form-control text-right washing-number" id="meter_awal" name="meter_awal" value="<?= htmlspecialchars(fmt_num_input($row['Meter_Awal'] ?? null, 2)) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="meter_akhir">Meter Akhir</label>
                            <input type="text" class="form-control text-right washing-number" id="meter_akhir" name="meter_akhir" value="<?= htmlspecialchars(fmt_num_input($row['Meter_Ahir'] ?? null, 2)) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="operasional_mesin">Operasional Mesin</label>
                            <input type="text" class="form-control text-right washing-number" id="operasional_mesin" name="operasional_mesin" value="<?= htmlspecialchars(fmt_num_input($row['Operasional_Mesin'] ?? null, 2)) ?>">
                        </div>
                        <div class="form-group">
                            <label for="total_pemakaian">Total Pemakaian</label>
                            <input type="text" class="form-control text-right" id="total_pemakaian" name="total_pemakaian" value="<?= htmlspecialchars(fmt_num_input($row['Total_Pemakaian'] ?? null, 2)) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="pemakaian_rata2perjam">Pemakaian Rata2 / Jam</label>
                            <input type="text" class="form-control text-right" id="pemakaian_rata2perjam" name="pemakaian_rata2perjam" value="<?= htmlspecialchars(fmt_num_input($row['Pemakaian_rata2perjam'] ?? null, 2)) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars($row['Keterangan'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="catatan">Catatan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3"><?= htmlspecialchars($row['Catatan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="washing.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<script>
function parseNumber(val) {
    if (!val) return null;
    val = String(val).replace(/[^0-9.,]/g, '');
    if (val === '') return null;
    val = val.replace(/,/g, '');
    var num = Number(val);
    return isNaN(num) ? null : num;
}

function formatNumberUS(num, decimals) {
    if (num === null || num === undefined || isNaN(num)) return '';
    return Number(num).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function isOffKeterangan() {
    var ket = ($('#keterangan').val() || '').trim().toLowerCase();
    return ket === 'off';
}

function recalcTotals() {
    if (isOffKeterangan()) {
        $('#operasional_mesin').val('').prop('disabled', true);
        $('#total_pemakaian').val('');
        $('#pemakaian_rata2perjam').val('');
        return;
    }

    $('#operasional_mesin').prop('disabled', false);
    var awal = parseNumber($('#meter_awal').val());
    var akhir = parseNumber($('#meter_akhir').val());
    var operasional = parseNumber($('#operasional_mesin').val());
    if (awal === null || akhir === null || isNaN(awal) || isNaN(akhir)) {
        $('#total_pemakaian').val('');
        $('#pemakaian_rata2perjam').val('');
        return;
    }
    var total = akhir - awal;
    $('#total_pemakaian').val(formatNumberUS(total, 2));
    if (operasional !== null && !isNaN(operasional) && operasional > 0) {
        var rata = total / operasional;
        $('#pemakaian_rata2perjam').val(formatNumberUS(rata, 2));
    } else {
        $('#pemakaian_rata2perjam').val('');
    }
}

$(document).on('input', '.washing-number', function() {
    this.value = this.value.replace(/[^0-9.,]/g, '');
    recalcTotals();
});
$('#keterangan').on('input change', recalcTotals);

$(document).ready(function() {
    recalcTotals();
});
</script>

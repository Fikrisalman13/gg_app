<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId LAB di database
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && (int)$permissions['CanEdit'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: lab.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: lab.php');
    exit;
}

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Akhir, Total_Pemakaian, Keterangan, Catatan
        FROM dbo.lab_air
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [(int)$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data.";
    header('Location: lab.php');
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_BOTH);
if ($stmt) sqlsrv_free_stmt($stmt);
if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: lab.php');
    exit;
}

function fmt_num_input($val, $decimals = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', ',');
}

$data = [
    'tanggal' => $row['Tanggal'] ?? $row['tanggal'] ?? $row[1] ?? null,
    'meter_awal' => $row['Meter_Awal'] ?? $row['meter_awal'] ?? $row[2] ?? null,
    'meter_akhir' => $row['Meter_Akhir'] ?? $row['meter_akhir'] ?? $row[3] ?? null,
    'total_pemakaian' => $row['Total_Pemakaian'] ?? $row['total_pemakaian'] ?? $row[4] ?? null,
    'keterangan' => $row['Keterangan'] ?? $row['keterangan'] ?? $row[5] ?? null,
    'catatan' => $row['Catatan'] ?? $row['catatan'] ?? $row[6] ?? null
];

$tanggalValue = '';
if (($data['tanggal'] ?? null) instanceof DateTime) {
    $tanggalValue = $data['tanggal']->format('Y-m-d');
} elseif (!empty($data['tanggal'])) {
    $ts = strtotime((string)$data['tanggal']);
    $tanggalValue = $ts ? date('Y-m-d', $ts) : (string)$data['tanggal'];
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Edit Meter Air LAB</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                </div>
                <form method="post" action="update_lab.php" autocomplete="off">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggalValue) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="meter_awal">Awal (M<sup>3</sup>)</label>
                            <input type="text" class="form-control text-right lab-number" id="meter_awal" name="meter_awal" data-decimals="3" value="<?= htmlspecialchars(fmt_num_input($data['meter_awal'] ?? null, 3)) ?>" required>
                        </div>
                        <div class="form-group text-right mb-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnAmbilPrevAkhir">
                                <i class="fas fa-history mr-1"></i> Ambil Awal = Akhir Sebelumnya
                            </button>
                        </div>
                        <div class="form-group">
                            <label for="meter_akhir">Akhir (M<sup>3</sup>)</label>
                            <input type="text" class="form-control text-right lab-number" id="meter_akhir" name="meter_akhir" data-decimals="3" value="<?= htmlspecialchars(fmt_num_input($data['meter_akhir'] ?? null, 3)) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="total_pemakaian">Pemakaian (M<sup>3</sup>)</label>
                            <input type="text" class="form-control text-right bg-light" id="total_pemakaian" name="total_pemakaian" data-decimals="2" value="<?= htmlspecialchars(fmt_num_input($data['total_pemakaian'] ?? null, 2)) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="2"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="catatan">Catatan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3"><?= htmlspecialchars($data['catatan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="lab.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
function parseNumber(val) {
    if (!val) return NaN;
    val = String(val).replace(/[^0-9.,]/g, '');
    if (val === '') return NaN;
    val = val.replace(/,/g, '');
    var num = Number(val);
    return isNaN(num) ? NaN : num;
}

function formatNumberUS(num, decimals) {
    if (num === null || num === undefined || isNaN(num)) return '';
    return Number(num).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function normalizeDateValue(raw) {
    var v = (raw || '').toString().trim();
    if (v === '') return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
    var m = v.match(/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/);
    if (m) return m[3] + '-' + m[2] + '-' + m[1];
    return v;
}

function formatNumberInput($el) {
    var decimals = parseInt($el.data('decimals') || 2, 10);
    var parsed = parseNumber($el.val());
    if (!isNaN(parsed)) {
        $el.val(formatNumberUS(parsed, decimals));
    }
}

function recalcPemakaian() {
    var awal = parseNumber($('#meter_awal').val());
    var akhir = parseNumber($('#meter_akhir').val());
    if (isNaN(awal) || isNaN(akhir)) {
        $('#total_pemakaian').val('');
        return;
    }
    $('#total_pemakaian').val(formatNumberUS((akhir - awal), 2));
}

function fetchPrevAkhir() {
    var tanggal = normalizeDateValue($('#tanggal').val());
    if (!tanggal) {
        Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Pilih tanggal terlebih dahulu.' });
        return;
    }
    $.ajax({
        url: 'get_lab_prev.php',
        type: 'GET',
        dataType: 'json',
        data: { tanggal: tanggal, exclude_id: <?= json_encode((string)$id) ?> },
        success: function(resp) {
            if (resp && resp.success && resp.meter_akhir !== null && resp.meter_akhir !== '') {
                $('#meter_awal').val(formatNumberUS(resp.meter_akhir, 3));
                recalcPemakaian();
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Meter awal diambil dari meter akhir hari sebelumnya.' });
            } else {
                Swal.fire({ icon: 'info', title: 'Info', text: (resp && resp.message) ? resp.message : 'Data meter akhir sebelumnya tidak ditemukan.' });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat mengambil data sebelumnya.' });
        }
    });
}

$(document).on('input', '.lab-number', function() {
    this.value = this.value.replace(/[^0-9.,]/g, '');
    recalcPemakaian();
});
$(document).on('blur', '.lab-number', function() {
    formatNumberInput($(this));
    recalcPemakaian();
});

$('#btnAmbilPrevAkhir').on('click', function() {
    fetchPrevAkhir();
});

$('#tanggal').on('change', function() {
    recalcPemakaian();
});

$(document).ready(function() {
    recalcPemakaian();
});
</script>

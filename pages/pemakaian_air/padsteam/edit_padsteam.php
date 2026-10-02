<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId padsteam di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: padsteam.php');
    exit;
}

function normalize_decimal($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $value = preg_replace('/[^0-9.,]/', '', $value);
    if ($value === '') return null;

    // Primary format: 77,942.2956 (comma thousands, dot decimal)
    if (strpos($value, '.') !== false) {
        $value = str_replace(',', '', $value);
        if (is_numeric($value)) return $value;
    }

    // Backward compatibility for old input like 77.942.2956
    if (strpos($value, '.') !== false && strpos($value, ',') === false) {
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $dec = array_pop($parts);
            $int = implode('', $parts);
            $normalized = $int . '.' . $dec;
            if (is_numeric($normalized)) return $normalized;
        }
    }

    // Fallback: comma decimal
    $fallback = str_replace('.', '', $value);
    $fallback = str_replace(',', '.', $fallback);
    return is_numeric($fallback) ? $fallback : null;
}

function fmt_num_input($val, $decimals = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', ',');
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: padsteam.php');
    exit;
}

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Ahir, Oprasional_Mesin, Pemakaian_Rata2perjam, Keterangan
        FROM dbo.padsteam_air
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data.";
    header('Location: padsteam.php');
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_BOTH);
sqlsrv_free_stmt($stmt);
if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: padsteam.php');
    exit;
}

$data = [
    'tanggal' => $row['Tanggal'] ?? $row[1] ?? null,
    'meter_awal' => $row['Meter_Awal'] ?? $row[2] ?? null,
    'meter_akhir' => $row['Meter_Ahir'] ?? $row[3] ?? null,
    'oprasional_mesin' => $row['Oprasional_Mesin'] ?? $row[4] ?? null,
    'pemakaian_rata2perjam' => $row['Pemakaian_Rata2perjam'] ?? $row[5] ?? null,
    'keterangan' => $row['Keterangan'] ?? $row[6] ?? null
];

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Edit Pemakaian Air padsteam</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                </div>
                <form method="post" action="update_padsteam.php" autocomplete="off">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($data['tanggal'] instanceof DateTime ? $data['tanggal']->format('Y-m-d') : $data['tanggal']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="meter_awal">Meter Awal</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right padsteam-number-only" id="meter_awal" name="meter_awal" value="<?= htmlspecialchars(fmt_num_input($data['meter_awal'], 2)) ?>" data-decimals="2" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="meter_akhir">Meter Akhir</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right padsteam-number-only" id="meter_akhir" name="meter_akhir" value="<?= htmlspecialchars(fmt_num_input($data['meter_akhir'], 2)) ?>" data-decimals="2" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="oprasional_mesin">Operasional Mesin / Jam</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right padsteam-number-only" id="oprasional_mesin" name="oprasional_mesin" value="<?= htmlspecialchars(fmt_num_input($data['oprasional_mesin'], 2)) ?>" data-decimals="2">
                                <div class="input-group-append">
                                    <span class="input-group-text">Jam</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="total_pemakaian">Total Pemakaian</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right" id="total_pemakaian" name="total_pemakaian" readonly>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pemakaian_rata2perjam">Pemakaian Rata Rata / Jam</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right" id="pemakaian_rata2perjam" name="pemakaian_rata2perjam" value="<?= htmlspecialchars(fmt_num_input($data['pemakaian_rata2perjam'], 2)) ?>" readonly>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="padsteam.php" class="btn btn-secondary">Batal</a>
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

function recalcTotals() {
    var ket = ($('#keterangan').val() || '').trim().toLowerCase();
    if (ket === 'off') {
        $('#oprasional_mesin').val('').prop('disabled', true);
        $('#pemakaian_rata2perjam').val('');
        $('#total_pemakaian').val('');
        return;
    } else {
        $('#oprasional_mesin').prop('disabled', false);
    }
    var awal = parseNumber($('#meter_awal').val());
    var akhir = parseNumber($('#meter_akhir').val());
    var opr = parseNumber($('#oprasional_mesin').val());
    if (awal === null || akhir === null || isNaN(awal) || isNaN(akhir)) {
        $('#total_pemakaian').val('');
        $('#pemakaian_rata2perjam').val('');
        return;
    }
    var total = akhir - awal;
    $('#total_pemakaian').val(formatNumberUS(total, 2));
    if (opr !== null && !isNaN(opr) && opr > 0) {
    $('#pemakaian_rata2perjam').val(formatNumberUS(total / opr, 2));
    } else {
        $('#pemakaian_rata2perjam').val('');
    }
}

$(document).on('input', '.padsteam-number-only', function() {
    this.value = this.value.replace(/[^0-9.,]/g, '');
    recalcTotals();
});
$(document).on('blur', '.padsteam-number-only', function() {
    var decimals = parseInt($(this).data('decimals') || 4, 10);
    var parsed = parseNumber(this.value);
    if (parsed !== null && !isNaN(parsed)) {
        this.value = formatNumberUS(parsed, decimals);
    }
    recalcTotals();
});

$(document).ready(function() {
    recalcTotals();
});

$('#keterangan').on('input change', function() {
    recalcTotals();
});
</script>


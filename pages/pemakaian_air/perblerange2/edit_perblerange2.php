<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId perblerange2 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: perblerange2.php');
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

function get_next_meter_awal_for_date($conn, $tanggalValue) {
    $tanggalValue = trim((string)$tanggalValue);
    if ($tanggalValue === '') return null;

    $dt = DateTime::createFromFormat('Y-m-d', $tanggalValue);
    if (!$dt) {
        $ts = strtotime($tanggalValue);
        if ($ts === false) return null;
        $dt = new DateTime(date('Y-m-d', $ts));
    }

    $nextDate = $dt->modify('+1 day')->format('Y-m-d');
    $sql = "SELECT TOP 1 Meter_Awal
            FROM dbo.perblerange2_air
            WHERE CAST(Tanggal AS DATE) = ?
            ORDER BY Tanggal ASC, Id ASC";
    $stmt = sqlsrv_query($conn, $sql, [$nextDate]);
    if ($stmt === false) return null;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row['Meter_Awal'] ?? null;
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: perblerange2.php');
    exit;
}

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Ahir, Oprasional_Mesin, Pemakaian_Rata2perjam, Keterangan, PBR1, PBR2
        FROM dbo.perblerange2_air
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data.";
    header('Location: perblerange2.php');
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_BOTH);
sqlsrv_free_stmt($stmt);
if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: perblerange2.php');
    exit;
}

$data = [
    'tanggal' => $row['Tanggal'] ?? $row[1] ?? null,
    'meter_awal' => $row['Meter_Awal'] ?? $row[2] ?? null,
    'meter_akhir' => $row['Meter_Ahir'] ?? $row[3] ?? null,
    'oprasional_mesin' => $row['Oprasional_Mesin'] ?? $row[4] ?? null,
    'pemakaian_rata2perjam' => $row['Pemakaian_Rata2perjam'] ?? $row[5] ?? null,
    'keterangan' => $row['Keterangan'] ?? $row[6] ?? null,
    'pbr1' => $row['PBR1'] ?? $row[7] ?? null,
    'pbr2' => $row['PBR2'] ?? $row[8] ?? null
];

$tanggalForMode = $data['tanggal'] instanceof DateTime ? $data['tanggal']->format('Y-m-d') : (string)$data['tanggal'];
$nextMeterAwalForMode = get_next_meter_awal_for_date($conn, $tanggalForMode);
$initialMeterAkhirMode = 'otomatis';
$currentMeterAkhir = $data['meter_akhir'];
if ($currentMeterAkhir !== null && $currentMeterAkhir !== '') {
    if (is_numeric($currentMeterAkhir) && is_numeric($nextMeterAwalForMode) && abs((float)$currentMeterAkhir - (float)$nextMeterAwalForMode) <= 0.0001) {
        $initialMeterAkhirMode = 'otomatis';
    } else {
        $initialMeterAkhirMode = 'manual';
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Edit Pemakaian Air perblerange2</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                </div>
                <form method="post" action="update_perblerange2.php" autocomplete="off">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($data['tanggal'] instanceof DateTime ? $data['tanggal']->format('Y-m-d') : $data['tanggal']) ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="pbr1">PBR1</label>
                                <div class="input-group">
                                    <input type="text" class="form-control text-right perblerange2-number-only" id="pbr1" name="pbr1" value="<?= htmlspecialchars(fmt_num_input($data['pbr1'], 2)) ?>" data-decimals="2" placeholder="-">
                                    <div class="input-group-append">
                                        <span class="input-group-text">M<sup>3</sup></span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="pbr2">PBR2</label>
                                <div class="input-group">
                                    <input type="text" class="form-control text-right perblerange2-number-only" id="pbr2" name="pbr2" value="<?= htmlspecialchars(fmt_num_input($data['pbr2'], 2)) ?>" data-decimals="2" placeholder="-">
                                    <div class="input-group-append">
                                        <span class="input-group-text">M<sup>3</sup></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="meter_awal">Meter Awal (PBR1 + PBR2)</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right" id="meter_awal" name="meter_awal" value="<?= htmlspecialchars(fmt_num_input($data['meter_awal'], 2)) ?>" data-original="<?= htmlspecialchars(fmt_num_input($data['meter_awal'], 2)) ?>" readonly>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-2">
                            <label class="d-block mb-1">Mode Meter Akhir</label>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="meter_akhir_mode_otomatis" name="meter_akhir_mode" value="otomatis" class="custom-control-input" <?= $initialMeterAkhirMode === 'otomatis' ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="meter_akhir_mode_otomatis">Otomatis (H+1)</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="meter_akhir_mode_manual" name="meter_akhir_mode" value="manual" class="custom-control-input" <?= $initialMeterAkhirMode === 'manual' ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="meter_akhir_mode_manual">Manual</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="meter_akhir">Meter Akhir</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right perblerange2-number-only <?= $initialMeterAkhirMode === 'manual' ? '' : 'bg-light' ?>" id="meter_akhir" name="meter_akhir" value="<?= htmlspecialchars(fmt_num_input($data['meter_akhir'], 2)) ?>" data-original="<?= htmlspecialchars(fmt_num_input($data['meter_akhir'], 2)) ?>" data-decimals="2" inputmode="decimal" pattern="[0-9.,]*" <?= $initialMeterAkhirMode === 'manual' ? '' : 'readonly' ?>>
                                <div class="input-group-append">
                                    <span class="input-group-text">M<sup>3</sup></span>
                                </div>
                            </div>
                            <small class="form-text text-muted" id="meter_akhir_hint"><?= $initialMeterAkhirMode === 'manual' ? 'Mode manual: isi meter akhir secara manual.' : 'Mode otomatis: diambil dari nilai awal tanggal berikutnya (H+1).' ?></small>
                        </div>
                        <div class="form-group">
                            <label for="oprasional_mesin">Operasional Mesin / Jam</label>
                            <div class="input-group">
                                <input type="text" class="form-control text-right perblerange2-number-only" id="oprasional_mesin" name="oprasional_mesin" value="<?= htmlspecialchars(fmt_num_input($data['oprasional_mesin'], 2)) ?>" data-decimals="2">
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
                        <a href="perblerange2.php" class="btn btn-secondary">Batal</a>
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

function getMeterAkhirMode() {
    return $('input[name="meter_akhir_mode"]:checked').val() || 'otomatis';
}

function syncMeterAkhirMode(options) {
    var mode = getMeterAkhirMode();
    var isManual = (mode === 'manual');
    var fallbackToOriginal = !!(options && options.fallbackToOriginal);
    var $meterAkhir = $('#meter_akhir');
    $meterAkhir.prop('readonly', !isManual);
    $meterAkhir.prop('required', isManual);
    $meterAkhir.toggleClass('bg-light', !isManual);
    $('#meter_akhir_hint').text(
        isManual
            ? 'Mode manual: isi meter akhir secara manual.'
            : 'Mode otomatis: diambil dari nilai awal tanggal berikutnya (H+1).'
    );
    if (isManual) {
        recalcTotals();
    } else {
        fetchMeterAkhirFromNext(fallbackToOriginal);
    }
}

function updateMeterAwalFromPbr() {
    var pbr1 = parseNumber($('#pbr1').val());
    var pbr2 = parseNumber($('#pbr2').val());
    if ((pbr1 === null || isNaN(pbr1)) && (pbr2 === null || isNaN(pbr2))) {
        var original = $('#meter_awal').data('original');
        $('#meter_awal').val(original || '');
        return;
    }
    if (pbr1 === null || isNaN(pbr1)) pbr1 = 0;
    if (pbr2 === null || isNaN(pbr2)) pbr2 = 0;
    $('#meter_awal').val(formatNumberUS(pbr1 + pbr2, 2));
}

function fetchMeterAkhirFromNext(fallbackToOriginal) {
    if (getMeterAkhirMode() !== 'otomatis') {
        recalcTotals();
        return;
    }
    var tanggal = $('#tanggal').val();
    if (!tanggal) {
        $('#meter_akhir').val('');
        recalcTotals();
        return;
    }
    $.ajax({
        url: 'get_perblerange2_next.php',
        type: 'GET',
        dataType: 'json',
        data: { tanggal: tanggal },
        success: function(resp) {
            if (getMeterAkhirMode() !== 'otomatis') return;
            if (resp && resp.success && resp.meter_awal !== null && resp.meter_awal !== '') {
                $('#meter_akhir').val(formatNumberUS(resp.meter_awal, 2));
            } else if (fallbackToOriginal) {
                var original = $('#meter_akhir').data('original');
                $('#meter_akhir').val(original || '');
            } else {
                $('#meter_akhir').val('');
            }
            recalcTotals();
        },
        error: function() {
            if (getMeterAkhirMode() !== 'otomatis') return;
            if (fallbackToOriginal) {
                var original = $('#meter_akhir').data('original');
                $('#meter_akhir').val(original || '');
            } else {
                $('#meter_akhir').val('');
            }
            recalcTotals();
        }
    });
}

function recalcTotals() {
    updateMeterAwalFromPbr();
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

$(document).on('input', '.perblerange2-number-only', function() {
    this.value = this.value.replace(/[^0-9.,]/g, '');
    recalcTotals();
});
$(document).on('blur', '.perblerange2-number-only', function() {
    var decimals = parseInt($(this).data('decimals') || 4, 10);
    var parsed = parseNumber(this.value);
    if (parsed !== null && !isNaN(parsed)) {
        this.value = formatNumberUS(parsed, decimals);
    }
    recalcTotals();
});

$(document).ready(function() {
    updateMeterAwalFromPbr();
    syncMeterAkhirMode({ fallbackToOriginal: true });
});

$('#keterangan').on('input change', function() {
    recalcTotals();
});

$('#tanggal').on('change', function() {
    if (getMeterAkhirMode() === 'otomatis') {
        fetchMeterAkhirFromNext(false);
    } else {
        recalcTotals();
    }
});

$('input[name="meter_akhir_mode"]').on('change', function() {
    syncMeterAkhirMode({ fallbackToOriginal: false });
});
</script>







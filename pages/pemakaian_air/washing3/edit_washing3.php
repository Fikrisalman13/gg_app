<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId Washing3 di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanEdit']) && $permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: washing3.php');
    exit;
}

function fmt_num_input($val, $decimals = 2) {
    if ($val === null || $val === '') return '';
    if (!is_numeric($val)) return (string)$val;
    return number_format((float)$val, $decimals, '.', ',');
}

function has_meter_flow_column($conn) {
    $stmt = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.washing3_air','MeterFlow') AS meter_flow");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return isset($row['meter_flow']) && $row['meter_flow'] !== null;
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: washing3.php');
    exit;
}

$hasMeterFlowColumn = has_meter_flow_column($conn);
$meterFlowSelect = $hasMeterFlowColumn ? 'MeterFlow' : 'NULL AS MeterFlow';

$sql = "SELECT Id, Tanggal, $meterFlowSelect, WaterFlow, OperasionalMesin, TotalPemakaian, Keterangan, Catatan
        FROM dbo.washing3_air
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data.";
    header('Location: washing3.php');
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_BOTH);
if ($stmt) sqlsrv_free_stmt($stmt);
if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: washing3.php');
    exit;
}

$data = [
    'tanggal' => $row['Tanggal'] ?? $row[1] ?? null,
    'meter_flow' => $row['MeterFlow'] ?? $row[2] ?? null,
    'water_flow' => $row['WaterFlow'] ?? $row[3] ?? null,
    'operasional_mesin' => $row['OperasionalMesin'] ?? $row[4] ?? null,
    'total_pemakaian' => $row['TotalPemakaian'] ?? $row[5] ?? null,
    'keterangan' => $row['Keterangan'] ?? $row[6] ?? null,
    'catatan' => $row['Catatan'] ?? $row[7] ?? null
];

$tanggalValue = '';
if ($data['tanggal'] instanceof DateTime) {
    $tanggalValue = $data['tanggal']->format('Y-m-d');
} elseif (is_string($data['tanggal']) && $data['tanggal'] !== '') {
    $ts = strtotime($data['tanggal']);
    $tanggalValue = $ts ? date('Y-m-d', $ts) : $data['tanggal'];
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Edit Pemakaian Air Washing 3</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Edit Data</h3>
                </div>
                <form method="post" action="update_washing3.php" autocomplete="off">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id) ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tanggal">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= htmlspecialchars($tanggalValue) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="meter_flow">Meter Flow</label>
                            <input type="text" class="form-control text-right w3-number" id="meter_flow" name="meter_flow" value="<?= htmlspecialchars(fmt_num_input($data['meter_flow'], 2)) ?>" data-decimals="2">
                            <small class="form-text text-muted" id="meter_flow_prev_info">Meter Flow sebelumnya: -</small>
                        </div>
                        <div class="form-group">
                            <label for="water_flow">Water Flow</label>
                            <input type="text" class="form-control text-right bg-light" id="water_flow" name="water_flow" value="<?= htmlspecialchars(fmt_num_input($data['water_flow'], 2)) ?>" data-decimals="2" readonly>
                        </div>
                        <div class="form-group">
                            <label for="operasional_mesin">Operasional Mesin</label>
                            <input type="text" class="form-control text-right w3-number" id="operasional_mesin" name="operasional_mesin" value="<?= htmlspecialchars(fmt_num_input($data['operasional_mesin'], 2)) ?>">
                        </div>
                        <div class="form-group">
                            <label for="total_pemakaian">Total Pemakaian</label>
                            <input type="text" class="form-control text-right w3-number" id="total_pemakaian" name="total_pemakaian" value="<?= htmlspecialchars(fmt_num_input($data['total_pemakaian'], 2)) ?>">
                        </div>
                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="catatan">Catatan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3"><?= htmlspecialchars($data['catatan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="washing3.php" class="btn btn-secondary">Batal</a>
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

function normalizeDateValue(raw) {
    var v = (raw || '').toString().trim();
    if (v === '') return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v;
    var m = v.match(/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/);
    if (m) return m[3] + '-' + m[2] + '-' + m[1];
    return v;
}

var prevMeterFlow = NaN;
var currentId = <?= json_encode((string)$id) ?>;

function formatNumberInput($el) {
    var decimals = parseInt($el.data('decimals') || 2, 10);
    var parsed = parseNumber($el.val());
    if (parsed !== null && !isNaN(parsed)) {
        $el.val(formatNumberUS(parsed, decimals));
    }
}

function recalcWaterFlowAndTotal() {
    var meterFlow = parseNumber($('#meter_flow').val());
    var operasional = parseNumber($('#operasional_mesin').val());
    if (meterFlow !== null && !isNaN(meterFlow) && !isNaN(prevMeterFlow) && operasional !== null && !isNaN(operasional) && operasional > 0) {
        var waterFlow = (meterFlow - prevMeterFlow) / operasional;
        $('#water_flow').val(formatNumberUS(waterFlow, 2));
        $('#total_pemakaian').val(formatNumberUS((waterFlow * operasional), 2));
    } else {
        $('#water_flow').val('');
    }
}

function fetchPrevMeterFlow() {
    var tanggal = normalizeDateValue($('#tanggal').val());
    if (!tanggal) {
        prevMeterFlow = NaN;
        $('#meter_flow_prev_info').text('Meter Flow sebelumnya: -');
        recalcWaterFlowAndTotal();
        return;
    }
    $.ajax({
        url: 'get_washing3_prev.php',
        type: 'GET',
        dataType: 'json',
        data: { tanggal: tanggal, exclude_id: currentId },
        success: function(resp) {
            if (resp && resp.success && resp.meter_flow !== null && resp.meter_flow !== '') {
                prevMeterFlow = parseNumber(resp.meter_flow);
                $('#meter_flow_prev_info').text('Meter Flow sebelumnya: ' + formatNumberUS(prevMeterFlow, 2));
            } else {
                prevMeterFlow = NaN;
                $('#meter_flow_prev_info').text('Meter Flow sebelumnya: -');
            }
            recalcWaterFlowAndTotal();
        },
        error: function() {
            prevMeterFlow = NaN;
            $('#meter_flow_prev_info').text('Meter Flow sebelumnya: -');
            recalcWaterFlowAndTotal();
        }
    });
}

$(document).on('input', '.w3-number', function() {
    this.value = this.value.replace(/[^0-9.,]/g, '');
});
$(document).on('blur', '.w3-number', function() {
    formatNumberInput($(this));
});

$('#tanggal').on('change', function() {
    fetchPrevMeterFlow();
});

$('#meter_flow, #operasional_mesin').on('input', function() {
    recalcWaterFlowAndTotal();
});

$(document).ready(function() {
    fetchPrevMeterFlow();
});
</script>

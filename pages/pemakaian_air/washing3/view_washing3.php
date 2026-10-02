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
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: washing3.php');
    exit;
}

function has_meter_flow_column($conn) {
    $stmt = sqlsrv_query($conn, "SELECT COL_LENGTH('dbo.washing3_air','MeterFlow') AS meter_flow");
    if ($stmt === false) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($stmt) sqlsrv_free_stmt($stmt);
    return isset($row['meter_flow']) && $row['meter_flow'] !== null;
}

$hasMeterFlowColumn = has_meter_flow_column($conn);
$meterFlowSelect = $hasMeterFlowColumn ? 'MeterFlow' : 'NULL AS MeterFlow';

$sql = "SELECT Id, Tanggal, $meterFlowSelect, WaterFlow, OperasionalMesin, TotalPemakaian,
               Keterangan, Catatan, CreatBy, CreatAt, UpdateBy, UpdateAt
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
    'catatan' => $row['Catatan'] ?? $row[7] ?? null,
    'created_by' => $row['CreatBy'] ?? $row[8] ?? null,
    'created_at' => $row['CreatAt'] ?? $row[9] ?? null,
    'update_by' => $row['UpdateBy'] ?? $row[10] ?? null,
    'update_at' => $row['UpdateAt'] ?? $row[11] ?? null
];

function fmtDateTime($dt) {
    if ($dt instanceof DateTime) return $dt->format('Y-m-d H:i:s');
    return $dt ?: '-';
}
function fmtNum($v) {
    if ($v === null || $v === '') return '-';
    return is_numeric($v) ? number_format((float)$v, 2, '.', ',') : $v;
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Detail Pemakaian Air Washing 3</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="washing3.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <tr><th width="30%">Tanggal</th><td><?= htmlspecialchars(fmtDateTime($data['tanggal'] ?? null)) ?></td></tr>
                        <tr><th>Meter Flow</th><td><?= htmlspecialchars(fmtNum($data['meter_flow'] ?? null)) ?></td></tr>
                        <tr><th>Water Flow</th><td><?= htmlspecialchars(fmtNum($data['water_flow'] ?? null)) ?></td></tr>
                        <tr><th>Operasional Mesin</th><td><?= htmlspecialchars(fmtNum($data['operasional_mesin'] ?? null)) ?></td></tr>
                        <tr><th>Total Pemakaian</th><td><?= htmlspecialchars(fmtNum($data['total_pemakaian'] ?? null)) ?></td></tr>
                        <tr><th>Keterangan</th><td><?= htmlspecialchars($data['keterangan'] ?? '-') ?></td></tr>
                        <tr><th>Catatan</th><td><?= htmlspecialchars($data['catatan'] ?? '-') ?></td></tr>
                        <tr><th>Created By</th><td><?= htmlspecialchars($data['created_by'] ?? '-') ?></td></tr>
                        <tr><th>Created At</th><td><?= htmlspecialchars(fmtDateTime($data['created_at'] ?? null)) ?></td></tr>
                        <tr><th>Update By</th><td><?= htmlspecialchars($data['update_by'] ?? '-') ?></td></tr>
                        <tr><th>Update At</th><td><?= htmlspecialchars(fmtDateTime($data['update_at'] ?? null)) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

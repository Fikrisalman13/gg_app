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
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: washing.php');
    exit;
}

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Ahir, Total_Pemakaian, Operasional_Mesin, Pemakaian_rata2perjam,
               Keterangan, Catatan, CreatBy, CreatAt, UpdateBy, UpdateAt
        FROM dbo.washing_air
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data.";
    header('Location: washing.php');
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_BOTH);
if ($stmt) sqlsrv_free_stmt($stmt);

if (!$row) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: washing.php');
    exit;
}

function fmtDateTime($dt) {
    if ($dt instanceof DateTime) return $dt->format('Y-m-d H:i:s');
    return $dt ?: '-';
}
function fmtNum($v) {
    if ($v === null || $v === '') return '-';
    return is_numeric($v) ? number_format((float)$v, 2, '.', ',') : $v;
}

$data = [
    'tanggal' => $row['Tanggal'] ?? $row['tanggal'] ?? $row[1] ?? null,
    'meter_awal' => $row['Meter_Awal'] ?? $row['meter_awal'] ?? $row[2] ?? null,
    'meter_akhir' => $row['Meter_Ahir'] ?? $row['meter_akhir'] ?? $row[3] ?? null,
    'total_pemakaian' => $row['Total_Pemakaian'] ?? $row['total_pemakaian'] ?? $row[4] ?? null,
    'operasional_mesin' => $row['Operasional_Mesin'] ?? $row['operasional_mesin'] ?? $row[5] ?? null,
    'pemakaian_rata2perjam' => $row['Pemakaian_rata2perjam'] ?? $row['pemakaian_rata2perjam'] ?? $row[6] ?? null,
    'keterangan' => $row['Keterangan'] ?? $row['keterangan'] ?? $row[7] ?? null,
    'catatan' => $row['Catatan'] ?? $row['catatan'] ?? $row[8] ?? null,
    'created_by' => $row['CreatBy'] ?? $row['creatby'] ?? $row[9] ?? null,
    'created_at' => $row['CreatAt'] ?? $row['creatat'] ?? $row[10] ?? null,
    'update_by' => $row['UpdateBy'] ?? $row['updateby'] ?? $row[11] ?? null,
    'update_at' => $row['UpdateAt'] ?? $row['updateat'] ?? $row[12] ?? null
];

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Detail Pemakaian Air Washing</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="washing.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <tr><th width="30%">Tanggal</th><td><?= htmlspecialchars(fmtDateTime($data['tanggal'])) ?></td></tr>
                        <tr><th>Meter Awal</th><td><?= htmlspecialchars(fmtNum($data['meter_awal'])) ?></td></tr>
                        <tr><th>Meter Akhir</th><td><?= htmlspecialchars(fmtNum($data['meter_akhir'])) ?></td></tr>
                        <tr><th>Total Pemakaian</th><td><?= htmlspecialchars(fmtNum($data['total_pemakaian'])) ?></td></tr>
                        <tr><th>Operasional Mesin</th><td><?= htmlspecialchars(fmtNum($data['operasional_mesin'])) ?></td></tr>
                        <tr><th>Pemakaian Rata2 / Jam</th><td><?= htmlspecialchars(fmtNum($data['pemakaian_rata2perjam'])) ?></td></tr>
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

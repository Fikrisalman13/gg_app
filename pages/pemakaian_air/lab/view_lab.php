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
if (!empty($permissions) && isset($permissions['CanView']) && (int)$permissions['CanView'] !== 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '' || !ctype_digit((string)$id)) {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: lab.php');
    exit;
}

$sql = "SELECT Id, Tanggal, Meter_Awal, Meter_Akhir, Total_Pemakaian, Keterangan, Catatan,
               CreatBy, CreatAt, UpdateBy, UpdateAt
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

function fmtDateTime($dt) {
    if ($dt instanceof DateTime) return $dt->format('Y-m-d H:i:s');
    if (is_string($dt) && $dt !== '') {
        $ts = strtotime($dt);
        if ($ts) return date('Y-m-d H:i:s', $ts);
    }
    return '-';
}

function fmtNum($v, $dec = 2) {
    if ($v === null || $v === '') return '-';
    return is_numeric($v) ? number_format((float)$v, $dec, '.', ',') : (string)$v;
}

$data = [
    'tanggal' => $row['Tanggal'] ?? $row['tanggal'] ?? $row[1] ?? null,
    'meter_awal' => $row['Meter_Awal'] ?? $row['meter_awal'] ?? $row[2] ?? null,
    'meter_akhir' => $row['Meter_Akhir'] ?? $row['meter_akhir'] ?? $row[3] ?? null,
    'total_pemakaian' => $row['Total_Pemakaian'] ?? $row['total_pemakaian'] ?? $row[4] ?? null,
    'keterangan' => $row['Keterangan'] ?? $row['keterangan'] ?? $row[5] ?? null,
    'catatan' => $row['Catatan'] ?? $row['catatan'] ?? $row[6] ?? null,
    'created_by' => $row['CreatBy'] ?? $row['creatby'] ?? $row[7] ?? null,
    'created_at' => $row['CreatAt'] ?? $row['creatat'] ?? $row[8] ?? null,
    'update_by' => $row['UpdateBy'] ?? $row['updateby'] ?? $row[9] ?? null,
    'update_at' => $row['UpdateAt'] ?? $row['updateat'] ?? $row[10] ?? null
];

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Detail Meter Air LAB</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="lab.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <tr><th width="30%">Tanggal</th><td><?= htmlspecialchars(fmtDateTime($data['tanggal'])) ?></td></tr>
                        <tr><th>Awal (M<sup>3</sup>)</th><td><?= htmlspecialchars(fmtNum($data['meter_awal'], 3)) ?></td></tr>
                        <tr><th>Akhir (M<sup>3</sup>)</th><td><?= htmlspecialchars(fmtNum($data['meter_akhir'], 3)) ?></td></tr>
                        <tr><th>Pemakaian (M<sup>3</sup>)</th><td><?= htmlspecialchars(fmtNum($data['total_pemakaian'], 2)) ?></td></tr>
                        <tr><th>Keterangan</th><td><?= htmlspecialchars($data['keterangan'] ?? '-') ?></td></tr>
                        <tr><th>Catatan</th><td><?= htmlspecialchars($data['catatan'] ?? '-') ?></td></tr>
                        <tr><th>Created By</th><td><?= htmlspecialchars($data['created_by'] ?? '-') ?></td></tr>
                        <tr><th>Created At</th><td><?= htmlspecialchars(fmtDateTime($data['created_at'])) ?></td></tr>
                        <tr><th>Update By</th><td><?= htmlspecialchars($data['update_by'] ?? '-') ?></td></tr>
                        <tr><th>Update At</th><td><?= htmlspecialchars(fmtDateTime($data['update_at'])) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

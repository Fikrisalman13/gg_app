<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu.";
    header('Location: /gg_app/login.php');
    exit;
}

$menuId = 230; // TODO: ganti dengan MenuId jetdyeing di database
$permissions = getPermissions($conn, $_SESSION['GroupId'], $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: jetdyeing.php');
    exit;
}

$sql = "SELECT 
            Id AS id,
            Tanggal AS tanggal,
            CreatBy AS creat_by,
            CreatAt AS creat_at,
            Meter_Awal AS meter_awal,
            Meter_Ahir AS meter_akhir,
            Total_Pemakaian AS total_pemakaian,
            Pemakaian_rata2perjam AS pemakaian_rata2perjam,
            Keterangan AS keterangan
        FROM dbo.jetdyeing_air
        WHERE Id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
if ($stmt === false) {
    $_SESSION['error'] = "Gagal mengambil data.";
    header('Location: jetdyeing.php');
    exit;
}
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_BOTH);
sqlsrv_free_stmt($stmt);

if (!$row || !is_array($row)) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: jetdyeing.php');
    exit;
}

$data = [
    'tanggal' => $row['tanggal'] ?? $row['Tanggal'] ?? $row[1] ?? null,
    'creat_by' => $row['creat_by'] ?? $row['CreatBy'] ?? $row[2] ?? null,
    'creat_at' => $row['creat_at'] ?? $row['CreatAt'] ?? $row[3] ?? null,
    'meter_awal' => $row['meter_awal'] ?? $row['Meter_Awal'] ?? $row[4] ?? null,
    'meter_akhir' => $row['meter_akhir'] ?? $row['Meter_Ahir'] ?? $row[5] ?? null,
    'total_pemakaian' => $row['total_pemakaian'] ?? $row['Total_Pemakaian'] ?? $row[6] ?? null,
    'pemakaian_rata2perjam' => $row['pemakaian_rata2perjam'] ?? $row['Pemakaian_rata2perjam'] ?? $row[7] ?? null,
    'keterangan' => $row['keterangan'] ?? $row['Keterangan'] ?? $row[8] ?? null
];

function fmtDate($dt) {
    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }
    return $dt ?: '-';
}

function fmtNum($val, $decimals = 2) {
    if ($val === null || $val === '') return '-';
    if (is_numeric($val)) {
        return number_format((float)$val, $decimals, '.', ',');
    }
    return $val;
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Detail Pemakaian Air Jet Dyeing</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="jetdyeing.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th width="30%">Tanggal</th>
                            <td><?= htmlspecialchars(fmtDate($data['tanggal'] ?? null)) ?></td>
                        </tr>
                        <tr>
                            <th>Meter Awal</th>
                            <td><?= htmlspecialchars(fmtNum($data['meter_awal'] ?? null, 2)) ?><?= ($data['meter_awal'] ?? null) === null ? '' : ' M<sup>3</sup>' ?></td>
                        </tr>
                        <tr>
                            <th>Meter Akhir</th>
                            <td><?= htmlspecialchars(fmtNum($data['meter_akhir'] ?? null, 2)) ?><?= ($data['meter_akhir'] ?? null) === null ? '' : ' M<sup>3</sup>' ?></td>
                        </tr>
                        <tr>
                            <th>Total Pemakaian</th>
                            <td><?= htmlspecialchars(fmtNum($data['total_pemakaian'] ?? null, 2)) ?><?= ($data['total_pemakaian'] ?? null) === null ? '' : ' M<sup>3</sup>' ?></td>
                        </tr>
                        <tr>
                            <th>Pemakaian Rata Rata / Jam</th>
                            <td><?= htmlspecialchars(fmtNum($data['pemakaian_rata2perjam'] ?? null, 2)) ?><?= ($data['pemakaian_rata2perjam'] ?? null) === null ? '' : ' M<sup>3</sup>' ?></td>
                        </tr>
                        <tr>
                            <th>Keterangan</th>
                            <td><?= htmlspecialchars($data['keterangan'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th>Created By</th>
                            <td><?= htmlspecialchars($data['creat_by'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td><?= htmlspecialchars(fmtDate($data['creat_at'] ?? null)) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>




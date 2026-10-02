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
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$id = $_GET['id'] ?? '';
if ($id === '') {
    $_SESSION['error'] = "ID tidak valid.";
    header('Location: padsteam.php');
    exit;
}

$sql = "SELECT 
            Id AS id,
            Tanggal AS tanggal,
            CreatBy AS creat_by,
            CreatAt AS creat_at,
            UpdateAt AS update_at,
            Meter_Awal AS meter_awal,
            Meter_Ahir AS meter_akhir,
            Oprasional_Mesin AS oprasional_mesin,
            Pemakaian_Rata2perjam AS pemakaian_rata2perjam,
            Keterangan AS keterangan
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

if (!$row || !is_array($row)) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: padsteam.php');
    exit;
}

$data = [
    'tanggal' => $row['tanggal'] ?? $row['Tanggal'] ?? $row[1] ?? null,
    'creat_by' => $row['creat_by'] ?? $row['CreatBy'] ?? $row[2] ?? null,
    'creat_at' => $row['creat_at'] ?? $row['CreatAt'] ?? $row[3] ?? null,
    'update_at' => $row['update_at'] ?? $row['UpdateAt'] ?? $row[4] ?? null,
    'meter_awal' => $row['meter_awal'] ?? $row['Meter_Awal'] ?? $row[5] ?? null,
    'meter_akhir' => $row['meter_akhir'] ?? $row['Meter_Ahir'] ?? $row[6] ?? null,
    'oprasional_mesin' => $row['oprasional_mesin'] ?? $row['Oprasional_Mesin'] ?? $row[7] ?? null,
    'pemakaian_rata2perjam' => $row['pemakaian_rata2perjam'] ?? $row['Pemakaian_Rata2perjam'] ?? $row[8] ?? null,
    'keterangan' => $row['keterangan'] ?? $row['Keterangan'] ?? $row[9] ?? null
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
            <h1>Detail Pemakaian Air padsteam</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <a href="padsteam.php" class="btn btn-secondary btn-sm float-right">Kembali</a>
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
                            <?php
                                $totalPemakaian = null;
                                if (is_numeric($data['meter_awal']) && is_numeric($data['meter_akhir'])) {
                                    $totalPemakaian = (float)$data['meter_akhir'] - (float)$data['meter_awal'];
                                }
                            ?>
                            <td><?= htmlspecialchars(fmtNum($totalPemakaian, 2)) ?><?= ($totalPemakaian === null) ? '' : ' M<sup>3</sup>' ?></td>
                        </tr>
                        <tr>
                            <th>Operasional Mesin / Jam</th>
                            <td><?= (strtolower(trim((string)($data['keterangan'] ?? ''))) === 'off') ? '-' : htmlspecialchars(fmtNum($data['oprasional_mesin'] ?? null, 2)) ?></td>
                        </tr>
                        <tr>
                            <th>Pemakaian Rata Rata / Jam</th>
                            <td><?= (strtolower(trim((string)($data['keterangan'] ?? ''))) === 'off') ? '-' : htmlspecialchars(fmtNum($data['pemakaian_rata2perjam'] ?? null, 2)) ?><?= (strtolower(trim((string)($data['keterangan'] ?? ''))) === 'off' || ($data['pemakaian_rata2perjam'] ?? null) === null) ? '' : ' M<sup>3</sup>' ?></td>
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
                        <tr>
                            <th>Update At</th>
                            <td><?= htmlspecialchars(fmtDate($data['update_at'] ?? null)) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>


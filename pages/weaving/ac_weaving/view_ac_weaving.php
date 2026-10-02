<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include(__DIR__ . '/../weaving_permissions.php');
include(__DIR__ . '/ac_weaving_helper.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: /gg_app/login.php');
    exit;
}
$permissions = weaving_require($conn, 'CanView');
$canEdit = weaving_can($permissions, 'CanEdit');

$id = $_GET['id'] ?? '';
$tanggalParam = trim($_GET['tanggal'] ?? '');
$mesinParam = trim($_GET['mesin'] ?? '');

if (!ac_weaving_table_exists($conn)) {
    $_SESSION['error'] = 'Tabel belum tersedia.';
    header('Location: ac_weaving.php');
    exit;
}

$sheet = null;
if ($id !== '' && ctype_digit((string)$id)) {
    $sheet = ac_weaving_resolve_sheet($conn, (int)$id);
}
if (!$sheet && $tanggalParam !== '' && $mesinParam !== '') {
    $sheet = ac_weaving_resolve_sheet_by_keys($conn, $tanggalParam, $mesinParam);
}

if (!$sheet) {
    $_SESSION['error'] = 'Data tidak ditemukan.';
    header('Location: ac_weaving.php');
    exit;
}

$tanggal = ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'Y-m-d');
$mesin = $sheet['Mesin'] ?? '';

function format_indo_date_single($value)
{
    $bulanIndo = [
        1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
        'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'
    ];
    $ts = strtotime($value);
    if (!$ts) return $value;
    $d = (int)date('d', $ts);
    $m = $bulanIndo[(int)date('n', $ts)] ?? strtoupper(date('F', $ts));
    $y = date('Y', $ts);
    return "$d $m $y";
}

$dateLabel = format_indo_date_single($tanggal);

$sql = "SELECT Mesin, Tanggal, Jam, Aktual_Check, Pb1_Dew_Point, Humidity, Amper,
            Differential_Best_Air, Petugas, [Shift] AS ShiftName
        FROM dbo.ac_weaving
        WHERE CAST(Tanggal AS DATE) = ?
          AND Mesin = ?
        ORDER BY CASE WHEN CAST(Jam AS TIME) >= '06:00:00' THEN 0 ELSE 1 END ASC,
                 CAST(Jam AS TIME) ASC, Id ASC";
$stmt = sqlsrv_query($conn, $sql, [$tanggal, $mesin]);
$rows = [];
if ($stmt !== false) {
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = [
            'tanggal' => ac_weaving_fmt_date($r['Tanggal'] ?? null, 'd/m/Y'),
            'jam' => ac_weaving_fmt_time($r['Jam'] ?? null),
            'dew_point' => ac_weaving_fmt_num($r['Pb1_Dew_Point'] ?? null, 2),
            'humidity' => ac_weaving_fmt_num($r['Humidity'] ?? null, 1),
            'amper' => ac_weaving_fmt_num($r['Amper'] ?? null, 0),
            'differential' => ac_weaving_fmt_num($r['Differential_Best_Air'] ?? null, 0),
            'petugas' => (string)($r['Petugas'] ?? ''),
            'shift' => (string)($r['ShiftName'] ?? ''),
        ];
    }
    sqlsrv_free_stmt($stmt);
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h1 class="m-0">Detail Pengecekan AC Weaving</h1>
                </div>
                <div class="col-sm-4 text-right">
                    <?php if ($canEdit): ?>
                        <a href="edit_ac_weaving.php?id=<?= urlencode((string)$id) ?>" class="btn btn-warning btn-sm mr-1"><i class="fas fa-edit"></i> Edit</a>
                    <?php endif; ?>
                    <a href="ac_weaving.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center justify-content-between">
                    <h3 class="card-title m-0"><i class="fas fa-eye mr-1"></i> Detail Data</h3>
                    <div>
                        <?php if ($canEdit): ?>
                            <a href="edit_ac_weaving.php?id=<?= urlencode((string)$id) ?>" class="btn btn-warning btn-sm text-dark"><i class="fas fa-edit"></i> Edit</a>
                        <?php endif; ?>
                        <a href="ac_weaving.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <tr><th width="20%">Tanggal</th><td><?= htmlspecialchars(ac_weaving_fmt_date($sheet['Tanggal'] ?? null, 'd/m/Y')) ?></td></tr>
                            <tr><th>AC</th><td><?= htmlspecialchars($sheet['Mesin'] ?? '-') ?></td></tr>
                            <tr><th>Petugas</th><td><?= htmlspecialchars(ac_weaving_petugas_for_sheet_query($conn, $tanggal, $sheet['Mesin'] ?? '')) ?: '-' ?></td></tr>
                            <tr><th>Shift</th><td><?= htmlspecialchars(ac_weaving_shifts_for_sheet_query($conn, $tanggal, $sheet['Mesin'] ?? '')) ?: ($sheet['ShiftName'] ?? '-') ?></td></tr>
                            <tr><th>Keterangan</th><td><?= htmlspecialchars($sheet['Keterangan'] ?? '-') ?></td></tr>
                            <tr><th>Created By</th><td><?= htmlspecialchars($sheet['CreatBy'] ?? '-') ?></td></tr>
                            <tr><th>Created At</th><td><?= htmlspecialchars(ac_weaving_fmt_date($sheet['CreatAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                            <tr><th>Update By</th><td><?= htmlspecialchars($sheet['UpdateBy'] ?? '-') ?></td></tr>
                            <tr><th>Update At</th><td><?= htmlspecialchars(ac_weaving_fmt_date($sheet['UpdateAt'] ?? null, 'd/m/Y H:i')) ?></td></tr>
                        </table>
                    </div>
                    <div class="ac-report-wrap">
                        <div class="ac-report-panel">
                            <table class="ac-report">
                                <colgroup>
                                    <col style="width:12%">
                                    <col style="width:8%">
                                    <col style="width:11%">
                                    <col style="width:10%">
                                    <col style="width:8%">
                                    <col style="width:13%">
                                    <col style="width:28%">
                                    <col style="width:10%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="sheet-title" colspan="8">PENGECEKAN TEMPERATUR AREA WEAVING</th>
                                    </tr>
                                    <tr>
                                        <th class="date-title" colspan="6" style="white-space: nowrap;">TANGGAL : <?= htmlspecialchars($dateLabel) ?></th>
                                        <th class="date-title text-center" colspan="2" style="white-space: nowrap;">SUM-FM-THK-WV-005</th>
                                    </tr>
                                    <tr>
                                        <th class="machine-title" colspan="8"><?= htmlspecialchars($mesin) ?></th>
                                    </tr>
                                    <tr>
                                        <th class="head">TANGGAL</th>
                                        <th class="head">JAM</th>
                                        <th class="head">pB1 Dew<br>Point</th>
                                        <th class="head">HUMIDITY</th>
                                        <th class="head">AMPER</th>
                                        <th class="head">DEFFERENTIAL<br>BEST AIR</th>
                                        <th class="head">PETUGAS</th>
                                        <th class="head">SHIFT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr>
                                            <td class="empty" colspan="8">Tidak ada data.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['tanggal']) ?></td>
                                                <td><?= htmlspecialchars($row['jam']) ?></td>
                                                <td><?= htmlspecialchars($row['dew_point']) ?></td>
                                                <td><?= htmlspecialchars($row['humidity']) ?></td>
                                                <td><?= htmlspecialchars($row['amper']) ?></td>
                                                <td><?= htmlspecialchars($row['differential']) ?></td>
                                                <td class="text-left" title="<?= htmlspecialchars($row['petugas']) ?>"><?= htmlspecialchars($row['petugas']) ?></td>
                                                <td><?= htmlspecialchars($row['shift']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
</div>

<style>
.ac-report-wrap {
    overflow: auto;
    background: #f8fafc;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
}
.ac-report-panel {
    background: #fff;
    border: 1px solid #64748b;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.ac-report {
    width: 100%;
    min-width: 760px;
    table-layout: fixed;
    border-collapse: collapse;
    border-spacing: 0;
    background: #fff;
    font-size: 11px;
}
.ac-report th,
.ac-report td {
    border: 1px solid #111;
    text-align: center;
    vertical-align: middle;
    padding: 3px 4px;
    white-space: nowrap;
    height: 22px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ac-report .sheet-title {
    background: #fff;
    font-size: 12px;
    font-weight: 800;
    text-align: center;
}
.ac-report .date-title {
    background: #fff;
    font-weight: 700;
    text-align: left;
    font-size: 11px;
}
.ac-report .date-title.text-center {
    text-align: center;
}
.ac-report .machine-title {
    background: #fff;
    text-align: left;
    font-weight: 800;
    font-size: 11px;
}
.ac-report .head {
    background: #9dc3e6;
    font-weight: 700;
    color: #000;
    font-size: 10px;
    line-height: 1.15;
}
.ac-report tbody tr:nth-child(even) td {
    background: #fbfdff;
}
.ac-report .text-left {
    text-align: left;
}
.ac-report .empty {
    color: #64748b;
    background: #f8fafc;
}
@media (max-width: 767.98px) {
    .ac-report {
        font-size: 10px;
    }
}
</style>

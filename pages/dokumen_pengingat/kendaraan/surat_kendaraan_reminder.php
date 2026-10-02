<?php
// ======================================================
// surat_kendaraan_reminder.php — FINAL (MATCH TABLE)
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------
// CORE INCLUDE
// ------------------------------------------------------
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// ------------------------------------------------------
// PERMISSION
// ------------------------------------------------------
$menuId = 171; // MENU SURAT KENDARAAN
requireView($conn, $menuId);

$theme = $_SESSION['Theme'] ?? 'primary';

// ------------------------------------------------------
// AMBIL INTERVAL REMINDER (HARI)
// dr_setting(setting_key, setting_value)
// ------------------------------------------------------
$reminderDays = 7;

$st = sqlsrv_query(
    $conn,
    "SELECT CAST(setting_value AS INT) AS hari
     FROM dr_setting
     WHERE setting_key = 'reminder_interval_surat_kendaraan'"
);

if ($st && ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) {
    if (is_numeric($r['hari'])) {
        $reminderDays = (int)$r['hari'];
    }
}

// ------------------------------------------------------
// QUERY SURAT KENDARAAN REMINDER
// ------------------------------------------------------
$sql = "
SELECT
    sk.id,
    sk.nama_kendaraan,
    sk.no_polisi,
    sk.expire_date,
    sk.file_kendaraan,
    b.nama_bagian
FROM dr_surat_kendaraan sk
LEFT JOIN dr_bagian b ON sk.bagian_id = b.id
WHERE sk.expire_date >= CAST(GETDATE() AS DATE)
  AND sk.expire_date <= DATEADD(DAY, ?, CAST(GETDATE() AS DATE))
ORDER BY sk.expire_date ASC
";

$stmt = sqlsrv_query($conn, $sql, [$reminderDays]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<div class="content-wrapper">

<section class="content-header">
    <div class="container-fluid">
        <h1>Surat Kendaraan Reminder</h1>
        <small class="text-muted">
            Akan kadaluarsa dalam <?= $reminderDays ?> hari
        </small>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
<div class="card-header">
    <h3 class="card-title">Daftar Surat Kendaraan Reminder</h3>
    <div class="card-tools">
        <a href="export_excel/export_excel_surat_kendaraan_reminder.php"
           class="btn btn-success btn-sm">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="export_pdf/export_pdf_surat_kendaraan_reminder.php"
           class="btn btn-danger btn-sm">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
    </div>
</div>

<div class="card-body">
<table id="example1" class="table table-bordered table-striped">
<thead>
<tr>
    <th>No</th>
    <th>Nama Kendaraan</th>
    <th>No Polisi</th>
    <th>Tgl Expire</th>
    <th>File</th>
    <th>Bagian</th>
    <th>Status</th>
</tr>
</thead>
<tbody>

<?php
$no = 1;
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $expire = $row['expire_date'] instanceof DateTimeInterface
        ? $row['expire_date']->format('Y-m-d')
        : '-';

    $fileUrl = !empty($row['file_kendaraan'])
        ? "/gg_app/pages/dokumen_pengingat/surat_kendaraan/uploads/kendaraan/"
          . rawurlencode($row['file_kendaraan'])
        : null;

    echo "<tr>
        <td>{$no}</td>
        <td>".htmlspecialchars($row['nama_kendaraan'])."</td>
        <td>".htmlspecialchars($row['no_polisi'])."</td>
        <td>{$expire}</td>
        <td class='text-center'>"
            . ($fileUrl
                ? "<a href='{$fileUrl}' target='_blank'>
                        <i class='fas fa-eye'></i> Lihat
                   </a>"
                : "-")
        . "</td>
        <td>".htmlspecialchars($row['nama_bagian'] ?? '-')."</td>
        <td><span class='badge badge-warning'>Reminder</span></td>
    </tr>";

    $no++;
}
?>

</tbody>
</table>
</div>
</div>

</div>
</section>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<script>
$(function () {
    $('#example1').DataTable({
        responsive: true,
        autoWidth: false,
        ordering: true,
        dom:
            "<'row'<'col-sm-6'l><'col-sm-6 text-right'f>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row'<'col-sm-5'i><'col-sm-7'p>>"
    });
});
</script>

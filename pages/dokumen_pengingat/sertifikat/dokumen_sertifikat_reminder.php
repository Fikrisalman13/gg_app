<?php
// ======================================================
// sertifikat_reminder.php — FINAL (FIXED & CONSISTENT)
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
$menuId = 169; // MENU SERTIFIKAT
requireView($conn, $menuId);

// ------------------------------------------------------
// AMBIL INTERVAL REMINDER (HARI)
// dr_setting: setting_key | setting_value
// ------------------------------------------------------
$reminderDays = 7; // default

$st = sqlsrv_query(
    $conn,
    "SELECT CAST(setting_value AS INT) AS hari
     FROM dr_setting
     WHERE setting_key = 'reminder_interval_sertifikat'"
);

if ($st && ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC))) {
    if (is_numeric($r['hari'])) {
        $reminderDays = (int)$r['hari'];
    }
}

// ------------------------------------------------------
// QUERY SERTIFIKAT REMINDER
// expire_date BETWEEN today AND today + reminderDays
// ------------------------------------------------------
$sql = "
SELECT
    s.id,
    s.nama_lembaga,
    s.nama_sertifikat,
    s.no_sertifikat,
    s.expire_date,
    s.file_path,
    b.nama_bagian
FROM dr_sertifikat s
LEFT JOIN dr_bagian b ON s.bagian_id = b.id
WHERE s.expire_date >= CAST(GETDATE() AS DATE)
  AND s.expire_date <= DATEADD(DAY, ?, CAST(GETDATE() AS DATE))
ORDER BY s.expire_date ASC
";

$stmt = sqlsrv_query($conn, $sql, [$reminderDays]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$theme = $_SESSION['Theme'] ?? 'primary';
?>

<div class="content-wrapper">

<section class="content-header">
    <div class="container-fluid">
        <h1>Sertifikat Reminder</h1>
        <small class="text-muted">
            Akan kadaluarsa dalam <?= (int)$reminderDays ?> hari
        </small>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
    <div class="card-header bg-<?= htmlspecialchars($theme) ?>">
        <h3 class="card-title text-white">Daftar Sertifikat Reminder</h3>
        <div class="card-tools">
            <a href="../export_excel/export_excel_sertifikat_reminder.php"
               class="btn btn-success btn-sm">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="../export_pdf/export_pdf_sertifikat_reminder.php"
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
                <th>Nama Lembaga</th>
                <th>Nama Sertifikat</th>
                <th>No Sertifikat</th>
                <th>Tgl Expired</th>
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

    $fileUrl = !empty($row['file_path'])
        ? "/gg_app/pages/dokumen_pengingat/sertifikat/uploads/sertifikat/"
          . rawurlencode($row['file_path'])
        : null;

    echo "<tr>
        <td>{$no}</td>
        <td>".htmlspecialchars($row['nama_lembaga'])."</td>
        <td>".htmlspecialchars($row['nama_sertifikat'])."</td>
        <td>".htmlspecialchars($row['no_sertifikat'])."</td>
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
        ordering: true
    });
});
</script>

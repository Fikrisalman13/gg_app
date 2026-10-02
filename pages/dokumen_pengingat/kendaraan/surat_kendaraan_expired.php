<?php
// ======================================================
// surat_kendaraan_kadaluarsa.php — FINAL (MATCH TABLE)
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
// QUERY SURAT KENDARAAN KADALUARSA
// Kadaluarsa = expire_date < hari ini
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
WHERE sk.expire_date < CAST(GETDATE() AS DATE)
ORDER BY sk.expire_date DESC
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<div class="content-wrapper">

<section class="content-header">
    <div class="container-fluid">
        <h1>Surat Kendaraan Kadaluarsa</h1>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
<div class="card-header">
    <h3 class="card-title">Daftar Surat Kendaraan Kadaluarsa</h3>
    <div class="card-tools">
        <a href="export_excel/export_excel_surat_kendaraan_expired.php"
           class="btn btn-success btn-sm">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="export_pdf/export_pdf_surat_kendaraan_expired.php"
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
        <td><span class='badge badge-danger'>Kadaluarsa</span></td>
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

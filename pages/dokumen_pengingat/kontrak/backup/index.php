<?php
// ======================================================
// dokumen.php — Dokumen Kontrak (FINAL - m_bag FIXED)
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
$menuId = 168;
requireView($conn, $menuId);

// permission sekali saja
$perm = userPermissions($conn, $menuId);

$theme    = $_SESSION['Theme'] ?? 'primary';
$username = $_SESSION['UserName'] ?? null;

// ------------------------------------------------------
// AMBIL BAGIAN USER LOGIN (m_bag)
// ------------------------------------------------------
$idDeptUser = null;

$stmtDeptUser = sqlsrv_query(
    $conn,
    "SELECT b.id_bag
     FROM dbo.SMUserMs u
     JOIN dbo.m_emp e ON u.EmpId = e.id_emp
     JOIN dbo.m_bag b ON e.id_bag = b.id_bag
     WHERE u.UserName = ?",
    [$username]
);

if ($stmtDeptUser && $r = sqlsrv_fetch_array($stmtDeptUser, SQLSRV_FETCH_ASSOC)) {
    $idDeptUser = $r['id_bag'];
}
sqlsrv_free_stmt($stmtDeptUser);
?>

<div class="content-wrapper">

    <!-- CONTENT HEADER -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Dokumen Kontrak</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item">
                            <a href="/gg_app/index.php">Home</a>
                        </li>
                        <li class="breadcrumb-item active">Dokumen Kontrak</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- ACTION BUTTON -->
            <div class="row mb-3">
                <div class="col-12">
                    <?php if ($perm['CanAdd'] == 1): ?>
                        <a href="/gg_app/pages/dokumen_pengingat/kontrak/tambah_dokumen_kontrak.php"
                           class="btn btn-success">
                            <i class="fas fa-plus"></i> Tambah Dokumen Kontrak
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
                <div class="card-header">
                    <h3 class="card-title">Daftar Dokumen Kontrak</h3>
                    <div class="card-tools">
                        <a href="/gg_app/pages/dokumen_pengingat/export_excel/export_excel_kontrak.php"
                           class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="/gg_app/pages/dokumen_pengingat/export_pdf/export_pdf_kontrak.php"
                           class="btn btn-danger btn-sm">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <table id="example1" class="table table-bordered table-striped">
                        <thead>
                        <tr>
                            <th width="40">#</th>
                            <th>Nama Vendor</th>
                            <th>Nama Pekerjaan</th>
                            <th>No Kontrak</th>
                            <th>Expire Date</th>
                            <th>File</th>
                            <th>Keterangan</th>
                            <th>Bagian</th>
                            <th>Tgl Dibuat</th>
                            <th width="120">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>

<?php
// ------------------------------------------------------
// QUERY KONTRAK (JOIN m_bag, FILTER BAGIAN USER)
// ------------------------------------------------------
$sql = "
    SELECT
        a.id,
        a.nama_vendor,
        a.nama_pekerjaan,
        a.no_kontrak,
        a.expire_date,
        a.file_path,
        a.keterangan,
        mb.bagian AS nama_bagian,
        a.createdate
    FROM dr_kontrak a
    LEFT JOIN dbo.m_bag mb
        ON a.bagian_id = mb.id_bag
    WHERE a.bagian_id = ?
    ORDER BY a.createdate DESC
";

$stmt = sqlsrv_query($conn, $sql, [$idDeptUser]);

if ($stmt) {
    $no = 1;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

        $expireDate = ($row['expire_date'] instanceof DateTimeInterface)
            ? $row['expire_date']->format('Y-m-d')
            : '-';

        $createDate = ($row['createdate'] instanceof DateTimeInterface)
            ? $row['createdate']->format('Y-m-d H:i')
            : '-';

        $filePath = trim((string)$row['file_path']);
        $fileUrl  = $filePath !== ''
            ? "/gg_app/pages/dokumen_pengingat/kontrak/uploads/kontrak/" . rawurlencode($filePath)
            : null;

        echo "<tr>";
        echo "<td>{$no}</td>";
        echo "<td>" . htmlspecialchars($row['nama_vendor']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_pekerjaan']) . "</td>";
        echo "<td>" . htmlspecialchars($row['no_kontrak']) . "</td>";
        echo "<td>{$expireDate}</td>";

        echo "<td class='text-center'>";
        if ($fileUrl) {
            echo "<a href='{$fileUrl}' target='_blank'>
                    <i class='fas fa-eye'></i> Lihat
                  </a>";
        } else {
            echo "<span class='text-muted'>-</span>";
        }
        echo "</td>";

        echo "<td>" . htmlspecialchars($row['keterangan'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_bagian'] ?? '-') . "</td>";
        echo "<td>{$createDate}</td>";

        echo "<td class='text-center'>";

        if ($perm['CanEdit'] == 1) {
            echo "
                <a href='/gg_app/pages/dokumen_pengingat/kontrak/edit_dokumen_kontrak.php?id={$row['id']}'
                   class='btn btn-primary btn-xs'>
                   <i class='fas fa-edit'></i>
                </a>
            ";
        }

        if ($perm['CanDelete'] == 1) {
            echo "
                <a href='/gg_app/pages/dokumen_pengingat/kontrak/hapus_dokumen_kontrak.php?id={$row['id']}'
                   class='btn btn-danger btn-xs'
                   onclick=\"return confirm('Yakin ingin menghapus data ini?')\">
                   <i class='fas fa-trash'></i>
                </a>
            ";
        }

        echo "</td>";
        echo "</tr>";

        $no++;
    }
    sqlsrv_free_stmt($stmt);
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
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $('#example1').DataTable({
            responsive: true,
            autoWidth: false,
            ordering: true
        });
    } else {
        console.error('jQuery / DataTables belum ter-load');
    }
});
</script>

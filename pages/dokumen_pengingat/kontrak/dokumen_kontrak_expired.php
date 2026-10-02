<?php
// ======================================================
// kontrak_kadaluarsa.php — FINAL (SQL SERVER)
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
$menuId = 172; // MENU KONTRAK KADALUARSA (sesuaikan)
requireView($conn, $menuId);

// ------------------------------------------------------
// QUERY KONTRAK KADALUARSA
// Kadaluarsa = expire_date < hari ini
// ------------------------------------------------------

// Ambil bagian_id user
$idDeptUser = null;
$username = $_SESSION['UserName'] ?? null;
if ($username) {
    $stmtDeptUser = sqlsrv_query(
        $conn,
        "SELECT b.id_bag FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp JOIN dbo.m_bag b ON e.id_bag = b.id_bag WHERE u.UserName = ?",
        [$username]
    );
    if ($stmtDeptUser && $r = sqlsrv_fetch_array($stmtDeptUser, SQLSRV_FETCH_ASSOC)) {
        $idDeptUser = $r['id_bag'];
    }
    sqlsrv_free_stmt($stmtDeptUser);
}

$whereBagian = '';
if ($idDeptUser !== null && $idDeptUser != 1) {
    $whereBagian = 'AND k.bagian_id = ' . (int)$idDeptUser;
}

$sql = "
SELECT
    k.id,
    k.nama_vendor,
    k.nama_pekerjaan,
    k.no_kontrak,
    k.expire_date,
    k.file_path,
    b.nama_bagian
FROM dr_kontrak k
LEFT JOIN dr_bagian b ON k.bagian_id = b.id
WHERE k.expire_date < CAST(GETDATE() AS DATE)
    $whereBagian
ORDER BY k.expire_date DESC
";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die('Query error: ' . print_r(sqlsrv_errors(), true));
}

$theme = $_SESSION['Theme'] ?? 'primary';
?>

<div class="content-wrapper">

    <!-- CONTENT HEADER -->
    <section class="content-header">
        <div class="container-fluid">
            <h1>Kontrak Kadaluarsa</h1>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
                <div class="card-header">
                    <h3 class="card-title">Daftar Kontrak Kadaluarsa</h3>
                    <div class="card-tools">
                        <a href="../export_excel/export_excel_kontrak_expired.php"
                           class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="../export_excel/export_pdf_kontrak_expired.php"
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
                            <th>Nama Vendor</th>
                            <th>Nama Pekerjaan</th>
                            <th>No Kontrak</th>
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

    $fileLink = !empty($row['file_path'])
        ? "<a href='uploads/kontrak/" . htmlspecialchars($row['file_path']) . "' target='_blank'>
                <i class='fas fa-eye'></i> Lihat
           </a>"
        : "-";

    echo "<tr>";
    echo "<td>{$no}</td>";
    echo "<td>" . htmlspecialchars($row['nama_vendor']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_pekerjaan']) . "</td>";
    echo "<td>" . htmlspecialchars($row['no_kontrak']) . "</td>";
    echo "<td>{$expire}</td>";
    echo "<td class='text-center'>{$fileLink}</td>";
    echo "<td>" . htmlspecialchars($row['nama_bagian'] ?? '-') . "</td>";
    echo "<td><span class='badge badge-danger'>Kadaluarsa</span></td>";
    echo "</tr>";

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

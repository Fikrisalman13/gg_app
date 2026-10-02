<?php
// ===================================================
// 1. INIT: SESSION & BUFFERING
// ===================================================
session_start();
ob_start();

// Database connection
require_once __DIR__ . '/../../koneksi.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Cek koneksi database
if (!$conn) {
    die("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
}

// ===================================================
// 2. CEK HAK AKSES
// ===================================================
$groupId = $_SESSION['GroupId'];
$menuId  = 67; // Menu Output Packing

$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
        FROM dbo.SMGroupTrustee
        WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

$permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $permissions = $row;
}
sqlsrv_free_stmt($stmt);

// Jika tidak punya akses view
if ($permissions['CanView'] == 0) {
    include '../../includes/header.php';
    include '../../includes/sidebar.php';
    echo '<div class="content-wrapper"><div class="content">
            <div class="alert alert-danger m-3">Anda tidak memiliki hak untuk melihat halaman ini.</div>
          </div></div>';
    include '../../includes/footer.php';
    exit;
}

// ===================================================
// 3. AMBIL DATA PACKING
// ===================================================
$query = "SELECT id, tanggal, qty, qty_a1 FROM packing_output ORDER BY tanggal DESC";
$stmt  = sqlsrv_query($conn, $query);

$packingData = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $packingData[] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// ===================================================
// 4. LOAD TEMPLATE
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- ===================================================
    CONTENT WRAPPER
======================================================= -->
<div class="content-wrapper">

    <!-- Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Data Output Packing</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Data Output Packing</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Content -->
    <section class="content">
    <div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="card shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-list mr-1"></i> Daftar Data Output Packing Harian
                    </h3>

                    <?php if ($permissions['CanEdit'] == 1): ?>
                        <a href="auto_packing_settings.php" class="btn btn-info btn-sm float-right mr-2">
                            <i class="fas fa-clock"></i> Otomatis
                        </a>
                    <?php endif; ?>
                    <?php if ($permissions['CanAdd'] == 1): ?>
                        <a href="add_outputvpk.php" class="btn btn-success btn-sm float-right">
                            <i class="fas fa-plus"></i> Tambah Data
                        </a>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <table id="packingTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">Tanggal</th>
                                <th width="20%">Qty Packing</th>
                                <th width="20%">Qty A1</th>
                                <th width="15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packingData)): ?>
                                <tr><td colspan="5" class="text-center">Tidak ada data packing</td></tr>
                            <?php else: ?>
                                <?php foreach ($packingData as $i => $row): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <?= ($row['tanggal'] instanceof DateTime)
                                                ? $row['tanggal']->format('d-m-Y')
                                                : date('d-m-Y', strtotime($row['tanggal'])) ?>
                                        </td>
                                        <td><?= number_format($row['qty'], 0, ',', '.') ?></td>
                                        <td><?= number_format($row['qty_a1'], 0, ',', '.') ?></td>
                                        <td class="text-center">

                                            <?php if ($permissions['CanEdit'] == 1): ?>
                                            <a href="edit_outputvpk.php?id=<?= $row['id'] ?>"
                                               class="btn btn-warning btn-sm">
                                               <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>

                                            <?php if ($permissions['CanDelete'] == 1): ?>
                                            <button class="btn btn-danger btn-sm btn-delete"
                                                    data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>

                                        </td>
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

<?php include '../../includes/footer.php'; ?>


<!-- ===================================================
    DATATABLES & SWEETALERT
======================================================= -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>


<script>
$(document).ready(function() {

    // DataTable
    $('#packingTable').DataTable({
        responsive: true,
        autoWidth: false,
        language: {
            lengthMenu: "Tampilkan _MENU_ data",
            zeroRecords: "Tidak ada data",
            info: "Halaman _PAGE_ dari _PAGES_",
            search: "Cari:",
            paginate: {
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        }
    });

    // Delete Confirmation
    $(document).on("click", ".btn-delete", function() {
        let id = $(this).data("id");

        Swal.fire({
            title: "Hapus Data?",
            text: "Data ini akan dihapus permanen.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Ya, Hapus!"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete_outputvpk.php?id=" + id;
            }
        });
    });

    // Success Notification
    <?php if (!empty($_SESSION['success'])): ?>
    Swal.fire({ icon: "success", title: "Sukses!", text: "<?= $_SESSION['success'] ?>", timer: 2000, showConfirmButton: false });
    <?php unset($_SESSION['success']); endif; ?>

    // Error Notification
    <?php if (!empty($_SESSION['error'])): ?>
    Swal.fire({ icon: "error", title: "Gagal!", text: "<?= $_SESSION['error'] ?>", timer: 2000, showConfirmButton: false });
    <?php unset($_SESSION['error']); endif; ?>

});
</script>

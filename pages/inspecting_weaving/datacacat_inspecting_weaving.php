<?php
session_start();
ob_start();
// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}


// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// Ambil MenuId untuk Inspecting Weaving
$menuId = 23; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);

// Cek apakah pengguna memiliki hak akses CanView
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}
ob_end_flush();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Data Cacat Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Data Cacat Inspect Weaving</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                        <h3 class="card-title">Daftar Data Cacat Inspect Weaving</h3>
                        <?php if (isset($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="add_data_cacat.php" class="btn btn-success btn-sm float-right">
                                <i class="fas fa-plus"></i> Tambah Data Cacat
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body table-responsive">
                        <table id="dataCacatTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No CP</th>
                                    <th>No Detail</th>
                                    <th>Kode Cacat</th>                                    
                                    <th>Dari</th>
                                    <th>Sampai</th>
                                    <th>Point</th>                                   
                                    <th>UpdDate</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                               
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    $('#dataCacatTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: 'ajax_datacacat_inspecting_weaving.php',
    columns: [
        { data: 'no' },
        { data: 'NoCP' },
        { data: 'NoDetail' },
        { data: 'CacatKode' },
        { data: 'MeterKe' },
        { data: 'SMeterKe' },
        { data: 'PointCacat' },
        { data: 'UpdDate' },
        { data: 'aksi', orderable: false, searchable: false }
    ]
});


        // Notifikasi sukses
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Sukses!',
                text: "<?= $_SESSION['success'] ?>",
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        // Notifikasi error
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: "<?= $_SESSION['error'] ?>",
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        // Event delegation untuk tombol delete
        $(document).on('click', '.btn-delete', function () {
    let id = $(this).data('id'); // Ambil Id dari atribut data-id
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: 'Data akan dihapus secara permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'delete_data_cacat.php',
                type: 'POST',
                data: { id: id }, // Kirim Id sebagai id
                success: function (response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Data telah dihapus.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan saat menghapus data.'
                    });
                }
            });
        }
    });
});
    
</script>

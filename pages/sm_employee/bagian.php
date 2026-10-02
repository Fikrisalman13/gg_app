<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil GroupId dari session
$groupId = $_SESSION['GroupId'];

// MenuId khusus untuk halaman Bagian
$menuId = 39; // Sesuaikan dengan MenuId untuk bagian

// Ambil hak akses
$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = [];
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Jika tidak punya hak lihat
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
                    <h1 class="m-0">Bagian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Bagian</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                        <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                        Daftar Bagian</h3>
                        <?php if (isset($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href="add_bagian.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Bagian</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body table-responsive">
                        <table id="bagianTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Bagian</th>
                                    <th>Singkatan</th>
                                    <th>Nama Departemen</th>
                                    <th>Tgl Pembaharuan</th>
                                    <th>Diperbaharui</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT
                                            m_bag.id_bag, 
                                            m_bag.bagian, 
                                            m_bag.sn_bagian,                                         
                                            m_dept.dept,
                                            m_bag.upddate,
                                            m_bag.upduser
                                        FROM
                                            dbo.m_bag
                                            LEFT JOIN
                                            dbo.m_dept
                                            ON 
                                                m_bag.id_dept = m_dept.id_dept";
                                $stmt = sqlsrv_query($conn, $sql);

                                if ($stmt === false) {
                                    echo "<tr><td colspan='7' class='text-center text-danger'>Gagal mengambil data.</td></tr>";
                                } else {
                                    $no = 1;
                                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                        echo "<tr>";
                                        echo "<td>{$no}</td>";
                                        echo "<td>" . htmlspecialchars($row['bagian']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['sn_bagian']) . "</td>";                                     
                                        echo "<td>" . htmlspecialchars($row['dept']) . "</td>";
                                        echo "<td>" . ($row['upddate'] ? $row['upddate']->format('Y-m-d H:i:s') : '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($row['upduser']) . "</td>";
                                        echo "<td>";
                                        if (isset($permissions['CanEdit']) && $permissions['CanEdit'] == 1) {
                                            echo "<a href='edit_bagian.php?id=" . urlencode($row['id_bag']) . "' class='btn btn-warning btn-sm'><i class='fas fa-edit'></i></a> ";
                                        }
                                        if (isset($permissions['CanDelete']) && $permissions['CanDelete'] == 1) {
                                            echo "<button class='btn btn-danger btn-sm btn-delete' data-id='{$row['id_bag']}'><i class='fas fa-trash'></i></button>";
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
    $(document).ready(function () {
    $('#bagianTable').DataTable({
        responsive: true,
        autoWidth: false,
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        },
    });
    // Gunakan event delegation agar tombol delete tetap berfungsi di semua halaman DataTable
    $(document).on('click', '.btn-delete', function () {
        let idBag = $(this).data('id');
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
                window.location.href = '/gg_app/pages/sm_employee/delete_bagian.php?id=' + idBag;
            }
        });
    });

        <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= $_SESSION['success'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= $_SESSION['error'] ?>",
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['error']); endif; ?>
    });
</script>

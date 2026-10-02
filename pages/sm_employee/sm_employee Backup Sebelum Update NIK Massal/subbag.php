<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Cek login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Cek koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Ambil hak akses
$groupId = $_SESSION['GroupId'];
$menuId = 40; // Ganti dengan MenuId untuk Subbag

$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);

$permissions = [];
if ($stmt === false) {
    die("Query gagal: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
}

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
                    <h1 class="m-0">Sub Bagian</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Sub Bagian</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                        <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                            Daftar Sub Bagian</h3>
                        <?php if (!empty($permissions['CanAdd'])): ?>
                            <a href="add_subbag.php" class="btn btn-success btn-sm float-right"><i class="fas fa-plus"></i> Tambah Sub Bagian</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body table-responsive">
                        <table id="subbagTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Sub Bagian</th>
                                    <th>Singkatan</th>
                                    <th>Bagian</th>
                                    <th>Departemen</th>
                                    <th>Tgl Update</th>
                                    <th>Oleh</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "
                                    SELECT
                                        m_subbag.id_subbag, 
                                        m_subbag.subbag, 
                                        m_subbag.sn_subbag,
                                        m_bag.bagian, 
                                        m_dept.dept,
                                        m_subbag.upddate, 
                                        m_subbag.upduser
                                    FROM
                                        dbo.m_subbag
                                    LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
                                    LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
                                ";
                                $stmt = sqlsrv_query($conn, $query);

                                if ($stmt === false) {
                                    echo "<tr><td colspan='8' class='text-danger'>Gagal mengambil data.</td></tr>";
                                } else {
                                    $no = 1;
                                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                        echo "<tr>";
                                        echo "<td>{$no}</td>";
                                        echo "<td>" . htmlspecialchars($row['subbag']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['sn_subbag']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['bagian']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['dept']) . "</td>";
                                        echo "<td>" . ($row['upddate'] ? $row['upddate']->format('Y-m-d H:i:s') : '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($row['upduser']) . "</td>";
                                        echo "<td>";
                                        if (!empty($permissions['CanEdit'])) {
                                            echo "<a href='edit_subbag.php?id=" . urlencode($row['id_subbag']) . "' class='btn btn-warning btn-sm'><i class='fas fa-edit'></i></a> ";
                                        }
                                        if (!empty($permissions['CanDelete'])) {
                                            echo "<button class='btn btn-danger btn-sm btn-delete' data-id='{$row['id_subbag']}'><i class='fas fa-trash'></i></button>";
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
    </section>
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
        $('#subbagTable').DataTable({
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
            let idSubbag = $(this).data('id');
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
                    window.location.href = '/gg_app/pages/sm_employee/delete_subbag.php?id=' + idSubbag;
                }
            });
        });

        <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Sukses!', text: "<?= $_SESSION['success'] ?>", timer: 3000, showConfirmButton: false });
        <?php unset($_SESSION['success']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Gagal!', text: "<?= $_SESSION['error'] ?>", timer: 3000, showConfirmButton: false });
        <?php unset($_SESSION['error']); endif; ?>
    });
</script>


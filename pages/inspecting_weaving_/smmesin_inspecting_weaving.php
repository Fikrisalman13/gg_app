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

// Ambil MenuId untuk SMMesin Inspector Weaving
$menuId = 15; // Sesuaikan dengan MenuId yang benar

// Query untuk mengambil hak akses berdasarkan GroupId dan MenuId
$sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

// Cek jika query berhasil dan ambil hak akses
$permissions = [];
if ($stmt === false) {
    die("Terjadi kesalahan dalam query: " . print_r(sqlsrv_errors(), true));
} else {
    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
}

// Cek apakah pengguna memiliki hak akses CanView
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesin</title>
    <!-- CSS -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Mesin</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Mesin</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?= $error_message ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                        <h3 class="card-title">Daftar Mesin Inspector</h3>
                        <?php if (isset($permissions['CanAdd']) && $permissions['CanAdd'] == 1): ?>
                            <a href='add_smmesin_inspecting_weaving.php' class='btn btn-success btn-sm float-right'><i class='fas fa-plus'></i> Tambah Mesin</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body table-responsive">
                        <table id="mesinTable" class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>Mesin Tipe</th>
                                    <th>No Mesin</th>
                                    <th>Nama Mesin</th>
                                    <th>Update Date</th>
                                    <th>Updated By</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT
                                            b.TypeName, 
                                            a.MesinNo, 
                                            a.MesinName, 
                                            a.UpdDate, 
                                            a.UpdUser
                                        FROM
                                            dbo.SMMesinInspector AS a
                                        LEFT JOIN
                                            dbo.SMMesinType AS b
                                        ON 
                                            a.TypeId = b.TypeId
                                        ORDER BY
                                            a.TypeId ASC, 
                                            a.MesinNo ASC";
                                $stmt = sqlsrv_query($conn, $sql);

                                if ($stmt === false) {
                                    echo "<tr><td colspan='7' class='text-center text-danger'>Terjadi kesalahan dalam mengambil data.</td></tr>";
                                } else {
                                    $no = 1;
                                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                        echo "<tr>";
                                        echo "<td>{$no}</td>";
                                        echo "<td>" . htmlspecialchars($row['TypeName']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['MesinNo']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['MesinName']) . "</td>";
                                        echo "<td>" . ($row['UpdDate'] ? $row['UpdDate']->format('Y-m-d H:i:s') : '-') . "</td>";
                                        echo "<td>" . htmlspecialchars($row['UpdUser']) . "</td>";
                                        echo "<td>";
                                        if (isset($permissions['CanEdit']) && $permissions['CanEdit'] == 1) {
                                            echo "<a href='edit_smmesin_inspecting_weaving.php?no=" . urlencode($row['MesinNo']) . "' class='btn btn-warning btn-sm'><i class='fas fa-edit'></i></a> ";
                                        }
                                        if (isset($permissions['CanDelete']) && $permissions['CanDelete'] == 1) {
                                            echo "<button class='btn btn-danger btn-sm btn-delete' data-no='{$row['MesinNo']}'><i class='fas fa-trash'></i></button>";
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
        // Inisialisasi DataTables dengan destroy agar event tetap bekerja
        $('#mesinTable').DataTable({
            "destroy": true
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

        // Event delegation untuk tombol delete agar tetap bekerja di DataTables
        $(document).on('click', '.btn-delete', function () {
            let mesinNo = $(this).data('no');
            console.log("No Mesin yang akan dihapus:", mesinNo); // Debugging

            if (!mesinNo) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'No Mesin tidak valid!'
                });
                return;
            }

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
                        url: 'delete_smmesin_inspecting_weaving.php',
                        type: 'POST',
                        data: { no: mesinNo },
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
    });
</script>
</body>
</html>


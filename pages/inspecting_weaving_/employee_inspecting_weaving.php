<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 2. PENGATURAN NOTIFIKASI
// ===================================================
// *Menangani notifikasi sukses dan error dari session
$successMessage = $_SESSION['success'] ?? null;
$errorMessage = $_SESSION['error'] ?? null;
// *Hapus notifikasi dari session setelah diambil
unset($_SESSION['success'], $_SESSION['error']);

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../../includes/header.php';
include '../../includes/sidebar.php';
// Import konstanta dan permission
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireView($conn, MENU_EMP_INSPECT);
$perm = userPermissions($conn, MENU_EMP_INSPECT);

// ===================================================
// 7. FUNGSI BANTU
// ===================================================
function getAllEmp($conn)
{
    $sql = "
        SELECT
            a.EmpName, 
            b.DeptName, 
            c.BagianName, 
            d.ShiftKode, 
            a.UpdDate, 
            a.UpdUser, 
            a.EmpId
        FROM
            dbo.SMEmployeeInspector AS a
            LEFT JOIN
            dbo.SMDeptWInspector AS b
            ON 
                a.DeptId = b.DeptId
            LEFT JOIN
            dbo.SMBagianWInspector AS c
            ON 
                a.BagianId = c.BagianId
            LEFT JOIN
            dbo.SMShiftWInspector AS d
            ON 
                a.ShiftId = d.ShiftId
    ";

    $stmt = sqlsrv_query($conn, $sql);

    $data = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
    }

    return $data;
}
// ===================================================
// 8. MENDAPATKAN DATA
// ===================================================
$emp = getAllEmp($conn);
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';

?>
<!-- ===================================================
    9. HTML: STRUKTUR HALAMAN
======================================================= -->
<div class="content-wrapper">

    <!-- Page Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">

                <div class="col-sm-6">
                    <h1>Employee Weaving</h1>
                </div>

                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Employee Weaving</li>
                    </ol>
                </div>

            </div>
        </div>
    </section>

      <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <div class="card">
                
                <!-- Card Header -->
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i>List Employee Weaving</h3>

                    <?php if ($perm['CanAdd'] == 1): ?>
                        <a href="add_employee_inspecting_weaving.php"
                           class="btn btn-success btn-sm float-right">
                            <i class="fas fa-user-plus"></i> Add Employee Weaving
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <table id="empTable" class="table table-hover table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Karyawan</th>
                                <th>Departemen</th>
                                <th>Bagian</th>
                                <th>Shift</th>
                                <th>Update Date</th>
                                <th>Updated By</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($emp)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No data</td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($emp as $i => $u): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>

                                    <td><?= htmlspecialchars($u['EmpName']) ?></td>
                                    <td><?= htmlspecialchars($u['DeptName']) ?></td>
                                    <td><?= htmlspecialchars($u['BagianName']) ?></td>
                                    <td><?= htmlspecialchars($u['ShiftKode']) ?></td>
                                  
                                    <td>
                                        <?= $u['UpdDate']
                                            ? $u['UpdDate']->format('Y-m-d H:i:s')
                                            : 'N/A'
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($u['UpdUser']) ?></td>
                                    <td>
                                        <!-- EDIT -->
                                        <?php if ($perm['CanEdit'] == 1): ?>
                                            <a class="btn btn-warning btn-sm"
                                               href="edit_employee_inspecting_weaving.php?id=<?= (int)$u['EmpId'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- DELETE -->
                                        <?php if ($perm['CanDelete'] == 1): ?>
                                            <button class="btn btn-danger btn-sm btn-delete"
                                                    data-id="<?= (int)$u['EmpId'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php endif; ?>
                        </tbody>

                    </table>
                </div>


            </div>

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

<!-- ===================================================
    12. JAVASCRIPT CUSTOM
======================================================= -->
<!-- NOTIFIKASI SUKSES/ERROR -->
<script>
$(function() {
    <?php if ($successMessage): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses',
            text: <?= json_encode($successMessage) ?>,
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: <?= json_encode($errorMessage) ?>,
            timer: 2500,
            showConfirmButton: false
        });
    <?php endif; ?>
});
</script>

<script>
$(function() {
    // Inisialisasi DataTables dengan responsive dan autoWidth false
    $("#empTable").DataTable({
        responsive: true,
        autoWidth: false
    });

    // Tombol Delete
    $(document).on("click", ".btn-delete", function () {
        let id = $(this).data("id");

        Swal.fire({
            title: "Delete this Employee?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, Delete",
            cancelButtonText: "Cancel"
        }).then((res) => {
            if (res.isConfirmed) {
                // Kirim request POST ke delete_employee_inspecting_weaving.php dengan data id
               $.post("delete_employee_inspecting_weaving.php", { id: id }, function (response) {
                    let res = {};

                    try {
                        res = JSON.parse(response);
                    } catch(e) {
                        Swal.fire("Error", "Invalid server response!", "error");
                        return;
                    }

                    if (res.status === "success") {
                        Swal.fire("Deleted!", res.message, "success");
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        Swal.fire("Error", res.message, "error");
                    }
                });
            }
        });
    });
    
});
</script>


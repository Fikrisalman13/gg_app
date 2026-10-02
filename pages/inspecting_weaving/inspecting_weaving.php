<?php
// inspect_weaving.php (perbaikan)
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================
session_start();
date_default_timezone_set('Asia/Jakarta');
ob_start();

// ===================================================
// 2. KONEKSI DATABASE + VALIDASI SESSION
// ===================================================
include '../../koneksi.php';

// Jika user belum login -> redirect ke login (path mengikuti project Anda)
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Pastikan koneksi tersedia
if (!$conn) {
    $errors = function_exists('sqlsrv_errors') ? sqlsrv_errors() : null;
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 3. IMPORT DEPENDENSI LAYOUT & PERMISSIONS (opsional)
// ===================================================
// Jika Anda punya file menu_constants.php / permissions.php gunakan seperti di kode referensi.
// Jika tidak ada, baris ini bisa di-comment / dihapus.
@include_once '../../includes/menu_constants.php';
@include_once '../../includes/permissions.php';

// ===================================================
// 4. PENANGANAN NOTIFIKASI
// ===================================================
$successMessage = $_SESSION['success'] ?? null;
$errorMessage   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// ===================================================
// 5. VALIDASI IZIN AKSES (gunakan helper jika ada; fallback query manual)
// ===================================================
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Jika helper requireView / userPermissions tersedia, gunakan
$perm = [];
if (function_exists('requireView') && defined('MENU_INSPECT_HEADER')) {
    // requireView akan melakukan redirect/throw jika tidak punya view rights
    try {
        requireView($conn, MENU_INSPECT_HEADER);
        $perm = userPermissions($conn, MENU_INSPECT_HEADER);
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    // fallback: query manual berdasarkan GroupId dan MenuId (sesuaikan $menuId jika perlu)
    $groupId = $_SESSION['GroupId'] ?? 0;
    $menuId  = 20; // sesuaikan sesuai DB Anda
    $sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            // normalisasi: buat key exist sebagai integer 0/1
            $perm = [
                'CanView'   => (isset($row['CanView']) ? (int)$row['CanView'] : 0),
                'CanAdd'    => (isset($row['CanAdd'])  ? (int)$row['CanAdd']  : 0),
                'CanEdit'   => (isset($row['CanEdit']) ? (int)$row['CanEdit'] : 0),
                'CanDelete' => (isset($row['CanDelete']) ? (int)$row['CanDelete'] : 0),
            ];
        } else {
            $perm = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
        }
        sqlsrv_free_stmt($stmt);
    } else {
        // query error -> log dan set permission nol (aman)
        error_log("Gagal ambil permission: " . print_r(sqlsrv_errors(), true));
        $perm = ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
    }

    if ($perm['CanView'] == 0) {
        $errorMessage = "Anda tidak memiliki hak untuk melihat halaman ini.";
    }
}

// ===================================================
// 6. IMPORT LAYOUT HEADER & SIDEBAR (setelah cek session)
// ===================================================
include '../../includes/header.php';
include '../../includes/sidebar.php';

ob_end_flush();
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Inspect Weaving</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Inspect Weaving</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                        <h3 class="card-title">Daftar Inspect Weaving</h3>

                        <?php if (!empty($perm['CanAdd'])): ?>
                            <a href="add_inspecting_weaving.php" class="btn btn-success btn-sm float-right">
                                <i class="fas fa-plus"></i> Add Detail Inspecting Weaving
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="inspectingTable" class="table table-hover table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>No</th>
                                        <th>No CP</th>
                                        <th>Date</th>
                                        <th>Inspektor</th>
                                        <th>Artikel</th>
                                        <th>Lot</th>
                                        <th>UpdUser</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<?php include '../../includes/footer.php'; ?>

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
    // instance table global supaya bisa di-reload tanpa reset page
    var table = $('#inspectingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "ajax_inspecting_weaving.php",
        columns: [
            { data: "no", className: 'text-center' },
            { data: "NoCP", className: 'text-center' },
            { data: "InspectDate", className: 'text-center' },
            { data: "EmpName", className: 'text-center' },
            { data: "ArtikelKode", className: 'text-center' },
            { data: "Lot", className: 'text-center' },
            { data: "UpdUser", className: 'text-center' },
            { data: "aksi", className: 'text-center', orderable: false }
        ],
        order: [[1,'desc']],
        lengthMenu: [10,25,50],
        responsive: true,
        deferRender: true
    });

    // Notifikasi sukses
    <?php if (!empty($successMessage)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Sukses!',
            text: "<?= addslashes($successMessage) ?>",
            timer: 3000,
            showConfirmButton: false
        });
    <?php endif; ?>

    // Notifikasi error (session)
    <?php if (!empty($errorMessage) && empty($perm)): /* jika errorMessage sudah ditampilkan di atas, ini safe */ ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "<?= addslashes($errorMessage) ?>",
            timer: 3000,
            showConfirmButton: false
        });
    <?php endif; ?>

    // Hapus data - delegated event
    $(document).on('click', '.btn-delete', function () {
        let noCP = $(this).data('id');

        if (!noCP) {
            Swal.fire({ icon: 'error', title: 'Error!', text: 'ID tidak valid!' });
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
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'delete_inspecting_weaving.php',
                method: 'POST',
                data: { id: noCP },
                dataType: 'json',
                timeout: 15000
            })
            .done(function (res) {
                if (res && res.status && res.status.toLowerCase() === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message || 'Data berhasil dihapus.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function() {
                        if (table && table.ajax) {
                            table.ajax.reload(null, false);
                        } else {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: (res && res.message) ? res.message : 'Server mengembalikan error.' });
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                var raw = jqXHR.responseText ? jqXHR.responseText.trim() : '';
                if (raw) {
                    try {
                        var parsed = JSON.parse(raw);
                        if (parsed && parsed.status && parsed.status.toLowerCase() === 'success') {
                            Swal.fire('Berhasil', parsed.message || 'Data berhasil dihapus.', 'success')
                                .then(function(){ if (table && table.ajax) table.ajax.reload(null,false); else location.reload(); });
                            return;
                        } else {
                            Swal.fire('Gagal', (parsed && parsed.message) ? parsed.message : 'Server mengembalikan error.', 'error');
                            return;
                        }
                    } catch (e) {
                        console.error('Gagal parsing fallback response:', e, raw);
                    }
                }
                Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Respon tidak valid dari server atau koneksi gagal (' + textStatus + ')' });
            });
        });
    });
});
</script>

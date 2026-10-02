<?php
// delete_usage.php - Hapus Data Pemakaian Padder
session_start();
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ===== Auth & Permission =====
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$groupId = $_SESSION['GroupId'];
$menuId = 115;

$sqlPerm = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$perm = sqlsrv_query($conn, $sqlPerm, [$groupId, $menuId]);
$canDelete = ($perm && $row = sqlsrv_fetch_array($perm, SQLSRV_FETCH_ASSOC)) ? $row['CanDelete'] : 0;
if ($canDelete != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data pemakaian.";
    header('Location: usage.php');
    exit;
}

// Ambil usage_id
$usageId = $_GET['id'] ?? '';
if (!$usageId) {
    $_SESSION['error'] = "Usage ID tidak valid!";
    header('Location: usage.php');
    exit;
}

// Ambil data pemakaian + padder
$sql = "SELECT u.*, p.padder_id, p.padder_name, p.status as padder_status
        FROM dbo.pad_t_usage u
        INNER JOIN dbo.pad_m_padder p ON u.padder_id = p.padder_id
        WHERE u.id = ?";
$usage = sqlsrv_query($conn, $sql, [$usageId]);
$usage = sqlsrv_fetch_array($usage, SQLSRV_FETCH_ASSOC);

if (!$usage) {
    $_SESSION['error'] = "Data pemakaian tidak ditemukan!";
    header('Location: usage.php');
    exit;
}

// Format tanggal
if ($usage['used_date'] instanceof DateTime) {
    $usage['used_date_formatted'] = $usage['used_date']->format('d/m/Y');
} else {
    $usage['used_date_formatted'] = $usage['used_date'];
}

// Proses hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirmation'] ?? '') !== 'HAPUS') {
        $_SESSION['error'] = "Konfirmasi tidak valid! Ketik HAPUS.";
        header('Location: delete_usage.php?id=' . $usageId);
        exit;
    }

    try {
        sqlsrv_begin_transaction($conn);

        // Hapus data pemakaian
        sqlsrv_query($conn, "DELETE FROM dbo.pad_t_usage WHERE id = ?", [$usageId]);

        // Update status padder kembali ke READY (karena pemakaian dihapus)
        sqlsrv_query($conn, "UPDATE dbo.pad_m_padder SET status='READY' WHERE padder_id = ?", [$usage['padder_id']]);

        // Tambah log untuk menandai penghapusan pemakaian
        $logRemarks = "Pemakaian dihapus: Mesin " . $usage['machine_name'] . ", Lokasi " . $usage['location'] . " - " . ($usage['remarks'] ?? '');
        sqlsrv_query($conn, 
            "INSERT INTO dbo.pad_status_log (padder_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)", 
            [$usage['padder_id'], 'READY', $_SESSION['UserName'], $logRemarks]
        );

        sqlsrv_commit($conn);

        $_SESSION['success'] = "Data pemakaian berhasil dihapus! Padder dikembalikan ke status READY.";
        header('Location: usage.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: delete_usage.php?id=' . $usageId);
        exit;
    }
}

// Include layout
include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Pemakaian - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        .info-card {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark text-danger">
                            <i class="fas fa-trash-alt"></i> Hapus Data Pemakaian
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="usage.php">Daftar Pemakaian</a></li>
                            <li class="breadcrumb-item active">Hapus Pemakaian</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Peringatan:</strong> Data pemakaian akan dihapus permanen dan tidak dapat dikembalikan!
                </div>

                <form method="POST" id="deleteForm">
                    <div class="card card-danger info-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-trash mr-2"></i>
                                Konfirmasi Penghapusan
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Informasi Pemakaian</h5>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th width="40%">Padder</th>
                                            <td>
                                                <strong class="text-primary">
                                                    <?= htmlspecialchars($usage['padder_id'] . ' - ' . $usage['padder_name']) ?>
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Pasang</th>
                                            <td>
                                                <?= htmlspecialchars($usage['used_date_formatted']) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Lokasi</th>
                                            <td>
                                                <?= htmlspecialchars($usage['location']) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Mesin</th>
                                            <td>
                                                <?= htmlspecialchars($usage['machine_name']) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status Padder</th>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $usage['padder_status'] == 'READY' ? 'success' : 
                                                    ($usage['padder_status'] == 'IN_USE' ? 'primary' : 
                                                    ($usage['padder_status'] == 'MAINTENANCE' ? 'warning' : 
                                                    ($usage['padder_status'] == 'REPAIRED' ? 'info' : 
                                                    ($usage['padder_status'] == 'SCRAP' ? 'danger' : 'secondary'))))
                                                ?>">
                                                    <?= $usage['padder_status'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Keterangan</th>
                                            <td><?= htmlspecialchars($usage['remarks'] ?? '-') ?></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5>Dampak Penghapusan</h5>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Perubahan yang akan terjadi:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Data pemakaian akan dihapus permanen</li>
                                            <li>Status padder akan dikembalikan ke <strong>READY</strong></li>
                                            <li>Riwayat penghapusan akan dicatat dalam log sistem</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-4">
                                <label for="confirmation" class="font-weight-bold">
                                    Ketik <span class="text-danger">HAPUS</span> untuk mengonfirmasi penghapusan:
                                </label>
                                <input type="text" name="confirmation" id="confirmation" 
                                       class="form-control form-control-lg" 
                                       placeholder="Ketik HAPUS di sini..." 
                                       required 
                                       style="font-weight: bold; letter-spacing: 1px;">
                                <small class="form-text text-muted">
                                    Tindakan ini tidak dapat dibatalkan. Pastikan Anda yakin ingin menghapus data pemakaian ini.
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger btn-lg" id="deleteButton" disabled>
                                <i class="fas fa-trash-alt mr-2"></i> HAPUS PERMANEN
                            </button>
                            <a href="usage.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times mr-2"></i> BATAL
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
    // Enable delete button only when confirmation text matches
    $('#confirmation').on('input', function(){
        const isConfirmed = $(this).val().toUpperCase() === 'HAPUS';
        $('#deleteButton').prop('disabled', !isConfirmed);
        
        // Visual feedback
        if (isConfirmed) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
        }
    });

    // Form submission with confirmation
    $('#deleteForm').on('submit', function(e){
        e.preventDefault();
        
        Swal.fire({
            title: 'Hapus Data Pemakaian?',
            html: `<div class="text-left">
                    <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
                    <ul class="text-left">
                        <li>Data pemakaian akan dihapus permanen</li>
                        <li>Status padder akan dikembalikan ke READY</li>
                    </ul>
                   </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt mr-2"></i> YA, HAPUS',
            cancelButtonText: '<i class="fas fa-times mr-2"></i> BATAL',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                $('#deleteButton').html('<i class="fas fa-spinner fa-spin mr-2"></i> Menghapus...').prop('disabled', true);
                
                // Submit form
                $('#deleteForm').off('submit').submit();
            }
        });
    });

    // Focus on confirmation input
    $(document).ready(function() {
        $('#confirmation').focus();
    });

})();
</script>

</body>
</html>

<?php 
include '../../includes/footer.php'; 
?>
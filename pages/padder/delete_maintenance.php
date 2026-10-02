<?php
// delete_maintenance.php - Hapus Data Maintenance Padder
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
$menuId = 116;

$sqlPerm = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$perm = sqlsrv_query($conn, $sqlPerm, [$groupId, $menuId]);
$canDelete = ($perm && $row = sqlsrv_fetch_array($perm, SQLSRV_FETCH_ASSOC)) ? $row['CanDelete'] : 0;
if ($canDelete != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data maintenance.";
    header('Location: maintenance.php');
    exit;
}

// Ambil maintenance_id
$maintenanceId = $_GET['id'] ?? '';
if (!$maintenanceId) {
    $_SESSION['error'] = "Maintenance ID tidak valid!";
    header('Location: maintenance.php');
    exit;
}

// Ambil data maintenance + padder
$sql = "SELECT m.*, p.padder_id, p.padder_name, p.status as padder_status
        FROM dbo.pad_t_maintenance m
        INNER JOIN dbo.pad_m_padder p ON m.padder_id = p.padder_id
        WHERE m.id = ?";
$maintenance = sqlsrv_query($conn, $sql, [$maintenanceId]);
$maintenance = sqlsrv_fetch_array($maintenance, SQLSRV_FETCH_ASSOC);

if (!$maintenance) {
    $_SESSION['error'] = "Data maintenance tidak ditemukan!";
    header('Location: maintenance.php');
    exit;
}

// Format tanggal
if ($maintenance['maintenance_date'] instanceof DateTime) {
    $maintenance['maintenance_date_formatted'] = $maintenance['maintenance_date']->format('d/m/Y');
} else {
    $maintenance['maintenance_date_formatted'] = $maintenance['maintenance_date'];
}

// Proses hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirmation'] ?? '') !== 'HAPUS') {
        $_SESSION['error'] = "Konfirmasi tidak valid! Ketik HAPUS.";
        header('Location: delete_maintenance.php?id=' . $maintenanceId);
        exit;
    }

    try {
        sqlsrv_begin_transaction($conn);

        // Hapus data maintenance
        sqlsrv_query($conn, "DELETE FROM dbo.pad_t_maintenance WHERE id = ?", [$maintenanceId]);

        // Update status padder kembali ke IN_USE (karena maintenance dihapus)
        // Hanya jika padder masih dalam status MAINTENANCE
        if ($maintenance['padder_status'] == 'MAINTENANCE') {
            sqlsrv_query($conn, "UPDATE dbo.pad_m_padder SET status='IN_USE' WHERE padder_id = ?", [$maintenance['padder_id']]);
        }

        // Tambah log untuk menandai penghapusan maintenance
        $logRemarks = "Maintenance dihapus: " . substr($maintenance['work_done'], 0, 100) . 
                     (strlen($maintenance['work_done']) > 100 ? '...' : '');
        
        if (!empty($maintenance['hardness_check'])) {
            $logRemarks .= " | Hardness: " . $maintenance['hardness_check'];
        }
        
        sqlsrv_query($conn, 
            "INSERT INTO dbo.pad_status_log (padder_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)", 
            [
                $maintenance['padder_id'], 
                ($maintenance['padder_status'] == 'MAINTENANCE') ? 'IN_USE' : $maintenance['padder_status'],
                $_SESSION['UserName'], 
                $logRemarks
            ]
        );

        sqlsrv_commit($conn);

        $_SESSION['success'] = "Data maintenance berhasil dihapus!" . 
                              ($maintenance['padder_status'] == 'MAINTENANCE' ? " Padder dikembalikan ke status IN_USE." : "");
        header('Location: maintenance.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: delete_maintenance.php?id=' . $maintenanceId);
        exit;
    }
}

// Include layout
include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'warning';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Maintenance - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        .info-card {
            border-left: 4px solid #dc3545;
        }
        .hardness-value {
            font-weight: bold;
            color: #28a745;
        }
        .work-done-preview {
            max-height: 100px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-size: 0.9em;
            border: 1px solid #dee2e6;
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
                            <i class="fas fa-trash-alt"></i> Hapus Data Maintenance
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="maintenance.php">Daftar Maintenance</a></li>
                            <li class="breadcrumb-item active">Hapus Maintenance</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Peringatan:</strong> Data maintenance akan dihapus permanen dan tidak dapat dikembalikan!
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
                                    <h5>Informasi Maintenance</h5>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th width="40%">Padder</th>
                                            <td>
                                                <strong class="text-warning">
                                                    <?= htmlspecialchars($maintenance['padder_id'] . ' - ' . $maintenance['padder_name']) ?>
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Maintenance</th>
                                            <td>
                                                <?= htmlspecialchars($maintenance['maintenance_date_formatted']) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Hardness Check</th>
                                            <td>
                                                <?php if (!empty($maintenance['hardness_check'])): ?>
                                                    <span class="hardness-value"><?= htmlspecialchars($maintenance['hardness_check']) ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status Padder</th>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $maintenance['padder_status'] == 'READY' ? 'success' : 
                                                    ($maintenance['padder_status'] == 'IN_USE' ? 'primary' : 
                                                    ($maintenance['padder_status'] == 'MAINTENANCE' ? 'warning' : 
                                                    ($maintenance['padder_status'] == 'REPAIRED' ? 'info' : 
                                                    ($maintenance['padder_status'] == 'SCRAP' ? 'danger' : 'secondary'))))
                                                ?>">
                                                    <?= $maintenance['padder_status'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5>Pekerjaan Dilakukan</h5>
                                    <div class="work-done-preview">
                                        <?= nl2br(htmlspecialchars($maintenance['work_done'])) ?>
                                    </div>
                                    
                                    <?php if (!empty($maintenance['notes'])): ?>
                                    <h6 class="mt-3">Catatan:</h6>
                                    <div class="work-done-preview">
                                        <?= nl2br(htmlspecialchars($maintenance['notes'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <h5>Dampak Penghapusan</h5>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Perubahan yang akan terjadi:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Data maintenance akan dihapus permanen</li>
                                            <?php if ($maintenance['padder_status'] == 'MAINTENANCE'): ?>
                                            <li>Status padder akan dikembalikan ke <strong>IN_USE</strong></li>
                                            <?php else: ?>
                                            <li>Status padder tetap <strong><?= $maintenance['padder_status'] ?></strong></li>
                                            <?php endif; ?>
                                            <li>Riwayat penghapusan akan dicatat dalam log sistem</li>
                                            <li>Data tidak dapat dipulihkan kembali</li>
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
                                    Tindakan ini tidak dapat dibatalkan. Pastikan Anda yakin ingin menghapus data maintenance ini.
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger btn-lg" id="deleteButton" disabled>
                                <i class="fas fa-trash-alt mr-2"></i> HAPUS PERMANEN
                            </button>
                            <a href="maintenance.php" class="btn btn-secondary btn-lg">
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
        
        const padderStatus = '<?= $maintenance['padder_status'] ?>';
        const statusChange = padderStatus === 'MAINTENANCE' ? 
            'Status padder akan dikembalikan ke IN_USE' : 
            'Status padder tetap ' + padderStatus;
        
        Swal.fire({
            title: 'Hapus Data Maintenance?',
            html: `<div class="text-left">
                    <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
                    <ul class="text-left">
                        <li>Data maintenance akan dihapus permanen</li>
                        <li>${statusChange}</li>
                        <li>Riwayat penghapusan akan dicatat dalam log</li>
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
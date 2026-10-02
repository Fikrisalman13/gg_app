<?php
// delete_repair.php - Hapus Data Perbaikan Padder
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
$menuId = 117;

$sqlPerm = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$perm = sqlsrv_query($conn, $sqlPerm, [$groupId, $menuId]);
$canDelete = ($perm && $row = sqlsrv_fetch_array($perm, SQLSRV_FETCH_ASSOC)) ? $row['CanDelete'] : 0;
if ($canDelete != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data perbaikan.";
    header('Location: repair.php');
    exit;
}

// Ambil repair_id
$repairId = $_GET['id'] ?? '';
if (!$repairId) {
    $_SESSION['error'] = "Repair ID tidak valid!";
    header('Location: repair.php');
    exit;
}

// Ambil data repair + padder
$sql = "SELECT r.*, p.padder_id, p.padder_name, p.status as padder_status, v.vendor_name
        FROM dbo.pad_t_repair r
        INNER JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id
        LEFT JOIN dbo.pad_m_vendor v ON r.vendor_id = v.vendor_id
        WHERE r.id = ?";
$repair = sqlsrv_query($conn, $sql, [$repairId]);
$repair = sqlsrv_fetch_array($repair, SQLSRV_FETCH_ASSOC);

if (!$repair) {
    $_SESSION['error'] = "Data perbaikan tidak ditemukan!";
    header('Location: repair.php');
    exit;
}

// Format tanggal
if ($repair['send_date'] instanceof DateTime) {
    $repair['send_date_formatted'] = $repair['send_date']->format('d/m/Y');
} else {
    $repair['send_date_formatted'] = $repair['send_date'];
}

// Proses hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirmation'] ?? '') !== 'HAPUS') {
        $_SESSION['error'] = "Konfirmasi tidak valid! Ketik HAPUS.";
        header('Location: delete_repair.php?id=' . $repairId);
        exit;
    }

    try {
        sqlsrv_begin_transaction($conn);

        // 1. Hapus file foto terkait
        $deleteFilesSql = "DELETE FROM dbo.pad_t_repair_files WHERE repair_id = ?";
        sqlsrv_query($conn, $deleteFilesSql, [$repairId]);

        // 2. Hapus data perbaikan
        sqlsrv_query($conn, "DELETE FROM dbo.pad_t_repair WHERE id = ?", [$repairId]);

        // 3. Update status padder kembali ke status sebelumnya
        // Jika status saat ini adalah REPAIR_VENDOR, kembalikan ke IN_USE
        if ($repair['padder_status'] == 'REPAIR_VENDOR') {
            $updatePadderSql = "UPDATE dbo.pad_m_padder SET status='IN_USE' WHERE padder_id = ?";
            sqlsrv_query($conn, $updatePadderSql, [$repair['padder_id']]);
        }

        // 4. Tambah log untuk menandai penghapusan perbaikan
        $logRemarks = "Perbaikan dihapus: Vendor " . ($repair['vendor_name'] ?? '-') . 
                     " | No. SJ: " . ($repair['sj_number'] ?? '-') . 
                     " | Catatan: " . ($repair['repair_notes'] ?? '');
        
        $logSql = "INSERT INTO dbo.pad_status_log (padder_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)";
        
        $logStatus = ($repair['padder_status'] == 'REPAIR_VENDOR') ? 'IN_USE' : $repair['padder_status'];
        sqlsrv_query($conn, $logSql, [$repair['padder_id'], $logStatus, $_SESSION['UserName'], $logRemarks]);

        sqlsrv_commit($conn);

        $_SESSION['success'] = "Data perbaikan berhasil dihapus!" . 
                              ($repair['padder_status'] == 'REPAIR_VENDOR' ? " Padder dikembalikan ke status IN_USE." : "");
        header('Location: repair.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: delete_repair.php?id=' . $repairId);
        exit;
    }
}

// Include layout
include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'info';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Perbaikan - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        .info-card {
            border-left: 4px solid #dc3545;
        }
        .photo-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin: 2px;
            border: 1px solid #dee2e6;
        }
        .photo-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
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
                            <i class="fas fa-trash-alt"></i> Hapus Data Perbaikan
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="repair.php">Daftar Perbaikan</a></li>
                            <li class="breadcrumb-item active">Hapus Perbaikan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Peringatan:</strong> Data perbaikan akan dihapus permanen dan tidak dapat dikembalikan!
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
                                    <h5>Informasi Perbaikan</h5>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th width="40%">Padder</th>
                                            <td>
                                                <strong class="text-info">
                                                    <?= htmlspecialchars($repair['padder_id'] . ' - ' . $repair['padder_name']) ?>
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Kirim</th>
                                            <td>
                                                <?= htmlspecialchars($repair['send_date_formatted']) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Vendor</th>
                                            <td>
                                                <?= htmlspecialchars($repair['vendor_name'] ?? '-') ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>No. Surat Jalan</th>
                                            <td>
                                                <?= htmlspecialchars($repair['sj_number'] ?? '-') ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status Perbaikan</th>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $repair['status'] == 'ON REPAIR' ? 'warning' : 
                                                    ($repair['status'] == 'COMPLETED' ? 'success' : 
                                                    ($repair['status'] == 'CANCELLED' ? 'danger' : 'secondary'))
                                                ?>">
                                                    <?= $repair['status'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status Padder</th>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $repair['padder_status'] == 'READY' ? 'success' : 
                                                    ($repair['padder_status'] == 'IN_USE' ? 'primary' : 
                                                    ($repair['padder_status'] == 'MAINTENANCE' ? 'warning' : 
                                                    ($repair['padder_status'] == 'REPAIR_VENDOR' ? 'info' : 
                                                    ($repair['padder_status'] == 'REPAIRED' ? 'info' : 
                                                    ($repair['padder_status'] == 'SCRAP' ? 'danger' : 'secondary')))))
                                                ?>">
                                                    <?= $repair['padder_status'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5>Dampak Penghapusan</h5>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Perubahan yang akan terjadi:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Data perbaikan akan dihapus permanen</li>
                                            <li>Semua foto terkait akan dihapus</li>
                                            <?php if ($repair['padder_status'] == 'REPAIR_VENDOR'): ?>
                                            <li>Status padder akan dikembalikan ke <strong>IN_USE</strong></li>
                                            <?php else: ?>
                                            <li>Status padder tetap <strong><?= $repair['padder_status'] ?></strong></li>
                                            <?php endif; ?>
                                            <li>Riwayat penghapusan akan dicatat dalam log sistem</li>
                                            <li>Data tidak dapat dipulihkan kembali</li>
                                        </ul>
                                    </div>

                                    <?php
                                    // Get photos for display
                                    $photoSql = "SELECT file_path, file_category FROM dbo.pad_t_repair_files WHERE repair_id = ?";
                                    $photoStmt = sqlsrv_query($conn, $photoSql, [$repairId]);
                                    $photos = [];
                                    if ($photoStmt !== false) {
                                        while ($photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
                                            $photos[] = $photo;
                                        }
                                        sqlsrv_free_stmt($photoStmt);
                                    }
                                    
                                    if (!empty($photos)): ?>
                                    <h6>Foto yang akan dihapus:</h6>
                                    <div class="photo-gallery">
                                        <?php foreach ($photos as $photo): ?>
                                            <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                                 alt="<?= htmlspecialchars($photo['file_category']) ?>" 
                                                 class="photo-thumbnail"
                                                 title="<?= htmlspecialchars($photo['file_category']) ?>">
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($repair['repair_notes'])): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Catatan Perbaikan:</h6>
                                    <div class="bg-light p-3 rounded">
                                        <?= nl2br(htmlspecialchars($repair['repair_notes'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

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
                                    Tindakan ini tidak dapat dibatalkan. Pastikan Anda yakin ingin menghapus data perbaikan ini.
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger btn-lg" id="deleteButton" disabled>
                                <i class="fas fa-trash-alt mr-2"></i> HAPUS PERMANEN
                            </button>
                            <a href="repair.php" class="btn btn-secondary btn-lg">
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
        
        const padderStatus = '<?= $repair['padder_status'] ?>';
        const statusChange = padderStatus === 'REPAIR_VENDOR' ? 
            'Status padder akan dikembalikan ke IN_USE' : 
            'Status padder tetap ' + padderStatus;
        
        Swal.fire({
            title: 'Hapus Data Perbaikan?',
            html: `<div class="text-left">
                    <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
                    <ul class="text-left">
                        <li>Data perbaikan akan dihapus permanen</li>
                        <li>Semua foto terkait akan dihapus</li>
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
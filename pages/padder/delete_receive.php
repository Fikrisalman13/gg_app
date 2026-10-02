<?php
// delete_receive.php - Hapus Penerimaan Padder Beserta Foto
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
$menuId = 114;

$sqlPerm = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$perm = sqlsrv_query($conn, $sqlPerm, [$groupId, $menuId]);
$canDelete = ($perm && $row = sqlsrv_fetch_array($perm, SQLSRV_FETCH_ASSOC)) ? $row['CanDelete'] : 0;
if ($canDelete != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data penerimaan.";
    header('Location: receive_list.php');
    exit;
}

// Ambil receive_id
$receiveId = $_GET['id'] ?? '';
if (!$receiveId) {
    $_SESSION['error'] = "Receive ID tidak valid!";
    header('Location: receive_list.php');
    exit;
}

// Ambil data penerimaan + padder
$sql = "SELECT r.*, p.padder_id, p.padder_name, p.status as padder_status
        FROM pad_t_receive r
        LEFT JOIN pad_m_padder p ON r.padder_id = p.padder_id
        WHERE r.id = ?";
$receive = sqlsrv_query($conn, $sql, [$receiveId]);
$receive = sqlsrv_fetch_array($receive, SQLSRV_FETCH_ASSOC);

if (!$receive) {
    $_SESSION['error'] = "Data penerimaan tidak ditemukan!";
    header('Location: receive_list.php');
    exit;
}

// Hitung jumlah foto terkait
$sqlPhotos = "SELECT COUNT(*) as count, file_path FROM pad_t_receive_files WHERE receive_id = ? GROUP BY file_path";
$photos = sqlsrv_query($conn, $sqlPhotos, [$receiveId]);
$photoCount = 0;
$photoPaths = [];

if ($photos !== false) {
    while ($row = sqlsrv_fetch_array($photos, SQLSRV_FETCH_ASSOC)) {
        $photoCount = $row['count'];
        $photoPaths[] = $row['file_path'];
    }
}

// Cek apakah ada purchase order terkait (untuk informasi)
$sqlPurchase = "SELECT COUNT(*) as count FROM pad_t_purchase WHERE padder_id = ?";
$purchase = sqlsrv_query($conn, $sqlPurchase, [$receive['padder_id']]);
$purchaseCount = ($purchase && $row = sqlsrv_fetch_array($purchase, SQLSRV_FETCH_ASSOC)) ? $row['count'] : 0;

// Proses hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirmation'] ?? '') !== 'HAPUS') {
        $_SESSION['error'] = "Konfirmasi tidak valid! Ketik HAPUS.";
        header('Location: delete_receive.php?id=' . $receiveId);
        exit;
    }

    try {
        sqlsrv_begin_transaction($conn);

        // Hapus file fisik foto
        if ($photoCount > 0) {
            foreach ($photoPaths as $filePath) {
                $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        }

        // Hapus data foto dari database
        sqlsrv_query($conn, "DELETE FROM pad_t_receive_files WHERE receive_id = ?", [$receiveId]);

        // Hapus data penerimaan
        sqlsrv_query($conn, "DELETE FROM pad_t_receive WHERE id = ?", [$receiveId]);

        // Update status padder kembali ke DIBELI (karena penerimaan dihapus)
        sqlsrv_query($conn, "UPDATE pad_m_padder SET status='DIBELI' WHERE padder_id = ?", [$receive['padder_id']]);

        // Hapus log status READY untuk padder ini
        sqlsrv_query($conn, "DELETE FROM pad_status_log WHERE padder_id = ? AND status='READY'", [$receive['padder_id']]);

        // Tambah log untuk menandai penghapusan penerimaan
        $logRemarks = "Penerimaan dihapus: " . ($receive['grn_number'] ? "GRN {$receive['grn_number']}" : "Tanpa GRN") . " - " . ($receive['remarks'] ?? '');
        sqlsrv_query($conn, 
            "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)", 
            [$receive['padder_id'], 'DIBELI', $_SESSION['UserName'], $logRemarks]
        );

        sqlsrv_commit($conn);

        $_SESSION['success'] = "Data penerimaan berhasil dihapus! Padder dikembalikan ke status DIBELI.";
        header('Location: receive_list.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: delete_receive.php?id=' . $receiveId);
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
    <title>Hapus Penerimaan - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        .photo-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .photo-preview-item {
            position: relative;
            width: 80px;
            height: 80px;
        }
        .photo-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
            border: 2px solid #dc3545;
        }
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
                            <i class="fas fa-trash-alt"></i> Hapus Data Penerimaan
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="receive_list.php">Daftar Penerimaan</a></li>
                            <li class="breadcrumb-item active">Hapus Penerimaan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Peringatan:</strong> Data penerimaan akan dihapus permanen dan tidak dapat dikembalikan!
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
                                    <h5>Informasi Penerimaan</h5>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <th width="40%">Padder</th>
                                            <td>
                                                <strong class="text-primary">
                                                    <?= htmlspecialchars($receive['padder_id'] . ' - ' . $receive['padder_name']) ?>
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Penerimaan</th>
                                            <td>
                                                <?= $receive['receive_date'] ? $receive['receive_date']->format('d/m/Y') : '-' ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>GRN Number</th>
                                            <td>
                                                <?= htmlspecialchars($receive['grn_number'] ?? '-') ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status Padder</th>
                                            <td>
                                                <span class="badge badge-<?= 
                                                    $receive['padder_status'] == 'READY' ? 'success' : 
                                                    ($receive['padder_status'] == 'DIBELI' ? 'warning' : 'secondary')
                                                ?>">
                                                    <?= $receive['padder_status'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Keterangan</th>
                                            <td><?= htmlspecialchars($receive['remarks'] ?? '-') ?></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5>Dampak Penghapusan</h5>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Perubahan yang akan terjadi:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Data penerimaan akan dihapus permanen</li>
                                            <?php if ($photoCount > 0): ?>
                                                <li><?= $photoCount ?> foto akan dihapus</li>
                                            <?php endif; ?>
                                            <li>Status padder akan dikembalikan ke <strong>DIBELI</strong></li>
                                            <li>Riwayat status <strong>READY</strong> akan dihapus</li>
                                        </ul>
                                    </div>

                                    <?php if ($photoCount > 0): ?>
                                        <div class="mt-3">
                                            <strong>Foto yang akan dihapus:</strong>
                                            <div class="photo-preview mt-2">
                                                <?php 
                                                // Ambil beberapa foto untuk preview
                                                $previewPhotos = sqlsrv_query($conn, 
                                                    "SELECT file_path FROM pad_t_receive_files WHERE receive_id = ? LIMIT 6", 
                                                    [$receiveId]
                                                );
                                                $previewCount = 0;
                                                if ($previewPhotos !== false) {
                                                    while ($photo = sqlsrv_fetch_array($previewPhotos, SQLSRV_FETCH_ASSOC) && $previewCount < 6) {
                                                        echo '<div class="photo-preview-item">';
                                                        echo '<img src="' . htmlspecialchars($photo['file_path']) . '" alt="Photo">';
                                                        echo '</div>';
                                                        $previewCount++;
                                                    }
                                                }
                                                if ($photoCount > 6) {
                                                    echo '<div class="photo-preview-item bg-light d-flex align-items-center justify-content-center">';
                                                    echo '<small class="text-muted">+' . ($photoCount - 6) . ' more</small>';
                                                    echo '</div>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
                                    Tindakan ini tidak dapat dibatalkan. Pastikan Anda yakin ingin menghapus data penerimaan ini.
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger btn-lg" id="deleteButton" disabled>
                                <i class="fas fa-trash-alt mr-2"></i> HAPUS PERMANEN
                            </button>
                            <a href="receive_list.php" class="btn btn-secondary btn-lg">
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
            title: 'Hapus Data Penerimaan?',
            html: `<div class="text-left">
                    <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
                    <ul class="text-left">
                        <li>Data penerimaan akan dihapus permanen</li>
                        <?php if ($photoCount > 0): ?>
                        <li><?= $photoCount ?> foto akan dihapus</li>
                        <?php endif; ?>
                        <li>Status padder akan dikembalikan ke DIBELI</li>
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
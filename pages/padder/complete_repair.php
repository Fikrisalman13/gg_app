<?php
// complete_repair.php - Menyelesaikan perbaikan dan mengembalikan padder
session_start();
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Check permission
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);
    $canEdit = 0;
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $canEdit = $row['CanEdit'];
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $canEdit;
}

$canEdit = checkPermissions($conn, $_SESSION['GroupId'], 117);
if ($canEdit != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menyelesaikan perbaikan.";
    header('Location: repair.php');
    exit;
}

// Get repair ID
$repairId = $_GET['id'] ?? '';
if (empty($repairId)) {
    $_SESSION['error'] = "ID perbaikan tidak valid";
    header('Location: repair.php');
    exit;
}

// Get repair data
$sql = "SELECT r.*, p.padder_name, v.vendor_name 
        FROM dbo.pad_t_repair r 
        INNER JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id 
        LEFT JOIN dbo.pad_m_vendor v ON r.vendor_id = v.vendor_id 
        WHERE r.id = ? AND r.status = 'ON REPAIR'";
$stmt = sqlsrv_query($conn, $sql, [$repairId]);
$repairData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$repairData) {
    $_SESSION['error'] = "Data perbaikan tidak ditemukan atau sudah selesai";
    header('Location: repair.php');
    exit;
}

// Get existing photos
$repairPhotos = [];
$photoSql = "SELECT id, file_path, file_category 
             FROM dbo.pad_t_repair_files 
             WHERE repair_id = ? 
             ORDER BY file_category, uploaded_at";
$photoStmt = sqlsrv_query($conn, $photoSql, [$repairId]);
if ($photoStmt !== false) {
    while ($photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
        $repairPhotos[] = $photo;
    }
    sqlsrv_free_stmt($photoStmt);
}

// Function to handle photo uploads
function handlePhotoUploads($conn, $repairId, $category, $files) {
    $uploadDir = __DIR__ . '/../../uploads/repair_photos/';
    
    // Create directory if not exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9\.]/", "_", $files['name'][$i]);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($files['tmp_name'][$i], $filePath)) {
                // Insert file record to database
                $insertFileSql = "INSERT INTO dbo.pad_t_repair_files (
                                    repair_id, file_path, file_category
                                ) VALUES (?, ?, ?)";
                $fileParams = [
                    $repairId,
                    '/gg_app/uploads/repair_photos/' . $fileName,
                    $category
                ];
                sqlsrv_query($conn, $insertFileSql, $fileParams);
            }
        }
    }
}

// Process completion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $finalStatus = $_POST['final_status'] ?? 'READY';
    $hardnessCheck = $_POST['hardness_check'] ?? '';
    $completionNotes = $_POST['completion_notes'] ?? '';
    
    try {
        sqlsrv_begin_transaction($conn);
        
        // 1. Update repair status to COMPLETED
        $updateRepairSql = "UPDATE dbo.pad_t_repair 
                           SET status = 'COMPLETED'
                           WHERE id = ?";
        $repairStmt = sqlsrv_query($conn, $updateRepairSql, [$repairId]);
        
        if ($repairStmt === false) {
            throw new Exception("Gagal mengupdate status perbaikan");
        }
        
        // 2. Handle AFTER photo uploads
        if (!empty($_FILES['after_photos']['name'][0])) {
            handlePhotoUploads($conn, $repairId, 'AFTER', $_FILES['after_photos']);
        }
        
        // 3. Update padder status
        $updatePadderSql = "UPDATE dbo.pad_m_padder 
                           SET status = ?, 
                               updated_at = GETDATE(),
                               updated_by = ?
                           WHERE padder_id = ?";
        $padderParams = [
            $finalStatus,
            $_SESSION['UserName'],
            $repairData['padder_id']
        ];
        $padderStmt = sqlsrv_query($conn, $updatePadderSql, $padderParams);
        
        if ($padderStmt === false) {
            throw new Exception("Gagal mengupdate status padder");
        }
        
        // 4. Insert status log
        $logRemarks = "Perbaikan selesai dari vendor: " . $repairData['vendor_name'];
        if (!empty($hardnessCheck)) {
            $logRemarks .= " | Hardness: " . $hardnessCheck;
        }
        if (!empty($completionNotes)) {
            $logRemarks .= " | Catatan: " . $completionNotes;
        }
        
        $logSql = "INSERT INTO dbo.pad_status_log (
                    padder_id, status, changed_by, remarks
                ) VALUES (?, ?, ?, ?)";
        $logParams = [
            $repairData['padder_id'],
            $finalStatus,
            $_SESSION['UserName'],
            $logRemarks
        ];
        $logStmt = sqlsrv_query($conn, $logSql, $logParams);
        
        if ($logStmt === false) {
            throw new Exception("Gagal mencatat history status");
        }
        
        // 5. If hardness check provided, insert maintenance record
        if (!empty($hardnessCheck)) {
            $maintenanceSql = "INSERT INTO dbo.pad_t_maintenance (
                                padder_id, maintenance_date, work_done, hardness_check, notes
                            ) VALUES (?, GETDATE(), ?, ?, ?)";
            $maintenanceParams = [
                $repairData['padder_id'],
                "Quality check setelah perbaikan vendor",
                $hardnessCheck,
                "Quality check otomatis setelah perbaikan selesai. " . $completionNotes
            ];
            $maintenanceStmt = sqlsrv_query($conn, $maintenanceSql, $maintenanceParams);
            
            if ($maintenanceStmt === false) {
                throw new Exception("Gagal mencatat quality check");
            }
        }
        
        sqlsrv_commit($conn);
        
        $_SESSION['success'] = "Perbaikan berhasil diselesaikan! Padder dikembalikan dengan status: " . $finalStatus;
        header('Location: repair.php');
        exit;
        
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header('Location: complete_repair.php?id=' . $repairId);
        exit;
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Selesaikan Perbaikan - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    
    <style>
        .required:after {
            content: " *";
            color: red;
        }
        .file-upload-box {
            border: 2px dashed #dee2e6;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
            background: #f8f9fa;
            margin-bottom: 10px;
        }
        .file-preview {
            margin-top: 10px;
        }
        .file-preview-item {
            display: inline-block;
            margin: 5px;
            padding: 5px 10px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .existing-photos {
            margin-bottom: 15px;
        }
        .photo-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin: 5px;
            border: 2px solid #dee2e6;
        }
        .photo-item {
            display: inline-block;
            text-align: center;
            margin: 5px;
        }
        .photo-badge {
            font-size: 0.7em;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Selesaikan Perbaikan</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Konfirmasi Penyelesaian Perbaikan</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Konfirmasi bahwa padder telah selesai diperbaiki dan siap digunakan kembali.
                            </div>

                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label><strong>Padder:</strong></label>
                                    <p class="form-control-plaintext">
                                        <?= htmlspecialchars($repairData['padder_id'] . ' - ' . $repairData['padder_name']) ?>
                                    </p>
                                </div>

                                <div class="form-group">
                                    <label><strong>Vendor:</strong></label>
                                    <p class="form-control-plaintext">
                                        <?= htmlspecialchars($repairData['vendor_name'] ?? '-') ?>
                                    </p>
                                </div>

                                <div class="form-group">
                                    <label><strong>No. Surat Jalan:</strong></label>
                                    <p class="form-control-plaintext">
                                        <?= htmlspecialchars($repairData['sj_number'] ?? '-') ?>
                                    </p>
                                </div>

                                <!-- Existing Photos -->
                                <?php if (!empty($repairPhotos)): ?>
                                <div class="form-group">
                                    <label>Foto Existing</label>
                                    <div class="existing-photos">
                                        <?php 
                                        $beforePhotos = array_filter($repairPhotos, function($photo) {
                                            return $photo['file_category'] === 'BEFORE';
                                        });
                                        $afterPhotos = array_filter($repairPhotos, function($photo) {
                                            return $photo['file_category'] === 'AFTER';
                                        });
                                        ?>
                                        
                                        <?php if (!empty($beforePhotos)): ?>
                                        <div class="mb-3">
                                            <strong>Before:</strong>
                                            <?php foreach ($beforePhotos as $photo): ?>
                                                <div class="photo-item">
                                                    <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                                         alt="Before" 
                                                         class="photo-thumbnail"
                                                         onclick="viewPhoto('<?= htmlspecialchars($photo['file_path']) ?>', 'Before')">
                                                    <div>
                                                        <small class="badge badge-warning photo-badge">Before</small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($afterPhotos)): ?>
                                        <div class="mb-3">
                                            <strong>After:</strong>
                                            <?php foreach ($afterPhotos as $photo): ?>
                                                <div class="photo-item">
                                                    <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                                         alt="After" 
                                                         class="photo-thumbnail"
                                                         onclick="viewPhoto('<?= htmlspecialchars($photo['file_path']) ?>', 'After')">
                                                    <div>
                                                        <small class="badge badge-success photo-badge">After</small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Upload After Photos -->
                                <div class="form-group">
                                    <label for="after_photos">Foto Setelah Perbaikan (AFTER)</label>
                                    <div class="file-upload-box">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-2">Klik untuk memilih foto kondisi setelah perbaikan</p>
                                        <input type="file" id="after_photos" name="after_photos[]" 
                                               class="d-none" multiple accept="image/*">
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="document.getElementById('after_photos').click()">
                                            <i class="fas fa-images"></i> Tambah Foto After
                                        </button>
                                    </div>
                                    <div id="afterPhotosPreview" class="file-preview"></div>
                                    <small class="form-text text-muted">Upload foto kondisi padder setelah diperbaiki (maksimal 5 foto)</small>
                                </div>

                                <div class="form-group">
                                    <label for="final_status" class="required">Status Akhir Padder</label>
                                    <select id="final_status" name="final_status" class="form-control" required>
                                        <option value="READY">READY - Siap digunakan</option>
                                        <option value="IN_USE">IN_USE - Langsung digunakan</option>
                                    </select>
                                    <small class="form-text text-muted">Pilih status padder setelah perbaikan selesai</small>
                                </div>

                                <div class="form-group">
                                    <label for="hardness_check">Hardness Check</label>
                                    <input type="text" id="hardness_check" name="hardness_check" class="form-control" 
                                           placeholder="Hasil pengukuran hardness setelah perbaikan">
                                    <small class="form-text text-muted">Opsional: hasil quality check setelah perbaikan</small>
                                </div>

                                <div class="form-group">
                                    <label for="completion_notes">Catatan Penyelesaian</label>
                                    <textarea id="completion_notes" name="completion_notes" class="form-control" rows="3" 
                                              placeholder="Catatan mengenai hasil perbaikan..."></textarea>
                                    <small class="form-text text-muted">Opsional: catatan mengenai kualitas perbaikan</small>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Konfirmasi Selesai
                                    </button>
                                    <a href="repair.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Batal
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Photo -->
<div class="modal fade" id="viewPhotoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="viewPhotoTitle">Foto Perbaikan</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="viewPhotoImage" src="" alt="Foto Perbaikan" class="img-fluid" style="max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
// Function to view photo in modal
function viewPhoto(src, category) {
    $('#viewPhotoTitle').text('Foto ' + category + ' - Perbaikan');
    $('#viewPhotoImage').attr('src', src);
    $('#viewPhotoModal').modal('show');
}

// File upload preview for after photos
$('#after_photos').on('change', function() {
    var files = this.files;
    var $preview = $('#afterPhotosPreview');
    $preview.empty();
    
    if (files.length > 5) {
        Swal.fire({
            icon: 'warning',
            title: 'Maksimal 5 Foto',
            text: 'Maksimal 5 foto yang diizinkan untuk upload sekaligus',
            timer: 3000,
            showConfirmButton: false
        });
        this.value = '';
        return;
    }
    
    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        if (file.type.match('image.*')) {
            var reader = new FileReader();
            reader.onload = (function(file) {
                return function(e) {
                    var previewItem = $('<div class="file-preview-item"></div>');
                    previewItem.html('<i class="fas fa-image text-success mr-1"></i>' + file.name);
                    $preview.append(previewItem);
                };
            })(file);
            reader.readAsDataURL(file);
        }
    }
});

// Form validation
$('form').on('submit', function(e) {
    var finalStatus = $('#final_status').val();
    
    if (!finalStatus) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Data Belum Lengkap',
            text: 'Harap pilih status akhir padder',
            timer: 3000,
            showConfirmButton: false
        });
        return false;
    }
    
    // Validate file size and type
    var afterFiles = $('#after_photos')[0].files;
    
    for (var i = 0; i < afterFiles.length; i++) {
        if (afterFiles[i].size > 5 * 1024 * 1024) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'File Terlalu Besar',
                text: 'Maksimal ukuran file 5MB per foto',
                timer: 3000,
                showConfirmButton: false
            });
            return false;
        }
    }
    
    // Show confirmation
    e.preventDefault();
    
    Swal.fire({
        title: 'Konfirmasi Penyelesaian Perbaikan',
        html: 'Anda yakin ingin menyelesaikan perbaikan ini?<br><br><small class="text-muted">Padder akan dikembalikan dengan status: ' + finalStatus + '</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check"></i> Ya, Selesaikan',
        cancelButtonText: '<i class="fas fa-times"></i> Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Menyelesaikan Perbaikan',
                text: 'Sedang memproses...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit form
            $('form').off('submit').submit();
        }
    });
});

// Show notification if any
<?php if (isset($_SESSION['error'])): ?>
Swal.fire({ 
    icon: 'error', 
    title: 'Gagal!', 
    html: <?= json_encode($_SESSION['error']) ?>, 
    timer: 5000, 
    showConfirmButton: true 
});
<?php unset($_SESSION['error']); endif; ?>
</script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>
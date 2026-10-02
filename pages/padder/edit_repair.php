<?php
// edit_repair.php - Form Edit Perbaikan Padder
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

// helper: ambil permission user untuk menu Repair
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);

    return $permissions;
}

// Ambil permission
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 117);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data perbaikan.";
    header('Location: repair.php');
    exit;
}

// Get repair data by ID
$id = $_GET['id'] ?? '';
$repairData = null;
$repairPhotos = [];

if (empty($id)) {
    $_SESSION['error'] = "ID perbaikan tidak valid";
    header('Location: repair.php');
    exit;
}

// Fetch repair data
$sql = "SELECT r.*, p.padder_name, p.status as padder_status, v.vendor_name
        FROM dbo.pad_t_repair r
        INNER JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id
        LEFT JOIN dbo.pad_m_vendor v ON r.vendor_id = v.vendor_id
        WHERE r.id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $repairData = $row;
    
    // Format dates
    if ($repairData['send_date'] instanceof DateTime) {
        $repairData['send_date'] = $repairData['send_date']->format('Y-m-d');
    }
}
sqlsrv_free_stmt($stmt);

if (!$repairData) {
    $_SESSION['error'] = "Data perbaikan tidak ditemukan";
    header('Location: repair.php');
    exit;
}

// Get repair photos
$photoSql = "SELECT id, file_path, file_category 
             FROM dbo.pad_t_repair_files 
             WHERE repair_id = ? 
             ORDER BY file_category, uploaded_at";
$photoStmt = sqlsrv_query($conn, $photoSql, [$id]);
if ($photoStmt !== false) {
    while ($photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
        $repairPhotos[] = $photo;
    }
    sqlsrv_free_stmt($photoStmt);
}

// ====== Get Lists ======
function getVendorList($conn) {
    $sql = "SELECT vendor_id, vendor_name FROM dbo.pad_m_vendor ORDER BY vendor_name";
    $stmt = sqlsrv_query($conn, $sql);
    $vendors = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $vendors[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $vendors;
}

// Process form submission for edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $send_date = $_POST['send_date'] ?? '';
    $vendor_id = $_POST['vendor_id'] ?? '';
    $sj_number = $_POST['sj_number'] ?? '';
    $repair_notes = $_POST['repair_notes'] ?? '';
    
    $errors = [];
    
    // Validasi
    if (empty($send_date)) {
        $errors[] = "Tanggal pengiriman harus diisi";
    }
    
    if (empty($vendor_id)) {
        $errors[] = "Vendor harus dipilih";
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            sqlsrv_begin_transaction($conn);
            
            // Simpan data lama untuk log
            $old_data = $repairData;
            
            // Update data perbaikan
            $updateSql = "UPDATE dbo.pad_t_repair 
                         SET send_date = ?, vendor_id = ?, sj_number = ?, repair_notes = ?
                         WHERE id = ?";
            
            $params = [
                $send_date,
                $vendor_id,
                $sj_number,
                $repair_notes,
                $id
            ];
            
            $updateStmt = sqlsrv_query($conn, $updateSql, $params);
            
            if ($updateStmt === false) {
                throw new Exception("Gagal mengupdate data perbaikan");
            }
            
            // Handle new photo uploads
            if (!empty($_FILES['new_before_photos']['name'][0])) {
                handlePhotoUploads($conn, $id, 'BEFORE', $_FILES['new_before_photos']);
            }
            
            if (!empty($_FILES['new_after_photos']['name'][0])) {
                handlePhotoUploads($conn, $id, 'AFTER', $_FILES['new_after_photos']);
            }
            
            // Insert ke log status - mencatat perubahan data perbaikan
            $logSql = "INSERT INTO dbo.pad_status_log (
                        padder_id, status, changed_by, remarks
                    ) VALUES (?, ?, ?, ?)";
            
            $logRemarks = "Update Data Perbaikan: ";
            $changes = [];
            
            // Catat perubahan tanggal
            if ($old_data['send_date'] != $send_date) {
                $oldDate = $old_data['send_date'];
                if ($oldDate instanceof DateTime) {
                    $oldDate = $oldDate->format('Y-m-d');
                }
                $changes[] = "Tanggal: {$oldDate} → {$send_date}";
            }
            
            // Catat perubahan vendor
            if ($old_data['vendor_id'] != $vendor_id) {
                $changes[] = "Vendor diubah";
            }
            
            // Catat perubahan nomor SJ
            if (($old_data['sj_number'] ?? '') != $sj_number) {
                $oldSj = $old_data['sj_number'] ?? '(kosong)';
                $newSj = $sj_number ?: '(kosong)';
                $changes[] = "No. SJ: {$oldSj} → {$newSj}";
            }
            
            // Catat perubahan catatan
            if (($old_data['repair_notes'] ?? '') != $repair_notes) {
                $oldNotes = $old_data['repair_notes'] ?? '(kosong)';
                $newNotes = $repair_notes ?: '(kosong)';
                $changes[] = "Catatan: {$oldNotes} → {$newNotes}";
            }
            
            if (!empty($changes)) {
                $logRemarks .= implode(', ', $changes);
                
                $logParams = [
                    $repairData['padder_id'],
                    'REPAIR_VENDOR',
                    $_SESSION['UserName'],
                    $logRemarks
                ];
                
                $logStmt = sqlsrv_query($conn, $logSql, $logParams);
                
                if ($logStmt === false) {
                    throw new Exception("Gagal mencatat history perubahan");
                }
            }
            
            // Commit transaction
            sqlsrv_commit($conn);
            
            $_SESSION['success'] = "Data perbaikan berhasil diupdate!";
            header('Location: repair.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            sqlsrv_rollback($conn);
            $_SESSION['error'] = $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    // Reload data setelah error
    $sql = "SELECT r.*, p.padder_name, p.status as padder_status, v.vendor_name
            FROM dbo.pad_t_repair r
            INNER JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id
            LEFT JOIN dbo.pad_m_vendor v ON r.vendor_id = v.vendor_id
            WHERE r.id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $repairData = $row;
        if ($repairData['send_date'] instanceof DateTime) {
            $repairData['send_date'] = $repairData['send_date']->format('Y-m-d');
        }
    }
    sqlsrv_free_stmt($stmt);
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

$vendorList = getVendorList($conn);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Perbaikan Padder</title>

    <!-- CSS (AdminLTE) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/bootstrap-datepicker3.min.css">

    <style>
        .required:after {
            content: " *";
            color: red;
        }
        .card-header {
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .info-box {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .readonly-field {
            background-color: #e9ecef;
            opacity: 1;
        }
        .change-log {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
            font-size: 0.9em;
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
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Perbaikan Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="repair.php">Perbaikan Padder</a></li>
                        <li class="breadcrumb-item active">Edit Perbaikan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'info'); ?> text-white">
                            <h3 class="card-title">Form Edit Perbaikan</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($repairData): ?>
                            <!-- Info Box -->
                            <div class="info-box">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Informasi:</strong> Hanya data tanggal, vendor, nomor SJ, catatan, dan foto yang dapat diubah. Padder tidak dapat diubah setelah dikirim ke vendor. Semua perubahan akan dicatat dalam log sistem.
                            </div>

                            <form method="POST" action="" id="editForm" enctype="multipart/form-data">
                                <!-- Padder Info (Readonly) -->
                                <div class="form-group">
                                    <label>Padder</label>
                                    <input type="text" class="form-control readonly-field" 
                                           value="<?= htmlspecialchars($repairData['padder_id'] . ' - ' . $repairData['padder_name']) ?>" 
                                           readonly>
                                    <small class="form-text text-muted">Padder tidak dapat diubah setelah dikirim ke vendor</small>
                                </div>

                                <div class="form-group">
                                    <label>Status Perbaikan Saat Ini</label>
                                    <?php
                                    $statusClass = 'badge-secondary';
                                    switch($repairData['status']) {
                                        case 'ON REPAIR': $statusClass = 'badge-warning'; break;
                                        case 'COMPLETED': $statusClass = 'badge-success'; break;
                                        case 'CANCELLED': $statusClass = 'badge-danger'; break;
                                    }
                                    ?>
                                    <div>
                                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($repairData['status']) ?></span>
                                    </div>
                                    <small class="form-text text-muted">Status perbaikan saat ini</small>
                                </div>

                                <!-- Editable Fields -->
                                <div class="form-group">
                                    <label for="send_date" class="required">Tanggal Pengiriman</label>
                                    <input type="date" id="send_date" name="send_date" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($repairData['send_date']) ?>" 
                                           required>
                                    <small class="form-text text-muted">Tanggal ketika padder dikirim ke vendor</small>
                                </div>

                                <div class="form-group">
                                    <label for="vendor_id" class="required">Vendor</label>
                                    <select id="vendor_id" name="vendor_id" class="form-control" required>
                                        <option value="">-- Pilih Vendor --</option>
                                        <?php foreach ($vendorList as $vendor): ?>
                                            <option value="<?= htmlspecialchars($vendor['vendor_id']) ?>" 
                                                <?= ($repairData['vendor_id'] == $vendor['vendor_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($vendor['vendor_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Vendor yang melakukan perbaikan</small>
                                </div>

                                <div class="form-group">
                                    <label for="sj_number">No. Surat Jalan</label>
                                    <input type="text" id="sj_number" name="sj_number" class="form-control" 
                                           value="<?= htmlspecialchars($repairData['sj_number'] ?? '') ?>" 
                                           placeholder="Nomor surat jalan pengiriman">
                                    <small class="form-text text-muted">Nomor surat jalan pengiriman</small>
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

                                <!-- New Before Photos -->
                                <div class="form-group">
                                    <label for="new_before_photos">Tambah Foto Before Baru</label>
                                    <div class="file-upload-box">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-2">Klik untuk memilih foto before tambahan</p>
                                        <input type="file" id="new_before_photos" name="new_before_photos[]" 
                                               class="d-none" multiple accept="image/*">
                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="document.getElementById('new_before_photos').click()">
                                            <i class="fas fa-images"></i> Tambah Foto Before
                                        </button>
                                    </div>
                                    <div id="newBeforePhotosPreview" class="file-preview"></div>
                                    <small class="form-text text-muted">Opsional: tambahan foto kondisi sebelum perbaikan</small>
                                </div>

                                <!-- New After Photos -->
                                <div class="form-group">
                                    <label for="new_after_photos">Tambah Foto After Baru</label>
                                    <div class="file-upload-box">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-2">Klik untuk memilih foto after tambahan</p>
                                        <input type="file" id="new_after_photos" name="new_after_photos[]" 
                                               class="d-none" multiple accept="image/*">
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="document.getElementById('new_after_photos').click()">
                                            <i class="fas fa-images"></i> Tambah Foto After
                                        </button>
                                    </div>
                                    <div id="newAfterPhotosPreview" class="file-preview"></div>
                                    <small class="form-text text-muted">Opsional: tambahan foto kondisi setelah perbaikan</small>
                                </div>

                                <div class="form-group">
                                    <label for="repair_notes">Catatan Perbaikan</label>
                                    <textarea id="repair_notes" name="repair_notes" class="form-control" rows="3" 
                                              placeholder="Catatan perbaikan..."><?= htmlspecialchars($repairData['repair_notes'] ?? '') ?></textarea>
                                    <small class="form-text text-muted">Catatan mengenai perbaikan</small>
                                </div>

                                <!-- Change Summary (akan diisi oleh JavaScript) -->
                                <div id="changeSummary" class="change-log" style="display: none;">
                                    <h6><i class="fas fa-history"></i> Ringkasan Perubahan:</h6>
                                    <div id="changeDetails"></div>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Update Data
                                    </button>
                                    <a href="repair.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
                                    <button type="button" id="btnReset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Data tidak ditemukan.
                                </div>
                                <a href="repair.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'info'); ?> text-white">
                            <h3 class="card-title">Informasi Data</h3>
                        </div>
                        <div class="card-body">
                            <h6><i class="fas fa-database"></i> Detail Data:</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>ID Record:</strong></td>
                                    <td><?= htmlspecialchars($repairData['id'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Padder ID:</strong></td>
                                    <td><?= htmlspecialchars($repairData['padder_id'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Nama Padder:</strong></td>
                                    <td><?= htmlspecialchars($repairData['padder_name'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status Padder:</strong></td>
                                    <td>
                                        <?php if ($repairData): ?>
                                            <?php
                                            $padderStatusClass = 'badge-secondary';
                                            switch($repairData['padder_status']) {
                                                case 'READY': $padderStatusClass = 'badge-success'; break;
                                                case 'IN_USE': $padderStatusClass = 'badge-primary'; break;
                                                case 'MAINTENANCE': $padderStatusClass = 'badge-warning'; break;
                                                case 'REPAIR_VENDOR': $padderStatusClass = 'badge-info'; break;
                                                case 'REPAIRED': $padderStatusClass = 'badge-info'; break;
                                                case 'SCRAP': $padderStatusClass = 'badge-danger'; break;
                                            }
                                            ?>
                                            <span class="badge <?= $padderStatusClass ?>">
                                                <?= htmlspecialchars($repairData['padder_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Vendor Saat Ini:</strong></td>
                                    <td><?= htmlspecialchars($repairData['vendor_name'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>No. SJ Saat Ini:</strong></td>
                                    <td><?= htmlspecialchars($repairData['sj_number'] ?? '-') ?></td>
                                </tr>
                            </table>
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

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
    // Store original values for comparison
    const originalValues = {
        send_date: '<?= htmlspecialchars($repairData['send_date']) ?>',
        vendor_id: '<?= htmlspecialchars($repairData['vendor_id'] ?? '') ?>',
        sj_number: '<?= htmlspecialchars($repairData['sj_number'] ?? '') ?>',
        repair_notes: `<?= str_replace(["\r", "\n"], '', addslashes($repairData['repair_notes'] ?? '')) ?>`
    };

    // Reset form handler
    $('#btnReset').on('click', function() {
        // Reset form ke nilai semula
        $('#send_date').val(originalValues.send_date);
        $('#vendor_id').val(originalValues.vendor_id);
        $('#sj_number').val(originalValues.sj_number);
        $('#repair_notes').val(originalValues.repair_notes);
        
        // Clear file inputs
        $('#new_before_photos').val('');
        $('#new_after_photos').val('');
        $('#newBeforePhotosPreview').empty();
        $('#newAfterPhotosPreview').empty();
        
        // Sembunyikan ringkasan perubahan
        $('#changeSummary').hide();
        
        Swal.fire({
            icon: 'info',
            title: 'Form Direset',
            text: 'Form telah dikembalikan ke nilai semula',
            timer: 2000,
            showConfirmButton: false
        });
    });

    // Function to view photo in modal
    window.viewPhoto = function(src, category) {
        $('#viewPhotoTitle').text('Foto ' + category + ' - Perbaikan');
        $('#viewPhotoImage').attr('src', src);
        $('#viewPhotoModal').modal('show');
    };

    // File upload preview for new before photos
    $('#new_before_photos').on('change', function() {
        var files = this.files;
        var $preview = $('#newBeforePhotosPreview');
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
                        previewItem.html('<i class="fas fa-image text-warning mr-1"></i>' + file.name);
                        $preview.append(previewItem);
                    };
                })(file);
                reader.readAsDataURL(file);
            }
        }
        detectChanges();
    });

    // File upload preview for new after photos
    $('#new_after_photos').on('change', function() {
        var files = this.files;
        var $preview = $('#newAfterPhotosPreview');
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
        detectChanges();
    });

    // Function to detect changes and show summary
    function detectChanges() {
        const currentValues = {
            send_date: $('#send_date').val(),
            vendor_id: $('#vendor_id').val(),
            sj_number: $('#sj_number').val(),
            repair_notes: $('#repair_notes').val()
        };

        const changes = [];
        
        // Check each field for changes
        if (currentValues.send_date !== originalValues.send_date) {
            changes.push(`<strong>Tanggal:</strong> ${originalValues.send_date} → ${currentValues.send_date}`);
        }
        
        if (currentValues.vendor_id !== originalValues.vendor_id) {
            changes.push(`<strong>Vendor diubah</strong>`);
        }
        
        if (currentValues.sj_number !== originalValues.sj_number) {
            const oldSj = originalValues.sj_number || '(kosong)';
            const newSj = currentValues.sj_number || '(kosong)';
            changes.push(`<strong>No. SJ:</strong> ${oldSj} → ${newSj}`);
        }
        
        if (currentValues.repair_notes !== originalValues.repair_notes) {
            const oldNotes = originalValues.repair_notes || '(kosong)';
            const newNotes = currentValues.repair_notes || '(kosong)';
            changes.push(`<strong>Catatan:</strong> ${oldNotes} → ${newNotes}`);
        }

        // Check for new files
        const newBeforeFiles = $('#new_before_photos')[0].files;
        const newAfterFiles = $('#new_after_photos')[0].files;
        
        if (newBeforeFiles.length > 0) {
            changes.push(`<strong>Foto Before Baru:</strong> ${newBeforeFiles.length} file`);
        }
        
        if (newAfterFiles.length > 0) {
            changes.push(`<strong>Foto After Baru:</strong> ${newAfterFiles.length} file`);
        }

        // Show/hide change summary
        const changeSummary = $('#changeSummary');
        const changeDetails = $('#changeDetails');
        
        if (changes.length > 0) {
            changeDetails.html(changes.join('<br>'));
            changeSummary.show();
        } else {
            changeSummary.hide();
        }
    }

    // Form validation
    $('#editForm').on('submit', function(e) {
        var sendDate = $('#send_date').val();
        var vendorId = $('#vendor_id').val();
        
        if (!sendDate || !vendorId) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap',
                text: 'Harap lengkapi semua field yang wajib diisi',
                timer: 3000,
                showConfirmButton: false
            });
            return false;
        }
        
        // Validate file size and type
        var beforeFiles = $('#new_before_photos')[0].files;
        var afterFiles = $('#new_after_photos')[0].files;
        
        for (var i = 0; i < beforeFiles.length; i++) {
            if (beforeFiles[i].size > 5 * 1024 * 1024) {
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
        
        // Show loading dengan konfirmasi perubahan
        const changes = [];
        if ($('#send_date').val() !== originalValues.send_date) changes.push('Tanggal');
        if ($('#vendor_id').val() !== originalValues.vendor_id) changes.push('Vendor');
        if ($('#sj_number').val() !== originalValues.sj_number) changes.push('No. SJ');
        if ($('#repair_notes').val() !== originalValues.repair_notes) changes.push('Catatan');
        if (beforeFiles.length > 0) changes.push('Foto Before Baru');
        if (afterFiles.length > 0) changes.push('Foto After Baru');
        
        let confirmMessage = 'Anda yakin ingin menyimpan perubahan?';
        if (changes.length > 0) {
            confirmMessage = `Anda yakin ingin menyimpan perubahan pada: ${changes.join(', ')}?`;
        }
        
        e.preventDefault();
        
        Swal.fire({
            title: 'Konfirmasi Update',
            html: confirmMessage + '<br><br><small class="text-muted">Perubahan akan dicatat dalam log sistem.</small>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-save"></i> Ya, Update',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Memperbarui Data',
                    text: 'Sedang menyimpan perubahan...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit form
                $('#editForm').off('submit').submit();
            }
        });
    });

    // Listen for changes in form fields
    $('#send_date, #vendor_id, #sj_number, #repair_notes').on('change input', detectChanges);

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

    // Auto-focus first field
    $(document).ready(function() {
        $('#send_date').focus();
        
        // Initialize change detection
        detectChanges();
    });

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
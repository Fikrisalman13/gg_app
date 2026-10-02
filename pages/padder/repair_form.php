<?php
// repair_form.php - Form Input Perbaikan Padder ke Vendor
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

// Ambil permission (sesuaikan MenuId dengan menu Repair di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 117);
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data perbaikan.";
    header('Location: repair.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getAvailablePadders($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            WHERE status IN ('IN_USE', 'MAINTENANCE')
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

// ====== Get Vendor List for Dropdown ======
function getVendorList($conn) {
    $sql = "SELECT vendor_id, vendor_name 
            FROM dbo.pad_m_vendor 
            ORDER BY vendor_name";
    $stmt = sqlsrv_query($conn, $sql);
    
    $vendors = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $vendors[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $vendors;
}

// ====== Process Form Submission ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $padder_id = $_POST['padder_id'] ?? '';
    $send_date = $_POST['send_date'] ?? '';
    $vendor_id = $_POST['vendor_id'] ?? '';
    $sj_number = $_POST['sj_number'] ?? '';
    $repair_notes = $_POST['repair_notes'] ?? '';
    
    // Validasi input
    $errors = [];
    
    if (empty($padder_id)) {
        $errors[] = "Padder harus dipilih";
    }
    
    if (empty($send_date)) {
        $errors[] = "Tanggal pengiriman harus diisi";
    }
    
    if (empty($vendor_id)) {
        $errors[] = "Vendor harus dipilih";
    }
    
    // Check if padder exists and is in valid status
    if (!empty($padder_id)) {
        $checkSql = "SELECT status FROM dbo.pad_m_padder WHERE padder_id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$padder_id]);
        if ($checkStmt !== false && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            if (!in_array($row['status'], ['IN_USE', 'MAINTENANCE'])) {
                $errors[] = "Padder tidak dalam status yang valid untuk perbaikan. Status saat ini: " . $row['status'];
            }
        } else {
            $errors[] = "Padder tidak ditemukan";
        }
        if ($checkStmt) sqlsrv_free_stmt($checkStmt);
    }
    
    // Check if vendor exists
    if (!empty($vendor_id)) {
        $checkVendorSql = "SELECT vendor_id FROM dbo.pad_m_vendor WHERE vendor_id = ?";
        $checkVendorStmt = sqlsrv_query($conn, $checkVendorSql, [$vendor_id]);
        if ($checkVendorStmt === false || !sqlsrv_fetch_array($checkVendorStmt, SQLSRV_FETCH_ASSOC)) {
            $errors[] = "Vendor tidak valid";
        }
        if ($checkVendorStmt) sqlsrv_free_stmt($checkVendorStmt);
    }
    
    if (empty($errors)) {
        // Begin transaction
        sqlsrv_begin_transaction($conn);
        
        try {
            // 1. Insert into repair table
            $insertRepairSql = "INSERT INTO dbo.pad_t_repair (
                                padder_id, send_date, vendor_id, sj_number, repair_notes, status
                            ) VALUES (?, ?, ?, ?, ?, 'ON REPAIR')";
            
            $repairParams = [
                $padder_id,
                $send_date,
                $vendor_id,
                $sj_number,
                $repair_notes
            ];
            
            $repairStmt = sqlsrv_query($conn, $insertRepairSql, $repairParams);
            
            if ($repairStmt === false) {
                throw new Exception("Gagal menyimpan data perbaikan");
            }
            
            // Get the inserted repair ID
            $repairId = null;
            $getIdSql = "SELECT IDENT_CURRENT('dbo.pad_t_repair') as repair_id";
            $getIdStmt = sqlsrv_query($conn, $getIdSql);
            if ($getIdStmt !== false && $row = sqlsrv_fetch_array($getIdStmt, SQLSRV_FETCH_ASSOC)) {
                $repairId = $row['repair_id'];
            }
            if ($getIdStmt) sqlsrv_free_stmt($getIdStmt);
            
            // 2. Update padder status to REPAIR_VENDOR
            $updatePadderSql = "UPDATE dbo.pad_m_padder 
                               SET status = 'REPAIR_VENDOR', 
                                   updated_at = GETDATE(),
                                   updated_by = ?
                               WHERE padder_id = ?";
            
            $updateParams = [
                $_SESSION['UserName'],
                $padder_id
            ];
            
            $updateStmt = sqlsrv_query($conn, $updatePadderSql, $updateParams);
            
            if ($updateStmt === false) {
                throw new Exception("Gagal mengupdate status padder");
            }
            
            // 3. Insert into status log
            $insertLogSql = "INSERT INTO dbo.pad_status_log (
                                padder_id, status, changed_by, remarks
                            ) VALUES (?, 'REPAIR_VENDOR', ?, ?)";
            
            $logRemarks = "Dikirim ke vendor untuk perbaikan";
            if (!empty($sj_number)) {
                $logRemarks .= " | No. SJ: " . $sj_number;
            }
            if (!empty($repair_notes)) {
                $logRemarks .= " | Catatan: " . $repair_notes;
            }
            
            $logParams = [
                $padder_id,
                $_SESSION['UserName'],
                $logRemarks
            ];
            
            $logStmt = sqlsrv_query($conn, $insertLogSql, $logParams);
            
            if ($logStmt === false) {
                throw new Exception("Gagal mencatat history status");
            }
            
            // 4. Handle file uploads if any
            if (!empty($_FILES['before_photos']['name'][0])) {
                handlePhotoUploads($conn, $repairId, 'BEFORE', $_FILES['before_photos']);
            }
            
            // Commit transaction
            sqlsrv_commit($conn);
            
            $_SESSION['success'] = "Data perbaikan berhasil disimpan! Padder telah dikirim ke vendor.";
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

$padderList = getAvailablePadders($conn);
$vendorList = getVendorList($conn);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Perbaikan Padder ke Vendor</title>

    <!-- CSS (AdminLTE) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    
    <!-- Datepicker CSS -->
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
        .padder-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #17a2b8;
            margin-top: 10px;
            display: none;
        }
        .file-upload-box {
            border: 2px dashed #dee2e6;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            margin-bottom: 10px;
        }
        .file-upload-box:hover {
            border-color: #17a2b8;
            background: #e9f7fe;
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
        .spec-item {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        .spec-name {
            font-weight: bold;
            color: #495057;
        }
        .spec-value {
            color: #28a745;
        }
        .info-section {
            margin-bottom: 15px;
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
                    <h1 class="m-0">Input Perbaikan ke Vendor</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="repair.php">Perbaikan Padder</a></li>
                        <li class="breadcrumb-item active">Input Perbaikan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'info'); ?> text-white">
                            <h3 class="card-title">Form Input Perbaikan ke Vendor</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                <h6><i class="fas fa-info-circle"></i> Informasi</h6>
                                <small>Form ini untuk mencatat pengiriman padder ke vendor untuk perbaikan. Pastikan padder dalam status IN_USE atau MAINTENANCE.</small>
                            </div>

                            <form id="repairForm" method="POST" action="" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="padder_id" class="required">Padder</label>
                                    <select id="padder_id" name="padder_id" class="form-control" required>
                                        <option value="">-- Pilih Padder --</option>
                                        <?php foreach ($padderList as $padder): ?>
                                            <option value="<?= htmlspecialchars($padder['padder_id']) ?>" 
                                                <?= (isset($_POST['padder_id']) && $_POST['padder_id'] == $padder['padder_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Pilih padder yang akan dikirim untuk perbaikan</small>
                                </div>

                                <!-- Padder Info (akan diisi via AJAX) -->
                                <div id="padderInfo" class="padder-info">
                                    <!-- Informasi akan diisi oleh JavaScript -->
                                </div>

                                <div class="form-group">
                                    <label for="send_date" class="required">Tanggal Pengiriman</label>
                                    <input type="text" id="send_date" name="send_date" class="form-control datepicker" 
                                           value="<?= isset($_POST['send_date']) ? htmlspecialchars($_POST['send_date']) : date('Y-m-d') ?>" 
                                           required readonly>
                                    <small class="form-text text-muted">Tanggal ketika padder dikirim ke vendor</small>
                                </div>

                                <div class="form-group">
                                    <label for="vendor_id" class="required">Vendor</label>
                                    <select id="vendor_id" name="vendor_id" class="form-control" required>
                                        <option value="">-- Pilih Vendor --</option>
                                        <?php foreach ($vendorList as $vendor): ?>
                                            <option value="<?= htmlspecialchars($vendor['vendor_id']) ?>" 
                                                <?= (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $vendor['vendor_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($vendor['vendor_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Pilih vendor yang akan melakukan perbaikan</small>
                                </div>

                                <div class="form-group">
                                    <label for="sj_number">No. Surat Jalan</label>
                                    <input type="text" id="sj_number" name="sj_number" class="form-control" 
                                           value="<?= isset($_POST['sj_number']) ? htmlspecialchars($_POST['sj_number']) : '' ?>" 
                                           placeholder="Nomor surat jalan pengiriman">
                                    <small class="form-text text-muted">Opsional: nomor surat jalan pengiriman</small>
                                </div>

                                <div class="form-group">
                                    <label for="before_photos">Foto Sebelum Perbaikan (Before)</label>
                                    <div class="file-upload-box">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-2">Klik untuk memilih foto atau drag & drop file di sini</p>
                                        <input type="file" id="before_photos" name="before_photos[]" 
                                               class="d-none" multiple accept="image/*">
                                        <button type="button" class="btn btn-outline-info btn-sm" onclick="document.getElementById('before_photos').click()">
                                            <i class="fas fa-images"></i> Pilih Foto
                                        </button>
                                    </div>
                                    <div id="beforePhotosPreview" class="file-preview"></div>
                                    <small class="form-text text-muted">Opsional: foto kondisi padder sebelum dikirim ke vendor (maks. 5 foto)</small>
                                </div>

                                <div class="form-group">
                                    <label for="repair_notes">Catatan Perbaikan</label>
                                    <textarea id="repair_notes" name="repair_notes" class="form-control" rows="3" 
                                              placeholder="Jelaskan kerusakan atau pekerjaan perbaikan yang diperlukan..."><?= isset($_POST['repair_notes']) ? htmlspecialchars($_POST['repair_notes']) : '' ?></textarea>
                                    <small class="form-text text-muted">Opsional: catatan mengenai kerusakan atau perbaikan yang diperlukan</small>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor ?? 'info');?>">
                                        <i class="fas fa-paper-plane"></i> Kirim ke Vendor
                                    </button>
                                    <a href="repair.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
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

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.id.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
    // Initialize datepicker
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true,
        language: 'id'
    });

    // Set default date to today
    $('#send_date').val(new Date().toISOString().split('T')[0]);

    // Padder selection change handler
    $('#padder_id').on('change', function() {
        var padderId = $(this).val();
        var $padderInfo = $('#padderInfo');
        
        if (padderId) {
            // Show loading
            $padderInfo.html(`
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-info" role="status"></div> 
                    <small class="text-muted ml-2">Memuat informasi padder...</small>
                </div>
            `).show();
            
            // Fetch padder details via AJAX
            $.ajax({
                url: 'get_padder_detail.php',
                type: 'POST',
                data: { 
                    padder_id: padderId 
                },
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;
                        renderPadderInfo(data);
                    } else {
                        $padderInfo.hide();
                        var errorMsg = response.message || 'Gagal mengambil data padder';
                        showError('Error', errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX request failed:', error);
                    $padderInfo.hide();
                    showError('Error', 'Terjadi kesalahan saat mengambil data padder');
                }
            });
        } else {
            $padderInfo.hide();
        }
    });

    // Function to render padder information
    function renderPadderInfo(data) {
        var $padderInfo = $('#padderInfo');
        
        // Set badge color based on status
        var badgeClass = 'badge-secondary';
        switch(data.status) {
            case 'READY': badgeClass = 'badge-success'; break;
            case 'IN_USE': badgeClass = 'badge-primary'; break;
            case 'MAINTENANCE': badgeClass = 'badge-warning'; break;
            case 'REPAIR_VENDOR': badgeClass = 'badge-info'; break;
            case 'REPAIRED': badgeClass = 'badge-info'; break;
            case 'SCRAP': badgeClass = 'badge-danger'; break;
            default: badgeClass = 'badge-secondary';
        }

        var infoHtml = `
            <h6><i class="fas fa-info-circle"></i> Informasi Padder</h6>
            
            <!-- Basic Information -->
            <div class="info-section">
                <div class="row">
                    <div class="col-md-6">
                        <small><strong>ID:</strong> ${data.padder_id || '-'}</small><br>
                        <small><strong>Nama:</strong> ${data.padder_name || '-'}</small>
                    </div>
                    <div class="col-md-6">
                        <small><strong>Status:</strong> <span class="badge ${badgeClass}">${data.status || '-'}</span></small><br>
                        <small><strong>Update Terakhir:</strong> ${data.updated_at_formatted || '-'}</small>
                    </div>
                </div>
            </div>
        `;

        // Specifications
        if (data.specifications && data.specifications.length > 0) {
            infoHtml += `
                <div class="info-section">
                    <h6><i class="fas fa-list-alt"></i> Spesifikasi Teknis</h6>
                    <div class="mt-2">
            `;
            
            data.specifications.forEach(function(spec) {
                infoHtml += `
                    <div class="spec-item">
                        <span class="spec-name">${spec.spec_name}:</span>
                        <span class="spec-value"> ${spec.spec_value}</span>
                    </div>
                `;
            });
            
            infoHtml += `
                    </div>
                </div>
            `;
        }

        $padderInfo.html(infoHtml).show();
    }

    // File upload preview
    $('#before_photos').on('change', function() {
        var files = this.files;
        var $preview = $('#beforePhotosPreview');
        $preview.empty();
        
        if (files.length > 5) {
            showError('Error', 'Maksimal 5 foto yang diizinkan');
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
                        previewItem.html('<i class="fas fa-image text-info mr-1"></i>' + file.name);
                        $preview.append(previewItem);
                    };
                })(file);
                reader.readAsDataURL(file);
            }
        }
    });

    // Drag and drop functionality
    $('.file-upload-box').on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('bg-light');
    });

    $('.file-upload-box').on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('bg-light');
    });

    $('.file-upload-box').on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('bg-light');
        
        var files = e.originalEvent.dataTransfer.files;
        $('#before_photos')[0].files = files;
        $('#before_photos').trigger('change');
    });

    // Helper function untuk menampilkan error
    function showError(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            timer: 5000,
            showConfirmButton: true
        });
    }

    // Form submission validation
    $('#repairForm').on('submit', function(e) {
        var padderId = $('#padder_id').val();
        var sendDate = $('#send_date').val();
        var vendorId = $('#vendor_id').val();
        
        if (!padderId || !sendDate || !vendorId) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Harap lengkapi semua field yang wajib diisi',
                timer: 3000,
                showConfirmButton: false
            });
            return false;
        }
        
        // Validate file size and type
        var files = $('#before_photos')[0].files;
        for (var i = 0; i < files.length; i++) {
            if (files[i].size > 5 * 1024 * 1024) { // 5MB
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
        
        // Show loading
        Swal.fire({
            title: 'Mengirim Data',
            text: 'Sedang menyimpan data perbaikan...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
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

    <?php if (isset($_SESSION['success'])): ?>
    Swal.fire({ 
        icon: 'success', 
        title: 'Sukses!', 
        text: <?= json_encode($_SESSION['success']) ?>, 
        timer: 3000, 
        showConfirmButton: false 
    });
    <?php unset($_SESSION['success']); endif; ?>

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
<?php
// receive.php - Form Penerimaan Barang (GRN) + Upload Foto
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

// Cek permission untuk modul receive
function checkReceivePermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanAdd' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $result;
}

$permissions = checkReceivePermission($conn, $_SESSION['GroupId'], 114);
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk input penerimaan barang.";
    header('Location: master_padder.php');
    exit;
}

// Get data untuk dropdowns
function getDropdownData($conn) {
    $data = [];
    
    // Get padders yang sudah dibeli (status DIBELI) dan belum diterima
    $sqlPadders = "SELECT p.padder_id, p.padder_name, po.po_number, v.vendor_name
                   FROM pad_m_padder p
                   INNER JOIN pad_t_purchase po ON p.padder_id = po.padder_id
                   LEFT JOIN pad_m_vendor v ON po.vendor_id = v.vendor_id
                   WHERE p.status = 'DIBELI'
                   AND p.padder_id NOT IN (SELECT padder_id FROM pad_t_receive)
                   ORDER BY p.padder_id";
    $stmtPadders = sqlsrv_query($conn, $sqlPadders);
    $data['padders'] = [];
    while ($row = sqlsrv_fetch_array($stmtPadders, SQLSRV_FETCH_ASSOC)) {
        $data['padders'][] = $row;
    }
    if ($stmtPadders !== false) sqlsrv_free_stmt($stmtPadders);
    
    return $data;
}

$dropdownData = getDropdownData($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $padderId = trim($_POST['padder_id'] ?? '');
        $receiveDate = trim($_POST['receive_date'] ?? '');
        $grnNumber = trim($_POST['grn_number'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $createdBy = $_SESSION['UserName'];
        
        // Validation
        if (empty($padderId)) {
            throw new Exception("Padder harus dipilih!");
        }
        
        if (empty($receiveDate)) {
            throw new Exception("Tanggal penerimaan harus diisi!");
        }
        
        if (empty($grnNumber)) {
            throw new Exception("Nomor GRN harus diisi!");
        }
        
        // Cek status padder
        $sqlStatus = "SELECT status FROM pad_m_padder WHERE padder_id = ?";
        $stmtStatus = sqlsrv_query($conn, $sqlStatus, [$padderId]);
        $rowStatus = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC);
        if (!$rowStatus || $rowStatus['status'] != 'DIBELI') {
            throw new Exception("Status padder tidak valid untuk diterima! Harus dalam status DIBELI.");
        }
        
        // Cek apakah padder sudah pernah diterima
        $sqlCheck = "SELECT COUNT(*) as count FROM pad_t_receive WHERE padder_id = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$padderId]);
        $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        if ($rowCheck['count'] > 0) {
            throw new Exception("Padder ini sudah pernah diterima!");
        }
        
        // Handle file uploads
$uploadedFiles = [];
if (!empty($_FILES['receive_photos']['name'][0])) {
    $uploadDir = __DIR__ . '/../../uploads/receive/';
    
    // Create directory if not exists dengan permission yang benar
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Gagal membuat directory upload: " . $uploadDir);
        }
    }
    
    // Cek apakah directory writable
    if (!is_writable($uploadDir)) {
        throw new Exception("Directory upload tidak writable: " . $uploadDir);
    }
    
    foreach ($_FILES['receive_photos']['name'] as $key => $name) {
        if ($_FILES['receive_photos']['error'][$key] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['receive_photos']['tmp_name'][$key];
            $fileExtension = pathinfo($name, PATHINFO_EXTENSION);
            
            // Generate safe filename
            $safeFileName = 'GRN_' . preg_replace('/[^a-zA-Z0-9]/', '_', $grnNumber) . '_' . uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $safeFileName;
            
            // Debug: log path untuk troubleshooting
            error_log("Upload attempt: " . $tmpName . " to " . $filePath);
            
            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array(strtolower($fileExtension), $allowedTypes)) {
                throw new Exception("File type tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, WEBP yang diperbolehkan.");
            }
            
            // Validate file size (max 5MB)
            if ($_FILES['receive_photos']['size'][$key] > 5 * 1024 * 1024) {
                throw new Exception("File terlalu besar. Maksimal 5MB per file.");
            }
            
            // Cek apakah file temporary ada
            if (!file_exists($tmpName)) {
                throw new Exception("File temporary tidak ditemukan: " . $tmpName);
            }
            
            if (move_uploaded_file($tmpName, $filePath)) {
                // Konversi path untuk disimpan di database (relative path)
                $relativePath = '/gg_app/uploads/receive/' . $safeFileName;
                $uploadedFiles[] = [
                    'name' => $safeFileName,
                    'path' => $relativePath,
                    'type' => $_FILES['receive_photos']['type'][$key]
                ];
            } else {
                $error = error_get_last();
                throw new Exception("Gagal upload file: " . $name . " - Error: " . ($error['message'] ?? 'Unknown error'));
            }
        } elseif ($_FILES['receive_photos']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
            // Handle upload errors
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder tidak ada',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension PHP'
            ];
            throw new Exception("Upload error: " . ($uploadErrors[$_FILES['receive_photos']['error'][$key]] ?? 'Unknown error'));
        }
    }
}
        
        // Begin transaction
        sqlsrv_begin_transaction($conn);
        
        // Insert receive data
        $sqlInsert = "INSERT INTO pad_t_receive 
                     (padder_id, receive_date, grn_number, remarks) 
                     VALUES (?, ?, ?, ?)";
        $paramsInsert = [
            $padderId, 
            $receiveDate, 
            $grnNumber, 
            $remarks
        ];
        
        $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
        
        if ($stmtInsert === false) {
            throw new Exception("Gagal menyimpan data penerimaan: " . print_r(sqlsrv_errors(), true));
        }
        
        // Get the inserted receive ID
        $receiveId = null;
        $sqlGetId = "SELECT IDENT_CURRENT('pad_t_receive') as receive_id";
        $stmtGetId = sqlsrv_query($conn, $sqlGetId);
        if ($stmtGetId && $row = sqlsrv_fetch_array($stmtGetId, SQLSRV_FETCH_ASSOC)) {
            $receiveId = $row['receive_id'];
        }
        
        // Insert file records
        if ($receiveId && !empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                $sqlFile = "INSERT INTO pad_t_receive_files 
                           (receive_id, file_path, file_type) 
                           VALUES (?, ?, ?)";
                sqlsrv_query($conn, $sqlFile, [$receiveId, $file['path'], $file['type']]);
            }
        }
        
        // Update padder status to READY
        $sqlUpdatePadder = "UPDATE pad_m_padder SET status = 'READY' WHERE padder_id = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdatePadder, [$padderId]);
        
        if ($stmtUpdate === false) {
            throw new Exception("Gagal update status padder!");
        }
        
        // Log status change
        $sqlLog = "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) 
                  VALUES (?, 'READY', ?, ?)";
        $logRemarks = "Penerimaan barang : " . $grnNumber . " - " . $remarks;
        sqlsrv_query($conn, $sqlLog, [$padderId, $createdBy, $logRemarks]);
        
        // Commit transaction
        sqlsrv_commit($conn);
        
        $_SESSION['success'] = "Penerimaan barang berhasil! Padder " . $padderId . " sekarang status READY.";
        header('Location: receive_list.php?id=' . $padderId);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            sqlsrv_rollback($conn);
        }
        
        // Clean up uploaded files if any
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                if (file_exists(__DIR__ . '/../../' . $file['path'])) {
                    unlink(__DIR__ . '/../../' . $file['path']);
                }
            }
        }
        
        $_SESSION['error'] = $e->getMessage();
        
        // Store POST data for form repopulation
        $formData = [
            'padder_id' => $_POST['padder_id'] ?? '',
            'receive_date' => $_POST['receive_date'] ?? '',
            'grn_number' => $_POST['grn_number'] ?? '',
            'remarks' => $_POST['remarks'] ?? ''
        ];
    }
}

// Include layout parts
include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penerimaan Barang - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2-bootstrap4.min.css">
    <style>
        .required-label::after { content: " *"; color: #dc3545; }
        .file-input-container { position: relative; }
        .file-preview { margin-top: 10px; }
        .file-preview-item {
            display: inline-block;
            margin: 5px;
            text-align: center;
        }
        .file-preview-img {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .file-preview-name {
            font-size: 0.8em;
            word-break: break-all;
        }
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            background: #e9ecef;
            border-color: #0056b3;
        }
        .upload-area.dragover {
            background: #d4edda;
            border-color: #28a745;
        }
        .padder-info-card { border-left: 4px solid #28a745; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">
                            Penerimaan Barang (GRN)
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="/gg_app/pages/padder/receive_list.php">Penerimaan Padder</a></li>                         
                            <li class="breadcrumb-item active">Input Penerimaan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title">                                  
                                    Form Penerimaan Barang
                                </h3>
                                <div class="card-tools">
                                    <a href="receive_list.php" class="btn btn-info btn-sm">
                                        <i class="fas fa-list mr-1"></i> Riwayat Penerimaan
                                    </a>                                
                                </div>
                            </div>

                            <form method="POST" id="receiveForm" enctype="multipart/form-data">
                                <div class="card-body">
                                    <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        <i class="fas fa-exclamation-triangle mr-2"></i> 
                                        <?= htmlspecialchars($_SESSION['error']) ?>
                                        <?php unset($_SESSION['error']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <!-- Left Column - Receive Data -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">Data Penerimaan</h3>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Padder Selection -->
                                                    <div class="form-group">
                                                        <label for="padder_id" class="required-label">Pilih Padder</label>
                                                        <select class="form-control select2" id="padder_id" name="padder_id" 
                                                                required style="width: 100%;" 
                                                                data-placeholder="Pilih padder...">
                                                            <option value=""></option>
                                                            <?php foreach ($dropdownData['padders'] as $padder): ?>
                                                                <option value="<?= $padder['padder_id'] ?>" 
                                                                        <?= isset($formData['padder_id']) && $formData['padder_id'] == $padder['padder_id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                                                    (PO: <?= htmlspecialchars($padder['po_number']) ?> - <?= htmlspecialchars($padder['vendor_name']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="form-text text-muted">
                                                            Pilih padder yang akan diterima (hanya padder dengan status "DIBELI" yang tersedia)
                                                        </small>
                                                    </div>

                                                    <!-- Receive Date -->
                                                    <div class="form-group">
                                                        <label for="receive_date" class="required-label">Tanggal Penerimaan</label>
                                                        <input type="date" class="form-control" id="receive_date" 
                                                               name="receive_date" 
                                                               value="<?= isset($formData['receive_date']) ? $formData['receive_date'] : date('Y-m-d') ?>" 
                                                               required>
                                                    </div>

                                                    <!-- GRN Number -->
                                                    <div class="form-group">
                                                        <label for="grn_number" class="required-label">Nomor GRN</label>
                                                        <input type="text" class="form-control" id="grn_number" 
                                                               name="grn_number" 
                                                               value="<?= isset($formData['grn_number']) ? htmlspecialchars($formData['grn_number']) : '' ?>" 
                                                               required maxlength="50" 
                                                               placeholder="Masukkan nomor GRN...">
                                                        <small class="form-text text-muted">
                                                            Contoh: GRN/PD/2024/001
                                                        </small>
                                                    </div>

                                                    <!-- Remarks -->
                                                    <div class="form-group">
                                                        <label for="remarks">Keterangan Penerimaan</label>
                                                        <textarea class="form-control" id="remarks" name="remarks" 
                                                                  rows="3" placeholder="Keterangan tambahan penerimaan..."
                                                                  maxlength="255"><?= isset($formData['remarks']) ? htmlspecialchars($formData['remarks']) : '' ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column - Photo Upload & Information -->
                                        <div class="col-md-6">
                                            <!-- Photo Upload -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-camera mr-2"></i>
                                                        Foto Penerimaan
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <!-- File Upload Area -->
                                                    <div class="upload-area" id="uploadArea">
                                                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                                        <h5>Drop files here or click to upload</h5>
                                                        <p class="text-muted">Upload foto penerimaan barang (Max 5MB per file, JPG, PNG, GIF, WEBP)</p>
                                                        <input type="file" id="receive_photos" name="receive_photos[]" 
                                                               multiple accept="image/*" style="display: none;">
                                                    </div>

                                                    <!-- File Preview -->
                                                    <div class="file-preview" id="filePreview"></div>

                                                    <!-- Upload Info -->
                                                    <div class="mt-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle mr-1"></i>
                                                            Foto akan disimpan sebagai dokumentasi penerimaan barang
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Selected Padder Info -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        Informasi Padder
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <div id="padderInfo" class="text-center text-muted py-4">
                                                        <i class="fas fa-cube fa-2x mb-2"></i><br>
                                                        Pilih padder untuk melihat informasi
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan </button>
                                    <a href="receive_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
<script src="/gg_app/plugins/js/select2.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
// Padder data for dynamic display
const padderData = {
    <?php foreach ($dropdownData['padders'] as $padder): ?>
    "<?= $padder['padder_id'] ?>": <?= json_encode($padder) ?>,
    <?php endforeach; ?>
};

(function(){
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: function() {
            return $(this).data('placeholder');
        },
        allowClear: true
    });

    // File upload functionality
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('receive_photos');
    const filePreview = document.getElementById('filePreview');

    // Click to upload
    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });

    // Drag and drop functionality
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => {
            uploadArea.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => {
            uploadArea.classList.remove('dragover');
        }, false);
    });

    uploadArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    // File input change
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        filePreview.innerHTML = '';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            if (!file.type.match('image.*')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Type Error',
                    text: 'Hanya file gambar yang diperbolehkan!',
                    confirmButtonColor: '#ffc107'
                });
                continue;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: 'File ' + file.name + ' terlalu besar. Maksimal 5MB!',
                    confirmButtonColor: '#ffc107'
                });
                continue;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'file-preview-item';
                previewItem.innerHTML = `
                    <img src="${e.target.result}" class="file-preview-img" alt="Preview">
                    <div class="file-preview-name">${file.name}</div>
                `;
                filePreview.appendChild(previewItem);
            };
            
            reader.readAsDataURL(file);
        }
    }

    // Update padder info when selection changes
    $('#padder_id').on('change', function() {
        const padderId = $(this).val();
        const infoContainer = $('#padderInfo');
        
        if (padderId && padderData[padderId]) {
            const padder = padderData[padderId];
            
            infoContainer.html(`
                <div class="text-left">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%">Padder ID</th>
                            <td><strong class="text-primary">${padder.padder_id}</strong></td>
                        </tr>
                        <tr>
                            <th>Nama Padder</th>
                            <td>${padder.padder_name}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><span class="badge badge-warning">DIBELI</span></td>
                        </tr>
                        <tr>
                            <th>Nomor PO</th>
                            <td>${padder.po_number}</td>
                        </tr>
                        <tr>
                            <th>Vendor</th>
                            <td>${padder.vendor_name}</td>
                        </tr>
                    </table>
                </div>
            `);
        } else {
            infoContainer.html(`
                <div class="text-center text-muted py-4">
                    <i class="fas fa-cube fa-2x mb-2"></i><br>
                    Pilih padder untuk melihat informasi
                </div>
            `);
        }
    });

    // Form validation and submission
    $('#receiveForm').on('submit', function(e) {
        e.preventDefault();
        
        const padderId = $('#padder_id').val();
        const grnNumber = $('#grn_number').val().trim();
        const receiveDate = $('#receive_date').val();
        const fileCount = fileInput.files.length;
        
        // Basic validation
        if (!padderId) {
            Swal.fire({
                icon: 'warning',
                title: 'Padder Belum Dipilih',
                text: 'Silakan pilih padder terlebih dahulu!',
                confirmButtonColor: '#ffc107'
            });
            $('#padder_id').focus();
            return false;
        }
        
        if (!grnNumber) {
            Swal.fire({
                icon: 'warning',
                title: 'Nomor GRN Kosong',
                text: 'Silakan masukkan nomor GRN!',
                confirmButtonColor: '#ffc107'
            });
            $('#grn_number').focus();
            return false;
        }
        
        if (!receiveDate) {
            Swal.fire({
                icon: 'warning',
                title: 'Tanggal Belum Dipilih',
                text: 'Silakan pilih tanggal penerimaan!',
                confirmButtonColor: '#ffc107'
            });
            $('#receive_date').focus();
            return false;
        }
        
        // Get form data for confirmation
        const padderName = padderData[padderId] ? padderData[padderId].padder_name : '';
        const poNumber = padderData[padderId] ? padderData[padderId].po_number : '';
        
        // Confirmation dialog
        Swal.fire({
            title: 'Konfirmasi Penerimaan Barang?',
            html: `<div class="text-left">
                <strong>Padder:</strong> ${padderId} - ${padderName}<br>
                <strong>Nomor PO:</strong> ${poNumber}<br>
                <strong>Nomor GRN:</strong> ${grnNumber}<br>
                <strong>Tanggal:</strong> ${receiveDate}<br>
                <strong>Foto:</strong> ${fileCount} file<br>
                <div class="alert alert-warning mt-2 small">
                    <i class="fas fa-info-circle mr-1"></i>
                    Status padder akan berubah menjadi "READY"
                </div>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '<?= $themeColor === 'primary' ? '#007bff' : $themeColor ?>',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check mr-2"></i> Ya, Konfirmasi',
            cancelButtonText: '<i class="fas fa-times mr-2"></i> Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Menyimpan...',
                    text: 'Sedang menyimpan data penerimaan',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit form
                $('#receiveForm').off('submit').submit();
            }
        });
    });

    // Initialize padder info display
    $(document).ready(function() {
        const padderId = $('#padder_id').val();
        if (padderId) {
            $('#padder_id').trigger('change');
        }
        
        // Focus on first field
        $('#padder_id').select2('focus');
    });

})();
</script>

</body>
</html>

<?php 
include '../../includes/footer.php'; 
?>
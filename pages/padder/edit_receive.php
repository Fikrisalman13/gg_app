<?php
// edit_penerimaan.php - Form Edit Penerimaan Padder
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

// Cek permission untuk edit data
function checkEditPermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanEdit' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $result;
}

$permissions = checkEditPermission($conn, $_SESSION['GroupId'], 114);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data penerimaan.";
    header('Location: receive_list.php');
    exit;
}

// ====== Get Receive Data ======
$id = $_GET['id'] ?? '';
if (empty($id)) {
    $_SESSION['error'] = "ID penerimaan tidak valid!";
    header('Location: receive_list.php');
    exit;
}

// Ambil data penerimaan
$sql = "SELECT r.*, p.padder_name, p.status as padder_status
        FROM dbo.pad_t_receive r
        LEFT JOIN dbo.pad_m_padder p ON r.padder_id = p.padder_id
        WHERE r.id = ?";

$stmt = sqlsrv_query($conn, $sql, [$id]);
$receiveData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$receiveData) {
    $_SESSION['error'] = "Data penerimaan tidak ditemukan!";
    header('Location: receive_list.php');
    exit;
}

// Format tanggal untuk input
if ($receiveData['receive_date']) {
    $receiveData['receive_date'] = $receiveData['receive_date']->format('Y-m-d');
}

// Ambil foto yang sudah ada
$existingPhotos = [];
$photoSql = "SELECT id, file_path, file_type 
            FROM dbo.pad_t_receive_files 
            WHERE receive_id = ? 
            ORDER BY uploaded_at";
$photoStmt = sqlsrv_query($conn, $photoSql, [$id]);

if ($photoStmt !== false) {
    while ($photo = sqlsrv_fetch_array($photoStmt, SQLSRV_FETCH_ASSOC)) {
        // Normalize file path
        $filePath = $photo['file_path'];
        if (!empty($filePath) && $filePath[0] !== '/') {
            $filePath = '/' . $filePath;
        }
        $filePath = str_replace('//', '/', $filePath);
        $photo['file_path'] = $filePath;
        
        $existingPhotos[] = $photo;
    }
    sqlsrv_free_stmt($photoStmt);
}

// ====== Get Dropdown Data ======
function getDropdownData($conn, $currentPadderId) {
    $data = [];
    
    // Get padder list with purchase status
    $sqlPadders = "SELECT 
                    p.padder_id, 
                    p.padder_name,
                    p.status as padder_status,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM dbo.pad_t_purchase pur 
                        WHERE pur.padder_id = p.padder_id
                    ) THEN 1 ELSE 0 END as has_purchase
                FROM dbo.pad_m_padder p
                ORDER BY p.padder_id";
    $stmtPadders = sqlsrv_query($conn, $sqlPadders);
    $data['padders'] = [];
    while ($row = sqlsrv_fetch_array($stmtPadders, SQLSRV_FETCH_ASSOC)) {
        $data['padders'][] = $row;
    }
    if ($stmtPadders !== false) sqlsrv_free_stmt($stmtPadders);
    
    // Get current padder data dengan spesifikasi
    $sqlPadder = "SELECT p.padder_id, p.padder_name, p.created_at, p.remarks, p.status
                  FROM pad_m_padder p
                  WHERE p.padder_id = ?";
    $stmtPadder = sqlsrv_query($conn, $sqlPadder, [$currentPadderId]);
    $data['current_padder'] = sqlsrv_fetch_array($stmtPadder, SQLSRV_FETCH_ASSOC);
    
    if ($data['current_padder']) {
        // Get spesifikasi untuk padder
        $sqlSpec = "SELECT spec_name, spec_value 
                    FROM pad_m_padder_spec 
                    WHERE padder_id = ? 
                    ORDER BY id";
        $stmtSpec = sqlsrv_query($conn, $sqlSpec, [$currentPadderId]);
        $data['current_padder']['specifications'] = [];
        while ($spec = sqlsrv_fetch_array($stmtSpec, SQLSRV_FETCH_ASSOC)) {
            $data['current_padder']['specifications'][] = $spec;
        }
        if ($stmtSpec !== false) sqlsrv_free_stmt($stmtSpec);
    }
    
    return $data;
}

$dropdownData = getDropdownData($conn, $receiveData['padder_id']);

// ====== Process Form Submission ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $padder_id = trim($_POST['padder_id'] ?? '');
        $receive_date = trim($_POST['receive_date'] ?? '');
        $grn_number = trim($_POST['grn_number'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $delete_photos = $_POST['delete_photos'] ?? [];
        
        // Validation
        if (empty($padder_id)) {
            throw new Exception("Padder harus dipilih!");
        }
        
        if (empty($receive_date)) {
            throw new Exception("Tanggal penerimaan harus diisi!");
        }
        
        // Validasi: Cek apakah padder sudah ada transaksi pembelian
        $hasPurchase = false;
        foreach ($dropdownData['padders'] as $padder) {
            if ($padder['padder_id'] == $padder_id && $padder['has_purchase'] == 1) {
                $hasPurchase = true;
                break;
            }
        }
        
        if (!$hasPurchase) {
            throw new Exception("Padder ini belum memiliki transaksi pembelian. Tidak dapat melakukan penerimaan.");
        }
        
        // Begin transaction
        sqlsrv_begin_transaction($conn);
        
        // Update data penerimaan
        $sql = "UPDATE dbo.pad_t_receive 
                SET padder_id = ?, receive_date = ?, grn_number = ?, remarks = ?
                WHERE id = ?";
        
        $params = [
            $padder_id,
            $receive_date,
            $grn_number,
            $remarks,
            $id
        ];
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Gagal mengupdate data penerimaan: " . print_r(sqlsrv_errors(), true));
        }
        
        // Delete selected photos
        if (!empty($delete_photos)) {
            foreach ($delete_photos as $photoId) {
                // Get file path first
                $getPhotoSql = "SELECT file_path FROM dbo.pad_t_receive_files WHERE id = ?";
                $getStmt = sqlsrv_query($conn, $getPhotoSql, [$photoId]);
                if ($getStmt !== false && $photoRow = sqlsrv_fetch_array($getStmt, SQLSRV_FETCH_ASSOC)) {
                    // Delete physical file
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . $photoRow['file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                if ($getStmt) sqlsrv_free_stmt($getStmt);
                
                // Delete database record
                $deleteSql = "DELETE FROM dbo.pad_t_receive_files WHERE id = ?";
                $deleteStmt = sqlsrv_query($conn, $deleteSql, [$photoId]);
                if ($deleteStmt === false) {
                    throw new Exception("Gagal menghapus foto: " . print_r(sqlsrv_errors(), true));
                }
                sqlsrv_free_stmt($deleteStmt);
            }
        }
        
        // Process new file uploads
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/penerimaan/';
            
            // Create directory if not exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Calculate total photos after operations
            $remainingExisting = count($existingPhotos) - count($delete_photos);
            $newPhotosCount = count($_FILES['photos']['name']);
            $totalPhotos = $remainingExisting + $newPhotosCount;
            
            if ($totalPhotos > 10) {
                throw new Exception("Total foto tidak boleh lebih dari 10. Saat ini: {$remainingExisting} foto existing + {$newPhotosCount} foto baru = {$totalPhotos}");
            }
            
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    // Validasi ukuran file (5MB)
                    if ($_FILES['photos']['size'][$key] > 5 * 1024 * 1024) {
                        throw new Exception("File \"{$_FILES['photos']['name'][$key]}\" melebihi 5MB");
                    }
                    
                    $fileName = uniqid() . '_' . basename($_FILES['photos']['name'][$key]);
                    $filePath = $uploadDir . $fileName;
                    $relativePath = '/gg_app/uploads/penerimaan/' . $fileName;
                    
                    if (move_uploaded_file($tmp_name, $filePath)) {
                        // Insert file record
                        $fileSql = "INSERT INTO dbo.pad_t_receive_files (receive_id, file_path, file_type) 
                                   VALUES (?, ?, ?)";
                        $fileParams = [
                            $id,
                            $relativePath,
                            'photo'
                        ];
                        
                        $fileStmt = sqlsrv_query($conn, $fileSql, $fileParams);
                        if ($fileStmt === false) {
                            throw new Exception("Gagal menyimpan data foto: " . print_r(sqlsrv_errors(), true));
                        }
                        sqlsrv_free_stmt($fileStmt);
                    } else {
                        throw new Exception("Gagal mengupload file: " . $_FILES['photos']['name'][$key]);
                    }
                }
            }
        }
        
        // Log the update
        $sqlLog = "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) 
                  VALUES (?, ?, ?, ?)";
        $logRemarks = "Update Penerimaan: " . ($grn_number ? "GRN {$grn_number}" : "Tanpa GRN") . " - " . $remarks;
        sqlsrv_query($conn, $sqlLog, [$padder_id, $receiveData['padder_status'], $_SESSION['UserName'], $logRemarks]);
        
        // Commit transaction
        sqlsrv_commit($conn);
        
        $_SESSION['success'] = "Data penerimaan berhasil diupdate!";
        header('Location: receive_list.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            sqlsrv_rollback($conn);
        }
        $_SESSION['error'] = $e->getMessage();
        header("Location: edit_receive.php?id=" . $id);
        exit;
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
    <title>Edit Penerimaan - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2-bootstrap4.min.css">
    <style>
        .required-label::after { content: " *"; color: #dc3545; }
        .padder-info-card { border-left: 4px solid #28a745; }
        .photo-info-card { border-left: 4px solid #ffc107; }
        .select2-container--bootstrap4 .select2-selection--single { height: calc(2.25rem + 2px); }
        .spec-item { 
            padding: 4px 8px; 
            margin: 2px 0; 
            background: #f8f9fa; 
            border-radius: 4px;
            font-size: 0.85em;
        }
        .padder-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .purchase-status {
            font-size: 0.75em;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .status-has-purchase {
            background-color: #28a745;
            color: white;
        }
        .status-no-purchase {
            background-color: #6c757d;
            color: white;
        }
        .photo-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .photo-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
        }
        .photo-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
        }
        .photo-preview-item .remove-photo {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        .existing-photo-item {
            position: relative;
            width: 100px;
            height: 100px;
            border: 2px solid #28a745;
        }
        .existing-photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
        }
        .existing-photo-item .remove-existing {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        .file-input-label {
            cursor: pointer;
            padding: 15px;
            border: 2px dashed #dee2e6;
            border-radius: 4px;
            text-align: center;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }
        .file-input-label:hover {
            border-color: #007bff;
            background-color: #e9ecef;
        }
        .photo-to-delete {
            opacity: 0.5;
            border-color: #dc3545 !important;
        }
        .photo-count {
            font-size: 0.85em;
            color: #6c757d;
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
                        <h1 class="m-0 text-dark">
                            Edit Penerimaan Padder
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="receive_list.php">Daftar Penerimaan</a></li>
                            <li class="breadcrumb-item active">Edit Penerimaan</li>
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
                                    Form Edit Penerimaan
                                </h3>
                                <div class="card-tools">
                                    <a href="receive_list.php" class="btn btn-info btn-sm">
                                        <i class="fas fa-list mr-1"></i> Lihat Daftar
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
                                                                        <?= $receiveData['padder_id'] == $padder['padder_id'] ? 'selected' : '' ?>
                                                                        data-has-purchase="<?= $padder['has_purchase'] ?>">
                                                                    <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                                                    (<?= $padder['has_purchase'] ? 'Sudah Pembelian' : 'Belum Pembelian' ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small id="purchaseStatusMessage" class="form-text"></small>
                                                    </div>

                                                    <!-- Receive Date -->
                                                    <div class="form-group">
                                                        <label for="receive_date" class="required-label">Tanggal Penerimaan</label>
                                                        <input type="date" class="form-control" id="receive_date" 
                                                               name="receive_date" 
                                                               value="<?= htmlspecialchars($receiveData['receive_date']) ?>" 
                                                               required>
                                                    </div>

                                                    <!-- GRN Number -->
                                                    <div class="form-group">
                                                        <label for="grn_number">GRN Number</label>
                                                        <input type="text" class="form-control" id="grn_number" 
                                                               name="grn_number" 
                                                               value="<?= htmlspecialchars($receiveData['grn_number'] ?? '') ?>" 
                                                               maxlength="50" 
                                                               placeholder="Masukkan GRN Number...">
                                                        <small class="form-text text-muted">
                                                            Contoh: GRN/PD/2024/001
                                                        </small>
                                                    </div>

                                                    <!-- Remarks -->
                                                    <div class="form-group">
                                                        <label for="remarks">Keterangan</label>
                                                        <textarea class="form-control" id="remarks" name="remarks" 
                                                                  rows="3" placeholder="Keterangan tambahan..."
                                                                  maxlength="255"><?= htmlspecialchars($receiveData['remarks'] ?? '') ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column - Information -->
                                        <div class="col-md-6">
                                            <!-- Padder Info -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        Informasi Padder
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <?php if ($dropdownData['current_padder']): ?>
                                                        <?php 
                                                        $padder = $dropdownData['current_padder'];
                                                        $createdDate = $padder['created_at'] ? $padder['created_at']->format('d/m/Y') : '-';
                                                        ?>
                                                        <div class="text-left">
                                                            <table class="table table-sm table-borderless mb-2">
                                                                <tr>
                                                                    <th width="40%">Padder ID</th>
                                                                    <td><strong class="text-primary"><?= htmlspecialchars($padder['padder_id']) ?></strong></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Nama Padder</th>
                                                                    <td><?= htmlspecialchars($padder['padder_name']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Status</th>
                                                                    <td>
                                                                        <span class="badge badge-<?= 
                                                                            $padder['status'] == 'DITERIMA' ? 'success' : 
                                                                            ($padder['status'] == 'DIBELI' ? 'warning' : 'secondary')
                                                                        ?>">
                                                                            <?= $padder['status'] ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Dibuat</th>
                                                                    <td><?= $createdDate ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Keterangan</th>
                                                                    <td><?= htmlspecialchars($padder['remarks'] ?? '-') ?></td>
                                                                </tr>
                                                            </table>
                                                            
                                                            <?php if (!empty($padder['specifications'])): ?>
                                                                <div class="mt-3">
                                                                    <strong>Spesifikasi:</strong>
                                                                    <div class="mt-1">
                                                                        <?php foreach ($padder['specifications'] as $spec): ?>
                                                                            <div class="spec-item">
                                                                                <strong><?= htmlspecialchars($spec['spec_name']) ?>:</strong> <?= htmlspecialchars($spec['spec_value']) ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="mt-2">
                                                                    <small class="text-muted">
                                                                        <i class="fas fa-info-circle mr-1"></i>
                                                                        Tidak ada spesifikasi
                                                                    </small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center text-muted py-4">
                                                            <i class="fas fa-cube fa-2x mb-2"></i><br>
                                                            Data padder tidak ditemukan
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Photo Info -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-camera mr-2"></i>
                                                        Foto Penerimaan
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Existing Photos -->
                                                    <?php if (!empty($existingPhotos)): ?>
                                                        <div class="mb-3">
                                                            <label class="text-success"><small><strong>Foto yang sudah ada:</strong></small></label>
                                                            <div class="photo-preview" id="existingPhotos">
                                                                <?php foreach ($existingPhotos as $photo): ?>
                                                                    <div class="existing-photo-item" id="photo-<?= $photo['id'] ?>">
                                                                        <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                                                             alt="Existing Photo" 
                                                                             onclick="viewPhoto('<?= htmlspecialchars($photo['file_path']) ?>')">
                                                                        <div class="remove-existing" 
                                                                             onclick="markPhotoForDeletion(<?= $photo['id'] ?>)">
                                                                            <i class="fas fa-times"></i>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- New Photos Upload -->
                                                    <div class="mb-3">
                                                        <label><strong>Tambah Foto Baru:</strong></label>
                                                        <div class="file-input-label" onclick="document.getElementById('photos').click()">
                                                            <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
                                                            <p class="mb-1">Klik untuk menambah foto baru</p>
                                                            <small class="text-muted">Format: JPG, PNG, GIF (Maks. 5MB per file)</small>
                                                        </div>
                                                        <input type="file" id="photos" name="photos[]" multiple 
                                                               accept="image/*" style="display: none;" 
                                                               onchange="previewPhotos(this)">
                                                        
                                                        <div id="photoPreview" class="photo-preview mt-2"></div>
                                                        <div class="photo-count mt-1" id="photoCount">
                                                            Total foto: <?= count($existingPhotos) ?> (Maksimal 10 foto)
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>">
                                        <i class="fas fa-save"></i> Simpan Perubahan
                                    </button>
                                    <a href="receive_list.php" class="btn btn-secondary">
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

<!-- Modal View Photo -->
<div class="modal fade" id="viewPhotoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Foto Penerimaan</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="viewPhotoImg" src="" class="img-fluid" alt="Foto Penerimaan">
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
<script src="/gg_app/plugins/js/select2.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
// Global variables
const existingPhotosCount = <?= count($existingPhotos) ?>;
let photosToDelete = [];
let newPhotosCount = 0;

(function(){
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: function() {
            return $(this).data('placeholder');
        },
        allowClear: true
    });

    // Check purchase status when padder selection changes
    function checkPurchaseStatus(padderId) {
        const selectedOption = document.querySelector(`#padder_id option[value="${padderId}"]`);
        const statusMessage = document.getElementById('purchaseStatusMessage');
        const submitBtn = document.querySelector('button[type="submit"]');
        
        if (selectedOption) {
            const hasPurchase = selectedOption.getAttribute('data-has-purchase') === '1';
            
            if (hasPurchase) {
                statusMessage.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Padder ini sudah memiliki transaksi pembelian. Dapat dilakukan penerimaan.</span>';
                if (submitBtn) submitBtn.disabled = false;
            } else {
                statusMessage.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Padder ini belum memiliki transaksi pembelian. Tidak dapat dilakukan penerimaan.</span>';
                if (submitBtn) submitBtn.disabled = true;
            }
        } else {
            statusMessage.innerHTML = '';
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    // View photo in modal
    function viewPhoto(src) {
        $('#viewPhotoImg').attr('src', src);
        $('#viewPhotoModal').modal('show');
    }

    // Mark photo for deletion
    function markPhotoForDeletion(photoId) {
        if (!photosToDelete.includes(photoId)) {
            photosToDelete.push(photoId);
            
            // Create hidden input for deletion
            let deleteInput = document.querySelector(`input[name="delete_photos[]"][value="${photoId}"]`);
            if (!deleteInput) {
                deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_photos[]';
                deleteInput.value = photoId;
                document.getElementById('receiveForm').appendChild(deleteInput);
            }
            
            // Mark visually as to be deleted
            const photoElement = document.getElementById(`photo-${photoId}`);
            if (photoElement) {
                photoElement.classList.add('photo-to-delete');
            }
            
            updatePhotoCounts();
        }
    }

    // Preview new photos
    function previewPhotos(input) {
        const preview = document.getElementById('photoPreview');
        
        // Calculate total photos
        const remainingExisting = existingPhotosCount - photosToDelete.length;
        newPhotosCount = input.files ? input.files.length : 0;
        const totalPhotos = remainingExisting + newPhotosCount;
        
        if (totalPhotos > 10) {
            Swal.fire({
                icon: 'error',
                title: 'Terlalu banyak foto',
                text: `Total foto tidak boleh lebih dari 10. Saat ini: ${remainingExisting} foto existing + ${newPhotosCount} foto baru = ${totalPhotos}`,
                timer: 3000,
                showConfirmButton: false
            });
            input.value = '';
            newPhotosCount = 0;
            updatePhotoCounts();
            return;
        }
        
        preview.innerHTML = '';
        
        if (input.files && input.files.length > 0) {
            Array.from(input.files).forEach((file, index) => {
                // Validasi ukuran file (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File terlalu besar',
                        text: `File "${file.name}" melebihi 5MB`,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'photo-preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <div class="remove-photo" onclick="removePhoto(${index})">
                            <i class="fas fa-times"></i>
                        </div>
                    `;
                    preview.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
        }
        
        updatePhotoCounts();
    }

    // Remove photo from preview
    function removePhoto(index) {
        // Remove from preview
        const previewItems = document.querySelectorAll('.photo-preview-item');
        if (previewItems[index]) {
            previewItems[index].remove();
        }
        
        // Remove from file input
        const fileInput = document.getElementById('photos');
        const dt = new DataTransfer();
        
        Array.from(fileInput.files).forEach((file, i) => {
            if (i !== index) {
                dt.items.add(file);
            }
        });
        
        fileInput.files = dt.files;
        newPhotosCount = fileInput.files.length;
        
        updatePhotoCounts();
    }

    // Update photo counts display
    function updatePhotoCounts() {
        const remainingExisting = existingPhotosCount - photosToDelete.length;
        const totalPhotos = remainingExisting + newPhotosCount;
        const countElement = document.getElementById('photoCount');
        
        if (countElement) {
            countElement.innerHTML = `Total foto: ${totalPhotos} (${remainingExisting} existing + ${newPhotosCount} baru) - Maksimal 10 foto`;
            
            if (totalPhotos > 10) {
                countElement.className = 'photo-count mt-1 text-danger';
            } else {
                countElement.className = 'photo-count mt-1';
            }
        }
    }

    // Form validation and submission
    $('#receiveForm').on('submit', function(e) {
        e.preventDefault();
        
        const padderId = $('#padder_id').val();
        const receiveDate = $('#receive_date').val();
        const selectedOption = document.querySelector(`#padder_id option[value="${padderId}"]`);
        const hasPurchase = selectedOption ? selectedOption.getAttribute('data-has-purchase') === '1' : false;
        
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
        
        if (!hasPurchase) {
            Swal.fire({
                icon: 'error',
                title: 'Tidak Dapat Melakukan Penerimaan',
                text: 'Padder yang dipilih belum memiliki transaksi pembelian!',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        // Check photo limits
        const remainingExisting = existingPhotosCount - photosToDelete.length;
        const totalPhotos = remainingExisting + newPhotosCount;
        if (totalPhotos > 10) {
            Swal.fire({
                icon: 'error',
                title: 'Terlalu Banyak Foto',
                text: `Total foto tidak boleh lebih dari 10. Saat ini: ${totalPhotos} foto`,
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        // Confirmation dialog
        Swal.fire({
            title: 'Update Data Penerimaan?',
            html: `Anda yakin ingin mengupdate data penerimaan ini?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '<?= $themeColor === 'primary' ? '#007bff' : $themeColor ?>',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-save mr-2"></i> Ya, Update',
            cancelButtonText: '<i class="fas fa-times mr-2"></i> Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit form
                $('#receiveForm').off('submit').submit();
            }
        });
    });

    // Event listeners
    $('#padder_id').on('change', function() {
        checkPurchaseStatus($(this).val());
    });

    // Initialize on page load
    $(document).ready(function() {
        const initialPadderId = $('#padder_id').val();
        if (initialPadderId) {
            checkPurchaseStatus(initialPadderId);
        }
        updatePhotoCounts();
        
        // Focus on first editable field
        $('#padder_id').focus();
    });

    // Make functions global for HTML onclick
    window.checkPurchaseStatus = checkPurchaseStatus;
    window.viewPhoto = viewPhoto;
    window.markPhotoForDeletion = markPhotoForDeletion;
    window.previewPhotos = previewPhotos;
    window.removePhoto = removePhoto;
    window.updatePhotoCounts = updatePhotoCounts;

})();
</script>

</body>
</html>

<?php 
include '../../includes/footer.php'; 
?>
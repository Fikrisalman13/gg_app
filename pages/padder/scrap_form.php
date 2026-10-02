<?php
// scrap_form.php - Form Input Scrap Padder Baru
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

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 118);
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data scrap.";
    header('Location: scrap.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getAvailablePadders($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            WHERE status NOT IN ('SCRAP')
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

$padderList = getAvailablePadders($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $padderId = $_POST['padder_id'] ?? '';
        $scrapDate = $_POST['scrap_date'] ?? '';
        $scrapLocation = $_POST['scrap_location'] ?? '';
        $scrapNotes = $_POST['scrap_notes'] ?? '';

        // Validation
        if (empty($padderId) || empty($scrapDate)) {
            throw new Exception("Padder ID dan Tanggal Scrap wajib diisi!");
        }

        // Check if padder exists and not already scrapped
        $checkSql = "SELECT status FROM dbo.pad_m_padder WHERE padder_id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$padderId]);
        if (!$checkStmt || !sqlsrv_fetch($checkStmt)) {
            throw new Exception("Padder tidak ditemukan!");
        }
        
        $currentStatus = sqlsrv_get_field($checkStmt, 0);
        if ($currentStatus === 'SCRAP') {
            throw new Exception("Padder ini sudah berstatus SCRAP!");
        }
        
        sqlsrv_free_stmt($checkStmt);

        // Begin transaction
        sqlsrv_begin_transaction($conn);

        // Insert scrap record
        $insertScrapSql = "INSERT INTO dbo.pad_t_scrap 
                          (padder_id, scrap_date, scrap_location, scrap_notes) 
                          VALUES (?, ?, ?, ?)";
        $scrapParams = [
            $padderId,
            $scrapDate,
            $scrapLocation,
            $scrapNotes
        ];
        
        $scrapStmt = sqlsrv_query($conn, $insertScrapSql, $scrapParams);
        if (!$scrapStmt) {
            throw new Exception("Gagal menyimpan data scrap!");
        }

        // Get the inserted scrap ID
        $scrapId = null;
        $getIdSql = "SELECT SCOPE_IDENTITY() as scrap_id";
        $getIdStmt = sqlsrv_query($conn, $getIdSql);
        if ($getIdStmt && $row = sqlsrv_fetch_array($getIdStmt, SQLSRV_FETCH_ASSOC)) {
            $scrapId = $row['scrap_id'];
        }
        sqlsrv_free_stmt($getIdStmt);

        // Update padder status to SCRAP
        $updatePadderSql = "UPDATE dbo.pad_m_padder 
                           SET status = 'SCRAP', updated_at = GETDATE(), updated_by = ?
                           WHERE padder_id = ?";
        $updateParams = [$_SESSION['UserName'], $padderId];
        $updateStmt = sqlsrv_query($conn, $updatePadderSql, $updateParams);
        if (!$updateStmt) {
            throw new Exception("Gagal update status padder!");
        }

        // Add status log
        $logSql = "INSERT INTO dbo.pad_status_log 
                  (padder_id, status, changed_by, remarks) 
                  VALUES (?, 'SCRAP', ?, ?)";
        $logParams = [
            $padderId,
            $_SESSION['UserName'],
            "Scrap: " . $scrapNotes
        ];
        $logStmt = sqlsrv_query($conn, $logSql, $logParams);

        // Handle file uploads
        if (!empty($_FILES['scrap_photos']['name'][0])) {
            $uploadDir = __DIR__ . '/../../uploads/scrap/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            foreach ($_FILES['scrap_photos']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['scrap_photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = uniqid() . '_' . basename($_FILES['scrap_photos']['name'][$key]);
                    $filePath = $uploadDir . $fileName;
                    $relativePath = '/gg_app/uploads/scrap/' . $fileName;

                    if (move_uploaded_file($tmpName, $filePath)) {
                        $fileSql = "INSERT INTO dbo.pad_t_scrap_files 
                                   (scrap_id, file_path, file_type, uploaded_at) 
                                   VALUES (?, ?, 'photo', GETDATE())";
                        $fileParams = [$scrapId, $relativePath];
                        sqlsrv_query($conn, $fileSql, $fileParams);
                    }
                }
            }
        }

        // Commit transaction
        sqlsrv_commit($conn);

        $_SESSION['success'] = "Data scrap berhasil disimpan!";
        header('Location: scrap.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Scrap Padder</title>
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
            width: 100px;
            height: 100px;
        }
        .photo-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
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
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Input Scrap Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="scrap.php">Data Scrap</a></li>
                        <li class="breadcrumb-item active">Input Baru</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'info'); ?> text-white">
                    <h3 class="card-title">Form Input Scrap Padder</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form id="scrapForm" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="padder_id">Padder <span class="text-danger">*</span></label>
                                    <select class="form-control" id="padder_id" name="padder_id" required>
                                        <option value="">Pilih Padder</option>
                                        <?php foreach ($padderList as $padder): ?>
                                            <option value="<?= htmlspecialchars($padder['padder_id']) ?>">
                                                <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="scrap_date">Tanggal Scrap <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="scrap_date" name="scrap_date" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="scrap_location">Lokasi Scrap</label>
                                    <input type="text" class="form-control" id="scrap_location" name="scrap_location" 
                                           placeholder="Masukkan lokasi scrap">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scrap_photos">Foto Scrap</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="scrap_photos" 
                                               name="scrap_photos[]" multiple accept="image/*">
                                        <label class="custom-file-label" for="scrap_photos">Pilih file foto</label>
                                    </div>
                                    <small class="form-text text-muted">Dapat memilih multiple file (JPEG, PNG, JPG)</small>
                                    
                                    <div class="photo-preview" id="photoPreview"></div>
                                </div>

                                <div class="form-group">
                                    <label for="scrap_notes">Catatan Scrap</label>
                                    <textarea class="form-control" id="scrap_notes" name="scrap_notes" 
                                              rows="4" placeholder="Masukkan catatan scrap..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Simpan Data Scrap
                            </button>
                            <a href="scrap.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(document).ready(function() {
    // File input preview
    $('#scrap_photos').on('change', function() {
        const preview = $('#photoPreview');
        preview.empty();
        
        const files = this.files;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewItem = $(
                        '<div class="photo-preview-item">' +
                        '<img src="' + e.target.result + '" alt="Preview">' +
                        '<div class="remove-photo">&times;</div>' +
                        '</div>'
                    );
                    
                    previewItem.find('.remove-photo').on('click', function() {
                        previewItem.remove();
                        // Remove file from input
                        const dt = new DataTransfer();
                        const input = document.getElementById('scrap_photos');
                        for (let j = 0; j < input.files.length; j++) {
                            if (j !== i) {
                                dt.items.add(input.files[j]);
                            }
                        }
                        input.files = dt.files;
                    });
                    
                    preview.append(previewItem);
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Update file label
        const fileNames = Array.from(files).map(f => f.name).join(', ');
        $(this).next('.custom-file-label').text(fileNames || 'Pilih file foto');
    });

    // Form validation
    $('#scrapForm').on('submit', function(e) {
        const padderId = $('#padder_id').val();
        const scrapDate = $('#scrap_date').val();
        
        if (!padderId || !scrapDate) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap',
                text: 'Padder dan Tanggal Scrap wajib diisi!',
                confirmButtonColor: '#dc3545'
            });
        }
    });

    // Confirm before submit
    $('#scrapForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Simpan Data Scrap?',
            text: 'Padder akan berstatus SCRAP dan tidak dapat digunakan lagi!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });
});
</script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>
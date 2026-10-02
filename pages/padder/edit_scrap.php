<?php
// edit_scrap.php - Form Edit Data Scrap
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
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data scrap.";
    header('Location: scrap.php');
    exit;
}

// Get scrap ID from URL
$scrapId = $_GET['id'] ?? 0;
if (!$scrapId) {
    $_SESSION['error'] = "ID Scrap tidak valid!";
    header('Location: scrap.php');
    exit;
}

// Get scrap data
function getScrapData($conn, $scrapId) {
    $sql = "SELECT s.*, p.padder_name, p.status as padder_status
            FROM dbo.pad_t_scrap s
            INNER JOIN dbo.pad_m_padder p ON s.padder_id = p.padder_id
            WHERE s.id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$scrapId]);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row;
    }
    return null;
}

// Get scrap photos
function getScrapPhotos($conn, $scrapId) {
    $sql = "SELECT id, file_path, uploaded_at
            FROM dbo.pad_t_scrap_files
            WHERE scrap_id = ?
            ORDER BY uploaded_at";
    $stmt = sqlsrv_query($conn, $sql, [$scrapId]);
    
    $photos = [];
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $photos[] = $row;
    }
    return $photos;
}

// Get original scrap data for comparison
function getOriginalScrapData($conn, $scrapId) {
    $sql = "SELECT scrap_date, scrap_location, scrap_notes 
            FROM dbo.pad_t_scrap 
            WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$scrapId]);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row;
    }
    return null;
}

$scrapData = getScrapData($conn, $scrapId);
if (!$scrapData) {
    $_SESSION['error'] = "Data scrap tidak ditemukan!";
    header('Location: scrap.php');
    exit;
}

$existingPhotos = getScrapPhotos($conn, $scrapId);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $scrapDate = $_POST['scrap_date'] ?? '';
        $scrapLocation = $_POST['scrap_location'] ?? '';
        $scrapNotes = $_POST['scrap_notes'] ?? '';
        $deletePhotos = $_POST['delete_photos'] ?? [];

        // Validation
        if (empty($scrapDate)) {
            throw new Exception("Tanggal Scrap wajib diisi!");
        }

        // Get original data for comparison
        $originalData = getOriginalScrapData($conn, $scrapId);
        if (!$originalData) {
            throw new Exception("Gagal mengambil data asli untuk perbandingan!");
        }

        // Begin transaction
        sqlsrv_begin_transaction($conn);

        // Update scrap record
        $updateScrapSql = "UPDATE dbo.pad_t_scrap 
                          SET scrap_date = ?, scrap_location = ?, scrap_notes = ?
                          WHERE id = ?";
        $scrapParams = [
            $scrapDate,
            $scrapLocation,
            $scrapNotes,
            $scrapId
        ];
        
        $scrapStmt = sqlsrv_query($conn, $updateScrapSql, $scrapParams);
        if (!$scrapStmt) {
            throw new Exception("Gagal update data scrap!");
        }

        // Delete selected photos
        if (!empty($deletePhotos)) {
            $deleteSql = "DELETE FROM dbo.pad_t_scrap_files WHERE id IN (" . 
                        implode(',', array_fill(0, count($deletePhotos), '?')) . ")";
            $deleteStmt = sqlsrv_query($conn, $deleteSql, $deletePhotos);
            if (!$deleteStmt) {
                throw new Exception("Gagal menghapus foto!");
            }
        }

        // Handle new file uploads
        $newPhotosCount = 0;
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
                        $newPhotosCount++;
                    }
                }
            }
        }

        // === TAMBAHAN: Update Log Status ===
        $changes = [];
        
        // Compare date
        $originalDate = $originalData['scrap_date']->format('Y-m-d');
        if ($originalDate !== $scrapDate) {
            $changes[] = "Tanggal scrap diubah dari " . $originalData['scrap_date']->format('d/m/Y') . " menjadi " . date('d/m/Y', strtotime($scrapDate));
        }
        
        // Compare location
        $originalLocation = $originalData['scrap_location'] ?? '';
        if ($originalLocation !== $scrapLocation) {
            $oldLoc = $originalLocation ?: '(kosong)';
            $newLoc = $scrapLocation ?: '(kosong)';
            $changes[] = "Lokasi scrap diubah dari '{$oldLoc}' menjadi '{$newLoc}'";
        }
        
        // Compare notes
        $originalNotes = $originalData['scrap_notes'] ?? '';
        if ($originalNotes !== $scrapNotes) {
            $oldNotes = $originalNotes ?: '(kosong)';
            $newNotes = $scrapNotes ?: '(kosong)';
            if (strlen($oldNotes) > 50) $oldNotes = substr($oldNotes, 0, 50) . '...';
            if (strlen($newNotes) > 50) $newNotes = substr($newNotes, 0, 50) . '...';
            $changes[] = "Catatan scrap diubah";
        }
        
        // Track photo changes
        $deletedPhotosCount = count($deletePhotos);
        if ($deletedPhotosCount > 0) {
            $changes[] = "{$deletedPhotosCount} foto dihapus";
        }
        if ($newPhotosCount > 0) {
            $changes[] = "{$newPhotosCount} foto baru ditambahkan";
        }

        // Create log entry if there are changes
        if (!empty($changes)) {
            $logRemarks = "Update data scrap: " . implode(', ', $changes);
            
            $logSql = "INSERT INTO dbo.pad_status_log 
                      (padder_id, status, changed_by, remarks) 
                      VALUES (?, 'SCRAP', ?, ?)";
            $logParams = [
                $scrapData['padder_id'],
                $_SESSION['UserName'],
                $logRemarks
            ];
            $logStmt = sqlsrv_query($conn, $logSql, $logParams);
            
            if (!$logStmt) {
                throw new Exception("Gagal mencatat log perubahan!");
            }
        }

        // Commit transaction
        sqlsrv_commit($conn);

        $_SESSION['success'] = "Data scrap berhasil diupdate!" . 
                              (empty($changes) ? " (Tidak ada perubahan data)" : "");
        header('Location: scrap.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        // Reload data
        $scrapData = getScrapData($conn, $scrapId);
        $existingPhotos = getScrapPhotos($conn, $scrapId);
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Data Scrap</title>
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
        .existing-photo {
            border: 2px solid #28a745;
        }
        .changes-summary {
            background: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Data Scrap</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="scrap.php">Data Scrap</a></li>
                        <li class="breadcrumb-item active">Edit Data</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-warning">
                    <h3 class="card-title">Form Edit Data Scrap</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <!-- Changes Preview Section -->
                    <div id="changesPreview" class="changes-summary" style="display: none;">
                        <h6><i class="fas fa-history"></i> Ringkasan Perubahan:</h6>
                        <ul id="changesList"></ul>
                    </div>

                    <form id="scrapForm" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="padder_id">Padder</label>
                                    <input type="text" class="form-control" 
                                           value="<?= htmlspecialchars($scrapData['padder_id'] . ' - ' . $scrapData['padder_name']) ?>" 
                                           readonly>
                                    <input type="hidden" name="padder_id" value="<?= htmlspecialchars($scrapData['padder_id']) ?>">
                                    <small class="form-text text-muted">Status: <span class="badge badge-danger">SCRAP</span></small>
                                </div>

                                <div class="form-group">
                                    <label for="scrap_date">Tanggal Scrap <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="scrap_date" name="scrap_date" 
                                           value="<?= htmlspecialchars($scrapData['scrap_date']->format('Y-m-d')) ?>" required
                                           onchange="trackChanges()">
                                </div>

                                <div class="form-group">
                                    <label for="scrap_location">Lokasi Scrap</label>
                                    <input type="text" class="form-control" id="scrap_location" name="scrap_location" 
                                           value="<?= htmlspecialchars($scrapData['scrap_location'] ?? '') ?>" 
                                           placeholder="Masukkan lokasi scrap"
                                           onchange="trackChanges()">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Foto Existing</label>
                                    <div class="photo-preview" id="existingPhotos">
                                        <?php foreach ($existingPhotos as $photo): ?>
                                            <div class="photo-preview-item existing-photo">
                                                <img src="<?= htmlspecialchars($photo['file_path']) ?>" 
                                                     alt="Existing Photo" 
                                                     onclick="viewPhoto('<?= htmlspecialchars($photo['file_path']) ?>')">
                                                <div class="remove-photo" onclick="markPhotoForDelete(<?= $photo['id'] ?>, this)">
                                                    &times;
                                                </div>
                                                <input type="hidden" name="keep_photos[]" value="<?= $photo['id'] ?>">
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($existingPhotos)): ?>
                                            <div class="text-muted">Tidak ada foto</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="scrap_photos">Tambah Foto Baru</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="scrap_photos" 
                                               name="scrap_photos[]" multiple accept="image/*"
                                               onchange="trackChanges()">
                                        <label class="custom-file-label" for="scrap_photos">Pilih file foto baru</label>
                                    </div>
                                    <small class="form-text text-muted">Dapat memilih multiple file (JPEG, PNG, JPG)</small>
                                    
                                    <div class="photo-preview" id="photoPreview"></div>
                                </div>

                                <div class="form-group">
                                    <label for="scrap_notes">Catatan Scrap</label>
                                    <textarea class="form-control" id="scrap_notes" name="scrap_notes" 
                                              rows="4" placeholder="Masukkan catatan scrap..."
                                              onchange="trackChanges()"><?= htmlspecialchars($scrapData['scrap_notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update Data Scrap
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

<!-- Modal View Photo -->
<div class="modal fade" id="viewPhotoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Foto Scrap</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="modalPhoto" src="" alt="Foto Scrap" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
let photosToDelete = [];
let originalData = {
    scrap_date: '<?= $scrapData['scrap_date']->format('Y-m-d') ?>',
    scrap_location: '<?= addslashes($scrapData['scrap_location'] ?? '') ?>',
    scrap_notes: '<?= addslashes($scrapData['scrap_notes'] ?? '') ?>',
    existing_photos_count: <?= count($existingPhotos) ?>
};

function viewPhoto(src) {
    $('#modalPhoto').attr('src', src);
    $('#viewPhotoModal').modal('show');
}

function markPhotoForDelete(photoId, element) {
    if (!photosToDelete.includes(photoId)) {
        photosToDelete.push(photoId);
    }
    
    // Create hidden input for photos to delete
    if ($('#deletePhotosInput').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'deletePhotosInput',
            name: 'delete_photos[]'
        }).appendTo('#scrapForm');
    }
    
    // Update hidden input value
    $('#deletePhotosInput').val(photosToDelete.join(','));
    
    // Visual feedback
    $(element).closest('.photo-preview-item').fadeOut(300, function() {
        $(this).remove();
    });
    
    trackChanges();
}

function trackChanges() {
    const currentData = {
        scrap_date: $('#scrap_date').val(),
        scrap_location: $('#scrap_location').val(),
        scrap_notes: $('#scrap_notes').val(),
        new_photos_count: $('#scrap_photos')[0].files.length,
        deleted_photos_count: photosToDelete.length
    };

    const changes = [];

    // Check date change
    if (currentData.scrap_date !== originalData.scrap_date) {
        changes.push(`Tanggal scrap diubah menjadi ${formatDate(currentData.scrap_date)}`);
    }

    // Check location change
    if (currentData.scrap_location !== originalData.scrap_location) {
        const oldLoc = originalData.scrap_location || '(kosong)';
        const newLoc = currentData.scrap_location || '(kosong)';
        changes.push(`Lokasi scrap diubah dari '${oldLoc}' menjadi '${newLoc}'`);
    }

    // Check notes change
    if (currentData.scrap_notes !== originalData.scrap_notes) {
        changes.push('Catatan scrap diubah');
    }

    // Check photo changes
    if (currentData.deleted_photos_count > 0) {
        changes.push(`${currentData.deleted_photos_count} foto dihapus`);
    }
    if (currentData.new_photos_count > 0) {
        changes.push(`${currentData.new_photos_count} foto baru ditambahkan`);
    }

    // Update changes preview
    const changesPreview = $('#changesPreview');
    const changesList = $('#changesList');
    
    if (changes.length > 0) {
        changesList.empty();
        changes.forEach(change => {
            changesList.append(`<li>${change}</li>`);
        });
        changesPreview.show();
    } else {
        changesPreview.hide();
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID');
}

$(document).ready(function() {
    // File input preview for new photos
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
                        trackChanges();
                    });
                    
                    preview.append(previewItem);
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Update file label
        const fileNames = Array.from(files).map(f => f.name).join(', ');
        $(this).next('.custom-file-label').text(fileNames || 'Pilih file foto');
        
        trackChanges();
    });

    // Form validation
    $('#scrapForm').on('submit', function(e) {
        const scrapDate = $('#scrap_date').val();
        
        if (!scrapDate) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap',
                text: 'Tanggal Scrap wajib diisi!',
                confirmButtonColor: '#dc3545'
            });
            return;
        }

        // Show confirmation with changes summary
        const changes = $('#changesList li').length;
        if (changes > 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Update Data Scrap?',
                html: `Anda akan mengupdate data scrap dengan <strong>${changes} perubahan</strong>. Perubahan akan dicatat dalam log sistem.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Update!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        }
    });

    // Initialize changes tracking
    trackChanges();
});
</script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>
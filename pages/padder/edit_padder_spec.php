<?php
// edit_padder_spec.php - Edit Spesifikasi Padder
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

// helper: ambil permission user untuk menu Padder Spec
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

// Ambil permission (sesuaikan MenuId dengan menu Padder Spec di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 111);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit spesifikasi padder.";
    header('Location: m_padder_spec.php');
    exit;
}

// ====== Get Padder List for Dropdown ======
function getPadderList($conn) {
    $sql = "SELECT padder_id, padder_name 
            FROM dbo.pad_m_padder 
            ORDER BY padder_id";
    $stmt = sqlsrv_query($conn, $sql);
    
    $padders = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $padders[] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $padders;
}

$padderList = getPadderList($conn);

// ====== Get Spec Data ======
$specId = $_GET['id'] ?? '';
$spec = null;
$errors = [];

if (empty($specId)) {
    $_SESSION['error'] = "ID Spesifikasi tidak valid!";
    header('Location: m_padder_spec.php');
    exit;
}

// Fetch spec data
$sql = "SELECT 
            ps.id,
            ps.padder_id,
            ps.spec_name,
            ps.spec_value,
            p.padder_name
        FROM dbo.pad_m_padder_spec ps
        INNER JOIN dbo.pad_m_padder p ON ps.padder_id = p.padder_id
        WHERE ps.id = ?";
$stmt = sqlsrv_query($conn, $sql, [$specId]);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $spec = $row;
} else {
    $_SESSION['error'] = "Data spesifikasi tidak ditemukan!";
    header('Location: m_padder_spec.php');
    exit;
}
if ($stmt) sqlsrv_free_stmt($stmt);

// ====== Process Form Submission ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $padderId = $_POST['padder_id'] ?? '';
    $specName = $_POST['spec_name'] ?? '';
    $specValue = $_POST['spec_value'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($padderId)) {
        $errors[] = "Padder harus dipilih";
    }
    
    if (empty($specName)) {
        $errors[] = "Nama spesifikasi harus diisi";
    }
    
    if (empty($specValue)) {
        $errors[] = "Nilai spesifikasi harus diisi";
    }
    
    // Check if spec already exists for this padder (excluding current record)
    if (empty($errors)) {
        $checkSql = "SELECT COUNT(*) as count FROM dbo.pad_m_padder_spec 
                     WHERE padder_id = ? AND spec_name = ? AND id != ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$padderId, $specName, $specId]);
        if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['count'] > 0) {
                $errors[] = "Spesifikasi dengan nama '" . $specName . "' sudah ada untuk padder ini";
            }
        }
        if ($checkStmt) sqlsrv_free_stmt($checkStmt);
    }
    
    if (empty($errors)) {
        // Update data
        $sql = "UPDATE dbo.pad_m_padder_spec 
                SET padder_id = ?, 
                    spec_name = ?, 
                    spec_value = ?
                WHERE id = ?";
        
        $params = [$padderId, $specName, $specValue, $specId];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt !== false) {
            $_SESSION['success'] = "Spesifikasi padder berhasil diupdate!";
            header('Location: m_padder_spec.php');
            exit;
        } else {
            $errors[] = "Gagal mengupdate data: " . print_r(sqlsrv_errors(), true);
        }
        
        if ($stmt) sqlsrv_free_stmt($stmt);
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        // Reload data setelah error
        header('Location: edit_padder_spec.php?id=' . $specId);
        exit;
    }
}

// Include layout parts (header/sidebar)
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Spesifikasi Padder</title>

    <!-- CSS (AdminLTE) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">

    <style>
        .required:after {
            content: " *";
            color: red;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
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
                    <h1 class="m-0">Edit Spesifikasi Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="m_padder.php">Padder</a></li>
                        <li class="breadcrumb-item"><a href="m_padder_spec.php">Spesifikasi</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                    <h3 class="card-title">Form Edit Spesifikasi Padder</h3>
                    <div class="card-tools">
                        <a href="m_padder_spec.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="form-container">
                        <!-- Information Box -->
                        <div class="info-box">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">ID Spesifikasi:</span>
                                        <span><?= htmlspecialchars($spec['id']) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Padder Saat Ini:</span>
                                        <span><?= htmlspecialchars($spec['padder_id']) ?> - <?= htmlspecialchars($spec['padder_name']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form id="editPadderSpecForm" method="post" action="">
                            <div class="form-group">
                                <label for="padder_id" class="required">Padder</label>
                                <select class="form-control" id="padder_id" name="padder_id" required>
                                    <option value="">-- Pilih Padder --</option>
                                    <?php foreach ($padderList as $padder): ?>
                                        <option value="<?= htmlspecialchars($padder['padder_id']) ?>" 
                                            <?= (isset($_POST['padder_id']) ? $_POST['padder_id'] : $spec['padder_id']) === $padder['padder_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    Pilih padder yang akan diubah spesifikasinya
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="spec_name" class="required">Nama Spesifikasi</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="spec_name" 
                                       name="spec_name" 
                                       value="<?= isset($_POST['spec_name']) ? htmlspecialchars($_POST['spec_name']) : htmlspecialchars($spec['spec_name']) ?>"
                                       placeholder="Contoh: Diameter, Panjang, Berat, Material, dll."
                                       required
                                       maxlength="100">
                                <small class="form-text text-muted">
                                    Maksimal 100 karakter
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="spec_value" class="required">Nilai Spesifikasi</label>
                                <textarea class="form-control" 
                                          id="spec_value" 
                                          name="spec_value" 
                                          rows="3" 
                                          placeholder="Masukkan nilai spesifikasi"
                                          required
                                          maxlength="255"><?= isset($_POST['spec_value']) ? htmlspecialchars($_POST['spec_value']) : htmlspecialchars($spec['spec_value']) ?></textarea>
                                <small class="form-text text-muted">
                                    Maksimal 255 karakter
                                </small>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Update
                                </button>
                                <a href="m_padder_spec.php" class="btn btn-secondary">
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

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(document).ready(function() {
    // Form validation
    $('#editPadderSpecForm').on('submit', function(e) {
        var padderId = $('#padder_id').val();
        var specName = $('#spec_name').val().trim();
        var specValue = $('#spec_value').val().trim();
        
        if (!padderId) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Padder harus dipilih!',
                confirmButtonColor: '#3085d6',
            });
            $('#padder_id').focus();
            return false;
        }
        
        if (!specName) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Nama spesifikasi harus diisi!',
                confirmButtonColor: '#3085d6',
            });
            $('#spec_name').focus();
            return false;
        }
        
        if (!specValue) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Nilai spesifikasi harus diisi!',
                confirmButtonColor: '#3085d6',
            });
            $('#spec_value').focus();
            return false;
        }
        
        // Validate max length
        if (specName.length > 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Nama spesifikasi maksimal 100 karakter!',
                confirmButtonColor: '#3085d6',
            });
            $('#spec_name').focus();
            return false;
        }
        
        if (specValue.length > 255) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Nilai spesifikasi maksimal 255 karakter!',
                confirmButtonColor: '#3085d6',
            });
            $('#spec_value').focus();
            return false;
        }
        
        // Show loading
        Swal.fire({
            title: 'Mengupdate...',
            text: 'Sedang mengupdate spesifikasi padder',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
    
    // Real-time character counter for spec_name
    $('#spec_name').on('input', function() {
        var length = $(this).val().length;
        var maxLength = 100;
        var remaining = maxLength - length;
        
        // Update counter text
        var counter = $(this).next('.form-text');
        if (remaining < 0) {
            counter.html('<span style="color: red;">Melebihi batas maksimal! (' + length + '/' + maxLength + ')</span>');
        } else if (remaining < 20) {
            counter.html('<span style="color: orange;">Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')</span>');
        } else {
            counter.html('Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')');
        }
    });
    
    // Real-time character counter for spec_value
    $('#spec_value').on('input', function() {
        var length = $(this).val().length;
        var maxLength = 255;
        var remaining = maxLength - length;
        
        // Update counter text
        var counter = $(this).next('.form-text');
        if (remaining < 0) {
            counter.html('<span style="color: red;">Melebihi batas maksimal! (' + length + '/' + maxLength + ')</span>');
        } else if (remaining < 50) {
            counter.html('<span style="color: orange;">Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')</span>');
        } else {
            counter.html('Sisa karakter: ' + remaining + ' (' + length + '/' + maxLength + ')');
        }
    });
    
    // Show notification if any
    <?php if (isset($_SESSION['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        html: <?= json_encode($_SESSION['error']) ?>,
        confirmButtonColor: '#3085d6',
    });
    <?php unset($_SESSION['error']); endif; ?>
});
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
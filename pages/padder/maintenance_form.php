<?php
// maintenance_form.php - Form Input Maintenance Padder
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

// helper: ambil permission user untuk menu Maintenance
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

// Ambil permission (sesuaikan MenuId dengan menu Maintenance di sistem Anda)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 116);
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menambah data maintenance.";
    header('Location: maintenance.php');
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

// ====== Process Form Submission ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $padder_id = $_POST['padder_id'] ?? '';
    $maintenance_date = $_POST['maintenance_date'] ?? '';
    $work_done = $_POST['work_done'] ?? '';
    $hardness_check = $_POST['hardness_check'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Validasi input
    $errors = [];
    
    if (empty($padder_id)) {
        $errors[] = "Padder harus dipilih";
    }
    
    if (empty($maintenance_date)) {
        $errors[] = "Tanggal maintenance harus diisi";
    }
    
    if (empty($work_done)) {
        $errors[] = "Pekerjaan yang dilakukan harus diisi";
    }
    
    // Check if padder exists and is in valid status
    if (!empty($padder_id)) {
        $checkSql = "SELECT status FROM dbo.pad_m_padder WHERE padder_id = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$padder_id]);
        if ($checkStmt !== false && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            if (!in_array($row['status'], ['IN_USE', 'MAINTENANCE'])) {
                $errors[] = "Padder tidak dalam status yang valid untuk maintenance. Status saat ini: " . $row['status'];
            }
        } else {
            $errors[] = "Padder tidak ditemukan";
        }
        if ($checkStmt) sqlsrv_free_stmt($checkStmt);
    }
    
    if (empty($errors)) {
        // Begin transaction
        sqlsrv_begin_transaction($conn);
        
        try {
            // 1. Insert into maintenance table
            $insertMaintenanceSql = "INSERT INTO dbo.pad_t_maintenance (
                                padder_id, maintenance_date, work_done, hardness_check, notes
                            ) VALUES (?, ?, ?, ?, ?)";
            
            $maintenanceParams = [
                $padder_id,
                $maintenance_date,
                $work_done,
                $hardness_check,
                $notes
            ];
            
            $maintenanceStmt = sqlsrv_query($conn, $insertMaintenanceSql, $maintenanceParams);
            
            if ($maintenanceStmt === false) {
                throw new Exception("Gagal menyimpan data maintenance");
            }
            
            // 2. Update padder status to MAINTENANCE if not already
            $updatePadderSql = "UPDATE dbo.pad_m_padder 
                               SET status = 'MAINTENANCE', 
                                   updated_at = GETDATE(),
                                   updated_by = ?
                               WHERE padder_id = ? AND status != 'MAINTENANCE'";
            
            $updateParams = [
                $_SESSION['UserName'],
                $padder_id
            ];
            
            $updateStmt = sqlsrv_query($conn, $updatePadderSql, $updateParams);
            
            if ($updateStmt === false) {
                throw new Exception("Gagal mengupdate status padder");
            }
            
            // 3. Insert into status log if status changed
            if (sqlsrv_rows_affected($updateStmt) > 0) {
                $insertLogSql = "INSERT INTO dbo.pad_status_log (
                                    padder_id, status, changed_by, remarks
                                ) VALUES (?, 'MAINTENANCE', ?, ?)";
                
                $logRemarks = "Maintenance: " . $work_done . ". Hardness: " . $hardness_check;
                $logParams = [
                    $padder_id,
                    $_SESSION['UserName'],
                    $logRemarks
                ];
                
                $logStmt = sqlsrv_query($conn, $insertLogSql, $logParams);
                
                if ($logStmt === false) {
                    throw new Exception("Gagal mencatat history status");
                }
            }
            
            // Commit transaction
            sqlsrv_commit($conn);
            
            $_SESSION['success'] = "Data maintenance berhasil disimpan!";
            header('Location: maintenance.php');
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

$padderList = getAvailablePadders($conn);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Maintenance Padder</title>

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
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .padder-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            margin-top: 10px;
            display: none;
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
        .info-section:last-child {
            margin-bottom: 0;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
            min-width: 120px;
            display: inline-block;
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
                    <h1 class="m-0">Input Maintenance Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="maintenance.php">Maintenance Padder</a></li>
                        <li class="breadcrumb-item active">Input Maintenance</li>
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
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'warning'); ?> text-white">
                            <h3 class="card-title">Form Input Maintenance</h3>
                        </div>
                        <div class="card-body">
                            <form id="maintenanceForm" method="POST" action="">
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
                                    <small class="form-text text-muted">Pilih padder yang akan dilakukan maintenance</small>
                                </div>

                                <!-- Padder Info (akan diisi via AJAX) -->
                                <div id="padderInfo" class="padder-info">
                                    <!-- Informasi akan diisi oleh JavaScript -->
                                </div>

                                <div class="form-group">
                                    <label for="maintenance_date" class="required">Tanggal Maintenance</label>
                                    <input type="text" id="maintenance_date" name="maintenance_date" class="form-control datepicker" 
                                           value="<?= isset($_POST['maintenance_date']) ? htmlspecialchars($_POST['maintenance_date']) : date('Y-m-d') ?>" 
                                           required readonly>
                                    <small class="form-text text-muted">Tanggal ketika maintenance dilakukan</small>
                                </div>

                                <div class="form-group">
                                    <label for="work_done" class="required">Pekerjaan yang Dilakukan</label>
                                    <textarea id="work_done" name="work_done" class="form-control" rows="3" 
                                              placeholder="Jelaskan pekerjaan maintenance yang dilakukan..." required><?= isset($_POST['work_done']) ? htmlspecialchars($_POST['work_done']) : '' ?></textarea>
                                    <small class="form-text text-muted">Deskripsi detail pekerjaan maintenance</small>
                                </div>

                                <div class="form-group">
                                    <label for="hardness_check">Hardness Check</label>
                                    <input type="text" id="hardness_check" name="hardness_check" class="form-control" 
                                           value="<?= isset($_POST['hardness_check']) ? htmlspecialchars($_POST['hardness_check']) : '' ?>" 
                                           placeholder="Hasil pengukuran hardness">
                                    <small class="form-text text-muted">Opsional: hasil pengukuran hardness</small>
                                </div>

                                <div class="form-group">
                                    <label for="notes">Catatan</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                                              placeholder="Catatan tambahan mengenai maintenance..."><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                                    <small class="form-text text-muted">Opsional: catatan khusus mengenai maintenance</small>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor ?? 'warning');?>"> <i class="fas fa-save"></i> Simpan</button>
                                    <a href="maintenance.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
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
    $('#maintenance_date').val(new Date().toISOString().split('T')[0]);

    // Padder selection change handler
    $('#padder_id').on('change', function() {
        var padderId = $(this).val();
        var $padderInfo = $('#padderInfo');
        
        console.log('Padder changed:', padderId);
        
        if (padderId) {
            // Show loading
            $padderInfo.html(`
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div> 
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
                    console.log('AJAX response:', response);
                    
                    if (response.success && response.data) {
                        var data = response.data;
                        renderPadderInfo(data);
                    } else {
                        $padderInfo.hide();
                        var errorMsg = response.message || 'Gagal mengambil data padder';
                        console.error('AJAX error:', errorMsg);
                        showError('Error', errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX request failed:', error);
                    $padderInfo.hide();
                    
                    var errorMessage = 'Terjadi kesalahan saat mengambil data padder';
                    if (status === 'timeout') {
                        errorMessage = 'Timeout: Server terlalu lama merespon';
                    }
                    
                    showError('Error', errorMessage);
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
            case 'READY':
                badgeClass = 'badge-success';
                break;
            case 'IN_USE':
                badgeClass = 'badge-primary';
                break;
            case 'MAINTENANCE':
                badgeClass = 'badge-warning';
                break;
            case 'REPAIRED':
                badgeClass = 'badge-info';
                break;
            case 'SCRAP':
                badgeClass = 'badge-danger';
                break;
            default:
                badgeClass = 'badge-secondary';
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
    $('#maintenanceForm').on('submit', function(e) {
        var padderId = $('#padder_id').val();
        var maintenanceDate = $('#maintenance_date').val();
        var workDone = $('#work_done').val();
        
        if (!padderId || !maintenanceDate || !workDone) {
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
        
        // Show loading
        Swal.fire({
            title: 'Menyimpan Data',
            text: 'Sedang menyimpan data maintenance...',
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
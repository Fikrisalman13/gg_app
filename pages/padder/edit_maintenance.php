<?php
// edit_maintenance.php - Form Edit Maintenance Padder
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

// Ambil permission
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 116);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data maintenance.";
    header('Location: maintenance.php');
    exit;
}

// Get maintenance data by ID
$id = $_GET['id'] ?? '';
$maintenanceData = null;

if (empty($id)) {
    $_SESSION['error'] = "ID maintenance tidak valid";
    header('Location: maintenance.php');
    exit;
}

// Fetch maintenance data
$sql = "SELECT m.*, p.padder_name, p.status as padder_status
        FROM dbo.pad_t_maintenance m
        INNER JOIN dbo.pad_m_padder p ON m.padder_id = p.padder_id
        WHERE m.id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $maintenanceData = $row;
    
    // Format dates
    if ($maintenanceData['maintenance_date'] instanceof DateTime) {
        $maintenanceData['maintenance_date'] = $maintenanceData['maintenance_date']->format('Y-m-d');
    }
}
sqlsrv_free_stmt($stmt);

if (!$maintenanceData) {
    $_SESSION['error'] = "Data maintenance tidak ditemukan";
    header('Location: maintenance.php');
    exit;
}

// Process form submission for edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance_date = $_POST['maintenance_date'] ?? '';
    $work_done = $_POST['work_done'] ?? '';
    $hardness_check = $_POST['hardness_check'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    $errors = [];
    
    // Validasi
    if (empty($maintenance_date)) {
        $errors[] = "Tanggal maintenance harus diisi";
    }
    
    if (empty($work_done)) {
        $errors[] = "Pekerjaan yang dilakukan harus diisi";
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            sqlsrv_begin_transaction($conn);
            
            // Simpan data lama untuk log
            $old_data = $maintenanceData;
            
            // Update data maintenance
            $updateSql = "UPDATE dbo.pad_t_maintenance 
                         SET maintenance_date = ?, work_done = ?, hardness_check = ?, notes = ?
                         WHERE id = ?";
            
            $params = [
                $maintenance_date,
                $work_done,
                $hardness_check,
                $notes,
                $id
            ];
            
            $updateStmt = sqlsrv_query($conn, $updateSql, $params);
            
            if ($updateStmt === false) {
                throw new Exception("Gagal mengupdate data maintenance");
            }
            
            // Insert ke log status - mencatat perubahan data maintenance
            $logSql = "INSERT INTO dbo.pad_status_log (
                        padder_id, status, changed_by, remarks
                    ) VALUES (?, ?, ?, ?)";
            
            $logRemarks = "Update Data Maintenance: ";
            $changes = [];
            
            // Catat perubahan tanggal
            if ($old_data['maintenance_date'] != $maintenance_date) {
                $oldDate = $old_data['maintenance_date'];
                if ($oldDate instanceof DateTime) {
                    $oldDate = $oldDate->format('Y-m-d');
                }
                $changes[] = "Tanggal: {$oldDate} → {$maintenance_date}";
            }
            
            // Catat perubahan pekerjaan
            if ($old_data['work_done'] != $work_done) {
                $oldWork = substr($old_data['work_done'], 0, 50) . (strlen($old_data['work_done']) > 50 ? '...' : '');
                $newWork = substr($work_done, 0, 50) . (strlen($work_done) > 50 ? '...' : '');
                $changes[] = "Pekerjaan: {$oldWork} → {$newWork}";
            }
            
            // Catat perubahan hardness check
            if (($old_data['hardness_check'] ?? '') != $hardness_check) {
                $oldHardness = $old_data['hardness_check'] ?? '(kosong)';
                $newHardness = $hardness_check ?: '(kosong)';
                $changes[] = "Hardness: {$oldHardness} → {$newHardness}";
            }
            
            // Catat perubahan catatan
            if (($old_data['notes'] ?? '') != $notes) {
                $oldNotes = $old_data['notes'] ?? '(kosong)';
                $newNotes = $notes ?: '(kosong)';
                $changes[] = "Catatan: {$oldNotes} → {$newNotes}";
            }
            
            if (!empty($changes)) {
                $logRemarks .= implode(', ', $changes);
                
                $logParams = [
                    $maintenanceData['padder_id'],
                    'MAINTENANCE', // Status tetap MAINTENANCE karena hanya edit data maintenance
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
            
            $_SESSION['success'] = "Data maintenance berhasil diupdate!";
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
    
    // Reload data setelah error
    $sql = "SELECT m.*, p.padder_name, p.status as padder_status
            FROM dbo.pad_t_maintenance m
            INNER JOIN dbo.pad_m_padder p ON m.padder_id = p.padder_id
            WHERE m.id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $maintenanceData = $row;
        if ($maintenanceData['maintenance_date'] instanceof DateTime) {
            $maintenanceData['maintenance_date'] = $maintenanceData['maintenance_date']->format('Y-m-d');
        }
    }
    sqlsrv_free_stmt($stmt);
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Maintenance Padder</title>

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
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .padder-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            margin-top: 10px;
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
        .hardness-value {
            font-weight: bold;
            color: #28a745;
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
                    <h1 class="m-0">Edit Maintenance Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="maintenance.php">Maintenance Padder</a></li>
                        <li class="breadcrumb-item active">Edit Maintenance</li>
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
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'warning'); ?> text-white">
                            <h3 class="card-title">Form Edit Maintenance</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($maintenanceData): ?>
                            <!-- Info Box -->
                            <div class="info-box">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Informasi:</strong> Hanya data tanggal, pekerjaan, hardness check, dan catatan yang dapat diubah. Padder tidak dapat diubah setelah maintenance dilakukan. Semua perubahan akan dicatat dalam log sistem.
                            </div>

                            <form method="POST" action="" id="editForm">
                                <!-- Padder Info (Readonly) -->
                                <div class="form-group">
                                    <label>Padder</label>
                                    <input type="text" class="form-control readonly-field" 
                                           value="<?= htmlspecialchars($maintenanceData['padder_id'] . ' - ' . $maintenanceData['padder_name']) ?>" 
                                           readonly>
                                    <small class="form-text text-muted">Padder tidak dapat diubah setelah maintenance dilakukan</small>
                                </div>

                                <div class="form-group">
                                    <label>Status Padder Saat Ini</label>
                                    <?php
                                    $statusClass = 'badge-secondary';
                                    switch($maintenanceData['padder_status']) {
                                        case 'READY': $statusClass = 'badge-success'; break;
                                        case 'IN_USE': $statusClass = 'badge-primary'; break;
                                        case 'MAINTENANCE': $statusClass = 'badge-warning'; break;
                                        case 'REPAIRED': $statusClass = 'badge-info'; break;
                                        case 'SCRAP': $statusClass = 'badge-danger'; break;
                                    }
                                    ?>
                                    <div>
                                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($maintenanceData['padder_status']) ?></span>
                                    </div>
                                    <small class="form-text text-muted">Status padder saat ini</small>
                                </div>

                                <!-- Editable Fields -->
                                <div class="form-group">
                                    <label for="maintenance_date" class="required">Tanggal Maintenance</label>
                                    <input type="date" id="maintenance_date" name="maintenance_date" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($maintenanceData['maintenance_date']) ?>" 
                                           required>
                                    <small class="form-text text-muted">Tanggal ketika maintenance dilakukan</small>
                                </div>

                                <div class="form-group">
                                    <label for="work_done" class="required">Pekerjaan yang Dilakukan</label>
                                    <textarea id="work_done" name="work_done" class="form-control" rows="4" 
                                              placeholder="Jelaskan detail pekerjaan maintenance yang dilakukan..." required><?= htmlspecialchars($maintenanceData['work_done'] ?? '') ?></textarea>
                                    <small class="form-text text-muted">Deskripsi detail pekerjaan maintenance</small>
                                </div>

                                <div class="form-group">
                                    <label for="hardness_check">Hardness Check</label>
                                    <input type="text" id="hardness_check" name="hardness_check" class="form-control hardness-value" 
                                           value="<?= htmlspecialchars($maintenanceData['hardness_check'] ?? '') ?>" 
                                           placeholder="Hasil pengukuran hardness">
                                    <small class="form-text text-muted">Opsional: hasil pengukuran hardness (contoh: 65 HRC, 70 Shore D)</small>
                                </div>

                                <div class="form-group">
                                    <label for="notes">Catatan</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                                              placeholder="Catatan tambahan mengenai maintenance..."><?= htmlspecialchars($maintenanceData['notes'] ?? '') ?></textarea>
                                    <small class="form-text text-muted">Opsional: catatan khusus mengenai maintenance</small>
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
                                    <a href="maintenance.php" class="btn btn-secondary">
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
                                <a href="maintenance.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'warning'); ?> text-white">
                            <h3 class="card-title">Informasi Data</h3>
                        </div>
                        <div class="card-body">
                            <h6><i class="fas fa-database"></i> Detail Data:</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>ID Record:</strong></td>
                                    <td><?= htmlspecialchars($maintenanceData['id'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Padder ID:</strong></td>
                                    <td><?= htmlspecialchars($maintenanceData['padder_id'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Nama Padder:</strong></td>
                                    <td><?= htmlspecialchars($maintenanceData['padder_name'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php if ($maintenanceData): ?>
                                            <span class="badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($maintenanceData['padder_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal Maintenance:</strong></td>
                                    <td><?= htmlspecialchars($maintenanceData['maintenance_date'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Hardness Check:</strong></td>
                                    <td>
                                        <?php if (!empty($maintenanceData['hardness_check'])): ?>
                                            <span class="hardness-value"><?= htmlspecialchars($maintenanceData['hardness_check']) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <hr>
                            
                            <h6><i class="fas fa-lightbulb"></i> Tips:</h6>
                            <ul class="small">
                                <li>Pastikan tanggal maintenance sesuai dengan tanggal sebenarnya</li>
                                <li>Jelaskan pekerjaan dengan detail dan jelas</li>
                                <li>Catat hasil hardness check jika dilakukan pengukuran</li>
                                <li>Tambahkan catatan jika ada informasi penting</li>
                                <li>Semua perubahan akan tercatat dalam log sistem</li>
                                <li>Data yang sudah diupdate tidak dapat dikembalikan</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-3">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'warning'); ?> text-white">
                            <h3 class="card-title">Aksi Cepat</h3>
                        </div>
                        <div class="card-body text-center">
                            <a href="maintenance.php" class="btn btn-outline-warning btn-sm mb-2">
                                <i class="fas fa-list"></i> Lihat Semua Data
                            </a>
                            <br>
                            <a href="maintenance_form.php" class="btn btn-outline-success btn-sm mb-2">
                                <i class="fas fa-plus"></i> Tambah Data Baru
                            </a>
                            <br>
                            <?php if ($maintenanceData && $permissions['CanDelete'] == 1): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btnDelete">
                                    <i class="fas fa-trash"></i> Hapus Data Ini
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Log Information -->
                    <div class="card mt-3">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'warning'); ?> text-white">
                            <h3 class="card-title">Informasi Log</h3>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Setiap perubahan data maintenance akan dicatat dalam sistem log dengan detail:
                            </p>
                            <ul class="small">
                                <li>Tanggal dan waktu perubahan</li>
                                <li>User yang melakukan perubahan</li>
                                <li>Field yang diubah</li>
                                <li>Nilai sebelum dan sesudah</li>
                                <li>Padder yang terkait</li>
                            </ul>
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
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
(function(){
    // Store original values for comparison
    const originalValues = {
        maintenance_date: '<?= htmlspecialchars($maintenanceData['maintenance_date']) ?>',
        work_done: `<?= str_replace(["\r", "\n"], '', addslashes($maintenanceData['work_done'] ?? '')) ?>`,
        hardness_check: '<?= htmlspecialchars($maintenanceData['hardness_check'] ?? '') ?>',
        notes: `<?= str_replace(["\r", "\n"], '', addslashes($maintenanceData['notes'] ?? '')) ?>`
    };

    // Reset form handler
    $('#btnReset').on('click', function() {
        // Reset form ke nilai semula
        $('#maintenance_date').val(originalValues.maintenance_date);
        $('#work_done').val(originalValues.work_done);
        $('#hardness_check').val(originalValues.hardness_check);
        $('#notes').val(originalValues.notes);
        
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

    // Delete confirmation
    $('#btnDelete').on('click', function() {
        Swal.fire({
            title: 'Hapus Data Maintenance?',
            text: 'Data akan dihapus permanen dan tidak dapat dikembalikan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = 'delete_maintenance.php?id=<?= $id ?>';
            }
        });
    });

    // Function to detect changes and show summary
    function detectChanges() {
        const currentValues = {
            maintenance_date: $('#maintenance_date').val(),
            work_done: $('#work_done').val(),
            hardness_check: $('#hardness_check').val(),
            notes: $('#notes').val()
        };

        const changes = [];
        
        // Check each field for changes
        if (currentValues.maintenance_date !== originalValues.maintenance_date) {
            changes.push(`<strong>Tanggal:</strong> ${originalValues.maintenance_date} → ${currentValues.maintenance_date}`);
        }
        
        if (currentValues.work_done !== originalValues.work_done) {
            const oldWork = originalValues.work_done.length > 50 ? 
                originalValues.work_done.substring(0, 50) + '...' : originalValues.work_done;
            const newWork = currentValues.work_done.length > 50 ? 
                currentValues.work_done.substring(0, 50) + '...' : currentValues.work_done;
            changes.push(`<strong>Pekerjaan:</strong> ${oldWork} → ${newWork}`);
        }
        
        if (currentValues.hardness_check !== originalValues.hardness_check) {
            const oldHardness = originalValues.hardness_check || '(kosong)';
            const newHardness = currentValues.hardness_check || '(kosong)';
            changes.push(`<strong>Hardness:</strong> ${oldHardness} → ${newHardness}`);
        }
        
        if (currentValues.notes !== originalValues.notes) {
            const oldNotes = originalValues.notes || '(kosong)';
            const newNotes = currentValues.notes || '(kosong)';
            changes.push(`<strong>Catatan:</strong> ${oldNotes} → ${newNotes}`);
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
        var maintenanceDate = $('#maintenance_date').val();
        var workDone = $('#work_done').val();
        
        if (!maintenanceDate || !workDone) {
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
        
        // Show loading dengan konfirmasi perubahan
        const changes = [];
        if ($('#maintenance_date').val() !== originalValues.maintenance_date) changes.push('Tanggal');
        if ($('#work_done').val() !== originalValues.work_done) changes.push('Pekerjaan');
        if ($('#hardness_check').val() !== originalValues.hardness_check) changes.push('Hardness Check');
        if ($('#notes').val() !== originalValues.notes) changes.push('Catatan');
        
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
    $('#maintenance_date, #work_done, #hardness_check, #notes').on('change input', detectChanges);

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
        $('#maintenance_date').focus();
        
        // Initialize change detection
        detectChanges();
    });

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
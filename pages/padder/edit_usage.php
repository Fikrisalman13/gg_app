<?php
// edit_usage.php - Form Edit Pemakaian Padder
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

$themeColor = $_SESSION['Theme'] ?? 'primary';

// helper: ambil permission user untuk menu Pemakaian
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
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 115);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data pemakaian.";
    header('Location: usage.php');
    exit;
}

// Get usage data by ID
$id = $_GET['id'] ?? '';
$usageData = null;

if (empty($id)) {
    $_SESSION['error'] = "ID pemakaian tidak valid";
    header('Location: usage.php');
    exit;
}

// Fetch usage data
$sql = "SELECT u.*, p.padder_name, p.status as padder_status
        FROM dbo.pad_t_usage u
        INNER JOIN dbo.pad_m_padder p ON u.padder_id = p.padder_id
        WHERE u.id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $usageData = $row;
    
    // Format dates
    if ($usageData['used_date'] instanceof DateTime) {
        $usageData['used_date'] = $usageData['used_date']->format('Y-m-d');
    }
}
sqlsrv_free_stmt($stmt);

if (!$usageData) {
    $_SESSION['error'] = "Data pemakaian tidak ditemukan";
    header('Location: usage.php');
    exit;
}

// ====== Static Lists (Machine & Location) - SAMA DENGAN usage_form.php ======
$machineList = [
    ['machine_name' => 'Mesin Pad Steam'],
    ['machine_name' => 'Mesin Washing 1'],
    ['machine_name' => 'Mesin Washing 2'],
    ['machine_name' => 'Mesin Washing 3'],
    ['machine_name' => 'Mesin PBR 1'],
    ['machine_name' => 'Mesin PBR 2']
];

$locationList = [
    ['location' => 'Dyeing Finishing'],
    ['location' => 'Weaving']
];

// Process form submission for edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $used_date = $_POST['used_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $machine_name = $_POST['machine_name'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $errors = [];
    
    // Validasi
    if (empty($used_date)) {
        $errors[] = "Tanggal pemakaian harus diisi";
    }
    
    if (empty($location)) {
        $errors[] = "Lokasi harus diisi";
    }
    
    if (empty($machine_name)) {
        $errors[] = "Nama mesin harus diisi";
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            sqlsrv_begin_transaction($conn);
            
            // Simpan data lama untuk log
            $old_data = $usageData;
            
            // Update data pemakaian
            $updateSql = "UPDATE dbo.pad_t_usage 
                         SET used_date = ?, location = ?, machine_name = ?, remarks = ?
                         WHERE id = ?";
            
            $params = [
                $used_date,
                $location,
                $machine_name,
                $remarks,
                $id
            ];
            
            $updateStmt = sqlsrv_query($conn, $updateSql, $params);
            
            if ($updateStmt === false) {
                throw new Exception("Gagal mengupdate data pemakaian");
            }
            
            // Insert ke log status - mencatat perubahan data pemakaian
            $logSql = "INSERT INTO dbo.pad_status_log (
                        padder_id, status, changed_by, remarks
                    ) VALUES (?, ?, ?, ?)";
            
            $logRemarks = "Update Data Pemakaian: ";
            $changes = [];
            
            // Catat perubahan tanggal
            if ($old_data['used_date'] != $used_date) {
                $oldDate = $old_data['used_date'];
                if ($oldDate instanceof DateTime) {
                    $oldDate = $oldDate->format('Y-m-d');
                }
                $changes[] = "Tanggal: {$oldDate} → {$used_date}";
            }
            
            // Catat perubahan lokasi
            if ($old_data['location'] != $location) {
                $changes[] = "Lokasi: {$old_data['location']} → {$location}";
            }
            
            // Catat perubahan mesin
            if ($old_data['machine_name'] != $machine_name) {
                $changes[] = "Mesin: {$old_data['machine_name']} → {$machine_name}";
            }
            
            // Catat perubahan keterangan
            if (($old_data['remarks'] ?? '') != $remarks) {
                $oldRemarks = $old_data['remarks'] ?? '(kosong)';
                $newRemarks = $remarks ?: '(kosong)';
                $changes[] = "Keterangan: {$oldRemarks} → {$newRemarks}";
            }
            
            if (!empty($changes)) {
                $logRemarks .= implode(', ', $changes);
                
                $logParams = [
                    $usageData['padder_id'],
                    'IN_USE', // Status tetap IN_USE karena hanya edit data pemakaian
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
            
            $_SESSION['success'] = "Data pemakaian berhasil diupdate!";
            header('Location: usage.php');
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
    $sql = "SELECT u.*, p.padder_name, p.status as padder_status
            FROM dbo.pad_t_usage u
            INNER JOIN dbo.pad_m_padder p ON u.padder_id = p.padder_id
            WHERE u.id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $usageData = $row;
        if ($usageData['used_date'] instanceof DateTime) {
            $usageData['used_date'] = $usageData['used_date']->format('Y-m-d');
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
    <title>Edit Pemakaian Padder</title>

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
            border-left: 4px solid #28a745;
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
    </style>
</head>
<body>
<div class="content-wrapper">
    <!-- header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Pemakaian Padder</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="usage.php">Pemakaian Padder</a></li>
                        <li class="breadcrumb-item active">Edit Pemakaian</li>
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
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                            <h3 class="card-title">Form Edit Pemakaian</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($usageData): ?>
                            <!-- Info Box -->
                            <div class="info-box">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Informasi:</strong> Hanya data tanggal, lokasi, mesin, dan keterangan yang dapat diubah. Padder tidak dapat diubah setelah pemasangan. Semua perubahan akan dicatat dalam log sistem.
                            </div>

                            <form method="POST" action="" id="editForm">
                                <!-- Padder Info (Readonly) -->
                                <div class="form-group">
                                    <label>Padder</label>
                                    <input type="text" class="form-control readonly-field" 
                                           value="<?= htmlspecialchars($usageData['padder_id'] . ' - ' . $usageData['padder_name']) ?>" 
                                           readonly>
                                    <small class="form-text text-muted">Padder tidak dapat diubah setelah pemasangan</small>
                                </div>

                                <div class="form-group">
                                    <label>Status Padder Saat Ini</label>
                                    <?php
                                    $statusClass = 'badge-secondary';
                                    switch($usageData['padder_status']) {
                                        case 'READY': $statusClass = 'badge-success'; break;
                                        case 'IN_USE': $statusClass = 'badge-primary'; break;
                                        case 'MAINTENANCE': $statusClass = 'badge-warning'; break;
                                        case 'REPAIRED': $statusClass = 'badge-info'; break;
                                        case 'SCRAP': $statusClass = 'badge-danger'; break;
                                    }
                                    ?>
                                    <div>
                                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($usageData['padder_status']) ?></span>
                                    </div>
                                    <small class="form-text text-muted">Status padder saat ini</small>
                                </div>

                                <!-- Editable Fields -->
                                <div class="form-group">
                                    <label for="used_date" class="required">Tanggal Pemakaian</label>
                                    <input type="date" id="used_date" name="used_date" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($usageData['used_date']) ?>" 
                                           required>
                                    <small class="form-text text-muted">Tanggal ketika padder dipasang di mesin</small>
                                </div>

                                <div class="form-group">
                                    <label for="location" class="required">Lokasi</label>
                                    <select id="location" name="location" class="form-control" required>
                                        <option value="">-- Pilih Lokasi --</option>
                                        <?php foreach ($locationList as $loc): ?>
                                            <option value="<?= htmlspecialchars($loc['location']) ?>" 
                                                <?= ($usageData['location'] == $loc['location']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($loc['location']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Lokasi dimana mesin berada</small>
                                </div>

                                <div class="form-group">
                                    <label for="machine_name" class="required">Nama Mesin</label>
                                    <select id="machine_name" name="machine_name" class="form-control" required>
                                        <option value="">-- Pilih Mesin --</option>
                                        <?php foreach ($machineList as $machine): ?>
                                            <option value="<?= htmlspecialchars($machine['machine_name']) ?>" 
                                                <?= ($usageData['machine_name'] == $machine['machine_name']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($machine['machine_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Nama mesin dimana padder dipasang</small>
                                </div>

                                <div class="form-group">
                                    <label for="remarks">Keterangan</label>
                                    <textarea id="remarks" name="remarks" class="form-control" rows="3" 
                                              placeholder="Catatan tambahan mengenai pemasangan..."><?= htmlspecialchars($usageData['remarks'] ?? '') ?></textarea>
                                    <small class="form-text text-muted">Opsional: catatan khusus mengenai pemasangan</small>
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
                                    <a href="usage.php" class="btn btn-secondary">
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
                                <a href="usage.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                            <h3 class="card-title">Informasi Data</h3>
                        </div>
                        <div class="card-body">
                            <h6><i class="fas fa-database"></i> Detail Data:</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>ID Record:</strong></td>
                                    <td><?= htmlspecialchars($usageData['id'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Padder ID:</strong></td>
                                    <td><?= htmlspecialchars($usageData['padder_id'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Nama Padder:</strong></td>
                                    <td><?= htmlspecialchars($usageData['padder_name'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php if ($usageData): ?>
                                            <span class="badge <?= $statusClass ?>">
                                                <?= htmlspecialchars($usageData['padder_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Lokasi Saat Ini:</strong></td>
                                    <td><?= htmlspecialchars($usageData['location'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Mesin Saat Ini:</strong></td>
                                    <td><?= htmlspecialchars($usageData['machine_name'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal Input:</strong></td>
                                    <td><?= htmlspecialchars($usageData['used_date'] ?? '-') ?></td>
                                </tr>
                            </table>

                            <hr>
                            
                            <h6><i class="fas fa-lightbulb"></i> Tips:</h6>
                            <ul class="small">
                                <li>Pastikan tanggal pemakaian sesuai dengan tanggal sebenarnya</li>
                                <li>Pilih lokasi dan mesin yang tepat</li>
                                <li>Tambahkan keterangan jika ada informasi penting</li>
                                <li>Semua perubahan akan tercatat dalam log sistem</li>
                                <li>Data yang sudah diupdate tidak dapat dikembalikan</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-3">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                            <h3 class="card-title">Aksi Cepat</h3>
                        </div>
                        <div class="card-body text-center">
                            <a href="usage.php" class="btn btn-outline-primary btn-sm mb-2">
                                <i class="fas fa-list"></i> Lihat Semua Data
                            </a>
                            <br>
                            <a href="usage_form.php" class="btn btn-outline-success btn-sm mb-2">
                                <i class="fas fa-plus"></i> Tambah Data Baru
                            </a>
                            <br>
                            <?php if ($usageData && $permissions['CanDelete'] == 1): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btnDelete">
                                    <i class="fas fa-trash"></i> Hapus Data Ini
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Log Information -->
                    <div class="card mt-3">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                            <h3 class="card-title">Informasi Log</h3>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Setiap perubahan data akan dicatat dalam sistem log dengan detail:
                            </p>
                            <ul class="small">
                                <li>Tanggal dan waktu perubahan</li>
                                <li>User yang melakukan perubahan</li>
                                <li>Field yang diubah</li>
                                <li>Nilai sebelum dan sesudah</li>
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
        used_date: '<?= htmlspecialchars($usageData['used_date']) ?>',
        location: '<?= htmlspecialchars($usageData['location'] ?? '') ?>',
        machine_name: '<?= htmlspecialchars($usageData['machine_name'] ?? '') ?>',
        remarks: '<?= htmlspecialchars($usageData['remarks'] ?? '') ?>'
    };

    // Reset form handler
    $('#btnReset').on('click', function() {
        // Reset form ke nilai semula
        document.forms[0].reset();
        
        // Set nilai default untuk select fields
        $('#location').val('<?= htmlspecialchars($usageData['location'] ?? '') ?>');
        $('#machine_name').val('<?= htmlspecialchars($usageData['machine_name'] ?? '') ?>');
        $('#remarks').val('<?= htmlspecialchars($usageData['remarks'] ?? '') ?>');
        
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
            title: 'Hapus Data Pemakaian?',
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
                window.location.href = 'delete_usage.php?id=<?= $id ?>';
            }
        });
    });

    // Function to detect changes and show summary
    function detectChanges() {
        const currentValues = {
            used_date: $('#used_date').val(),
            location: $('#location').val(),
            machine_name: $('#machine_name').val(),
            remarks: $('#remarks').val()
        };

        const changes = [];
        
        // Check each field for changes
        if (currentValues.used_date !== originalValues.used_date) {
            changes.push(`<strong>Tanggal:</strong> ${originalValues.used_date} → ${currentValues.used_date}`);
        }
        
        if (currentValues.location !== originalValues.location) {
            changes.push(`<strong>Lokasi:</strong> ${originalValues.location} → ${currentValues.location}`);
        }
        
        if (currentValues.machine_name !== originalValues.machine_name) {
            changes.push(`<strong>Mesin:</strong> ${originalValues.machine_name} → ${currentValues.machine_name}`);
        }
        
        if (currentValues.remarks !== originalValues.remarks) {
            const oldRemarks = originalValues.remarks || '(kosong)';
            const newRemarks = currentValues.remarks || '(kosong)';
            changes.push(`<strong>Keterangan:</strong> ${oldRemarks} → ${newRemarks}`);
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
        var usedDate = $('#used_date').val();
        var location = $('#location').val();
        var machineName = $('#machine_name').val();
        
        if (!usedDate || !location || !machineName) {
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
        if ($('#used_date').val() !== originalValues.used_date) changes.push('Tanggal');
        if ($('#location').val() !== originalValues.location) changes.push('Lokasi');
        if ($('#machine_name').val() !== originalValues.machine_name) changes.push('Mesin');
        if ($('#remarks').val() !== originalValues.remarks) changes.push('Keterangan');
        
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
    $('#used_date, #location, #machine_name, #remarks').on('change input', detectChanges);

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
        $('#used_date').focus();
        
        // Initialize change detection
        detectChanges();
    });

})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
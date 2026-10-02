<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

if (!$conn) {
    die("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'];
$menuId = 44; // Ganti dengan MenuId untuk emp

// Hak akses CanDelete
$sql = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canDelete = false;

if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
} else {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $canDelete = $row && $row['CanDelete'] == 1;
    sqlsrv_free_stmt($stmt);
}

if (!$canDelete) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: emp.php');
    exit;
}

// Get NIK from URL parameter
$nik = isset($_GET['nik']) ? $_GET['nik'] : null;

if (!$nik) {
    $_SESSION['error'] = "NIK tidak valid";
    header('Location: emp.php');
    exit;
}

// Fetch employee data for confirmation - termasuk foto_path
$sql = "SELECT nik, nama_lengkap, foto_path FROM dbo.m_emp WHERE nik = ?";
$params = [$nik];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Error fetching employee data: " . print_r(sqlsrv_errors(), true));
}

$employee = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$employee) {
    $_SESSION['error'] = "Data karyawan dengan NIK $nik tidak ditemukan";
    header('Location: emp.php');
    exit;
}

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete'])) {
        
        // Hapus file foto jika ada
        $photoDeleted = false;
        if (!empty($employee['foto_path']) && file_exists("../../" . $employee['foto_path'])) {
            try {
                if (unlink("../../" . $employee['foto_path'])) {
                    $photoDeleted = true;
                } else {
                    error_log("Gagal menghapus file foto: ../../" . $employee['foto_path']);
                }
            } catch (Exception $e) {
                error_log("Error menghapus file foto: " . $e->getMessage());
            }
        }
        
        // Perform the delete operation
        $sql = "DELETE FROM dbo.m_emp WHERE nik = ?";
        $params = [$nik];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $_SESSION['error'] = "Gagal menghapus data: " . print_r(sqlsrv_errors(), true);
            header("Location: delete_emp.php?nik=$nik");
            exit;
        } else {
            $message = "Data karyawan {$employee['nama_lengkap']} (NIK: $nik) berhasil dihapus.";
            if ($photoDeleted) {
                $message .= " File foto juga berhasil dihapus.";
            } elseif (!empty($employee['foto_path'])) {
                $message .= " Namun file foto gagal dihapus dari server.";
            }
            
            $_SESSION['success'] = $message;
            header('Location: emp.php');
            exit;
        }
    } else {
        // User canceled the deletion
        $_SESSION['info'] = "Penghapusan data karyawan {$employee['nama_lengkap']} (NIK: $nik) dibatalkan.";
        header('Location: emp.php');
        exit;
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Karyawan</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <style>
        .photo-info {
            background-color: #f8f9fa;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .file-path {
            font-family: monospace;
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">Hapus Karyawan</h1>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                        Anda akan menghapus data karyawan berikut:
                        <ul class="mt-2 mb-3">
                            <li><strong>NIK:</strong> <?= htmlspecialchars($employee['nik']) ?></li>
                            <li><strong>Nama:</strong> <?= htmlspecialchars($employee['nama_lengkap']) ?></li>
                        </ul>
                        
                        <?php if (!empty($employee['foto_path'])): ?>
                        <div class="photo-info">
                            <i class="fas fa-image mr-2"></i>
                            <strong>File foto juga akan dihapus:</strong>
                            <div class="file-path mt-1"><?= htmlspecialchars($employee['foto_path']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <p class="mt-3 mb-0"><strong>Data yang dihapus tidak dapat dikembalikan!</strong></p>
                    </div>
                    
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="nik" value="<?= htmlspecialchars($employee['nik']) ?>">
                        
                        <div class="form-group">
                            <label for="confirm">Konfirmasi penghapusan:</label>
                            <div class="custom-control custom-checkbox">
                                <input class="custom-control-input" type="checkbox" id="confirm" name="confirm" required>
                                <label for="confirm" class="custom-control-label">Ya, saya yakin ingin menghapus data ini dan semua file terkait</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <form method="POST" id="actionForm">
                        <input type="hidden" name="nik" value="<?= htmlspecialchars($employee['nik']) ?>">
                        <button type="submit" name="confirm_delete" class="btn btn-danger" id="deleteBtn" disabled>
                            <i class="fas fa-trash"></i> Hapus Data
                        </button>
                        <a href="emp.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Enable/disable delete button based on checkbox
    $('#confirm').change(function() {
        $('#deleteBtn').prop('disabled', !this.checked);
    });
    
    // Confirm before deletion
    $('#actionForm').on('submit', function(e) {
        if ($('#confirm').is(':checked')) {
            const employeeName = '<?= htmlspecialchars($employee['nama_lengkap']) ?>';
            const nik = '<?= htmlspecialchars($employee['nik']) ?>';
            
            if (!confirm(`Apakah Anda benar-benar yakin ingin menghapus data karyawan:\n\n${employeeName}\nNIK: ${nik}\n\nTindakan ini tidak dapat dibatalkan!`)) {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>

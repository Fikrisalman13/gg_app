<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Check permissions
$groupId = $_SESSION['GroupId'];
$menuId = 71; // MenuId untuk e-dokumen

$sql = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !sqlsrv_fetch($stmt) || sqlsrv_get_field($stmt, 0) != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus dokumen.";
    header('Location: e_dokumen.php');
    exit;
}

// Get document ID from URL
$id_dok = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_dok <= 0) {
    $_SESSION['error'] = "Dokumen tidak valid!";
    header('Location: e_dokumen.php');
    exit;
}

// Fetch document data with additional joins for better logging
$sql = "SELECT d.*, k.nama_kategori, m_dept.dept 
        FROM dbo.dokumen AS d
        LEFT JOIN dbo.m_kategori_dok AS k ON d.id_kategori = k.id_kategori
        LEFT JOIN dbo.m_dept ON d.id_dept = m_dept.id_dept
        WHERE d.id_dok = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_dok]);

if ($stmt === false) {
    $_SESSION['error'] = "Error saat mengambil data dokumen: " . print_r(sqlsrv_errors(), true);
    header('Location: e_dokumen.php');
    exit;
}

if (!$document = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $_SESSION['error'] = "Dokumen tidak ditemukan!";
    header('Location: e_dokumen.php');
    exit;
}

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token keamanan tidak valid!";
        header("Location: delete_dokumen.php?id=$id_dok");
        exit;
    }

    // Validasi input konfirmasi
    $confirm = isset($_POST['confirm']) ? strtoupper(trim($_POST['confirm'])) : '';
    if ($confirm !== 'HAPUS') {
        $_SESSION['error'] = "Konfirmasi penghapusan tidak valid! Harap ketik 'HAPUS' untuk konfirmasi.";
        header("Location: delete_dokumen.php?id=$id_dok");
        exit;
    }

    try {
        // Mulai transaksi
        if (sqlsrv_begin_transaction($conn) === false) {
            throw new Exception("Gagal memulai transaksi: " . print_r(sqlsrv_errors(), true));
        }

        // Delete file if exists
        if (!empty($document['file_pdf'])) {
            $file_path = realpath('../../' . ltrim($document['file_pdf'], '/'));
            
            // Verifikasi path file untuk keamanan (directory traversal protection)
            $base_dir = realpath('../../');
            if (strpos($file_path, $base_dir) !== 0 || !file_exists($file_path)) {
                // Log attempt to delete invalid file path
                error_log("Attempt to delete file with invalid path: " . $document['file_pdf']);
            } elseif (!unlink($file_path)) {
                throw new Exception("Gagal menghapus file PDF dari server");
            }
        }

        // Log informasi sebelum menghapus
        $log_message = sprintf(
            "User %s menghapus dokumen ID %d: %s (Kategori: %s, Dept: %s)",
            $_SESSION['UserName'],
            $id_dok,
            $document['nama_dokumen'],
            $document['nama_kategori'] ?? 'Unknown',
            $document['dept'] ?? 'Unknown'
        );
        error_log($log_message);

        // Delete document from database
        $sql = "DELETE FROM dbo.dokumen WHERE id_dok = ?";
        $stmt = sqlsrv_query($conn, $sql, [$id_dok]);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Gagal menghapus data dari database: " . print_r($errors, true));
        }
        
        // Check rows affected
        $rows_affected = sqlsrv_rows_affected($stmt);
        if ($rows_affected === false || $rows_affected < 1) {
            throw new Exception("Tidak ada data yang terhapus dari database");
        }
        
        // Commit transaksi jika berhasil
        if (sqlsrv_commit($conn) === false) {
            throw new Exception("Gagal commit transaksi: " . print_r(sqlsrv_errors(), true));
        }
        
        $_SESSION['success'] = "Dokumen berhasil dihapus!";
        header('Location: e_dokumen.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        if (sqlsrv_errors()) {
            sqlsrv_rollback($conn);
        }
        
        // Log error untuk debugging
        error_log("Error saat menghapus dokumen ID $id_dok: " . $e->getMessage());
        
        $_SESSION['error'] = "Gagal menghapus dokumen. " . $e->getMessage();
        header("Location: delete_dokumen.php?id=$id_dok");
        exit;
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Dokumen</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Konfirmasi Penghapusan Dokumen</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="e_dokumen.php">E-Dokumen</a></li>
                        <li class="breadcrumb-item active">Hapus Dokumen</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 offset-md-3">
                    <div class="card card-danger">
                        <div class="card-header">
                            <h3 class="card-title">Konfirmasi Penghapusan</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                                Anda akan menghapus dokumen berikut secara permanen. Tindakan ini tidak dapat dibatalkan.
                            </div>

                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Nama Dokumen</th>
                                    <td><?= htmlspecialchars($document['nama_dokumen']) ?></td>
                                </tr>
                                <tr>
                                    <th>Kode Dokumen</th>
                                    <td><?= htmlspecialchars($document['kode_dok_seq'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Revisi</th>
                                    <td><?= htmlspecialchars($document['revisi'] ?? '1') ?></td>
                                </tr>
                                <tr>
                                    <th>File</th>
                                    <td>
                                        <?php if (!empty($document['file_pdf'])): ?>
                                            <a href="<?= htmlspecialchars($document['file_pdf']) ?>" target="_blank">
                                                <i class="fas fa-file-pdf text-danger"></i> Lihat File
                                            </a>
                                        <?php else: ?>
                                            Tidak ada file
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="form-group">
                                    <label for="confirm">Ketik <strong>HAPUS</strong> untuk konfirmasi:</label>
                                    <input type="text" class="form-control" id="confirm" name="confirm" 
                                           required autocomplete="off" placeholder="HAPUS">
                                </div>
                                <div class="form-group text-center">
                                    <button type="submit" class="btn btn-danger mr-2">
                                        <i class="fas fa-trash"></i> Hapus Permanen
                                    </button>
                                    <a href="e_dokumen.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Batal
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
</body>
</html>
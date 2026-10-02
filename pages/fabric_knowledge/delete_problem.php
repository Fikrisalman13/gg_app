<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Cek Login ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: ../../login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$groupId = $_SESSION['GroupId'];
$menuId = 123; // Menu ID halaman Problem Knowledge

// ====== Cek Koneksi ======
if (!$conn) {
    die("Koneksi database gagal: " . print_r(sqlsrv_errors(), true));
}

// ====== Cek Hak Akses Delete ======
$sql = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$canDelete = false;
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $canDelete = $row['CanDelete'] == 1;
}
if ($stmt) sqlsrv_free_stmt($stmt);

if (!$canDelete) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus data.";
    header('Location: problem_list.php');
    exit;
}

// ====== Validasi ID ======
$id_problem = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_problem <= 0) {
    $_SESSION['error'] = "ID masalah tidak valid!";
    header('Location: problem_list.php');
    exit;
}

// ====== Ambil Data Problem ======
$sql = "SELECT p.id_problem, p.judul, p.nocp, p.created_at, 
               k.nama_kategori, t.nama_tag, s.status
        FROM dbo.fab_m_problem p
        LEFT JOIN dbo.fab_m_kategori k ON p.id_kategori = k.id_kategori
        LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag
        LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem
        WHERE p.id_problem = ?";
$params = [$id_problem];
$stmt = sqlsrv_query($conn, $sql, $params);
$problem = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$problem) {
    $_SESSION['error'] = "Data masalah tidak ditemukan!";
    header('Location: problem_list.php');
    exit;
}

// ====== Proses Hapus ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        sqlsrv_begin_transaction($conn);

        // Ambil file lampiran
        $attachments = [];
        $stmt = sqlsrv_query($conn, "SELECT path_file FROM dbo.fab_t_attachment WHERE id_problem = ?", [$id_problem]);
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $attachments[] = $row['path_file'];
            }
        }

        // Urutan hapus penting
        $deleteTables = ['fab_t_attachment', 'fab_t_history', 'fab_m_solution', 'fab_m_problem'];
        foreach ($deleteTables as $tbl) {
            $sqlDel = "DELETE FROM dbo.$tbl WHERE id_problem = ?";
            $stmtDel = sqlsrv_query($conn, $sqlDel, [$id_problem]);
            if ($stmtDel === false) {
                throw new Exception("Gagal menghapus data dari tabel $tbl");
            }
        }

        // Hapus file fisik
        $deletedFiles = 0;
        foreach ($attachments as $path) {
            $filePath = realpath(__DIR__ . '/../../' . $path);
            if ($filePath && file_exists($filePath) && unlink($filePath)) {
                $deletedFiles++;
            }
        }

        sqlsrv_commit($conn);
        $_SESSION['success'] = "Data masalah '" . htmlspecialchars($problem['judul']) . "' berhasil dihapus. "
                              . ($deletedFiles > 0 ? "($deletedFiles file lampiran dihapus)" : "");
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
    }

    header('Location: problem_list.php');
    exit;
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hapus Masalah</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Hapus Masalah</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="problem_list.php">Daftar Masalah</a></li>
                        <li class="breadcrumb-item active">Hapus Masalah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title">Konfirmasi Penghapusan Masalah</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan!</h5>
                        Anda akan menghapus data masalah berikut. Data yang sudah dihapus tidak dapat dikembalikan.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">Judul Masalah</th>
                                <td><?= htmlspecialchars($problem['judul']) ?></td>
                            </tr>
                            <tr>
                                <th>No CP</th>
                                <td><?= htmlspecialchars($problem['nocp'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Kategori</th>
                                <td><?= htmlspecialchars($problem['nama_kategori'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td><?= htmlspecialchars($problem['status'] ?? 'Open') ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal Dibuat</th>
                                <td>
                                    <?= $problem['created_at'] instanceof DateTime
                                        ? $problem['created_at']->format('d-m-Y H:i')
                                        : date('d-m-Y H:i', strtotime($problem['created_at'])) ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="confirm_delete" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Ya, Hapus Data
                        </button>
                        <a href="problem_detail.php?id=<?= $id_problem ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Lihat Detail
                        </a>
                        <a href="problem_list.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>

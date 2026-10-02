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

$themeColor = $_SESSION['Theme'] ?? 'primary';
date_default_timezone_set('Asia/Jakarta');

// ---- MENU ID Fingerprint ----
define('FINGERPRINT_MENU_ID', 96);

// ---- Cek Permission ----
function checkUserPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    if ($stmt === false) return ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row ?: ['CanView'=>0,'CanAdd'=>0,'CanEdit'=>0,'CanDelete'=>0];
}

$permissions = checkUserPermissions($conn, $_SESSION['GroupId'], FINGERPRINT_MENU_ID);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak akses untuk mengedit mesin Fingerprint.";
    header('Location: fingerprint.php');
    exit;
}

// ---- Ambil ID mesin ----
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    $_SESSION['error'] = "ID mesin tidak valid.";
    header('Location: fingerprint.php');
    exit;
}

// ---- Ambil data mesin ----
$sql = "SELECT * FROM m_fingerprint WHERE id = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$mesin = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$mesin) {
    $_SESSION['error'] = "Mesin fingerprint tidak ditemukan.";
    header('Location: fingerprint.php');
    exit;
}

// ---- Proses Update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_mesin      = trim($_POST['nama_mesin'] ?? '');
    $ip_address      = trim($_POST['ip_address'] ?? '');
    $comm_key        = $_POST['comm_key'] ?? '';
    $lokasi          = trim($_POST['lokasi'] ?? '');
    $deskripsi       = trim($_POST['deskripsi'] ?? '');

    // Validasi field wajib
    if (strlen($nama_mesin) === 0 || strlen($ip_address) === 0 || !isset($comm_key)) {
        $_SESSION['error'] = "Nama mesin, IP Address, dan Comm Key wajib diisi.";
    } else {
        $sql = "UPDATE m_fingerprint 
                SET nama_mesin = ?, ip_address = ?, comm_key = ?, lokasi = ?, deskripsi = ?, UpdBy = ?, UpdDate = GETDATE()
                WHERE id = ?";
        $params = [$nama_mesin, $ip_address, $comm_key, $lokasi, $deskripsi, $_SESSION['UserName'], $id];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $_SESSION['success'] = "Mesin Fingerprint berhasil diperbarui.";
            header("Location: fingerprint.php");
            exit;
        } else {
            $_SESSION['error'] = "Gagal menyimpan data: ".print_r(sqlsrv_errors(), true);
        }
    }
}

$success_message = $_SESSION['success'] ?? '';
$error_message   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Edit Mesin Fingerprint</title>
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between">
        <h1>Edit Mesin Fingerprint</h1>
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
          <li class="breadcrumb-item"><a href="fingerprint.php">Fingerprint</a></li>
          <li class="breadcrumb-item active">Edit</li>
        </ol>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title">Form Edit Mesin</h3>
          </div>
          <div class="card-body">
            <form method="post">
              <div class="form-group">
                <label>Nama Mesin <span class="text-danger">*</span></label>
                <input type="text" name="nama_mesin" class="form-control" required
                       value="<?= htmlspecialchars($mesin['nama_mesin']) ?>">
              </div>
              
              <div class="form-group">
                <label>IP Address <span class="text-danger">*</span></label>
                <input type="text" name="ip_address" class="form-control" required
                       value="<?= htmlspecialchars($mesin['ip_address']) ?>">
              </div>
              <div class="form-group">
                <label>Comm Key <span class="text-danger">*</span></label>
                <input type="number" name="comm_key" class="form-control" required
                       value="<?= htmlspecialchars($mesin['comm_key']) ?>">
                <small class="text-muted">Nilai 0 diperbolehkan untuk comm key</small>
              </div>
              <div class="form-group">
                <label>Lokasi</label>
                <input type="text" name="lokasi" class="form-control"
                       value="<?= htmlspecialchars($mesin['lokasi'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="deskripsi" class="form-control"><?= htmlspecialchars($mesin['deskripsi'] ?? '') ?></textarea>
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>"><i class="fas fa-save"></i> Simpan</button>
                <a href="fingerprint.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
<?php if ($success_message): ?>
  Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text: <?= json_encode($success_message) ?>,
    timer: 2000,
    showConfirmButton: false
  });
<?php endif; ?>
<?php if ($error_message): ?>
  Swal.fire({
    icon: 'error',
    title: 'Gagal',
    text: <?= json_encode($error_message) ?>
  });
<?php endif; ?>
</script>
</body>
</html>
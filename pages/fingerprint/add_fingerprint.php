<?php
// ===================================================
// 1. INISIALISASI DAN KONFIGURASI
// ===================================================

// Mulai session
session_start();

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// ===================================================
// 3. VALIDASI AUTENTIKASI
// ===================================================
// *Jika belum, redirect ke halaman login
if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// ===================================================
// 4. KONEKSI DATABASE
// ===================================================
/**
 * Membuat koneksi ke database
 * Menghentikan eksekusi jika koneksi gagal
 */
include '../../koneksi.php';
if (!$conn) {
    $errors = sqlsrv_errors();
    error_log("Koneksi database gagal: " . print_r($errors, true));
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// ===================================================
// 5. IMPORT DEPENDENSI
// ===================================================
// Import layout dan komponen
include '../../includes/header.php';
include '../../includes/sidebar.php';
// Import konstanta dan permission
include '../../includes/menu_constants.php';
include '../../includes/permissions.php';

// ===================================================
// 6. VALIDASI IZIN AKSES
// ===================================================
requireAdd($conn, MENU_SETTING_FINGERPRINT);
// ===================================================
// 7. PROSES SIMPAN
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_mesin     = trim($_POST['nama_mesin'] ?? '');
    $ip_address     = trim($_POST['ip_address'] ?? '');
    $comm_key       = $_POST['comm_key'] ?? '';
    $lokasi         = trim($_POST['lokasi'] ?? '');
    $deskripsi      = trim($_POST['deskripsi'] ?? '');

    // Validasi field wajib - PERBAIKAN: gunakan !strlen() untuk string dan isset() untuk number
    if (strlen($nama_mesin) === 0 || strlen($ip_address) === 0 || !isset($comm_key)) {
        $_SESSION['error'] = "Nama mesin, IP Address, dan Comm Key wajib diisi.";
    } else {
        $sql = "INSERT INTO m_fingerprint 
                (nama_mesin, ip_address, comm_key, lokasi, deskripsi, status, CreatedBy, CreatedDate) 
                VALUES (?, ?, ?, ?, ?, 'Disconnected', ?, GETDATE())";
        $params = [$nama_mesin, $ip_address, $comm_key, $lokasi, $deskripsi, $_SESSION['UserName']];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $_SESSION['success'] = "Mesin Fingerprint berhasil ditambahkan.";
            header("Location: fingerprint.php");
            exit;
        } else {
            $_SESSION['error'] = "Gagal menyimpan data: " . print_r(sqlsrv_errors(), true);
        }
    }
}

$success_message = $_SESSION['success'] ?? '';
$error_message   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
// ===================================================
// 8. MENDAPATKAN DATA
// ===================================================
// Mendapatkan tema dari session untuk konsistensi UI
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!-- ===================================================
    9. HTML: STRUKTUR HALAMAN
======================================================= -->
<div class="wrapper">
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid d-flex justify-content-between">
        <h1>Tambah Mesin Fingerprint</h1>
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
          <li class="breadcrumb-item"><a href="fingerprint.php">Fingerprint</a></li>
          <li class="breadcrumb-item active">Tambah</li>
        </ol>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header bg-<?= htmlspecialchars($themeColor) ?>">
            <h3 class="card-title">Form Tambah Mesin</h3>
          </div>
          <div class="card-body">
            <form method="post">
              <div class="form-group">
                <label>Nama Mesin <span class="text-danger">*</span></label>
                <input type="text" name="nama_mesin" class="form-control" required value="<?= isset($_POST['nama_mesin']) ? htmlspecialchars($_POST['nama_mesin']) : '' ?>">
              </div>
              <div class="form-group">
                <label>IP Address <span class="text-danger">*</span></label>
                <input type="text" name="ip_address" class="form-control" required value="<?= isset($_POST['ip_address']) ? htmlspecialchars($_POST['ip_address']) : '' ?>">
              </div>
              <div class="form-group">
                <label>Comm Key <span class="text-danger">*</span></label>
                <input type="number" name="comm_key" class="form-control" required value="<?= isset($_POST['comm_key']) ? htmlspecialchars($_POST['comm_key']) : '0' ?>">
                <small class="text-muted">Nilai 0 diperbolehkan untuk comm key</small>
              </div>
              
              <div class="form-group">
                <label>Lokasi</label>
                <input type="text" name="lokasi" class="form-control" value="<?= isset($_POST['lokasi']) ? htmlspecialchars($_POST['lokasi']) : '' ?>">
              </div>
              <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="deskripsi" class="form-control"><?= isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '' ?></textarea>
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
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>

<!-- ===================================================
    12. JAVASCRIPT CUSTOM
======================================================= -->
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

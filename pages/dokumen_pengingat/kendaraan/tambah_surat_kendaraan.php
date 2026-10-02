<?php
// ======================================================
// tambah_surat_kendaraan.php — FINAL FIXED + SWEETALERT
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------
// CORE INCLUDE
// ------------------------------------------------------
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/sidebar.php';

// ------------------------------------------------------
// PERMISSION
// ------------------------------------------------------
$menuId = 171;
requireAdd($conn, $menuId);

$theme = $_SESSION['Theme'] ?? 'primary';
$error = null;

// ------------------------------------------------------
// USER LOGIN
// ------------------------------------------------------
$username = $_SESSION['UserName'] ?? null;
if (!$username) {
    die('User belum login');
}

// ------------------------------------------------------
// AMBIL EmpId DARI USER LOGIN
// ------------------------------------------------------
$empId = null;
$stmtEmp = sqlsrv_query(
    $conn,
    "SELECT EmpId FROM dbo.SMUserMs WHERE UserName = ?",
    [$username]
);

if ($stmtEmp && $r = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
    $empId = $r['EmpId'];
}
sqlsrv_free_stmt($stmtEmp);

if (!$empId) {
    die('EmpId tidak ditemukan untuk user login');
}

// ------------------------------------------------------
// LOAD BAGIAN SESUAI EMP LOGIN
// ------------------------------------------------------
$bagian = [];
$stmtBag = sqlsrv_query(
    $conn,
    "SELECT 
        b.id_bag,
        b.bagian
     FROM dbo.m_emp e
     INNER JOIN dbo.m_bag b ON e.id_bag = b.id_bag
     WHERE e.id_emp = ?",
    [$empId]
);

if ($stmtBag === false) {
    die(print_r(sqlsrv_errors(), true));
}

while ($r = sqlsrv_fetch_array($stmtBag, SQLSRV_FETCH_ASSOC)) {
    $bagian[] = $r;
}
sqlsrv_free_stmt($stmtBag);

// ------------------------------------------------------
// SUBMIT FORM
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $no_polisi       = trim($_POST['no_polisi']);
    $jenis_kendaraan = trim($_POST['jenis_kendaraan']);
    $nama_kendaraan  = trim($_POST['nama_kendaraan']);
    $nama_pemilik    = trim($_POST['nama_pemilik']);
    $expire_date     = $_POST['expire_date'];
    $keterangan      = $_POST['keterangan'] ?? null;
    $email_reminder  = $_POST['email_reminder'] ?? null;
    $no_whatsapp     = $_POST['no_whatsapp'] ?? null;
    $bagian_id       = $_POST['bagian_id'] ?? null;

    // ------------------------------
    // VALIDASI FILE
    // ------------------------------
    if (empty($_FILES['file_kendaraan']['name'])) {
        $error = 'File kendaraan wajib diupload';
    } else {
        $ext = strtolower(pathinfo($_FILES['file_kendaraan']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $error = 'File harus PDF';
        } else {

            $uploadDir = __DIR__ . '/uploads/kendaraan/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['file_kendaraan']['name']);
            $dest = $uploadDir . $fileName;

            if (!move_uploaded_file($_FILES['file_kendaraan']['tmp_name'], $dest)) {
                $error = 'Gagal upload file';
            }
        }
    }

    // ------------------------------
    // INSERT DATABASE
    // ------------------------------
    if (!$error) {

        $sql = "
        INSERT INTO dbo.dr_surat_kendaraan (
            no_polisi,
            jenis_kendaraan,
            nama_kendaraan,
            nama_pemilik,
            expire_date,
            file_kendaraan,
            keterangan,
            email_reminder,
            no_whatsapp,
            bagian_id,
            created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $params = [
            $no_polisi,
            $jenis_kendaraan,
            $nama_kendaraan,
            $nama_pemilik,
            $expire_date,
            $fileName,
            $keterangan,
            $email_reminder,
            $no_whatsapp,
            $bagian_id,
            $username
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            @unlink($dest);
            $error = print_r(sqlsrv_errors(), true);
        } else {
            // FLAG SUKSES
            $success = true;
        }
    }
}
?>

<div class="content-wrapper">
<section class="content-header">
    <div class="container-fluid">
        <h1>Tambah Surat Kendaraan</h1>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-outline card-<?= htmlspecialchars($theme) ?>">


<form method="post" enctype="multipart/form-data">
<div class="card-body">

<div class="form-group">
    <label>No Polisi</label>
    <input type="text" name="no_polisi" class="form-control" required>
</div>

<div class="form-group">
    <label>Jenis Kendaraan</label>
    <input type="text" name="jenis_kendaraan" class="form-control" required>
</div>

<div class="form-group">
    <label>Nama Kendaraan</label>
    <input type="text" name="nama_kendaraan" class="form-control" required>
</div>

<div class="form-group">
    <label>Nama Pemilik</label>
    <input type="text" name="nama_pemilik" class="form-control" required>
</div>

<div class="form-group">
    <label>Expire Date</label>
    <input type="date" name="expire_date" class="form-control" required>
</div>

<div class="form-group">
    <label>File Kendaraan (PDF)</label>
    <input type="file" name="file_kendaraan" class="form-control" accept=".pdf" required>
</div>

<div class="form-group">
    <label>Keterangan</label>
    <textarea name="keterangan" class="form-control"></textarea>
</div>

<div class="form-group">
    <label>Bagian (Sesuai Login)</label>
    <select name="bagian_id" class="form-control" required> 
        <option value="">-- Pilih Bagian --</option>
        <?php foreach ($bagian as $b): ?>
            <option value="<?= $b['id_bag'] ?>">
                <?= htmlspecialchars($b['bagian']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label>Email Reminder</label>
    <input type="email" name="email_reminder" class="form-control">
</div>

<div class="form-group">
    <label>No Whatsapp</label>
    <input type="text" name="no_whatsapp" class="form-control">
</div>

</div>

<div class="card-footer">
    <button class="btn btn-primary">
        <i class="fas fa-save"></i> Simpan
    </button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</div>

</form>
</div>
</div>
</section>
</div>

<!-- SWEETALERT -->
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<?php if (!empty($success)): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text: 'Surat kendaraan berhasil ditambahkan',
    timer: 2000,
    showConfirmButton: false
}).then(() => {
    window.location.href = 'index.php';
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

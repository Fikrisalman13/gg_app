<?php
// ======================================================
// tambah_dokumen_kontrak.php — FINAL (LOGIN + BAGIAN + CREATED_BY)
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
$menuId = 166;
requireAdd($conn, $menuId);

$theme   = $_SESSION['Theme'] ?? 'primary';
$error   = null;
$success = false;

// ------------------------------------------------------
// USER LOGIN
// ------------------------------------------------------
$username = $_SESSION['UserName'] ?? null;
if (!$username) {
    echo "<script>
        alert('Session login tidak ditemukan');
        window.location.href='/gg_app/login.php';
    </script>";
    exit;
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
// LOAD BAGIAN SESUAI LOGIN
// ------------------------------------------------------
$bagian = [];
$stmtBag = sqlsrv_query(
    $conn,
    "SELECT 
        b.id_bag AS id,
        b.bagian AS nama_bagian
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

    $nama_vendor     = trim($_POST['nama_vendor'] ?? '');
    $nama_pekerjaan  = trim($_POST['nama_pekerjaan'] ?? '');
    $no_kontrak      = trim($_POST['no_kontrak'] ?? '');
    $expire_date     = $_POST['expire_date'] ?? null;
    $keterangan      = $_POST['keterangan'] ?? null;
    $email_reminder  = $_POST['email_reminder'] ?? null;
    $no_whatsapp     = $_POST['no_whatsapp'] ?? null;

    // bagian_id dipaksa dari login
    $bagian_id = $bagian[0]['id'] ?? null;

    // ----------------------------
    // VALIDASI FILE
    // ----------------------------
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File wajib diupload';
    } else {
        $allowedExt = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $error = 'File harus PDF / DOC / DOCX';
        }
    }

    // ----------------------------
    // UPLOAD FILE
    // ----------------------------
    if (!$error) {

        $uploadDir = __DIR__ . '/uploads/kontrak/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['file']['name']);
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
            $error = 'Gagal upload file';
        }
    }

    // ----------------------------
    // INSERT DATABASE
    // ----------------------------
    if (!$error) {

        $sql = "
            INSERT INTO dr_kontrak
            (
                nama_vendor,
                nama_pekerjaan,
                no_kontrak,
                expire_date,
                file_path,
                keterangan,
                bagian_id,
                email_reminder,
                no_whatsapp,
                created_by,
                createdate
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())
        ";

        $params = [
            $nama_vendor,
            $nama_pekerjaan,
            $no_kontrak,
            $expire_date,
            $fileName,
            $keterangan,
            $bagian_id,
            $email_reminder,
            $no_whatsapp,
            $username
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            @unlink($filePath);
            $error = print_r(sqlsrv_errors(), true);
        } else {
            $success = true;
        }
    }
}
?>

<div class="content-wrapper">

<section class="content-header">
    <div class="container-fluid">
        <h1>Tambah Dokumen Kontrak</h1>
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
    <label>Nama Vendor</label>
    <input type="text" name="nama_vendor" class="form-control" required>
</div>

<div class="form-group">
    <label>Nama Pekerjaan</label>
    <input type="text" name="nama_pekerjaan" class="form-control" required>
</div>

<div class="form-group">
    <label>No Kontrak</label>
    <input type="text" name="no_kontrak" class="form-control" required>
</div>

<div class="form-group">
    <label>Expire Date</label>
    <input type="date" name="expire_date" class="form-control" required>
</div>

<div class="form-group">
    <label>File (PDF / DOC / DOCX)</label>
    <input type="file" name="file" class="form-control" required>
</div>

<div class="form-group">
    <label>Keterangan</label>
    <textarea name="keterangan" class="form-control"></textarea>
</div>

<div class="form-group">
    <label>Bagian (Sesuai Login)</label>
    <input type="text"
           class="form-control"
           value="<?= htmlspecialchars($bagian[0]['nama_bagian'] ?? '-') ?>"
           readonly>
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
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Simpan
    </button>
    <a href="/gg_app/pages/dokumen_pengingat/kontrak/index.php" class="btn btn-secondary">Batal</a>
</div>

</form>
</div>
</div>
</section>
</div>

<!-- SWEETALERT -->
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text: 'Dokumen kontrak berhasil ditambahkan',
    timer: 2000,
    showConfirmButton: false
}).then(() => {
    window.location.href = '/gg_app/pages/dokumen_pengingat/kontrak/index.php';
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

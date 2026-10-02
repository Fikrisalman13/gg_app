<?php
// ======================================================
// edit_dokumen_kontrak.php — FINAL (BAGIAN BY USER + UPDATED_BY)
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

// ------------------------------------------------------
// CORE INCLUDE
// ------------------------------------------------------
require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once __DIR__ . '/../../../includes/sidebar.php';

// ------------------------------------------------------
// PERMISSION
// ------------------------------------------------------
$menuId = 166;
requireEdit($conn, $menuId);

$theme    = $_SESSION['Theme'] ?? 'primary';
$username = $_SESSION['UserName'] ?? null;

if (!$username) {
    echo "<script>
        alert('Session user tidak ditemukan');
        window.location.href='/gg_app/login.php';
    </script>";
    exit;
}

// ------------------------------------------------------
// VALIDASI ID
// ------------------------------------------------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "<script>alert('ID tidak valid');window.location.href='index.php';</script>";
    exit;
}

// ------------------------------------------------------
// LOAD DATA KONTRAK
// ------------------------------------------------------
$stmt = sqlsrv_query($conn, "SELECT * FROM dr_kontrak WHERE id = ?", [$id]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    echo "<script>alert('Data tidak ditemukan');window.location.href='index.php';</script>";
    exit;
}

// ------------------------------------------------------
// AMBIL BAGIAN USER LOGIN
// ------------------------------------------------------
$idDeptUser = null;
$stmtDeptUser = sqlsrv_query(
    $conn,
    "SELECT b.id_bag
     FROM dbo.SMUserMs u
     JOIN dbo.m_emp e ON u.EmpId = e.id_emp
     JOIN dbo.m_bag b ON e.id_bag = b.id_bag
     WHERE u.UserName = ?",
    [$username]
);

if ($stmtDeptUser && $r = sqlsrv_fetch_array($stmtDeptUser, SQLSRV_FETCH_ASSOC)) {
    $idDeptUser = $r['id_bag'];
}

// ------------------------------------------------------
// AMBIL BAGIAN DARI DATA LAMA
// ------------------------------------------------------
$idDeptData = $data['bagian_id'] ?? null;

// ------------------------------------------------------
// GABUNGKAN BAGIAN (USER + DATA)
// ------------------------------------------------------
$deptIds = array_unique(array_filter([$idDeptUser, $idDeptData]));

// ------------------------------------------------------
// LOAD BAGIAN (PASTI ADA)
// ------------------------------------------------------
$bagian = [];

if ($deptIds) {
    $in = implode(',', array_fill(0, count($deptIds), '?'));
    $sqlBag = "
        SELECT id_bag AS id, bagian AS nama_bagian
        FROM dbo.m_bag
        WHERE id_bag IN ($in)
        ORDER BY bagian
    ";
    $stmtBag = sqlsrv_query($conn, $sqlBag, $deptIds);

    if ($stmtBag) {
        while ($r = sqlsrv_fetch_array($stmtBag, SQLSRV_FETCH_ASSOC)) {
            $bagian[] = $r;
        }
    }
}

$error = null;

// ------------------------------------------------------
// SUBMIT UPDATE
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama_vendor    = trim($_POST['nama_vendor'] ?? '');
    $nama_pekerjaan = trim($_POST['nama_pekerjaan'] ?? '');
    $no_kontrak     = trim($_POST['no_kontrak'] ?? '');
    $expire_date    = $_POST['expire_date'] ?? null;
    $keterangan     = $_POST['keterangan'] ?? null;
    $email_reminder = $_POST['email_reminder'] ?? null;
    $no_whatsapp    = $_POST['no_whatsapp'] ?? null;
    $bagian_id      = $_POST['bagian_id'] ?: null;

    // file lama
    $fileName = $data['file_path'];

    // --------------------------------------------------
    // UPLOAD FILE BARU (OPSIONAL)
    // --------------------------------------------------
    if (!empty($_FILES['file']['name'])) {

        $allowedExt = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            $error = 'File harus PDF / DOC / DOCX';
        } else {

            $uploadDir = __DIR__ . '/uploads/kontrak/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['file']['name']);
            $dest = $uploadDir . $newName;

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                $error = 'Gagal upload file';
            } else {
                if (!empty($fileName) && file_exists($uploadDir . $fileName)) {
                    @unlink($uploadDir . $fileName);
                }
                $fileName = $newName;
            }
        }
    }

    // --------------------------------------------------
    // UPDATE DATABASE (updated_by SAJA)
    // --------------------------------------------------
    if (!$error) {

        $sqlUpdate = "
            UPDATE dr_kontrak SET
                nama_vendor     = ?,
                nama_pekerjaan  = ?,
                no_kontrak      = ?,
                expire_date     = ?,
                file_path       = ?,
                keterangan      = ?,
                bagian_id       = ?,
                email_reminder  = ?,
                no_whatsapp     = ?,
                updated_by      = ?,
                updatedate      = GETDATE()
            WHERE id = ?
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
            $username,
            $id
        ];

        if (sqlsrv_query($conn, $sqlUpdate, $params) === false) {
            $error = print_r(sqlsrv_errors(), true);
        } else {
            echo "
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Dokumen kontrak berhasil diperbarui',
                    timer: 1800,
                    showConfirmButton: false
                });
                setTimeout(function () {
                    window.location.href = 'index.php';
                }, 1800);
            </script>";
            exit;
        }
    }
}
?>

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
    <h1>Edit Dokumen Kontrak</h1>
</div>
</section>

<section class="content">
<div class="container-fluid">

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
<div class="card-header">
    <h3 class="card-title">Form Edit Dokumen Kontrak</h3>
</div>

<form method="post" enctype="multipart/form-data">
<div class="card-body">

<div class="form-group">
    <label>Nama Vendor</label>
    <input type="text" name="nama_vendor" class="form-control"
           value="<?= htmlspecialchars($data['nama_vendor']) ?>" required>
</div>

<div class="form-group">
    <label>Nama Pekerjaan</label>
    <input type="text" name="nama_pekerjaan" class="form-control"
           value="<?= htmlspecialchars($data['nama_pekerjaan']) ?>" required>
</div>

<div class="form-group">
    <label>No Kontrak</label>
    <input type="text" name="no_kontrak" class="form-control"
           value="<?= htmlspecialchars($data['no_kontrak']) ?>" required>
</div>

<div class="form-group">
    <label>Expire Date</label>
    <input type="date" name="expire_date" class="form-control"
           value="<?= $data['expire_date'] instanceof DateTimeInterface ? $data['expire_date']->format('Y-m-d') : '' ?>"
           required>
</div>

<div class="form-group">
    <label>File (opsional)</label>
    <input type="file" name="file" class="form-control">
    <?php if (!empty($data['file_path'])): ?>
        <small>
            File saat ini:
            <a href="uploads/kontrak/<?= htmlspecialchars($data['file_path']) ?>" target="_blank">
                <?= htmlspecialchars($data['file_path']) ?>
            </a>
        </small>
    <?php endif; ?>
</div>

<div class="form-group">
    <label>Keterangan</label>
    <textarea name="keterangan" class="form-control"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
</div>

<div class="form-group">
    <label>Bagian</label>
    <select name="bagian_id" class="form-control" required>
        <?php foreach ($bagian as $b): ?>
            <option value="<?= $b['id'] ?>" <?= ($b['id'] == $data['bagian_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['nama_bagian']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label>Email Reminder</label>
    <input type="email" name="email_reminder" class="form-control"
           value="<?= htmlspecialchars($data['email_reminder'] ?? '') ?>">
</div>

<div class="form-group">
    <label>No Whatsapp</label>
    <input type="text" name="no_whatsapp" class="form-control"
           value="<?= htmlspecialchars($data['no_whatsapp'] ?? '') ?>">
</div>

</div>

<div class="card-footer">
    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</div>

</form>
</div>
</div>
</section>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

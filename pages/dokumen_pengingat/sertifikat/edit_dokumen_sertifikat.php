<?php
// ======================================================
// edit_dokumen_sertifikat.php — FINAL (BAGIAN BY USER + UPDATED_BY)
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
$menuId = 169;
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
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<script>alert('ID tidak valid');location.href='index.php';</script>";
    exit;
}

// ------------------------------------------------------
// LOAD DATA SERTIFIKAT
// ------------------------------------------------------
$stmt = sqlsrv_query($conn, "SELECT * FROM dr_sertifikat WHERE id = ?", [$id]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    echo "<script>alert('Data tidak ditemukan');location.href='index.php';</script>";
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

    $nama_lembaga    = trim($_POST['nama_lembaga']);
    $nama_sertifikat = trim($_POST['nama_sertifikat']);
    $no_sertifikat   = trim($_POST['no_sertifikat']);
    $expire_date     = $_POST['expire_date'];
    $keterangan      = $_POST['keterangan'] ?? null;
    $bagian_id       = $_POST['bagian_id'] ?: null;
    $email_reminder  = $_POST['email_reminder'] ?? null;
    $no_whatsapp     = $_POST['no_whatsapp'] ?? null;

    // 1. Ambil file yang dipertahankan
    $existingFiles = $_POST['existing_files'] ?? [];

    // 2. Upload file baru
    $newUploaded = [];
    if (!empty($_FILES['file']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/sertifikat/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        foreach ($_FILES['file']['name'] as $key => $val) {
            $tmpName = $_FILES['file']['tmp_name'][$key];
            if ($_FILES['file']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($val, PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed)) continue;

            $fileName = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $val);
            if (move_uploaded_file($tmpName, $uploadDir . $fileName)) {
                $newUploaded[] = $fileName;
            }
        }
    }

    // 3. Merge files
    $finalFilesArr = array_merge($existingFiles, $newUploaded);
    $allFilesStr = implode(',', $finalFilesArr);

    // 4. Hapus fisik yang sudah dibuang
    $oldFilesArr = !empty($data['file_path']) ? explode(',', $data['file_path']) : [];
    foreach ($oldFilesArr as $old) {
        if (!in_array($old, $existingFiles)) {
            $path = __DIR__ . '/uploads/sertifikat/' . $old;
            if (file_exists($path)) @unlink($path);
        }
    }

    // --------------------------------------------------
    // UPDATE DATABASE
    // --------------------------------------------------
    $sqlUpdate = "
        UPDATE dr_sertifikat SET
            nama_lembaga    = ?,
            nama_sertifikat = ?,
            no_sertifikat   = ?,
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
        $nama_lembaga, $nama_sertifikat, $no_sertifikat, $expire_date,
        $allFilesStr, $keterangan, $bagian_id, $email_reminder,
        $no_whatsapp, $username, $id
    ];

    if (sqlsrv_query($conn, $sqlUpdate, $params) === false) {
        $error = print_r(sqlsrv_errors(), true);
    } else {
        echo "
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Dokumen sertifikat berhasil diperbarui',
                timer: 1800,
                showConfirmButton: false
            }).then(() => {
                window.location.href = '../master_dokumen.php';
            });
        </script>";
        exit;
    }
}
?>

<div class="content-wrapper">
<section class="content-header">
    <div class="container-fluid">
        <h1>Edit Dokumen Sertifikat</h1>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
<div class="card-header bg-<?= htmlspecialchars($theme) ?>">
    <h3 class="card-title text-white">Form Edit Dokumen Sertifikat</h3>
</div>

<form method="post" enctype="multipart/form-data">
<div class="card-body">

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Nama Lembaga</label>
                <input type="text" name="nama_lembaga" class="form-control"
                       value="<?= htmlspecialchars($data['nama_lembaga']) ?>" required>
            </div>

            <div class="form-group">
                <label>Nama Sertifikat</label>
                <input type="text" name="nama_sertifikat" class="form-control"
                       value="<?= htmlspecialchars($data['nama_sertifikat']) ?>" required>
            </div>

            <div class="form-group">
                <label>No Sertifikat</label>
                <input type="text" name="no_sertifikat" class="form-control"
                       value="<?= htmlspecialchars($data['no_sertifikat']) ?>" required>
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

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email Reminder</label>
                        <input type="email" name="email_reminder" class="form-control"
                               value="<?= htmlspecialchars($data['email_reminder'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>No Whatsapp</label>
                        <input type="text" name="no_whatsapp" class="form-control"
                               value="<?= htmlspecialchars($data['no_whatsapp'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Expire Date</label>
                <input type="date" name="expire_date" class="form-control"
                       value="<?= $data['expire_date'] instanceof DateTimeInterface ? $data['expire_date']->format('Y-m-d') : '' ?>"
                       required>
            </div>

            <div class="form-group">
                <label>File Terlampir</label>
                <div id="file-container" class="border p-2 rounded bg-light" style="min-height: 50px;">
                    <?php 
                    $files = !empty($data['file_path']) ? explode(',', $data['file_path']) : [];
                    if (empty($files)): ?>
                        <span class="text-muted italic small">Tidak ada file.</span>
                    <?php else: 
                        foreach ($files as $f): 
                            $fShort = (strlen($f) > 25) ? substr($f, 0, 10).'...'.substr($f, -10) : $f;
                    ?>
                        <div class="file-item d-flex justify-content-between align-items-center mb-1 p-1 bg-white border rounded">
                            <a href="uploads/sertifikat/<?= htmlspecialchars($f) ?>" target="_blank" title="<?= htmlspecialchars($f) ?>">
                                <i class="fas fa-file-alt mr-1"></i> <?= htmlspecialchars($fShort) ?>
                            </a>
                            <input type="hidden" name="existing_files[]" value="<?= htmlspecialchars($f) ?>">
                            <button type="button" class="btn btn-xs btn-danger ml-2" onclick="this.parentElement.remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <small class="text-muted italic">Klik tanda silang (x) untuk menghapus file.</small>
            </div>

            <div class="form-group">
                <label>Tambah File Baru</label>
                <input type="file" name="file[]" class="form-control" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <small class="text-muted">Pilih satu atau beberapa file untuk ditambahkan.</small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Keterangan</label>
        <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
    </div>

</div>

<div class="card-footer">
    <button class="btn btn-primary shadow"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
    <a href="../master_dokumen.php" class="btn btn-secondary border">Batal</a>
</div>
</form>

</div>
</div>
</section>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

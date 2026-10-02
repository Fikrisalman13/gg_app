<?php
// ======================================================
// edit_surat_kendaraan.php — FINAL (BAGIAN BY USER + UPDATED_BY)
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
$menuId = 171;
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
// LOAD DATA SURAT KENDARAAN
// ------------------------------------------------------
$stmt = sqlsrv_query($conn, "SELECT * FROM dr_surat_kendaraan WHERE id = ?", [$id]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$data) {
    echo "<script>alert('Data tidak ditemukan');window.location.href='index.php';</script>";
    exit;
}

// ------------------------------------------------------
// AMBIL DEPARTEMEN USER LOGIN
// ------------------------------------------------------
$idDeptUser = null;
$stmtDept = sqlsrv_query(
    $conn,
    "SELECT b.id_bag
     FROM dbo.SMUserMs u
     JOIN dbo.m_emp e ON u.EmpId = e.id_emp
     JOIN dbo.m_bag b ON e.id_bag = b.id_bag
     WHERE u.UserName = ?",
    [$username]
);

if ($stmtDept && $rDept = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
    $idDeptUser = $rDept['id_bag'];
}

// ------------------------------------------------------
// AMBIL DEPARTEMEN DARI DATA LAMA (JIKA ADA)
// ------------------------------------------------------
$idDeptData = null;
if (!empty($data['bagian_id'])) {
    $stmtDeptData = sqlsrv_query(
        $conn,
        "SELECT id_bag FROM dbo.m_bag WHERE id_bag = ?",
        [$data['bagian_id']]
    );
    if ($stmtDeptData && $r = sqlsrv_fetch_array($stmtDeptData, SQLSRV_FETCH_ASSOC)) {
        $idDeptData = $r['id_bag'];
    }
}

// ------------------------------------------------------
// GABUNGKAN DEPT (USER + DATA)
// ------------------------------------------------------
$deptIds = array_unique(array_filter([$idDeptUser, $idDeptData]));

// ------------------------------------------------------
// LOAD BAGIAN (PASTI TAMPIL & TIDAK KOSONG)
// ------------------------------------------------------
$bagian = [];

if ($deptIds) {
    $in  = implode(',', array_fill(0, count($deptIds), '?'));
    $sqlBag = "
        SELECT id_bag, bagian
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

    $no_polisi       = trim($_POST['no_polisi']);
    $jenis_kendaraan = trim($_POST['jenis_kendaraan']);
    $nama_kendaraan  = trim($_POST['nama_kendaraan']);
    $nama_pemilik    = trim($_POST['nama_pemilik']);
    $expire_date     = $_POST['expire_date'];
    $keterangan      = $_POST['keterangan'] ?? null;
    $email_reminder  = $_POST['email_reminder'] ?? null;
    $no_whatsapp     = $_POST['no_whatsapp'] ?? null;
    $bagian_id       = $_POST['bagian_id'] ?: null;

    // 1. Ambil file yang dipertahankan dari form
    $existingFiles = $_POST['existing_files'] ?? []; // Ini array filenames yang tidak dihapus
    
    // 2. Proses upload file baru jika ada
    $newUploaded = [];
    if (!empty($_FILES['new_files']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/kendaraan/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        foreach ($_FILES['new_files']['name'] as $key => $val) {
            $tmpName = $_FILES['new_files']['tmp_name'][$key];
            if ($_FILES['new_files']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($val, PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed)) continue;

            $fileName = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $val);
            if (move_uploaded_file($tmpName, $uploadDir . $fileName)) {
                $newUploaded[] = $fileName;
            }
        }
    }

    // 3. Gabungkan file lama yang tersisa dengan file baru
    $finalFilesArr = array_merge($existingFiles, $newUploaded);
    $allFilesStr = implode(',', $finalFilesArr);

    // 4. Bandingkan file lama dengan file baru untuk hapus fisik yang sudah tidak dipakai
    $oldFilesArr = !empty($data['file_kendaraan']) ? explode(',', $data['file_kendaraan']) : [];
    foreach ($oldFilesArr as $old) {
        if (!in_array($old, $existingFiles)) {
            $path = __DIR__ . '/uploads/kendaraan/' . $old;
            if (file_exists($path)) @unlink($path);
        }
    }

    // --------------------------------------------------
    // UPDATE DATABASE
    // --------------------------------------------------
    $sqlUpdate = "
        UPDATE dr_surat_kendaraan SET
            no_polisi       = ?,
            jenis_kendaraan = ?,
            nama_kendaraan  = ?,
            nama_pemilik    = ?,
            expire_date     = ?,
            file_kendaraan  = ?,
            keterangan      = ?,
            email_reminder  = ?,
            no_whatsapp     = ?,
            bagian_id       = ?,
            updated_by      = ?,
            update_at       = GETDATE()
        WHERE id = ?
    ";

    $params = [
        $no_polisi, $jenis_kendaraan, $nama_kendaraan, $nama_pemilik,
        $expire_date, $allFilesStr, $keterangan, $email_reminder,
        $no_whatsapp, $bagian_id, $username, $id
    ];

    if (sqlsrv_query($conn, $sqlUpdate, $params) === false) {
        $error = print_r(sqlsrv_errors(), true);
    } else {
        echo "
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Data surat kendaraan berhasil diperbarui',
                timer: 1800,
                showConfirmButton: false
            }).then(() => {
                window.location.href = '../master_dokumen.php';
            });
        </script>
        ";
        exit;
    }
}
?>

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
    <h1>Edit Surat Kendaraan</h1>
</div>
</section>

<section class="content">
<div class="container-fluid">

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
<div class="card-header bg-<?= htmlspecialchars($theme) ?>">
    <h3 class="card-title text-white">Form Edit Surat Kendaraan</h3>
</div>

<form method="post" enctype="multipart/form-data">
<div class="card-body">

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>No Polisi</label>
                <input type="text" name="no_polisi" class="form-control" value="<?= htmlspecialchars($data['no_polisi']) ?>" required>
            </div>

            <div class="form-group">
                <label>Jenis Kendaraan</label>
                <input type="text" name="jenis_kendaraan" class="form-control" value="<?= htmlspecialchars($data['jenis_kendaraan']) ?>" required>
            </div>

            <div class="form-group">
                <label>Nama Kendaraan</label>
                <input type="text" name="nama_kendaraan" class="form-control" value="<?= htmlspecialchars($data['nama_kendaraan']) ?>" required>
            </div>

            <div class="form-group">
                <label>Nama Pemilik</label>
                <input type="text" name="nama_pemilik" class="form-control" value="<?= htmlspecialchars($data['nama_pemilik']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Bagian</label>
                <select name="bagian_id" class="form-control" required>
                    <option value="">-- Pilih Bagian --</option>
                    <?php foreach ($bagian as $b): ?>
                        <option value="<?= $b['id_bag'] ?>" <?= ($b['id_bag'] == $data['bagian_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['bagian']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email Reminder</label>
                        <input type="email" name="email_reminder" class="form-control" value="<?= htmlspecialchars($data['email_reminder'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>No Whatsapp</label>
                        <input type="text" name="no_whatsapp" class="form-control" value="<?= htmlspecialchars($data['no_whatsapp'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Tanggal Expire</label>
                <input type="date" name="expire_date" class="form-control"
                       value="<?= $data['expire_date'] instanceof DateTimeInterface ? $data['expire_date']->format('Y-m-d') : '' ?>" required>
            </div>

            <div class="form-group">
                <label>File Terlampir</label>
                <div id="file-container" class="border p-2 rounded bg-light" style="min-height: 50px;">
                    <?php 
                    $files = !empty($data['file_kendaraan']) ? explode(',', $data['file_kendaraan']) : [];
                    if (empty($files)): ?>
                        <span class="text-muted italic small">Tidak ada file.</span>
                    <?php else: 
                        foreach ($files as $f): 
                            $fShort = (strlen($f) > 25) ? substr($f, 0, 10).'...'.substr($f, -10) : $f;
                    ?>
                        <div class="file-item d-flex justify-content-between align-items-center mb-1 p-1 bg-white border rounded">
                            <a href="uploads/kendaraan/<?= htmlspecialchars($f) ?>" target="_blank" title="<?= htmlspecialchars($f) ?>">
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
                <input type="file" name="new_files[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png">
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
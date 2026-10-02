<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// --- Cek login ---
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// --- Cek koneksi database ---
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// --- Cek hak akses edit ---
$groupId = $_SESSION['GroupId'];
$menuId  = 71; // MenuId untuk e-dokumen

$sql   = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt   = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !sqlsrv_fetch($stmt) || sqlsrv_get_field($stmt, 0) != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit dokumen.";
    header('Location: e_dokumen.php');
    exit;
}

// --- Ambil parameter dokumen ---
$id_dok = $_GET['id'] ?? 0;
if (!$id_dok) {
    $_SESSION['error'] = "Parameter ID dokumen tidak valid!";
    header('Location: e_dokumen.php');
    exit;
}

// --- Ambil data dokumen ---
$sql  = "SELECT * FROM dbo.dokumen WHERE id_dok = ?";
$stmt = sqlsrv_query($conn, $sql, [$id_dok]);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $_SESSION['error'] = "Terjadi kesalahan saat mengambil data: " . $errors[0]['message'];
    header('Location: e_dokumen.php');
    exit;
}

$document = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$document) {
    $_SESSION['error'] = "Dokumen dengan ID $id_dok tidak ditemukan!";
    header('Location: e_dokumen.php');
    exit;
}

// Format tanggal untuk form input
$tanggal_terbit = $document['tanggal_terbit'] ? $document['tanggal_terbit']->format('Y-m-d') : '';
$tanggal_revisi = $document['tanggal_revisi'] ? $document['tanggal_revisi']->format('Y-m-d') : '';

// --- Jika form disubmit ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
      

        // Tanggal terbit dan revisi
        $tanggal_terbit = !empty($_POST['tanggal_terbit']) ? date('Y-m-d', strtotime($_POST['tanggal_terbit'])) : NULL;
        $tanggal_revisi = !empty($_POST['tanggal_revisi']) ? date('Y-m-d', strtotime($_POST['tanggal_revisi'])) : NULL;

        // Upload file PDF
        $file_pdf = $document['file_pdf'];
        if (isset($_FILES['file_pdf']) && $_FILES['file_pdf']['error'] == UPLOAD_ERR_OK) {
            $allowedTypes = ['application/pdf'];
            $fileInfo     = finfo_open(FILEINFO_MIME_TYPE);
            $detectedType = finfo_file($fileInfo, $_FILES['file_pdf']['tmp_name']);
            finfo_close($fileInfo);

            if (!in_array($detectedType, $allowedTypes)) {
                throw new Exception("Hanya file PDF yang diizinkan!");
            }

            // Simpan file baru
            $ext       = pathinfo($_FILES['file_pdf']['name'], PATHINFO_EXTENSION);
            $filename  = uniqid('doc_') . '.' . $ext;
            $uploadDir = '../../uploads/dokumen/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $destination = $uploadDir . $filename;
            if (!move_uploaded_file($_FILES['file_pdf']['tmp_name'], $destination)) {
                throw new Exception("Gagal mengupload file!");
            }

            // Hapus file lama
            if ($file_pdf && file_exists('../../' . ltrim($file_pdf, '/'))) {
                unlink('../../' . ltrim($file_pdf, '/'));
            }

            $file_pdf = '/gg_app/uploads/dokumen/' . $filename;
        }

        // Update dokumen
        $sql = "UPDATE dbo.dokumen SET 
                    id_kode_dok = ?, 
                    id_kategori = ?, 
                    id_dept     = ?, 
                    id_bag      = ?, 
                    id_subbag   = ?,
                    nama_dokumen= ?, 
                    revisi      = ?, 
                    tanggal_terbit = ?,
                    tanggal_revisi = ?,
                    file_pdf    = ?, 
                    deskripsi   = ?,
                    upddate     = ?, 
                    upduser     = ?
                WHERE id_dok = ?";

        $params = [
            $_POST['id_kode_dok'],
            $_POST['id_kategori'],
            $_POST['id_dept'],
            $_POST['id_bag'] ?? null,
            $_POST['id_subbag'] ?? null,
            $_POST['nama_dokumen'],
            $_POST['revisi'],
            $tanggal_terbit,
            $tanggal_revisi,
            $file_pdf,
            $_POST['deskripsi'],
            date('Y-m-d H:i:s'),
            $_SESSION['UserName'],
            $id_dok
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Gagal memperbarui data: " . $errors[0]['message']);
        }

        $_SESSION['success'] = "Dokumen berhasil diperbarui!";
        header('Location: e_dokumen.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// --- Fungsi dropdown ---
function getDropdownOptions($conn, $table, $idCol, $nameCol, $selectedId = null) {
    $options = "";
    $sql  = "SELECT $idCol, $nameCol FROM $table ORDER BY $nameCol";
    $stmt = sqlsrv_query($conn, $sql);

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $selected = $row[$idCol] == $selectedId ? 'selected' : '';
        $options .= "<option value='{$row[$idCol]}' $selected>{$row[$nameCol]}</option>";
    }

    sqlsrv_free_stmt($stmt);
    return $options;
}
?>

<div class="content-wrapper">
    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Dokumen</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="e_dokumen.php">E-Dokumen</a></li>
                        <li class="breadcrumb-item active">Edit Dokumen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title"><i class="fas fa-edit"></i> Form Edit Dokumen</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="icon fas fa-ban"></i> <?= $_SESSION['error'] ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <!-- Kolom kiri -->
                            <div class="col-md-6">
                                <!-- Kode Dokumen -->
                                <div class="form-group">
                                    <label>Kode Dokumen</label>
                                    <select class="form-control" disabled>
                                        <?= getDropdownOptions($conn, 'm_kode_dok', 'id_kode_dok', 'kode_dok', $document['id_kode_dok']) ?>
                                    </select>
                                    <input type="hidden" name="id_kode_dok" value="<?= $document['id_kode_dok'] ?>">
                                </div>
                                <!-- Kategori -->
                                <div class="form-group">
                                    <label>Kategori Dokumen</label>
                                    <select class="form-control" disabled>
                                        <?= getDropdownOptions($conn, 'm_kategori_dok', 'id_kategori', 'nama_kategori', $document['id_kategori']) ?>
                                    </select>
                                    <input type="hidden" name="id_kategori" value="<?= $document['id_kategori'] ?>">
                                </div>
                                <!-- Departemen -->
                                <div class="form-group">
                                    <label>Departemen</label>
                                    <select class="form-control" disabled>
                                        <?= getDropdownOptions($conn, 'm_dept', 'id_dept', 'dept', $document['id_dept']) ?>
                                    </select>
                                    <input type="hidden" name="id_dept" value="<?= $document['id_dept'] ?>">
                                </div>
                                <!-- Bagian -->
                                <div class="form-group">
                                    <label>Bagian</label>
                                    <select class="form-control" disabled>
                                        <?= getDropdownOptions($conn, 'm_bag', 'id_bag', 'bagian', $document['id_bag']) ?>
                                    </select>
                                    <input type="hidden" name="id_bag" value="<?= $document['id_bag'] ?>">
                                </div>
                                <!-- Sub Bagian -->
                                <div class="form-group">
                                    <label>Sub Bagian</label>
                                    <select class="form-control" disabled>
                                        <?= getDropdownOptions($conn, 'm_subbag', 'id_subbag', 'subbag', $document['id_subbag']) ?>
                                    </select>
                                    <input type="hidden" name="id_subbag" value="<?= $document['id_subbag'] ?>">
                                </div>
                            </div>

                            <!-- Kolom kanan -->
                            <div class="col-md-6">
                                <!-- Nama Dokumen -->
                                <div class="form-group">
                                    <label>Nama Dokumen</label>
                                    <input type="text" class="form-control" name="nama_dokumen"
                                           value="<?= htmlspecialchars($document['nama_dokumen']) ?>" required>
                                </div>
                                
                                <!-- Revisi -->
                                <div class="form-group">
                                    <label>Revisi</label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        name="revisi"
                                        value="<?= htmlspecialchars(str_pad($document['revisi'], 2, '0', STR_PAD_LEFT)) ?>" 
                                        required
                                        maxlength="2"
                                        pattern="[0-9]{2}"
                                        title="Harus berupa 2 digit angka (00-99)"
                                        placeholder="00"
                                    >
                                    <small class="form-text text-muted">Revisi dimulai dari 00 (dokumen baru), maksimal 99</small>
                                </div>

                                
                                <!-- Tanggal Terbit -->
                                <div class="form-group">
                                    <label>Tanggal Terbit</label>
                                    <input type="date" class="form-control" name="tanggal_terbit"
                                           value="<?= $tanggal_terbit ?>">
                                </div>
                                
                                <!-- Tanggal Revisi -->
                                <div class="form-group">
                                    <label>Tanggal Revisi</label>
                                    <input type="date" class="form-control" name="tanggal_revisi"
                                           value="<?= $tanggal_revisi ?>">
                                    <small class="form-text text-muted">Diisi jika dokumen ini merupakan revisi</small>
                                </div>
                                
                                <!-- File PDF -->
                                <div class="form-group">
                                    <label>File PDF</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" name="file_pdf" id="file_pdf" accept=".pdf">
                                        <label class="custom-file-label" for="file_pdf">Pilih file baru</label>
                                    </div>
                                    <?php if ($document['file_pdf']): ?>
                                        <div class="file-info mt-2">
                                            File saat ini:
                                            <a href="<?= $document['file_pdf'] ?>" target="_blank">
                                                <?= basename($document['file_pdf']) ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Deskripsi -->
                                <div class="form-group">
                                    <label>Deskripsi</label>
                                    <textarea class="form-control" name="deskripsi" rows="3"><?= htmlspecialchars($document['deskripsi']) ?></textarea>
                                </div>
                                
                                <!-- Info -->
                                <div class="form-group">
                                    <label>Informasi Dokumen</label>
                                    <div class="form-control-plaintext">
                                        <p>Kode Dokumen: <strong><?= htmlspecialchars($document['kode_dok_seq']) ?></strong></p>
                                        <p>Diupload pada:
                                            <strong><?= $document['tanggal_upload'] instanceof DateTime ? $document['tanggal_upload']->format('d/m/Y H:i') : '' ?></strong>
                                        </p>
                                        <p>Terakhir diupdate:
                                            <strong><?= $document['upddate'] instanceof DateTime ? $document['upddate']->format('d/m/Y H:i') : '' ?></strong>
                                            oleh <strong><?= htmlspecialchars($document['upduser']) ?></strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol -->
                        <div class="mt-3">
                            <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                            <a href="e_dokumen.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>


<script>
    $(function () { bsCustomFileInput.init(); });
</script>

<?php ob_end_flush(); ?>
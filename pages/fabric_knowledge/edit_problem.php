<?php
// pages/fabric_knowledge/edit_problem.php
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 123);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data masalah.";
    header('Location: problem_list.php');
    exit;
}

// ====== Get Problem Data ======
$id = $_GET['id'] ?? '';
if (empty($id)) {
    $_SESSION['error'] = "ID masalah tidak ditemukan.";
    header('Location: problem_list.php');
    exit;
}

$sql = "SELECT p.*, k.nama_kategori, t.nama_tag, s.status
        FROM dbo.fab_m_problem p
        LEFT JOIN dbo.fab_m_kategori k ON p.id_kategori = k.id_kategori
        LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag
        LEFT JOIN dbo.fab_m_solution s ON p.id_problem = s.id_problem
        WHERE p.id_problem = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$problem = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$problem) {
    $_SESSION['error'] = "Data masalah tidak ditemukan.";
    header('Location: problem_list.php');
    exit;
}

// ====== Dropdown Kategori & Tag ======
function getDropdownOptions($conn)
{
    $options = ['kategori' => [], 'tag' => []];
    $stmt = sqlsrv_query($conn, "SELECT id_kategori, nama_kategori FROM dbo.fab_m_kategori ORDER BY nama_kategori");
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $options['kategori'][] = $row;
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    $stmt = sqlsrv_query($conn, "SELECT id_tag, nama_tag, id_kategori FROM dbo.fab_m_tag ORDER BY nama_tag");
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $options['tag'][] = $row;
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    return $options;
}
$dropdownOptions = getDropdownOptions($conn);

// ====== Ambil Data Solution ======
$solution = [];
$sqlSolution = "SELECT * FROM dbo.fab_m_solution WHERE id_problem = ?";
$stmtSolution = sqlsrv_query($conn, $sqlSolution, [$id]);
if ($stmtSolution) {
    $solution = sqlsrv_fetch_array($stmtSolution, SQLSRV_FETCH_ASSOC) ?: [];
}

// ====== Tentukan Status ======
$currentStatus = $solution['status'] ?? 'Open';

// ====== Validasi ID Tag ======
function isValidTag($conn, $tagId)
{
    if (empty($tagId)) return true; // NULL diizinkan
    
    $sql = "SELECT COUNT(*) as count FROM dbo.fab_m_tag WHERE id_tag = ?";
    $stmt = sqlsrv_query($conn, $sql, [$tagId]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['count'] > 0;
    }
    return false;
}

function isValidKategori($conn, $kategoriId)
{
    if (empty($kategoriId)) return false; // Kategori wajib diisi
    
    $sql = "SELECT COUNT(*) as count FROM dbo.fab_m_kategori WHERE id_kategori = ?";
    $stmt = sqlsrv_query($conn, $sql, [$kategoriId]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['count'] > 0;
    }
    return false;
}

// ====== Update Logic ======
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $id_kategori = $_POST['id_kategori'] ?? '';
        $id_tag = $_POST['id_tag'] ?? '';
        $nocp = trim($_POST['nocp'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $routing = trim($_POST['routing'] ?? '');
        $status_qc = trim($_POST['status_qc'] ?? '');
        $tgl_lkp_qc = trim($_POST['tgl_lkp_qc'] ?? '');
        $pdr = $_POST['pdr'] ?? null;
        $resep = trim($_POST['resep'] ?? '');
        $analis = trim($_POST['analis'] ?? '');
        $review = trim($_POST['review'] ?? '');
        $solusi = trim($_POST['solusi'] ?? '');

        // Validasi input
        if (empty($id_kategori)) {
            throw new Exception("Kategori wajib diisi!");
        }

        // Validasi format nocp jika diisi
        if (!empty($nocp)) {
            if (strlen($nocp) > 20) {
                throw new Exception("No Kartu Produksi terlalu panjang. Maksimal 20 karakter.");
            }
        }

        // Validasi kategori
        if (!isValidKategori($conn, $id_kategori)) {
            throw new Exception("Kategori yang dipilih tidak valid!");
        }

        // Validasi tag
        if (!empty($id_tag) && !isValidTag($conn, $id_tag)) {
            throw new Exception("Tag yang dipilih tidak valid!");
        }

        // Konversi id_tag ke NULL jika kosong
        $id_tag = !empty($id_tag) ? $id_tag : null;
        // Konversi nocp ke NULL jika kosong
        $nocp = !empty($nocp) ? $nocp : null;
        // Konversi pdr ke NULL jika kosong
        $pdr = !empty($pdr) ? intval($pdr) : null;

        sqlsrv_begin_transaction($conn);

        // Update data masalah
        $sql = "UPDATE dbo.fab_m_problem 
                SET deskripsi = ?, id_kategori = ?, id_tag = ?, nocp = ?, color = ?, routing = ?, 
                    status_qc = ?, tgl_lkp_qc = ?, pdr = ?, resep = ?, analis = ?, review = ?, solusi = ?,
                    updated_at = GETDATE(), updated_by = ?
                WHERE id_problem = ?";
        
        $params = [
            $deskripsi, 
            $id_kategori, 
            $id_tag, 
            $nocp,
            !empty($color) ? $color : null,
            !empty($routing) ? $routing : null,
            !empty($status_qc) ? $status_qc : null,
            !empty($tgl_lkp_qc) ? $tgl_lkp_qc : null,
            $pdr,
            !empty($resep) ? $resep : null,
            !empty($analis) ? $analis : null,
            !empty($review) ? $review : null,
            !empty($solusi) ? $solusi : null,
            $_SESSION['UserName'], 
            $id
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log("SQL Error: " . print_r($errors, true));
            throw new Exception("Gagal mengupdate data masalah: " . $errors[0]['message']);
        }

        // Cek apakah ada baris yang teraffected
        $rows_affected = sqlsrv_rows_affected($stmt);
        error_log("Rows affected: $rows_affected");
        
        if ($rows_affected === false) {
            throw new Exception("Error saat mengecek jumlah baris yang diupdate.");
        }

        // History
        $sql_history = "INSERT INTO dbo.fab_t_history (id_problem, aksi, catatan, created_by)
                        VALUES (?, 'Update', 'Data masalah diperbarui', ?)";
        $stmt_history = sqlsrv_query($conn, $sql_history, [$id, $_SESSION['UserName']]);
        
        if ($stmt_history === false) {
            $errors = sqlsrv_errors();
            error_log("History Error: " . print_r($errors, true));
            throw new Exception("Gagal menyimpan history: " . $errors[0]['message']);
        }

        // Handle upload file baru
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = __DIR__ . '/../../uploads/fabric_problems/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception("Gagal membuat direktori upload.");
                }
            }

            foreach ($_FILES['attachments']['name'] as $i => $name) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['attachments']['tmp_name'][$i];
                    $fileSize = $_FILES['attachments']['size'][$i];
                    
                    // Validasi file size
                    if ($fileSize > 5 * 1024 * 1024) {
                        throw new Exception("File $name terlalu besar. Maksimal 5MB.");
                    }
                    
                    // Validasi file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                    $fileType = mime_content_type($tmp);
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception("Tipe file $name tidak diizinkan. Hanya JPEG, PNG, GIF, PDF.");
                    }
                    
                    $unique = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $name);
                    $dest = $uploadDir . $unique;
                    
                    if (move_uploaded_file($tmp, $dest)) {
                        $sql_attachment = "INSERT INTO dbo.fab_t_attachment 
                                          (id_problem, nama_file, path_file, uploaded_by)
                                          VALUES (?, ?, ?, ?)";
                        $stmt_attachment = sqlsrv_query($conn, $sql_attachment, [
                            $id, 
                            $name, 
                            'uploads/fabric_problems/' . $unique, 
                            $_SESSION['UserName']
                        ]);
                        
                        if ($stmt_attachment === false) {
                            $errors = sqlsrv_errors();
                            error_log("Attachment Error: " . print_r($errors, true));
                            throw new Exception("Gagal menyimpan data attachment: " . $errors[0]['message']);
                        }
                    } else {
                        throw new Exception("Gagal upload file: $name");
                    }
                }
            }
        }

        sqlsrv_commit($conn);
        $_SESSION['success'] = "Data masalah berhasil diperbarui!";
        header('Location: problem_list.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $error = $e->getMessage();
        error_log("Update Error: " . $error);
    }
}

// Ambil attachment terkait
$attachments = [];
$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.fab_t_attachment WHERE id_problem=?", [$id]);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $attachments[] = $row;
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
$id = $_GET['id'] ?? ''; // Restore: sidebar.php overwrites $id via foreach key
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Edit Masalah - <?= htmlspecialchars($problem['deskripsi'] ?? '') ?></title>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<style>
.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #dee2e6;
    padding: 8px 12px;
    margin-bottom: 5px;
    border-radius: 4px;
    background: #fff;
    font-size: 0.9em;
}
.file-upload-container {
    border: 2px dashed #dee2e6;
    border-radius: 5px;
    padding: 15px;
    text-align: center;
    background: #f8f9fa;
    margin-bottom: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.file-upload-container:hover {
    border-color: #007bff;
    background: #e3f2fd;
}
.file-upload-container.dragover {
    border-color: #007bff;
    background: #e3f2fd;
}
.btn-remove-file {
    color: #dc3545;
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 3px;
}
.btn-remove-file:hover {
    background: #f8d7da;
}
.required-field::after {
    content: " *";
    color: #dc3545;
}
.form-note {
    font-size: 0.85em;
    color: #6c757d;
    margin-top: 5px;
}
.status-badge {
    font-size: 0.8em;
    padding: 4px 8px;
}
.tag-loading {
    display: none;
    color: #007bff;
    font-size: 0.8em;
}
.nocp-input {
    font-family: monospace;
}
</style>
</head>
<body>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Masalah</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="problem_list.php">Knowledge Base</a></li>
                        <li class="breadcrumb-item"><a href="problem_detail.php?id=<?= $id ?>">Detail</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h5><i class="icon fas fa-ban"></i> Error!</h5>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-<?= $themeColor ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-edit"></i> Form Edit Masalah
                        <span class="badge badge-light status-badge ml-2">
                            Status: 
                            <span class="badge badge-<?= 
                                $currentStatus == 'Solved' ? 'success' : 
                                ($currentStatus == 'Reopen' ? 'danger' : 'warning')
                            ?>">
                                <?= htmlspecialchars($currentStatus) ?>
                            </span>
                        </span>
                    </h3>
                    <div class="card-tools">
                        <a href="analisa_solusi.php?id=<?= $id ?>" class="btn btn-info btn-sm">
                            <i class="fas fa-lightbulb"></i> Analisa & Solusi
                        </a>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="editForm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nocp">No Kartu Produksi</label>
                                    <input type="text" class="form-control nocp-input" id="nocp" name="nocp" 
                                           value="<?php echo htmlspecialchars($problem['nocp'] ?? ''); ?>" 
                                           placeholder="Contoh: D25I0979.01.0101"
                                           maxlength="20">
                                    <div class="form-note">Format: DXXIXXXX.XX.XXXX</div>
                                </div>

                                <div class="form-group">
                                    <label for="color">Kode Warna</label>
                                    <input type="text" class="form-control" id="color" name="color" 
                                           value="<?php echo htmlspecialchars($problem['color'] ?? ''); ?>" 
                                           placeholder="Contoh: 6.3.11.0.0782"
                                           maxlength="50">
                                </div>

                                <div class="form-group">
                                    <label for="routing">Routing</label>
                                    <input type="text" class="form-control" id="routing" name="routing" 
                                           value="<?php echo htmlspecialchars($problem['routing'] ?? ''); ?>" 
                                           placeholder="Contoh: INSPECT FINAL"
                                           maxlength="100">
                                </div>

                                <?php
                                $tgl_lkp_qc_val = '';
                                if (!empty($problem['tgl_lkp_qc'])) {
                                    $tgl_lkp_qc_val = $problem['tgl_lkp_qc'] instanceof DateTime ? $problem['tgl_lkp_qc']->format('Y-m-d') : date('Y-m-d', strtotime($problem['tgl_lkp_qc']));
                                }
                                ?>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="status_qc">Status QC</label>
                                            <select class="form-control" id="status_qc" name="status_qc">
                                                <option value="">Pilih Status QC</option>
                                                <option value="Pass" <?= (($problem['status_qc'] ?? '') == 'Pass') ? 'selected' : '' ?>>Pass</option>
                                                <option value="Fail" <?= (($problem['status_qc'] ?? '') == 'Fail') ? 'selected' : '' ?>>Fail</option>
                                                <option value="Hold" <?= (($problem['status_qc'] ?? '') == 'Hold') ? 'selected' : '' ?>>Hold</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="tgl_lkp_qc">Tgl Lkp Qc</label>
                                            <input type="date" class="form-control" id="tgl_lkp_qc" name="tgl_lkp_qc"
                                                   value="<?= htmlspecialchars($tgl_lkp_qc_val) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pdr">PDR</label>
                                    <input type="number" class="form-control" id="pdr" name="pdr" 
                                           value="<?php echo htmlspecialchars($problem['pdr'] ?? ''); ?>" 
                                           placeholder="Contoh: 4"
                                           min="0" max="100">
                                </div>

                                <div class="form-group">
                                    <label for="resep">Resep</label>
                                    <input type="text" class="form-control" id="resep" name="resep" 
                                           value="<?php echo htmlspecialchars($problem['resep'] ?? ''); ?>" 
                                           placeholder="Contoh: MB"
                                           maxlength="50">
                                </div>

                                <div class="form-group">
                                    <label for="analis">Analis</label>
                                    <input type="text" class="form-control" id="analis" name="analis" 
                                           value="<?php echo htmlspecialchars($problem['analis'] ?? ''); ?>" 
                                           placeholder="Contoh: Dikdik"
                                           maxlength="100">
                                </div>

                                <div class="form-group">
                                    <label for="review">Review</label>
                                    <input type="text" class="form-control" id="review" name="review" 
                                           value="<?php echo htmlspecialchars($problem['review'] ?? ''); ?>" 
                                           placeholder="Contoh: OVER WARNA"
                                           maxlength="200">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="id_kategori" class="required-field">Kategori</label>
                                    <select class="form-control" id="id_kategori" name="id_kategori" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($dropdownOptions['kategori'] as $row): ?>
                                            <option value="<?= htmlspecialchars($row['id_kategori']) ?>" 
                                                <?= (($problem['id_kategori'] ?? '') == $row['id_kategori']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['nama_kategori']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-note">Pilih kategori yang paling sesuai dengan area masalah.</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="id_tag">Tag</label>
                                    <select class="form-control" id="id_tag" name="id_tag">
                                        <option value="">Pilih Tag (Opsional)</option>
                                        <?php foreach ($dropdownOptions['tag'] as $row): ?>
                                            <option value="<?= htmlspecialchars($row['id_tag']) ?>" 
                                                data-kategori="<?= htmlspecialchars($row['id_kategori']) ?>"
                                                <?= (($problem['id_tag'] ?? '') == $row['id_tag']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($row['nama_tag']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="tag-loading" id="tagLoading">
                                        <i class="fas fa-spinner fa-spin"></i> Memuat tag...
                                    </div>
                                    <div class="form-note">Tag akan otomatis difilter berdasarkan kategori yang dipilih.</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="deskripsi">Deskripsi Masalah</label>
                                    <textarea class="form-control" id="deskripsi" name="deskripsi" 
                                              rows="4" placeholder="Jelaskan detail masalah yang terjadi"><?php echo htmlspecialchars($problem['deskripsi'] ?? ''); ?></textarea>
                                    <div class="form-note">Deskripsi yang lengkap akan membantu tim analisa memahami masalah dengan baik.</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="solusi">Solusi</label>
                                    <textarea class="form-control" id="solusi" name="solusi" 
                                              rows="3" placeholder="Masukkan solusi yang diberikan"><?php echo htmlspecialchars($problem['solusi'] ?? ''); ?></textarea>
                                    <div class="form-note">Solusi yang telah diberikan untuk masalah ini.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Dokumen Tambahan -->
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Upload Dokumen Tambahan</label>
                                    <div class="file-upload-container" id="uploadContainer">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-1">Klik atau drag & drop file di sini</p>
                                        <small class="text-muted d-block">Maksimal 5MB per file</small>
                                        <small class="text-muted">Format: JPG, PNG, GIF, PDF</small>
                                        <input type="file" class="d-none" id="attachments" 
                                               name="attachments[]" multiple 
                                               accept=".jpg,.jpeg,.png,.gif,.pdf">
                                    </div>
                                    <div class="file-preview" id="filePreview"></div>

                                    <div class="mt-3">
                                        <label>File Terlampir:</label>
                                        <?php if (count($attachments) > 0): ?>
                                            <?php foreach ($attachments as $f): ?>
                                                <div class="file-item">
                                                    <a href="/gg_app/<?= htmlspecialchars($f['path_file']) ?>" target="_blank" class="text-primary">
                                                        <i class="fas fa-paperclip"></i> <?= htmlspecialchars($f['nama_file']) ?>
                                                    </a>
                                                    <small class="text-muted">
                                                        <?= $f['uploaded_at'] instanceof DateTime ? 
                                                            $f['uploaded_at']->format('d-m-Y H:i') : 
                                                            date('d-m-Y H:i', strtotime($f['uploaded_at'])) ?>
                                                    </small>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted"><i>Tidak ada file terlampir</i></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi tentang analisa & solusi -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Informasi</h6>
                            <p class="mb-0">
                                <strong>Analisa dan solusi</strong> dikelola terpisah melalui halaman 
                                <a href="analisa_solusi.php?id=<?= $id ?>" class="alert-link">Analisa & Solusi</a>. 
                                Tim QA/Engineering dapat menganalisa akar masalah dan memberikan solusi yang tepat.
                            </p>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="problem_detail.php?id=<?= $id ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Lihat Detail
                        </a>
                        <a href="problem_list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                        </a>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
    // Fungsi untuk filter tag berdasarkan kategori
    function filterTagsByCategory(categoryId) {
        const tagSelect = document.getElementById('id_tag');
        const tagLoading = document.getElementById('tagLoading');
        const currentSelectedTag = tagSelect.value;
        
        // Show loading
        tagLoading.style.display = 'block';
        
        // Reset tag options, keep the first empty option
        const emptyOption = tagSelect.querySelector('option[value=""]');
        tagSelect.innerHTML = '';
        if (emptyOption) {
            tagSelect.appendChild(emptyOption);
        }
        
        // Get all tag options from original data
        const allTags = <?php echo json_encode($dropdownOptions['tag']); ?>;
        
        // Filter tags by selected category
        const filteredTags = allTags.filter(tag => {
            return !categoryId || tag.id_kategori == categoryId;
        });
        
        // Add filtered tags to select
        filteredTags.forEach(tag => {
            const option = document.createElement('option');
            option.value = tag.id_tag;
            option.textContent = tag.nama_tag;
            option.setAttribute('data-kategori', tag.id_kategori);
            tagSelect.appendChild(option);
        });
        
        // Try to restore previous selection if it exists in filtered tags
        if (currentSelectedTag && filteredTags.some(tag => tag.id_tag == currentSelectedTag)) {
            tagSelect.value = currentSelectedTag;
        } else {
            tagSelect.value = '';
        }
        
        // Hide loading
        tagLoading.style.display = 'none';
    }

    // Event listener untuk perubahan kategori
    document.getElementById('id_kategori').addEventListener('change', function() {
        const selectedCategoryId = this.value;
        filterTagsByCategory(selectedCategoryId);
    });

    // Initialize tags based on selected category (if any)
    const initialCategoryId = document.getElementById('id_kategori').value;
    if (initialCategoryId) {
        filterTagsByCategory(initialCategoryId);
    }

    // File upload elements
    const fileInput = document.getElementById('attachments');
    const filePreview = document.getElementById('filePreview');
    const uploadContainer = document.getElementById('uploadContainer');
    let fileList = new DataTransfer();

    // Click on upload container to trigger file input
    uploadContainer.addEventListener('click', function() {
        fileInput.click();
    });

    // File input change event
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag and drop functionality
    uploadContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadContainer.classList.add('dragover');
    });

    uploadContainer.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadContainer.classList.remove('dragover');
    });

    uploadContainer.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadContainer.classList.remove('dragover');
        
        if (e.dataTransfer.files.length > 0) {
            handleFiles(e.dataTransfer.files);
        }
    });

    // Function to handle files
    function handleFiles(files) {
        for (let file of files) {
            // Validasi file size
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Terlalu Besar',
                    text: `File ${file.name} melebihi 5MB`
                });
                continue;
            }

            // Validasi file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Format File Tidak Didukung',
                    text: `File ${file.name} harus berupa JPG, PNG, GIF, atau PDF`
                });
                continue;
            }

            // Add to file list
            fileList.items.add(file);
        }

        // Update file input
        fileInput.files = fileList.files;
        
        // Update preview
        updateFilePreview();
    }

    // Function to update file preview
    function updateFilePreview() {
        filePreview.innerHTML = '';
        
        if (fileList.files.length === 0) {
            return;
        }

        for (let i = 0; i < fileList.files.length; i++) {
            const file = fileList.files[i];
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            
            const fileInfo = document.createElement('span');
            fileInfo.innerHTML = `
                <i class="fas ${getFileIcon(file.type)} text-muted mr-2"></i>
                ${file.name} 
                <small class="text-muted ml-2">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            `;
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-remove-file';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.onclick = function() {
                removeFile(i);
            };
            
            fileItem.appendChild(fileInfo);
            fileItem.appendChild(removeBtn);
            filePreview.appendChild(fileItem);
        }
    }

    // Function to remove file
    function removeFile(index) {
        fileList.items.remove(index);
        fileInput.files = fileList.files;
        updateFilePreview();
    }

    // Function to get file icon based on type
    function getFileIcon(fileType) {
        if (fileType.startsWith('image/')) return 'fa-file-image';
        if (fileType === 'application/pdf') return 'fa-file-pdf';
        return 'fa-file';
    }

    // Form validation
    document.getElementById('editForm').addEventListener('submit', function(e) {
        const kategori = document.getElementById('id_kategori').value;
        const nocp = document.getElementById('nocp').value.trim();
        
        if (!kategori) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap',
                text: 'Kategori wajib diisi!',
                confirmButtonText: 'Mengerti'
            });
            return false;
        }

        // Show loading
        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    });

    
})();
</script>

</body>
</html>

<?php include '../../includes/footer.php'; ?>
<?php
// pages/fabric_knowledge/analisa_solusi.php
session_start();
ob_start();

require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ====== Auth ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// ====== Permission Check ======
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanEdit, CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $perm = ['CanEdit' => 0, 'CanAdd' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $perm = $row;
    }
    return $perm;
}
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 123);

// ====== Ambil ID Problem ======
$id = $_GET['id'] ?? '';
if (empty($id)) {
    $_SESSION['error'] = "ID masalah tidak ditemukan.";
    header('Location: problem_list.php');
    exit;
}

// ====== Ambil Data Problem ======
$sql = "SELECT p.*, k.nama_kategori, t.nama_tag
        FROM dbo.fab_m_problem p
        LEFT JOIN dbo.fab_m_kategori k ON p.id_kategori = k.id_kategori
        LEFT JOIN dbo.fab_m_tag t ON p.id_tag = t.id_tag
        WHERE p.id_problem = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);
$problem = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$problem) {
    $_SESSION['error'] = "Data masalah tidak ditemukan.";
    header('Location: problem_list.php');
    exit;
}

// ====== Cek apakah sudah ada solusi ======
$sqlSolution = "SELECT * FROM dbo.fab_m_solution WHERE id_problem = ?";
$stmtSolution = sqlsrv_query($conn, $sqlSolution, [$id]);
$existingSolution = sqlsrv_fetch_array($stmtSolution, SQLSRV_FETCH_ASSOC);

// ====== Tentukan status yang akan digunakan ======
$currentStatus = 'Open'; // Default status
if ($existingSolution) {
    $currentStatus = $existingSolution['status'] ?? 'Open';
}

// ====== Ambil Data Lampiran ======
$attachments = [];
$sqlAttachments = "SELECT * FROM dbo.fab_t_attachment WHERE id_problem = ? ORDER BY uploaded_at DESC";
$stmtAttachments = sqlsrv_query($conn, $sqlAttachments, [$id]);
while ($row = sqlsrv_fetch_array($stmtAttachments, SQLSRV_FETCH_ASSOC)) {
    $attachments[] = $row;
}

// ====== Proses Upload Lampiran ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attachments'])) {
    if (!empty($_FILES['attachments']['name'][0])) {
        $uploadDir = __DIR__ . '/../../uploads/fabric_problems/';

        // Buat direktori jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $uploadSuccess = 0;
        $uploadErrors = [];

        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['attachments']['tmp_name'][$key];
                $fileSize = $_FILES['attachments']['size'][$key];

                // Validasi file size (max 5MB)
                if ($fileSize > 5 * 1024 * 1024) {
                    $uploadErrors[] = "File $name terlalu besar. Maksimal 5MB.";
                    continue;
                }

                // Validasi file type
                $allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ];
                $fileType = mime_content_type($tmpName);
                if (!in_array($fileType, $allowedTypes)) {
                    $uploadErrors[] = "Tipe file $name tidak diizinkan. Hanya JPEG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX.";
                    continue;
                }

                // Generate unique filename
                $fileExtension = pathinfo($name, PATHINFO_EXTENSION);
                $uniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $name);
                $filePath = $uploadDir . $uniqueName;

                if (move_uploaded_file($tmpName, $filePath)) {
                    // Simpan ke database
                    $sql_attachment = "INSERT INTO dbo.fab_t_attachment 
                                      (id_problem, nama_file, path_file, uploaded_by) 
                                      VALUES (?, ?, ?, ?)";
                    $params_attachment = [
                        $id,
                        $name,
                        'uploads/fabric_problems/' . $uniqueName,
                        $_SESSION['UserName']
                    ];

                    $stmt_attachment = sqlsrv_query($conn, $sql_attachment, $params_attachment);
                    if ($stmt_attachment !== false) {
                        $uploadSuccess++;

                        // Tambahkan history untuk upload
                        $sqlHistory = "INSERT INTO dbo.fab_t_history (id_problem, aksi, catatan, created_by)
                                       VALUES (?, 'Upload Lampiran', ?, ?)";
                        sqlsrv_query($conn, $sqlHistory, [$id, "File: $name", $_SESSION['UserName']]);
                    } else {
                        $uploadErrors[] = "Gagal menyimpan data file: $name";
                        // Hapus file yang sudah diupload jika gagal simpan ke database
                        unlink($filePath);
                    }
                } else {
                    $uploadErrors[] = "Gagal upload file: $name";
                }
            }
        }

        if ($uploadSuccess > 0) {
            $_SESSION['success'] = "Berhasil upload $uploadSuccess file.";
        }
        if (!empty($uploadErrors)) {
            $_SESSION['error'] = implode("<br>", $uploadErrors);
        }

        header("Location: analisa_solusi.php?id=" . urlencode($id));
        exit;
    }
}

// ====== Proses Tambah/Update Analisa & Solusi ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analisa_akar_masalah'])) {
    $analisa_akar_masalah = trim($_POST['analisa_akar_masalah'] ?? '');
    $tindakan_perbaikan = trim($_POST['tindakan_perbaikan'] ?? '');
    $tindakan_pencegahan = trim($_POST['tindakan_pencegahan'] ?? '');
    $status = $_POST['status'] ?? $currentStatus;

    // Validasi minimal analisa akar masalah harus diisi
    if (empty($analisa_akar_masalah)) {
        $_SESSION['error'] = "Analisa Akar Masalah wajib diisi.";
    } else {
        if ($existingSolution) {
            // Update solusi yang sudah ada
            $sql = "UPDATE dbo.fab_m_solution 
                    SET analisa_akar_masalah = ?, 
                        tindakan_perbaikan = ?, 
                        tindakan_pencegahan = ?,
                        status = ?,
                        updated_by = ?, 
                        updated_at = GETDATE()
                    WHERE id_problem = ?";
            $params = [$analisa_akar_masalah, $tindakan_perbaikan, $tindakan_pencegahan, $status, $_SESSION['UserName'], $id];
        } else {
            // Insert solusi baru
            $sql = "INSERT INTO dbo.fab_m_solution 
                    (id_problem, analisa_akar_masalah, tindakan_perbaikan, tindakan_pencegahan, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $params = [$id, $analisa_akar_masalah, $tindakan_perbaikan, $tindakan_pencegahan, $status, $_SESSION['UserName']];
        }

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            // Update timestamp di tabel problem
            $sqlStatus = "UPDATE dbo.fab_m_problem 
                          SET updated_by = ?, updated_at = GETDATE()
                          WHERE id_problem = ?";
            sqlsrv_query($conn, $sqlStatus, [$_SESSION['UserName'], $id]);

            // Tambahkan history
            $aksi = $existingSolution ? "Update Solusi" : "Tambah Solusi";
            $sqlHistory = "INSERT INTO dbo.fab_t_history (id_problem, aksi, catatan, created_by)
                           VALUES (?, ?, ?, ?)";
            $catatanHistory = "Analisa & solusi telah " . ($existingSolution ? "diperbarui" : "ditambahkan") . ". Status: " . $status;
            sqlsrv_query($conn, $sqlHistory, [$id, $aksi, $catatanHistory, $_SESSION['UserName']]);

            $_SESSION['success'] = "Analisa & Solusi berhasil " . ($existingSolution ? "diperbarui" : "disimpan") . "!";
            header("Location: analisa_solusi.php?id=" . urlencode($id));
            exit;
        } else {
            $_SESSION['error'] = "Gagal menyimpan analisa: " . print_r(sqlsrv_errors(), true);
        }
    }
}

// ====== Ambil Data Solusi ======
if ($existingSolution) {
    $sqlSolution = "SELECT * FROM dbo.fab_m_solution WHERE id_problem = ?";
    $stmtSolution = sqlsrv_query($conn, $sqlSolution, [$id]);
    $solution = sqlsrv_fetch_array($stmtSolution, SQLSRV_FETCH_ASSOC);
    // Update current status dari solution yang sudah diambil
    $currentStatus = $solution['status'] ?? 'Open';
} else {
    $solution = [
        'analisa_akar_masalah' => '',
        'tindakan_perbaikan' => '',
        'tindakan_pencegahan' => '',
        'status' => 'Open'
    ];
}

// ====== Ambil Riwayat ======
$sqlHistory = "SELECT aksi, catatan, created_by, created_at 
               FROM dbo.fab_t_history
               WHERE id_problem = ?
               ORDER BY created_at DESC";
$stmtHistory = sqlsrv_query($conn, $sqlHistory, [$id]);
$histories = [];
while ($row = sqlsrv_fetch_array($stmtHistory, SQLSRV_FETCH_ASSOC)) {
    $histories[] = $row;
}

// ====== Format date function ======
function formatDate($date)
{
    if (!$date)
        return '-';
    if ($date instanceof DateTime) {
        return $date->format('d-m-Y H:i');
    }
    return date('d-m-Y H:i', strtotime($date));
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
$id = $_GET['id'] ?? ''; // Restore: sidebar.php overwrites $id via foreach key
?>

<style>
    .history-card {
        background: #f9f9f9;
        border-left: 4px solid #007bff;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 4px;
    }

    .history-time {
        font-size: 0.85em;
        color: #6c757d;
        margin-top: 8px;
    }

    .form-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 15px;
        border-left: 4px solid #28a745;
    }

    .form-section h6 {
        color: #28a745;
        margin-bottom: 10px;
    }

    .required::after {
        content: " *";
        color: #dc3545;
    }

    .solution-badge {
        font-size: 0.8em;
        margin-left: 10px;
    }

    .section-complete {
        border-left-color: #28a745;
    }

    .section-incomplete {
        border-left-color: #ffc107;
    }

    .nocp-badge {
        background-color: #e9ecef;
        color: #495057;
        font-family: monospace;
        font-size: 0.85em;
        padding: 3px 6px;
        border-radius: 3px;
        border: 1px solid #ced4da;
    }

    .info-row {
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 1px solid #f0f0f0;
    }

    .info-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .attachment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: white;
        border: 1px solid #e3e6f0;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 8px;
        transition: all 0.3s ease;
    }

    .attachment-item:hover {
        background: #f8f9fa;
        border-color: #b7d1ff;
    }

    .file-upload-container {
        border: 2px dashed #dee2e6;
        border-radius: 5px;
        padding: 20px;
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

    .file-preview {
        margin-top: 10px;
    }

    .file-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 5px;
        background: white;
        font-size: 0.9em;
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

    .technical-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-family: monospace;
        font-size: 0.8em;
        padding: 2px 6px;
        border-radius: 3px;
        margin-right: 5px;
    }

    .pdr-badge {
        background-color: #17a2b8;
        color: white;
        font-weight: 600;
        font-size: 0.8em;
        padding: 2px 6px;
        border-radius: 3px;
        min-width: 25px;
        text-align: center;
        display: inline-block;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Analisa & Solusi Masalah</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="problem_list.php">Knowledge Base</a></li>
                        <li class="breadcrumb-item"><a href="problem_detail.php?id=<?= $id ?>">Detail</a></li>
                        <li class="breadcrumb-item active">Analisa & Solusi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Detail Problem -->
            <div class="card mb-4">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i>
                        Informasi Masalah
                        <?php if ($existingSolution): ?>
                            <span class="badge badge-success solution-badge">Solusi Tersedia</span>
                        <?php else: ?>
                            <span class="badge badge-warning solution-badge">Belum Ada Solusi</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <strong>No Kartu Produksi:</strong><br>
                                <?php if (!empty($problem['nocp'])): ?>
                                    <span class="nocp-badge"><?= htmlspecialchars($problem['nocp']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>

                            <div class="info-row">
                                <strong>Kategori:</strong><br>
                                <?= htmlspecialchars($problem['nama_kategori'] ?? '-'); ?>
                            </div>

                            <div class="info-row">
                                <strong>Tag:</strong><br>
                                <?= htmlspecialchars($problem['nama_tag'] ?? '-'); ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-row">
                                <strong>Informasi Teknis:</strong><br>
                                <?php if (!empty($problem['color'])): ?>
                                    <span class="technical-badge">Warna: <?= htmlspecialchars($problem['color']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($problem['pdr'])): ?>
                                    <span class="pdr-badge">PDR: <?= htmlspecialchars($problem['pdr']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($problem['routing'])): ?>
                                    <br><small class="text-muted">Routing:
                                        <?= htmlspecialchars($problem['routing']) ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="info-row">
                                <strong>Status:</strong><br>
                                <span class="badge badge-<?=
                                    $currentStatus == 'Solved' ? 'success' :
                                    ($currentStatus == 'Reopen' ? 'danger' : 'warning')
                                    ?>">
                                    <?= htmlspecialchars($currentStatus); ?>
                                </span>
                            </div>

                            <div class="info-row">
                                <strong>Dibuat Oleh:</strong><br>
                                <?= htmlspecialchars($problem['created_by']); ?>
                                <small class="text-muted">
                                    (<?= formatDate($problem['created_at']); ?>)
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="info-row mt-3">
                        <strong>Deskripsi Masalah:</strong>
                        <div class="bg-light p-3 rounded mt-2">
                            <?= nl2br(htmlspecialchars($problem['deskripsi'] ?? 'Tidak ada deskripsi')); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lampiran Analisa -->
            <div class="card mb-4">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-paperclip"></i> Lampiran Analisa
                        <span class="badge badge-light solution-badge"><?= count($attachments) ?> File</span>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Form Upload Lampiran -->
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="file-upload-container" id="uploadContainer">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-1">Klik atau drag & drop file lampiran di sini</p>
                            <small class="text-muted d-block">Maksimal 5MB per file</small>
                            <small class="text-muted">Format: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX</small>
                            <input type="file" class="d-none" id="attachments" name="attachments[]" multiple
                                accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx">
                        </div>
                        <div class="file-preview" id="filePreview"></div>

                        <div class="text-center mt-3">
                            <button type="submit" class="btn btn-primary" id="btnUpload" disabled>
                                <i class="fas fa-upload"></i> Upload Lampiran
                            </button>
                        </div>
                    </form>

                    <!-- Daftar Lampiran -->
                    <?php if (count($attachments) > 0): ?>
                        <hr>
                        <h6><i class="fas fa-list"></i> File Terupload</h6>
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-item">
                                <div>
                                    <a href="/gg_app/<?= htmlspecialchars($attachment['path_file']) ?>" target="_blank"
                                        class="text-primary">
                                        <i class="fas fa-file"></i> <?= htmlspecialchars($attachment['nama_file']) ?>
                                    </a>
                                </div>
                                <div>
                                    <small class="text-muted mr-2">
                                        <?= formatDate($attachment['uploaded_at']) ?>
                                    </small>
                                    <small class="text-muted">
                                        oleh <?= htmlspecialchars($attachment['uploaded_by']) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-file-excel fa-2x mb-2"></i>
                            <p class="mb-0">Belum ada lampiran</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Analisa & Solusi -->
            <?php if ($permissions['CanAdd'] == 1 || $permissions['CanEdit'] == 1): ?>
                <div class="card mb-4">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tools"></i> Form Analisa & Solusi
                            <?php if ($existingSolution): ?>
                                <span class="badge badge-light solution-badge">Edit Solusi</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <form method="POST">
                        <div class="card-body">

                            <div
                                class="form-section <?= !empty($solution['analisa_akar_masalah']) ? 'section-complete' : 'section-incomplete' ?>">
                                <h6>
                                    <i class="fas fa-search"></i> Analisa Akar Masalah
                                    <?php if (!empty($solution['analisa_akar_masalah'])): ?>
                                        <i class="fas fa-check-circle text-success ml-1"></i>
                                    <?php endif; ?>
                                </h6>
                                <div class="form-group">
                                    <label for="analisa_akar_masalah" class="required">Identifikasi penyebab utama
                                        masalah</label>
                                    <textarea name="analisa_akar_masalah" id="analisa_akar_masalah" class="form-control"
                                        rows="5"
                                        placeholder="Jelaskan analisa mendalam tentang akar penyebab masalah. Gunakan metode 5-Why atau fishbone diagram untuk analisa yang komprehensif..."
                                        required><?= htmlspecialchars($solution['analisa_akar_masalah']); ?></textarea>
                                    <small class="form-text text-muted">Wajib diisi. Analisa yang baik akan membantu
                                        menemukan solusi yang tepat.</small>
                                </div>
                            </div>

                            <div
                                class="form-section <?= !empty($solution['tindakan_perbaikan']) ? 'section-complete' : 'section-incomplete' ?>">
                                <h6>
                                    <i class="fas fa-wrench"></i> Tindakan Perbaikan
                                    <?php if (!empty($solution['tindakan_perbaikan'])): ?>
                                        <i class="fas fa-check-circle text-success ml-1"></i>
                                    <?php endif; ?>
                                </h6>
                                <div class="form-group">
                                    <label for="tindakan_perbaikan">Tindakan korektif yang dilakukan</label>
                                    <textarea name="tindakan_perbaikan" id="tindakan_perbaikan" class="form-control"
                                        rows="4"
                                        placeholder="Jelaskan langkah-langkah perbaikan yang telah atau akan dilakukan untuk mengatasi masalah saat ini..."><?= htmlspecialchars($solution['tindakan_perbaikan']); ?></textarea>
                                    <small class="form-text text-muted">Opsional. Deskripsikan tindakan perbaikan yang
                                        spesifik dan terukur.</small>
                                </div>
                            </div>

                            <div
                                class="form-section <?= !empty($solution['tindakan_pencegahan']) ? 'section-complete' : 'section-incomplete' ?>">
                                <h6>
                                    <i class="fas fa-shield-alt"></i> Tindakan Pencegahan
                                    <?php if (!empty($solution['tindakan_pencegahan'])): ?>
                                        <i class="fas fa-check-circle text-success ml-1"></i>
                                    <?php endif; ?>
                                </h6>
                                <div class="form-group">
                                    <label for="tindakan_pencegahan">Tindakan preventif untuk menghindari terulang</label>
                                    <textarea name="tindakan_pencegahan" id="tindakan_pencegahan" class="form-control"
                                        rows="4"
                                        placeholder="Jelaskan langkah-langkah pencegahan untuk menghindari terulangnya masalah di masa depan..."><?= htmlspecialchars($solution['tindakan_pencegahan']); ?></textarea>
                                    <small class="form-text text-muted">Opsional. Fokus pada pencegahan jangka
                                        panjang.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="status" class="required">Status Masalah</label>
                                <select name="status" id="status" class="form-control" required>
                                    <option value="Open" <?= ($currentStatus == 'Open') ? 'selected' : ''; ?>>Open - Masih
                                        dalam investigasi</option>
                                    <option value="Solved" <?= ($currentStatus == 'Solved') ? 'selected' : ''; ?>>Solved -
                                        Masalah telah diselesaikan</option>
                                    <option value="Reopen" <?= ($currentStatus == 'Reopen') ? 'selected' : ''; ?>>Reopen -
                                        Masalah muncul kembali</option>
                                </select>
                                <small class="form-text text-muted">Update status berdasarkan progress penanganan
                                    masalah.</small>
                            </div>

                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i>
                                <?= $existingSolution ? 'Update Analisa & Solusi' : 'Simpan Analisa & Solusi' ?>
                            </button>
                            <a href="problem_detail.php?id=<?= $id ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Detail
                            </a>
                            <a href="problem_list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Daftar Masalah
                            </a>
                            <?php if ($existingSolution): ?>
                                <a href="view_solution.php?id=<?= $id ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Lihat Solusi
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Anda tidak memiliki hak akses untuk menambah atau mengedit analisa & solusi.
                </div>
            <?php endif; ?>

            <!-- Riwayat Aktivitas -->
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i> Riwayat Aktivitas
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($histories)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Belum ada riwayat aktivitas.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($histories as $index => $h): ?>
                            <div class="history-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <strong class="text-primary"><?= htmlspecialchars($h['aksi']); ?></strong>
                                    <small class="history-time">
                                        <i class="far fa-user"></i> <?= htmlspecialchars($h['created_by']); ?> |
                                        <i class="far fa-clock"></i>
                                        <?= formatDate($h['created_at']); ?>
                                    </small>
                                </div>
                                <?php if (!empty($h['catatan'])): ?>
                                    <div class="mt-2"><?= nl2br(htmlspecialchars($h['catatan'])); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<script>
    // Validasi form dan upload
    $(document).ready(function () {
        // Auto-save indicator
        let isChanged = false;
        $('textarea, select').on('input change', function () {
            isChanged = true;
        });

        // Konfirmasi sebelum meninggalkan halaman jika ada perubahan
        $(window).on('beforeunload', function () {
            if (isChanged) {
                return 'Anda memiliki perubahan yang belum disimpan. Yakin ingin meninggalkan halaman?';
            }
        });

        // Reset flag ketika form disubmit
        $('form').on('submit', function () {
            isChanged = false;
        });

        // Auto-expand textarea berdasarkan konten
        $('textarea').on('input', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Trigger auto-expand untuk textarea yang sudah ada konten
        $('textarea').each(function () {
            if (this.value) {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            }
        });

        // ====== File Upload Functionality ======
        const fileInput = document.getElementById('attachments');
        const filePreview = document.getElementById('filePreview');
        const uploadContainer = document.getElementById('uploadContainer');
        const btnUpload = document.getElementById('btnUpload');
        let fileList = new DataTransfer();

        // Click on upload container to trigger file input
        uploadContainer.addEventListener('click', function () {
            fileInput.click();
        });

        // File input change event
        fileInput.addEventListener('change', function (e) {
            handleFiles(e.target.files);
        });

        // Drag and drop functionality
        uploadContainer.addEventListener('dragover', function (e) {
            e.preventDefault();
            uploadContainer.classList.add('dragover');
        });

        uploadContainer.addEventListener('dragleave', function (e) {
            e.preventDefault();
            uploadContainer.classList.remove('dragover');
        });

        uploadContainer.addEventListener('drop', function (e) {
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
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Tidak Didukung',
                        text: `File ${file.name} harus berupa JPG, PNG, GIF, PDF, DOC, DOCX, XLS, atau XLSX`
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

            // Enable upload button
            btnUpload.disabled = fileList.files.length === 0;
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
                removeBtn.onclick = function () {
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
            btnUpload.disabled = fileList.files.length === 0;
        }

        // Function to get file icon based on type
        function getFileIcon(fileType) {
            if (fileType.startsWith('image/')) return 'fa-file-image';
            if (fileType === 'application/pdf') return 'fa-file-pdf';
            if (fileType.includes('word')) return 'fa-file-word';
            if (fileType.includes('excel') || fileType.includes('sheet')) return 'fa-file-excel';
            return 'fa-file';
        }

        // Upload form submission
        $('#uploadForm').on('submit', function (e) {
            if (fileList.files.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih File',
                    text: 'Silakan pilih file terlebih dahulu'
                });
                return false;
            }

            // Show loading
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
        });
    });
</script>

<!-- SweetAlert Notifikasi -->
<?php if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= addslashes($_SESSION['success']) ?>',
            timer: 2000,
            showConfirmButton: false
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: '<?= addslashes($_SESSION['error']) ?>',
            confirmButtonText: 'Mengerti'
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>
<?php
// pages/fabric_knowledge/problem_detail.php
session_start();
ob_start();

require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Cek hak akses lihat detail
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $perm = ['CanView'=>0,'CanEdit'=>0,'CanDelete'=>0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $perm = $row;
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $perm;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 123); // Changed to 123 for Knowledge Base
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: problem_list.php');
    exit;
}

// ====== Get Problem ID ======
$id = $_GET['id'] ?? '';
if (empty($id)) {
    $_SESSION['error'] = "Data tidak ditemukan.";
    header('Location: problem_list.php');
    exit;
}

// ====== Fetch Problem Data ======
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

// ====== Fetch Solution Data ======
$solution = [];
$sqlSolution = "SELECT * FROM dbo.fab_m_solution WHERE id_problem = ?";
$stmtSolution = sqlsrv_query($conn, $sqlSolution, [$id]);
if ($stmtSolution) {
    $solution = sqlsrv_fetch_array($stmtSolution, SQLSRV_FETCH_ASSOC) ?: [];
}

// ====== Determine current status ======
$currentStatus = $solution['status'] ?? 'Open';

// ====== Fetch Attachments ======
$attachments = [];
$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.fab_t_attachment WHERE id_problem = ?", [$id]);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $attachments[] = $row;

// ====== Fetch History ======
$history = [];
$stmt = sqlsrv_query($conn, "SELECT * FROM dbo.fab_t_history WHERE id_problem = ? ORDER BY created_at DESC", [$id]);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $history[] = $row;

// ====== Format date function ======
function formatDate($date) {
    if (!$date) return '-';
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
.detail-label {font-weight: 600; color: #555;}
.detail-value {margin-bottom: 10px;}
.attachment-item {
    display: flex; align-items: center; justify-content: space-between;
    background: #fff; border: 1px solid #dee2e6; border-radius: 5px;
    padding: 8px 10px; margin-bottom: 5px;
}
.timeline {border-left: 3px solid #007bff; margin-left: 20px; padding-left: 15px;}
.timeline-item {margin-bottom: 15px;}
.timeline-date {font-size: 0.85rem; color: #888;}
.solution-section {
    background: #f8f9fa;
    border-left: 4px solid #28a745;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 5px;
}
.solution-header {
    color: #28a745;
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.solution-content {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #dee2e6;
    white-space: pre-line;
    line-height: 1.6;
}
.status-badge {
    font-size: 0.9em;
    padding: 6px 12px;
}
.nocp-badge {
    background-color: #e9ecef;
    color: #495057;
    font-family: monospace;
    font-size: 0.9em;
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #ced4da;
}
.info-box {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 5px;
}
.technical-info {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 5px;
}

</style>

<div class="content-wrapper">
    <div class="content-header">
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Detail Masalah Kain</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="problem_list.php">Problem List</a></li>
                <li class="breadcrumb-item active">Detail</li>
            </ol>
        </div>
    </div>
</div>
</div>

<div class="content">
<div class="container-fluid">
<div class="card">
<div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
    <h3 class="card-title">
        <i class="fas fa-search"></i> Informasi Masalah
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
        <a href="problem_list.php" class="btn btn-light btn-sm btn-back-to-list">
    <i class="fas fa-arrow-left"></i> Kembali
</a>
    
        <a href="analisa_solusi.php?id=<?= urlencode($id) ?>" class="btn btn-primary btn-sm"><i class="fas fa-lightbulb"></i> Analisa & Solusi</a>
        <?php if (!empty($solution)): ?>
            <a href="view_solution.php?id=<?= urlencode($id) ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> Lihat Solusi</a>
        <?php endif; ?>
    </div>
</div>

<div class="card-body">
    <!-- Basic Information -->
    <div class="row">
        <div class="col-md-6">
            <!-- No Kartu Produksi -->
            <div class="detail-label">No Kartu Produksi</div>
            <div class="detail-value">
                <?php if (!empty($problem['nocp'])): ?>
                    <span class="nocp-badge"><?= htmlspecialchars($problem['nocp']) ?></span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </div>

            <div class="detail-label">Kategori</div>
            <div class="detail-value"><?= htmlspecialchars($problem['nama_kategori'] ?? '-') ?></div>
            <div class="detail-label">Tag</div>
            <div class="detail-value"><?= htmlspecialchars($problem['nama_tag'] ?? '-') ?></div>
            <div class="detail-label">Kode Warna</div>
            <div class="detail-value"><?= !empty($problem['color']) ? htmlspecialchars($problem['color']) : '<span class="text-muted">-</span>' ?></div>
            <div class="detail-label">Routing</div>
            <div class="detail-value"><?= !empty($problem['routing']) ? htmlspecialchars($problem['routing']) : '<span class="text-muted">-</span>' ?></div>
            <div class="detail-label">Status QC</div>
            <div class="detail-value"><?php if (!empty($problem['status_qc'])): ?>
                        <span class="badge badge-<?= 
                            $problem['status_qc'] == 'Pass' ? 'success' : 
                            ($problem['status_qc'] == 'Fail' ? 'danger' : 'warning')
                        ?>">
                            <?= htmlspecialchars($problem['status_qc']) ?>
                        </span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                </div>
            <div class="detail-label">Tgl Lkp Qc</div>
            <div class="detail-value">
                <?= !empty($problem['tgl_lkp_qc']) ? htmlspecialchars($problem['tgl_lkp_qc'] instanceof DateTime ? $problem['tgl_lkp_qc']->format('d-m-Y') : date('d-m-Y', strtotime($problem['tgl_lkp_qc']))) : '<span class="text-muted">-</span>' ?>
            </div>
            <div class="detail-label">PDR</div>
            <div class="detail-value"><?= !empty($problem['pdr']) ? htmlspecialchars($problem['pdr']) : '<span class="text-muted">-</span>' ?></div>
        </div>

        <div class="col-md-6">                
            <div class="detail-label">Resep</div>
            <div class="detail-value"><?= !empty($problem['resep']) ? htmlspecialchars($problem['resep']) : '<span class="text-muted">-</span>' ?></div>
            <div class="detail-label">Analis</div>
            <div class="detail-value">
                <?= !empty($problem['analis']) ? htmlspecialchars($problem['analis']) : '<span class="text-muted">-</span>' ?>
            </div>

            <div class="detail-label">Review</div>
            <div class="detail-value">
                <?= !empty($problem['review']) ? htmlspecialchars($problem['review']) : '<span class="text-muted">-</span>' ?>
            </div>
     

            <div class="detail-label">Dibuat Oleh</div>
            <div class="detail-value">
                <?= htmlspecialchars($problem['created_by']) ?> 
                <small class="text-muted">(<?= formatDate($problem['created_at']) ?>)</small>
            </div>

            <div class="detail-label">Terakhir Diperbarui</div>
            <div class="detail-value">
                <?= htmlspecialchars($problem['updated_by'] ?? $problem['created_by']) ?> 
                <small class="text-muted">(<?= formatDate($problem['updated_at'] ?? $problem['created_at']) ?>)</small>
            </div>

            <div class="detail-label">Lampiran</div>
            <?php if (count($attachments) > 0): ?>
                <?php foreach ($attachments as $a): ?>
                    <div class="attachment-item">
                        <a href="/gg_app/<?= htmlspecialchars($a['path_file']) ?>" target="_blank" class="text-primary">
                            <i class="fas fa-paperclip"></i> <?= htmlspecialchars($a['nama_file']) ?>
                        </a>
                        <small class="text-muted"><?= formatDate($a['uploaded_at']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted"><i>Tidak ada lampiran</i></p>
            <?php endif; ?>
        </div>
    </div>

    <hr>

    <!-- Problem Description -->
    <div class="row">
        <div class="col-12">
            <div class="detail-label">Deskripsi Masalah</div>
            <div class="detail-value border p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($problem['deskripsi'] ?? 'Tidak ada deskripsi')) ?>
            </div>
        </div>
    </div>

    <!-- Existing Solution -->
    <?php if (!empty($problem['solusi'])): ?>
    <hr>
    <div class="row">
        <div class="col-12">
            <div class="solution-section">
                <h6 class="text-success"><i class="fas fa-check-circle"></i> Solusi yang Diberikan</h6>
                <div class="solution-content">
                    <?= nl2br(htmlspecialchars($problem['solusi'])) ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Solution Information -->
    <?php if (!empty($solution)): ?>
    <hr>
    <div class="row">
        <div class="col-12">
            <h5 class="solution-header">
                <i class="fas fa-check-circle"></i> Analisa & Solusi
                <small class="text-muted float-right">
                    Terakhir update: <?= formatDate($solution['updated_at'] ?? $solution['created_at']) ?>
                    oleh <?= htmlspecialchars($solution['updated_by'] ?? $solution['created_by']) ?>
                </small>
            </h5>

            <!-- Analisa Akar Masalah -->
            <?php if (!empty($solution['analisa_akar_masalah'])): ?>
            <div class="solution-section">
                <h6 class="text-primary"><i class="fas fa-search"></i> Analisa Akar Masalah</h6>
                <div class="solution-content">
                    <?= nl2br(htmlspecialchars($solution['analisa_akar_masalah'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tindakan Perbaikan -->
            <?php if (!empty($solution['tindakan_perbaikan'])): ?>
            <div class="solution-section">
                <h6 class="text-warning"><i class="fas fa-wrench"></i> Tindakan Perbaikan</h6>
                <div class="solution-content">
                    <?= nl2br(htmlspecialchars($solution['tindakan_perbaikan'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tindakan Pencegahan -->
            <?php if (!empty($solution['tindakan_pencegahan'])): ?>
            <div class="solution-section">
                <h6 class="text-success"><i class="fas fa-shield-alt"></i> Tindakan Pencegahan</h6>
                <div class="solution-content">
                    <?= nl2br(htmlspecialchars($solution['tindakan_pencegahan'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Jika tidak ada solusi lengkap -->
            <?php if (empty($solution['analisa_akar_masalah']) && empty($solution['tindakan_perbaikan']) && empty($solution['tindakan_pencegahan'])): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Solusi telah dibuat namun konten analisa dan solusi belum diisi.
                <a href="analisa_solusi.php?id=<?= $id ?>" class="alert-link">Klik di sini untuk melengkapi</a>.
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <hr>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Belum ada analisa dan solusi untuk masalah ini.</strong>
                <a href="analisa_solusi.php?id=<?= $id ?>" class="alert-link float-right">
                    <i class="fas fa-lightbulb"></i> Buat Analisa & Solusi
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- History -->
    <hr>
    <div class="row">
        <div class="col-12">
            <h5><i class="fas fa-history"></i> Riwayat Aktivitas</h5>
            <?php if (count($history) > 0): ?>
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <strong class="text-primary"><?= htmlspecialchars($h['aksi']) ?></strong>
                            <?php if (!empty($h['catatan'])): ?>
                                — <?= nl2br(htmlspecialchars($h['catatan'])) ?>
                            <?php endif; ?>
                            <br>
                            <span class="timeline-date">
                                <i class="far fa-user"></i> <?= htmlspecialchars($h['created_by']) ?> 
                                | <i class="far fa-clock"></i> <?= formatDate($h['created_at']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted"><i>Belum ada riwayat aktivitas.</i></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Card Footer with Action Buttons -->
<div class="card-footer">
    <div class="row">
        <div class="col-md-6">
            <a href="problem_list.php" class="btn btn-secondary btn-back-to-list">
    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
</a>
            <!-- <?php if ($permissions['CanEdit'] == 1): ?>
                <a href="edit_problem.php?id=<?= urlencode($id) ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Masalah
                </a>
            <?php endif; ?> -->
        </div>
        <div class="col-md-6 text-right">
            <a href="analisa_solusi.php?id=<?= urlencode($id) ?>" class="btn btn-primary">
                <i class="fas fa-lightbulb"></i> Analisa & Solusi
            </a>
            <?php if (!empty($solution)): ?>
                <a href="view_solution.php?id=<?= urlencode($id) ?>" class="btn btn-info">
                    <i class="fas fa-eye"></i> Lihat Solusi Lengkap
                </a>
            <?php endif; ?>
            <?php if ($permissions['CanDelete'] == 1): ?>
                <a href="delete_problem.php?id=<?= urlencode($id) ?>" class="btn btn-danger" 
                   onclick="return confirm('Apakah Anda yakin ingin menghapus masalah ini?')">
                    <i class="fas fa-trash"></i> Hapus
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>
</div>
</div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>



<!-- SweetAlert untuk Notifikasi -->
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

// problem_detail.php - Tambahkan di bagian script

// Fungsi untuk kembali ke halaman list dengan state yang disimpan
function goBackToProblemList() {
    // Arahkan ke halaman list
    window.location.href = 'problem_list.php';
}

// Event listener untuk tombol "Kembali"
$(document).ready(function() {
    $('.btn-back-to-list').on('click', function(e) {
        e.preventDefault();
        goBackToProblemList();
    });
    
    // Juga tangani tombol browser back
    window.addEventListener('popstate', function() {
        goBackToProblemList();
    });
});
</script>
<?php unset($_SESSION['error']); endif; ?>

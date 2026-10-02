<?php
// pages/fabric_knowledge/view_solution.php
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
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $perm = ['CanView' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $perm = $row;
    }
    return $perm;
}
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 123);

if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: problem_list.php');
    exit;
}

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

// ====== Ambil Data Solusi ======
$sqlSolution = "SELECT * FROM dbo.fab_m_solution WHERE id_problem = ?";
$stmtSolution = sqlsrv_query($conn, $sqlSolution, [$id]);
$solution = sqlsrv_fetch_array($stmtSolution, SQLSRV_FETCH_ASSOC);

if (!$solution) {
    $_SESSION['error'] = "Belum ada analisa dan solusi untuk masalah ini.";
    header('Location: problem_list.php');
    exit;
}

// ====== Ambil Data Lampiran ======
$attachments = [];
$sqlAttachments = "SELECT * FROM dbo.fab_t_attachment WHERE id_problem = ? ORDER BY uploaded_at DESC";
$stmtAttachments = sqlsrv_query($conn, $sqlAttachments, [$id]);
while ($row = sqlsrv_fetch_array($stmtAttachments, SQLSRV_FETCH_ASSOC)) {
    $attachments[] = $row;
}

// ====== Inisialisasi status ======
$problemStatus = $solution['status'] ?? 'Open'; // Ambil status dari tabel solution

// ====== Format tanggal ======
function formatTanggal($date)
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

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Lihat Solusi - <?= htmlspecialchars($problem['nocp'] ?? 'No NOCP'); ?></title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
    <script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

    <style>
        .solution-section {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .solution-header {
            color: #007bff;
            border-bottom: 2px solid #e9ecef;
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

        .metadata {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 0.9em;
        }

        .print-only {
            display: none;
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #007bff;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 0.9em;
        }

        .info-value {
            margin-top: 5px;
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

        .technical-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .production-info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .print-only {
                display: block;
            }

            .solution-section {
                border-left: 4px solid #000 !important;
                background: #fff !important;
                margin-bottom: 15px;
            }

            .solution-content {
                border: 1px solid #000 !important;
            }

            .card {
                border: 1px solid #000 !important;
            }

            .nocp-badge {
                background-color: #fff !important;
                border: 1px solid #000 !important;
                color: #000 !important;
            }

            .technical-badge,
            .pdr-badge {
                background-color: #fff !important;
                border: 1px solid #000 !important;
                color: #000 !important;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Detail Analisa & Solusi</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="problem_list.php">Knowledge Base</a></li>
                            <li class="breadcrumb-item"><a href="analisa_solusi.php?id=<?= $id ?>">Analisa & Solusi</a>
                            </li>
                            <li class="breadcrumb-item active">Lihat Solusi</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Header Info -->
                <div class="card mb-4">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-alt"></i>
                            Informasi Masalah & Solusi
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Grid Informasi Dasar -->
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">No Kartu Produksi</div>
                                <div class="info-value">
                                    <?php if (!empty($problem['nocp'])): ?>
                                        <span class="nocp-badge"><?= htmlspecialchars($problem['nocp']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Kategori</div>
                                <div class="info-value"><?= htmlspecialchars($problem['nama_kategori']); ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Tag</div>
                                <div class="info-value"><?= htmlspecialchars($problem['nama_tag']); ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <span class="badge badge-<?=
                                        $problemStatus == 'Solved' ? 'success' :
                                        ($problemStatus == 'Reopen' ? 'danger' : 'warning')
                                        ?>">
                                        <?= htmlspecialchars($problemStatus); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Dibuat Oleh</div>
                                <div class="info-value"><?= htmlspecialchars($problem['created_by']); ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Tanggal Update</div>
                                <div class="info-value">
                                    <?= formatTanggal($solution['updated_at'] ?? $solution['created_at']); ?></div>
                            </div>
                        </div>

                        <!-- Informasi Teknis -->
                        <div class="technical-info">
                            <h6 class="section-title"><i class="fas fa-cogs"></i> Informasi Teknis</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-label">Kode Warna</div>
                                    <div class="info-value">
                                        <?= !empty($problem['color']) ? htmlspecialchars($problem['color']) : '<span class="text-muted">-</span>' ?>
                                    </div>

                                    <div class="info-label">Routing</div>
                                    <div class="info-value">
                                        <?= !empty($problem['routing']) ? htmlspecialchars($problem['routing']) : '<span class="text-muted">-</span>' ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Status QC</div>
                                    <div class="info-value">
                                        <?php if (!empty($problem['status_qc'])): ?>
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

                                    <div class="info-label">Tgl Lkp Qc</div>
                                    <div class="info-value">
                                        <?= !empty($problem['tgl_lkp_qc']) ? htmlspecialchars($problem['tgl_lkp_qc'] instanceof DateTime ? $problem['tgl_lkp_qc']->format('d-m-Y') : date('d-m-Y', strtotime($problem['tgl_lkp_qc']))) : '<span class="text-muted">-</span>' ?>
                                    </div>

                                    <div class="info-label">PDR</div>
                                    <div class="info-value">
                                        <?= !empty($problem['pdr']) ? htmlspecialchars($problem['pdr']) : '<span class="text-muted">-</span>' ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi Produksi -->
                        <div class="production-info">
                            <h6 class="section-title"><i class="fas fa-industry"></i> Informasi Produksi</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="info-label">Resep</div>
                                    <div class="info-value">
                                        <?= !empty($problem['resep']) ? htmlspecialchars($problem['resep']) : '<span class="text-muted">-</span>' ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label">Analis</div>
                                    <div class="info-value">
                                        <?= !empty($problem['analis']) ? htmlspecialchars($problem['analis']) : '<span class="text-muted">-</span>' ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-label">Review</div>
                                    <div class="info-value">
                                        <?= !empty($problem['review']) ? htmlspecialchars($problem['review']) : '<span class="text-muted">-</span>' ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Deskripsi Masalah -->
                        <div class="mt-3">
                            <div class="info-label">Deskripsi Masalah</div>
                            <div class="bg-light p-3 rounded mt-2">
                                <?= nl2br(htmlspecialchars($problem['deskripsi'])); ?>
                            </div>
                        </div>

                        <!-- Lampiran -->
                        <?php if (count($attachments) > 0): ?>
                            <div class="mt-4">
                                <div class="info-label">Lampiran Analisa</div>
                                <div class="mt-2">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <div class="attachment-item">
                                            <div>
                                                <a href="/gg_app/<?= htmlspecialchars($attachment['path_file']) ?>"
                                                    target="_blank" class="text-primary">
                                                    <i class="fas fa-file"></i>
                                                    <?= htmlspecialchars($attachment['nama_file']) ?>
                                                </a>
                                            </div>
                                            <div>
                                                <small class="text-muted mr-2">
                                                    <?= formatTanggal($attachment['uploaded_at']) ?>
                                                </small>
                                                <small class="text-muted">
                                                    oleh <?= htmlspecialchars($attachment['uploaded_by']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Analisa Akar Masalah -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-search"></i> Analisa Akar Masalah
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($solution['analisa_akar_masalah'])): ?>
                            <div class="solution-content">
                                <?= nl2br(htmlspecialchars($solution['analisa_akar_masalah'])); ?>
                            </div>
                            <div class="metadata mt-3">
                                <small>
                                    <i class="far fa-user"></i> Dibuat oleh:
                                    <?= htmlspecialchars($solution['created_by']); ?>
                                    pada <?= formatTanggal($solution['created_at']); ?>
                                    <?php if (!empty($solution['updated_by'])): ?>
                                        | <i class="fas fa-edit"></i> Diupdate oleh:
                                        <?= htmlspecialchars($solution['updated_by']); ?>
                                        pada <?= formatTanggal($solution['updated_at']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-search fa-2x mb-3"></i>
                                <p>Belum ada analisa akar masalah.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tindakan Perbaikan -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-wrench"></i> Tindakan Perbaikan
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($solution['tindakan_perbaikan'])): ?>
                            <div class="solution-content">
                                <?= nl2br(htmlspecialchars($solution['tindakan_perbaikan'])); ?>
                            </div>
                            <?php if (!empty($solution['updated_at'])): ?>
                                <div class="metadata mt-3">
                                    <small>
                                        <i class="fas fa-edit"></i> Terakhir diupdate:
                                        <?= formatTanggal($solution['updated_at']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-wrench fa-2x mb-3"></i>
                                <p>Belum ada tindakan perbaikan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tindakan Pencegahan -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-shield-alt"></i> Tindakan Pencegahan
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($solution['tindakan_pencegahan'])): ?>
                            <div class="solution-content">
                                <?= nl2br(htmlspecialchars($solution['tindakan_pencegahan'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-shield-alt fa-2x mb-3"></i>
                                <p>Belum ada tindakan pencegahan.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card no-print">
                    <div class="card-body text-center">
                        <a href="analisa_solusi.php?id=<?= $id ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Analisa & Solusi
                        </a>
                        <a href="problem_list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                        </a>
                        <button onclick="window.print()" class="btn btn-info">
                            <i class="fas fa-print"></i> Cetak Solusi
                        </button>
                        <!-- TOMBOL PDF BARU -->
                        <a href="generate_pdf_single.php?id=<?= $id ?>" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </a>
                        <a href="problem_detail.php?id=<?= $id ?>" class="btn btn-outline-dark">
                            <i class="fas fa-eye"></i> Lihat Detail Masalah
                        </a>
                    </div>
                </div>

                <!-- Print Header (hanya tampil saat print) -->
                <div class="print-only">
                    <div class="text-center mb-4">
                        <h2>Analisa & Solusi Masalah Produksi</h2>
                        <h4>No CP: <?= !empty($problem['nocp']) ? htmlspecialchars($problem['nocp']) : 'No NOCP' ?></h4>
                        <p>
                            Kategori: <?= htmlspecialchars($problem['nama_kategori']); ?> |
                            Tag: <?= htmlspecialchars($problem['nama_tag']); ?> |
                            Status: <?= htmlspecialchars($problemStatus); ?> |
                            Warna: <?= !empty($problem['color']) ? htmlspecialchars($problem['color']) : '-' ?> |
                            Status QC:
                            <?= !empty($problem['status_qc']) ? htmlspecialchars($problem['status_qc']) : '-' ?> |
                            Tgl Lkp Qc:
                            <?= !empty($problem['tgl_lkp_qc']) ? htmlspecialchars($problem['tgl_lkp_qc'] instanceof DateTime ? $problem['tgl_lkp_qc']->format('d-m-Y') : date('d-m-Y', strtotime($problem['tgl_lkp_qc']))) : '-' ?>
                            |
                            PDR: <?= !empty($problem['pdr']) ? htmlspecialchars($problem['pdr']) : '-' ?>
                        </p>
                        <p>Dicetak pada: <?= date('d-m-Y H:i'); ?></p>
                        <hr>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Fungsi untuk print
        function printSolution() {
            window.print();
        }

        // Keyboard shortcut untuk print (Ctrl + P)
        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printSolution();
            }
        });

        // SweetAlert untuk notifikasi
        <?php if (!empty($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= addslashes($_SESSION['success']) ?>',
                timer: 2000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); endif; ?>

        <?php if (!empty($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '<?= addslashes($_SESSION['error']) ?>',
                confirmButtonText: 'Mengerti'
            });
            <?php unset($_SESSION['error']); endif; ?>
    </script>

</body>

</html>

<?php include '../../includes/footer.php'; ?>
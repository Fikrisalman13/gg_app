<?php
// Start session and output buffering
session_start();
ob_start();

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Include necessary files
require_once '../../koneksi.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check database connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Authorization check
$groupId = $_SESSION['GroupId'];
$menuId = 56; // Menu ID untuk halaman Asset

// Check if user has CanView permission
$sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Kesalahan saat mengambil hak akses: " . print_r(sqlsrv_errors(), true));
}

$canView = false;
if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $canView = $row['CanView'] == 1;
}
sqlsrv_free_stmt($stmt);

// Check if user has CanEdit permission for edit button
$sqlEdit = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmtEdit = sqlsrv_query($conn, $sqlEdit, $params);
$canEdit = false;
if ($stmtEdit !== false && $row = sqlsrv_fetch_array($stmtEdit, SQLSRV_FETCH_ASSOC)) {
    $canEdit = $row['CanEdit'] == 1;
}
sqlsrv_free_stmt($stmtEdit);

// Redirect if user doesn't have permission
if (!$canView) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat data.";
    header('Location: asset.php');
    exit;
}

// Check if asset ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID Asset tidak valid.";
    header('Location: asset.php');
    exit;
}

$assetId = $_GET['id'];

// Fetch asset data with joins to get related information
$sql = "SELECT 
            a.*,
            ka.kode_asset AS jenis_asset,
            k.nama_kategori,
            m.nama_merk,
            t.nama_tipe,
            l.nama_lokasi,
            l.divisi,
            s.nama_status,
            e.nama_lengkap AS nama_pegawai
        FROM dbo.m_asset a
        LEFT JOIN dbo.m_kode_asset ka ON a.id_kode = ka.id_kode
        LEFT JOIN dbo.m_kategori k ON a.id_kategori = k.id_kategori
        LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
        LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
        LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
        LEFT JOIN dbo.m_status s ON a.id_status = s.id_status
        LEFT JOIN dbo.m_emp e ON a.id_emp = e.id_emp
        WHERE a.id_asset = ?";
$params = [$assetId];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Gagal mengambil data asset: " . print_r(sqlsrv_errors(), true));
}

$assetData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$assetData) {
    $_SESSION['error'] = "Asset tidak ditemukan.";
    header('Location: asset.php');
    exit;
}

// Fetch history for display
$historyRows = [];
$hSql = "SELECT TOP 10 id_history, id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at
         FROM dbo.asset_history
         WHERE id_asset = ?
         ORDER BY created_at DESC";
$hstmt = sqlsrv_query($conn, $hSql, [$assetId]);
if ($hstmt !== false) {
    while ($hr = sqlsrv_fetch_array($hstmt, SQLSRV_FETCH_ASSOC)) {
        $historyRows[] = $hr;
    }
    sqlsrv_free_stmt($hstmt);
}

// Format date for display
function formatDateDisplay($date) {
    if (empty($date)) return '-';
    
    if ($date instanceof DateTime) {
        return $date->format('d-m-Y');
    } elseif (is_string($date)) {
        $dt = date_create($date);
        return $dt ? $dt->format('d-m-Y') : '-';
    }
    return '-';
}

function formatDateTimeDisplay($date) {
    if (empty($date)) return '-';
    
    if ($date instanceof DateTime) {
        return $date->format('d-m-Y H:i');
    } elseif (is_string($date)) {
        $dt = date_create($date);
        return $dt ? $dt->format('d-m-Y H:i') : '-';
    }
    return '-';
}

/**
 * Detects ticket numbers in text (e.g. TKT-27-12-2025-0001) 
 * and wraps them in a link to the ticket detail page.
 * Distinguishes between existing (blue) and deleted (red) tickets.
 */
function linkTicketNumbers($text, $conn) {
    if (empty($text) || !$conn) return $text;
    
    // Pattern for TKT-DD-MM-YYYY-XXXX
    return preg_replace_callback('/(TKT-\d{2}-\d{2}-\d{4}-\d+)/', function($matches) use ($conn) {
        $ticketNo = $matches[1];
        
        // Check if ticket exists in database
        $exists = false;
        $sql = "SELECT TOP 1 ticket_id FROM dbo.tickets WHERE ticket_no = ?";
        $stmt = sqlsrv_query($conn, $sql, [$ticketNo]);
        if ($stmt && sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $exists = true;
        }
        if ($stmt) sqlsrv_free_stmt($stmt);

        if ($exists) {
            return '<a href="/gg_app/pages/ticket/detail.php?no='.$ticketNo.'" class="text-primary font-weight-bold" title="View Ticket Detail">'.$ticketNo.'</a>';
        } else {
            return '<span class="text-danger font-weight-bold" title="Ticket telah dihapus atau tidak ditemukan">'.$ticketNo.'</span>';
        }
    }, $text);
}


$tanggal_pembelian_display = formatDateDisplay($assetData['tanggal_pembelian']);
$upddate_display = formatDateTimeDisplay($assetData['upddate']);
$tgl_rusak_display = formatDateDisplay($assetData['tgl_rusak']);
?>


    <style>
       
        .small-muted { 
            font-size: .85rem; 
            color:#6c757d; 
        }
        .info-section-title {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: #495057;
        }
        .asset-id-display {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #007bff;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .history-table th, .history-table td { 
            vertical-align: middle; 
            font-size: 0.875rem;
        }
        .history-note {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .info-label {
            font-weight: 500;
            color: #495057;
        }
        .info-value {
            color: #212529;
            font-weight: 400;
        }
    </style>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">Detail Asset</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php"> Home</a></li>
                            <li class="breadcrumb-item"><a href="asset.php"> Asset</a></li>
                            <li class="breadcrumb-item active">Detail Asset</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    
                        <!-- Main Asset Information Card -->
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                                <h3 class="card-title">Informasi Asset</h3>
                                <div class="card-tools">
                                    <?php if ($canEdit): ?>
                                        <a href="edit_asset.php?id=<?= $assetId ?>" class="btn btn-sm btn-primary mr-2">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <a href="asset.php" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                                    </a>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="asset-id-display">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>ID Asset:</strong> <?= htmlspecialchars($assetData['id_asset']) ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Kode Asset:</strong> <?= htmlspecialchars($assetData['kode_asset_seq']) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="info-section-title">Informasi Utama</h5>
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4 info-label">Jenis Asset</dt>
                                            <dd class="col-sm-8 info-value"><?= htmlspecialchars($assetData['jenis_asset']) ?></dd>

                                            <dt class="col-sm-4 info-label">Kategori</dt>
                                            <dd class="col-sm-8 info-value"><?= htmlspecialchars($assetData['nama_kategori']) ?></dd>

                                            <dt class="col-sm-4 info-label">Merk</dt>
                                            <dd class="col-sm-8 info-value"><?= htmlspecialchars($assetData['nama_merk']) ?></dd>

                                            <dt class="col-sm-4 info-label">Tipe</dt>
                                            <dd class="col-sm-8 info-value"><?= htmlspecialchars($assetData['nama_tipe']) ?></dd>

                                            <dt class="col-sm-4 info-label">Serial Number</dt>
                                            <dd class="col-sm-8 info-value"><?= htmlspecialchars($assetData['serial_number']) ?></dd>
                                        </dl>
                                    </div>

                                    <div class="col-md-6">
                                        <h5 class="info-section-title">Status & Lokasi</h5>
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4 info-label">Lokasi</dt>
                                            <dd class="col-sm-8 info-value"><?= htmlspecialchars($assetData['nama_lokasi']) ?></dd>
                                            
                                            <dt class="col-sm-4 info-label">Divisi</dt>
                                            <dd class="col-sm-8 info-value"><?= htmlspecialchars($assetData['divisi']) ?></dd>

                                            <dt class="col-sm-4 info-label">Status</dt>
                                            <dd class="col-sm-8 info-value">
                                                <?php 
                                                $currentStatus = $assetData['nama_status'];
                                                $statusClass = 'badge-secondary';
                                                if (strpos(strtolower($currentStatus), 'baik') !== false) $statusClass = 'badge-success';
                                                if (strpos(strtolower($currentStatus), 'rusak') !== false) $statusClass = 'badge-danger';
                                                if (strpos(strtolower($currentStatus), 'maintenance') !== false) $statusClass = 'badge-warning';
                                                ?>
                                                <span class="badge <?= $statusClass ?> status-badge"><?= htmlspecialchars($currentStatus) ?></span>
                                            </dd>

                                            <dt class="col-sm-4 info-label">Pegawai</dt>
                                            <dd class="col-sm-8 info-value"><?= !empty($assetData['nama_pegawai']) ? htmlspecialchars($assetData['nama_pegawai']) : '-' ?></dd>

                                            <dt class="col-sm-4 info-label">Tanggal Pembelian</dt>
                                            <dd class="col-sm-8 info-value"><?= $tanggal_pembelian_display ?></dd>

                                            <dt class="col-sm-4 info-label">No PO</dt>
                                            <dd class="col-sm-8 info-value"><?= !empty($assetData['no_po']) ? htmlspecialchars($assetData['no_po']) : '-' ?></dd>
                                        </dl>
                                    </div>
                                </div>

                                <?php if (!empty($assetData['tgl_rusak']) || !empty($assetData['ket_rusak'])): ?>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <h5 class="info-section-title">Informasi Kerusakan</h5>
                                        <dl class="row mb-0">
                                            <?php if (!empty($assetData['tgl_rusak'])): ?>
                                            <dt class="col-sm-2 info-label">Tanggal Rusak</dt>
                                            <dd class="col-sm-4 info-value"><?= $tgl_rusak_display ?></dd>
                                            <?php endif; ?>
                                            <?php if (!empty($assetData['ket_rusak'])): ?>
                                            <dt class="col-sm-2 info-label">Keterangan Rusak</dt>
                                            <dd class="col-sm-4 info-value"><?= !empty($assetData['ket_rusak']) ? linkTicketNumbers(htmlspecialchars($assetData['ket_rusak']), $conn) : '-' ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <h5 class="info-section-title">Keterangan</h5>
                                        <div class="info-value">
                                            <?= !empty($assetData['keterangan']) ? nl2br(linkTicketNumbers(htmlspecialchars($assetData['keterangan']), $conn)) : '-' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <div class="small-muted">
                                    <i class="fas fa-user mr-1"></i>Dibuat oleh: <?= htmlspecialchars($assetData['upduser']) ?>
                                </div>
                                <div class="small-muted">
                                    <i class="fas fa-history mr-1"></i>Terakhir diupdate: <?= $upddate_display ?>
                                </div>
                            </div>
                        </div>

                        <!-- History Card -->
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                                <h3 class="card-title">Riwayat Perubahan Asset</h3>
                                <div class="card-tools">
                                    <span class="badge badge-light"><?= count($historyRows) ?> Riwayat</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($historyRows)): ?>
                                    <div class="p-3 text-center text-muted">
                                        <i class="fas fa-info-circle mr-1"></i>Belum ada riwayat perubahan untuk asset ini.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead class="thead-light">

                                            <tr>
                                            <th>#</th>
                                            <th>Tanggal</th>
                                            <th>User</th>
                                            <th>Status</th>
                                            <th>Perubahan</th>
                                            <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($historyRows as $h): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($h['id_history']) ?></td>
                                                <td><?= isset($h['created_at']) && $h['created_at'] instanceof DateTime ? $h['created_at']->format('d-m-Y H:i') : htmlspecialchars($h['created_at']) ?></td>
                                                <td><?= htmlspecialchars($h['created_by']) ?></td>
                                                <td><?= htmlspecialchars(($h['old_status'] ?: '-') . ' → ' . ($h['new_status'] ?: '-')) ?></td>
                                                <td><?= htmlspecialchars($h['jenis_perubahan']) ?></td>
                                                <td><?= linkTicketNumbers(htmlspecialchars($h['note']), $conn) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    
                </div>
            </div>
        </section>
    </div>
</div>

<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<?php
ob_end_flush();
?>
<?php
/**
 * Halaman Utama PR Online (Purchase Request Online)
 * Manajemen pemilihan PR dan rincian produk dinamis dari ERP PostgreSQL untuk PO Online
 * Dilengkapi status pengerjaan, aksi Ambil/Assign PIC, deteksi No PO dari ERP, dan Summary Cards
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(__DIR__, 3);
require_once $rootPath . '/koneksi.php';
require_once $rootPath . '/koneksi3.php';

// Validasi autentikasi login
if (empty($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu untuk mengakses menu ini.';
    header('Location: /gg_app/login.php');
    exit;
}

date_default_timezone_set('Asia/Jakarta');
$themeColor         = $_SESSION['Theme'] ?? 'primary';
$currentUserName   = trim($_SESSION['UserName'] ?? '');
$currentNamaLengkap = trim($_SESSION['NamaLengkap'] ?? $currentUserName);
$groupId            = (int)($_SESSION['GroupId'] ?? 0);
$menuId             = 1355; // PR Online

// Cek hak akses menu
$permStmt = sqlsrv_query($conn, 'SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?', [$groupId, $menuId]);
$permissions = $permStmt ? sqlsrv_fetch_array($permStmt, SQLSRV_FETCH_ASSOC) : [];
$canAdd = !empty($permissions['CanAdd']) || $groupId == 1;
$canDelete = !empty($permissions['CanDelete']) || $groupId == 1;

// Sinkronisasi otomatis No PO dari ERP untuk PR yang belum berstatus Selesai
$sqlSyncCheck = "SELECT pphdid FROM dbo.pr_online_header WHERE status_pengerjaan != 'Selesai' OR no_po IS NULL OR no_po = ''";
$stmtSyncCheck = sqlsrv_query($conn, $sqlSyncCheck);
$activePphdids = [];
if ($stmtSyncCheck !== false) {
    while ($rSync = sqlsrv_fetch_array($stmtSyncCheck, SQLSRV_FETCH_ASSOC)) {
        $activePphdids[] = (int)$rSync['pphdid'];
    }
}

if (!empty($activePphdids)) {
    $activePphdids = array_unique($activePphdids);
    $placeholders = implode(',', array_fill(0, count($activePphdids), '?'));
    $sqlPgSync = "SELECT DISTINCT 
                    d.popphdid, 
                    p.ponmbr, 
                    p.podate 
                  FROM prpodt d 
                  JOIN prpohd p ON d.pohdid = p.pohdid 
                  WHERE d.popphdid IN ($placeholders) 
                    AND p.ponmbr IS NOT NULL 
                    AND TRIM(p.ponmbr) != ''";
    $stmtPgSync = $conn3->prepare($sqlPgSync);
    $stmtPgSync->execute(array_values($activePphdids));
    $foundPos = $stmtPgSync->fetchAll(PDO::FETCH_ASSOC);

    foreach ($foundPos as $po) {
        $poPphdid = (int)$po['popphdid'];
        $poNmbr   = trim($po['ponmbr']);
        $poDate   = !empty($po['podate']) ? substr($po['podate'], 0, 10) : null;
        $upSql = "UPDATE dbo.pr_online_header 
                  SET no_po = ?, po_date = ?, status_pengerjaan = 'Selesai' 
                  WHERE pphdid = ? AND (no_po IS NULL OR no_po = '' OR status_pengerjaan != 'Selesai')";
        sqlsrv_query($conn, $upSql, [$poNmbr, $poDate, $poPphdid]);
    }
}

// Filter Form
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');
$filterStatus = trim($_GET['filter_status'] ?? '');

$whereClauses = [];
$queryParams  = [];

if (!empty($dateFrom)) {
    $whereClauses[] = "h.ppdate >= ?";
    $queryParams[]  = $dateFrom;
}
if (!empty($dateTo)) {
    $whereClauses[] = "h.ppdate <= ?";
    $queryParams[]  = $dateTo;
}
if (!empty($filterStatus)) {
    $whereClauses[] = "h.status_pengerjaan = ?";
    $queryParams[]  = $filterStatus;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Hitung data untuk Summary Stat Cards
$countSql = "SELECT 
                COUNT(*) AS total_all,
                SUM(CASE WHEN ISNULL(status_pengerjaan, 'Belum Dikerjakan') = 'Belum Dikerjakan' THEN 1 ELSE 0 END) AS total_belum,
                SUM(CASE WHEN status_pengerjaan = 'Sedang Dikerjakan' THEN 1 ELSE 0 END) AS total_sedang,
                SUM(CASE WHEN status_pengerjaan = 'Selesai' THEN 1 ELSE 0 END) AS total_selesai
             FROM dbo.pr_online_header";
$countStmt = sqlsrv_query($conn, $countSql);
$counts = $countStmt ? sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC) : [];
$statTotalAll     = (int)($counts['total_all'] ?? 0);
$statTotalBelum   = (int)($counts['total_belum'] ?? 0);
$statTotalSedang  = (int)($counts['total_sedang'] ?? 0);
$statTotalSelesai = (int)($counts['total_selesai'] ?? 0);

// Query daftar PR Online tersimpan di SQL Server
$sqlList = "SELECT 
                h.id,
                h.pphdid,
                h.ppnmbr,
                h.ppdate,
                h.ppdesc,
                h.fgstatus,
                h.prorgname,
                h.created_by,
                h.created_at,
                h.status_po,
                ISNULL(h.status_pengerjaan, 'Belum Dikerjakan') AS status_pengerjaan,
                h.pic_name,
                h.pic_username,
                h.assigned_at,
                h.no_po,
                h.po_date,
                COUNT(d.id) AS total_items
            FROM dbo.pr_online_header h
            LEFT JOIN dbo.pr_online_detail d ON h.id = d.header_id
            $whereSql
            GROUP BY 
                h.id,
                h.pphdid,
                h.ppnmbr,
                h.ppdate,
                h.ppdesc,
                h.fgstatus,
                h.prorgname,
                h.created_by,
                h.created_at,
                h.status_po,
                h.status_pengerjaan,
                h.pic_name,
                h.pic_username,
                h.assigned_at,
                h.no_po,
                h.po_date
            ORDER BY h.id DESC";

$stmtList = sqlsrv_query($conn, $sqlList, $queryParams);
$savedPrs = [];
if ($stmtList !== false) {
    while ($r = sqlsrv_fetch_array($stmtList, SQLSRV_FETCH_ASSOC)) {
        $savedPrs[] = $r;
    }
}

// Include Header & Sidebar
include $rootPath . '/includes/header.php';
include $rootPath . '/includes/sidebar.php';

$e = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
?>

<div class="content-wrapper">
    <!-- Header Konten & Breadcrumb -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-file-invoice text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>PR Online
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item">Purchase Request</li>
                        <li class="breadcrumb-item active">PR Online</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Konten Utama -->
    <section class="content">
        <div class="container-fluid">

            <!-- Summary Stat Cards -->
            <div class="row mb-3">
                <div class="col-md-3 col-sm-6 col-12 mb-2">
                    <div class="info-box shadow-sm status-stat-card border" data-status="" style="cursor: pointer;" title="Klik untuk menampilkan semua PR">
                        <span class="info-box-icon bg-secondary text-white elevation-1"><i class="fas fa-list-ul"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text font-weight-bold text-secondary">Total PR Terpilih</span>
                            <span class="info-box-number text-dark" style="font-size: 1.4rem;"><?= $statTotalAll ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12 mb-2">
                    <div class="info-box shadow-sm status-stat-card border" data-status="Belum Dikerjakan" style="cursor: pointer;" title="Klik untuk memfilter status Belum Dikerjakan">
                        <span class="info-box-icon bg-warning text-white elevation-1"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text font-weight-bold text-warning">Belum Dikerjakan</span>
                            <span class="info-box-number text-warning" style="font-size: 1.4rem;"><?= $statTotalBelum ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12 mb-2">
                    <div class="info-box shadow-sm status-stat-card border" data-status="Sedang Dikerjakan" style="cursor: pointer;" title="Klik untuk memfilter status Sedang Dikerjakan">
                        <span class="info-box-icon bg-primary text-white elevation-1"><i class="fas fa-user-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text font-weight-bold text-primary">Sedang Dikerjakan</span>
                            <span class="info-box-number text-primary" style="font-size: 1.4rem;"><?= $statTotalSedang ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 col-12 mb-2">
                    <div class="info-box shadow-sm status-stat-card border" data-status="Selesai" style="cursor: pointer;" title="Klik untuk memfilter status Selesai">
                        <span class="info-box-icon bg-success text-white elevation-1"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text font-weight-bold text-success">Selesai (Ada No PO)</span>
                            <span class="info-box-number text-success" style="font-size: 1.4rem;"><?= $statTotalSelesai ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Utama -->
            <div class="card card-outline card-<?= htmlspecialchars($themeColor) ?> shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h3 class="card-title text-bold mb-1">
                            <i class="fas fa-list-alt mr-1 text-<?= htmlspecialchars($themeColor) ?>"></i> Daftar PR Terpilih (Untuk PO Online)
                        </h3>
                        <div class="card-tools">
                            <?php if ($canAdd): ?>
                                <button type="button" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm shadow-sm" id="btnOpenAddModal">
                                    <i class="fas fa-plus-circle mr-1"></i> Tambah PR Online
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Form Filter Tanggal & Status -->
                    <form method="GET" class="form-inline mb-4 p-3 bg-light rounded border" id="filterForm">
                        <label class="mr-2 font-weight-bold text-secondary" for="date_from">
                            <i class="far fa-calendar-alt mr-1"></i> Dari Tgl:
                        </label>
                        <input type="date" class="form-control form-control-sm mr-3 mb-2" id="date_from" name="date_from" value="<?= $e($dateFrom) ?>">

                        <label class="mr-2 font-weight-bold text-secondary" for="date_to">
                            <i class="far fa-calendar-alt mr-1"></i> Sampai Tgl:
                        </label>
                        <input type="date" class="form-control form-control-sm mr-3 mb-2" id="date_to" name="date_to" value="<?= $e($dateTo) ?>">

                        <label class="mr-2 font-weight-bold text-secondary" for="filter_status">
                            <i class="fas fa-tasks mr-1"></i> Status:
                        </label>
                        <select class="form-control form-control-sm mr-3 mb-2" id="filter_status" name="filter_status">
                            <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="Belum Dikerjakan" <?= $filterStatus === 'Belum Dikerjakan' ? 'selected' : '' ?>>Belum Dikerjakan</option>
                            <option value="Sedang Dikerjakan" <?= $filterStatus === 'Sedang Dikerjakan' ? 'selected' : '' ?>>Sedang Dikerjakan</option>
                            <option value="Selesai" <?= $filterStatus === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>

                        <button type="submit" class="btn btn-info btn-sm mr-2 mb-2 shadow-sm">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                        <a href="pr_online.php" class="btn btn-secondary btn-sm mb-2 shadow-sm">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
                    </form>

                    <!-- Tabel Daftar PR Online Tersimpan -->
                    <div class="table-responsive">
                        <table id="prOnlineTable" class="table table-bordered table-hover table-striped table-sm text-sm">
                            <thead class="thead-light">
                                <tr class="text-center align-middle">
                                    <th style="width: 35px;">No</th>
                                    <th>No PR</th>
                                    <th style="width: 85px;">Tgl PR</th>
                                    <th style="width: 120px;">Status Pengerjaan</th>
                                    <th style="width: 130px;">PIC</th>
                                    <th style="width: 130px;">No PO</th>
                                    <th>PR Organization</th>
                                    <th>Deskripsi</th>
                                    <th style="width: 75px;">Jml Item</th>
                                    <th style="width: 90px;">Diinput Oleh</th>
                                    <th style="width: 110px;">Waktu Input</th>
                                    <th style="width: 145px; min-width: 145px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($savedPrs as $idx => $r): 
                                    $tglPr = !empty($r['ppdate']) 
                                        ? ($r['ppdate'] instanceof DateTime ? $r['ppdate']->format('d/m/Y') : date('d/m/Y', strtotime($r['ppdate']))) 
                                        : '-';
                                    $waktuInput = !empty($r['created_at']) 
                                        ? ($r['created_at'] instanceof DateTime ? $r['created_at']->format('d/m/Y H:i') : date('d/m/Y H:i', strtotime($r['created_at']))) 
                                        : '-';

                                    $statusPengerjaan = trim($r['status_pengerjaan'] ?? 'Belum Dikerjakan');
                                    $picName = trim($r['pic_name'] ?? '');
                                    $picUser = trim($r['pic_username'] ?? '');
                                    $noPo = trim($r['no_po'] ?? '');

                                    // Badge status pengerjaan
                                    if ($statusPengerjaan === 'Selesai' || !empty($noPo)) {
                                        $statusBadge = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Selesai</span>';
                                    } elseif ($statusPengerjaan === 'Sedang Dikerjakan') {
                                        $statusBadge = '<span class="badge badge-primary px-2 py-1"><i class="fas fa-user-clock mr-1"></i>Sedang Dikerjakan</span>';
                                    } else {
                                        $statusBadge = '<span class="badge badge-warning px-2 py-1 text-dark"><i class="fas fa-clock mr-1"></i>Belum Dikerjakan</span>';
                                    }

                                    // Boleh lepas tugas jika user adalah PIC terkait atau Admin
                                    $canUnassign = ($statusPengerjaan === 'Sedang Dikerjakan') && ($groupId == 1 || strcasecmp($picUser, $currentUserName) === 0);
                                ?>
                                    <tr>
                                        <td class="text-center font-weight-bold align-middle"><?= $idx + 1 ?></td>
                                        <td class="font-weight-bold text-primary align-middle"><?= $e($r['ppnmbr']) ?></td>
                                        <td class="text-center align-middle"><?= $tglPr ?></td>
                                        <td class="text-center align-middle"><?= $statusBadge ?></td>
                                        <td class="text-center align-middle">
                                            <?php if (!empty($picName)): ?>
                                                <span class="badge badge-light border text-truncate d-inline-block" style="max-width: 120px;" title="<?= $e($picName) ?>">
                                                    <i class="fas fa-user mr-1 text-secondary"></i><?= $e($picName) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted font-italic">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle font-weight-bold">
                                            <?php if (!empty($noPo)): ?>
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-file-invoice mr-1"></i><?= $e($noPo) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted font-italic">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle"><?= $e($r['prorgname'] ?: '-') ?></td>
                                        <td class="align-middle"><?= nl2br($e($r['ppdesc'] ?: '-')) ?></td>
                                        <td class="text-center align-middle">
                                            <span class="badge badge-info px-2 py-1 font-weight-bold">
                                                <i class="fas fa-boxes mr-1"></i><?= (int)$r['total_items'] ?> Item
                                            </span>
                                        </td>
                                        <td class="text-center align-middle"><span class="badge badge-light border"><?= $e($r['created_by']) ?></span></td>
                                        <td class="text-center text-muted align-middle" style="font-size: 0.85rem;"><?= $waktuInput ?></td>
                                        <td class="text-center align-middle">
                                            <div class="d-inline-flex align-items-center justify-content-center" style="gap: 4px; white-space: nowrap;">
                                                <!-- Tombol Ambil PR (Jika Belum Dikerjakan) -->
                                                <?php if ($statusPengerjaan === 'Belum Dikerjakan' && empty($noPo)): ?>
                                                    <button type="button" class="btn btn-success btn-xs px-2 shadow-sm btn-assign-pr" data-id="<?= (int)$r['id'] ?>" data-pr="<?= $e($r['ppnmbr']) ?>" title="Ambil PR ini (Tugaskan ke Saya)">
                                                        <i class="fas fa-hand-holding mr-1"></i>Ambil
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Tombol Lepas Tugas (Jika Sedang Dikerjakan & Punya Hak) -->
                                                <?php if ($canUnassign): ?>
                                                    <button type="button" class="btn btn-outline-warning btn-xs px-2 shadow-sm text-dark btn-unassign-pr" data-id="<?= (int)$r['id'] ?>" data-pr="<?= $e($r['ppnmbr']) ?>" title="Batal Ambil (Lepas Tugas)">
                                                        <i class="fas fa-undo mr-1"></i>Lepas
                                                    </button>
                                                <?php endif; ?>

                                                <!-- Tombol Lihat Detail -->
                                                <button type="button" class="btn btn-info btn-xs px-2 shadow-sm btn-view-detail" data-id="<?= (int)$r['id'] ?>" data-pr="<?= $e($r['ppnmbr']) ?>" title="Lihat Rincian Item Produk">
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <!-- Tombol Hapus -->
                                                <?php if ($canDelete): ?>
                                                    <button type="button" class="btn btn-danger btn-xs px-2 shadow-sm btn-delete-pr" data-id="<?= (int)$r['id'] ?>" data-pr="<?= $e($r['ppnmbr']) ?>" title="Hapus dari PR Online">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: Tambah Data - Wizard Step 1 & Step 2 -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalAddPr" tabindex="-1" role="dialog" aria-labelledby="modalAddPrLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white py-2">
                <h5 class="modal-title font-weight-bold" id="modalAddPrLabel">
                    <i class="fas fa-plus-circle mr-2"></i>Tambah PR Online (Pilih PR & Produk)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Stepper Progress Bar -->
            <div class="px-3 pt-2 pb-2 bg-light border-bottom">
                <div class="d-flex justify-content-center align-items-center">
                    <div id="stepBadge1" class="font-weight-bold text-primary">
                        <span class="badge badge-primary px-2 py-1 mr-1" style="font-size: 0.85rem;">1</span>
                        <span>Pilih No PR</span>
                    </div>
                    <div class="mx-3 text-muted">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <div id="stepBadge2" class="font-weight-bold text-muted">
                        <span class="badge badge-secondary px-2 py-1 mr-1" style="font-size: 0.85rem;">2</span>
                        <span>Pilih Rincian Produk</span>
                    </div>
                </div>
            </div>

            <div class="modal-body p-3">
                <!-- ================= STEP 1: PILIH NO PR ================= -->
                <div id="step1Container">
                    <div id="loadingAvailablePr" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Memuat data...</span>
                        </div>
                        <div class="text-muted mt-2">Sedang memuat data Purchase Request (Status Approve) dari ERP...</div>
                    </div>

                    <div id="wrapperAvailablePr" class="d-none">
                        <div class="table-responsive">
                            <table id="availablePrTable" class="table table-bordered table-hover table-striped table-sm text-sm" style="width: 100%;">
                                <thead class="thead-light">
                                    <tr class="text-center">
                                        <th style="width: 40px;" class="align-middle">
                                            <input type="checkbox" id="selectAllPr" class="pr-checkbox-header" style="transform: scale(1.2); cursor: pointer;" title="Pilih Semua">
                                        </th>
                                        <th style="width: 40px;" class="align-middle">No</th>
                                        <th class="align-middle">No PR</th>
                                        <th style="width: 95px;" class="align-middle">Tgl PR</th>
                                        <th style="width: 80px;" class="align-middle">Status</th>
                                        <th class="align-middle">PR Organization</th>
                                        <th style="width: 120px;" class="align-middle">Item Tersedia</th>
                                        <th class="align-middle">Deskripsi</th>
                                        <th style="width: 100px;" class="align-middle">Pembuat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Diisi via AJAX DataTables -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================= STEP 2: PILIH RINCIAN PRODUK ================= -->
                <div id="step2Container" style="display: none;">
                    <div id="loadingStep2Products" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Memuat produk...</span>
                        </div>
                        <div class="text-muted mt-2">Sedang memuat rincian produk untuk PR terpilih...</div>
                    </div>

                    <div id="wrapperStep2Products" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                            <div class="text-secondary font-weight-bold text-sm">
                                <i class="fas fa-info-circle mr-1 text-info"></i>
                                Seluruh produk otomatis tercentang. Hilangkan centang (uncheck) pada produk yang <strong>tidak</strong> ingin disimpan.
                            </div>
                            <div>
                                <button type="button" class="btn btn-xs btn-outline-primary" id="btnToggleAllStep2">
                                    <i class="fas fa-check-double mr-1"></i> Pilih/Batalkan Semua
                                </button>
                            </div>
                        </div>

                        <!-- Kontainer Kartu Produk per PR -->
                        <div id="productGroupsContainer">
                            <!-- Diisi via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Dinamis -->
            <div class="modal-footer bg-light d-flex justify-content-between align-items-center py-2">
                <div>
                    <span id="step1PrCountBadge" class="badge badge-secondary px-3 py-2 text-sm">
                        <i class="fas fa-file-invoice mr-1"></i> <span id="selectedPrCount">0</span> PR dipilih
                    </span>
                    <span id="step2ProductCountBadge" class="badge badge-success px-3 py-2 text-sm" style="display: none;">
                        <i class="fas fa-boxes mr-1"></i> <span id="selectedProductCount">0</span> Produk dipilih
                    </span>
                </div>

                <div>
                    <!-- Tombol Step 1 -->
                    <div id="step1Buttons">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary btn-sm font-weight-bold ml-1 shadow-sm" id="btnGoToStep2" disabled>
                            Lanjut ke Pilih Produk <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>

                    <!-- Tombol Step 2 -->
                    <div id="step2Buttons" style="display: none;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBackToStep1">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Pilih PR
                        </button>
                        <button type="button" class="btn btn-success btn-sm font-weight-bold ml-1 shadow-sm" id="btnSaveSelectedProducts">
                            <i class="fas fa-save mr-1"></i> Simpan ke PR Online
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: Lihat Rincian Item Produk PR Online -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalDetailPr" tabindex="-1" role="dialog" aria-labelledby="modalDetailPrLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title font-weight-bold" id="modalDetailPrLabel">
                    <i class="fas fa-box-open mr-2"></i>Rincian Produk - <span id="detailPrNumber" class="text-warning"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Info Header PR -->
                <div class="card card-outline card-info mb-3">
                    <div class="card-body p-3 bg-light">
                        <div class="row text-sm">
                            <div class="col-md-3">
                                <strong><i class="fas fa-hashtag text-info mr-1"></i> No PR:</strong>
                                <div id="infoPrNumber" class="font-weight-bold text-primary">-</div>
                            </div>
                            <div class="col-md-3">
                                <strong><i class="far fa-calendar-alt text-info mr-1"></i> Tgl PR:</strong>
                                <div id="infoPrDate">-</div>
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fas fa-tasks text-info mr-1"></i> Status Pengerjaan:</strong>
                                <div id="infoPrStatusPengerjaan">-</div>
                            </div>
                            <div class="col-md-3">
                                <strong><i class="fas fa-user-tag text-info mr-1"></i> PIC (Yang Mengerjakan):</strong>
                                <div id="infoPrPic">-</div>
                            </div>
                            <div class="col-md-3 mt-2">
                                <strong><i class="fas fa-file-invoice text-success mr-1"></i> No PO (ERP):</strong>
                                <div id="infoPrNoPo" class="font-weight-bold text-success">-</div>
                            </div>
                            <div class="col-md-3 mt-2">
                                <strong><i class="far fa-calendar-check text-success mr-1"></i> Tgl PO:</strong>
                                <div id="infoPrPoDate">-</div>
                            </div>
                            <div class="col-md-3 mt-2">
                                <strong><i class="fas fa-building text-info mr-1"></i> PR Organization:</strong>
                                <div id="infoPrOrg">-</div>
                            </div>
                            <div class="col-md-3 mt-2">
                                <strong><i class="fas fa-user-edit text-info mr-1"></i> Diinput Oleh:</strong>
                                <div id="infoPrUser">-</div>
                            </div>
                            <div class="col-12 mt-2">
                                <strong><i class="fas fa-align-left text-info mr-1"></i> Deskripsi PR:</strong>
                                <div id="infoPrDesc" class="text-dark bg-white p-2 rounded border mt-1">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Item Produk -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold text-secondary mb-0">
                        <i class="fas fa-cubes mr-1"></i> Daftar Barang / Produk:
                    </h6>
                    <span id="detailItemCountBadge" class="badge badge-info px-2 py-1"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped text-sm mb-0">
                        <thead class="thead-light text-center">
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th style="width: 140px;">Kode Produk</th>
                                <th>Nama Produk</th>
                                <th style="width: 80px;">Qty</th>
                                <th style="width: 70px;">Satuan</th>
                                <th>Keterangan / Spesifikasi</th>
                                <?php if ($canDelete): ?>
                                    <th style="width: 50px;">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="detailProductTableBody">
                            <!-- Diisi via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<?php include $rootPath . '/includes/footer.php'; ?>

<!-- Plugins & CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<style>
    .pr-checkbox-item, .pr-checkbox-header, .product-item-checkbox, .pr-group-checkbox {
        cursor: pointer;
    }
    #prOnlineTable td, #availablePrTable td {
        vertical-align: middle;
    }
    .pr-card-header {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .status-stat-card:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    }
</style>

<script>
$(document).ready(function() {
    const canDelete = <?= $canDelete ? 'true' : 'false' ?>;

    // 1. Inisialisasi DataTable Tabel Utama
    const prOnlineTable = $('#prOnlineTable').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ PR Online",
            infoEmpty: "Tidak ada data yang tersedia",
            infoFiltered: "(difilter dari _MAX_ total data)",
            zeroRecords: "Data tidak ditemukan",
            paginate: {
                first: "Awal",
                last: "Akhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        },
        columnDefs: [
            { targets: [0, 11], orderable: false }
        ]
    });

    // Klik Summary Card untuk filter cepat
    $('.status-stat-card').on('click', function() {
        const targetStatus = $(this).data('status');
        $('#filter_status').val(targetStatus);
        $('#filterForm').submit();
    });

    // 2. Tombol Ambil PR (Assign PIC)
    $('#prOnlineTable').on('click', '.btn-assign-pr', function() {
        const id = $(this).data('id');
        const prNumber = $(this).data('pr');

        Swal.fire({
            title: 'Ambil PR ini?',
            html: `Apakah Anda ingin mengambil tugas untuk PR <b>${prNumber}</b>?<br><small class="text-muted">Nama Anda akan tercatat sebagai PIC pengerjaan.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-hand-holding mr-1"></i> Ya, Ambil!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'pr_online_serverside.php',
                    type: 'POST',
                    data: {
                        action: 'assign_pr',
                        id: id
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil Diambil!',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message || 'Gagal mengambil PR.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    });

    // 4. Tombol Batal Ambil PR (Lepas Tugas)
    $('#prOnlineTable').on('click', '.btn-unassign-pr', function() {
        const id = $(this).data('id');
        const prNumber = $(this).data('pr');

        Swal.fire({
            title: 'Lepas Penugasan PR?',
            html: `Apakah Anda ingin membatalkan penugasan untuk PR <b>${prNumber}</b>?<br><small class="text-muted">Status akan kembali menjadi 'Belum Dikerjakan' dan dapat diambil oleh PIC lain.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-undo mr-1"></i> Ya, Lepas!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'pr_online_serverside.php',
                    type: 'POST',
                    data: {
                        action: 'unassign_pr',
                        id: id
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Penugasan Dilepas',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message || 'Gagal membatalkan penugasan.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                    }
                });
            }
        });
    });

    // 5. Variabel State Wizard Modal Tambah PR
    let availablePrTable = null;
    let selectedPrNumbers = new Set();
    let currentStep = 1;

    function updateStep1Badge() {
        const count = selectedPrNumbers.size;
        $('#selectedPrCount').text(count);
        if (count > 0) {
            $('#btnGoToStep2').prop('disabled', false);
            $('#step1PrCountBadge').removeClass('badge-secondary').addClass('badge-primary');
        } else {
            $('#btnGoToStep2').prop('disabled', true);
            $('#step1PrCountBadge').removeClass('badge-primary').addClass('badge-secondary');
        }
    }

    function updateStep2Badge() {
        const checkedCount = $('.product-item-checkbox:checked').length;
        $('#selectedProductCount').text(checkedCount);
        if (checkedCount > 0) {
            $('#btnSaveSelectedProducts').prop('disabled', false);
            $('#step2ProductCountBadge').removeClass('badge-secondary').addClass('badge-success');
        } else {
            $('#btnSaveSelectedProducts').prop('disabled', true);
            $('#step2ProductCountBadge').removeClass('badge-success').addClass('badge-secondary');
        }
    }

    function showStep(step) {
        currentStep = step;
        if (step === 1) {
            $('#stepBadge1').removeClass('text-muted').addClass('text-primary');
            $('#stepBadge1 .badge').removeClass('badge-secondary').addClass('badge-primary');
            $('#stepBadge2').removeClass('text-primary').addClass('text-muted');
            $('#stepBadge2 .badge').removeClass('badge-primary').addClass('badge-secondary');

            $('#step1Container').show();
            $('#step2Container').hide();
            $('#step1Buttons').show();
            $('#step2Buttons').hide();
            $('#step1PrCountBadge').show();
            $('#step2ProductCountBadge').hide();
            updateStep1Badge();
        } else if (step === 2) {
            $('#stepBadge1').removeClass('text-primary').addClass('text-muted');
            $('#stepBadge1 .badge').removeClass('badge-primary').addClass('badge-secondary');
            $('#stepBadge2').removeClass('text-muted').addClass('text-primary');
            $('#stepBadge2 .badge').removeClass('badge-secondary').addClass('badge-primary');

            $('#step1Container').hide();
            $('#step2Container').show();
            $('#step1Buttons').hide();
            $('#step2Buttons').show();
            $('#step1PrCountBadge').hide();
            $('#step2ProductCountBadge').show();
        }
    }

    // Buka Modal Tambah Data
    $('#btnOpenAddModal').on('click', function() {
        $('#modalAddPr').modal('show');
        selectedPrNumbers.clear();
        $('#selectAllPr').prop('checked', false);
        showStep(1);

        $('#loadingAvailablePr').removeClass('d-none');
        $('#wrapperAvailablePr').addClass('d-none');

        $.ajax({
            url: 'pr_online_serverside.php',
            type: 'GET',
            data: { action: 'get_available_pr' },
            dataType: 'json',
            success: function(res) {
                $('#loadingAvailablePr').addClass('d-none');
                $('#wrapperAvailablePr').removeClass('d-none');

                if (res.status === 'ok') {
                    if ($.fn.DataTable.isDataTable('#availablePrTable')) {
                        $('#availablePrTable').DataTable().destroy();
                    }

                    availablePrTable = $('#availablePrTable').DataTable({
                        data: res.data,
                        responsive: true,
                        autoWidth: false,
                        pageLength: 10,
                        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
                        columns: [
                            {
                                data: null,
                                orderable: false,
                                className: 'text-center',
                                render: function(data, type, row) {
                                    const checked = selectedPrNumbers.has(row.ppnmbr) ? 'checked' : '';
                                    return `<input type="checkbox" class="pr-checkbox-item" data-ppnmbr="${row.ppnmbr}" style="transform: scale(1.2); cursor: pointer;" ${checked}>`;
                                }
                            },
                            { data: 'no', className: 'text-center' },
                            { 
                                data: 'ppnmbr', 
                                className: 'font-weight-bold text-primary' 
                            },
                            { data: 'ppdate', className: 'text-center' },
                            { data: 'status_badge', className: 'text-center' },
                            { data: 'prorgname' },
                            { data: 'items_badge', className: 'text-center' },
                            { data: 'ppdesc' },
                            { data: 'createdby', className: 'text-center' }
                        ],
                        language: {
                            search: "Cari PR:",
                            lengthMenu: "Tampilkan _MENU_ data",
                            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ PR",
                            infoEmpty: "Tidak ada PR ERP yang tersedia",
                            infoFiltered: "(difilter dari _MAX_ data)",
                            zeroRecords: "Tidak ada PR yang cocok",
                            paginate: {
                                next: "Next",
                                previous: "Prev"
                            }
                        }
                    });

                    $('#availablePrTable tbody').off('change', '.pr-checkbox-item').on('change', '.pr-checkbox-item', function() {
                        const ppnmbr = $(this).data('ppnmbr');
                        if ($(this).is(':checked')) {
                            selectedPrNumbers.add(ppnmbr);
                        } else {
                            selectedPrNumbers.delete(ppnmbr);
                            $('#selectAllPr').prop('checked', false);
                        }
                        updateStep1Badge();
                    });

                } else {
                    Swal.fire('Gagal Memuat Data', res.message || 'Terjadi kesalahan.', 'error');
                }
            },
            error: function() {
                $('#loadingAvailablePr').addClass('d-none');
                Swal.fire('Error Jaringan', 'Tidak dapat terhubung ke server.', 'error');
            }
        });
    });

    // Checkbox "Pilih Semua" pada Step 1
    $('#selectAllPr').on('change', function() {
        const isChecked = $(this).is(':checked');
        if (availablePrTable) {
            availablePrTable.rows({ search: 'applied' }).every(function() {
                const data = this.data();
                if (isChecked) {
                    selectedPrNumbers.add(data.ppnmbr);
                } else {
                    selectedPrNumbers.delete(data.ppnmbr);
                }
            });
            $('.pr-checkbox-item').prop('checked', isChecked);
            updateStep1Badge();
        }
    });

    // Navigasi: Lanjut ke Step 2
    $('#btnGoToStep2').on('click', function() {
        const listPr = Array.from(selectedPrNumbers);
        if (listPr.length === 0) return;

        showStep(2);
        $('#loadingStep2Products').removeClass('d-none');
        $('#wrapperStep2Products').addClass('d-none');
        $('#productGroupsContainer').empty();

        $.ajax({
            url: 'pr_online_serverside.php',
            type: 'POST',
            data: {
                action: 'get_pr_products',
                ppnmbrs: listPr
            },
            dataType: 'json',
            success: function(res) {
                $('#loadingStep2Products').addClass('d-none');
                $('#wrapperStep2Products').removeClass('d-none');

                if (res.status === 'ok' && res.data && res.data.length > 0) {
                    renderStep2Cards(res.data);
                    updateStep2Badge();
                } else {
                    $('#productGroupsContainer').html(`
                        <div class="alert alert-warning text-center py-4">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Tidak ada produk yang tersedia untuk disimpan pada PR terpilih.
                        </div>
                    `);
                    $('#btnSaveSelectedProducts').prop('disabled', true);
                }
            },
            error: function() {
                $('#loadingStep2Products').addClass('d-none');
                Swal.fire('Error Jaringan', 'Gagal mengambil rincian produk dari server.', 'error');
            }
        });
    });

    function renderStep2Cards(groups) {
        let html = '';
        groups.forEach(function(grp) {
            const cardId = `prGroupCard_${grp.pphdid}`;
            const checkAllId = `checkPrGroup_${grp.pphdid}`;

            html += `
                <div class="card card-outline card-secondary mb-3 shadow-sm" id="${cardId}">
                    <div class="card-header pr-card-header py-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="d-flex align-items-center">
                                <input type="checkbox" id="${checkAllId}" class="pr-group-checkbox mr-2" data-pphdid="${grp.pphdid}" style="transform: scale(1.2);" checked title="Pilih Semua Produk PR Ini">
                                <label for="${checkAllId}" class="mb-0 font-weight-bold text-primary" style="cursor: pointer;">
                                    <i class="fas fa-file-invoice mr-1"></i> ${grp.ppnmbr}
                                </label>
                                <span class="badge badge-light border ml-2">${grp.prorgname}</span>
                                <span class="text-muted ml-2 text-sm"><i class="far fa-calendar-alt mr-1"></i>${grp.ppdate}</span>
                            </div>
                            <div>
                                <span class="badge badge-info group-selected-count-badge font-weight-bold" id="badgeGroupCount_${grp.pphdid}">
                                    ${grp.items.length} / ${grp.items.length} Produk
                                </span>
                            </div>
                        </div>
                        <div class="text-muted text-xs mt-1 ml-4 text-truncate" title="${grp.ppdesc}">
                            ${grp.ppdesc}
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover table-striped mb-0 text-sm">
                                <thead class="thead-light text-center">
                                    <tr>
                                        <th style="width: 40px;">Pilih</th>
                                        <th style="width: 40px;">No</th>
                                        <th style="width: 140px;">Kode Produk</th>
                                        <th>Nama Produk</th>
                                        <th style="width: 80px;">Qty</th>
                                        <th style="width: 70px;">Satuan</th>
                                        <th>Keterangan / Spesifikasi</th>
                                    </tr>
                                </thead>
                                <tbody>
            `;

            grp.items.forEach(function(item) {
                html += `
                    <tr>
                        <td class="text-center align-middle">
                            <input type="checkbox" class="product-item-checkbox pr-item-${grp.pphdid}" 
                                   data-ppnmbr="${grp.ppnmbr}" 
                                   data-ppdtid="${item.ppdtid}" 
                                   data-pphdid="${grp.pphdid}" 
                                   style="transform: scale(1.2);" checked>
                        </td>
                        <td class="text-center align-middle font-weight-bold">${item.no}</td>
                        <td class="align-middle font-weight-bold text-monospace">${item.ppprodcode}</td>
                        <td class="align-middle">${item.ppprodname}</td>
                        <td class="text-right align-middle font-weight-bold">${item.ppqty}</td>
                        <td class="text-center align-middle"><span class="badge badge-light border">${item.uomname}</span></td>
                        <td class="align-middle">${item.ppproddesc}</td>
                    </tr>
                `;
            });

            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#productGroupsContainer').html(html);

        $('.product-item-checkbox').on('change', function() {
            const pphdid = $(this).data('pphdid');
            const totalInGroup = $(`.pr-item-${pphdid}`).length;
            const checkedInGroup = $(`.pr-item-${pphdid}:checked`).length;

            $(`#checkPrGroup_${pphdid}`).prop('checked', checkedInGroup === totalInGroup);
            $(`#badgeGroupCount_${pphdid}`).text(`${checkedInGroup} / ${totalInGroup} Produk`);
            updateStep2Badge();
        });

        $('.pr-group-checkbox').on('change', function() {
            const pphdid = $(this).data('pphdid');
            const isChecked = $(this).is(':checked');
            $(`.pr-item-${pphdid}`).prop('checked', isChecked);
            const totalInGroup = $(`.pr-item-${pphdid}`).length;
            const checkedInGroup = isChecked ? totalInGroup : 0;
            $(`#badgeGroupCount_${pphdid}`).text(`${checkedInGroup} / ${totalInGroup} Produk`);
            updateStep2Badge();
        });
    }

    // Toggle All produk pada Step 2
    $('#btnToggleAllStep2').on('click', function() {
        const total = $('.product-item-checkbox').length;
        const checked = $('.product-item-checkbox:checked').length;
        const targetState = (checked < total);

        $('.product-item-checkbox').prop('checked', targetState);
        $('.pr-group-checkbox').prop('checked', targetState);

        $('.pr-group-checkbox').each(function() {
            const pphdid = $(this).data('pphdid');
            const totalInGroup = $(`.pr-item-${pphdid}`).length;
            const checkedInGroup = targetState ? totalInGroup : 0;
            $(`#badgeGroupCount_${pphdid}`).text(`${checkedInGroup} / ${totalInGroup} Produk`);
        });

        updateStep2Badge();
    });

    $('#btnBackToStep1').on('click', function() {
        showStep(1);
    });

    // Simpan Produk Terpilih ke SQL Server
    $('#btnSaveSelectedProducts').on('click', function() {
        const selectedItems = [];
        $('.product-item-checkbox:checked').each(function() {
            selectedItems.push({
                ppnmbr: $(this).data('ppnmbr'),
                ppdtid: $(this).data('ppdtid')
            });
        });

        if (selectedItems.length === 0) {
            Swal.fire('Peringatan', 'Silakan centang minimal satu produk untuk disimpan.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Simpan ke PR Online?',
            html: `Anda akan menyimpan <b>${selectedItems.length}</b> produk ke daftar PR Online.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Ya, Simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const btn = $('#btnSaveSelectedProducts');
                const origHtml = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                $.ajax({
                    url: 'pr_online_serverside.php',
                    type: 'POST',
                    data: {
                        action: 'save_selected_products',
                        selected_items: selectedItems
                    },
                    dataType: 'json',
                    success: function(res) {
                        btn.prop('disabled', false).html(origHtml);
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                $('#modalAddPr').modal('hide');
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal Menyimpan', res.message || 'Terjadi kesalahan.', 'error');
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).html(origHtml);
                        Swal.fire('Error Sistem', 'Gagal menghubungi server.', 'error');
                    }
                });
            }
        });
    });

    // 6. Tombol Lihat Detail Produk
    let currentDetailHeaderId = null;
    $('#prOnlineTable').on('click', '.btn-view-detail', function() {
        const headerId = $(this).data('id');
        const prNumber = $(this).data('pr');
        currentDetailHeaderId = headerId;

        $('#detailPrNumber').text(prNumber);
        $('#infoPrNumber').text(prNumber);
        $('#infoPrDate').text('Memuat...');
        $('#infoPrStatusPengerjaan').html('<i class="fas fa-spinner fa-spin mr-1"></i> Memuat...');
        $('#infoPrPic').text('Memuat...');
        $('#infoPrNoPo').text('Memuat...');
        $('#infoPrPoDate').text('-');
        $('#infoPrOrg').text('Memuat...');
        $('#infoPrUser').text('Memuat...');
        $('#infoPrDesc').text('Memuat...');
        $('#detailItemCountBadge').text('');
        $('#detailProductTableBody').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat detail produk...</td></tr>');

        $('#modalDetailPr').modal('show');
        loadPrDetail(headerId);
    });

    function loadPrDetail(headerId) {
        $.ajax({
            url: 'pr_online_serverside.php',
            type: 'GET',
            data: {
                action: 'get_pr_detail',
                header_id: headerId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    const h = res.header;
                    $('#infoPrDate').text(h.ppdate_formatted || '-');
                    $('#infoPrOrg').text(h.prorgname || '-');
                    $('#infoPrUser').text(h.created_by || '-');
                    $('#infoPrDesc').html(h.ppdesc ? h.ppdesc.replace(/\n/g, '<br>') : '-');
                    $('#detailItemCountBadge').text(`${res.total_items} Produk`);

                    // Status Badge
                    let stHtml = '';
                    if (h.status_pengerjaan === 'Selesai' || (h.no_po && h.no_po.trim() !== '')) {
                        stHtml = '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Selesai</span>';
                    } else if (h.status_pengerjaan === 'Sedang Dikerjakan') {
                        stHtml = '<span class="badge badge-primary px-2 py-1"><i class="fas fa-user-clock mr-1"></i>Sedang Dikerjakan</span>';
                    } else {
                        stHtml = '<span class="badge badge-warning px-2 py-1 text-dark"><i class="fas fa-clock mr-1"></i>Belum Dikerjakan</span>';
                    }
                    $('#infoPrStatusPengerjaan').html(stHtml);

                    // PIC
                    $('#infoPrPic').text(h.pic_name ? h.pic_name + (h.assigned_at_formatted ? ` (${h.assigned_at_formatted})` : '') : '-');

                    // No PO & Tgl PO
                    $('#infoPrNoPo').text(h.no_po || '-');
                    $('#infoPrPoDate').text(h.po_date_formatted || '-');

                    let rowsHtml = '';
                    if (res.details && res.details.length > 0) {
                        res.details.forEach(function(item) {
                            rowsHtml += `
                                <tr id="rowDetailItem_${item.id}">
                                    <td class="text-center font-weight-bold">${item.no}</td>
                                    <td class="font-weight-bold text-monospace">${item.ppprodcode}</td>
                                    <td>${item.ppprodname}</td>
                                    <td class="text-right font-weight-bold">${item.ppqty}</td>
                                    <td class="text-center"><span class="badge badge-light border">${item.uomname}</span></td>
                                    <td>${item.ppproddesc}</td>
                                    ${canDelete ? `
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-xs btn-delete-detail-item" data-id="${item.id}" data-name="${item.ppprodname}" title="Hapus Produk Ini">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    ` : ''}
                                </tr>
                            `;
                        });
                    } else {
                        rowsHtml = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada detail item produk yang tersimpan.</td></tr>';
                    }
                    $('#detailProductTableBody').html(rowsHtml);
                } else {
                    $('#detailProductTableBody').html(`<tr><td colspan="7" class="text-center text-danger py-3">${res.message || 'Gagal memuat detail.'}</td></tr>`);
                }
            },
            error: function() {
                $('#detailProductTableBody').html('<tr><td colspan="7" class="text-center text-danger py-3">Gagal mengambil data dari server.</td></tr>');
            }
        });
    }

    // 7. Hapus Satu Item Produk dari Modal Detail
    $('#detailProductTableBody').on('click', '.btn-delete-detail-item', function() {
        const detailId = $(this).data('id');
        const prodName = $(this).data('name');

        Swal.fire({
            title: 'Hapus Item Produk?',
            html: `Apakah Anda yakin ingin menghapus produk <b>${prodName}</b> dari PR Online?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'pr_online_serverside.php',
                    type: 'POST',
                    data: {
                        action: 'delete_single_item',
                        detail_id: detailId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus!',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                if (res.header_deleted) {
                                    $('#modalDetailPr').modal('hide');
                                    location.reload();
                                } else {
                                    loadPrDetail(currentDetailHeaderId);
                                }
                            });
                        } else {
                            Swal.fire('Gagal Menghapus', res.message || 'Terjadi kesalahan.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error Sistem', 'Tidak dapat menghubungi server.', 'error');
                    }
                });
            }
        });
    });

    // 8. Hapus Seluruh PR Online
    $('#prOnlineTable').on('click', '.btn-delete-pr', function() {
        const id = $(this).data('id');
        const prNumber = $(this).data('pr');

        Swal.fire({
            title: 'Hapus PR Online?',
            html: `Apakah Anda yakin ingin menghapus <b>${prNumber}</b> beserta seluruh detail produknya dari daftar PR Online?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'pr_online_serverside.php',
                    type: 'POST',
                    data: {
                        action: 'delete_pr',
                        id: id
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus!',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal Menghapus', res.message || 'Terjadi kesalahan.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error Sistem', 'Tidak dapat menghubungi server.', 'error');
                    }
                });
            }
        });
    });
});
</script>

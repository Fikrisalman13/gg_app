<?php
session_start();
require_once '../koneksi.php';

if (!isset($_SESSION['UserId'])) {
    header('Location: ../login.php');
    exit;
}

$userIdParam = isset($_GET['userid']) ? (int)$_GET['userid'] : 0;
if ($userIdParam <= 0) {
    header('Location: usermanager.php');
    exit;
}

// 1. Fetch User Profile
$sqlUser = "
    SELECT 
        u.UserId, u.UserName, u.EmpId,
        e.nama_lengkap, e.nik, e.foto_path, e.id_emp, e.tgl_masuk,
        j.jabatan, d.dept, b.bagian,
        g.GroupName
    FROM dbo.SMUserMs u
    LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
    LEFT JOIN dbo.m_jab j ON e.id_jab = j.id_jab
    LEFT JOIN dbo.m_dept d ON e.id_dept = d.id_dept
    LEFT JOIN dbo.m_bag b ON e.id_bag = b.id_bag
    LEFT JOIN dbo.SMUserGroup g ON u.GroupId = g.GroupId
    WHERE u.UserId = ?
";
$stmtUser = sqlsrv_query($conn, $sqlUser, [$userIdParam]);
$user = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

$empId = $user['EmpId'];
$idEmp = $user['id_emp'];
$nik = $user['nik'];
$viewedUserName = $user['UserName'];  // Store separately to avoid being overwritten by header.php
$fullName = $user['nama_lengkap'] ?? 'N/A';
$fotoPath = $user['foto_path'];
$tglMasuk = $user['tgl_masuk'];

// Tenure Calculation Helper
function calculateTenure($joinDate) {
    if (!$joinDate) return "N/A";
    $today = new DateTime();
    $start = $joinDate instanceof DateTime ? $joinDate : new DateTime($joinDate);
    
    // SQL Server often uses 1900-01-01 as a default/empty date.
    // If date is 1900-01-01 or generally before 1970, we treat it as invalid/empty data.
    if ($start->format('Y-m-d') === '1900-01-01' || $start->format('Y') < 1920) {
        return "Belum Update";
    }
    
    // If start date is in the future, something is wrong
    if ($start > $today) return "Belum Mulai";

    $diff = $today->diff($start);
    
    $parts = [];
    if ($diff->y > 0) $parts[] = $diff->y . " Th";
    if ($diff->m > 0) $parts[] = $diff->m . " Bln";
    if ($diff->y == 0 && $diff->m == 0) $parts[] = $diff->d . " Hari";
    
    return empty($parts) ? "Baru Bergabung" : implode(" ", $parts);
}
$tenure = calculateTenure($tglMasuk);

// 2. Fetch Stats
// Total Tickets (Creator OR Technician)
$sqlTicketCount = "SELECT COUNT(*) as cnt FROM dbo.tickets WHERE creator_id = ? OR assigned_to = ?";
$stmtTicketCount = sqlsrv_query($conn, $sqlTicketCount, [$userIdParam, $empId]);
$ticketCount = ($stmtTicketCount && $row = sqlsrv_fetch_array($stmtTicketCount, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;

// Total Assets
$assetCount = 0;
if ($empId) {
    $sqlAssetCount = "SELECT COUNT(*) as cnt FROM dbo.m_asset WHERE id_emp = ?";
    $stmtAssetCount = sqlsrv_query($conn, $sqlAssetCount, [$empId]);
    $assetCount = ($stmtAssetCount && $row = sqlsrv_fetch_array($stmtAssetCount, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;
}

// Total Issues & Activities (Client OR Technician)
$issueCount = 0;
$sqlIssueCount = "SELECT COUNT(*) as cnt FROM dbo.issues WHERE client_id = ? OR LTRIM(RTRIM(created_by)) = LTRIM(RTRIM(?))";
$stmtIssueCount = sqlsrv_query($conn, $sqlIssueCount, [$idEmp, $fullName]);
$issueCount = ($stmtIssueCount && $row = sqlsrv_fetch_array($stmtIssueCount, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;

// Total Form IT (Active)
$sqlFormITCount = "
    SELECT COUNT(*) as cnt FROM (
        SELECT nama_pemohon FROM Form_Pengajuan_Barang WHERE status_ticket NOT IN ('Approved', 'Ditolak', 'Rejected')
        UNION ALL
        SELECT nama_pemohon FROM Form_Pengajuan_CCTV WHERE status_ticket NOT IN ('Approved', 'Ditolak', 'Rejected')
        UNION ALL
        SELECT nama_pemohon FROM Form_Pengajuan_Akses_Internet WHERE status_ticket NOT IN ('Approved', 'Ditolak', 'Rejected')
    ) x WHERE x.nama_pemohon = ?
";
$stmtFormITCount = sqlsrv_query($conn, $sqlFormITCount, [$fullName]);
$formITCount = ($stmtFormITCount && $row = sqlsrv_fetch_array($stmtFormITCount, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;

// Pending E-Sign
$pendingSignCount = 0;
if ($nik) {
    $sqlSignCount = "SELECT COUNT(*) as cnt FROM dbo.kontrak_kerja WHERE nik = ? AND status_tanda_tangan = '0'";
    $stmtSignCount = sqlsrv_query($conn, $sqlSignCount, [$nik]);
    $pendingSignCount = ($stmtSignCount && $row = sqlsrv_fetch_array($stmtSignCount, SQLSRV_FETCH_ASSOC)) ? $row['cnt'] : 0;
}

// 3. Fetch Recent Tables
$recentTickets = [];
$sqlRecentTickets = "SELECT TOP 5 t.ticket_id, t.ticket_no, t.subject, ts.status_name, t.created_at, t.creator_id, t.assigned_to 
                    FROM dbo.tickets t 
                    LEFT JOIN dbo.ticket_statuses ts ON t.status_id = ts.status_id
                    WHERE t.creator_id = ? OR t.assigned_to = ? 
                    ORDER BY t.created_at DESC";
$stmtRT = sqlsrv_query($conn, $sqlRecentTickets, [$userIdParam, $empId]);
while ($stmtRT && $row = sqlsrv_fetch_array($stmtRT, SQLSRV_FETCH_ASSOC)) {
    $recentTickets[] = $row;
}

$userAssets = [];
if ($empId) {
    $sqlUserAssets = "
        SELECT a.id_asset, a.kode_asset_seq, m.nama_merk, t.nama_tipe, l.nama_lokasi, a.keterangan
        FROM dbo.m_asset a
        LEFT JOIN dbo.m_merk m ON a.id_merk = m.id_merk
        LEFT JOIN dbo.m_tipe t ON a.id_tipe = t.id_tipe
        LEFT JOIN dbo.m_lokasi l ON a.id_lokasi = l.id_lokasi
        WHERE a.id_emp = ?
    ";
    $stmtUA = sqlsrv_query($conn, $sqlUserAssets, [$empId]);
    while ($stmtUA && $row = sqlsrv_fetch_array($stmtUA, SQLSRV_FETCH_ASSOC)) {
        $userAssets[] = $row;
    }
}

$recentIssues = [];
// Combined Client and Technician issues (TOP 5)
$sqlRecentIssues = "SELECT TOP 5 issue_id, issue_name, status, created_at, created_by, client_id 
                    FROM dbo.issues 
                    WHERE client_id = ? OR LTRIM(RTRIM(created_by)) = LTRIM(RTRIM(?))
                    ORDER BY created_at DESC";
$stmtRI = sqlsrv_query($conn, $sqlRecentIssues, [$idEmp, $fullName]);
while ($stmtRI && $row = sqlsrv_fetch_array($stmtRI, SQLSRV_FETCH_ASSOC)) {
    $recentIssues[] = $row;
}



$themeColor = $_SESSION['Theme'] ?? 'primary';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>User Dashboard: <?= htmlspecialchars($fullName) ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="usermanager.php" class="btn btn-secondary shadow-sm">
                        <i class="fas fa-arrow-left"></i> Back to User Manager
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Profile Info -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-<?= $themeColor ?> shadow-sm">
                        <div class="card-body box-profile">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h3 class="profile-username font-weight-bold mb-1"><?= htmlspecialchars($fullName) ?></h3>
                                    <p class="text-muted mb-3"><i class="fas fa-id-badge mr-1"></i> <?= htmlspecialchars($user['jabatan'] ?? 'Position N/A') ?></p>
                                    
                                    <div class="row mt-2">
                                        <div class="col-5">
                                            <small class="text-muted d-block">Username</small>
                                            <span class="font-weight-500"><?= htmlspecialchars($viewedUserName) ?></span>
                                        </div>
                                        <div class="col-7">
                                            <small class="text-muted d-block">Total Waktu Bekerja</small>
                                            <span class="font-weight-500 text-primary"><i class="fas fa-history mr-1"></i> <?= $tenure ?></span>
                                        </div>
                                    </div>
                                    <div class="row mt-2 border-top pt-2">
                                        <div class="col-5">
                                            <small class="text-muted d-block">UserId</small>
                                            <span class="badge badge-secondary"><?= $userIdParam ?></span>
                                        </div>
                                        <div class="col-7">
                                            <small class="text-muted d-block">EmpId</small>
                                            <span class="badge badge-info"><?= htmlspecialchars($idEmp ?? 'N/A') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 border-left pl-4">
                                    <ul class="list-group list-group-unbordered mb-0 mt-4">
                                        <li class="list-group-item border-top-0 pt-0">
                                            <b>Department</b> <a class="float-right text-dark"><?= htmlspecialchars($user['dept'] ?? 'N/A') ?></a>
                                        </li>
                                        <li class="list-group-item">
                                            <b>Bagian</b> <a class="float-right text-dark"><?= htmlspecialchars($user['bagian'] ?? 'N/A') ?></a>
                                        </li>
                                        <li class="list-group-item border-bottom-0">
                                            <b>User Group</b> <a class="float-right text-dark"><?= htmlspecialchars($user['GroupName'] ?? 'N/A') ?></a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat Boxes -->
            <div class="row">
                <div class="col-lg-2 col-6">
                    <a href="ticket/list.php" class="small-box-footer-link" style="color: inherit; text-decoration: none;">
                        <div class="small-box bg-info shadow-sm clickable-box">
                            <div class="inner">
                                <h3><?= $ticketCount ?></h3>
                                <p>Tickets</p>
                            </div>
                            <div class="icon"><i class="fas fa-ticket-alt"></i></div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-2 col-6">
                    <a href="asset/asset.php" class="small-box-footer-link" style="color: inherit; text-decoration: none;">
                        <div class="small-box bg-success shadow-sm clickable-box">
                            <div class="inner">
                                <h3><?= $assetCount ?></h3>
                                <p>Assets Assigned</p>
                            </div>
                            <div class="icon"><i class="fas fa-laptop"></i></div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-6">
                    <a href="issues/list_issues.php" class="small-box-footer-link" style="color: inherit; text-decoration: none;">
                        <div class="small-box bg-warning shadow-sm clickable-box">
                            <div class="inner">
                                <h3><?= $issueCount ?></h3>
                                <p>Issues</p>
                            </div>
                            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-2 col-6">
                    <a href="form_it/list_form.php" class="small-box-footer-link" style="color: inherit; text-decoration: none;">
                        <div class="small-box bg-navy shadow-sm clickable-box">
                            <div class="inner">
                                <h3><?= $formITCount ?></h3>
                                <p>IT Forms Request</p>
                            </div>
                            <div class="icon"><i class="fas fa-edit"></i></div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-6">
                    <a href="esign/data_kontrak_belumttd.php" class="small-box-footer-link" style="color: inherit; text-decoration: none;">
                        <div class="small-box bg-danger shadow-sm clickable-box">
                            <div class="inner">
                                <h3><?= $pendingSignCount ?></h3>
                                <p>Pending E-Sign Docs</p>
                            </div>
                            <div class="icon"><i class="fas fa-signature"></i></div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Main Row -->
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Recent Tickets -->
                    <div class="card card-<?= $themeColor ?> shadow-sm">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-ticket-alt mr-1"></i> Recent Tickets</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div style="max-height: 350px; overflow-y: auto;">
                                <table class="table table-hover m-0 table-head-fixed">
                                    <thead>
                                    <tr>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Ticket No</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Subject</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Role</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Status</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Date</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($recentTickets)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No tickets found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentTickets as $t): ?>
                                            <tr>
                                                <td><a href="ticket/detail.php?id=<?= $t['ticket_id'] ?>" class="text-bold"><?= htmlspecialchars($t['ticket_no']) ?></a></td>
                                                <td><?= htmlspecialchars($t['subject']) ?></td>
                                                <td>
                                                    <?php if ($t['assigned_to'] == $empId && $empId > 0): ?>
                                                        <span class="badge badge-secondary shadow-sm">Technician</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light border shadow-sm">Requester</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-info shadow-sm"><?= htmlspecialchars($t['status_name'] ?? 'N/A') ?></span></td>
                                                <td><?= $t['created_at'] ? $t['created_at']->format('d-m-Y') : '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-center">
                            <a href="ticket/list.php" class="uppercase">View All Tickets</a>
                        </div>
                    </div>

                    <!-- Issues & Activities -->
                    <div class="card card-<?= $themeColor ?> shadow-sm">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> Recent Issues & Activities</h3>
                        </div>
                        <div class="card-body p-0">
                            <div style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover m-0 table-head-fixed">
                                    <thead>
                                    <tr>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Issue Name</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Role</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Status</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Date</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($recentIssues)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No issues found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentIssues as $i): ?>
                                            <tr>
                                                <td class="text-bold"><?= htmlspecialchars($i['issue_name']) ?></td>
                                                <td>
                                                    <?php if (trim($i['created_by']) == trim($fullName)): ?>
                                                        <span class="badge badge-secondary shadow-sm">Technician</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light border shadow-sm">Client</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-primary shadow-sm"><?= htmlspecialchars($i['status'] ?? 'N/A') ?></span></td>
                                                <td><?= $i['created_at'] ? $i['created_at']->format('d-m-Y') : '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Assigned Assets -->
                    <div class="card card-<?= $themeColor ?> shadow-sm">
                        <div class="card-header bg-<?= $themeColor ?> text-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-laptop mr-1"></i> Assigned Assets</h3>
                        </div>
                        <div class="card-body p-0">
                            <div style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-valign-middle m-0 table-head-fixed">
                                    <thead>
                                    <tr>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Asset Code</th>
                                        <th style="position: sticky; top: 0; background: #fff; z-index: 10; border-bottom: 1px solid #dee2e6;">Merk/Tipe</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($userAssets)): ?>
                                        <tr><td colspan="2" class="text-center text-muted py-3">No assets assigned</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($userAssets as $a): ?>
                                            <tr>
                                                <td class="text-bold">
                                                    <a href="asset/view_asset.php?id=<?= (int)$a['id_asset'] ?>" class="text-success hover-link">
                                                        <?= htmlspecialchars($a['kode_asset_seq']) ?>
                                                    </a>
                                                </td>
                                                <td><small><?= htmlspecialchars($a['nama_merk'] . ' ' . $a['nama_tipe']) ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

        </div>
    </section>
</div>

<?php 
// Add some CSS for better aesthetics
echo '<style>
    .font-weight-500 { font-weight: 500; }
    .profile-user-img { border: 3px solid #adb5bd; padding: 3px; }
    .small-box .icon { top: 10px; }
    .card-header .card-title { font-size: 1.1rem; }
    .table thead th { border-top: 0; }
    .table-head-fixed th { z-index: 10; }
    .hover-link:hover { text-decoration: underline; opacity: 0.8; }
    .clickable-box { transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; }
    .clickable-box:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important; }
</style>';

include '../includes/footer.php'; 
?>

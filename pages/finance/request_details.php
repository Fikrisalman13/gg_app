<?php
// request_details.php - Detailed view with audit logs and timeline
session_start();
ob_start();
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$requestId = $_GET['id'] ?? '';
if (empty($requestId)) {
    die("ID Pengajuan tidak ditemukan.");
}

// 1. Fetch Request Details
$sql = "SELECT r.*, u.UnitName, c.CategoryName, ar.RuleName, ar.MinAmount, ar.MaxAmount,
               emp.nama_lengkap as RequesterName, curr_s.StepName as CurrentStepName,
               bp.Period as BudgetPeriod,
               (SELECT COUNT(*) FROM fin_request_history h WHERE h.RequestId = r.RequestId AND h.Action = 'Approve') as ApprovalCount
        FROM fin_requests r
        LEFT JOIN fin_org_units u ON r.UnitId = u.UnitId
        LEFT JOIN fin_categories c ON r.CategoryId = c.CategoryId
        LEFT JOIN fin_budget_plans bp ON r.BudgetId = bp.BudgetId
        LEFT JOIN fin_approval_rules ar ON r.RuleId = ar.RuleId
        LEFT JOIN fin_workflow_steps curr_s ON r.CurrentStepId = curr_s.StepId
        LEFT JOIN dbo.SMUserMs usr ON r.CreatedBy = usr.UserId
        LEFT JOIN dbo.m_emp emp ON usr.EmpId = emp.id_emp
        WHERE r.RequestId = ?";
$stmt = sqlsrv_query($conn, $sql, [$requestId]);

$req = null;
if ($stmt) {
    $req = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
}

if (!$req) {
    die("Data pengajuan tidak ditemukan atau tabel belum siap.");
}

// 2. Fetch History / Timeline
$histSql = "SELECT h.*, s.StepName, emp.nama_lengkap as ActorName
            FROM fin_request_history h
            LEFT JOIN fin_workflow_steps s ON h.StepId = s.StepId
            LEFT JOIN dbo.SMUserMs usr ON h.ActorUserId = usr.UserId
            LEFT JOIN dbo.m_emp emp ON usr.EmpId = emp.id_emp
            WHERE h.RequestId = ?
            ORDER BY h.ActionTimestamp ASC";
$histStmt = sqlsrv_query($conn, $histSql, [$requestId]);
$history = [];
if ($histStmt) {
    while ($row = sqlsrv_fetch_array($histStmt, SQLSRV_FETCH_ASSOC)) {
        $history[] = $row;
    }
    sqlsrv_free_stmt($histStmt);
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0 text-dark">Detail Pengajuan #<?= $requestId ?></h1></div>
                <div class="col-sm-6 text-right">
                    <a href="requests.php" class="btn btn-default"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Left: Info Card -->
                <div class="col-md-7">
                    <div class="card shadow-sm border-top-info">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                            <h3 class="card-title font-weight-bold">Informasi Pengajuan</h3>
                            <div class="card-tools">
                                <?php 
                                $statusBadge = 'secondary';
                                $displayStatus = $req['Status'];

                                if ($req['Status'] == 'Approved') { $statusBadge = 'success'; }
                                if ($req['Status'] == 'Rejected') { $statusBadge = 'danger'; }
                                if ($req['Status'] == 'Pending') { 
                                    if ($req['ApprovalCount'] > 0) {
                                        $statusBadge = 'info';
                                        $displayStatus = 'On Process';
                                    } else {
                                        $statusBadge = 'warning';
                                    }
                                }
                                if ($req['Status'] == 'Paid') { $statusBadge = 'primary'; }
                                ?>
                                <span class="badge badge-<?= $statusBadge ?> p-2 px-3"><?= strtoupper($displayStatus) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr><th width="150">Judul</th><td>: <?= htmlspecialchars($req['Title']) ?></td></tr>
                                <tr><th>Pengaju</th><td>: <?= htmlspecialchars($req['RequesterName']) ?></td></tr>
                                <tr><th>Unit</th><td>: <span class="badge badge-light border"><?= htmlspecialchars($req['UnitName']) ?></span></td></tr>
                                <tr><th>Kategori</th><td>: <?= htmlspecialchars($req['CategoryName']) ?></td></tr>
                                <tr><th>Budget Period</th><td>: <span class="badge badge-info"><?= $req['BudgetPeriod'] ?? 'N/A' ?></span></td></tr>
                                <tr><th>Nominal</th><td>: <span class="text-primary font-weight-bold" style="font-size: 1.2rem;">Rp <?= number_format($req['Amount'], 0, ',', '.') ?></span></td></tr>
                                <tr><th>Aturan (Rule)</th><td>: <small class="text-muted"><?= htmlspecialchars($req['RuleName']) ?> (Range: Rp <?= number_format($req['MinAmount'], 0) ?> - <?= number_format($req['MaxAmount'], 0) ?>)</small></td></tr>
                                <tr><th>Tahapan Saat Ini</th><td>: <span class="badge badge-outline-warning"><?= htmlspecialchars($req['CurrentStepName'] ?? 'Selesai') ?></span></td></tr>
                            </table>

                            <hr>
                            <strong><i class="fas fa-file-alt mr-1"></i> Deskripsi / Keterangan</strong>
                            <p class="text-muted mt-2">
                                <?= nl2br(htmlspecialchars($req['Description'] ?? 'Tidak ada deskripsi tambahan.')) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right: Timeline -->
                <div class="col-md-5">
                    <div class="card shadow-sm">
                        <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2"></i>Log & Timeline</h3>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($history as $h): ?>
                                <div>
                                    <?php 
                                    $icon = 'fas fa-info bg-gray';
                                    if ($h['Action'] == 'Submit') $icon = 'fas fa-paper-plane bg-blue';
                                    if ($h['Action'] == 'Approve') $icon = 'fas fa-check bg-green';
                                    if ($h['Action'] == 'Reject') $icon = 'fas fa-times bg-red';
                                    ?>
                                    <i class="<?= $icon ?>"></i>
                                    <div class="timeline-item shadow-none border">
                                        <span class="time"><i class="fas fa-clock"></i> <?= date_format($h['ActionTimestamp'], 'd/m/Y H:i') ?></span>
                                        <h3 class="timeline-header no-border font-weight-bold">
                                            <?= htmlspecialchars($h['ActorName']) ?> - <small><?= htmlspecialchars($h['StepName']) ?></small>
                                        </h3>
                                        <div class="timeline-body p-2">
                                            <span class="badge badge-<?= $h['Action'] == 'Approve' ? 'success' : ($h['Action'] == 'Reject' ? 'danger' : 'info') ?> py-1 px-2">
                                                <?= $h['Action'] ?>
                                            </span>
                                            <?php if ($h['Note']): ?>
                                                <div class="mt-2 text-sm text-dark italic"><?= htmlspecialchars($h['Note']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <div>
                                    <i class="fas fa-clock bg-gray"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

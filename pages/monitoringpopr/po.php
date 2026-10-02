<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

$groupId = $_SESSION['GroupId'];
$menuId = 34; // Adjust this to your actual menu ID for PO Monitoring

$sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);

$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);

if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

// Get PO Monitoring Data
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d H:i:s', strtotime('-24 hours'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d H:i:s');

$monitoringQuery = "SELECT
	a.ponmbr AS po_number,
	a.podate AS po_date,
	a.povendorname,
	a.upddate AS created_date,
	smemployee.empname AS creator_name 
FROM
	prpohd AS a
	LEFT JOIN smemployee ON a.poinitiatorid = smemployee.empid
WHERE
    a.upddate BETWEEN ? AND ?
ORDER BY
    a.ponmbr ASC";

$stmt = $conn3->prepare($monitoringQuery);
$stmt->execute([$startDate, $endDate]);
$monitoringData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by creator
$creatorStats = [];
$totalPOs = 0;

foreach ($monitoringData as $row) {
    $creator = $row['creator_name'] ?: $row['user_id'];
    if (!isset($creatorStats[$creator])) {
        $creatorStats[$creator] = 0;
    }
    $creatorStats[$creator]++;
    $totalPOs++;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Pembuatan PO</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
    <style>

.summary-box {
    background-color: #f8f9fa;
    border-left: 5px solid #17a2b8;
    padding: 1rem;
    border-radius: 0.5rem;
}
</style>

</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Monitoring Pembuatan PO</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Monitoring PO</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if (isset($error_message)) : ?>
                    <div class="alert alert-danger">
                        <?= $error_message ?>
                    </div>
                <?php else : ?>
                    <!-- Monitoring Section -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                    <h3 class="card-title">
                                        Monitoring Pembuatan PO
                                    </h3>
                                </div>
                                <div class="card-body">
                                <form method="get" class="form-inline mb-3">
                                    <div class="form-group mr-2">
                                        <label for="start_date" class="mr-2">Dari:</label>
                                        <input type="datetime-local" class="form-control form-control-sm" id="start_date" name="start_date" 
                                            value="<?= date('Y-m-d\TH:i', strtotime($startDate)) ?>">
                                    </div>
                                    <div class="form-group mr-2">
                                        <label for="end_date" class="mr-2">Sampai:</label>
                                        <input type="datetime-local" class="form-control form-control-sm" id="end_date" name="end_date" 
                                            value="<?= date('Y-m-d\TH:i', strtotime($endDate)) ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    
                                    <!-- Tombol Export PDF -->
                                    <a href="generate_pdf_po.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                    class="btn btn-danger btn-sm ml-2" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                    </a>
                                    
                                    <!-- Tombol baru untuk link ke status_po.php -->
                                    <a href="status_po.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                    class="btn btn-info btn-sm ml-2">
                                    <i class="fas fa-chart-bar"></i> Status PO
                                    </a>
                                </form>
                                    
                                    <!-- Summary Card -->
                                    <div class="card shadow-sm mb-3">
                                        <div class="card-body">
                                            <h5 class="mb-4  fw-bold">
                                                <i class="fas fa-info-circle"></i> Ringkasan Periode:
                                                <?= date('d M Y H:i', strtotime($startDate)) ?> - <?= date('d M Y H:i', strtotime($endDate)) ?>
                                            </h5>

                                            <div class="row g-3">
                                                <?php foreach ($creatorStats as $creator => $count) : ?>
                                                    <div class="col-lg-3 col-md-4 col-sm-6">
                                                        <div class="summary-box d-flex align-items-center">
                                                            <div class="me-3 text-info fs-4">
                                                                
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold fs-5"><?= htmlspecialchars($creator) ?></div>
                                                                <div class="text-muted fs-6"><?= $count ?> PO</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="text-end mt-4">
                                                <span class="badge bg-success fs-6 px-4 py-2">
                                                    <i class="fas fa-file-invoice"></i> Total: <strong><?= $totalPOs ?> PO</strong>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    
                                    <?php if (!empty($monitoringData)) : ?>
                                        <div class="table-responsive">
                                            <table id="poMonitoringTable" class="table table-bordered table-hover table-sm">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th class="text-center">No</th>
                                                        <th class="text-center">No PO</th>
                                                        <th class="text-center">Tgl PO</th>
                                                        <th class="text-center">Vendor</th>
                                                        <th class="text-center">Tgl Dibuat</th>
                                                        <th class="text-center">Nama Pembuat</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($monitoringData as $index => $row) : ?>
                                                        <tr>
                                                            <td class="text-center"><?= $index + 1 ?></td>
                                                            <td><?= htmlspecialchars($row['po_number']) ?></td>
                                                            <td class="text-center"><?= date('d/m/Y', strtotime($row['po_date'])) ?></td>
                                                            <td><?= htmlspecialchars($row['povendorname']) ?></td>
                                                            <td class="text-center"><?= date('d/m/Y H:i', strtotime($row['created_date'])) ?></td>
                                                            <td><?= htmlspecialchars($row['creator_name']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else : ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Tidak ada data PO yang dibuat pada periode ini.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
    $(document).ready(function() {
        $("#poMonitoringTable").DataTable({
            responsive: true,
            destroy: true,
            order: [[1, 'asc']],
            language: {
                url: '/gg_app/plugins/js/datatables/Indonesian.json'
            }
        });
    });
</script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>
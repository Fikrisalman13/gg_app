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
$menuId = 35; // Adjust this to your actual menu ID for PO Status Monitoring

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

// Set default date range (last 30 days)
$defaultStart = date('Y-m-d 00:00:00', strtotime('-30 days'));
$defaultEnd = date('Y-m-d 23:59:59');

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : $defaultStart;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $defaultEnd;

// Query untuk mendapatkan data PO dengan filter
$query = "SELECT
            prpohd.ponmbr, 
            prpohd.podate, 
            prpohd.povendorname, 
            prpohd.podesc,          
            prpohd.fgstatus
          FROM
            prpohd
        
          WHERE
            prpohd.podate BETWEEN ? AND ?
            AND prpohd.fgstatus IN ('X', 'O', 'C', 'V', 'U') 
            ORDER BY
            prpohd.ponmbr ASC";

$stmt = $conn3->prepare($query);
$stmt->execute([$startDate, $endDate]);
$poData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung summary berdasarkan status
$statusSummary = [
    'O' => ['count' => 0, 'label' => 'Open (Belum Disetujui)', 'color' => 'warning'],
    'V' => ['count' => 0, 'label' => 'Approve (Sudah Disetujui)', 'color' => 'success'],
    'C' => ['count' => 0, 'label' => 'Cancel (Batal)', 'color' => 'danger'],
    'U' => ['count' => 0, 'label' => 'Outstanding (Dalam Proses)', 'color' => 'info'],
    'X' => ['count' => 0, 'label' => 'Close (Selesai)', 'color' => 'primary']
];

foreach ($poData as $po) {
    if (isset($statusSummary[$po['fgstatus']])) {
        $statusSummary[$po['fgstatus']]['count']++;
    }
}

$totalPOs = count($poData);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Status PO</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
    <style>
        .summary-box {
            background-color: #f8f9fa;
            border-left: 5px solid;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .bg-open { border-left-color: #ffc107; }
        .bg-approve { border-left-color: #28a745; }
        .bg-cancel { border-left-color: #dc3545; }
        .bg-outstanding { border-left-color: #17a2b8; }
        .bg-close { border-left-color: #6c757d; }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.35em 0.65em;
        }
        .vendor-name {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                        <h1 class="m-0">Monitoring Status PO</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Status PO</li>
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
                    <!-- Filter Section -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                    <h3 class="card-title">
                                       Filter Data
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
                                        <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <a href="generate_pdf_statuspo.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                           class="btn btn-danger btn-sm ml-2" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Export PDF
                                        </a>
                                        <a href="po.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" 
                                           class="btn btn-info btn-sm ml-2">
                                            <i class="fas fa-list"></i> Monitoring PO
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Section -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                    <h3 class="card-title">
                                        <i class="fas fa-chart-pie"></i> Ringkasan Status PO
                                    </h3>
                                    <div class="card-tools">
                                        <span class="badge bg-white text-primary">
                                            Periode: <?= date('d M Y H:i', strtotime($startDate)) ?> - <?= date('d M Y H:i', strtotime($endDate)) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info mb-4">
                                        <h4 class="mb-0">
                                            <i class="fas fa-file-invoice"></i> Total PO: <strong><?= number_format($totalPOs, 0, ',', '.') ?></strong>
                                        </h4>
                                    </div>

                                    <div class="row g-3">
                                        <?php foreach ($statusSummary as $status => $info): ?>
                                            <?php if ($info['count'] > 0): ?>
                                                <div class="col-lg-3 col-md-4 col-sm-6">
                                                    <div class="summary-box bg-<?= strtolower(explode(' ', $info['label'])[0]) ?>">
                                                        <div class="d-flex align-items-center">
                                                            <div class="me-3 text-<?= $info['color'] ?> fs-4">
                                                                <i class="fas fa-<?= $status === 'O' ? 'folder-open' : ($status === 'V' ? 'check-circle' : ($status === 'C' ? 'times-circle' : ($status === 'U' ? 'spinner' : 'file-archive'))) ?>"></i>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold fs-5"><?= $info['label'] ?></div>
                                                                <div class="text-muted fs-6"><?= number_format($info['count'], 0, ',', '.') ?> PO</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                    <h3 class="card-title">
                                        <i class="fas fa-table"></i> Detail PO
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($poData)) : ?>
                                        <div class="table-responsive">
                                            <table id="poTable" class="table table-bordered table-hover table-sm">
                                            <thead class="thead-light">
                                                    <tr>
                                                        <th class="text-center">No</th>
                                                        <th class="text-center">Nomor PO</th>
                                                        <th class="text-center">Tanggal PO</th>
                                                        <th class="text-center">Vendor</th>
                                                        <th class="text-center">Deskripsi</th>
                                                       
                                                        <th class="text-center">Status</th>
                                                       
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($poData as $index => $po): ?>
                                                        <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>

                                                            <td><?= htmlspecialchars($po['ponmbr']) ?></td>
                                                            <td class="text-center"><?= date('d/m/Y', strtotime($po['podate'])) ?></td>
                                                            <td class="vendor-name" title="<?= htmlspecialchars($po['povendorname']) ?>">
                                                                <?= htmlspecialchars($po['povendorname']) ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($po['podesc']) ?></td>
                                                            
                                                            <td class="text-center">
                                                                <?php 
                                                                $statusText = '';
                                                                $badgeClass = '';
                                                                
                                                                switch ($po['fgstatus']) {
                                                                    case 'O':
                                                                        $statusText = 'Open (Belum Disetujui)';
                                                                        $badgeClass = 'warning';
                                                                        break;
                                                                    case 'V':
                                                                        $statusText = 'Approve (Sudah Disetujui)';
                                                                        $badgeClass = 'success';
                                                                        break;
                                                                    case 'C':
                                                                        $statusText = 'Cancel (Batal)';
                                                                        $badgeClass = 'danger';
                                                                        break;
                                                                    case 'U':
                                                                        $statusText = 'Outstanding (Dalam Proses)';
                                                                        $badgeClass = 'info';
                                                                        break;
                                                                    case 'X':
                                                                        $statusText = 'Close (Selesai)';
                                                                        $badgeClass = 'primary';
                                                                        break;
                                                                    default:
                                                                        $statusText = 'Unknown';
                                                                        $badgeClass = 'secondary';
                                                                }
                                                                ?>
                                                                <span class="badge status-badge badge-<?= $badgeClass ?>"><?= $statusText ?></span>
                                                            </td>
                                                            
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else : ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Tidak ada data PO yang ditemukan pada periode ini.
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
        $("#poTable").DataTable({
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
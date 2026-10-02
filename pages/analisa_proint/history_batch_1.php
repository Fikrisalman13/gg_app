<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: ../login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission Check
$groupId = $_SESSION['GroupId'];
$menuId  = 75; // Sesuaikan MenuId halaman ini
$sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

// Ambil batch number dari GET
$batchno = isset($_GET['batchno']) ? trim($_GET['batchno']) : '';
$results = [];

if ($batchno !== '') {
    $query = "
        SELECT DISTINCT
        A.transdestnmbr, 
        A.transdesttype, 
        D.transname, 
        E.wrhscode, 
        E.wrhsname, 
        A.transdestdate, 
        B.prodcode, 
        B.prodname, 
        G.uomname, 
        C.batchno, 
        C.batchinqty, 
        C.batchoutqty, 
        B.processdate, 
        B.upddate, 
        B.upduser,
        'NEW' AS sumber_batch
    FROM
        whtranshd AS A
        INNER JOIN whtransdt AS B ON A.transhdid = B.transhdid
        INNER JOIN whtransdtbatch AS C ON B.transdtid = C.whtransdtid
        INNER JOIN whtransms AS D ON A.transdesttype = D.transcode
        LEFT JOIN whwrhs AS E ON A.transdestwrhsid = E.wrhsid
        INNER JOIN smproduct AS F ON B.transprodid = F.prodid
        INNER JOIN smuom AS G ON F.uomid = G.uomid
    WHERE
        C.batchno = :batchno

    UNION ALL

    SELECT DISTINCT
        A.transdestnmbr, 
        A.transdesttype, 
        D.transname, 
        E.wrhscode, 
        E.wrhsname, 
        A.transdestdate, 
        B.prodcode, 
        B.prodname, 
        G.uomname, 
        C.batchno, 
        C.batchinqty, 
        C.batchoutqty, 
        B.processdate, 
        B.upddate, 
        B.upduser,
        'OLD' AS sumber_batch
    FROM
        whtranshd AS A
        INNER JOIN whtransdt AS B ON A.transhdid = B.transhdid
        INNER JOIN whtransdtbatch2022 AS C ON B.transdtid = C.whtransdtid
        INNER JOIN whtransms AS D ON A.transdesttype = D.transcode
        LEFT JOIN whwrhs AS E ON A.transdestwrhsid = E.wrhsid
        INNER JOIN smproduct AS F ON B.transprodid = F.prodid
        INNER JOIN smuom AS G ON F.uomid = G.uomid
    WHERE
        C.batchno = :batchno

    ORDER BY
        upddate ASC, 
        transdesttype DESC";
    $stmt = $conn3->prepare($query);
    $stmt->bindValue(':batchno', $batchno);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisa History Batch</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">History Batch</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                            <li class="breadcrumb-item active">History Batch</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">               
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title">History Transaksi Batch</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Batch -->
                                <form method="get" class="form-inline mb-3">
                                    <label for="batchno" class="mr-2">Batch No:</label>
                                    <input type="text" id="batchno" name="batchno" class="form-control form-control-sm mr-2" 
                                           value="<?= htmlspecialchars($batchno) ?>" placeholder="Masukkan no batch" required>
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm">
                                        <i class="fas fa-search"></i> Cari
                                    </button>                                    
                                </form>

                                <?php if ($batchno !== '' && count($results) > 0): ?>
                                        <a href="export_excel_analisa_batch.php?batchno=<?= urlencode($batchno) ?>" 
                                        class="btn btn-success btn-sm mb-3">
                                            <i class="fas fa-file-excel"></i> Export Excel
                                        </a>
                                    <?php endif; ?>

                               <!-- Data Table -->
                                <div class="table-responsive">                 
                                    <table id="batchTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center align-middle">
                                                <th>No</th>
                                                <th>Trans No</th>                                               
                                                <th>Warehouse</th>
                                                <th>Product</th>                                                
                                                <th>Batch</th>
                                                <th>In Qty</th>
                                                <th>Out Qty</th>
                                                <th>UOM</th>
                                                <th>Sumber</th>
                                                <th>Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (count($results) > 0) {
                                            $no = 1;
                                            foreach ($results as $row) {
                                                $sumberBadge = ($row['sumber_batch'] == 'NEW') 
                                                    ? "<span class='badge badge-success'>NEW</span>" 
                                                    : "<span class='badge badge-secondary'>OLD</span>";

                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";
                                                
                                                echo "<td>".htmlspecialchars($row['transdestnmbr'])."<br><small class='text-muted'>".htmlspecialchars($row['transname'])."</small></td>";


                                                // Warehouse gabungan
                                                echo "<td>".htmlspecialchars($row['wrhscode'])."<br><small class='text-muted'>".htmlspecialchars($row['wrhsname'])."</small></td>";

                                                // Product gabungan
                                                echo "<td>".htmlspecialchars($row['prodcode'])."<br><small class='text-muted'>".htmlspecialchars($row['prodname'])."</small></td>";

                                               
                                                // Batch No + Prod No gabungan
                                                echo "<td>".htmlspecialchars($row['batchno'])."</td>";

                                                echo "<td class='text-right'>".number_format($row['batchinqty'],2)."</td>";
                                                echo "<td class='text-right'>".number_format($row['batchoutqty'],2)."</td>";
                                                echo "<td class='text-center'>".htmlspecialchars($row['uomname'])."</td>";

                                                echo "<td class='text-center'>".$sumberBadge."</td>";

                                                // Update Date + User gabungan
                                                $updDate = $row['upddate'] ? date("d/m/Y H:i", strtotime($row['upddate'])) : '';
                                                echo "<td>".$updDate."<br><small class='text-muted'>".htmlspecialchars($row['upduser'])."</small></td>";

                                                echo "</tr>";
                                            }
                                        } else {
                                            if ($batchno !== '') {
                                                echo "<tr><td colspan='11' class='text-center py-4 text-danger'>Data tidak ditemukan untuk batch: <strong>".htmlspecialchars($batchno)."</strong></td></tr>";
                                            } else {
                                                echo "<tr><td colspan='11' class='text-center py-4'>Silakan masukkan no batch</td></tr>";
                                            }
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>


                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/js/datatables/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $("#batchTable").DataTable({
            responsive: true,
            destroy: true
        });
    });
</script>
</body>
</html>

<?php include '../../includes/footer.php'; ?>

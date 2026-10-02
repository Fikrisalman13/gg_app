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

// Ambil parameter dari GET
$batchno = isset($_GET['batchno']) ? trim($_GET['batchno']) : '';
$transno = isset($_GET['transno']) ? trim($_GET['transno']) : '';
$cpno = isset($_GET['cpno']) ? trim($_GET['cpno']) : '';
$results = [];

// Cek apakah ada parameter pencarian
if ($batchno !== '' || $transno !== '' || $cpno !== '') {
    $query = "
        SELECT DISTINCT
        A.transdestnmbr, 
        J.prdnmbr,
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
        INNER JOIN pdresultdt AS H ON C.batchno = H.batchno
        INNER JOIN pdresulthd AS I ON H.resulthdid = I.resulthdid
        INNER JOIN pdproductionhd AS J ON I.productionhdid = J.productionhdid
    WHERE
        1=1";

    $query_old = "
        SELECT DISTINCT
        A.transdestnmbr, 
        J.prdnmbr,
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
        INNER JOIN pdresultdt AS H ON C.batchno = H.batchno
        INNER JOIN pdresulthd AS I ON H.resulthdid = I.resulthdid
        INNER JOIN pdproductionhd AS J ON I.productionhdid = J.productionhdid
    WHERE
        1=1";

    // Tambahkan kondisi WHERE berdasarkan filter
    $conditions = [];
    $params = [];
    
    if ($batchno !== '') {
        $conditions[] = "C.batchno = :batchno";
        $params[':batchno'] = $batchno;
    }
    
    if ($transno !== '') {
        $conditions[] = "A.transdestnmbr LIKE :transno";
        $params[':transno'] = '%' . $transno . '%';
    }
    
    if ($cpno !== '') {
        $conditions[] = "J.prdnmbr LIKE :cpno";
        $params[':cpno'] = '%' . $cpno . '%';
    }
    
    // Gabungkan kondisi WHERE
    if (!empty($conditions)) {
        $where_clause = " AND " . implode(" AND ", $conditions);
        $query .= $where_clause;
        $query_old .= $where_clause;
    }
    
    // Gabungkan kedua query dengan UNION ALL
    $final_query = $query . " UNION ALL " . $query_old . " ORDER BY upddate ASC, transdesttype DESC";
    
    $stmt = $conn3->prepare($final_query);
    
    // Bind parameter
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
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
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
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
                                <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                                History Transaksi Batch</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="batchno">Batch No:</label>
                                                <input type="text" id="batchno" name="batchno" class="form-control form-control-sm" 
                                                       value="<?= htmlspecialchars($batchno) ?>" placeholder="Masukkan no batch">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="transno">Transaction No:</label>
                                                <input type="text" id="transno" name="transno" class="form-control form-control-sm" 
                                                       value="<?= htmlspecialchars($transno) ?>" placeholder="Masukkan transaction number">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="cpno">No CP:</label>
                                                <input type="text" id="cpno" name="cpno" class="form-control form-control-sm" 
                                                       value="<?= htmlspecialchars($cpno) ?>" placeholder="Masukkan no CP">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group" style="margin-top: 32px;">
                                                <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm">
                                                    <i class="fas fa-search"></i> Cari
                                                </button>
                                                <a href="?" class="btn btn-secondary btn-sm">
                                                    <i class="fas fa-refresh"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <?php if (($batchno !== '' || $transno !== '' || $cpno !== '') && count($results) > 0): ?>
                                    <a href="export_excel_analisa_batch.php?batchno=<?= urlencode($batchno) ?>&transno=<?= urlencode($transno) ?>&cpno=<?= urlencode($cpno) ?>" 
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
                                                <th>No CP</th>
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
                                                
                                                // Kolom No CP baru
                                                echo "<td>".htmlspecialchars($row['prdnmbr'])."</td>";

                                                // Warehouse gabungan
                                                echo "<td>".htmlspecialchars($row['wrhscode'])."<br><small class='text-muted'>".htmlspecialchars($row['wrhsname'])."</small></td>";

                                                // Product gabungan
                                                echo "<td>".htmlspecialchars($row['prodcode'])."<br><small class='text-muted'>".htmlspecialchars($row['prodname'])."</small></td>";

                                               
                                                // Batch No
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
                                            if ($batchno !== '' || $transno !== '' || $cpno !== '') {
                                                echo "<tr><td colspan='12' class='text-center py-4 text-danger'>Data tidak ditemukan";
                                                if ($batchno !== '') {
                                                    echo " untuk batch: <strong>".htmlspecialchars($batchno)."</strong>";
                                                }
                                                if ($transno !== '') {
                                                    echo " untuk transaction: <strong>".htmlspecialchars($transno)."</strong>";
                                                }
                                                if ($cpno !== '') {
                                                    echo " untuk CP: <strong>".htmlspecialchars($cpno)."</strong>";
                                                }
                                                echo "</td></tr>";
                                            } else {
                                                echo "<tr><td colspan='12' class='text-center py-4'>Silakan masukkan Batch No, Transaction No, atau No CP</td></tr>";
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

<script>
    $(document).ready(function() {
        $("#batchTable").DataTable({
        responsive: true,
        autoWidth: false,
        language: {
            processing: "Memproses...",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data ditemukan",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data tersedia",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        },
    });
    });
</script>

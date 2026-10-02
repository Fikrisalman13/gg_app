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
$cpno = isset($_GET['cpno']) ? trim($_GET['cpno']) : '';
$results = [];

// Cek apakah ada parameter pencarian
if ($cpno !== '') {
    $query = "WITH hd AS (\n    SELECT productionhdid, prdnmbr\n    FROM pdproductionhd\n    WHERE prdnmbr = :cpno\n),\nlast_p AS (\n    SELECT \n        r.productionhdid,\n        MAX(r.rtgseq) AS last_p_seq\n    FROM pdproductionrtg r\n    JOIN hd h ON h.productionhdid = r.productionhdid\n    WHERE r.fgresult = 'P'\n    GROUP BY r.productionhdid\n)\nSELECT \n    h.prdnmbr,\n    r.rtgseq,\n    r.rtgmsid,\n    m.rtgname,\n    r.starttime,\n    r.endtime,\n    r.upddate\nFROM pdproductionrtg r\nJOIN hd h \n    ON h.productionhdid = r.productionhdid\nJOIN last_p lp \n    ON lp.productionhdid = r.productionhdid\nLEFT JOIN pdrtgms m \n    ON m.rtgmsid = r.rtgmsid\nWHERE r.rtgseq <= lp.last_p_seq + 1\nORDER BY r.rtgseq;";
    $stmt = $conn3->prepare($query);
    $stmt->bindValue(':cpno', $cpno);
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
                        <h1 class="m-0">History CP Routing</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">History CP Routing</li>
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
                                History CP Routing</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="mb-3">
                                    <div class="row">
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
                                <!-- Data Table -->
                                <div class="table-responsive">                 
                                    <table id="cpRoutingTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr class="text-center align-middle">
                                                <th>No</th>
                                                <th>No CP</th>
                                                <th>Routing Seq</th>
                                                <th>Routing Name</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if ($cpno !== '' && count($results) > 0) {
                                            $no = 1;
                                            foreach ($results as $row) {
                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";
                                                echo "<td>".htmlspecialchars($row['prdnmbr'])."</td>";
                                                echo "<td class='text-center'>".htmlspecialchars($row['rtgseq'])."</td>";
                                                echo "<td>".htmlspecialchars($row['rtgname'])."</td>";
                                                echo "<td>".($row['starttime'] ? date("d/m/Y H:i", strtotime($row['starttime'])) : '')."</td>";
                                                echo "<td>".($row['endtime'] ? date("d/m/Y H:i", strtotime($row['endtime'])) : '')."</td>";
                                                echo "<td>".($row['upddate'] ? date("d/m/Y H:i", strtotime($row['upddate'])) : '')."</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            if ($cpno !== '') {
                                                echo "<tr><td colspan='7' class='text-center py-4 text-danger'>Data tidak ditemukan untuk CP: <strong>".htmlspecialchars($cpno)."</strong></td></tr>";
                                            } else {
                                                echo "<tr><td colspan='7' class='text-center py-4'>Silakan masukkan No CP</td></tr>";
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
<?php include '../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
    $(document).ready(function() {
        $("#cpRoutingTable").DataTable({
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

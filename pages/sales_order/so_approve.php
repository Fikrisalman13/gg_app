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
$menuId = 31;

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

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'lokal';
$status = isset($_GET['status']) ? $_GET['status'] : 'O'; // Default to Open status
$_SESSION['filter'] = $filter;

$queries = [
    'lokal' => "WITH FilteredInsohd AS (SELECT sohdid, sonmbr, sodate, socuscode, socusname, sodesc, fgstatus FROM insohd WHERE fgstatus = '$status' AND sotype IN ('G', 'J') AND soorgid = '22') SELECT A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus, SUM(B.sototalamounthc) AS total_amount FROM FilteredInsohd AS A JOIN insodt AS B ON A.sohdid = B.sohdid GROUP BY A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus ORDER BY A.sodate DESC",
    'eksport' => "WITH FilteredInsohd AS (SELECT sohdid, sonmbr, sodate, socuscode, socusname, sodesc, fgstatus FROM insohd WHERE fgstatus = '$status' AND sotype IN ('G', 'J') AND soorgid = '21') SELECT A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus, SUM(B.sototalamounthc) AS total_amount FROM FilteredInsohd AS A JOIN insodt AS B ON A.sohdid = B.sohdid GROUP BY A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus ORDER BY A.sodate DESC",
    'grosir' => "WITH FilteredInsohd AS (SELECT sohdid, sonmbr, sodate, socuscode, socusname, sodesc, fgstatus FROM insohd WHERE fgstatus = '$status' AND sotype IN ('G', 'J') AND soorgid = '25') SELECT A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus, SUM(B.sototalamounthc) AS total_amount FROM FilteredInsohd AS A JOIN insodt AS B ON A.sohdid = B.sohdid GROUP BY A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus ORDER BY A.sodate DESC",
    'surabaya' => "WITH FilteredInsohd AS (SELECT sohdid, sonmbr, sodate, socuscode, socusname, sodesc, fgstatus FROM insohd WHERE fgstatus = '$status' AND sotype IN ('G', 'J') AND soorgid = '17') SELECT A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus, SUM(B.sototalamounthc) AS total_amount FROM FilteredInsohd AS A JOIN insodt AS B ON A.sohdid = B.sohdid GROUP BY A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus ORDER BY A.sodate DESC",
    'online' => "WITH FilteredInsohd AS (SELECT sohdid, sonmbr, sodate, socuscode, socusname, sodesc, fgstatus FROM insohd WHERE fgstatus = '$status' AND sotype IN ('G', 'J') AND soorgid = '26') SELECT A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus, SUM(B.sototalamounthc) AS total_amount FROM FilteredInsohd AS A JOIN insodt AS B ON A.sohdid = B.sohdid GROUP BY A.sonmbr, A.sodate, A.socuscode, A.socusname, A.sodesc, A.fgstatus ORDER BY A.sodate DESC"
];

$query = $queries[$filter] ?? $queries['lokal'];
$result = $conn3->query($query);
?>


<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Sales Order</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Sales Order</li>
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
                                    Daftar Sales Order
                                </h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Section -->
                                <form method="get" class="form-inline mb-3">
                                    <div class="form-group mr-2">
                                        <label for="filter" class="mr-2">Sales Organization:</label>
                                        <select class="form-control form-control-sm" id="filter" name="filter" onchange="this.form.submit()">
                                            <option value="lokal" <?= $filter == 'lokal' ? 'selected' : '' ?>>Lokal</option>
                                            <option value="eksport" <?= $filter == 'eksport' ? 'selected' : '' ?>>Eksport</option>
                                            <option value="grosir" <?= $filter == 'grosir' ? 'selected' : '' ?>>Grosir</option>
                                            <option value="surabaya" <?= $filter == 'surabaya' ? 'selected' : '' ?>>Surabaya</option>
                                            <option value="online" <?= $filter == 'online' ? 'selected' : '' ?>>Online</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group mr-2">
                                        <label for="status" class="mr-2">Status:</label>
                                        <select class="form-control form-control-sm" id="status" name="status" onchange="this.form.submit()">
                                            <option value="O" <?= $status == 'O' ? 'selected' : '' ?>>Open (Belum Approve)</option>
                                            <option value="V" <?= $status == 'V' ? 'selected' : '' ?>>Approved</option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($status == 'O') : ?>
                                        <button type="button" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm mr-2" id="approve-selected">
                                            <i class="fas fa-check-circle"></i> Approve Selected
                                        </button>
                                    <?php else : ?>
                                        <button type="button" class="btn btn-warning btn-sm mr-2" id="unapprove-selected">
                                            <i class="fas fa-times-circle"></i> Unapprove Selected
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-info btn-sm" onclick="location.reload()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </form>
                            

                                <!-- Data Table -->
                                <div class="table-responsive">                 
                                    <table id="salesOrderTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center"><input type="checkbox" id="select-all"></th>
                                                <th class="text-center">No</th>
                                                <th class="text-center">No Sales Order</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-center">Customer Code</th>
                                                <th class="text-center">Customer Name</th>
                                                <th class="text-center">Description</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center">Total Amount (HC)</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        
                                        <tbody>
                                        <?php
                                        if ($result->rowCount() > 0) {
                                            $no = 1;
                                            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<tr>";
                                                echo "<td class='text-center'><input type='checkbox' class='select-order' name='sonmbrs[]' value='" . htmlspecialchars($row['sonmbr']) . "'></td>";
                                                echo "<td>" . $no++ . "</td>";
                                                echo "<td>" . htmlspecialchars($row['sonmbr']) . "</td>";            
                                                echo "<td>" . date("d/m/Y", strtotime($row['sodate'])) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['socuscode']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['socusname']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['sodesc']??'') . "</td>";
                                                echo "<td class='text-center'>" . ($row['fgstatus'] == 'O' ? '<span class="badge badge-warning">Open</span>' : '<span class="badge badge-success">Approved</span>') . "</td>";
                                                echo "<td class='text-right'>" . number_format($row['total_amount'], 2) . "</td>";
                                                echo "<td class='text-center'>
                                                        <button class='btn btn-info btn-sm' onclick='viewOrder(\"" . htmlspecialchars($row['sonmbr']) . "\", \"" . $filter . "\")'>
                                                            <i class='fas fa-eye'></i>
                                                        </button>
                                                    </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='10' class='text-center'>Tidak ada data yang tersedia</td></tr>";
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
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    $(document).ready(function() {
        $("#salesOrderTable").DataTable({
            responsive: true,
            destroy: true
        });

        // Pilih/Deselect semua checkbox
        $("#select-all").on("click", function () {
            $(".select-order").prop("checked", this.checked);
        });

        // Tombol Approve
        $("#approve-selected").on("click", function () {
            processSelected('approve');
        });

        // Tombol Unapprove
        $("#unapprove-selected").on("click", function () {
            processSelected('unapprove');
        });

        function processSelected(action) {
            let selected = $(".select-order:checked").map(function () {
                return this.value;
            }).get();

            if (selected.length === 0) {
                Swal.fire("Peringatan", "Pilih minimal satu Sales Order.", "warning");
                return;
            }

            const actionText = action === 'approve' ? 'Approve' : 'Unapprove';
            const actionColor = action === 'approve' ? '#28a745' : '#ffc107';
            const successText = action === 'approve' ? 'di-approve' : 'di-unapprove';

            Swal.fire({
                title: actionText + " " + selected.length + " Sales Order?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: actionColor,
                cancelButtonColor: "#d33",
                confirmButtonText: "Ya, " + actionText + "!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "/gg_app/pages/sales_order/proses_approve.php",
                        type: "POST",
                        data: { 
                            'sonmbrs[]': selected,
                            'action': action
                        },
                        traditional: true,
                        success: function (response) {
                            Swal.fire("Berhasil!", "Sales Order berhasil " + successText + ".", "success").then(() => {
                                location.reload();
                            });
                        },
                        error: function () {
                            Swal.fire("Gagal", "Terjadi kesalahan saat " + actionText.toLowerCase() + ".", "error");
                        }
                    });
                }
            });
        }
    });

    function viewOrder(sonmbr) {
        window.location.href = "/gg_app/pages/sales_order/view_order.php?sonmbr=" + sonmbr;
    }
</script>



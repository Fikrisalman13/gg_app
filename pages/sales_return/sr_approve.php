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
$menuId = 31; // Sesuaikan dengan menu ID untuk halaman ini

// Query untuk permission check (menggunakan koneksi yang tepat)
$sql = "SELECT TOP 1 CanView, CanAdd, CanEdit, CanDelete 
        FROM dbo.SMGroupTrustee 
        WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));

$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);

if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    $error_message = "Anda tidak memiliki hak untuk melihat halaman ini.";
}

$status = isset($_GET['status']) ? $_GET['status'] : 'O'; // Default to Open status

// Query untuk mengambil data return menggunakan PDO
$query = "SELECT 
    a.returnno, 
    a.returndate, 
    c.cuscode, 
    c.cusname, 
    d.currcode, 
    a.description, 
    a.soorgid, 
    e.soorgname, 
    f.wrhsname, 
    a.fgstatus, 
    a.approveby, 
    a.approvedate, 
    SUM(b.nettohc)      AS total_netto, 
    SUM(b.ppnhc)        AS total_ppn, 
    SUM(b.totalamount) AS total_amount
FROM 
    inreturnhd AS a
    LEFT JOIN inreturndt AS b ON a.returnhdid = b.returnhdid
    LEFT JOIN smcustomer AS c ON a.cusid = c.cusid
    LEFT JOIN smcurrency AS d ON a.currid = d.currid
    LEFT JOIN insoorg AS e ON a.soorgid = e.soorgid
    LEFT JOIN whwrhs AS f ON a.wrhsid = f.wrhsid
WHERE 
    a.fgstatus = :status
GROUP BY 
    a.returnno, 
    a.returndate, 
    c.cuscode, 
    c.cusname, 
    d.currcode, 
    a.description, 
    a.soorgid, 
    e.soorgname, 
    f.wrhsname, 
    a.fgstatus, 
    a.approveby, 
    a.approvedate
ORDER BY 
    a.returndate DESC;
";

// Menjalankan query dengan PDO
$stmt = $conn3->prepare($query);
$stmt->bindValue(':status', $status);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Sales Return</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="#">Sales</a></li>
                            <li class="breadcrumb-item active">Return Approval</li>
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
                                    Daftar Sales Return
                                </h3>
                               
                            </div>
                            <div class="card-body">
                                <!-- Filter Section -->
                                <form method="get" class="form-inline mb-3">
                                    <div class="form-group mr-2">
                                        <label for="status" class="mr-2">Status:</label>
                                        <select class="form-control form-control-sm" id="status" name="status" onchange="this.form.submit()">
                                            <option value="O" <?= $status == 'O' ? 'selected' : '' ?>>Open (Belum Approve)</option>
                                            <option value="V" <?= $status == 'V' ? 'selected' : '' ?>>Approved</option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($status == 'O') : ?>
                                        <button type="button" class="btn btn-success btn-sm mr-2" id="approve-selected">
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
                                    <table id="salesReturnTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center"><input type="checkbox" id="select-all"></th>
                                                <th class="text-center">No</th>
                                                <th class="text-center">Return No</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-center">Customer</th>
                                                <th class="text-center">Sales Org</th>
                                                <th class="text-center">Description</th>                                                                                       
                                                <th class="text-center">Status</th>
                                            
                                                <th class="text-center">Return Amount</th>                                    
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        
                                        <tbody>
                                        <?php
                                        if (count($results) > 0) {
                                            $no = 1;
                                            foreach ($results as $row) {
                                                $returnDate = $row['returndate'] ? date("d/m/Y", strtotime($row['returndate'])) : '';
                                                $approveDate = $row['approvedate'] ? date("d/m/Y H:i", strtotime($row['approvedate'])) : '-';
                                                
                                                echo "<tr>";
                                                echo "<td class='text-center'><input type='checkbox' class='select-return' name='returnnos[]' value='" . htmlspecialchars($row['returnno']) . "'></td>";
                                                echo "<td class='text-center'>" . $no++ . "</td>";
                                                echo "<td class='text-center'>" . htmlspecialchars($row['returnno']) . "</td>";            
                                                echo "<td class='text-center'>" . $returnDate . "</td>";
                                                echo "<td>" . htmlspecialchars($row['cuscode']) . "<br><small>" . htmlspecialchars($row['cusname']) . "</small></td>";
                                                echo "<td class='text-center'>" . htmlspecialchars($row['soorgname']) . "</td>";
                                                echo "<td><small>" . htmlspecialchars($row['description']) . "</small></td>";
                                                
                                                echo "<td class='text-center'>" . ($row['fgstatus'] == 'O' ? '<span class="badge badge-warning status-badge">Open</span>' : '<span class="badge badge-success status-badge">Approved</span>') . "</td>";
                                                
                                                echo "<td class='text-right'>" . number_format($row['total_amount'], 2) . "</td>";
                                            
                                                echo "<td class='text-center'>
                                                        <button class='btn btn-info btn-sm' onclick='viewReturn(\"" . htmlspecialchars($row['returnno']) . "\")'>
                                                            <i class='fas fa-eye'></i>
                                                        </button>
                                                    </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='15' class='text-center py-4'>No data available</td></tr>";
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
        $("#salesReturnTable").DataTable({
            responsive: true,
            destroy: true
        });

        // Pilih/Deselect semua checkbox
        $("#select-all").on("click", function () {
            $(".select-return").prop("checked", this.checked);
        });

        // Tombol Approve
        $("#approve-selected, #approve-selected-footer").on("click", function () {
            processSelected('approve');
        });

        // Tombol Unapprove
        $("#unapprove-selected, #unapprove-selected-footer").on("click", function () {
            processSelected('unapprove');
        });

        function processSelected(action) {
            let selected = $(".select-return:checked").map(function () {
                return this.value;
            }).get();

            if (selected.length === 0) {
                Swal.fire("Warning", "Please select at least one Sales Return.", "warning");
                return;
            }

            const actionText = action === 'approve' ? 'Approve' : 'Unapprove';
            const actionColor = action === 'approve' ? '#28a745' : '#ffc107';
            const successText = action === 'approve' ? 'approved' : 'unapproved';

            Swal.fire({
                title: actionText + " " + selected.length + " Sales Return?",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: actionColor,
                cancelButtonColor: "#d33",
                confirmButtonText: "Ya, " + actionText + "!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "/gg_app/pages/sales_return/proses_approve.php",
                        type: "POST",
                        data: { 
                            'returnnos[]': selected,
                            'action': action
                        },
                        traditional: true,
                        success: function (response) {
                            Swal.fire("Berhasil!", "Sales Return berhasil " + successText + ".", "success").then(() => {
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

    function viewReturn(returnno) {
        window.location.href = "/gg_app/pages/sales_return/view_return.php?returnno=" + returnno;
    }
</script>



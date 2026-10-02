<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Pastikan user sudah login
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
// Pastikan koneksi tersedia
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Atur timezone ke Indonesia
date_default_timezone_set('Asia/Jakarta');

// Ambil nilai filter dan status dari URL atau session
$filter = isset($_GET['filter']) ? $_GET['filter'] : (isset($_SESSION['filter']) ? $_SESSION['filter'] : 'lokal');
$status = isset($_GET['status']) ? $_GET['status'] : 'O'; // Default to Open status
$_SESSION['filter'] = $filter;

// Cek apakah ada parameter sonmbr yang dikirim melalui GET
if (isset($_GET['sonmbr'])) {
    $sonmbr = $_GET['sonmbr'];

    // Query untuk mengambil Header Sales Order
    $query = "
        SELECT
            a.sonmbr, a.sodate, a.socuscode, a.socusname, a.sotoc, a.refnmbr, 
            c.currcode, a.sodesc, a.soorgid, e.soorgname, a.socusaddress,  
            d.wrhsname, a.fgstatus, a.approveby, a.approvedate, a.soppntype
        FROM insohd AS a
        LEFT JOIN smcurrency AS c ON a.socurrid = c.currid
        LEFT JOIN insoorg AS e ON a.soorgid = e.soorgid
        LEFT JOIN whwrhs AS d ON a.sowrhsid = d.wrhsid  
        WHERE a.sonmbr = :sonmbr
    ";
    $stmt = $conn3->prepare($query);
    $stmt->bindParam(':sonmbr', $sonmbr);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Query untuk mengambil Detail Sales Order
        $detailQuery = "
            SELECT
                insodt.soseq, insodt.prodcode, insodt.prodname, insodt.soqty, 
                smuom.uomcode, insodt.soprice, insodt.soqtyprice, insodt.sobruto,
                insodt.sodisctype, insodt.sopcdisc1, insodt.sodisc1,                 
                insodt.sonetto, insodt.sopcppn, insodt.soppn, insodt.prodtypename,
                insodt.upduser, smprodstruct.structname, insodt.fgstatus, insodt.sodesc
            FROM insohd
            LEFT JOIN insodt ON insohd.sohdid = insodt.sohdid
            LEFT JOIN smuom ON insodt.souomid = smuom.uomid
            LEFT JOIN smprodstruct ON insodt.prodstructid = smprodstruct.prodstructid
            WHERE insohd.sonmbr = :sonmbr
            ORDER BY insodt.soseq
        ";
        $detailStmt = $conn3->prepare($detailQuery);
        $detailStmt->bindParam(':sonmbr', $sonmbr);
        $detailStmt->execute();
        $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

        // Hitung total untuk summary
        $subTotal = 0;
        $bruto = 0;
        $netto = 0;
        $ppn = 0;
        $totalAmount = 0;

        foreach ($details as $detail) {
            $subTotal += $detail['soqtyprice'];
            $bruto += $detail['sobruto'];
            $netto += $detail['sonetto'];
            $ppn += $detail['soppn'];
        }

        $totalAmount = $netto + $ppn;

        // Define display values
        $ppnType = [
            'N' => 'None',
            'I' => 'Include',
            'E' => 'Exclude'
        ];

        $discountTypes = [
            'N' => 'None',
            'P' => 'Percentage',
            'V' => 'Value',
            'Q' => 'Per Qty'
        ];

        $ppnDisplay = $ppnType[$order['soppntype']] ?? 'Unknown';
?>

    <style>
        .status-badge {
            font-size: 1rem;
            padding: 0.5em 0.8em;
        }
        .summary-table {
            width: 50%;
            margin-left: auto;
        }
        .summary-table th {
            background-color: #f8f9fa;
        }
        .table-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .table-row {
            display: flex;
            flex: 1 1 calc(50% - 10px);
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .table-label {
            font-weight: bold;
            flex: 1;
        }
        .table-value {
            flex: 2;
            text-align: left;
        }
        @media (max-width: 768px) {
            .table-row {
                flex: 1 1 100%;
            }
            .summary-table {
                width: 100%;
            }
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-detail {
            min-width: 80px;
        }
    </style>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Detail Sales Order</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="/gg_app/pages/sales_order/so_approve.php?filter=<?= $filter ?>&status=<?= $status ?>">Sales Order</a></li>
                            <li class="breadcrumb-item active">Detail</li>
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
                                <h3 class="card-title">
                                    Detail Sales Order
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="table-container">
                                    <!-- Basic Order Info -->
                                    <div class="table-row">
                                        <div class="table-label">No Sales Order</div>
                                        <div class="table-value"><?= htmlspecialchars($order['sonmbr']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Status</div>
                                        <div class="table-value">
                                            <?php if ($order['fgstatus'] == 'V'): ?>
                                                <span class="badge badge-success">Approved</span>
                                            <?php elseif ($order['fgstatus'] == 'O'): ?>
                                                <span class="badge badge-warning">Open</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Organization and Date -->
                                    <div class="table-row">
                                        <div class="table-label">Sales Organization</div>
                                        <div class="table-value"><?= htmlspecialchars($order['soorgname']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Tanggal Sales Order</div>
                                        <div class="table-value"><?= !empty($order['sodate']) ? date('d/m/Y', strtotime($order['sodate'])) : '-' ?></div>
                                    </div>
                                    
                                    <!-- Customer Information -->
                                    <div class="table-row">
                                        <div class="table-label">Nama Customer</div>
                                        <div class="table-value"><?= htmlspecialchars($order['socusname']) ?> (<?= htmlspecialchars($order['socuscode']) ?>)</div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">TOC</div>
                                        <div class="table-value"><?= htmlspecialchars($order['sotoc']) ?></div>
                                    </div>
                                    
                                    <!-- Address and Warehouse -->
                                    <div class="table-row">
                                        <div class="table-label">Alamat Customer</div>
                                        <div class="table-value"><?= htmlspecialchars($order['socusaddress']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Gudang</div>
                                        <div class="table-value"><?= htmlspecialchars($order['wrhsname']) ?></div>
                                    </div>
                                    
                                    <!-- Financial Info -->
                                    <div class="table-row">
                                        <div class="table-label">Currency</div>
                                        <div class="table-value"><?= htmlspecialchars($order['currcode']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">No PO</div>
                                        <div class="table-value"><?= htmlspecialchars($order['refnmbr'] ?? '-') ?></div>
                                    </div>
                                    
                                    <!-- Tax and Description -->
                                    <div class="table-row">
                                        <div class="table-label">PPN Type</div>
                                        <div class="table-value"><?= htmlspecialchars($ppnDisplay) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Deskripsi</div>
                                        <div class="table-value"><?= htmlspecialchars($order['sodesc']) ?></div>
                                    </div>
                                    
                                    <!-- Approval Info (if approved) -->
                                    <?php if ($order['fgstatus'] == 'V'): ?>
                                    <div class="table-row">
                                        <div class="table-label">Approved By</div>
                                        <div class="table-value"><?= htmlspecialchars($order['approveby']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Approved Date</div>
                                        <div class="table-value"><?= !empty($order['approvedate']) ? date('d/m/Y H:i', strtotime($order['approvedate'])) : '-' ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4">
                                    <h4>Detail Items</h4>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>No</th>
                                                    <th>Product Code</th>
                                                    <th>Product Name</th>
                                                    <th>Qty</th>
                                                    <th>UOM</th>
                                                    <th>Unit Price</th>
                                                    <th>SubTotal</th>
                                                    <th>Disc Type</th>
                                                    <th>Disc %</th>
                                                    <th>Disc Value</th>
                                                    <th>Netto</th>
                                                    <th>PPN %</th>
                                                    <th>PPN Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($details as $index => $detail): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($detail['prodcode']) ?></td>
                                                    <td><?= htmlspecialchars($detail['prodname']) ?></td>
                                                    <td class="text-right"><?= number_format($detail['soqty'], 0) ?></td>
                                                    <td><?= htmlspecialchars($detail['uomcode']) ?></td>
                                                    <td class="text-right"><?= number_format($detail['soprice'], 2) ?></td>
                                                    <td class="text-right"><?= number_format($detail['soqtyprice'], 2) ?></td>
                                                    <td><?= $discountTypes[$detail['sodisctype']] ?? htmlspecialchars($detail['sodisctype']) ?></td>
                                                    <td class="text-right"><?= number_format($detail['sopcdisc1'], 2) ?></td>
                                                    <td class="text-right"><?= number_format($detail['sodisc1'], 2) ?></td>
                                                    <td class="text-right"><?= number_format($detail['sonetto'], 2) ?></td>
                                                    <td class="text-right"><?= number_format($detail['sopcppn'], 0) ?></td>
                                                    <td class="text-right"><?= number_format($detail['soppn'], 2) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h4>Summary</h4>
                                    <table class="table table-bordered summary-table">
                                        <tr>
                                            <th>SubTotal</th>
                                            <td class="text-right"><?= number_format($subTotal, 2) . ' ' . htmlspecialchars($order['currcode']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Bruto</th>
                                            <td class="text-right"><?= number_format($bruto, 2) . ' ' . htmlspecialchars($order['currcode']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Netto</th>
                                            <td class="text-right"><?= number_format($netto, 2) . ' ' . htmlspecialchars($order['currcode']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>PPN</th>
                                            <td class="text-right"><?= number_format($ppn, 2) . ' ' . htmlspecialchars($order['currcode']) ?></td>
                                        </tr>
                                        <tr class="table-active">
                                            <th><strong>Total Amount</strong></th>
                                            <td class="text-right"><strong><?= number_format($totalAmount, 2) . ' ' . htmlspecialchars($order['currcode']) ?></strong></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="action-buttons">
                                    <?php if ($order['fgstatus'] == 'O'): ?>
                                        <button type="button" class="btn btn-success" id="approve-btn">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    <?php elseif ($order['fgstatus'] == 'V'): ?>
                                        <button type="button" class="btn btn-warning" id="unapprove-btn">
                                            <i class="fas fa-times"></i> Unapprove
                                        </button>
                                    <?php endif; ?>
                                    <a href="/gg_app/pages/sales_order/so_approve.php?filter=<?= $filter ?>&status=<?= $status ?>" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
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
<!-- ===================================================
    12. JAVASCRIPT CUSTOM
======================================================= -->
<script>
$(document).ready(function() {
    // Approve button handler
    $('#approve-btn').click(function() {
        Swal.fire({
            title: 'Approve Sales Order?',
            text: 'Anda yakin ingin approve Sales Order <?= $order['sonmbr'] ?>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Approve!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/gg_app/pages/sales_order/proses_approve.php',
                    type: 'POST',
                    data: {
                        sonmbr: '<?= $order['sonmbr'] ?>',
                        action: 'approve',
                        filter: '<?= $filter ?>',
                        status: '<?= $status ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.href = '/gg_app/pages/sales_order/so_approve.php?filter=<?= $filter ?>&status=O';
                            });
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Terjadi kesalahan saat memproses permintaan.', 'error');
                    }
                });
            }
        });
    });

    // Unapprove button handler
    $('#unapprove-btn').click(function() {
        Swal.fire({
            title: 'Unapprove Sales Order?',
            text: 'Anda yakin ingin unapprove Sales Order <?= $order['sonmbr'] ?>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Unapprove!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/gg_app/pages/sales_order/proses_approve.php',
                    type: 'POST',
                    data: {
                        sonmbr: '<?= $order['sonmbr'] ?>',
                        action: 'unapprove',
                        filter: '<?= $filter ?>',
                        status: '<?= $status ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.href = '/gg_app/pages/sales_order/so_approve.php?filter=<?= $filter ?>&status=V';
                            });
                        } else {
                            Swal.fire('Error!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Terjadi kesalahan saat memproses permintaan.', 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php
    } else {
        echo "<div class='alert alert-danger'>Data tidak ditemukan.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Permintaan tidak valid.</div>";
}

?>
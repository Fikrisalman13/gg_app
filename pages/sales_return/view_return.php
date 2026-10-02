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

// Cek apakah ada parameter returnno yang dikirim melalui GET
if (isset($_GET['returnno'])) {
    $returnno = $_GET['returnno'];

    // Query untuk mengambil Header Sales Return
    $query = "
        SELECT
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
        FROM
            inreturnhd AS a
            LEFT JOIN smcustomer AS c ON a.cusid = c.cusid
            LEFT JOIN smcurrency AS d ON a.currid = d.currid
            LEFT JOIN insoorg AS e ON a.soorgid = e.soorgid
            LEFT JOIN whwrhs AS f ON a.wrhsid = f.wrhsid
        WHERE a.returnno = :returnno
    ";
    $stmt = $conn3->prepare($query);
    $stmt->bindParam(':returnno', $returnno);
    $stmt->execute();
    $return = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($return) {
        // Query untuk mengambil Detail Sales Return
        $detailQuery = "
            SELECT
                b.nettohc,
                b.ppnperc,
                b.ppnhc,
                b.totalamount,
                b.prodcode,
                b.prodname,
                b.qty,
                b.qtyreq,
                b.uomid,
                b.price,
                b.totalprice,
                smuom.uomcode 
            FROM
                inreturnhd
                AS A LEFT JOIN inreturndt AS b ON A.returnhdid = b.returnhdid
                LEFT JOIN smuom ON b.uomid = smuom.uomid
                        WHERE a.returnno = :returnno
                        ORDER BY b.prodcode
                    ";
        $detailStmt = $conn3->prepare($detailQuery);
        $detailStmt->bindParam(':returnno', $returnno);
        $detailStmt->execute();
        $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

        // Hitung total untuk summary
        $subTotal = 0;
        $netto = 0;
        $ppn = 0;
        $totalAmount = 0;

        foreach ($details as $detail) {
            $subTotal += (float)$detail['totalprice'];
            $netto += (float)$detail['nettohc'];
            $ppn += (float)$detail['ppnhc'];
        }

        $totalAmount = $netto + $ppn;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Sales Return</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
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
        .table-responsive {
            overflow-x: auto;
        }
        .table th, .table td {
            white-space: nowrap;
        }
        .bg-return {
            background-color: #f8d7da;
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
                        <h1 class="m-0 text-dark">Detail Sales Return</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="/gg_app/pages/sales_return/sr_approve.php?status=<?= $status ?>">Sales Return</a></li>
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
                                    Detail Sales Return
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="table-container">
                                    <!-- Basic Return Info -->
                                    <div class="table-row">
                                        <div class="table-label">No Sales Return</div>
                                        <div class="table-value"><?= htmlspecialchars($return['returnno']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Status</div>
                                        <div class="table-value">
                                            <?php if ($return['fgstatus'] == 'V'): ?>
                                                <span class="badge badge-success">Approved</span>
                                            <?php elseif ($return['fgstatus'] == 'O'): ?>
                                                <span class="badge badge-warning">Open</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Organization and Date -->
                                    <div class="table-row">
                                        <div class="table-label">Sales Organization</div>
                                        <div class="table-value"><?= htmlspecialchars($return['soorgname']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Tanggal Return</div>
                                        <div class="table-value"><?= !empty($return['returndate']) ? date('d/m/Y', strtotime($return['returndate'])) : '-' ?></div>
                                    </div>
                                    
                                    <!-- Customer Information -->
                                    <div class="table-row">
                                        <div class="table-label">Nama Customer</div>
                                        <div class="table-value"><?= htmlspecialchars($return['cusname']) ?> (<?= htmlspecialchars($return['cuscode']) ?>)</div>
                                    </div>
                                    
                                    <!-- Warehouse -->
                                    <div class="table-row">
                                        <div class="table-label">Gudang</div>
                                        <div class="table-value"><?= htmlspecialchars($return['wrhsname']) ?></div>
                                    </div>
                                    
                                    <!-- Financial Info -->
                                    <div class="table-row">
                                        <div class="table-label">Currency</div>
                                        <div class="table-value"><?= htmlspecialchars($return['currcode']) ?></div>
                                    </div>
                                    
                                    <!-- Description -->
                                    <div class="table-row">
                                        <div class="table-label">Deskripsi</div>
                                        <div class="table-value"><?= htmlspecialchars($return['description']) ?></div>
                                    </div>
                                    
                                    <!-- Approval Info (if approved) -->
                                    <?php if ($return['fgstatus'] == 'V'): ?>
                                    <div class="table-row">
                                        <div class="table-label">Approved By</div>
                                        <div class="table-value"><?= htmlspecialchars($return['approveby']) ?></div>
                                    </div>
                                    <div class="table-row">
                                        <div class="table-label">Approved Date</div>
                                        <div class="table-value"><?= !empty($return['approvedate']) ? date('d/m/Y H:i', strtotime($return['approvedate'])) : '-' ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4">
                                    <h4>Detail Items</h4>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            
                                                <tr>
                                                    <th>No</th>
                                                    <th>Product Code</th>
                                                    <th>Product Name</th>
                                                    <th>Qty</th>
                                                    <th>Qty Return</th>
                                                    <th>UOM</th>
                                                    <th>Unit Price</th>
                                                    <th>Total Price</th>
                                                    <th>Netto</th>
                                                    <th>PPN %</th>
                                                    <th>PPN Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($details) > 0): ?>
                                                    <?php foreach ($details as $index => $detail): ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($detail['prodcode']) ?></td>
                                                        <td><?= htmlspecialchars($detail['prodname']) ?></td>
                                                        <td class="text-right"><?= number_format($detail['qty'], 0) ?></td>
                                                        <td class="text-right"><?= number_format($detail['qtyreq'], 0) ?></td>
                                                        <td><?= htmlspecialchars($detail['uomid']) ?></td>
                                                        <td class="text-right"><?= number_format($detail['price'], 2) ?></td>
                                                        <td class="text-right"><?= number_format($detail['totalprice'], 2) ?></td>
                                                        <td class="text-right"><?= number_format($detail['nettohc'], 2) ?></td>
                                                        <td class="text-right"><?= number_format($detail['ppnperc'], 0) ?></td>
                                                        <td class="text-right"><?= number_format($detail['ppnhc'], 2) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center">No items found</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h4>Summary</h4>
                                    <table class="table table-bordered summary-table">
                                        <tr>
                                            <th>SubTotal</th>
                                            <td class="text-right"><?= number_format($subTotal, 2) . ' ' . htmlspecialchars($return['currcode']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>Netto</th>
                                            <td class="text-right"><?= number_format($netto, 2) . ' ' . htmlspecialchars($return['currcode']) ?></td>
                                        </tr>
                                        <tr>
                                            <th>PPN</th>
                                            <td class="text-right"><?= number_format($ppn, 2) . ' ' . htmlspecialchars($return['currcode']) ?></td>
                                        </tr>
                                        <tr class="table-active">
                                            <th><strong>Total Amount</strong></th>
                                            <td class="text-right"><strong><?= number_format($totalAmount, 2) . ' ' . htmlspecialchars($return['currcode']) ?></strong></td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="action-buttons">
                                    <?php if ($return['fgstatus'] == 'O'): ?>
                                        <button type="button" class="btn btn-success" id="approve-btn">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    <?php elseif ($return['fgstatus'] == 'V'): ?>
                                        <button type="button" class="btn btn-warning" id="unapprove-btn">
                                            <i class="fas fa-times"></i> Unapprove
                                        </button>
                                    <?php endif; ?>
                                    <a href="/gg_app/pages/sales_return/sr_approve.php?status=<?= $status ?>" class="btn btn-secondary">
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

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(document).ready(function() {
    // Approve button handler
    $('#approve-btn').click(function() {
        Swal.fire({
            title: 'Approve Sales Return?',
            text: 'Anda yakin ingin approve Sales Return <?= $return['returnno'] ?>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Approve!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/gg_app/pages/sales_return/proses_approve.php',
                    type: 'POST',
                    data: {
                        returnno: '<?= $return['returnno'] ?>',
                        action: 'approve',
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
                                window.location.href = '/gg_app/pages/sales_return/sr_approve.php?status=O';
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
            title: 'Unapprove Sales Return?',
            text: 'Anda yakin ingin unapprove Sales Return <?= $return['returnno'] ?>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Unapprove!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/gg_app/pages/sales_return/proses_approve.php',
                    type: 'POST',
                    data: {
                        returnno: '<?= $return['returnno'] ?>',
                        action: 'unapprove',
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
                                window.location.href = '/gg_app/pages/sales_return/sr_approve.php?status=V';
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
</body>
</html>
<?php
    } else {
        echo "<div class='alert alert-danger'>Data tidak ditemukan.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Permintaan tidak valid.</div>";
}
include '../../includes/footer.php';
?>
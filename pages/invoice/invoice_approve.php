<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

if (!$conn3) {
    die('Koneksi ke database gagal.');
}

date_default_timezone_set('Asia/Jakarta');

$query = "SELECT invcnmbr, invcdate, invccusname, fgstatus
         FROM ininvchd
         WHERE fgstatus = 'O'
         ORDER BY invcdate DESC, invcnmbr DESC";
$result = $conn3->query($query);
?>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Invoice</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Invoice</li>
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
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i> Daftar Invoice</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-inline mb-3">
                                    <button type="button" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm mr-2" id="approve-selected">
                                        <i class="fas fa-check-circle"></i> Approve Selected
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" onclick="location.reload()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table id="invoiceTable" class="table table-hover table-sm">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center"><input type="checkbox" id="select-all"></th>
                                                <th class="text-center">No</th>
                                                <th class="text-center">No Invoice</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-center">Customer Name</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($result && $result->rowCount() > 0) {
                                                $no = 1;
                                                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                    $invoiceNumber = htmlspecialchars((string)($row['invcnmbr'] ?? ''));
                                                    $invoiceDate = !empty($row['invcdate']) ? date('d/m/Y', strtotime($row['invcdate'])) : '';
                                                    $customerName = htmlspecialchars((string)($row['invccusname'] ?? ''));
                                                    $status = (string)($row['fgstatus'] ?? '');
                                                    $statusBadge = $status === 'O' ? '<span class="badge badge-warning">Open</span>' : ($status === 'V' ? '<span class="badge badge-success">Approved</span>' : '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>');

                                                    echo '<tr>';
                                                    echo '<td class="text-center"><input type="checkbox" class="select-invoice" value="' . $invoiceNumber . '"></td>';
                                                    echo '<td class="text-center">' . $no++ . '</td>';
                                                    echo '<td>' . $invoiceNumber . '</td>';
                                                    echo '<td class="text-center">' . htmlspecialchars($invoiceDate) . '</td>';
                                                    echo '<td>' . $customerName . '</td>';
                                                    echo '<td class="text-center">' . $statusBadge . '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="6" class="text-center">Tidak ada data yang tersedia</td></tr>';
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
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    $('#invoiceTable').DataTable({
        responsive: true,
        destroy: true
    });

    $('#select-all').on('click', function() {
        $('.select-invoice').prop('checked', this.checked);
    });

    $('#approve-selected').on('click', function() {
        const selected = $('.select-invoice:checked').map(function() {
            return this.value;
        }).get();

        if (selected.length === 0) {
            Swal.fire('Peringatan', 'Pilih minimal satu invoice.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Approve ' + selected.length + ' Invoice?',
            text: 'Status invoice akan berubah dari O ke V.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Approve!'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: '/gg_app/pages/invoice/proses_approve.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'approve',
                    invcnmbrs: selected
                },
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Berhasil!', response.message, 'success').then(() => {
                            location.reload();
                        });
                        return;
                    }

                    Swal.fire('Error!', response.message || 'Gagal memproses invoice.', 'error');
                },
                error: function(xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi kesalahan saat memproses permintaan.';
                    Swal.fire('Error!', message, 'error');
                }
            });
        });
    });
});
</script>


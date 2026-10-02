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

$balenmbr = trim((string)($_GET['balenmbr'] ?? ''));
$rows = [];
$allRows = [];

if ($balenmbr !== '') {
    $query = "SELECT
                h.balenmbr,
                h.refnmbr,
                d.batchno,
                d.lot AS lot_baledt,
                sb.lot AS lot_stockbatch,
                p.wrhsid,
                w.wrhsname,
                p.balehdid,
                p.baleprodid,
                CASE
                    WHEN d.lot IS NULL AND sb.lot IS NULL THEN 'TIDAK ADA DATA'
                    WHEN d.lot IS NULL OR sb.lot IS NULL THEN 'BEDA'
                    WHEN d.lot = sb.lot THEN 'SAMA'
                    ELSE 'BEDA'
                END AS keterangan
            FROM whbalehd h
            INNER JOIN whbaledt d
                ON h.balehdid = d.balehdid
            LEFT JOIN whbaleprod p
                ON h.balehdid = p.balehdid
                AND d.baleprodid = p.baleprodid
            LEFT JOIN whstockbatch sb
                ON d.batchno = sb.batchno
                AND p.wrhsid = sb.wrhsid
            LEFT JOIN whwrhs w
                ON p.wrhsid = w.wrhsid
            WHERE h.balenmbr = :balenmbr
            ORDER BY d.batchno, p.baleprodid";
    $stmt = $conn3->prepare($query);
    $stmt->execute([':balenmbr' => $balenmbr]);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rows = array_values(array_filter($allRows, static function ($row) {
        return ($row['keterangan'] ?? '') === 'BEDA';
    }));
}
?>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Cek Lot Beda dan Update Lot</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Update Lot</li>
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
                                <h3 class="card-title"><i class="fas fa-tags mr-1"></i> Update Lot</h3>
                            </div>
                            <div class="card-body">
                                <form method="get" class="form-inline mb-3">
                                    <label for="balenmbr" class="mr-2">Bale Number</label>
                                    <input type="text" class="form-control form-control-sm mr-2" id="balenmbr" name="balenmbr" value="<?php echo htmlspecialchars($balenmbr); ?>" required>
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm mr-2">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" onclick="location.href='/gg_app/pages/updatelot/updatelot.php'">
                                        <i class="fas fa-sync-alt"></i> Reset
                                    </button>
                                </form>

                                <?php if ($balenmbr !== '') : ?>
                                    <div class="form-inline mb-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm mr-2" id="sync-baledt-to-stockbatch">
                                            <i class="fas fa-arrow-right"></i> Samakan semua lot_baledt ke lot_stockbatch
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="sync-stockbatch-to-baledt">
                                            <i class="fas fa-arrow-left"></i> Samakan semua lot_stockbatch ke lot_baledt
                                        </button>
                                    </div>

                                    <?php if (count($allRows) > 0 && count($rows) === 0) : ?>
                                        <div class="alert alert-success">
                                            Semua sudah sama.
                                        </div>
                                    <?php endif; ?>

                                    <?php if (count($rows) > 0) : ?>
                                    <div class="table-responsive">
                                        <table id="lotTable" class="table table-hover table-sm">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="text-center">No</th>
                                                    <th class="text-center">balenmbr</th>
                                                    <th class="text-center">refnmbr</th>
                                                    <th class="text-center">batchno</th>
                                                    <th class="text-center">wrhsname</th>
                                                    <th class="text-center">lot_baledt</th>
                                                    <th class="text-center">lot_stockbatch</th>
                                                    <th class="text-center">keterangan</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (count($rows) > 0) : ?>
                                                <?php foreach ($rows as $index => $row) : ?>
                                                    <tr>
                                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars((string)($row['balenmbr'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($row['refnmbr'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($row['batchno'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($row['wrhsname'] ?? '')); ?></td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm lot-baledt" value="<?php echo htmlspecialchars((string)($row['lot_baledt'] ?? '')); ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm lot-stockbatch" value="<?php echo htmlspecialchars((string)($row['lot_stockbatch'] ?? '')); ?>">
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-<?php echo ($row['keterangan'] ?? '') === 'SAMA' ? 'success' : (($row['keterangan'] ?? '') === 'BEDA' ? 'warning' : 'secondary'); ?>">
                                                                <?php echo htmlspecialchars((string)($row['keterangan'] ?? '')); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button"
                                                                    class="btn btn-primary btn-sm save-row"
                                                                    data-balenmbr="<?php echo htmlspecialchars((string)($row['balenmbr'] ?? '')); ?>"
                                                                    data-balehdid="<?php echo htmlspecialchars((string)($row['balehdid'] ?? '')); ?>"
                                                                    data-baleprodid="<?php echo htmlspecialchars((string)($row['baleprodid'] ?? '')); ?>"
                                                                    data-batchno="<?php echo htmlspecialchars((string)($row['batchno'] ?? '')); ?>"
                                                                    data-wrhsid="<?php echo htmlspecialchars((string)($row['wrhsid'] ?? '')); ?>">
                                                                <i class="fas fa-save"></i> Save
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <tr><td colspan="9" class="text-center">Tidak ada data yang berbeda</td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
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
    $('#lotTable').DataTable({
        responsive: false,
        autoWidth: false,
        destroy: true
    });

    $(document).on('click', '.save-row', function() {
        const button = $(this);
        const row = button.closest('tr');

        $.ajax({
            url: '/gg_app/pages/updatelot/save_lot.php',
            type: 'POST',
            dataType: 'json',
            data: {
                balenmbr: button.data('balenmbr'),
                balehdid: button.data('balehdid'),
                baleprodid: button.data('baleprodid'),
                batchno: button.data('batchno'),
                wrhsid: button.data('wrhsid'),
                lot_baledt: row.find('.lot-baledt').val(),
                lot_stockbatch: row.find('.lot-stockbatch').val()
            },
            beforeSend: function() {
                button.prop('disabled', true);
            },
            complete: function() {
                button.prop('disabled', false);
            },
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire('Berhasil!', response.message, 'success').then(() => location.reload());
                    return;
                }

                Swal.fire('Error!', response.message || 'Gagal simpan data.', 'error');
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi kesalahan saat simpan.';
                Swal.fire('Error!', message, 'error');
            }
        });
    });

    function syncAll(direction) {
        Swal.fire({
            title: 'Sync semua row?',
            text: direction === 'baledt-to-stockbatch'
                ? 'lot_baledt akan disamakan ke lot_stockbatch untuk semua row.'
                : 'lot_stockbatch akan disamakan ke lot_baledt untuk semua row.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, sync!'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: '/gg_app/pages/updatelot/sync_all_lot.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    balenmbr: '<?php echo htmlspecialchars($balenmbr, ENT_QUOTES); ?>',
                    direction: direction
                },
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Berhasil!', response.message, 'success').then(() => location.reload());
                        return;
                    }

                    Swal.fire('Error!', response.message || 'Gagal sync data.', 'error');
                },
                error: function(xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi kesalahan saat sync.';
                    Swal.fire('Error!', message, 'error');
                }
            });
        });
    }

    $(document).on('click', '#sync-baledt-to-stockbatch', function() {
        syncAll('baledt-to-stockbatch');
    });

    $(document).on('click', '#sync-stockbatch-to-baledt', function() {
        syncAll('stockbatch-to-baledt');
    });
});
</script>

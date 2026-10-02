<?php
// delete_purchase.php - Hapus Purchase Order Beserta Dependensi
session_start();
ob_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ===== Auth & Permission =====
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$groupId = $_SESSION['GroupId'];
$menuId = 111;

$sqlPerm = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$perm = sqlsrv_query($conn, $sqlPerm, [$groupId, $menuId]);
$canDelete = ($perm && $row = sqlsrv_fetch_array($perm, SQLSRV_FETCH_ASSOC)) ? $row['CanDelete'] : 0;
if ($canDelete != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus purchase order.";
    header('Location: purchase_list.php');
    exit;
}

// Ambil purchase_id
$purchaseId = $_GET['id'] ?? '';
if (!$purchaseId) {
    $_SESSION['error'] = "Purchase ID tidak valid!";
    header('Location: purchase_list.php');
    exit;
}

// Ambil data purchase + vendor + padder
$sql = "SELECT p.*, v.vendor_name, pad.padder_id, pad.padder_name, pad.status as padder_status
        FROM pad_t_purchase p
        LEFT JOIN pad_m_vendor v ON p.vendor_id = v.vendor_id
        LEFT JOIN pad_m_padder pad ON p.padder_id = pad.padder_id
        WHERE p.id = ?";
$purchase = sqlsrv_query($conn, $sql, [$purchaseId]);
$purchase = sqlsrv_fetch_array($purchase, SQLSRV_FETCH_ASSOC);

if (!$purchase) {
    $_SESSION['error'] = "Data purchase tidak ditemukan!";
    header('Location: purchase_list.php');
    exit;
}

// Hitung jumlah receive terkait
$sqlReceive = "SELECT COUNT(*) as count FROM pad_t_receive WHERE padder_id = ?";
$rcv = sqlsrv_query($conn, $sqlReceive, [$purchase['padder_id']]);
$receiveCount = ($rcv && $row = sqlsrv_fetch_array($rcv, SQLSRV_FETCH_ASSOC)) ? $row['count'] : 0;

// Proses hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirmation'] ?? '') !== 'HAPUS') {
        $_SESSION['error'] = "Konfirmasi tidak valid! Ketik HAPUS.";
        header('Location: delete_purchase.php?id=' . $purchaseId);
        exit;
    }

    try {
        sqlsrv_begin_transaction($conn);

        if ($receiveCount > 0) {
            sqlsrv_query($conn, "DELETE FROM pad_t_receive_files WHERE receive_id IN (SELECT id FROM pad_t_receive WHERE padder_id = ?)", [$purchase['padder_id']]);
            sqlsrv_query($conn, "DELETE FROM pad_t_receive WHERE padder_id = ?", [$purchase['padder_id']]);
        }

        sqlsrv_query($conn, "DELETE FROM pad_t_purchase WHERE id = ?", [$purchaseId]);
        sqlsrv_query($conn, "UPDATE pad_m_padder SET status='DIBUAT' WHERE padder_id = ?", [$purchase['padder_id']]);
        sqlsrv_query($conn, "DELETE FROM pad_status_log WHERE padder_id = ? AND status='DIBELI'", [$purchase['padder_id']]);
        sqlsrv_query($conn, "DELETE FROM pad_status_log WHERE padder_id = ? AND status='READY'", [$purchase['padder_id']]);

        sqlsrv_commit($conn);

        $_SESSION['success'] = "Purchase Order berhasil dihapus! Padder dikembalikan ke DIBUAT.";
        header('Location: purchase_list.php');
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header('Location: delete_purchase.php?id=' . $purchaseId);
        exit;
    }
}

// Include layout
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1 class="text-danger"><i class="fas fa-trash-alt"></i> Hapus Purchase Order</h1>
    </div>

    <div class="content px-3">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Data PO akan dihapus permanen!
        </div>

        <form method="POST" id="deleteForm">
            <div class="card card-danger">
                <div class="card-header"><h3>Konfirmasi Penghapusan</h3></div>
                <div class="card-body">
                    <p><strong>Nomor PO:</strong> <?= htmlspecialchars($purchase['po_number']) ?></p>
                    <p><strong>Padder:</strong> <?= htmlspecialchars($purchase['padder_id'] . ' - ' . $purchase['padder_name']) ?></p>
                    <p><strong>Vendor:</strong> <?= htmlspecialchars($purchase['vendor_name']) ?></p>
                    <p><strong>Status Padder:</strong> <?= $purchase['padder_status'] ?></p>
                    <?php if ($receiveCount): ?>
                        <div class="alert alert-danger">Akan menghapus <?= $receiveCount ?> data penerimaan terkait!</div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Ketik <strong>HAPUS</strong> untuk konfirmasi:</label>
                        <input type="text" name="confirmation" id="confirmation" class="form-control" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-danger" id="deleteButton" disabled>HAPUS PERMANEN</button>
                    <a href="purchase_list.php" class="btn btn-secondary">BATAL</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function(){
    $('#confirmation').on('input', function(){
        $('#deleteButton').prop('disabled', $(this).val().toUpperCase() !== 'HAPUS');
    });

    $('#deleteForm').on('submit', function(e){
        e.preventDefault();
        Swal.fire({
            title: 'Hapus PO?',
            html: 'Tindakan ini tidak dapat dibatalkan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'YA, HAPUS',
            cancelButtonText: 'BATAL',
            reverseButtons: true
        }).then((result)=>{
            if(result.isConfirmed) $(this).off('submit').submit();
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

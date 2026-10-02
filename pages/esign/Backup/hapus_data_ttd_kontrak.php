<?php
session_start();
require_once '../../koneksi.php'; // Sesuaikan path ke file koneksi

if (isset($_GET['nik']) && !empty($_GET['nik'])) {
    $nik = $_GET['nik'];

    $sql_delete_ttd = "DELETE FROM dbo.tanda_tangan WHERE nik = ?";
    $sql_update_kontrak = "UPDATE dbo.kontrak_kerja SET status_tanda_tangan = 0 WHERE nik = ?";

    sqlsrv_begin_transaction($conn);

    try {
        // Hapus data tanda tangan
        $stmt_ttd = sqlsrv_query($conn, $sql_delete_ttd, array($nik));
        if (!$stmt_ttd) {
            throw new Exception("Gagal menghapus data tanda tangan.");
        }

        // Update status tanda tangan di kontrak_kerja
        $stmt_update = sqlsrv_query($conn, $sql_update_kontrak, array($nik));
        if (!$stmt_update) {
            throw new Exception("Gagal memperbarui status tanda tangan di kontrak_kerja.");
        }

        sqlsrv_commit($conn);

        $_SESSION['notif'] = [
            'status' => 'success',
            'message' => 'Data berhasil dihapus dan status tanda tangan diperbarui!'
        ];

        header("Location: /gg_app/pages/esign/data_ttd_kontrak.php");
        exit();
    } catch (Exception $e) {
        sqlsrv_rollback($conn);

        $_SESSION['notif'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];

        header("Location: /gg_app/pages/esign/data_ttd_kontrak.php");
        exit();
    }
} else {
    $_SESSION['notif'] = [
        'status' => 'error',
        'message' => 'Parameter NIK tidak valid!'
    ];
    header("Location: /gg_app/pages/esign/data_ttd_kontrak.php");
    exit();
}
?>

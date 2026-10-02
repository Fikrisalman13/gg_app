<?php
session_start();
require_once '../../koneksi.php'; // Sesuaikan path ke file koneksi

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

if (isset($_GET['nik']) && !empty($_GET['nik'])) {
    $nik = $_GET['nik'];
    $id = isset($_GET['id']) ? $_GET['id'] : null;

    if ($id) {
        $sql_delete_ttd = "DELETE FROM dbo.tanda_tangan WHERE nik = ?"; // Tanda tangan usually NIK based, but we reset status for specific contract
        $sql_update_kontrak = "UPDATE dbo.kontrak_kerja SET status_tanda_tangan = 0 WHERE id = ? AND nik = ?";
        $sql_update_perjanjian = "UPDATE dbo.kontrak_kerja_perjanjian SET file_pdf_signed = NULL WHERE id_kontrak = ?";
        $params_update_kontrak = array($id, $nik);
        $params_update_perjanjian = array($id);
    } else {
        $sql_delete_ttd = "DELETE FROM dbo.tanda_tangan WHERE nik = ?";
        $sql_update_kontrak = "UPDATE dbo.kontrak_kerja SET status_tanda_tangan = 0 WHERE nik = ?";
        $sql_update_perjanjian = "UPDATE dbo.kontrak_kerja_perjanjian SET file_pdf_signed = NULL WHERE id_kontrak IN (SELECT id FROM dbo.kontrak_kerja WHERE nik = ?)";
        $params_update_kontrak = array($nik);
        $params_update_perjanjian = array($nik);
    }

    sqlsrv_begin_transaction($conn);

    try {
        // Update status tanda tangan di kontrak_kerja
        $stmt_update = sqlsrv_query($conn, $sql_update_kontrak, $params_update_kontrak);
        if ($stmt_update === false) {
            throw new Exception("Gagal memperbarui status tanda tangan di kontrak_kerja.");
        }

        // Update kontrak_kerja_perjanjian (Hapus file_pdf_signed)
        $stmt_perjanjian = sqlsrv_query($conn, $sql_update_perjanjian, $params_update_perjanjian);
        if ($stmt_perjanjian === false) {
            throw new Exception("Gagal menghapus data di kontrak_kerja_perjanjian.");
        }

        // Tentukan apakah harus menghapus master tanda tangan
        if (!$id) {
            // Jika hapus dari dashboard (tanpa ID spesifik), hapus master ttd
            sqlsrv_query($conn, "DELETE FROM dbo.tanda_tangan WHERE nik = ?", array($nik));
        } else {
            // Jika hapus kontrak spesifik, cek apakah masih ada kontrak lain yang sudah ditandatangani
            $sql_check = "SELECT COUNT(*) as total FROM dbo.kontrak_kerja WHERE nik = ? AND status_tanda_tangan = '1'";
            $stmt_check = sqlsrv_query($conn, $sql_check, array($nik));
            if ($stmt_check && $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC)) {
                if ($row_check['total'] == 0) {
                    // Jika tidak ada lagi kontrak yang signed, hapus master ttd
                    sqlsrv_query($conn, "DELETE FROM dbo.tanda_tangan WHERE nik = ?", array($nik));
                }
            }
        }

        sqlsrv_commit($conn);

        if ($is_ajax) {
            echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus!']);
            exit();
        }

        $_SESSION['notif'] = [
            'status' => 'success',
            'message' => 'Data berhasil dihapus dan status tanda tangan diperbarui!'
        ];

        header("Location: /gg_app/pages/esign/data_ttd_staff.php");
        exit();
    } catch (Exception $e) {
        sqlsrv_rollback($conn);

        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }

        $_SESSION['notif'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];

        header("Location: /gg_app/pages/esign/data_ttd_staff.php");
        exit();
    }
} else {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Parameter NIK tidak valid!']);
        exit();
    }
    $_SESSION['notif'] = [
        'status' => 'error',
        'message' => 'Parameter NIK tidak valid!'
    ];
    header("Location: /gg_app/pages/esign/data_ttd_staff.php");
    exit();
}
?>

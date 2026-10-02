<?php
// delete_padder.php - Hapus Padder Beserta Semua Dependensi
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
$menuId = 111; // ID menu master padder

// Cek permission delete
function checkDeletePermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanDelete FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanDelete' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) $result = $row;
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $result;
}

$permissions = checkDeletePermission($conn, $groupId, $menuId);
if ($permissions['CanDelete'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk menghapus padder.";
    header('Location: master_padder.php');
    exit;
}

// Ambil padder_id dari URL
$padderId = $_GET['id'] ?? '';
if (empty($padderId)) {
    $_SESSION['error'] = "Padder ID tidak valid!";
    header('Location: master_padder.php');
    exit;
}

try {
    // Mulai transaction
    sqlsrv_begin_transaction($conn);

    // 1️⃣ Hapus spesifikasi padder
    sqlsrv_query($conn, "DELETE FROM pad_m_padder_spec WHERE padder_id = ?", [$padderId]);

    // 2️⃣ Hapus log status padder
    sqlsrv_query($conn, "DELETE FROM pad_status_log WHERE padder_id = ?", [$padderId]);

    // 3️⃣ Hapus transaksi atau dependensi lain (sesuaikan dengan tabel Anda)
    // Contoh: padder maintenance, produksi
    // sqlsrv_query($conn, "DELETE FROM pad_maintenance WHERE padder_id = ?", [$padderId]);
    // sqlsrv_query($conn, "DELETE FROM pad_production WHERE padder_id = ?", [$padderId]);

    // 4️⃣ Hapus padder
    $stmt = sqlsrv_query($conn, "DELETE FROM pad_m_padder WHERE padder_id = ?", [$padderId]);
    if ($stmt === false) {
        throw new Exception("Gagal menghapus padder. " . print_r(sqlsrv_errors(), true));
    }

    // Commit transaction
    sqlsrv_commit($conn);

    $_SESSION['success'] = "Padder $padderId berhasil dihapus beserta semua dependensinya!";
    header('Location: master_padder.php');
    exit;

} catch (Exception $e) {
    // Rollback jika terjadi error
    sqlsrv_rollback($conn);
    $_SESSION['error'] = "Terjadi kesalahan saat menghapus padder: " . $e->getMessage();
    header('Location: master_padder.php');
    exit;
}
?>

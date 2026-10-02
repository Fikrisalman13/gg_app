<?php
session_start();

// Set header untuk JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

// Check if ticket is provided
$ticket = $_POST['ticket'] ?? '';
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket is required for delete!']);
    exit;
}

/* ================================
   KONEKSI SQL SERVER TERPUSAT
================================ */
require __DIR__ . '/../../koneksi.php';

/* ================================
   DELETE DATA
================================ */
// Start transaction
sqlsrv_begin_transaction($conn);

// Hapus TTD terlebih dahulu (berbasis ticket yang sama)
$sqlDeleteTTD = "DELETE FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
$stmtTTD = sqlsrv_query($conn, $sqlDeleteTTD, [$ticket]);
if ($stmtTTD === false) {
    $error = sqlsrv_errors();
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data TTD: ' . print_r($error, true)]);
    exit;
}

// Tentukan sumber tabel (Perangkat IT, CCTV, atau Internet Access)
$isCCTV = stripos($ticket, 'CCTV-') === 0;
$isInternet = stripos($ticket, 'INET-') === 0;


$isEmail = stripos($ticket, 'EMAIL-') === 0;
$isApplication = stripos($ticket, 'APP-') === 0;
$applicationAttachment = null;
if ($isApplication) {
    $attachmentSql = "SELECT lampiran_nama_file
                      FROM Form_Pengajuan_Aplikasi
                      WHERE ticket = ?";
    $attachmentStatement = sqlsrv_query($conn, $attachmentSql, [$ticket]);
    if ($attachmentStatement === false) {
        sqlsrv_rollback($conn);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal membaca lampiran pengajuan.',
        ]);
        exit;
    }
    $attachmentRow = sqlsrv_fetch_array($attachmentStatement, SQLSRV_FETCH_ASSOC);
    if ($attachmentRow) {
        $applicationAttachment = basename(
            (string) ($attachmentRow['lampiran_nama_file'] ?? '')
        );
    }
    sqlsrv_free_stmt($attachmentStatement);
}
if (stripos($ticket, 'CCTV-P-') === 0) {
    $sql = "DELETE FROM dbo.Form_Pemindahan_CCTV WHERE ticket = ?";
} elseif ($isCCTV) {
    $sql = "DELETE FROM Form_Pengajuan_CCTV WHERE ticket = ?";
} elseif ($isInternet) {
    $sql = "DELETE FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
} elseif ($isEmail) {
    $sql = "DELETE FROM Form_Pengajuan_Email_Account WHERE ticket = ?";
} elseif (stripos($ticket, 'GRT-') === 0) {
    $sql = "DELETE FROM Form_Grant_Revoke_Trustee WHERE ticket = ?";
} elseif (stripos($ticket, 'DB-') === 0) {
    $sql = "DELETE FROM Form_Perubahan_Data_Database WHERE ticket = ?";
} elseif (stripos($ticket, 'CLS-') === 0) {
    $sql = "DELETE FROM Form_Buka_Tanggal_Closingan WHERE ticket = ?";
} elseif ($isApplication) {
    $sql = "DELETE FROM Form_Pengajuan_Aplikasi WHERE ticket = ?";
} elseif (stripos($ticket, 'GDG-') === 0) {
    $sql = "DELETE FROM dbo.Form_Penambahan_Gudang_Baru_ERP WHERE ticket = ?";
} else {
    $sql = "DELETE FROM Form_Pengajuan_Barang WHERE ticket = ?";
}

$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if ($stmt === false) {
    $error = sqlsrv_errors();
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data tiket: ' . print_r($error, true)]);
    exit;
}

$rowsAffected = sqlsrv_rows_affected($stmt);
if ($rowsAffected === 0) {
    // Jika bukan prefix CCTV/INET mungkin tiket berada di tabel CCTV tanpa prefix (fallback check)
    if (!$isCCTV && !$isInternet) {
        $sql2 = "DELETE FROM Form_Pengajuan_CCTV WHERE ticket = ?";
        $stmt2 = sqlsrv_query($conn, $sql2, [$ticket]);
        if ($stmt2 !== false && sqlsrv_rows_affected($stmt2) > 0) {
            // sukses melalui fallback
            sqlsrv_commit($conn);
            sqlsrv_free_stmt($stmtTTD);
            sqlsrv_free_stmt($stmt2);
            sqlsrv_close($conn);
            echo json_encode(['success' => true, 'message' => 'Data CCTV berhasil dihapus!', 'ticket' => $ticket]);
            exit;
        }
    }
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan atau sudah dihapus!']);
    exit;
}

// If we get here, commit the transaction
sqlsrv_commit($conn);
if ($applicationAttachment) {
    $attachmentPath = __DIR__
        . '/../../storage/form_it/pengajuan_aplikasi/'
        . $applicationAttachment;
    if (is_file($attachmentPath) && !unlink($attachmentPath)) {
        error_log('Lampiran pengajuan aplikasi gagal dihapus: ' . $ticket);
    }
}

// Clean up
sqlsrv_free_stmt($stmtTTD);
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus!', 'ticket' => $ticket]);
?>

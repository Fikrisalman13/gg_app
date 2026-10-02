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
   KONEKSI SQL SERVER
================================ */
$serverName = "202.150.136.51";
$connectionInfo = [
    "Database" => "GG",
    "UID" => "sa",
    "PWD" => "rahasiaIT2020",
    "TrustServerCertificate" => true
];

$conn = sqlsrv_connect($serverName, $connectionInfo);
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Koneksi gagal: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

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
if ($isCCTV) {
    $sql = "DELETE FROM Form_Pengajuan_CCTV WHERE ticket = ?";
} elseif ($isInternet) {
    $sql = "DELETE FROM Form_Pengajuan_Akses_Internet WHERE ticket = ?";
} elseif ($isEmail) {
    $sql = "DELETE FROM Form_Pengajuan_Email_Account WHERE ticket = ?";
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

// Clean up
sqlsrv_free_stmt($stmtTTD);
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus!', 'ticket' => $ticket]);
?>
<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__DIR__, 2) . '/koneksi.php';
}
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['success' => false, 'error' => 'Koneksi database gagal']);
    exit;
}

$ticket = $_GET['ticket'] ?? '';
if (empty($ticket)) {
    echo json_encode(['success' => false, 'error' => 'Ticket tidak valid']);
    exit;
}

$sql = "SELECT * FROM dbo.Form_Purchasing_COD WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Format date fields
    if ($row['tgl_receipt'] instanceof DateTime) {
        $row['tgl_receipt'] = $row['tgl_receipt']->format('Y-m-d');
    }
    if ($row['tgl_pengajuan'] instanceof DateTime) {
        $row['tgl_pengajuan'] = $row['tgl_pengajuan']->format('Y-m-d');
    }
    if ($row['due_date'] instanceof DateTime) {
        $row['due_date'] = $row['due_date']->format('Y-m-d');
    }
    if ($row['created_at'] instanceof DateTime) {
        $row['created_at'] = $row['created_at']->format('Y-m-d H:i:s');
    }

    echo json_encode(['success' => true, 'data' => $row]);
} else {
    echo json_encode(['success' => false, 'error' => 'Data tidak ditemukan']);
}

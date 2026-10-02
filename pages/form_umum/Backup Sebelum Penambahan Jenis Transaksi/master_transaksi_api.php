<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    echo json_encode(['success' => false, 'message' => 'File koneksi tidak ditemukan']);
    exit;
}
require_once $koneksiPath;
require_once __DIR__ . '/transaksi_master_helper.php';

if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

ensureTransaksiMasterTable($conn);
seedDefaultTransaksiMaster($conn, $_SESSION['NamaLengkap'] ?? $_SESSION['UserName']);

$action = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    $sql = "SELECT id, jenis, nama_transaksi, is_active, created_at, created_by, updated_at, updated_by
            FROM dbo.Form_Umum_Master_Transaksi_Closingan
            ORDER BY CASE WHEN jenis = 'Procurement' THEN 1 WHEN jenis = 'Sales' THEN 2 ELSE 3 END, nama_transaksi";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data', 'detail' => sqlsrv_errors()]);
        exit;
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = [
            'id' => (int)$row['id'],
            'jenis' => $row['jenis'],
            'nama_transaksi' => $row['nama_transaksi'],
            'is_active' => (int)$row['is_active'],
            'created_at' => ($row['created_at'] instanceof DateTime) ? $row['created_at']->format('Y-m-d H:i:s') : null,
            'created_by' => $row['created_by'],
            'updated_at' => ($row['updated_at'] instanceof DateTime) ? $row['updated_at']->format('Y-m-d H:i:s') : null,
            'updated_by' => $row['updated_by'],
        ];
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

if ($action === 'save') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $jenis = trim((string)($_POST['jenis'] ?? ''));
    $nama = trim((string)($_POST['nama_transaksi'] ?? ''));
    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $isActive = $isActive === 0 ? 0 : 1;
    $userName = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];

    if (!in_array($jenis, ['Procurement', 'Sales'], true)) {
        echo json_encode(['success' => false, 'message' => 'Jenis harus Procurement atau Sales']);
        exit;
    }
    if ($nama === '') {
        echo json_encode(['success' => false, 'message' => 'Nama transaksi wajib diisi']);
        exit;
    }

    if ($id > 0) {
        $checkSql = "SELECT id FROM dbo.Form_Umum_Master_Transaksi_Closingan
                     WHERE jenis = ? AND nama_transaksi = ? AND id <> ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$jenis, $nama, $id]);
        if ($checkStmt !== false && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            sqlsrv_free_stmt($checkStmt);
            echo json_encode(['success' => false, 'message' => 'Data transaksi sudah ada']);
            exit;
        }
        if ($checkStmt !== false) sqlsrv_free_stmt($checkStmt);

        $sql = "UPDATE dbo.Form_Umum_Master_Transaksi_Closingan
                SET jenis = ?, nama_transaksi = ?, is_active = ?, updated_at = GETDATE(), updated_by = ?
                WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$jenis, $nama, $isActive, $userName, $id]);
    } else {
        $checkSql = "SELECT id FROM dbo.Form_Umum_Master_Transaksi_Closingan
                     WHERE jenis = ? AND nama_transaksi = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, [$jenis, $nama]);
        if ($checkStmt !== false && sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            sqlsrv_free_stmt($checkStmt);
            echo json_encode(['success' => false, 'message' => 'Data transaksi sudah ada']);
            exit;
        }
        if ($checkStmt !== false) sqlsrv_free_stmt($checkStmt);

        $sql = "INSERT INTO dbo.Form_Umum_Master_Transaksi_Closingan
                (jenis, nama_transaksi, is_active, created_at, created_by)
                VALUES (?, ?, ?, GETDATE(), ?)";
        $stmt = sqlsrv_query($conn, $sql, [$jenis, $nama, $isActive, $userName]);
    }

    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data', 'detail' => sqlsrv_errors()]);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan']);
    exit;
}

if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    $stmt = sqlsrv_query($conn, "DELETE FROM dbo.Form_Umum_Master_Transaksi_Closingan WHERE id = ?", [$id]);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data', 'detail' => sqlsrv_errors()]);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);

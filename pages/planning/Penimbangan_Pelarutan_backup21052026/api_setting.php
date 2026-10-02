<?php
session_start();
// Ensure server uses Jakarta timezone for issue timestamps
date_default_timezone_set('Asia/Jakarta');
if (!isset($_SESSION['UserName'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../../koneksi.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$currUser = $_SESSION['UserName'] ?? 'SYSTEM';

function jsonExit($data)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if ($action === 'get_settings') {
    $settings = [];
    $stmt = sqlsrv_query($conn, "SELECT setting_code, setting_value, description, updated_at, updated_by FROM dbo.cpp_paddry_setting");
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $settings[$row['setting_code']] = [
                'value' => $row['setting_value'],
                'desc' => $row['description'],
                'updated_at' => $row['updated_at'] ? $row['updated_at']->format('Y-m-d H:i:s') : null,
                'updated_by' => $row['updated_by']
            ];
        }
    } else {
        jsonExit(['success' => false, 'message' => 'Gagal mengambil pengaturan.']);
    }
    jsonExit(['success' => true, 'data' => $settings]);
}

if ($action === 'save_settings') {
    $updates = $_POST['settings'] ?? [];
    if (empty($updates) || !is_array($updates)) {
        jsonExit(['success' => false, 'message' => 'Tidak ada data pengaturan yang dikirim.']);
    }

    sqlsrv_begin_transaction($conn);
    $success = true;
    foreach ($updates as $code => $val) {
        $sql = "UPDATE dbo.cpp_paddry_setting 
                SET setting_value = ?, updated_at = GETDATE(), updated_by = ? 
                WHERE setting_code = ?";
        $stmt = sqlsrv_query($conn, $sql, [(string) $val, $currUser, $code]);
        if ($stmt === false) {
            $success = false;
            break;
        }
    }

    if ($success) {
        sqlsrv_commit($conn);
        jsonExit(['success' => true, 'message' => 'Pengaturan berhasil disimpan.']);
    } else {
        sqlsrv_rollback($conn);
        jsonExit(['success' => false, 'message' => 'Gagal menyimpan pengaturan.']);
    }
}

jsonExit(['success' => false, 'message' => 'Aksi tidak dikenal.']);

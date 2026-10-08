<?php
// pages/resep_obat/update_resep_params.php
// API for partial update of Lab and Production parameters
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');

require_once __DIR__ . '/../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['UserName'];
$type     = $_POST['type']     ?? ''; // 'lab' or 'production'
$resep_id = $_POST['resep_id'] ?? '';
$now      = date('Y-m-d H:i:s');

if (empty($resep_id) || empty($type)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap.']);
    exit;
}

// ============================
// Resolve user role
// ============================
function getUserRole($conn, $username) {
    $sql = "SELECT g.role_type
            FROM dbo.resep_obat_group_members m
            INNER JOIN dbo.resep_obat_groups g ON m.group_id = g.id
            WHERE m.username = ?";
    $stmt = sqlsrv_query($conn, $sql, [$username]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['role_type']; // LAB or PRODUCTION
    }
    return null; // Admin / regular user
}

$userRole    = getUserRole($conn, $user);
$userGroupId = $_SESSION['GroupId'] ?? 0;
$isAdmin     = ($userGroupId == 1);

// ============================
// Access control
// ============================
// If not admin, verify they have the required role
if (!$isAdmin) {
    if ($userRole === null) {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses (User tidak terdaftar di Role Manager).']);
        exit;
    }
    if ($type === 'lab' && !in_array($userRole, ['LAB', 'BOTH'])) {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses untuk update Lab Data.']);
        exit;
    }
    if ($type === 'production' && !in_array($userRole, ['PRODUCTION', 'BOTH'])) {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses untuk update Production Data.']);
        exit;
    }
}

// ============================
// LAB Update
// ============================
if ($type === 'lab') {
    $nilai_l = $_POST['nilai_l'] ?? null;
    $nilai_a = $_POST['nilai_a'] ?? null;
    $nilai_b = $_POST['nilai_b'] ?? null;

    // Handle existing paths
    $lampiran_existing     = $_POST['lampiran_existing']     ?? '';
    $lampiran_pdf_existing = $_POST['lampiran_pdf_existing'] ?? '';
    $lampiran_path         = $lampiran_existing;
    $lampiran_pdf_path     = $lampiran_pdf_existing;

    // Handle image upload
    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['lampiran']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Upload gambar gagal (kode: ' . $_FILES['lampiran']['error'] . ')']);
            exit;
        }
        $origName = $_FILES['lampiran']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Format gambar tidak valid.']);
            exit;
        }
        $uploadDir    = realpath(__DIR__ . '/../../uploads') . DIRECTORY_SEPARATOR . 'resep_obat';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($origName, PATHINFO_FILENAME)) ?: 'lampiran';
        $newName  = 'lampiran_' . date('Ymd_His') . '_' . $safeBase . '.' . $ext;
        if (!move_uploaded_file($_FILES['lampiran']['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $newName)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file gambar.']);
            exit;
        }
        // Delete old
        if (!empty($lampiran_existing)) {
            $old = realpath(__DIR__ . '/' . str_replace('/gg_app/uploads/', '../../uploads/', $lampiran_existing));
            if ($old && is_file($old)) @unlink($old);
        }
        $lampiran_path = '/gg_app/uploads/resep_obat/' . $newName;
    }

    // Handle PDF upload
    if (isset($_FILES['lampiran_pdf']) && $_FILES['lampiran_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['lampiran_pdf']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Upload PDF gagal (kode: ' . $_FILES['lampiran_pdf']['error'] . ')']);
            exit;
        }
        $origName = $_FILES['lampiran_pdf']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            echo json_encode(['status' => 'error', 'message' => 'Format file harus PDF.']);
            exit;
        }
        $uploadDir = realpath(__DIR__ . '/../../uploads') . DIRECTORY_SEPARATOR . 'resep_obat';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($origName, PATHINFO_FILENAME)) ?: 'lampiran';
        $newName  = 'doc_' . date('Ymd_His') . '_' . $safeBase . '.pdf';
        if (!move_uploaded_file($_FILES['lampiran_pdf']['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $newName)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file PDF.']);
            exit;
        }
        // Delete old
        if (!empty($lampiran_pdf_existing)) {
            $old = realpath(__DIR__ . '/' . str_replace('/gg_app/uploads/', '../../uploads/', $lampiran_pdf_existing));
            if ($old && is_file($old)) @unlink($old);
        }
        $lampiran_pdf_path = '/gg_app/uploads/resep_obat/' . $newName;
    }

    $sql = "UPDATE dbo.resep_obat SET
                nilai_l          = ?,
                nilai_a          = ?,
                nilai_b          = ?,
                lampiran_path    = ?,
                lampiran_pdf_path = ?,
                updated_at       = ?,
                updated_by       = ?
            WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [
        $nilai_l !== '' ? (float)$nilai_l : null,
        $nilai_a !== '' ? (float)$nilai_a : null,
        $nilai_b !== '' ? (float)$nilai_b : null,
        $lampiran_path ?: null,
        $lampiran_pdf_path ?: null,
        $now, $user, $resep_id
    ]);

    if ($stmt === false) {
        $err = print_r(sqlsrv_errors(), true);
        echo json_encode(['status' => 'error', 'message' => 'Gagal update: ' . $err]);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'Lab Data berhasil disimpan.']);
    exit;
}

// ============================
// PRODUCTION Update
// ============================
if ($type === 'production') {
    $machinesJson = $_POST['machines'] ?? '[]';
    $machines = json_decode($machinesJson, true);
    
    if (!is_array($machines)) {
        echo json_encode(['status' => 'error', 'message' => 'Format data mesin tidak valid.']);
        exit;
    }

    if (sqlsrv_begin_transaction($conn) === false) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memulai transaksi DB.']);
        exit;
    }

    // 1. Delete existing machines for this recipe
    $sqlDel = "DELETE FROM dbo.resep_obat_machines WHERE id_resep = ?";
    $stmtDel = sqlsrv_query($conn, $sqlDel, [$resep_id]);

    if ($stmtDel === false) {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Gagal membersihkan data lama.']);
        exit;
    }

    // 2. Insert new machine entries
    $temperatureColumns = [];
    for ($ch = 2; $ch <= 12; $ch++) {
        $temperatureColumns[] = 'temperature_ch' . $ch;
    }

    $sqlIns = "INSERT INTO dbo.resep_obat_machines 
               (id_resep, machine_code, machine_name, speed, temperature, " . implode(', ', $temperatureColumns) . ", lebar_kain, created_at, created_by, update_at, update_by)
               VALUES (?, ?, ?, ?, ?" . str_repeat(', ?', count($temperatureColumns)) . ", ?, ?, ?, ?, ?)";
    
    foreach ($machines as $m) {
        $params = [
            $resep_id,
            ($m['machine_code'] ?? '') ?: null,
            ($m['machine_name'] ?? '') ?: null,
            (isset($m['speed']) && $m['speed'] !== '' && $m['speed'] !== null) ? (float)$m['speed'] : null,
            (isset($m['temp_ch1']) && $m['temp_ch1'] !== '' && $m['temp_ch1'] !== null) ? (float)$m['temp_ch1'] : null,
        ];

        for ($ch = 2; $ch <= 12; $ch++) {
            $key = 'temp_ch' . $ch;
            $params[] = (isset($m[$key]) && $m[$key] !== '' && $m[$key] !== null) ? (float)$m[$key] : null;
        }

        $params[] = (isset($m['lebar_kain']) && $m['lebar_kain'] !== '' && $m['lebar_kain'] !== null) ? (float)$m['lebar_kain'] : null;
        array_push($params, $now, $user, $now, $user);

        $stmtIns = sqlsrv_query($conn, $sqlIns, $params);

        if ($stmtIns === false) {
            sqlsrv_rollback($conn);
            $err = print_r(sqlsrv_errors(), true);
            echo json_encode(['status' => 'error', 'message' => 'Gagal simpan data mesin: ' . $err]);
            exit;
        }
    }

    // 3. Update header table metadata (cleanup old columns)
    $sqlHeader = "UPDATE dbo.resep_obat SET 
                  machine_code = NULL, machine_name = NULL, speed = NULL, temperature = NULL, temperature_ch2 = NULL, lebar_kain = NULL,
                  updated_at = ?, updated_by = ? 
                  WHERE id = ?";
    $stmtHeader = sqlsrv_query($conn, $sqlHeader, [$now, $user, $resep_id]);

    if ($stmtHeader === false) {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Gagal sinkronisasi metadata header.']);
        exit;
    }

    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Production Data berhasil disimpan (Total: ' . count($machines) . ' mesin).']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Tipe update tidak dikenali.']);

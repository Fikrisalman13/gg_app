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
    $requestId = bin2hex(random_bytes(8));
    $nilai_l = $_POST['nilai_l'] ?? null;
    $nilai_a = $_POST['nilai_a'] ?? null;
    $nilai_b = $_POST['nilai_b'] ?? null;
    $lampiran_existing = $_POST['lampiran_existing'] ?? '';
    $lampiran_pdf_existing = $_POST['lampiran_pdf_existing'] ?? '';
    $lampiran_path = $lampiran_existing;
    $lampiran_pdf_path = $lampiran_pdf_existing;
    $newFiles = [];
    $oldFiles = [];

    $uploadDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resep_obat';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        error_log("PPC attachment error request_id={$requestId} stage=create_directory");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Penyimpanan lampiran tidak tersedia.', 'request_id' => $requestId]);
        exit;
    }
    $uploadRoot = realpath($uploadDir);

    $resolveStoredFile = static function (string $storedPath) use ($uploadRoot): ?string {
        $prefix = '/gg_app/uploads/resep_obat/';
        if ($storedPath === '' || strpos($storedPath, $prefix) !== 0) {
            return null;
        }
        $filename = basename($storedPath);
        $resolved = realpath($uploadRoot . DIRECTORY_SEPARATOR . $filename);
        return $resolved && dirname($resolved) === $uploadRoot ? $resolved : null;
    };

    $storeUpload = static function (string $field, array $allowedMime, string $prefix) use ($uploadRoot, $requestId): ?array {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload gagal (kode: {$_FILES[$field]['error']}).");
        }
        if ((int) $_FILES[$field]['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Ukuran lampiran maksimal 5 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$field]['tmp_name']);
        if (!isset($allowedMime[$mime])) {
            throw new RuntimeException('Format lampiran tidak valid.');
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($_FILES[$field]['name'], PATHINFO_FILENAME)) ?: 'lampiran';
        $newName = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safeBase . '.' . $allowedMime[$mime];
        $target = $uploadRoot . DIRECTORY_SEPARATOR . $newName;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            error_log("PPC attachment error request_id={$requestId} stage=move_upload field={$field}");
            throw new RuntimeException('Gagal menyimpan lampiran.');
        }
        return ['path' => $target, 'url' => '/gg_app/uploads/resep_obat/' . $newName];
    };

    try {
        $image = $storeUpload('lampiran', [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'
        ], 'lampiran_');
        if ($image) {
            $newFiles[] = $image['path'];
            $oldFiles[] = $resolveStoredFile($lampiran_existing);
            $lampiran_path = $image['url'];
        }

        $pdf = $storeUpload('lampiran_pdf', ['application/pdf' => 'pdf'], 'doc_');
        if ($pdf) {
            $newFiles[] = $pdf['path'];
            $oldFiles[] = $resolveStoredFile($lampiran_pdf_existing);
            $lampiran_pdf_path = $pdf['url'];
        }
    } catch (RuntimeException $exception) {
        foreach ($newFiles as $newFile) {
            if (is_file($newFile)) unlink($newFile);
        }
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => $exception->getMessage(), 'request_id' => $requestId]);
        exit;
    }

    $sql = "UPDATE dbo.resep_obat_v2 SET
                nilai_l = ?, nilai_a = ?, nilai_b = ?, lampiran_path = ?, lampiran_pdf_path = ?,
                updated_at = ?, updated_by = ? WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [
        $nilai_l !== '' ? (float) $nilai_l : null,
        $nilai_a !== '' ? (float) $nilai_a : null,
        $nilai_b !== '' ? (float) $nilai_b : null,
        $lampiran_path ?: null,
        $lampiran_pdf_path ?: null,
        $now, $user, $resep_id
    ]);

    if ($stmt === false) {
        foreach ($newFiles as $newFile) {
            if (is_file($newFile)) unlink($newFile);
        }
        error_log("PPC attachment error request_id={$requestId} stage=database_update recipe_id=" . (int) $resep_id);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan Lab Data.', 'request_id' => $requestId]);
        exit;
    }

    foreach (array_filter($oldFiles) as $oldFile) {
        if (is_file($oldFile) && !unlink($oldFile)) {
            error_log("PPC attachment warning request_id={$requestId} stage=delete_old_file");
        }
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
    $sqlDel = "DELETE FROM dbo.resep_obat_machines_v2 WHERE id_resep = ?";
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

    $sqlIns = "INSERT INTO dbo.resep_obat_machines_v2 
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
    $sqlHeader = "UPDATE dbo.resep_obat_v2 SET 
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

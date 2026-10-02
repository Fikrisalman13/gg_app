<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = $_SESSION['UserName'] ?? null;
if (!$username) {
    echo json_encode(['status' => 'error', 'message' => 'User belum login']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_vendor     = trim($_POST['nama_vendor'] ?? '');
    $nama_pekerjaan  = trim($_POST['nama_pekerjaan'] ?? '');
    $no_kontrak      = trim($_POST['no_kontrak'] ?? '');
    $expire_date     = $_POST['expire_date'] ?? '';
    $email_reminder  = trim($_POST['email_reminder'] ?? '') ?: null;
    $no_whatsapp     = trim($_POST['no_whatsapp'] ?? '') ?: null;
    $keterangan      = $_POST['keterangan'] ?? null;
    $bagian_id       = $_POST['bagian_id'] ?? null;

    if (empty($nama_vendor) || empty($no_kontrak) || empty($expire_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Field wajib diisi!']);
        exit;
    }

    $uploadedFiles = [];
    if (!empty($_FILES['file']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/kontrak/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['file']['name'] as $key => $val) {
            $tmpName = $_FILES['file']['tmp_name'][$key];
            if ($_FILES['file']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($val, PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $allowed)) continue;

            $fileName = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $val);
            $dest = $uploadDir . $fileName;

            if (move_uploaded_file($tmpName, $dest)) {
                $uploadedFiles[] = $fileName;
            }
        }
    }

    if (empty($uploadedFiles)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal upload file atau file tidak valid']);
        exit;
    }

    $allFiles = implode(',', $uploadedFiles);

    $sql = "
    INSERT INTO dr_kontrak (
        nama_vendor, nama_pekerjaan, no_kontrak, expire_date,
        file_path, keterangan, email_reminder, no_whatsapp,
        bagian_id, created_by, createdate
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

    $params = [
        $nama_vendor, $nama_pekerjaan, $no_kontrak, $expire_date,
        $allFiles, $keterangan, $email_reminder, $no_whatsapp,
        $bagian_id, $username
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        if ($fileName) @unlink($uploadDir . $fileName);
        echo json_encode(['status' => 'error', 'message' => sqlsrv_errors()]);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Data berhasil disimpan']);
    }
}

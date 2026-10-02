<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../../koneksi.php';

// Cek session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = $_SESSION['UserName'] ?? null;
if (!$username) {
    echo json_encode(['status' => 'error', 'message' => 'User belum login']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_polisi       = trim($_POST['no_polisi'] ?? '');
    $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? '');
    $nama_kendaraan  = trim($_POST['nama_kendaraan'] ?? '');
    $nama_pemilik    = trim($_POST['nama_pemilik'] ?? '');
    $expire_date     = $_POST['expire_date'] ?? '';
    $keterangan      = $_POST['keterangan'] ?? null;
    $email_reminder  = $_POST['email_reminder'] ?? null;
    $no_whatsapp     = $_POST['no_whatsapp'] ?? null;
    $bagian_id       = $_POST['bagian_id'] ?? null;

    if (empty($no_polisi) || empty($jenis_kendaraan) || empty($expire_date)) {
        echo json_encode(['status' => 'error', 'message' => 'Field wajib diisi!']);
        exit;
    }

    $uploadedFiles = [];
    if (!empty($_FILES['file_kendaraan']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/kendaraan/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['file_kendaraan']['name'] as $key => $val) {
            $tmpName = $_FILES['file_kendaraan']['tmp_name'][$key];
            if ($_FILES['file_kendaraan']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($val, PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
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
    INSERT INTO dbo.dr_surat_kendaraan (
        no_polisi, jenis_kendaraan, nama_kendaraan, nama_pemilik,
        expire_date, file_kendaraan, keterangan, email_reminder,
        no_whatsapp, bagian_id, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $no_polisi, $jenis_kendaraan, $nama_kendaraan, $nama_pemilik,
        $expire_date, $allFiles, $keterangan, $email_reminder,
        $no_whatsapp, $bagian_id, $username
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        if ($fileName) @unlink($uploadDir . $fileName);
        echo json_encode(['status' => 'error', 'message' => sqlsrv_errors()]);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Data berhasil disimpan']);
    }
}

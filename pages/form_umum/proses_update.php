<?php
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

$ticket = $_POST['ticket'] ?? '';
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket is required for update!']);
    exit;
}

// Include database connection
$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['success' => false, 'message' => 'File koneksi database tidak ditemukan!']);
    exit;
}

if (!isset($conn) || $conn === false) {
    echo json_encode(['success' => false, 'message' => 'Koneksi ke database gagal!']);
    exit;
}

// Deteksi jenis tiket
$isClosing = stripos($ticket, 'CLS-') === 0;
$isIKP     = (stripos($ticket, 'IKP-') === 0 || stripos($ticket, 'IKS-') === 0);
$isIPC     = stripos($ticket, 'IPC-') === 0;

if ($isClosing) {
    handleUpdateBukaTanggalClosingan($conn, $ticket);
} elseif ($isIKP) {
    handleUpdateIzinKeluarPabrik($conn, $ticket);
} elseif ($isIPC) {
    handleUpdateIzinPulangCepat($conn, $ticket);
} else {
    echo json_encode(['success' => false, 'message' => 'Jenis tiket tidak dikenali!']);
    exit;
}

function iksTimeToMinutes($timeValue) {
    if ($timeValue instanceof DateTime) {
        return ((int) $timeValue->format('H')) * 60 + (int) $timeValue->format('i');
    }

    $timeValue = trim((string) $timeValue);
    if ($timeValue === '') {
        return null;
    }

    if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?/', $timeValue, $m)) {
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    return null;
}

function iksIsLateReturn($jamKembaliReal, $estimasiKembali) {
    $actualMinutes = iksTimeToMinutes($jamKembaliReal);
    $estimateMinutes = iksTimeToMinutes($estimasiKembali);

    if ($actualMinutes === null || $estimateMinutes === null) {
        return false;
    }

    return $actualMinutes > $estimateMinutes;
}

function handleUpdateBukaTanggalClosingan($conn, $ticket) {
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $bagian      = $_POST['area'] ?? '';
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    $gudangTransaksi = $_POST['gudang_transaksi'] ?? '';
    $updatedByName = $_SESSION['NamaLengkap'] ?? $nama;

    // Validasi Items JSON
    $items = json_decode($gudangTransaksi, true);
    if (!is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'Data item tidak valid atau kosong!']);
        exit;
    }
    if (count($items) === 0) {
        echo json_encode(['success' => false, 'message' => 'Minimal satu item harus diisi!']);
        exit;
    }

    require_once __DIR__ . '/transaksi_master_helper.php';
    $itemValidation = validateAllClosinganItems($items);
    if (!$itemValidation['valid']) {
        echo json_encode(['success' => false, 'message' => $itemValidation['message']]);
        exit;
    }

    // Ekstrak buka_tgl dari item pertama
    $bukaTgl = $items[0]['buka_tgl'] ?? '';
    if (empty($bukaTgl)) {
        echo json_encode(['success' => false, 'message' => 'Buka Tgl pada item pertama wajib diisi!']);
        exit;
    }

    $hasGudang = false;
    $hasTransaksi = false;
    $isRevisiHarga = false;
    foreach ($items as $item) {
        if (!empty($item['request_gudang'])) $hasGudang = true;
        if (!empty($item['request_transaksi'])) $hasTransaksi = true;
        if (strcasecmp(trim($item['transaksi'] ?? ''), 'Revisi Harga') === 0) $isRevisiHarga = true;
    }
    $jenisPengajuan = 'campuran';
    if ($hasGudang && !$hasTransaksi) $jenisPengajuan = 'gudang';
    elseif (!$hasGudang && $hasTransaksi) $jenisPengajuan = 'transaksi';

    // Nilai request_gudang dan request_transaksi dari semua item agar flow approval akurat
    $reqGudang    = $hasGudang ? 1 : 0;
    $reqTransaksi = $hasTransaksi ? 1 : 0;
    $vendorCust   = $items[0]['vendor_cust'] ?? '';
    $keterangan   = $items[0]['keterangan'] ?? '';

    // Format tanggal pengajuan (DD-MM-YYYY to YYYY-MM-DD)
    if (!empty($tgl_form) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tgl_form)) {
        $parts = explode('-', $tgl_form);
        $tgl_form = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } elseif (empty($tgl_form) || $tgl_form == 'DD-MM-YYYY') {
        $tgl_form = date('Y-m-d');
    }

    // Format buka_tgl
    if (!empty($bukaTgl) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bukaTgl)) {
        $buka_tgl_sql = $bukaTgl;
    } else {
        $parts = explode('-', $bukaTgl);
        if (count($parts) == 3 && strlen($parts[0]) == 2) {
            $buka_tgl_sql = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        } else {
            $buka_tgl_sql = $bukaTgl;
        }
    }

    // --- Handle File Upload (Opsional saat update) ---
    $attachmentFilename = null;
    $attachmentPath = null;
    $attachmentSize = null;
    $attachmentUploadedAt = null;
    $shouldUpdateAttachment = false;

    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['lampiran'];
        
        // Validasi ukuran file (50MB)
        $maxSize = 50 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            echo json_encode([
                'success' => false,
                'message' => 'Ukuran file terlalu besar. Maksimal 50MB.'
            ]);
            exit;
        }
        
        // Validasi tipe file menggunakan finfo
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        // finfo_close is deprecated in PHP 8.5+ (auto-closed when variable goes out of scope)
        
        // Fallback: check extension if MIME type is not reliable
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];
        
        if (!in_array($mimeType, $allowedMimes) && !in_array($extension, $allowedExtensions)) {
            echo json_encode([
                'success' => false,
                'message' => 'Tipe file tidak diizinkan. Gunakan PDF, JPG, PNG, DOCX, atau XLSX.'
            ]);
            exit;
        }
        
        // Keep original filename
        $originalFilename = basename($file['name']);
        
        // Create upload directory if not exists
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/form_closing/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal membuat direktori upload.'
                ]);
                exit;
            }
        }
        
        // Move uploaded file
        $targetPath = $uploadDir . $originalFilename;
        
        // Handle duplicate filenames by adding a number suffix
        $counter = 1;
        $fileNameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
        $fileExt = pathinfo($originalFilename, PATHINFO_EXTENSION);
        while (file_exists($targetPath)) {
            $originalFilename = $fileNameWithoutExt . '_' . $counter . '.' . $fileExt;
            $targetPath = $uploadDir . $originalFilename;
            $counter++;
        }
        
        // Get existing attachment path to delete old file
        $sqlOld = "SELECT attachment_path FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?";
        $stmtOld = sqlsrv_query($conn, $sqlOld, [$ticket]);
        if ($stmtOld !== false) {
            $rowOld = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC);
            if ($rowOld && !empty($rowOld['attachment_path'])) {
                $oldFileFullPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/' . $rowOld['attachment_path'];
                if (file_exists($oldFileFullPath) && is_file($oldFileFullPath)) {
                    @unlink($oldFileFullPath);
                }
            }
            sqlsrv_free_stmt($stmtOld);
        }

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachmentFilename = $originalFilename;
            $attachmentPath = 'uploads/form_closing/' . $originalFilename;
            $attachmentSize = $file['size'];
            $attachmentUploadedAt = date('Y-m-d H:i:s');
            $shouldUpdateAttachment = true;
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengupload file.'
            ]);
            exit;
        }
    } elseif (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize di php.ini).',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE di form).',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP.'
        ];
        $errorCode = $_FILES['lampiran']['error'];
        $errorMsg = $errorMessages[$errorCode] ?? 'Error upload file tidak diketahui.';
        echo json_encode([
            'success' => false,
            'message' => $errorMsg
        ]);
        exit;
    }

    if ($shouldUpdateAttachment) {
        $sql = "UPDATE Form_Umum_Buka_Tanggal_Closingan SET
            nama_pemohon = ?,
            jabatan = ?,
            departemen = ?,
            bagian = ?,
            tgl_pengajuan = ?,
            request_gudang = ?,
            request_transaksi = ?,
            buka_tgl = ?,
            gudang_transaksi = ?,
            vendor_cust = ?,
            keterangan = ?,
            jenis_pengajuan = ?,
            is_revisi_harga = ?,
            attachment_filename = ?,
            attachment_path = ?,
            attachment_size = ?,
            attachment_uploaded_at = ?,
            updated_at = GETDATE(),
            updated_by = ?
        WHERE ticket = ?";
        
        $params = [
            $nama, $jab, $dept, $bagian, $tgl_form,
            $reqGudang, $reqTransaksi, $buka_tgl_sql,
            $gudangTransaksi,
            $vendorCust,
            $keterangan,
            $jenisPengajuan,
            $isRevisiHarga ? 1 : 0,
            $attachmentFilename,
            $attachmentPath,
            $attachmentSize,
            $attachmentUploadedAt,
            $updatedByName,
            $ticket
        ];
    } else {
        $sql = "UPDATE Form_Umum_Buka_Tanggal_Closingan SET
            nama_pemohon = ?,
            jabatan = ?,
            departemen = ?,
            bagian = ?,
            tgl_pengajuan = ?,
            request_gudang = ?,
            request_transaksi = ?,
            buka_tgl = ?,
            gudang_transaksi = ?,
            vendor_cust = ?,
            keterangan = ?,
            jenis_pengajuan = ?,
            is_revisi_harga = ?,
            updated_at = GETDATE(),
            updated_by = ?
        WHERE ticket = ?";
        
        $params = [
            $nama, $jab, $dept, $bagian, $tgl_form,
            $reqGudang, $reqTransaksi, $buka_tgl_sql,
            $gudangTransaksi,
            $vendorCust,
            $keterangan,
            $jenisPengajuan,
            $isRevisiHarga ? 1 : 0,
            $updatedByName,
            $ticket
        ];
    }
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal update Buka Closingan: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    
    sqlsrv_free_stmt($stmt);
    
    echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui!', 'ticket' => $ticket]);
}

function handleUpdateIzinKeluarPabrik($conn, $ticket) {
    $groupId      = $_SESSION['GroupId'] ?? 0;
    $updatedBy    = $_SESSION['NamaLengkap'] ?? '';
    $updateAction = $_POST['update_action'] ?? '';

    // ── Partial update: catatan_satpam ─────────────────────────────────────────
    if ($updateAction === 'catatan_satpam') {
        $catatanSatpam = $_POST['catatan_satpam'] ?? null;
        if ($catatanSatpam === '') $catatanSatpam = null;

        $sql = "UPDATE Form_Umum_Izin_Keluar_Pabrik
                SET catatan_satpam = ?, updated_at = GETDATE(), updated_by = ?
                WHERE ticket = ?";
        $stmt = sqlsrv_query($conn, $sql, [$catatanSatpam, $updatedBy, $ticket]);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan catatan SATPAM: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }
        sqlsrv_free_stmt($stmt);
        echo json_encode(['success' => true, 'message' => 'Catatan SATPAM berhasil disimpan!', 'ticket' => $ticket]);
        return;
    }

    // ── Partial update: scan_keluar (Security mencatat jam keluar nyata) ───────
    if ($updateAction === 'scan_keluar') {
        $jamKeluar = date('H:i');
        $sql = "UPDATE Form_Umum_Izin_Keluar_Pabrik
                SET jam_keluar_real = ?, status_kembali = 'Sedang Keluar',
                    updated_at = GETDATE(), updated_by = ?
                WHERE ticket = ? AND jam_keluar_real IS NULL";
        $stmt = sqlsrv_query($conn, $sql, [$jamKeluar, $updatedBy, $ticket]);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Gagal mencatat jam keluar: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }
        sqlsrv_free_stmt($stmt);
        echo json_encode(['success' => true, 'jam_keluar_real' => $jamKeluar, 'status_kembali' => 'Sedang Keluar', 'ticket' => $ticket]);
        return;
    }

    // ── Partial update: scan_kembali (Security mencatat jam kembali nyata) ─────
    if ($updateAction === 'scan_kembali') {
        // Ambil estimasi_kembali untuk cek apakah terlambat
        $sqlGet = "SELECT TOP 1 estimasi_kembali, jam_keluar_real FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
        $stmtGet = sqlsrv_query($conn, $sqlGet, [$ticket]);
        $rowGet = $stmtGet ? sqlsrv_fetch_array($stmtGet, SQLSRV_FETCH_ASSOC) : null;
        if ($stmtGet) sqlsrv_free_stmt($stmtGet);

        if (!$rowGet || empty($rowGet['jam_keluar_real'])) {
            echo json_encode(['success' => false, 'message' => 'Belum ada catatan jam keluar. Scan keluar dulu!']);
            exit;
        }

        $jamKembali = date('H:i');
        $estimasi   = $rowGet['estimasi_kembali'] ?? '';
        $statusKembali = iksIsLateReturn($jamKembali, $estimasi) ? 'Terlambat Kembali' : 'Sudah Kembali';

        $sql = "UPDATE Form_Umum_Izin_Keluar_Pabrik
                SET jam_kembali_real = ?, status_kembali = ?,
                    updated_at = GETDATE(), updated_by = ?
                WHERE ticket = ? AND jam_kembali_real IS NULL";
        $stmt = sqlsrv_query($conn, $sql, [$jamKembali, $statusKembali, $updatedBy, $ticket]);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Gagal mencatat jam kembali: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }
        sqlsrv_free_stmt($stmt);
        echo json_encode(['success' => true, 'jam_kembali_real' => $jamKembali, 'status_kembali' => $statusKembali, 'ticket' => $ticket]);
        return;
    }

    // ── Full form update (IKS fields) ──────────────────────────────────────────
    // Verifikasi izin CanEdit
    $sqlPerm  = "SELECT CanEdit FROM SMGroupTrustee WHERE GroupId = ? AND MenuId = 1293";
    $stmtPerm = sqlsrv_query($conn, $sqlPerm, [$groupId]);
    $rowPerm  = $stmtPerm ? sqlsrv_fetch_array($stmtPerm, SQLSRV_FETCH_ASSOC) : null;
    if ($stmtPerm) sqlsrv_free_stmt($stmtPerm);
    if ($groupId != 1 && (!$rowPerm || !$rowPerm['CanEdit'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengubah data ini.']);
        exit;
    }

    $nik              = trim($_POST['nik'] ?? '');
    $namaPemohon      = trim($_POST['nama_pemohon'] ?? '');
    $departemen       = trim($_POST['departemen'] ?? '');
    $bagian           = trim($_POST['bagian'] ?? '') ?: null;
    $jabatan          = trim($_POST['jabatan'] ?? '') ?: null;
    $hari             = trim($_POST['hari'] ?? '') ?: null;
    $tglPengajuan     = trim($_POST['tgl_pengajuan'] ?? '');
    $tglKeluar        = trim($_POST['tgl_keluar'] ?? '') ?: null;
    $jamKeluar        = trim($_POST['jam_keluar'] ?? '');
    $estimasiKembali  = trim($_POST['estimasi_kembali'] ?? '');
    $tujuan           = trim($_POST['tujuan'] ?? '') ?: null;
    $keperluan        = trim($_POST['keperluan'] ?? '');
    $keperluanLain    = trim($_POST['keperluan_lain'] ?? '') ?: null;
    $noHp             = trim($_POST['no_hp'] ?? '');
    $kendaraan        = trim($_POST['kendaraan'] ?? '');
    $kendaraanLain    = trim($_POST['kendaraan_lain'] ?? '') ?: null;

    $employees = [];
    $employeesJson = trim($_POST['employees_json'] ?? '');
    if ($employeesJson !== '') {
        $decodedEmployees = json_decode($employeesJson, true);
        if (!is_array($decodedEmployees)) {
            echo json_encode(['success' => false, 'message' => 'Data karyawan tidak valid.']);
            exit;
        }
        foreach ($decodedEmployees as $employee) {
            if (!is_array($employee)) continue;
            $employee = array_map(static fn($value) => trim((string) $value), $employee);
            if (($employee['nik'] ?? '') === '' || ($employee['nama_lengkap'] ?? '') === '') {
                echo json_encode(['success' => false, 'message' => 'NIK dan nama setiap karyawan wajib diisi.']);
                exit;
            }
            $employees[] = [
                'nik' => $employee['nik'], 'nama_pemohon' => $employee['nama_lengkap'],
                'departemen' => $employee['dept'] ?? '', 'bagian' => $employee['bagian'] ?? '',
                'jabatan' => $employee['jabatan'] ?? '', 'no_hp' => $employee['telp'] ?? '',
            ];
        }
    }
    if (!$employees && $nik !== '' && $namaPemohon !== '') {
        $employees[] = compact('nik', 'namaPemohon', 'departemen', 'bagian', 'jabatan', 'noHp');
        $employees[0]['nama_pemohon'] = $employees[0]['namaPemohon'];
        unset($employees[0]['namaPemohon']);
        $employees[0]['no_hp'] = $employees[0]['noHp'];
        unset($employees[0]['noHp']);
    }
    if (!$employees) {
        echo json_encode(['success' => false, 'message' => 'Minimal satu karyawan wajib dipilih.']);
        exit;
    }
    $niks = array_column($employees, 'nik');
    if (count($niks) !== count(array_unique($niks))) {
        echo json_encode(['success' => false, 'message' => 'Karyawan yang sama tidak boleh dipilih dua kali.']);
        exit;
    }
    $firstEmployee = $employees[0];
    $nik = $firstEmployee['nik']; $namaPemohon = $firstEmployee['nama_pemohon'];
    $departemen = $firstEmployee['departemen']; $bagian = $firstEmployee['bagian'];
    $jabatan = $firstEmployee['jabatan']; $noHp = $firstEmployee['no_hp'];

    // Handle file upload (lampiran) - Opsional saat update
    $attachmentFilename = null;
    $attachmentPath = null;
    $attachmentSize = null;
    $attachmentUploadedAt = null;
    $shouldUpdateAttachment = false;

    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['lampiran'];
        
        // Validate file size (50MB)
        $maxSize = 50 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            echo json_encode([
                'success' => false,
                'message' => 'Ukuran file terlalu besar. Maksimal 50MB.'
            ]);
            exit;
        }
        
        // Validate MIME type
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        $mimeType = mime_content_type($file['tmp_name']);
        
        // Fallback: check extension if MIME type is not reliable
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];
        
        if (!in_array($mimeType, $allowedMimes) && !in_array($extension, $allowedExtensions)) {
            echo json_encode([
                'success' => false,
                'message' => 'Tipe file tidak diizinkan. Gunakan PDF, JPG, PNG, DOCX, atau XLSX.'
            ]);
            exit;
        }
        
        // Keep original filename
        $originalFilename = basename($file['name']);
        
        // Create upload directory if not exists
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/form_izin_keluar/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal membuat direktori upload.'
                ]);
                exit;
            }
        }
        
        // Move uploaded file
        $targetPath = $uploadDir . $originalFilename;
        
        // Handle duplicate filenames by adding a number suffix
        $counter = 1;
        $fileNameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
        $fileExt = pathinfo($originalFilename, PATHINFO_EXTENSION);
        while (file_exists($targetPath)) {
            $originalFilename = $fileNameWithoutExt . '_' . $counter . '.' . $fileExt;
            $targetPath = $uploadDir . $originalFilename;
            $counter++;
        }
        
        // Get existing attachment path to delete old file
        $sqlOld = "SELECT attachment_path FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
        $stmtOld = sqlsrv_query($conn, $sqlOld, [$ticket]);
        if ($stmtOld !== false) {
            $rowOld = sqlsrv_fetch_array($stmtOld, SQLSRV_FETCH_ASSOC);
            if ($rowOld && !empty($rowOld['attachment_path'])) {
                $oldFileFullPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/' . $rowOld['attachment_path'];
                if (file_exists($oldFileFullPath) && is_file($oldFileFullPath)) {
                    @unlink($oldFileFullPath);
                }
            }
            sqlsrv_free_stmt($stmtOld);
        }

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachmentFilename = $originalFilename;
            $attachmentPath = 'uploads/form_izin_keluar/' . $originalFilename;
            $attachmentSize = $file['size'];
            $attachmentUploadedAt = date('Y-m-d H:i:s');
            $shouldUpdateAttachment = true;
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengupload file.'
            ]);
            exit;
        }
    } elseif (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize di php.ini).',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE di form).',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP.'
        ];
        $errorCode = $_FILES['lampiran']['error'];
        $errorMsg = $errorMessages[$errorCode] ?? 'Error upload file tidak diketahui.';
        echo json_encode([
            'success' => false,
            'message' => $errorMsg
        ]);
        exit;
    }

    // Validasi
    if (empty($nik) || empty($namaPemohon) || empty($departemen) || empty($jamKeluar) || empty($estimasiKembali) || empty($keperluan) || empty($kendaraan)) {
        echo json_encode(['success' => false, 'message' => 'Field wajib tidak boleh kosong.']);
        exit;
    }
    if ($estimasiKembali <= $jamKeluar) {
        echo json_encode(['success' => false, 'message' => 'Estimasi Kembali harus lebih besar dari Jam Keluar.']);
        exit;
    }

    // Normalisasi tanggal
    if (!empty($tglPengajuan) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tglPengajuan)) {
        $p = explode('-', $tglPengajuan);
        $tglPengajuan = $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (!empty($tglKeluar) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tglKeluar)) {
        $p = explode('-', $tglKeluar);
        $tglKeluar = $p[2] . '-' . $p[1] . '-' . $p[0];
    }

    $tipeKeluar = (strcasecmp($keperluan, 'Dinas Perusahaan') === 0) ? 'Dinas' : 'Pribadi';
    $alasanKeperluan = ($keperluan === 'Lainnya') ? $keperluanLain : $keperluan;
    $alasan = "[Keperluan: " . $alasanKeperluan . "] [Tujuan: " . $tujuan . "]";

    // Verifikasi tiket ada
    $stmtCheck = sqlsrv_query($conn, "SELECT TOP 1 ticket FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?", [$ticket]);
    if (!$stmtCheck || !sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
        if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);
        echo json_encode(['success' => false, 'message' => 'Tiket tidak ditemukan.']);
        exit;
    }
    sqlsrv_free_stmt($stmtCheck);

    $sql = "UPDATE Form_Umum_Izin_Keluar_Pabrik SET
                tipe_keluar       = ?,
                hari              = ?,
                tgl_pengajuan     = ?,
                nama_pemohon      = ?,
                departemen        = ?,
                bagian            = ?,
                jam_keluar_dari   = ?,
                jam_keluar_sampai = ?,
                alasan            = ?,
                nik               = ?,
                jabatan           = ?,
                tgl_keluar        = ?,
                jam_keluar        = ?,
                estimasi_kembali  = ?,
                tujuan            = ?,
                keperluan         = ?,
                keperluan_lain    = ?,
                no_hp             = ?,
                kendaraan         = ?,
                kendaraan_lain    = ?";
    
    // Conditionally add attachment fields if file was uploaded
    if ($shouldUpdateAttachment) {
        $sql .= ",
                attachment_filename = ?,
                attachment_path = ?,
                attachment_size = ?,
                attachment_uploaded_at = ?";
    }
    
    $sql .= ",
                updated_at        = GETDATE(),
                updated_by        = ?
            WHERE ticket = ?";

    $params = [
        $tipeKeluar, $hari, $tglPengajuan, $namaPemohon, $departemen, $bagian,
        $jamKeluar, $estimasiKembali, $alasan,
        $nik, $jabatan, $tglKeluar, $jamKeluar, $estimasiKembali, $tujuan,
        $keperluan, ($keperluan === 'Lainnya' ? $keperluanLain : null),
        $noHp, $kendaraan, ($kendaraan === 'Lainnya' ? $kendaraanLain : null)
    ];
    
    // Add attachment params if file was uploaded
    if ($shouldUpdateAttachment) {
        $params[] = $attachmentFilename;
        $params[] = $attachmentPath;
        $params[] = $attachmentSize;
        $params[] = $attachmentUploadedAt;
    }
    
    $params[] = $updatedBy;
    $params[] = $ticket;

    sqlsrv_begin_transaction($conn);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal update IKS.']);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    $deleteDetails = sqlsrv_query($conn, "DELETE FROM Form_Umum_Izin_Keluar_Pabrik_Detail WHERE ticket = ?", [$ticket]);
    if ($deleteDetails === false) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data karyawan.']);
        exit;
    }
    sqlsrv_free_stmt($deleteDetails);
    foreach ($employees as $employee) {
        $detailStmt = sqlsrv_query($conn, "INSERT INTO Form_Umum_Izin_Keluar_Pabrik_Detail (ticket, nik, nama_pemohon, departemen, bagian, jabatan, no_hp, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
            $ticket, $employee['nik'], $employee['nama_pemohon'], $employee['departemen'], $employee['bagian'], $employee['jabatan'], $employee['no_hp'], $updatedBy
        ]);
        if ($detailStmt === false) {
            sqlsrv_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan detail karyawan.']);
            exit;
        }
        sqlsrv_free_stmt($detailStmt);
    }
    sqlsrv_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui!', 'ticket' => $ticket]);
}
function handleUpdateIzinPulangCepat($conn, $ticket) {
    $updateAction = $_POST['update_action'] ?? '';
    $updatedBy = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? 'User';

    if ($updateAction === 'scan_keluar') {
        $sqlCheck = "SELECT status_ticket, jam_keluar_real FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$ticket]);
        if (!$stmtCheck || !sqlsrv_has_rows($stmtCheck)) {
            echo json_encode(['success' => false, 'message' => 'Tiket IPC tidak ditemukan.']);
            exit;
        }
        $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmtCheck);

        if (strtolower(trim($row['status_ticket'] ?? '')) !== 'approved') {
            echo json_encode(['success' => false, 'message' => 'Tiket belum approved.']);
            exit;
        }
        if (!empty($row['jam_keluar_real'])) {
            echo json_encode(['success' => false, 'message' => 'Tiket sudah discan keluar sebelumnya.']);
            exit;
        }

        $jamKeluar = date('H:i');
        $sql = "UPDATE Form_Umum_Izin_Pulang_Cepat
                SET jam_keluar_real = ?, status_keluar = 'Sudah Keluar', updated_at = GETDATE(), updated_by = ?
                WHERE ticket = ?";
        $stmt = sqlsrv_query($conn, $sql, [$jamKeluar, $updatedBy, $ticket]);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Gagal scan keluar IPC: ' . print_r(sqlsrv_errors(), true)]);
            exit;
        }
        sqlsrv_free_stmt($stmt);
        echo json_encode(['success' => true, 'message' => 'Scan keluar berhasil.', 'jam_keluar_real' => $jamKeluar, 'status_keluar' => 'Sudah Keluar']);
        exit;
    }

    $nik              = trim($_POST['nik'] ?? '');
    $namaPemohon      = trim($_POST['nama_pemohon'] ?? '');
    $departemen       = trim($_POST['departemen'] ?? '');
    $bagian           = trim($_POST['bagian'] ?? '') ?: null;
    $jabatan          = trim($_POST['jabatan'] ?? '') ?: null;
    $noHp             = trim($_POST['no_hp'] ?? '') ?: null;
    $tglPengajuan     = trim($_POST['tgl_pengajuan'] ?? '');
    $tanggal          = trim($_POST['tanggal'] ?? '');
    $jamPulangNormal  = trim($_POST['jam_pulang_normal'] ?? '16:15');
    $jamPulangDiminta = trim($_POST['jam_pulang_diminta'] ?? '');
    $alasan           = trim($_POST['alasan'] ?? '');
    $alasanLain       = trim($_POST['alasan_lain'] ?? '') ?: null;

    if ($nik === '' || $namaPemohon === '' || $departemen === '' || $tanggal === '' || $jamPulangNormal === '' || $jamPulangDiminta === '' || $alasan === '') {
        echo json_encode(['success' => false, 'message' => 'Field wajib tidak boleh kosong.']);
        exit;
    }
    if ($alasan === 'Lainnya' && empty($alasanLain)) {
        echo json_encode(['success' => false, 'message' => 'Alasan lainnya wajib diisi.']);
        exit;
    }
    if ($jamPulangDiminta >= $jamPulangNormal) {
        echo json_encode(['success' => false, 'message' => 'Jam Pulang Yang Diminta harus lebih awal dari Jam Pulang Normal.']);
        exit;
    }

    if (!empty($tglPengajuan) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tglPengajuan)) {
        $p = explode('-', $tglPengajuan);
        $tglPengajuan = $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (!empty($tanggal) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tanggal)) {
        $p = explode('-', $tanggal);
        $tanggal = $p[2] . '-' . $p[1] . '-' . $p[0];
    }

    $sql = "UPDATE Form_Umum_Izin_Pulang_Cepat
            SET nik = ?, nama_pemohon = ?, departemen = ?, bagian = ?, jabatan = ?, no_hp = ?,
                tgl_pengajuan = ?, tanggal = ?, jam_pulang_normal = ?, jam_pulang_diminta = ?,
                alasan = ?, alasan_lain = ?, updated_at = GETDATE(), updated_by = ?
            WHERE ticket = ?";
    $params = [$nik, $namaPemohon, $departemen, $bagian, $jabatan, $noHp, $tglPengajuan, $tanggal, $jamPulangNormal, $jamPulangDiminta, $alasan, ($alasan === 'Lainnya' ? $alasanLain : null), $updatedBy, $ticket];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Gagal update IPC: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    echo json_encode(['success' => true, 'message' => 'Data IPC berhasil diperbarui.', 'ticket' => $ticket]);
}

?>

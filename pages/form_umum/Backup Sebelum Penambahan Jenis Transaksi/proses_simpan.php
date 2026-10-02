<?php
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

$formType = $_POST['form_type'] ?? 'buka_tanggal_closingan';

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

// Handle different form types
switch ($formType) {
    case 'buka_tanggal_closingan':
        handleBukaTanggalClosingan($conn);
        break;
    case 'izin_keluar_pabrik':
        handleIzinKeluarPabrik($conn);
        break;
    case 'izin_pulang_cepat':
        handleIzinPulangCepat($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Jenis form tidak dikenal!']);
        exit;
}

function handleBukaTanggalClosingan($conn) {
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $bagian      = $_POST['area'] ?? '';
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    $gudangTransaksi = $_POST['gudang_transaksi'] ?? '';
    $createdBy   = $_SESSION['NamaLengkap'] ?? $nama;

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

    // Ekstrak buka_tgl dari item pertama
    $bukaTgl = $items[0]['buka_tgl'] ?? '';
    if (empty($bukaTgl)) {
        echo json_encode(['success' => false, 'message' => 'Buka Tgl pada item pertama wajib diisi!']);
        exit;
    }

    // --- Handle File Upload (Opsional) ---
    $attachmentFilename = null;
    $attachmentPath = null;
    $attachmentSize = null;
    $attachmentUploadedAt = null;

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
        
        // Keep original filename (as per user requirement)
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
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachmentFilename = $originalFilename;
            $attachmentPath = 'uploads/form_closing/' . $originalFilename;
            $attachmentSize = $file['size'];
            $attachmentUploadedAt = date('Y-m-d H:i:s');
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

    // --- Scan items untuk jenis_pengajuan & is_revisi_harga ---
    $hasGudang = false;
    $hasTransaksi = false;
    $isRevisiHarga = false;
    foreach ($items as $item) {
        if (!empty($item['request_gudang'])) $hasGudang = true;
        if (!empty($item['request_transaksi'])) $hasTransaksi = true;
        $transaksiName = trim($item['transaksi'] ?? '');
        if (strcasecmp($transaksiName, 'Revisi Harga') === 0) $isRevisiHarga = true;
    }
    $jenisPengajuan = 'campuran';
    if ($hasGudang && !$hasTransaksi) $jenisPengajuan = 'gudang';
    elseif (!$hasGudang && $hasTransaksi) $jenisPengajuan = 'transaksi';

        // Nilai request_gudang dan request_transaksi dari item pertama untuk backward compat
    $reqGudang    = !empty($items[0]['request_gudang']) ? 1 : 0;
    $reqTransaksi = !empty($items[0]['request_transaksi']) ? 1 : 0;
    $vendorCust   = $items[0]['vendor_cust'] ?? '';
    $keterangan   = $items[0]['keterangan'] ?? '';

    // Format tanggal pengajuan (DD-MM-YYYY to YYYY-MM-DD)
    if (!empty($tgl_form) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tgl_form)) {
        $parts = explode('-', $tgl_form);
        $tgl_form = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } elseif (empty($tgl_form) || $tgl_form == 'DD-MM-YYYY') {
        $tgl_form = date('Y-m-d');
    }

    // Format buka_tgl (if it happens to be DD-MM-YYYY)
    if (!empty($bukaTgl) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $bukaTgl)) {
        $parts = explode('-', $bukaTgl);
        $bukaTgl = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }

    // Generate ticket: CLS-DDMMYYYY-XXX
    $today = date('dmY');
    $like = "CLS-$today-%";
    $sqlTkt = "SELECT TOP 1 ticket FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket LIKE ? ORDER BY ticket DESC";
    $stmtTkt = sqlsrv_query($conn, $sqlTkt, [$like]);
    $last = 0;
    if ($stmtTkt) {
        $rowTkt = sqlsrv_fetch_array($stmtTkt, SQLSRV_FETCH_ASSOC);
        if ($rowTkt && isset($rowTkt['ticket'])) {
            $parts = explode('-', $rowTkt['ticket']);
            $last = isset($parts[2]) ? intval($parts[2]) : 0;
        }
        sqlsrv_free_stmt($stmtTkt);
    }
    $next = str_pad($last+1, 3, '0', STR_PAD_LEFT);
    $ticket = "CLS-$today-$next";

    $sql = "INSERT INTO Form_Umum_Buka_Tanggal_Closingan
        (ticket, nama_pemohon, jabatan, departemen, bagian, tgl_pengajuan,
         request_gudang, request_transaksi, buka_tgl, gudang_transaksi, vendor_cust, keterangan,
         jenis_pengajuan, is_revisi_harga,
         attachment_filename, attachment_path, attachment_size, attachment_uploaded_at,
         status_ticket, kategori, created_at, created_by)
        VALUES (?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,
         ?,?,?,?,
         'Pending', 'Buka Tanggal Closingan', GETDATE(), ?)";
    
    $params = [
        $ticket, $nama, $jab, $dept, $bagian, $tgl_form,
        $reqGudang, $reqTransaksi, $bukaTgl, $gudangTransaksi,
        $vendorCust, $keterangan,
        $jenisPengajuan, $isRevisiHarga ? 1 : 0,
        $attachmentFilename, $attachmentPath, $attachmentSize, $attachmentUploadedAt,
        $createdBy
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['success'=>false,'message'=>'Gagal simpan Buka Closingan: '.print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success'=>true,'message'=>'Pengajuan Buka Tanggal Closingan berhasil disimpan','ticket'=>$ticket]);
}

function handleIzinKeluarPabrik($conn) {
    // Baca dan sanitasi POST fields
    $nik               = trim($_POST['nik'] ?? '');
    $nama_pemohon      = trim($_POST['nama_pemohon'] ?? '');
    $departemen        = trim($_POST['departemen'] ?? '');
    $bagian            = trim($_POST['bagian'] ?? '');
    $jabatan           = trim($_POST['jabatan'] ?? '');
    $tgl_pengajuan     = trim($_POST['tgl_pengajuan'] ?? '');
    $tgl_keluar        = trim($_POST['tgl_keluar'] ?? '');
    $jam_keluar        = trim($_POST['jam_keluar'] ?? '');
    $estimasi_kembali  = trim($_POST['estimasi_kembali'] ?? '');
    $tujuan            = trim($_POST['tujuan'] ?? '') ?: null;
    $keperluan         = trim($_POST['keperluan'] ?? '');
    $keperluan_lain    = trim($_POST['keperluan_lain'] ?? '');
    $no_hp             = trim($_POST['no_hp'] ?? '');
    $kendaraan         = trim($_POST['kendaraan'] ?? '');
    $kendaraan_lain    = trim($_POST['kendaraan_lain'] ?? '');
    $hari              = trim($_POST['hari'] ?? '');
    $createdBy         = $_SESSION['NamaLengkap'] ?? $nama_pemohon;

    // Handle file upload (lampiran)
    $attachmentFilename = null;
    $attachmentPath = null;
    $attachmentSize = null;
    $attachmentUploadedAt = null;

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
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachmentFilename = $originalFilename;
            $attachmentPath = 'uploads/form_izin_keluar/' . $originalFilename;
            $attachmentSize = $file['size'];
            $attachmentUploadedAt = date('Y-m-d H:i:s');
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
                'nik' => $employee['nik'],
                'nama_pemohon' => $employee['nama_lengkap'],
                'departemen' => $employee['dept'] ?? '',
                'bagian' => $employee['bagian'] ?? '',
                'jabatan' => $employee['jabatan'] ?? '',
                'no_hp' => $employee['telp'] ?? '',
            ];
        }
    }
    if (!$employees && $nik !== '' && $nama_pemohon !== '') {
        $employees[] = compact('nik', 'nama_pemohon', 'departemen', 'bagian', 'jabatan', 'no_hp');
    }
    if (!$employees) {
        echo json_encode(['success' => false, 'message' => 'Minimal satu karyawan wajib dipilih.']);
        exit;
    }
    $employeeNiks = array_column($employees, 'nik');
    if (count($employeeNiks) !== count(array_unique($employeeNiks))) {
        echo json_encode(['success' => false, 'message' => 'Karyawan yang sama tidak boleh dipilih dua kali.']);
        exit;
    }
    $firstEmployee = $employees[0];
    $nik = $firstEmployee['nik'];
    $nama_pemohon = $firstEmployee['nama_pemohon'];
    $departemen = $firstEmployee['departemen'];
    $bagian = $firstEmployee['bagian'];
    $jabatan = $firstEmployee['jabatan'];
    $no_hp = $firstEmployee['no_hp'];

    // Validasi server-side
    if ($departemen === '' || $jam_keluar === '' || $estimasi_kembali === '' || $keperluan === '' || $kendaraan === '') {
        echo json_encode(['success' => false, 'message' => 'Field perjalanan wajib diisi.']);
        exit;
    }

    // Tentukan tipe_keluar untuk backward compatibility
    $tipe_keluar = (strcasecmp($keperluan, 'Dinas Perusahaan') === 0) ? 'Dinas' : 'Pribadi';

    // Gabung alasan untuk compatibility
    $alasan_keperluan = ($keperluan === 'Lainnya') ? $keperluan_lain : $keperluan;
    $alasan = "[Keperluan: " . $alasan_keperluan . "]";
    if (!empty($tujuan)) {
        $alasan .= " [Tujuan: " . $tujuan . "]";
    }

    // Format tanggal
    if (!empty($tgl_pengajuan) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tgl_pengajuan)) {
        $parts = explode('-', $tgl_pengajuan);
        $tgl_pengajuan = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } elseif (empty($tgl_pengajuan) || $tgl_pengajuan === 'DD-MM-YYYY') {
        $tgl_pengajuan = date('Y-m-d');
    }

    if (!empty($tgl_keluar) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tgl_keluar)) {
        $parts = explode('-', $tgl_keluar);
        $tgl_keluar = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } elseif (empty($tgl_keluar)) {
        $tgl_keluar = $tgl_pengajuan;
    }

    // Generate ticket: IKS-DDMMYYYY-NNN
    $today = date('dmY');
    $like  = "IKS-$today-%";
    $sqlTkt = "SELECT TOP 1 ticket FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket LIKE ? ORDER BY ticket DESC";
    $stmtTkt = sqlsrv_query($conn, $sqlTkt, [$like]);
    $last = 0;
    if ($stmtTkt) {
        $rowTkt = sqlsrv_fetch_array($stmtTkt, SQLSRV_FETCH_ASSOC);
        if ($rowTkt && isset($rowTkt['ticket'])) {
            $parts = explode('-', $rowTkt['ticket']);
            $last = isset($parts[2]) ? intval($parts[2]) : 0;
        }
        sqlsrv_free_stmt($stmtTkt);
    }
    $next   = str_pad($last + 1, 3, '0', STR_PAD_LEFT);
    $ticket = "IKS-$today-$next";

    sqlsrv_begin_transaction($conn);
    $sql = "INSERT INTO Form_Umum_Izin_Keluar_Pabrik
        (ticket, tipe_keluar, hari, tgl_pengajuan, nama_pemohon, departemen, bagian,
         jam_keluar_dari, jam_keluar_sampai, alasan, nik, jabatan, tgl_keluar, jam_keluar,
         estimasi_kembali, tujuan, keperluan, keperluan_lain, no_hp, kendaraan, kendaraan_lain,
         attachment_filename, attachment_path, attachment_size, attachment_uploaded_at,
         status_ticket, status_kembali, created_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                'Pending', 'Belum Keluar', GETDATE(), ?)";
    $params = [
        $ticket, $tipe_keluar, ($hari !== '' ? $hari : null), $tgl_pengajuan, $nama_pemohon, $departemen, $bagian,
        $jam_keluar, $estimasi_kembali, $alasan, $nik, $jabatan, $tgl_keluar, $jam_keluar, $estimasi_kembali,
        $tujuan, $keperluan, ($keperluan === 'Lainnya' ? $keperluan_lain : null), $no_hp, $kendaraan,
        ($kendaraan === 'Lainnya' ? $kendaraan_lain : null), $attachmentFilename, $attachmentPath,
        $attachmentSize, $attachmentUploadedAt, $createdBy
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal simpan IKS.']);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    $sqlDetail = "INSERT INTO Form_Umum_Izin_Keluar_Pabrik_Detail
        (ticket, nik, nama_pemohon, departemen, bagian, jabatan, no_hp, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    foreach ($employees as $employee) {
        $detailStmt = sqlsrv_query($conn, $sqlDetail, [
            $ticket, $employee['nik'], $employee['nama_pemohon'], $employee['departemen'],
            $employee['bagian'], $employee['jabatan'], $employee['no_hp'], $createdBy
        ]);
        if ($detailStmt === false) {
            sqlsrv_rollback($conn);
            echo json_encode(['success' => false, 'message' => 'Gagal simpan data karyawan.']);
            exit;
        }
        sqlsrv_free_stmt($detailStmt);
    }
    sqlsrv_commit($conn);
    echo json_encode(['success' => true, 'ticket' => $ticket]);
}

function handleIzinPulangCepat($conn) {
    $nik                = trim($_POST['nik'] ?? '');
    $namaPemohon        = trim($_POST['nama_pemohon'] ?? '');
    $departemen         = trim($_POST['departemen'] ?? '');
    $bagian             = trim($_POST['bagian'] ?? '');
    $jabatan            = trim($_POST['jabatan'] ?? '');
    $noHp               = trim($_POST['no_hp'] ?? '');
    $tglPengajuan       = trim($_POST['tgl_pengajuan'] ?? '');
    $tanggal            = trim($_POST['tanggal'] ?? '');
    $jamPulangNormal    = trim($_POST['jam_pulang_normal'] ?? '16:15');
    $jamPulangDiminta   = trim($_POST['jam_pulang_diminta'] ?? '');
    $alasan             = trim($_POST['alasan'] ?? '');
    $alasanLain         = trim($_POST['alasan_lain'] ?? '');
    $createdBy          = $_SESSION['NamaLengkap'] ?? $namaPemohon;

    if ($nik === '' || $namaPemohon === '' || $departemen === '' || $tanggal === '' || $jamPulangNormal === '' || $jamPulangDiminta === '' || $alasan === '') {
        echo json_encode(['success' => false, 'message' => 'Field wajib tidak boleh kosong.']);
        exit;
    }

    if ($alasan === 'Lainnya' && $alasanLain === '') {
        echo json_encode(['success' => false, 'message' => 'Alasan lainnya wajib diisi.']);
        exit;
    }

    if ($jamPulangDiminta >= $jamPulangNormal) {
        echo json_encode(['success' => false, 'message' => 'Jam Pulang Yang Diminta harus lebih awal dari Jam Pulang Normal.']);
        exit;
    }

    if ($tglPengajuan === '') {
        $tglPengajuan = date('Y-m-d');
    }

    if (!empty($tglPengajuan) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tglPengajuan)) {
        $p = explode('-', $tglPengajuan);
        $tglPengajuan = $p[2] . '-' . $p[1] . '-' . $p[0];
    }
    if (!empty($tanggal) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $tanggal)) {
        $p = explode('-', $tanggal);
        $tanggal = $p[2] . '-' . $p[1] . '-' . $p[0];
    }

    $attachmentFilename = null;
    $attachmentPath = null;
    $attachmentSize = null;
    $attachmentUploadedAt = null;

    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['lampiran'];
        $maxSize = 50 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 50MB.']);
            exit;
        }

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        $mimeType = mime_content_type($file['tmp_name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];

        if (!in_array($mimeType, $allowedMimes) && !in_array($extension, $allowedExtensions)) {
            echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan PDF, JPG, PNG, DOCX, atau XLSX.']);
            exit;
        }

        $originalFilename = basename($file['name']);
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/uploads/form_izin_pulang_cepat/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Gagal membuat direktori upload.']);
            exit;
        }

        $targetPath = $uploadDir . $originalFilename;
        $counter = 1;
        $fileNameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
        $fileExt = pathinfo($originalFilename, PATHINFO_EXTENSION);
        while (file_exists($targetPath)) {
            $originalFilename = $fileNameWithoutExt . '_' . $counter . '.' . $fileExt;
            $targetPath = $uploadDir . $originalFilename;
            $counter++;
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupload file.']);
            exit;
        }

        $attachmentFilename = $originalFilename;
        $attachmentPath = 'uploads/form_izin_pulang_cepat/' . $originalFilename;
        $attachmentSize = $file['size'];
        $attachmentUploadedAt = date('Y-m-d H:i:s');
    } elseif (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'Error upload file.']);
        exit;
    }

    $today = date('dmY');
    $like  = "IPC-$today-%";
    $sqlTkt = "SELECT TOP 1 ticket FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket LIKE ? ORDER BY ticket DESC";
    $stmtTkt = sqlsrv_query($conn, $sqlTkt, [$like]);
    $last = 0;
    if ($stmtTkt) {
        $rowTkt = sqlsrv_fetch_array($stmtTkt, SQLSRV_FETCH_ASSOC);
        if ($rowTkt && isset($rowTkt['ticket'])) {
            $parts = explode('-', $rowTkt['ticket']);
            $last = isset($parts[2]) ? intval($parts[2]) : 0;
        }
        sqlsrv_free_stmt($stmtTkt);
    }
    $ticket = "IPC-$today-" . str_pad($last + 1, 3, '0', STR_PAD_LEFT);

    $sql = "INSERT INTO Form_Umum_Izin_Pulang_Cepat
        (ticket, nik, nama_pemohon, departemen, bagian, jabatan, no_hp,
         tgl_pengajuan, tanggal, jam_pulang_normal, jam_pulang_diminta,
         alasan, alasan_lain, attachment_filename, attachment_path,
         attachment_size, attachment_uploaded_at, status_ticket, status_keluar,
         created_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Belum Keluar', GETDATE(), ?)";

    $params = [
        $ticket, $nik, $namaPemohon, $departemen, $bagian, $jabatan, $noHp,
        $tglPengajuan, $tanggal, $jamPulangNormal, $jamPulangDiminta,
        $alasan, ($alasan === 'Lainnya' ? $alasanLain : null),
        $attachmentFilename, $attachmentPath, $attachmentSize, $attachmentUploadedAt,
        $createdBy
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal simpan IPC: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['success' => true, 'ticket' => $ticket]);
}
?>


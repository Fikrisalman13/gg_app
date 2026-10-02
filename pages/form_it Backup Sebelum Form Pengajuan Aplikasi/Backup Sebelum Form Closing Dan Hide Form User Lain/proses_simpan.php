<?php
session_start();

// Set header untuk JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

// Get form type
$formType = $_POST['form_type'] ?? 'pengajuan_perangkat';

// Handle different form types
switch ($formType) {
    case 'pengajuan_perangkat':
        handlePengajuanPerangkat();
        break;
    case 'pengajuan_cctv':
        handlePengajuanCCTV();
        break;
    case 'pengajuan_akses_internet':
        handlePengajuanAksesInternet();
        break;
    case 'pengajuan_email_account':
        handlePengajuanEmailAccount();
        break;
    case 'maintenance_it':
        handleMaintenanceIT();
        break;
    case 'pengajuan_product_ga':
        handlePengajuanProductGA();
        break;
    case 'grant_revoke_trustee':
        handleGrantRevokeTrustee();
        break;
    case 'perubahan_data_database':
        handlePerubahanDataDatabase();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Jenis form tidak dikenal!']);
        exit;
}

// ... (existing functions) ...

function handlePerubahanDataDatabase() {
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $bagian      = $_POST['bagian'] ?? ''; // Added
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    
    // Checkboxes
    $reqAdd      = !empty($_POST['req_add']) ? 1 : 0;
    $reqEdit     = !empty($_POST['req_edit']) ? 1 : 0;
    $reqDelete   = !empty($_POST['req_delete']) ? 1 : 0;
    
    $appProint   = !empty($_POST['app_proint']) ? 1 : 0;
    $appHris     = !empty($_POST['app_hris']) ? 1 : 0;
    $appLainnya  = !empty($_POST['app_lainnya']) ? 1 : 0;
    $appLainnyaText = $_POST['app_lainnya_text'] ?? '';
    
    $perubahan   = $_POST['perubahan'] ?? '';
    $keterangan  = $_POST['keterangan'] ?? '';
    $kategori    = 'Database'; // Static value
    $createdBy   = $_SESSION['NamaLengkap'] ?? $nama;

    // Validate
    if (empty($perubahan)) {
        echo json_encode(['success' => false, 'message' => 'Kolom Perubahan wajib diisi!']);
        exit;
    }

    // Format tanggal
    $tgl_sql = null;
    if (!empty($tgl_form) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_form)) {
        $tgl_sql = $tgl_form;
    } else {
         $tgl_sql = date('Y-m-d'); 
    }

    // Koneksi
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

    // Generate ticket: DB-DDMMYYYY-XXX
    function generateTicketDatabase($conn) {
        $today = date('dmY');
        $like = "DB-$today-%";
        $sql = "SELECT TOP 1 ticket FROM Form_Perubahan_Data_Database WHERE ticket LIKE ? ORDER BY ticket DESC";
        $stmt = sqlsrv_query($conn, $sql, [$like]);
        $last = 0;
        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['ticket'])) {
                $parts = explode('-', $row['ticket']);
                $last = isset($parts[2]) ? intval($parts[2]) : 0;
            }
            sqlsrv_free_stmt($stmt);
        }
        $next = str_pad($last+1, 3, '0', STR_PAD_LEFT);
        return "DB-$today-$next";
    }
    $ticket = generateTicketDatabase($conn);

    $sql = "INSERT INTO Form_Perubahan_Data_Database
        (ticket, nama_pemohon, jabatan, departemen, tgl_pengajuan,
         req_add, req_edit, req_delete,
         app_proint, app_hris, app_lainnya, app_lainnya_text,
         perubahan, keterangan, status_ticket, created_at, created_by)
        VALUES (?,?,?,?,?, ?,?,?, ?,?,?,?, ?,?, 'Pending', GETDATE(), ?)";
    
    $params = [
        $ticket, $nama, $jab, $dept, $tgl_sql,
        $reqAdd, $reqEdit, $reqDelete,
        $appProint, $appHris, $appLainnya, $appLainnyaText,
        $perubahan, $keterangan, $createdBy
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        echo json_encode(['success'=>false,'message'=>'Gagal insert Perubahan Data: '.print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    echo json_encode(['success'=>true,'message'=>'Pengajuan Perubahan Data berhasil disimpan','ticket'=>$ticket]);
}
function handlePengajuanEmailAccount() {
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $area        = $_POST['area'] ?? '';
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    $emailAccount = $_POST['email_user'] ?? '';
    $reqEmail    = !empty($_POST['request_email_account']) ? 1 : 0;
    $reqChange   = !empty($_POST['request_change_access']) ? 1 : 0;
    $reqSetting  = !empty($_POST['request_setting_device']) ? 1 : 0;
    $deviceAndroid = !empty($_POST['device_android']) ? 1 : 0;
    $deviceIOS     = !empty($_POST['device_ios']) ? 1 : 0;
    $deviceLainnya = !empty($_POST['device_lainnya']) ? 1 : 0;
    $deviceLainnyaText = $_POST['device_lainnya_text'] ?? '';
    $aksesLocal   = !empty($_POST['akses_email_local']) ? 1 : 0;
    $aksesGlobal  = !empty($_POST['akses_email_global']) ? 1 : 0;
    $globalKirim  = !empty($_POST['global_kirim']) ? 1 : 0;
    $globalTerima = !empty($_POST['global_terima']) ? 1 : 0;
    $globalFull   = !empty($_POST['global_full']) ? 1 : 0;
    $keterangan   = $_POST['keterangan'] ?? '';
    $createdBy    = $_SESSION['NamaLengkap'] ?? $nama;

    // Format tanggal ke SQL Server (convert to YYYY-MM-DD)
    $tgl_sql = null;
    if (!empty($tgl_form)) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_form)) {
            $tgl_sql = $tgl_form;
        } else {
            $parts = explode("-", $tgl_form);
            if (count($parts) == 3) {
                $tgl_sql = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }
    }

    // Koneksi
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

    // Generate ticket: EMAIL-DDMMYYYY-XXX
    function generateTicketEmail($conn) {
        $today = date('dmY');
        $like = "EMAIL-$today-%";
        $sql = "SELECT TOP 1 ticket FROM Form_Pengajuan_Email_Account WHERE ticket LIKE ? ORDER BY ticket DESC";
        $stmt = sqlsrv_query($conn, $sql, [$like]);
        $last = 0;
        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['ticket'])) {
                $parts = explode('-', $row['ticket']);
                $last = isset($parts[2]) ? intval($parts[2]) : 0;
            }
            sqlsrv_free_stmt($stmt);
        }
        $next = str_pad($last+1, 3, '0', STR_PAD_LEFT);
        return "EMAIL-$today-$next";
    }
    $ticket = generateTicketEmail($conn);

    $sql = "INSERT INTO Form_Pengajuan_Email_Account
        (ticket, nama_pemohon, jabatan, departemen, area, tgl_pengajuan,
         request_email_account, request_change_access, request_setting_device,
         device_android, device_ios, device_lainnya, device_lainnya_text,
         akses_email_local, akses_email_global, global_kirim, global_terima, global_full,
         keterangan, email_account, status_ticket, kategori, created_at, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, 'Pending', 'Email Account', GETDATE(), ?)";
    $params = [
        $ticket, $nama, $jab, $dept, $area, $tgl_sql,
        $reqEmail, $reqChange, $reqSetting,
        $deviceAndroid, $deviceIOS, $deviceLainnya, $deviceLainnyaText,
        $aksesLocal, $aksesGlobal, $globalKirim, $globalTerima, $globalFull,
        $keterangan, $emailAccount, $createdBy
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        echo json_encode(['success'=>false,'message'=>'Gagal insert Email Account: '.print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    echo json_encode(['success'=>true,'message'=>'Pengajuan Email Account berhasil disimpan','ticket'=>$ticket]);
}

function handlePengajuanPerangkat() {
    // VALIDASI MINIMAL
    if (empty($_POST['pengajuan']) && empty($_POST['peripheral'])) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada item yang dikirim!']);
        exit;
    }

    // Ambil data form
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $bagian      = $_POST['bagian'] ?? '';
    $spesifikasi = $_POST['spesifikasi'] ?? '';
    $keterangan  = $_POST['keterangan'] ?? '';
    $createdBy   = $_SESSION['NamaLengkap'] ?? $nama;

    // Format tanggal ke SQL Server (convert to YYYY-MM-DD or NULL for DATE columns)
    $tgl_sql = null;
    if (!empty($tgl_form)) {
        $parts = explode("-", $tgl_form);
        if (count($parts) == 3) {
            // Expecting DD-MM-YYYY from older forms; convert to YYYY-MM-DD
            $tgl_sql = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_form)) {
            $tgl_sql = $tgl_form;
        }
    }

    // Convert array pengajuan/peripheral ke string
    $pengajuan_str  = !empty($_POST['pengajuan']) ? implode(",", $_POST['pengajuan']) : "";
    $peripheral_str = !empty($_POST['peripheral']) ? implode(",", $_POST['peripheral']) : "";

    // Helper function untuk ambil qty
    $getQty = function($key) {
        return intval($_POST[$key] ?? 0);
    };

    // QTY Pengajuan
    $qty_komputer  = $getQty("qty_komputer");
    $qty_laptop    = $getQty("qty_laptop");
    $qty_tablet    = $getQty("qty_tablet");
    $qty_handphone = $getQty("qty_handphone");
    $qty_lainnya   = $getQty("qty_lainnya");
    $ket_lainnya   = $_POST["ket_lainnya"] ?? "";

    // QTY Peripheral
    $qty_perip_keyboard = $getQty("qty_perip_keyboard");
    $qty_perip_mouse    = $getQty("qty_perip_mouse");
    $qty_perip_monitor  = $getQty("qty_perip_monitor");
    $qty_perip_printer  = $getQty("qty_perip_printer");
    $qty_perip_scanner  = $getQty("qty_perip_scanner");
    $qty_perip_lainnya  = $getQty("qty_perip_lainnya");
    $ket_perip_lainnya  = $_POST["ket_perip_lainnya"] ?? "";

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
       GENERATE TICKET (SETELAH CONNECT)
       Format: TKT-DDMMYYYY-XXX (reset tiap hari)
    ================================ */
    function generateTicket($conn) {
        $today = date("dmY"); // ddmmyyyy e.g. 15112025

        // Ambil ticket terakhir untuk hari ini
        $sql = "SELECT TOP 1 ticket
                FROM Form_Pengajuan_Barang
                WHERE ticket LIKE ?
                ORDER BY ticket DESC";

        $like = "TKT-{$today}-%";
        $stmt = sqlsrv_query($conn, $sql, [$like]);

        $last = 0;
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['ticket'])) {
                // ticket format: TKT-DDMMYYYY-XXX
                $parts = explode("-", $row['ticket']);
                $last = isset($parts[2]) ? intval($parts[2]) : 0;
            }
            sqlsrv_free_stmt($stmt);
        }

        $next = str_pad($last + 1, 3, "0", STR_PAD_LEFT);
        return "TKT-{$today}-{$next}";
    }

    // PANGGIL generateTicket setelah koneksi berhasil
    $tiket = generateTicket($conn);

    /* ================================
       INSERT BARIS (1 ROW)
    ================================ */
    $sql = "INSERT INTO Form_Pengajuan_Barang
    (
        ticket, nama_pemohon, jabatan, tgl_pengajuan, departemen, bagian,
        pengajuan, qty_komputer, qty_laptop, qty_tablet, qty_handphone, qty_lainnya, ket_lainnya,
        spesifikasi,
        peripheral, qty_perip_keyboard, qty_perip_mouse, qty_perip_monitor, qty_perip_printer, qty_perip_scanner, qty_perip_lainnya, ket_perip_lainnya,
        keterangan,
        kategori,
        created_at, created_by
    )
    VALUES 
    (?, ?, ?, ?, ?, ?,
     ?, ?, ?, ?, ?, ?, ?,
     ?,
     ?, ?, ?, ?, ?, ?, ?, ?,
     ?,
     ?,
     GETDATE(), ?
    )";

    $params = [
        $tiket, $nama, $jab, $tgl_sql, $dept, $bagian,
        $pengajuan_str, $qty_komputer, $qty_laptop, $qty_tablet, $qty_handphone, $qty_lainnya, $ket_lainnya,
        $spesifikasi,
        $peripheral_str, $qty_perip_keyboard, $qty_perip_mouse, $qty_perip_monitor, $qty_perip_printer, $qty_perip_scanner, $qty_perip_lainnya, $ket_perip_lainnya,
        $keterangan,
        'Perangkat IT',
        $createdBy
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Gagal insert: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan!', 'ticket' => $tiket]);
}

function handleMaintenanceIT() {
    // Placeholder untuk form Maintenance IT
    // TODO: Implementasi penyimpanan data Maintenance IT
    echo json_encode([
        'success' => false, 
        'message' => 'Form Maintenance IT belum tersedia. Silakan hubungi administrator untuk pengembangan lebih lanjut.'
    ]);
}

function handlePengajuanCCTV() {
    // Ambil data form CCTV
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $area        = $_POST['area'] ?? '';
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    $tanggal1    = $_POST['tanggal_1'] ?? '';
    $jamMulai1   = $_POST['jam_mulai_1'] ?? '';
    $jamSelesai1 = $_POST['jam_selesai_1'] ?? '';
    $tanggal2    = $_POST['tanggal_2'] ?? '';
    $jamMulai2   = $_POST['jam_mulai_2'] ?? '';
    $jamSelesai2 = $_POST['jam_selesai_2'] ?? '';
    $keterangan  = $_POST['keterangan'] ?? '';
    $createdBy   = $_SESSION['NamaLengkap'] ?? $nama;

    // Format tanggal - normalize to 'YYYY-MM-DD' or NULL (for DATE columns)
    $fmtDate = function($d){
        if (empty($d)) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        $p = explode('-', $d);
        if (count($p)==3 && strlen($p[0])==2) return $p[2].'-'.$p[1].'-'.$p[0];
        return null;
    };
    $tgl_pengajuan_sql = $fmtDate($tgl_form);
    $tanggal1_sql = $fmtDate($tanggal1);
    $tanggal2_sql = $fmtDate($tanggal2);

    // Koneksi
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

    // Generate ticket CCTV: CCTV-DDMMYYYY-XXX
    function generateTicketCCTV($conn) {
        $today = date('dmY');
        $like = "CCTV-$today-%";
        $sql = "SELECT TOP 1 ticket FROM Form_Pengajuan_CCTV WHERE ticket LIKE ? ORDER BY ticket DESC";
        $stmt = sqlsrv_query($conn, $sql, [$like]);
        $last = 0;
        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['ticket'])) {
                $parts = explode('-', $row['ticket']);
                $last = isset($parts[2]) ? intval($parts[2]) : 0;
            }
            sqlsrv_free_stmt($stmt);
        }
        $next = str_pad($last+1, 3, '0', STR_PAD_LEFT);
        return "CCTV-$today-$next";
    }
    $ticket = generateTicketCCTV($conn);

    $sql = "INSERT INTO Form_Pengajuan_CCTV
        (ticket, nama_pemohon, jabatan, departemen, area, tgl_pengajuan,
         tanggal_1, jam_mulai_1, jam_selesai_1, tanggal_2, jam_mulai_2, jam_selesai_2,
         keterangan, status_ticket, kategori, created_at, created_by)
        VALUES (?,?,?,?,?,?, ?,?,?, ?,?,?, ?, 'Pending', 'CCTV', GETDATE(), ?)";
    $params = [
        $ticket, $nama, $jab, $dept, $area, $tgl_pengajuan_sql,
        $tanggal1_sql, $jamMulai1, $jamSelesai1, $tanggal2_sql, $jamMulai2, $jamSelesai2,
        $keterangan, $createdBy
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        echo json_encode(['success'=>false,'message'=>'Gagal insert CCTV: '.print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    echo json_encode(['success'=>true,'message'=>'Pengajuan CCTV berhasil disimpan','ticket'=>$ticket]);
}

function handlePengajuanAksesInternet() {
    // Ambil data form Akses Internet
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $area        = $_POST['area'] ?? '';
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    // New fields
    $reqAkses    = !empty($_POST['request_akses_internet']) ? 1 : 0;
    $aksesType   = $_POST['akses_type'] ?? '';
    // akses temporary from/to
    $akses_from  = $_POST['akses_temporary_from'] ?? '';
    $akses_to    = $_POST['akses_temporary_to'] ?? '';

    $reqBw       = !empty($_POST['request_tambah_bandwidth']) ? 1 : 0;
    $bwType      = $_POST['bandwidth_type'] ?? '';
    $bw_from     = $_POST['bandwidth_temporary_from'] ?? '';
    $bw_to       = $_POST['bandwidth_temporary_to'] ?? '';
    $tambah_bw   = isset($_POST['tambah_bandwidth']) && $_POST['tambah_bandwidth'] !== '' ? intval($_POST['tambah_bandwidth']) : null;
    $tambah_bw_unit = $_POST['tambah_bandwidth_unit'] ?? null;
    $keterangan  = $_POST['keterangan'] ?? '';
    $createdBy   = $_SESSION['NamaLengkap'] ?? $nama;

    // Format tanggal - HTML5 date input sudah mengirim YYYY-MM-DD, langsung gunakan
    $fmtDate = function($d){
        if (empty($d)) return null;
        // Jika sudah format YYYY-MM-DD (dari type="date"), langsung return
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        // Jika masih DD-MM-YYYY (backward compatibility), konversi ke YYYY-MM-DD
        $p = explode('-', $d);
        if (count($p)==3 && strlen($p[0])==2) {
            return $p[2].'-'.$p[1].'-'.$p[0];
        }
        return null;
    };
    $tgl_pengajuan_sql = $fmtDate($tgl_form);
    $akses_from_sql = $fmtDate($akses_from);
    $akses_to_sql = $fmtDate($akses_to);
    $bw_from_sql = $fmtDate($bw_from);
    $bw_to_sql = $fmtDate($bw_to);

    // Build durasi_temporary string for backward compatibility (format: DD-MM-YYYY s/d DD-MM-YYYY)
    $durasi_temporary = null;
    if (!empty($akses_from) || !empty($akses_to)) {
        $df = $akses_from ? (preg_match('/^\d{4}-\d{2}-\d{2}$/',$akses_from_sql) ? date('d-m-Y', strtotime($akses_from_sql)) : $akses_from) : '';
        $dt = $akses_to ? (preg_match('/^\d{4}-\d{2}-\d{2}$/',$akses_to_sql) ? date('d-m-Y', strtotime($akses_to_sql)) : $akses_to) : '';
        $durasi_temporary = trim($df) . ' s/d ' . trim($dt);
    }

    // Koneksi
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

    // Generate ticket: INET-DDMMYYYY-XXX
    function generateTicketInternet($conn) {
        $today = date('dmY');
        $like = "INET-$today-%";
        $sql = "SELECT TOP 1 ticket FROM Form_Pengajuan_Akses_Internet WHERE ticket LIKE ? ORDER BY ticket DESC";
        $stmt = sqlsrv_query($conn, $sql, [$like]);
        $last = 0;
        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['ticket'])) {
                $parts = explode('-', $row['ticket']);
                $last = isset($parts[2]) ? intval($parts[2]) : 0;
            }
            sqlsrv_free_stmt($stmt);
        }
        $next = str_pad($last+1, 3, '0', STR_PAD_LEFT);
        return "INET-$today-$next";
    }
    $ticket = generateTicketInternet($conn);

    $sql = "INSERT INTO Form_Pengajuan_Akses_Internet
        (ticket, nama_pemohon, jabatan, departemen, area, tgl_pengajuan,
         request_akses_internet, akses_type, akses_temporary_from, akses_temporary_to, durasi_temporary,
         request_tambah_bandwidth, bandwidth_type, bandwidth_temporary_from, bandwidth_temporary_to, tambah_bandwidth, tambah_bandwidth_unit,
         keterangan, status_ticket, kategori, created_at, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, 'Pending', 'Akses Internet', GETDATE(), ?)";
    $params = [
        $ticket, $nama, $jab, $dept, $area, $tgl_pengajuan_sql,
        $reqAkses, $aksesType, $akses_from_sql, $akses_to_sql, $durasi_temporary,
        $reqBw, $bwType, $bw_from_sql, $bw_to_sql, $tambah_bw, $tambah_bw_unit,
        $keterangan, $createdBy
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        echo json_encode(['success'=>false,'message'=>'Gagal insert Akses Internet: '.print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    echo json_encode(['success'=>true,'message'=>'Pengajuan Akses Internet berhasil disimpan','ticket'=>$ticket]);
}

function handlePengajuanProductGA() {
    // Placeholder untuk form Pengajuan Product ke GA
    // TODO: Implementasi penyimpanan data Pengajuan Product ke GA
    echo json_encode([
        'success' => false, 
        'message' => 'Form Pengajuan Product ke GA belum tersedia. Silakan hubungi administrator untuk pengembangan lebih lanjut.'
    ]);
}

function handleGrantRevokeTrustee() {
    $nama        = $_POST['nama_pemohon'] ?? '';
    $jab         = $_POST['jabatan'] ?? '';
    $dept        = $_POST['departemen'] ?? '';
    $area        = $_POST['area'] ?? '';  // Added area
    $tgl_form    = $_POST['tgl_pengajuan'] ?? '';
    $jenis       = $_POST['jenis_permintaan'] ?? '';
    $menus       = $_POST['menu_akses'] ?? []; // Array
    $keterangan  = $_POST['keterangan'] ?? '';
    $createdBy   = $_SESSION['NamaLengkap'] ?? $nama;

    // Validate
    if (empty($jenis)) {
        echo json_encode(['success' => false, 'message' => 'Pilih jenis permintaan: GRANT atau REVOKE!']);
        exit;
    }

    // Convert menus array to simple string list (newline separated)
    $menu_list_str = '';
    if (is_array($menus)) {
        $filtered_menus = array_filter($menus, function($m) { return !empty(trim($m)); }); // Remove empty
        $menu_list_str = implode("\n", $filtered_menus);
    } else {
        $menu_list_str = $menus;
    }

    if (empty($menu_list_str)) {
         echo json_encode(['success' => false, 'message' => 'Minimal isi satu Menu ERP!']);
         exit;
    }

    // Format tanggal
    $tgl_sql = null;
    if (!empty($tgl_form) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_form)) {
        $tgl_sql = $tgl_form;
    } else {
         $tgl_sql = date('Y-m-d'); // Default today if invalid
    }

    // Koneksi
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

    // Generate ticket: GRT-DDMMYYYY-XXX
    function generateTicketGRT($conn) {
        $today = date('dmY');
        $like = "GRT-$today-%";
        $sql = "SELECT TOP 1 ticket FROM Form_Grant_Revoke_Trustee WHERE ticket LIKE ? ORDER BY ticket DESC";
        $stmt = sqlsrv_query($conn, $sql, [$like]);
        $last = 0;
        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row && isset($row['ticket'])) {
                $parts = explode('-', $row['ticket']);
                $last = isset($parts[2]) ? intval($parts[2]) : 0;
            }
            sqlsrv_free_stmt($stmt);
        }
        $next = str_pad($last+1, 3, '0', STR_PAD_LEFT);
        return "GRT-$today-$next";
    }
    $ticket = generateTicketGRT($conn);

    $sql = "INSERT INTO Form_Grant_Revoke_Trustee
        (ticket, nama_pemohon, jabatan, departemen, area, tgl_pengajuan,
         jenis_permintaan, menu_akses, keterangan, status_ticket, kategori, created_at, created_by)
        VALUES (?,?,?,?,?,?, ?,?,?, 'Pending', 'Grant/Revoke Trustee', GETDATE(), ?)";
    
    $params = [
        $ticket, $nama, $jab, $dept, $area, $tgl_sql,
        $jenis, $menu_list_str, $keterangan, $createdBy
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        echo json_encode(['success'=>false,'message'=>'Gagal insert Grant/Revoke: '.print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    echo json_encode(['success'=>true,'message'=>'Pengajuan Grant/Revoke Trustee berhasil disimpan','ticket'=>$ticket]);
}
?>

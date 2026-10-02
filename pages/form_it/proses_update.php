<?php
session_start();

// Set header untuk JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

// Check if ticket is provided
$ticket = $_POST['ticket'] ?? '';
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket is required for update!']);
    exit;
}

if (stripos($ticket, 'CCTV-P-') === 0) {
    $_POST['form_type'] = 'pemindahan_kamera_cctv';
    require __DIR__ . '/proses_simpan.php';
    exit;
}

// Deteksi tipe tiket (CCTV, Internet Access, atau Perangkat IT)
$isCCTV = stripos($ticket,'CCTV-') === 0;
$isInternet = stripos($ticket,'INET-') === 0;
$isGrantRevoke = stripos($ticket,'GRT-') === 0;
$isDatabase = stripos($ticket,'DB-') === 0;
$isClosing = stripos($ticket,'CLS-') === 0;
$isGudangBaruErp = stripos($ticket,'GDG-') === 0;

// VALIDASI MINIMAL (hanya untuk perangkat IT, CCTV dan Internet dan GRT dan Database tidak punya array pengajuan/peripheral)
if (!$isCCTV && !$isInternet && !$isGrantRevoke && !$isDatabase && !$isClosing && !$isGudangBaruErp && empty($_POST['pengajuan']) && empty($_POST['peripheral'])) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada item yang dikirim!']);
    exit;
}

// Ambil data form
$nama        = $_POST['nama_pemohon'] ?? '';
$jab         = $_POST['jabatan'] ?? '';
$tgl_form    = $_POST['tgl_pengajuan'] ?? '';
$dept        = $_POST['departemen'] ?? '';
$bagian      = $_POST['bagian'] ?? ($_POST['area'] ?? ''); // CCTV memakai 'area'
$spesifikasi = $_POST['spesifikasi'] ?? '';
$keterangan  = $_POST['keterangan'] ?? '';
$updatedByName = $_SESSION['NamaLengkap'] ?? $nama; // Nama lengkap (jika perlu ditampilkan)
$updatedById   = $_SESSION['UserId'] ?? 0; // Numeric UserId untuk audit kolom updated_by

// Format tanggal ke SQL Server (normalize to 'YYYY-MM-DD' or NULL)
$tgl_sql = null;
if (!empty($tgl_form)) {
    $parts = explode("-", $tgl_form);
    if (count($parts) == 3 && strlen($parts[0])==2) {
        $tgl_sql = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_form)) {
        $tgl_sql = $tgl_form;
    }
}

// Convert array pengajuan/peripheral ke string (hanya IT)
$pengajuan_str  = (!$isCCTV && !empty($_POST['pengajuan'])) ? implode(",", $_POST['pengajuan']) : "";
$peripheral_str = (!$isCCTV && !empty($_POST['peripheral'])) ? implode(",", $_POST['peripheral']) : "";

// Helper function untuk ambil qty (hanya relevan untuk IT)
$getQty = function($key) use ($isCCTV) {
    if ($isCCTV) return 0; // CCTV tidak punya qty item/peripheral
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
   KONEKSI SQL SERVER TERPUSAT
================================ */
require __DIR__ . '/../../koneksi.php';

/* ================================
   UPDATE DATA (branch CCTV atau IT)
================================ */
if ($isCCTV) {
    // Ambil field khusus CCTV
    $tanggal_1    = $_POST['tanggal_1'] ?? '';
    $jam_mulai_1  = $_POST['jam_mulai_1'] ?? '';
    $jam_selesai_1= $_POST['jam_selesai_1'] ?? '';
    $tanggal_2    = $_POST['tanggal_2'] ?? '';
    $jam_mulai_2  = $_POST['jam_mulai_2'] ?? '';
    $jam_selesai_2= $_POST['jam_selesai_2'] ?? '';

    // Format tanggal periode CCTV - HTML5 date input sudah mengirim YYYY-MM-DD
    $fmtDate = function($d){
        if (empty($d)) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        $p = explode('-', $d);
        if (count($p)==3 && strlen($p[0])==2) return $p[2].'-'.$p[1].'-'.$p[0];
        return null;
    };
    $tanggal_1_sql = $fmtDate($tanggal_1);
    $tanggal_2_sql = $fmtDate($tanggal_2);

    $sql = "UPDATE Form_Pengajuan_CCTV SET
        nama_pemohon = ?,
        jabatan = ?,
        departemen = ?,
        area = ?,
        tgl_pengajuan = ?,
        tanggal_1 = ?,
        jam_mulai_1 = ?,
        jam_selesai_1 = ?,
        tanggal_2 = ?,
        jam_mulai_2 = ?,
        jam_selesai_2 = ?,
        keterangan = ?,
        updated_at = GETDATE(),
        updated_by = ?
    WHERE ticket = ?";
    $params = [
        $nama, $jab, $dept, $bagian, $tgl_sql,
        $tanggal_1_sql, $jam_mulai_1, $jam_selesai_1,
        $tanggal_2_sql, $jam_mulai_2, $jam_selesai_2,
        $keterangan,
        $updatedById,
        $ticket
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
    // Optional: if later columns added, we can extend logic here.
} elseif ($isDatabase) {
    $bagian      = $_POST['bagian'] ?? '';
    // Checkboxes
    $reqAdd      = !empty($_POST['req_add']) ? 1 : 0;
    $reqEdit     = !empty($_POST['req_edit']) ? 1 : 0;
    $reqDelete   = !empty($_POST['req_delete']) ? 1 : 0;
    
    $appProint   = !empty($_POST['app_proint']) ? 1 : 0;
    $appHris     = !empty($_POST['app_hris']) ? 1 : 0;
    $appLainnya  = !empty($_POST['app_lainnya']) ? 1 : 0;
    $appLainnyaText = $_POST['app_lainnya_text'] ?? '';
    
    $perubahan   = $_POST['perubahan'] ?? '';

    $sql = "UPDATE Form_Perubahan_Data_Database SET
        nama_pemohon = ?,
        jabatan = ?,
        departemen = ?,
        bagian = ?,
        tgl_pengajuan = ?,
        req_add = ?, req_edit = ?, req_delete = ?,
        app_proint = ?, app_hris = ?, app_lainnya = ?, app_lainnya_text = ?,
        perubahan = ?, keterangan = ?,
        updated_at = GETDATE(),
        updated_by = ?
    WHERE ticket = ?";

    $params = [
        $nama, $jab, $dept, $bagian, $tgl_sql,
        $reqAdd, $reqEdit, $reqDelete,
        $appProint, $appHris, $appLainnya, $appLainnyaText,
        $perubahan, $keterangan, 
        $updatedByName, 
        $ticket
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
} elseif ($isGrantRevoke) {
     $isGrantRevoke = stripos($ticket,'GRT-') === 0;
     $jenis = $_POST['jenis_permintaan'] ?? '';
     $area = $_POST['area'] ?? '';
     $menu_items = $_POST['menu_akses'] ?? [];
     $menu_list_str = '';
     if (is_array($menu_items)) {
        $filtered = array_filter($menu_items, function($x){ return trim($x) !== ''; });
        $menu_list_str = implode("\n", $filtered);
    }

    $sql = "UPDATE Form_Grant_Revoke_Trustee SET
        nama_pemohon = ?,
        jabatan = ?,
        departemen = ?,
        area = ?,
        tgl_pengajuan = ?,
        jenis_permintaan = ?,
        menu_akses = ?,
        keterangan = ?,
        updated_at = GETDATE(),
        updated_by = ?
    WHERE ticket = ?";
    
     $params = [
        $nama, $jab, $dept, $area, $tgl_sql,
        $jenis, $menu_list_str, $keterangan,
        $updatedByName, // using name for updated_by in this table? or id? check create. create uses names? No create uses Login Name.
        $ticket
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);

} elseif ($isClosing) {
    $reqGudang   = !empty($_POST['request_gudang']) ? 1 : 0;
    $reqTransaksi = !empty($_POST['request_transaksi']) ? 1 : 0;
    $bukaTgl     = $_POST['buka_tgl'] ?? '';
    $gudangTransaksi = $_POST['gudang_transaksi'] ?? '';
    
    $fmtDate = function($d){
        if (empty($d)) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        $p = explode('-', $d);
        if (count($p)==3 && strlen($p[0])==2) return $p[2].'-'.$p[1].'-'.$p[0];
        return null;
    };
    $buka_tgl_sql = $fmtDate($bukaTgl);

    $sql = "UPDATE Form_Buka_Tanggal_Closingan SET
        nama_pemohon = ?,
        jabatan = ?,
        departemen = ?,
        bagian = ?,
        tgl_pengajuan = ?,
        request_gudang = ?,
        request_transaksi = ?,
        buka_tgl = ?,
        gudang_transaksi = ?,
        keterangan = ?,
        updated_at = GETDATE(),
        updated_by = ?
    WHERE ticket = ?";
    
    $params = [
        $nama, $jab, $dept, $bagian, $tgl_sql,
        $reqGudang, $reqTransaksi, $buka_tgl_sql, $gudangTransaksi, $keterangan,
        $updatedByName,
        $ticket
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
} elseif ($isGudangBaruErp) {
    $namaGudang = trim($_POST['nama_gudang_baru'] ?? '');
    $userAkses  = trim($_POST['user_akses_gudang'] ?? '');

    $sql = "UPDATE dbo.Form_Penambahan_Gudang_Baru_ERP SET
        nama_pemohon = ?,
        jabatan = ?,
        departemen = ?,
        bagian = ?,
        tgl_pengajuan = ?,
        nama_gudang_baru = ?,
        user_akses_gudang = ?,
        keterangan = ?,
        updated_at = GETDATE(),
        updated_by = ?
    WHERE ticket = ?";

    $params = [
        $nama, $jab, $dept, $bagian, $tgl_sql,
        $namaGudang, $userAkses, $keterangan,
        $updatedByName,
        $ticket
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
} elseif ($isInternet) {
    // Ambil field khusus Internet Access (termasuk bandwidth)
    $reqAkses    = !empty($_POST['request_akses_internet']) ? 1 : 0;
    $aksesType   = $_POST['akses_type'] ?? '';
    $akses_from  = $_POST['akses_temporary_from'] ?? '';
    $akses_to    = $_POST['akses_temporary_to'] ?? '';

    $reqBw       = !empty($_POST['request_tambah_bandwidth']) ? 1 : 0;
    $bwType      = $_POST['bandwidth_type'] ?? '';
    $bw_from     = $_POST['bandwidth_temporary_from'] ?? '';
    $bw_to       = $_POST['bandwidth_temporary_to'] ?? '';
    $tambah_bw   = isset($_POST['tambah_bandwidth']) && $_POST['tambah_bandwidth'] !== '' ? intval($_POST['tambah_bandwidth']) : null;
    $tambah_bw_unit = $_POST['tambah_bandwidth_unit'] ?? null;

    // parse dates using same helper logic as before
    $fmtDate = function($d){
        if (empty($d)) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        $p = explode('-', $d);
        if (count($p)==3 && strlen($p[0])==2) return $p[2].'-'.$p[1].'-'.$p[0];
        return null;
    };
    $akses_from_sql = $fmtDate($akses_from);
    $akses_to_sql = $fmtDate($akses_to);
    $bw_from_sql = $fmtDate($bw_from);
    $bw_to_sql = $fmtDate($bw_to);

    // Build durasi_temporary string for backward compatibility
    $durasi_temporary = null;
    if (!empty($akses_from) || !empty($akses_to)) {
        $df = $akses_from ? (preg_match('/^\d{4}-\d{2}-\d{2}$/',$akses_from_sql) ? date('d-m-Y', strtotime($akses_from_sql)) : $akses_from) : '';
        $dt = $akses_to ? (preg_match('/^\d{4}-\d{2}-\d{2}$/',$akses_to_sql) ? date('d-m-Y', strtotime($akses_to_sql)) : $akses_to) : '';
        $durasi_temporary = trim($df) . ' s/d ' . trim($dt);
    }

    $sql = "UPDATE Form_Pengajuan_Akses_Internet SET
        nama_pemohon = ?,
        jabatan = ?,
        departemen = ?,
        area = ?,
        tgl_pengajuan = ?,
        request_akses_internet = ?,
        akses_type = ?,
        akses_temporary_from = ?,
        akses_temporary_to = ?,
        durasi_temporary = ?,
        request_tambah_bandwidth = ?,
        bandwidth_type = ?,
        bandwidth_temporary_from = ?,
        bandwidth_temporary_to = ?,
        tambah_bandwidth = ?,
        tambah_bandwidth_unit = ?,
        keterangan = ?,
        updated_at = GETDATE(),
        updated_by = ?
    WHERE ticket = ?";
    $params = [
        $nama, $jab, $dept, $bagian, $tgl_sql,
        $reqAkses, $aksesType, $akses_from_sql, $akses_to_sql, $durasi_temporary,
        $reqBw, $bwType, $bw_from_sql, $bw_to_sql, $tambah_bw, $tambah_bw_unit,
        $keterangan,
        $updatedById,
        $ticket
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
} else {
    $sql = "UPDATE Form_Pengajuan_Barang SET
        nama_pemohon = ?,
        jabatan = ?,
        tgl_pengajuan = ?,
        departemen = ?,
        bagian = ?,
        pengajuan = ?,
        qty_komputer = ?,
        qty_laptop = ?,
        qty_tablet = ?,
        qty_handphone = ?,
        qty_lainnya = ?,
        ket_lainnya = ?,
        spesifikasi = ?,
        peripheral = ?,
        qty_perip_keyboard = ?,
        qty_perip_mouse = ?,
        qty_perip_monitor = ?,
        qty_perip_printer = ?,
        qty_perip_scanner = ?,
        qty_perip_lainnya = ?,
        ket_perip_lainnya = ?,
        keterangan = ?,
        updated_at = GETDATE(),
        updated_by = ?
    WHERE ticket = ?";
    $params = [
        $nama, $jab, $tgl_sql, $dept, $bagian,
        $pengajuan_str, $qty_komputer, $qty_laptop, $qty_tablet, $qty_handphone, $qty_lainnya, $ket_lainnya,
        $spesifikasi,
        $peripheral_str, $qty_perip_keyboard, $qty_perip_mouse, $qty_perip_monitor, $qty_perip_printer, $qty_perip_scanner, $qty_perip_lainnya, $ket_perip_lainnya,
        $keterangan,
        $updatedById,
        $ticket
    ];
    $stmt = sqlsrv_query($conn, $sql, $params);
}

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Gagal update: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui!', 'ticket' => $ticket, 'isCCTV' => $isCCTV]);


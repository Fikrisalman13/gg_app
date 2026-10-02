<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
if (!file_exists($koneksiPath)) {
    $koneksiPath = dirname(__DIR__, 2) . '/koneksi.php';
}
if (file_exists($koneksiPath)) {
    require_once $koneksiPath;
} else {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak ditemukan']);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Generate Ticket: COD-YYMMDD-XXX
$prefix = 'COD-' . date('ymd') . '-';
$sqlSeq = "SELECT TOP 1 ticket FROM dbo.Form_Purchasing_COD WHERE ticket LIKE ? ORDER BY ticket DESC";
$stmtSeq = sqlsrv_query($conn, $sqlSeq, [$prefix . '%']);
$nextSeq = 1;
if ($stmtSeq && $row = sqlsrv_fetch_array($stmtSeq, SQLSRV_FETCH_ASSOC)) {
    $lastTicket = $row['ticket'];
    $parts = explode('-', $lastTicket);
    if (isset($parts[2])) {
        $nextSeq = intval($parts[2]) + 1;
    }
}
$ticket = $prefix . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);

// Capture input fields
$no_receipt = trim($_POST['no_receipt'] ?? '');
$tgl_receipt = !empty($_POST['tgl_receipt']) ? $_POST['tgl_receipt'] : null;
$cash_bank_account = trim($_POST['cash_bank_account'] ?? '');
$supplier = trim($_POST['supplier'] ?? '');
$dibayar_kepada = trim($_POST['dibayar_kepada'] ?? '');
$rekening_ac = trim($_POST['rekening_ac'] ?? '');
$bank = trim($_POST['bank'] ?? '');
$currency = trim($_POST['currency'] ?? 'IDR');
$keterangan = trim($_POST['keterangan'] ?? '');
$ref_po_no = trim($_POST['ref_po_no'] ?? '');
$tgl_pengajuan = !empty($_POST['tgl_pengajuan']) ? $_POST['tgl_pengajuan'] : date('Y-m-d');
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$items_json = $_POST['items_json'] ?? '[]';
$subtotal = floatval($_POST['subtotal'] ?? 0);
require_once __DIR__ . '/helper_terbilang.php';
$terbilang = ($subtotal > 0) ? getTerbilangLengkapPHP($subtotal) : trim($_POST['terbilang'] ?? '');
$dibuat_oleh = trim($_POST['dibuat_oleh'] ?? '');
$dicek_oleh = trim($_POST['dicek_oleh'] ?? '');
$nama_pemohon = trim($_POST['nama_pemohon'] ?? ($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']));
$departemen = trim($_POST['departemen'] ?? '');
$created_by = $_SESSION['UserName'] ?? 'system';
$ttd_dibuat_oleh = !empty($_POST['ttd_dibuat_oleh']) ? trim($_POST['ttd_dibuat_oleh']) : null;

// Pastikan kolom tanda tangan ada di tabel jika tabel sudah terbuat sebelumnya
$colCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Purchasing_COD' AND COLUMN_NAME = 'ttd_dibuat_oleh'");
if ($colCheck && !sqlsrv_fetch_array($colCheck)) {
    sqlsrv_query($conn, "ALTER TABLE dbo.Form_Purchasing_COD ADD ttd_dibuat_oleh NVARCHAR(MAX) NULL, ttd_dicek_oleh NVARCHAR(MAX) NULL, tgl_dicek DATETIME NULL");
}

// Handle upload lampiran jika ada
$lampiranPath = null;
if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (in_array($ext, $allowed)) {
        $newFilename = 'COD_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $destPath = $uploadDir . $newFilename;
        if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $destPath)) {
            $lampiranPath = 'uploads/' . $newFilename;
        }
    }
}

$sql = "INSERT INTO dbo.Form_Purchasing_COD (
            ticket, no_receipt, tgl_receipt, cash_bank_account, supplier, 
            dibayar_kepada, rekening_ac, bank, currency, keterangan, 
            ref_po_no, tgl_pengajuan, due_date, items_json, subtotal, 
            terbilang, dibuat_oleh, dicek_oleh, nama_pemohon, departemen, 
            status, lampiran_path, ttd_dibuat_oleh, created_by, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 
            'Pending', ?, ?, ?, GETDATE()
        )";

$params = [
    $ticket, $no_receipt, $tgl_receipt, $cash_bank_account, $supplier,
    $dibayar_kepada, $rekening_ac, $bank, $currency, $keterangan,
    $ref_po_no, $tgl_pengajuan, $due_date, $items_json, $subtotal,
    $terbilang, $dibuat_oleh, $dicek_oleh, $nama_pemohon, $departemen,
    $lampiranPath, $ttd_dibuat_oleh, $created_by
];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $errMsg = 'Gagal menyimpan ke database';
    if (!empty($errors)) {
        $errMsg .= ': ' . $errors[0]['message'];
    }
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

// Simpan juga baris rincian ke Form_Purchasing_COD_Detail jika tabel tersedia
if (!empty($items_json)) {
    $detailCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_COD_Detail'");
    if ($detailCheck && sqlsrv_fetch_array($detailCheck)) {
        $parsedItems = json_decode($items_json, true);
        if (is_array($parsedItems)) {
            $rowNo = 1;
            foreach ($parsedItems as $item) {
                $sqlDet = "INSERT INTO dbo.Form_Purchasing_COD_Detail (ticket, row_no, keterangan, cd, sifat, currency, dpp, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
                $ket = trim($item['keterangan'] ?? '');
                $cd = trim($item['cd'] ?? 'D');
                $sifat = trim($item['sifat'] ?? '+');
                $curr = trim($item['curr'] ?? 'IDR');
                $dpp = floatval($item['dpp'] ?? 0);
                sqlsrv_query($conn, $sqlDet, [$ticket, $rowNo, $ket, $cd, $sifat, $curr, $dpp]);
                $rowNo++;
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'ticket' => $ticket,
    'message' => 'Pengajuan Cash On Delivery berhasil disimpan!'
]);

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

$ticket = trim($_POST['ticket'] ?? '');
if (empty($ticket)) {
    echo json_encode(['success' => false, 'message' => 'Ticket tidak valid']);
    exit;
}

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
$updated_by = $_SESSION['UserName'] ?? 'system';

$sql = "UPDATE dbo.Form_Purchasing_COD SET
            no_receipt = ?, tgl_receipt = ?, cash_bank_account = ?, supplier = ?, 
            dibayar_kepada = ?, rekening_ac = ?, bank = ?, currency = ?, 
            keterangan = ?, ref_po_no = ?, tgl_pengajuan = ?, due_date = ?, 
            items_json = ?, subtotal = ?, terbilang = ?, dibuat_oleh = ?, 
            dicek_oleh = ?, updated_by = ?, updated_at = GETDATE()
        WHERE ticket = ?";

$params = [
    $no_receipt, $tgl_receipt, $cash_bank_account, $supplier,
    $dibayar_kepada, $rekening_ac, $bank, $currency,
    $keterangan, $ref_po_no, $tgl_pengajuan, $due_date,
    $items_json, $subtotal, $terbilang, $dibuat_oleh,
    $dicek_oleh, $updated_by, $ticket
];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $errMsg = 'Gagal memperbarui data';
    if (!empty($errors)) {
        $errMsg .= ': ' . $errors[0]['message'];
    }
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

// Sinkronkan ke Form_Purchasing_COD_Detail jika tabel tersedia
if (!empty($items_json)) {
    $detailCheck = sqlsrv_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Form_Purchasing_COD_Detail'");
    if ($detailCheck && sqlsrv_fetch_array($detailCheck)) {
        sqlsrv_query($conn, "DELETE FROM dbo.Form_Purchasing_COD_Detail WHERE ticket = ?", [$ticket]);
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
    'message' => 'Pengajuan Cash On Delivery berhasil diperbarui!'
]);

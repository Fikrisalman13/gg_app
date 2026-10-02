<?php
// pages/resep_obat/save_resep.php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../koneksi.php';

header('Content-Type: application/json');

// Debug Logging
function logMsg($msg) {
    file_put_contents('debug_resep.log', date('Y-m-d H:i:s') . ": " . $msg . "\n", FILE_APPEND);
}

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['UserName'];

// Fix: Don't strip dots blindly. 
// For weight/plan_qty (type="number"), browser sends 100.50 (dot decimal).
// For receipe (type="text"), user sends 21,175 (comma decimal).
function cleanHeaderNum($str) {
    if ($str === '') return 0;
    // Assume standard float (dot decimal) from type="number"
    return floatval($str); 
}

// US Format: 3,410.50 (Comma thousand, Dot decimal)
function cleanUSNum($str) {
    if ($str === '') return 0;
    // Remove comma
    $val = str_replace(',', '', $str);
    return floatval($val);
}

function cleanReceipeNum($str) {
    // Legacy support or just alias?
    return cleanUSNum($str);
}

function cleanPriceNum($str) {
    // Input: "Rp 130.594,22" or "130594.22"
    // Remove Rp and spaces
    $val = str_replace(['Rp', ' '], '', $str);
    // Remove dots (thousand separator)
    $val = str_replace('.', '', $val);
    // Replace comma with dot (decimal separator)
    $val = str_replace(',', '.', $val);
    return floatval($val);
}

// Log Incoming Data
logMsg("POST Data: " . print_r($_POST, true));

$resep_id = $_POST['resep_id'] ?? '';
$no_cp = $_POST['no_cp'] ?? '';
if (trim($no_cp) === '') {
    $no_cp = '-';
}
$kode_warna = $_POST['kode_warna'] ?? '';
$color_name = $_POST['color_name'] ?? '';
$color_desc = $_POST['color_desc'] ?? '';
$lot_no = $_POST['lot_no'] ?? '';
$weight = cleanUSNum($_POST['weight'] ?? 0); // US Format
$plan_qty = cleanHeaderNum($_POST['plan_qty'] ?? 0);
$items = $_POST['items'] ?? [];

$kode_grey = $_POST['kode_grey'] ?? '';
$vlot = cleanHeaderNum($_POST['vlot'] ?? 0);

if (empty($kode_warna)) {
    echo json_encode(['status' => 'error', 'message' => 'Kode Warna wajib diisi']);
    exit;
}

$now = date('Y-m-d H:i:s');

if ($resep_id) {
    // UPDATE
    $sql = "UPDATE dbo.resep_obat SET no_cp = ?, kode_warna = ?, color_name = ?, color_desc = ?, lot_no = ?, weight = ?, plan_qty = ?, kode_grey = ?, vlot = ?, updated_at = ?, updated_by = ? WHERE id = ?";
    $params = [$no_cp, $kode_warna, $color_name, $color_desc, $lot_no, $weight, $plan_qty, $kode_grey, $vlot, $now, $user, $resep_id];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $err = print_r(sqlsrv_errors(), true);
        logMsg("Update Error: " . $err);
        echo json_encode(['status' => 'error', 'message' => 'Update Header Failed: ' . $err]);
        exit;
    }
    
    // Clear old details
    $delSql = "DELETE FROM dbo.resep_obat_detail WHERE id_resep = ?";
    sqlsrv_query($conn, $delSql, [$resep_id]);
    logMsg("Updated ID: $resep_id");
    $id = $resep_id; // For reference in response

} else {
    // INSERT
    // Using OUTPUT INSERTED.id is safer for some SQL Server drivers than multiple queries
    $sql = "INSERT INTO dbo.resep_obat (no_cp, kode_warna, color_name, color_desc, lot_no, weight, plan_qty, kode_grey, vlot, created_at, created_by) OUTPUT INSERTED.id VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [$no_cp, $kode_warna, $color_name, $color_desc, $lot_no, $weight, $plan_qty, $kode_grey, $vlot, $now, $user];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $err = print_r(sqlsrv_errors(), true);
        logMsg("Insert Error: " . $err);
        echo json_encode(['status' => 'error', 'message' => 'Insert Header Failed: ' . $err]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $id = $row['id'] ?? null;
    
    if (!$id) {
        logMsg("Insert Success but No ID returned");
        echo json_encode(['status' => 'error', 'message' => 'Gagal mendapatkan ID Resep baru']);
        exit;
    }
    logMsg("Inserted New ID: $id");
}

// Insert Details
$errDetail = '';
if (!empty($items) && is_array($items)) {
    $sqlDetail = "INSERT INTO dbo.resep_obat_detail (id_resep, kode, name, category, receipe, uom, cf, uom_cf, std_price, total, created_at, created_by, price_satuan, price_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    foreach ($items as $item) {
        $kode = $item['kode'];
        $name = $item['name'];
        $category = $item['category'] ?? ''; // New
        
        // Clean Parsing
        $receipe = cleanUSNum($item['receipe']);
        
        $uom = $item['uom'];
        // $cc = $item['cc']; // Removed
        $cf = cleanUSNum($item['cf'] ?? 0); // US Format
        $uom_cf = $item['uom_cf'] ?? ''; // New

        // Fix: Use cleanPriceNum
        $std_price = cleanPriceNum($item['std_price']); 
        
        // Calculate Total
        $total = $receipe * $std_price;
        
        // Apply G/L Logic (Same as input_resep.php)
        $uomUpper = strtoupper(trim($uom));
        if ($uomUpper === 'GR' || $uomUpper === 'G/L') {
            $total = $total / 1000;
        }
        $price_satuan = $item['price_satuan'] ?? '';
        $price_source = $item['price_source'] ?? '';
        
        $paramsDetail = [$id, $kode, $name, $category, $receipe, $uom, $cf, $uom_cf, $std_price, $total, $now, $user, $price_satuan, $price_source];
        $stmtD = sqlsrv_query($conn, $sqlDetail, $paramsDetail);
        if ($stmtD === false) {
            $e = print_r(sqlsrv_errors(), true);
            $errDetail .= "Item $kode Error: " . $e;
            logMsg("Detail Fail: $e");
        }
    }
}

if ($errDetail) {
    echo json_encode(['status' => 'success', 'id' => $id, 'warning' => 'Detail errors: ' . $errDetail]);
} else {
    echo json_encode(['status' => 'success', 'id' => $id]);
}
?>

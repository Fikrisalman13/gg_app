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

// Parameter Mesin
$speed = cleanHeaderNum($_POST['speed'] ?? '');
$temperature = cleanHeaderNum($_POST['temperature'] ?? '');
$nilai_l = cleanHeaderNum($_POST['nilai_l'] ?? '');
$nilai_a = cleanHeaderNum($_POST['nilai_a'] ?? '');
$nilai_b = cleanHeaderNum($_POST['nilai_b'] ?? '');

// Lampiran Foto
$lampiran_existing = $_POST['lampiran_existing'] ?? '';
$lampiran_path = $lampiran_existing;

// Lampiran PDF
$lampiran_pdf_existing = $_POST['lampiran_pdf_existing'] ?? '';
$lampiran_pdf_path = $lampiran_pdf_existing;

if (isset($_FILES['lampiran']) && is_array($_FILES['lampiran']) && $_FILES['lampiran']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['lampiran']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Upload lampiran gagal (kode: ' . $_FILES['lampiran']['error'] . ')']);
        exit;
    }

    $tmpName = $_FILES['lampiran']['tmp_name'];
    $origName = $_FILES['lampiran']['name'] ?? '';
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Format lampiran harus gambar (jpg/jpeg/png/gif/webp)']);
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../../uploads');
    if ($uploadDir === false) {
        echo json_encode(['status' => 'error', 'message' => 'Folder uploads tidak ditemukan']);
        exit;
    }

    $targetSubDir = $uploadDir . DIRECTORY_SEPARATOR . 'resep_obat';
    if (!is_dir($targetSubDir)) {
        @mkdir($targetSubDir, 0777, true);
    }

    if (!is_dir($targetSubDir)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat folder uploads/resep_obat']);
        exit;
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
    if ($safeBase === '') $safeBase = 'lampiran';
    $newName = 'lampiran_' . date('Ymd_His') . '_' . $safeBase . '.' . $ext;
    $targetFs = $targetSubDir . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($tmpName, $targetFs)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file upload']);
        exit;
    }

    // Deletion of old file
    if (!empty($lampiran_existing)) {
        // Map public path to filesystem path
        // Example: /gg_app/uploads/resep_obat/filename.jpg -> rel: ../../uploads/resep_obat/filename.jpg
        $relPath = str_replace('/gg_app/uploads/', '../../uploads/', $lampiran_existing);
        $oldFileFs = realpath(__DIR__ . '/' . $relPath);
        if ($oldFileFs && is_file($oldFileFs)) {
            @unlink($oldFileFs);
        }
    }

    // Public path
    $lampiran_path = '/gg_app/uploads/resep_obat/' . $newName;
}

// Handle PDF Upload
if (isset($_FILES['lampiran_pdf']) && is_array($_FILES['lampiran_pdf']) && $_FILES['lampiran_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['lampiran_pdf']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Upload lampiran PDF gagal (kode: ' . $_FILES['lampiran_pdf']['error'] . ')']);
        exit;
    }

    $tmpName = $_FILES['lampiran_pdf']['tmp_name'];
    $origName = $_FILES['lampiran_pdf']['name'] ?? '';
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    
    if ($ext !== 'pdf') {
        echo json_encode(['status' => 'error', 'message' => 'Format lampiran harus PDF']);
        exit;
    }

    $uploadDir = realpath(__DIR__ . '/../../uploads');
    $targetSubDir = $uploadDir . DIRECTORY_SEPARATOR . 'resep_obat';
    if (!is_dir($targetSubDir)) {
        @mkdir($targetSubDir, 0777, true);
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
    if ($safeBase === '') $safeBase = 'lampiran';
    $newName = 'doc_' . date('Ymd_His') . '_' . $safeBase . '.' . $ext;
    $targetFs = $targetSubDir . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($tmpName, $targetFs)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file PDF upload']);
        exit;
    }

    // Deletion of old PDF
    if (!empty($lampiran_pdf_existing)) {
        $relPath = str_replace('/gg_app/uploads/', '../../uploads/', $lampiran_pdf_existing);
        $oldFileFs = realpath(__DIR__ . '/' . $relPath);
        if ($oldFileFs && is_file($oldFileFs)) {
            @unlink($oldFileFs);
        }
    }

    $lampiran_pdf_path = '/gg_app/uploads/resep_obat/' . $newName;
}

if (empty($kode_warna)) {
    echo json_encode(['status' => 'error', 'message' => 'Kode Warna wajib diisi']);
    exit;
}

$now = date('Y-m-d H:i:s');

$resepprodcode = $_POST['resepprodcode'] ?? '';
$resepprodname = $_POST['resepprodname'] ?? '';

// ProInt Details
$resep_no   = $_POST['resep_no'] ?? '';
$resep_seq  = $_POST['resep_seq'] ?? NULL;
$resep_date = $_POST['resep_date'] ?? NULL;
$resep_type = $_POST['resep_type'] ?? '';
$is_manual  = $_POST['is_manual'] ?? 0;

// New Fields
$no_cp_resep = $_POST['no_cp_resep'] ?? '';
if (trim($no_cp_resep) === '') $no_cp_resep = '-';

$no_so = $_POST['no_so'] ?? '';
if (trim($no_so) === '') $no_so = '-';

$rtg_code = $_POST['rtg_code'] ?? '';
if (trim($rtg_code) === '') $rtg_code = '-';

$rtg_name = $_POST['rtg_name'] ?? '';
if (trim($rtg_name) === '') $rtg_name = '-';

$status_desc = $_POST['status_desc'] ?? '';
if (trim($status_desc) === '') $status_desc = '-';

$cus_color = $_POST['cus_color'] ?? '';
if (trim($cus_color) === '') {
    // Fallback: ambil segmen pertama dari Description (color_desc) sebelum '/'
    // Hanya jika Description mengandung '/', contoh: "0604/1616NEW/..." -> "0604"
    // Jika tidak ada '/' (misal "0.1.0331.0.0"), simpan '-'
    $desc_trimmed = trim($color_desc);
    if ($desc_trimmed !== '' && strpos($desc_trimmed, '/') !== false) {
        $desc_parts = explode('/', $desc_trimmed);
        $cus_color = trim($desc_parts[0]) !== '' ? trim($desc_parts[0]) : '-';
    } else {
        $cus_color = '-';
    }
}

if ($resep_id) {
    // UPDATE
    $sql = "UPDATE dbo.resep_obat SET no_cp = ?, kode_warna = ?, color_name = ?, color_desc = ?, lot_no = ?, weight = ?, plan_qty = ?, kode_grey = ?, vlot = ?, resep_prod_code = ?, resep_prod_name = ?, resep_no = ?, resep_seq =?, resep_date = ?, resep_type = ?, is_manual = ?, updated_at = ?, updated_by = ?, no_cp_resep = ?, no_so = ?, rtg_code = ?, rtg_name = ?, status_desc = ?, cus_color = ?, speed = ?, temperature = ?, nilai_l = ?, nilai_a = ?, nilai_b = ?, lampiran_path = ?, lampiran_pdf_path = ? WHERE id = ?";
    $params = [$no_cp, $kode_warna, $color_name, $color_desc, $lot_no, $weight, $plan_qty, $kode_grey, $vlot, $resepprodcode, $resepprodname, $resep_no, $resep_seq, $resep_date, $resep_type, $is_manual, $now, $user, $no_cp_resep, $no_so, $rtg_code, $rtg_name, $status_desc, $cus_color, $speed, $temperature, $nilai_l, $nilai_a, $nilai_b, $lampiran_path, $lampiran_pdf_path, $resep_id];
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
    $sql = "INSERT INTO dbo.resep_obat (no_cp, kode_warna, color_name, color_desc, lot_no, weight, plan_qty, kode_grey, vlot, resep_prod_code, resep_prod_name, resep_no, resep_seq, resep_date, resep_type, is_manual, created_at, created_by, no_cp_resep, no_so, rtg_code, rtg_name, status_desc, cus_color, speed, temperature, nilai_l, nilai_a, nilai_b, lampiran_path, lampiran_pdf_path) OUTPUT INSERTED.id VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [$no_cp, $kode_warna, $color_name, $color_desc, $lot_no, $weight, $plan_qty, $kode_grey, $vlot, $resepprodcode, $resepprodname, $resep_no, $resep_seq, $resep_date, $resep_type, $is_manual, $now, $user, $no_cp_resep, $no_so, $rtg_code, $rtg_name, $status_desc, $cus_color, $speed, $temperature, $nilai_l, $nilai_a, $nilai_b, $lampiran_path, $lampiran_pdf_path];
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
    $sqlDetail = "INSERT INTO dbo.resep_obat_detail (id_resep, kode, name, category, receipe, uom, cf, uom_cf, std_price, total, created_at, created_by, price_satuan, price_source, is_manual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
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
        $item_manual  = $item['is_manual'] ?? 0;
        
        $paramsDetail = [$id, $kode, $name, $category, $receipe, $uom, $cf, $uom_cf, $std_price, $total, $now, $user, $price_satuan, $price_source, $item_manual];
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

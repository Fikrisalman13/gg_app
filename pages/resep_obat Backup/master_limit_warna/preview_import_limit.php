<?php
// pages/resep_obat/master_limit_warna/preview_import_limit.php
require_once '../../../koneksi.php'; // For verifying limits in resep_limit_color (SQL Server)
require_once '../../../koneksi3.php'; // For verifying color codes in pdcolorms (PostgreSQL)
require_once '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if (!isset($_FILES['file']['tmp_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

try {
    $inputFile = $_FILES['file']['tmp_name'];
    $spreadsheet = IOFactory::load($inputFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Remove Header
    array_shift($rows); 
    
    // Check if second row is hint and remove
    if (isset($rows[0][0]) && strpos($rows[0][0], 'Kosongkan') !== false) {
        array_shift($rows);
    }

    // Pre-fetch existing limits for validation
    // Pre-fetch existing limits for validation
    $existingLimits = [];
    $stmtLimit = sqlsrv_query($conn, "SELECT kode_warna FROM resep_limit_color");
    if ($stmtLimit !== false) {
        while ($r = sqlsrv_fetch_array($stmtLimit, SQLSRV_FETCH_ASSOC)) {
            $existingLimits[trim($r['kode_warna'])] = true;
        }
    }

    $previewData = [];
    $validCount = 0;
    $seenInExcel = [];
    
    foreach ($rows as $index => $row) {
        // Skip empty rows
        if (empty($row[0])) continue;
        
        $kode = trim($row[0]);
        $maxCost = $row[1] ?? null;
        $maxDisc = $row[2] ?? null;
        $maxReact = $row[3] ?? null;
        $maxTotal = $row[4] ?? null;
        
        $isValid = true;
        $errors = [];
        $isUpdate = false;
        
        // 1. Validate Duplicate in Excel
        if (isset($seenInExcel[$kode])) {
            $isValid = false;
            $errors[] = "Data Duplikat di Excel";
        }
        $seenInExcel[$kode] = true;

        // 2. Validate Existing in DB
        if (isset($existingLimits[$kode])) {
            $isValid = false; // Prevent Update via Import
            $errors[] = "Data Limit Sudah Ada"; 
        }

        // 3. Validate Master Color (Must exist in pdcolorms)
        // Optimization: Prepare statement once outside loop? 
        // For distinct codes, checking one by one is OK for reasonable size.
        // For bulk, fetching all into array might be better but pdcolorms is huge.
        // Let's keep prepare/execute for now or optimize if needed.
        // Since we are inside loop, let's reuse prepared statement if possible.
        // But $conn3 is PDO.
        
        // Use global prepared statement for performance
        if (!isset($stmtCheckColor)) {
             $stmtCheckColor = $conn3->prepare("SELECT 1 FROM pdcolorms WHERE colorcode = :kode");
        }
        $stmtCheckColor->bindValue(':kode', $kode);
        $stmtCheckColor->execute();
        if (!$stmtCheckColor->fetch()) {
            $isValid = false;
            $errors[] = "Kode Warna Tidak Ditemukan Pada Master Color Pro-Int";
        }
        
        // Helper to parsing "Unlimit"
        $parseNum = function($v) {
            if ($v === null || trim($v) === '' || trim($v) === '-') return null;
            $v = str_replace(',', '.', $v); // Handle comma decimal if any
            if (!is_numeric($v)) return 'invalid';
            return floatval($v);
        };
        
        $cCost = $parseNum($maxCost);
        $cDisc = $parseNum($maxDisc);
        $cReact = $parseNum($maxReact);
        $cTotal = $parseNum($maxTotal);
        
        if ($cCost === 'invalid') { $isValid = false; $errors[] = "Format Cost salah"; }
        if ($cDisc === 'invalid') { $isValid = false; $errors[] = "Format Disperse salah"; }
        if ($cReact === 'invalid') { $isValid = false; $errors[] = "Format Reactive salah"; }
        if ($cTotal === 'invalid') { $isValid = false; $errors[] = "Format Total salah"; }
        
        if ($isValid) $validCount++;
        
        $previewData[] = [
            'valid' => $isValid,
            'is_update' => $isUpdate,
            'errors' => $errors,
            'kode' => $kode,
            'max_cost' => $cCost,
            'max_cf_disperse' => $cDisc,
            'max_cf_reactive' => $cReact,
            'max_cf_total' => $cTotal,
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        // 'html' => $html, // Moved to client-side
        'previewData' => $previewData,
        'validCount' => $validCount
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>

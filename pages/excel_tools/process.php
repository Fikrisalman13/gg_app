<?php
$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';

if (!file_exists($vendorAutoload)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'message' => "CRITICAL ERROR: File vendor/autoload.php tidak ditemukan di: " . realpath(__DIR__ . '/../../') . "/vendor/. \nSilahkan copy folder 'vendor' dari lokal ke server dengan lengkap."
    ]);
    exit;
}

require $vendorAutoload;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

ini_set('memory_limit', '512M');
set_time_limit(300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['fileExcel'])) {
    die("Invalid Request");
}

$file = $_FILES['fileExcel'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    die("Upload Error Code: " . $file['error']);
}

date_default_timezone_set('Asia/Jakarta');

$inputPath = $file['tmp_name'];

try {
    // Explicitly use FQCN to avoid any namespace ambiguity
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputPath);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setShowGridlines(false);
    $highestRow = $sheet->getHighestRow();
    
    // --- STEP 0: Auto-Detect Columns ---
    $headerRow = 0;
    $colMap = [];
    
    for ($r = 1; $r <= min(20, $highestRow); $r++) {
        $cellIterator = $sheet->getRowIterator($r)->current()->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $detected = [];
        foreach ($cellIterator as $cell) {
            $val = trim(strtolower($cell->getValue() ?? ''));
            $colLetter = $cell->getColumn();
            if ($val == 'no') $detected['no'] = $colLetter;
            if (strpos($val, 'tanggal') !== false && !isset($detected['date'])) $detected['date'] = $colLetter;
            if ($val == 'jam') $detected['time'] = $colLetter;
            if (strpos($val, 'nama') !== false) $detected['name'] = $colLetter;
        }
        if (isset($detected['no']) && isset($detected['date']) && isset($detected['name'])) {
            $headerRow = $r;
            $colMap = array_merge($colMap, $detected);
            if (isset($detected['time'])) {
                $colMap['time'] = $detected['time'];
            }
            break;
        }
    }
    
    $dataStartRow = ($headerRow > 0) ? $headerRow : (int)($_POST['start_row'] ?? 9);
    
    $cNo   = $colMap['no'] ?? 'A';
    $cDate = $colMap['date'] ?? strtoupper($_POST['col_date'] ?? 'B');
    $cTime = $colMap['time'] ?? strtoupper($_POST['col_time'] ?? 'C');
    $cName = $colMap['name'] ?? strtoupper($_POST['col_name'] ?? 'D');
    $cTarget = strtoupper($_POST['col_target'] ?? $cDate);

    // --- STEP 1: Remove Duplicates (Scoped per Section) ---
    // "per tabel data absensi" -> Reset duplicate cache when a new section title is found.
    $seenNames = [];
    $rowsToDelete = [];
    
    // Check if merge is enabled
    $enableMerge = isset($_POST['enable_merge']) && $_POST['enable_merge'] === 'on';

    for ($row = 1; $row <= $highestRow; $row++) {
        $valName = trim($sheet->getCell($cName . $row)->getValue() ?? '');
        $valA = trim($sheet->getCell('A' . $row)->getValue() ?? '');
        
        // 1. Detect New Section (Reset Cache)
        // Usually contains "DATA ABSENSI"
        if (stripos($valA, 'DATA ABSENSI') !== false) {
             $seenNames = []; // RESET
             continue;
        }
        
        // 2. Skip Header Rows
        if (stripos($valName, 'nama karyawan') !== false) continue;
        
        // 3. Skip Empty
        if ($valName === '') continue;
        
        // 4. Check Duplicate in Current Section
        if (isset($seenNames[$valName])) {
            $rowsToDelete[] = $row;
        } else {
            $seenNames[$valName] = true;
        }
    }
    
    rsort($rowsToDelete);
    foreach ($rowsToDelete as $r) {
        $sheet->removeRow($r, 1);
    }
    
    // Refresh
    $highestRow = $sheet->getHighestRow();
    
    // --- STEP 2: Processing, Renumbering, Styling (Row by Row) ---
    $globalSequence = 1;
    $regexDate = '/^(\d{1,4})[\/\-](\d{1,2})[\/\-](\d{1,4})/';
    $regexTime = '/^(\d{1,2})[\:\.](\d{1,2})(?:[\:\.](\d{1,2}))?/';
    
    // Styles
    $styleDataBorder = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]]
    ];
    $styleHeader = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']], 
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    $lastCol = $sheet->getHighestColumn();

    for ($row = 1; $row <= $highestRow; $row++) {
        $valName = trim($sheet->getCell($cName . $row)->getValue() ?? '');
        $valA = trim($sheet->getCell('A' . $row)->getValue() ?? '');
        
        // 1. Title Row ("DATA ABSENSI")
        if (stripos($valA, 'DATA ABSENSI') !== false) {
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            continue; 
        }
        
        // 2. Header Row
        if (stripos($valName, 'nama karyawan') !== false) {
             $sheet->getStyle("A$row:$lastCol$row")->applyFromArray($styleHeader);
             continue;
        }
        
        // 3. Data Row
        if ($valName !== '') {
            // RENUMBER: Update No column (Global Sequence)
            $sheet->setCellValue($cNo . $row, $globalSequence++);
            
            // CENTER No
            $sheet->getStyle($cNo . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // MERGE DATE/TIME (Only if enabled)
            if ($enableMerge) {
                $valDate = $sheet->getCell($cDate . $row)->getValue();
                $valTime = ($cTime && $cTime != $cDate) ? $sheet->getCell($cTime . $row)->getValue() : '';
                
                if ($valDate) {
                     $baseSerial = 0; $timeFraction = 0;
                     $dateFound = false;
                     
                     // Process Date
                     if (is_numeric($valDate)) {
                         $days = floor($valDate);
                         $timePart = $valDate - $days;
                         $baseSerial = $days;
                         if ($timePart > 0) $timeFraction = $timePart; 
                         $dateFound = true;
                     } else {
                         $strDate = trim((string)$valDate);
                         if (preg_match($regexDate, $strDate, $m)) {
                             list(, $d1, $d2, $d3) = $m;
                             if (strlen($d1) == 4) { $y=$d1; $m=$d2; $d=$d3; } else { $d=$d1; $m=$d2; $y=$d3;  }
                             if ($y < 100) $y+=2000;
                             // embedded time?
                             $embeddedTime = 0;
                             if (preg_match($regexTime, substr($strDate, 10), $tm)) {
                                 $h=$tm[1]; $i=$tm[2]; $s=$tm[3]??0;
                                 $embeddedTime = ($h*3600 + $i*60 + $s)/86400;
                             }
                             $baseSerial = Date::formattedPHPToExcel($y, $m, $d, 0,0,0);
                             $timeFraction = $embeddedTime;
                             $dateFound = true;
                         }
                     }
                     
                     // Process Time
                     if ($dateFound) {
                         if ($valTime !== '') {
                             if (is_numeric($valTime)) {
                                 $timeFraction = ($valTime < 1) ? $valTime : ($valTime - floor($valTime));
                             } else {
                                 $strTime = trim((string)$valTime);
                                 if (preg_match($regexTime, $strTime, $tm)) {
                                     $h=$tm[1]; $i=$tm[2]; $s=$tm[3]??0;
                                     $timeFraction = ($h*3600 + $i*60 + $s)/86400;
                                 }
                             }
                         }
                         // Final
                         $finalSerial = $baseSerial + $timeFraction;
                         $sheet->setCellValue($cTarget . $row, $finalSerial);
                         $sheet->getStyle($cTarget . $row)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
                         $sheet->getStyle($cTarget . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                     }
                }
            } else {
                // Fix Date Format to dd/mm/yyyy (Requested: 16-02-2026 -> 16/02/2026)
                $valDate = $sheet->getCell($cDate . $row)->getValue();
                if ($valDate) {
                    if (is_numeric($valDate)) {
                        $sheet->getStyle($cDate . $row)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } else {
                        $strDate = trim((string)$valDate);
                        if (preg_match($regexDate, $strDate, $m)) {
                             list(, $d1, $d2, $d3) = $m;
                             if (strlen($d1) == 4) { $y=$d1; $m=$d2; $d=$d3; } else { $d=$d1; $m=$d2; $y=$d3; }
                             if ($y < 100) $y+=2000;
                             $dateSerial = Date::formattedPHPToExcel($y, $m, $d, 0, 0, 0);
                             $sheet->setCellValue($cDate . $row, $dateSerial);
                             $sheet->getStyle($cDate . $row)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                        }
                    }
                    $sheet->getStyle($cDate . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
            
            // BORDERS
            $sheet->getStyle("A$row:$lastCol$row")->applyFromArray($styleDataBorder);
        }
    }
    
    // --- STEP 3: Cleanup ---
    if ($enableMerge && $cTime && $cTime !== $cTarget && $cTime !== $cName && $cTime !== $cNo) {
        $sheet->removeColumn($cTime);
    }
    
    // --- STEP 4: Footer ---
    $finalRow = $sheet->getHighestRow();
    $footerRow = $finalRow + 2;
    $totalRecords = $globalSequence - 1;
    
    $sheet->setCellValue('A' . $footerRow, "Total Data: " . $totalRecords . " record(s)");
    $sheet->getStyle('A' . $footerRow)->getFont()->setBold(true);

    // Set specific width for Column A (No)
    $sheet->getColumnDimension('A')->setWidth(17.29); 

    // AutoSize remaining columns
    $finalLastCol = $sheet->getHighestColumn();
    for ($col = 'B'; $col <= $finalLastCol; $col++) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    $filename = 'Processed_' . pathinfo($file['name'], PATHINFO_FILENAME) . '.xlsx';
    if(ob_get_length()) ob_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    die();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

#!/usr/bin/env php
<?php
/**
 * PDF Memory Optimization Template
 * 
 * Gunakan template ini untuk fix memory issues di file PDF export lainnya
 * 
 * File yang kemungkinan perlu di-fix:
 * - pages/form_builder/export_submissions_pdf.php
 * - pages/ticket/export_history_pdf.php
 * - pages/monfongs/export_*.php
 * - pages/monitoring_jaringan/export_*.php
 * - pages/sm_employee/export_emp_pdf.php
 */

echo "=== PDF Memory Optimization Guide ===\n\n";

echo "STEP 1: Add at the beginning of file (after <?php)\n";
echo "----------------------------------------\n";
echo <<<'CODE'
// Increase memory limit for PDF generation with large datasets
ini_set('memory_limit', '1024M');
// Increase execution time limit
set_time_limit(300); // 5 minutes

CODE;

echo "\n\nSTEP 2: Replace data loading pattern\n";
echo "----------------------------------------\n";
echo "BEFORE (BAD - loads all to memory):\n";
echo <<<'CODE'
$rows = [];
while ($record = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $record;
}
sqlsrv_free_stmt($stmt);

// Then later...
foreach ($rows as $record) {
    $tableRows .= '<tr>...</tr>';
}

CODE;

echo "\n\nAFTER (GOOD - streams data):\n";
echo <<<'CODE'
// Use output buffering
ob_start();

$counter = 1;
$hasData = false;

// Process rows one at a time
while ($record = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $hasData = true;
    
    // Build row HTML directly
    echo '<tr>...</tr>';
    
    // Free memory every 100 rows
    if ($counter % 100 === 0) {
        gc_collect_cycles();
    }
    $counter++;
}

if (!$hasData) {
    echo '<tr><td colspan="X">No data</td></tr>';
}

$tableRows = ob_get_clean();
sqlsrv_free_stmt($stmt);

CODE;

echo "\n\nSTEP 3: Optimize DomPDF configuration\n";
echo "----------------------------------------\n";
echo <<<'CODE'
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isFontSubsettingEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('debugKeepTemp', false);
$options->set('debugCss', false);
$options->set('debugLayout', false);
$options->set('debugLayoutLines', false);
$options->set('debugLayoutBlocks', false);
$options->set('debugLayoutInline', false);
$options->set('debugLayoutPaddingBox', false);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);

// Clear memory before rendering
unset($html);
unset($tableRows);
gc_collect_cycles();

$dompdf->render();

CODE;

echo "\n\nSTEP 4: Monitor memory usage (optional for debugging)\n";
echo "----------------------------------------\n";
echo <<<'CODE'
// Add before render
error_log("Memory before render: " . memory_get_usage(true) / 1024 / 1024 . " MB");
$dompdf->render();
error_log("Memory after render: " . memory_get_usage(true) / 1024 / 1024 . " MB");
error_log("Peak memory: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB");

CODE;

echo "\n\n=== Files to Check ===\n";
echo "Run this command to find files that might need optimization:\n";
echo "grep -r '\$rows = \[\];' pages/*pdf*.php\n";
echo "\nOr check files manually:\n";
$files = [
    'pages/form_builder/export_submissions_pdf.php',
    'pages/ticket/export_history_pdf.php',
    'pages/monfongs/export_monfongs1_pdf.php',
    'pages/monfongs/export_monfongs2_pdf.php',
    'pages/monitoring_jaringan/export_pdf_average_usage.php',
    'pages/sm_employee/export_emp_pdf.php',
];

foreach ($files as $file) {
    echo "  - $file\n";
}

echo "\n=== Quick Test ===\n";
echo "1. Test dengan data kecil (< 100 records)\n";
echo "2. Test dengan data medium (100-1000 records)\n";
echo "3. Test dengan data besar (> 1000 records)\n";
echo "4. Monitor Apache error log untuk memory errors\n";

echo "\n=== Done! ===\n";

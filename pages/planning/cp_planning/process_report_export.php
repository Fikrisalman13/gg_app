<?php
if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_start();

if (!defined('PROCESS_REPORT_EXPORT_BOOTSTRAP')) {
    define('PROCESS_REPORT_EXPORT_BOOTSTRAP', true);
}
require __DIR__ . '/process_report.php';

while (ob_get_level() > 0) {
    @ob_end_clean();
}
ob_start();

$rows = is_array($rows ?? null) ? $rows : [];
$processCardRows = is_array($processCardRows ?? null) ? $processCardRows : [];
$totalCpAllProcess = (int)($totalCpAllProcess ?? 0);
$allQtyTotal = (float)($allQtyTotal ?? 0.0);
$dateFrom = trim((string)($dateFrom ?? date('Y-m-01')));
$dateTo = trim((string)($dateTo ?? date('Y-m-d')));

$xmlEsc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
};

$toIsoDate = static function ($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i:s.u', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($raw);
    return ($ts !== false) ? date('Y-m-d', $ts) : '';
};

$formatDateDisplay = static function ($value) use ($toIsoDate): string {
    $iso = $toIsoDate($value);
    if ($iso === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $iso);
    return $dt ? $dt->format('d/m/Y') : $iso;
};

$formatIndoDate = static function (string $iso): string {
    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $dt = DateTime::createFromFormat('Y-m-d', $iso);
    if (!$dt) {
        return $iso;
    }
    $day = (int)$dt->format('j');
    $month = $months[(int)$dt->format('n')] ?? $dt->format('m');
    return $day . ' ' . $month . ' ' . $dt->format('Y');
};

$formatPercent = static function (float $value): string {
    return number_format($value, 1, ',', '.') . '%';
};

$strLen = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

$strSub = static function (string $value, int $start, int $length): string {
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, 'UTF-8');
    }
    return substr($value, $start, $length);
};

$sanitizeSheetName = static function (string $name) use ($strLen, $strSub): string {
    $clean = preg_replace('/[\\\\\\/?*\\[\\]:]/', ' ', $name) ?? $name;
    $clean = trim(preg_replace('/\\s+/', ' ', $clean) ?? $clean);
    if ($clean === '') {
        $clean = 'Sheet';
    }
    if ($strLen($clean) > 31) {
        $clean = $strSub($clean, 0, 31);
    }
    return $clean;
};

$uniqueSheetName = static function (string $name, array &$used) use ($sanitizeSheetName, $strLen, $strSub): string {
    $base = $sanitizeSheetName($name);
    if (!isset($used[strtoupper($base)])) {
        $used[strtoupper($base)] = true;
        return $base;
    }

    $counter = 2;
    while (true) {
        $suffix = ' (' . $counter . ')';
        $maxBaseLen = 31 - $strLen($suffix);
        $candidateBase = $base;
        if ($strLen($candidateBase) > $maxBaseLen) {
            $candidateBase = $strSub($candidateBase, 0, $maxBaseLen);
        }
        $candidate = $candidateBase . $suffix;
        $key = strtoupper($candidate);
        if (!isset($used[$key])) {
            $used[$key] = true;
            return $candidate;
        }
        $counter++;
    }
};

$buildCell = static function ($value, string $styleId = '', ?string $type = null, ?int $mergeAcross = null) use ($xmlEsc): string {
    $attrs = '';
    if ($styleId !== '') {
        $attrs .= ' ss:StyleID="' . $xmlEsc($styleId) . '"';
    }
    if ($mergeAcross !== null && $mergeAcross > 0) {
        $attrs .= ' ss:MergeAcross="' . (int)$mergeAcross . '"';
    }

    if ($type === null) {
        $type = (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value)) === 1))
            ? 'Number'
            : 'String';
    }

    if ($type === 'Number') {
        $number = is_numeric($value) ? (string)(0 + $value) : '0';
        return '<Cell' . $attrs . '><Data ss:Type="Number">' . $xmlEsc($number) . '</Data></Cell>';
    }
    return '<Cell' . $attrs . '><Data ss:Type="String">' . $xmlEsc((string)$value) . '</Data></Cell>';
};

$buildRow = static function (array $cells): string {
    return '<Row>' . implode('', $cells) . '</Row>';
};

$periodText = $formatIndoDate($dateFrom) . ' s/d ' . $formatIndoDate($dateTo);

$summaryRows = [];
$processOrder = [];
foreach ($processCardRows as $card) {
    $proc = trim((string)($card['process'] ?? ''));
    if ($proc === '') {
        continue;
    }
    $cpTotal = (int)($card['cp_total'] ?? 0);
    $qtyTotal = (float)($card['qty_total'] ?? 0.0);
    $cpPct = $totalCpAllProcess > 0 ? (($cpTotal / $totalCpAllProcess) * 100) : 0.0;
    $qtyPct = $allQtyTotal > 0 ? (($qtyTotal / $allQtyTotal) * 100) : 0.0;

    $summaryRows[] = [
        'process' => $proc,
        'process_label' => 'Proses ' . $proc,
        'cp_total' => $cpTotal,
        'cp_pct' => $cpPct,
        'qty_total' => $qtyTotal,
        'qty_pct' => $qtyPct,
    ];
    $processOrder[$proc] = $proc;
}

$rowsByProcess = [];
foreach ($rows as $row) {
    $proc = trim((string)($row['process'] ?? ''));
    if ($proc === '') {
        $proc = 'Unknown';
    }
    if (!isset($rowsByProcess[$proc])) {
        $rowsByProcess[$proc] = [];
    }
    $rowsByProcess[$proc][] = $row;
    if (!isset($processOrder[$proc])) {
        $processOrder[$proc] = $proc;
    }
}

$orderedProcesses = array_values($processOrder);

$xml = [];
$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
$xml[] = '<?mso-application progid="Excel.Sheet"?>';
$xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
$xml[] = ' xmlns:o="urn:schemas-microsoft-com:office:office"';
$xml[] = ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
$xml[] = ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
$xml[] = ' xmlns:html="http://www.w3.org/TR/REC-html40">';
$xml[] = '<Styles>';
$xml[] = '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>';
$xml[] = '  <Style ss:ID="sTitle"><Font ss:FontName="Calibri" ss:Size="13" ss:Bold="1"/></Style>';
$xml[] = '  <Style ss:ID="sSubtitle"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1"/></Style>';
$xml[] = '  <Style ss:ID="sHeader"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:Bold="1"/><Interior ss:Color="#BDD7EE" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
$xml[] = '  <Style ss:ID="sCell"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
$xml[] = '  <Style ss:ID="sCenter"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
$xml[] = '  <Style ss:ID="sNum"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><NumberFormat ss:Format="#,##0"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
$xml[] = '</Styles>';

$usedSheetNames = [];

// Summary sheet
$summarySheetName = $uniqueSheetName('Summary', $usedSheetNames);
$xml[] = '<Worksheet ss:Name="' . $xmlEsc($summarySheetName) . '">';
$xml[] = '  <Table>';
$xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="42"/>';
$xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="95"/>';
$xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="80"/>';
$xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="110"/>';
$xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="90"/>';
$xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="110"/>';
$xml[] = '    ' . $buildRow([$buildCell('Summary Process', 'sTitle', 'String', 5)]);
$xml[] = '    ' . $buildRow([$buildCell($periodText, 'sSubtitle', 'String', 5)]);
$xml[] = '    <Row/>';
$xml[] = '    ' . $buildRow([
    $buildCell('No', 'sHeader'),
    $buildCell('Proses', 'sHeader'),
    $buildCell('Total CP', 'sHeader'),
    $buildCell('Persentase CP', 'sHeader'),
    $buildCell('Total Qty', 'sHeader'),
    $buildCell('Persentase Qty', 'sHeader'),
]);

if (empty($summaryRows)) {
    $xml[] = '    ' . $buildRow([$buildCell('Tidak ada data', 'sCell', 'String', 5)]);
} else {
    $no = 1;
    foreach ($summaryRows as $row) {
        $xml[] = '    ' . $buildRow([
            $buildCell($no, 'sCenter', 'Number'),
            $buildCell((string)$row['process_label'], 'sCell'),
            $buildCell((int)$row['cp_total'], 'sNum', 'Number'),
            $buildCell($formatPercent((float)$row['cp_pct']), 'sCenter'),
            $buildCell((float)$row['qty_total'], 'sNum', 'Number'),
            $buildCell($formatPercent((float)$row['qty_pct']), 'sCenter'),
        ]);
        $no++;
    }
}

$xml[] = '  </Table>';
$xml[] = '  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><Selected/></WorksheetOptions>';
$xml[] = '</Worksheet>';

// Process sheets
foreach ($orderedProcesses as $proc) {
    $procKey = trim((string)$proc);
    if ($procKey === '') {
        continue;
    }
    $sheetRows = $rowsByProcess[$procKey] ?? [];
    $sheetName = $uniqueSheetName('Proses ' . $procKey, $usedSheetNames);

    $xml[] = '<Worksheet ss:Name="' . $xmlEsc($sheetName) . '">';
    $xml[] = '  <Table>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="42"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="120"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="82"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="95"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="190"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="82"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="95"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="82"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="95"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="185"/>';
    $xml[] = '    <Column ss:AutoFitWidth="0" ss:Width="95"/>';
    $xml[] = '    ' . $buildRow([$buildCell('Proses ' . $procKey, 'sTitle', 'String', 10)]);
    $xml[] = '    ' . $buildRow([$buildCell($periodText, 'sSubtitle', 'String', 10)]);
    $xml[] = '    <Row/>';
    $xml[] = '    ' . $buildRow([
        $buildCell('No', 'sHeader'),
        $buildCell('CP No', 'sHeader'),
        $buildCell('Tgl CP', 'sHeader'),
        $buildCell('Tgl Pemartaian', 'sHeader'),
        $buildCell('Material Name', 'sHeader'),
        $buildCell('Qty', 'sHeader'),
        $buildCell('Label', 'sHeader'),
        $buildCell('Cust Color', 'sHeader'),
        $buildCell('Kode Lab', 'sHeader'),
        $buildCell('Lokasi Paddry', 'sHeader'),
        $buildCell('Tgl Verpacking', 'sHeader'),
    ]);

    if (empty($sheetRows)) {
        $xml[] = '    ' . $buildRow([$buildCell('Tidak ada data', 'sCell', 'String', 10)]);
    } else {
        $no = 1;
        foreach ($sheetRows as $row) {
            $xml[] = '    ' . $buildRow([
                $buildCell($no, 'sCenter', 'Number'),
                $buildCell((string)($row['cp_no'] ?? ''), 'sCell'),
                $buildCell($formatDateDisplay($row['tgl_cp'] ?? ''), 'sCenter'),
                $buildCell($formatDateDisplay($row['tgl_pemartaian'] ?? ''), 'sCenter'),
                $buildCell((string)($row['material_name'] ?? ''), 'sCell'),
                $buildCell((float)($row['qty'] ?? 0), 'sNum', 'Number'),
                $buildCell((string)($row['label'] ?? ''), 'sCell'),
                $buildCell((string)($row['cust_color'] ?? ''), 'sCell'),
                $buildCell((string)($row['kode_lab'] ?? ''), 'sCell'),
                $buildCell((string)($row['lokasi_paddry'] ?? ''), 'sCell'),
                $buildCell($formatDateDisplay($row['tgl_verpacking'] ?? ''), 'sCenter'),
            ]);
            $no++;
        }
    }

    $xml[] = '  </Table>';
    $xml[] = '</Worksheet>';
}

$xml[] = '</Workbook>';

$filename = 'process_report_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo implode("\n", $xml);

while (ob_get_level() > 0) {
    @ob_end_flush();
}
exit;

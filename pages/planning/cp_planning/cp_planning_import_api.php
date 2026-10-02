<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
    ]);
    exit;
}

include '../../../koneksi.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!function_exists('cpPlanningImportJsonExit')) {
    function cpPlanningImportJsonExit(array $payload): void
    {
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('cpPlanningImportText')) {
    function cpPlanningImportText($value): string
    {
        return trim((string)($value ?? ''));
    }
}

if (!function_exists('cpPlanningImportHeaderToken')) {
    function cpPlanningImportHeaderToken($value): string
    {
        $text = strtolower(cpPlanningImportText($value));
        if ($text === '') {
            return '';
        }
        $text = str_replace(["\r", "\n", "\t", '_', '-'], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/[^a-z0-9\/ ]+/', '', $text);
        return trim($text);
    }
}

if (!function_exists('cpPlanningImportNormalizePlanType')) {
    function cpPlanningImportNormalizePlanType($value): string
    {
        $text = strtolower(cpPlanningImportText($value));
        if ($text === '') {
            return '';
        }
        $text = str_replace(['&', '/', '\\', '_', '-'], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string)$text);
    }
}

if (!function_exists('cpPlanningImportIsPaddryPlanType')) {
    function cpPlanningImportIsPaddryPlanType($value): bool
    {
        $key = cpPlanningImportNormalizePlanType($value);
        if ($key === '') {
            return false;
        }
        if ($key === 'paddry' || $key === 'planning paddry' || $key === 'plan paddry') {
            return true;
        }
        return strpos($key, 'paddry') !== false;
    }
}

if (!function_exists('cpPlanningImportLooksLikeCpNo')) {
    function cpPlanningImportLooksLikeCpNo(string $value): bool
    {
        $cp = strtoupper(cpPlanningImportText($value));
        if ($cp === '') {
            return false;
        }
        if (strpos($cp, ' ') !== false) {
            return false;
        }
        if (substr_count($cp, '.') < 2) {
            return false;
        }
        if (!preg_match('/\d/', $cp)) {
            return false;
        }
        return (bool)preg_match('/^[A-Z0-9]+(?:\.[A-Z0-9]+){2,}$/', $cp);
    }
}

if (!function_exists('cpPlanningImportExcelDateToIso')) {
    function cpPlanningImportExcelDateToIso(float $excelValue): string
    {
        if ($excelValue <= 0) {
            return '';
        }
        $days = (int)floor($excelValue);
        $base = new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC'));
        return $base->modify('+' . $days . ' days')->format('Y-m-d');
    }
}

if (!function_exists('cpPlanningImportDateDisplay')) {
    function cpPlanningImportDateDisplay($value): string
    {
        $raw = cpPlanningImportText($value);
        if ($raw === '') {
            return '';
        }

        if (is_numeric($raw)) {
            $num = (float)$raw;
            if ($num >= 1) {
                $iso = cpPlanningImportExcelDateToIso($num);
                if ($iso !== '') {
                    return substr($iso, 8, 2) . '/' . substr($iso, 5, 2) . '/' . substr($iso, 0, 4);
                }
            }
            return '';
        }

        $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'm/d/Y'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt instanceof DateTime) {
                return $dt->format('d/m/Y');
            }
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }

        return '';
    }
}

if (!function_exists('cpPlanningImportTime')) {
    function cpPlanningImportTime($value): string
    {
        $raw = cpPlanningImportText($value);
        if ($raw === '') {
            return '';
        }
        $raw = str_replace("\xC2\xA0", ' ', $raw); // NBSP
        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw) ?? $raw; // zero-width chars
        $raw = trim($raw);
        $raw = trim($raw, "'");
        if ($raw === '') {
            return '';
        }

        $numericRaw = str_replace(',', '.', $raw);
        if (is_numeric($numericRaw)) {
            $num = (float)$numericRaw;

            // Kasus angka jam seperti 930 / 0930.
            if (abs($num) >= 100 && abs($num) <= 2359 && floor($num) == $num) {
                $rawInt = (string)((int)$num);
                if (preg_match('/^\d{3,4}$/', $rawInt)) {
                    $rawInt = str_pad($rawInt, 4, '0', STR_PAD_LEFT);
                    $hh = (int)substr($rawInt, 0, 2);
                    $mm = (int)substr($rawInt, 2, 2);
                    if ($hh >= 0 && $hh <= 23 && $mm >= 0 && $mm <= 59) {
                        return sprintf('%02d:%02d', $hh, $mm);
                    }
                }
            }

            // Excel time serial bisa berupa:
            // 1) pure time (0..1)
            // 2) date+time (>1), ambil fraksi waktunya.
            if ($num >= 0) {
                $fraction = $num - floor($num);
                if ($num < 1 || $fraction > 0) {
                    $totalMinutes = (int)round($fraction * 24 * 60);
                    $totalMinutes = $totalMinutes % 1440;
                    $hh = (int)floor($totalMinutes / 60);
                    $mm = $totalMinutes % 60;
                    return sprintf('%02d:%02d', $hh, $mm);
                }
            }

            return '';
        }

        if (preg_match('/^(\d{1,2})[\.](\d{2})$/', $raw, $mDot)) {
            $hh = (int)$mDot[1];
            $mm = (int)$mDot[2];
            if ($hh >= 0 && $hh <= 23 && $mm >= 0 && $mm <= 59) {
                return sprintf('%02d:%02d', $hh, $mm);
            }
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m)) {
            $hh = (int)$m[1];
            $mm = (int)$m[2];
            if ($hh >= 0 && $hh <= 23 && $mm >= 0 && $mm <= 59) {
                return sprintf('%02d:%02d', $hh, $mm);
            }
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('H:i', $ts);
        }

        return '';
    }
}

if (!function_exists('cpPlanningImportExcelColToIndex')) {
    function cpPlanningImportExcelColToIndex(string $letters): int
    {
        $letters = strtoupper(trim($letters));
        $len = strlen($letters);
        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($letters[$i]);
            if ($ord < 65 || $ord > 90) {
                continue;
            }
            $num = ($num * 26) + ($ord - 64);
        }
        return $num;
    }
}

if (!function_exists('cpPlanningImportInlineString')) {
    function cpPlanningImportInlineString(SimpleXMLElement $cell): string
    {
        if (!isset($cell->is)) {
            return '';
        }
        $parts = [];
        if (isset($cell->is->t)) {
            $parts[] = (string)$cell->is->t;
        }
        if (isset($cell->is->r)) {
            foreach ($cell->is->r as $run) {
                if (isset($run->t)) {
                    $parts[] = (string)$run->t;
                }
            }
        }
        return implode('', $parts);
    }
}

if (!function_exists('cpPlanningImportNormalizeWorksheetTarget')) {
    function cpPlanningImportNormalizeWorksheetTarget(string $target): string
    {
        $path = str_replace('\\', '/', trim($target));
        if ($path === '') {
            return '';
        }
        while (strpos($path, '../') === 0) {
            $path = substr($path, 3);
        }
        if (strpos($path, './') === 0) {
            $path = substr($path, 2);
        }
        $path = ltrim($path, '/');
        if (strpos($path, 'xl/') === 0) {
            return $path;
        }
        if (strpos($path, 'worksheets/') === 0) {
            return 'xl/' . $path;
        }
        return 'xl/' . $path;
    }
}

if (!function_exists('cpPlanningImportResolveWorksheetPath')) {
    function cpPlanningImportResolveWorksheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';
        $GLOBALS['cpPlanningImportLastSheetName'] = '';
        $GLOBALS['cpPlanningImportLastSheetPath'] = '';

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            if ($zip->locateName($fallback) !== false) {
                $GLOBALS['cpPlanningImportLastSheetPath'] = $fallback;
                return $fallback;
            }
            return '';
        }

        libxml_use_internal_errors(true);
        $wb = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        libxml_clear_errors();

        if (!($wb instanceof SimpleXMLElement) || !($rels instanceof SimpleXMLElement)) {
            if ($zip->locateName($fallback) !== false) {
                $GLOBALS['cpPlanningImportLastSheetPath'] = $fallback;
                return $fallback;
            }
            return '';
        }

        $relMap = [];
        $relsNs = $rels->getNamespaces(true);
        $relsMainNs = $relsNs[''] ?? 'http://schemas.openxmlformats.org/package/2006/relationships';
        $rels->registerXPathNamespace('rel', $relsMainNs);
        $relNodes = $rels->xpath('//rel:Relationship');
        if (is_array($relNodes)) {
            foreach ($relNodes as $relNode) {
                $id = trim((string)($relNode['Id'] ?? ''));
                $type = trim((string)($relNode['Type'] ?? ''));
                $target = trim((string)($relNode['Target'] ?? ''));
                if ($id === '' || $target === '' || stripos($type, '/worksheet') === false) {
                    continue;
                }
                $targetPath = cpPlanningImportNormalizeWorksheetTarget($target);
                if ($targetPath !== '') {
                    $relMap[$id] = $targetPath;
                }
            }
        }

        if (empty($relMap)) {
            if ($zip->locateName($fallback) !== false) {
                $GLOBALS['cpPlanningImportLastSheetPath'] = $fallback;
                return $fallback;
            }
            return '';
        }

        $wbNs = $wb->getNamespaces(true);
        $wbMainNs = $wbNs[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $wbRelNs = $wbNs['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $wb->registerXPathNamespace('main', $wbMainNs);

        $activeTab = 0;
        $viewNodes = $wb->xpath('//main:bookViews/main:workbookView');
        if (is_array($viewNodes) && isset($viewNodes[0])) {
            $tabRaw = (string)($viewNodes[0]['activeTab'] ?? '0');
            if ($tabRaw !== '' && is_numeric($tabRaw)) {
                $activeTab = (int)$tabRaw;
            }
        }

        $sheets = [];
        $sheetNodes = $wb->xpath('//main:sheets/main:sheet');
        $ordinal = -1;
        if (is_array($sheetNodes)) {
            foreach ($sheetNodes as $sheetNode) {
                $ordinal++;
                $name = trim((string)($sheetNode['name'] ?? ''));
                $state = strtolower(trim((string)($sheetNode['state'] ?? '')));
                $ridAttrs = $sheetNode->attributes($wbRelNs, true);
                $rid = trim((string)($ridAttrs['id'] ?? ''));
                if ($rid === '' || !isset($relMap[$rid])) {
                    continue;
                }
                $path = $relMap[$rid];
                if ($zip->locateName($path) === false) {
                    continue;
                }
                $sheets[] = [
                    'ordinal' => $ordinal,
                    'name' => $name,
                    'state' => $state,
                    'path' => $path,
                ];
            }
        }

        if (empty($sheets)) {
            if ($zip->locateName($fallback) !== false) {
                $GLOBALS['cpPlanningImportLastSheetPath'] = $fallback;
                return $fallback;
            }
            return '';
        }

        foreach ($sheets as $sheet) {
            $isVisible = ($sheet['state'] !== 'hidden' && $sheet['state'] !== 'veryhidden');
            if ($isVisible && (int)$sheet['ordinal'] === $activeTab) {
                $GLOBALS['cpPlanningImportLastSheetName'] = (string)$sheet['name'];
                $GLOBALS['cpPlanningImportLastSheetPath'] = (string)$sheet['path'];
                return (string)$sheet['path'];
            }
        }

        foreach ($sheets as $sheet) {
            $isVisible = ($sheet['state'] !== 'hidden' && $sheet['state'] !== 'veryhidden');
            if ($isVisible) {
                $GLOBALS['cpPlanningImportLastSheetName'] = (string)$sheet['name'];
                $GLOBALS['cpPlanningImportLastSheetPath'] = (string)$sheet['path'];
                return (string)$sheet['path'];
            }
        }

        $first = $sheets[0];
        $GLOBALS['cpPlanningImportLastSheetName'] = (string)$first['name'];
        $GLOBALS['cpPlanningImportLastSheetPath'] = (string)$first['path'];
        return (string)$first['path'];
    }
}

if (!function_exists('cpPlanningImportReadXlsxRows')) {
    function cpPlanningImportReadXlsxRows(string $filePath): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Ekstensi ZIP pada PHP belum aktif.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('File Excel tidak bisa dibuka.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            libxml_use_internal_errors(true);
            $sharedObj = simplexml_load_string($sharedXml);
            libxml_clear_errors();
            if ($sharedObj instanceof SimpleXMLElement) {
                foreach ($sharedObj->si as $si) {
                    $parts = [];
                    if (isset($si->t)) {
                        $parts[] = (string)$si->t;
                    }
                    if (isset($si->r)) {
                        foreach ($si->r as $run) {
                            if (isset($run->t)) {
                                $parts[] = (string)$run->t;
                            }
                        }
                    }
                    $sharedStrings[] = implode('', $parts);
                }
            }
        }

        $sheetPath = cpPlanningImportResolveWorksheetPath($zip);
        if ($sheetPath === '' || $zip->locateName($sheetPath) === false) {
            $sheetPath = 'xl/worksheets/sheet1.xml';
        }

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Worksheet Excel tidak ditemukan.');
        }

        libxml_use_internal_errors(true);
        $sheetObj = simplexml_load_string($sheetXml);
        libxml_clear_errors();
        if (!($sheetObj instanceof SimpleXMLElement) || !isset($sheetObj->sheetData)) {
            throw new RuntimeException('Format worksheet Excel tidak valid.');
        }

        $rows = [];
        foreach ($sheetObj->sheetData->row as $rowNode) {
            $rowIndex = (int)($rowNode['r'] ?? 0);
            $cells = [];
            $seqCol = 0;

            foreach ($rowNode->c as $cell) {
                $cellRef = (string)($cell['r'] ?? '');
                $colIndex = 0;
                if ($cellRef !== '' && preg_match('/^([A-Z]+)\d+$/i', $cellRef, $mRef)) {
                    $colIndex = cpPlanningImportExcelColToIndex($mRef[1]);
                }
                if ($colIndex <= 0) {
                    $colIndex = $seqCol + 1;
                }
                $seqCol = $colIndex;

                $type = (string)($cell['t'] ?? '');
                $value = '';
                if ($type === 's') {
                    $idx = isset($cell->v) ? (int)$cell->v : -1;
                    if ($idx >= 0 && isset($sharedStrings[$idx])) {
                        $value = (string)$sharedStrings[$idx];
                    }
                } elseif ($type === 'inlineStr') {
                    $value = cpPlanningImportInlineString($cell);
                } else {
                    $value = isset($cell->v) ? (string)$cell->v : '';
                }

                $cells[$colIndex] = $value;
            }

            $rows[] = [
                'row_index' => $rowIndex,
                'cells' => $cells,
            ];
        }

        return $rows;
    }
}

if (!function_exists('cpPlanningImportFieldAliasMap')) {
    function cpPlanningImportFieldAliasMap(): array
    {
        $aliases = [
            'import_no' => ['no', 'nomor', 'seq'],
            'no_cp' => ['cp no', 'no cp', 'cpno', 'nocp'],
            'label' => ['label'],
            'cust_color' => ['cust color', 'custcolor'],
            'kode_lab' => ['kode lab', 'kodelab'],
            'material' => ['material name', 'material'],
            'qty' => ['qty', 'quantity'],
            'posisi_hari_ini' => ['posisi hari ini', 'posisihariini'],
            'speed' => ['speed'],
            'resp_lipat' => ['resep lipat'],
            'status_resp' => ['status resep'],
            'vlot_resp' => ['vlot resep', 'vlot resp', 'vlot'],
            'bon_resp' => ['bon resep', 'bon resp'],
            'plan_description' => ['ket', 'kategori timbang', 'kategori penimbangan', 'kategori'],
            'plan_date' => ['tgl', 'tanggal', 'date'],
            // Mapping khusus template "Monitoring Planning Paddry":
            // Rencana Timbang Mulai   -> Est Tmbng Plrtn LA/LAB
            // Rencana Timbang Selesai -> Est Plrtm Prdks
            'est_tmbng_plrtm_lalab' => [
                'est tmbng plrtn la/lab',
                'est tmbng plrtn lalab',
                'rencana timbang mulai',
                'rencana timbang start',
            ],
            'est_plrtm_prdks' => [
                'est plrtm prdks',
                'rencana timbang selesai',
                'rencana timbang finish',
            ],
            'actual_timbang_lalab_start' => ['aktual timbang la/lab start', 'aktual timbang la/lab mulai', 'aktual timbang mulai'],
            'actual_timbang_lalab_finish' => ['aktual timbang la/lab finish', 'aktual timbang la/lab selesai', 'aktual timbang selesai'],
            'actual_larut_lalab_start' => ['aktual larut la/lab start', 'aktual larut la/lab mulai', 'aktual larut mulai', 'aktual pelarutan la/lab start', 'aktual pelarutan mulai'],
            'actual_larut_lalab_finish' => ['aktual larut la/lab finish', 'aktual larut la/lab selesai', 'aktual larut selesai', 'aktual pelarutan la/lab finish', 'aktual pelarutan selesai'],
            'plan_start' => ['rencana celup start', 'rencana celup mulai'],
            'plan_end' => ['rencana celup finish', 'rencana celup selesai'],
            'actual_start' => ['aktual celup start', 'aktual celup mulai'],
            'actual_end' => ['aktual celup finish', 'aktual celup selesai'],
            'actual_topping_paddry_start' => ['aktual topping paddry start', 'aktual topping paddry mulai', 'aktual topping start', 'aktual topping mulai'],
            'actual_topping_paddry_finish' => ['aktual topping paddry finish', 'aktual topping paddry selesai', 'aktual topping finish', 'aktual topping selesai'],
            'actual_larut_prdks_start' => ['aktual larut produksi start', 'aktual larut produksi mulai', 'waktu tetes larutan serahkan', 'waktu tetes serahkan', 'waktu test larutan serahkan', 'waktu test serahkan'],
            'actual_larut_prdks_finish' => ['aktual larut produksi finish', 'aktual larut produksi selesai', 'waktu tetes larutan terima', 'waktu tetes terima', 'waktu test larutan terima', 'waktu test terima'],
            'actual_vlot' => ['vlt aktl', 'vlot aktual', 'aktual vlot', 'actual vlot'],
            'sisa_saturator' => ['sisa larutan', 'sisa larut'],
            'actual_operator' => ['operator', 'operat or'],
            'actual_shift' => ['shift'],
            'down_time' => ['wkt bilas', 'waktu bilas', 'down time'],
            'sample' => ['sample kain', 'sampel kain', 'sample'],
            'rko' => ['rko'],
            'next_routing' => ['next routing'],
        ];

        $out = [];
        foreach ($aliases as $field => $list) {
            foreach ($list as $alias) {
                $key = cpPlanningImportHeaderToken($alias);
                if ($key !== '' && !isset($out[$key])) {
                    $out[$key] = $field;
                }
            }
        }
        return $out;
    }
}

if (!function_exists('cpPlanningImportDetectHeader')) {
    function cpPlanningImportDetectHeader(array $rawRows): array
    {
        $aliasMap = cpPlanningImportFieldAliasMap();

        for ($ri = 0; $ri < count($rawRows) && $ri < 60; $ri++) {
            $row = $rawRows[$ri];
            $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];
            if (empty($cells)) {
                continue;
            }

            $tokenByCol = [];
            foreach ($cells as $col => $val) {
                $token = cpPlanningImportHeaderToken($val);
                if ($token !== '') {
                    $tokenByCol[(int)$col] = $token;
                }
            }
            if (empty($tokenByCol)) {
                continue;
            }

            $hasCpHeader = false;
            foreach ($tokenByCol as $tok) {
                if ($tok === 'cp no' || $tok === 'no cp' || $tok === 'cpno' || $tok === 'nocp') {
                    $hasCpHeader = true;
                    break;
                }
            }
            if (!$hasCpHeader) {
                continue;
            }

            $nextTokens = [];
            $hasSubHeader = false;
            $subHeaderOffset = 1;
            $bestSubHit = 0;
            $bestSubTokens = [];
            $bestSubOffset = 1;
            for ($off = 1; $off <= 3; $off++) {
                if (!isset($rawRows[$ri + $off])) {
                    continue;
                }
                $candCells = is_array($rawRows[$ri + $off]['cells'] ?? null) ? $rawRows[$ri + $off]['cells'] : [];
                if (empty($candCells)) {
                    continue;
                }

                $candTokens = [];
                $subHit = 0;
                foreach ($candCells as $col => $val) {
                    $tok = cpPlanningImportHeaderToken($val);
                    if ($tok === '') {
                        continue;
                    }
                    $candTokens[(int)$col] = $tok;
                    if ($tok === 'start' || $tok === 'finish' || $tok === 'mulai' || $tok === 'selesai' || $tok === 'serahkan' || $tok === 'terima' || $tok === 'p') {
                        $subHit++;
                    }
                }

                if ($subHit > $bestSubHit) {
                    $bestSubHit = $subHit;
                    $bestSubTokens = $candTokens;
                    $bestSubOffset = $off;
                }
            }
            if ($bestSubHit >= 2) {
                $hasSubHeader = true;
                $nextTokens = $bestSubTokens;
                $subHeaderOffset = $bestSubOffset;
            }

            $map = [];
            $cols = array_unique(array_merge(array_keys($tokenByCol), array_keys($nextTokens)));
            sort($cols, SORT_NUMERIC);
            $lastBase = '';
            $subHeaderHints = [
                'start' => true,
                'finish' => true,
                'mulai' => true,
                'selesai' => true,
                'p' => true,
                'serahkan' => true,
                'terima' => true,
            ];
            foreach ($cols as $col) {
                $base = $tokenByCol[$col] ?? '';
                $sub = $nextTokens[$col] ?? '';
                if ($base !== '') {
                    $lastBase = $base;
                } elseif ($sub !== '' && isset($subHeaderHints[$sub]) && $lastBase !== '') {
                    // Header merged (colspan) biasanya hanya mengisi kolom pertama.
                    // Turunkan judul grup ke kolom sub-header agar "mulai/selesai" tetap terpetakan.
                    $base = $lastBase;
                }
                $candidates = [];
                if ($base !== '' && $sub !== '') {
                    $candidates[] = cpPlanningImportHeaderToken($base . ' ' . $sub);
                }
                if ($base !== '') {
                    $candidates[] = $base;
                }
                if ($sub !== '') {
                    $candidates[] = $sub;
                }

                foreach ($candidates as $cand) {
                    if ($cand === '' || !isset($aliasMap[$cand])) {
                        continue;
                    }
                    $field = $aliasMap[$cand];
                    if (!isset($map[$field])) {
                        $map[$field] = (int)$col;
                    }
                }
            }

            // Fallback khusus header merge "Monitoring Planning Paddry":
            // pastikan mapping berbasis grup tetap terbaca meski alias per kolom tidak match sempurna.
            $groupRencanaTimbangCol = 0;
            $groupAktualLarutCol = 0;
            $groupRencanaCelupCol = 0;
            $groupAktualCelupCol = 0;
            foreach ($tokenByCol as $col => $tok) {
                $colInt = (int)$col;
                if ($groupRencanaTimbangCol <= 0 && (strpos($tok, 'rencana timbang') !== false)) {
                    $groupRencanaTimbangCol = $colInt;
                }
                if ($groupAktualLarutCol <= 0 && (strpos($tok, 'aktual larut') !== false || strpos($tok, 'aktual pelarutan') !== false)) {
                    $groupAktualLarutCol = $colInt;
                }
                if ($groupRencanaCelupCol <= 0 && (strpos($tok, 'rencana celup') !== false)) {
                    $groupRencanaCelupCol = $colInt;
                }
                if ($groupAktualCelupCol <= 0 && (strpos($tok, 'aktual celup') !== false)) {
                    $groupAktualCelupCol = $colInt;
                }
            }
            if ($groupRencanaTimbangCol > 0) {
                if (!isset($map['est_tmbng_plrtm_lalab'])) {
                    $map['est_tmbng_plrtm_lalab'] = $groupRencanaTimbangCol;
                }
                if (!isset($map['est_plrtm_prdks'])) {
                    $map['est_plrtm_prdks'] = $groupRencanaTimbangCol + 1;
                }
            }
            if ($groupAktualLarutCol > 0) {
                if (!isset($map['actual_larut_lalab_start'])) {
                    $map['actual_larut_lalab_start'] = $groupAktualLarutCol;
                }
                if (!isset($map['actual_larut_lalab_finish'])) {
                    $map['actual_larut_lalab_finish'] = $groupAktualLarutCol + 1;
                }
            }
            if ($groupRencanaCelupCol > 0) {
                if (!isset($map['plan_start'])) {
                    $map['plan_start'] = $groupRencanaCelupCol;
                }
                if (!isset($map['plan_end'])) {
                    $map['plan_end'] = $groupRencanaCelupCol + 1;
                }
            }
            if ($groupAktualCelupCol > 0) {
                $actualEndCol = $groupAktualCelupCol + 1;
                if (isset($nextTokens[$groupAktualCelupCol + 1]) && $nextTokens[$groupAktualCelupCol + 1] === 'p') {
                    $actualEndCol = $groupAktualCelupCol + 2;
                }
                if (!isset($map['actual_start'])) {
                    $map['actual_start'] = $groupAktualCelupCol;
                }
                if (!isset($map['actual_end'])) {
                    $map['actual_end'] = $actualEndCol;
                }
            }

            // Prioritas eksplisit: kolom "Sampel Kain" di file Excel
            // harus selalu masuk ke field sample pada sistem.
            $sampleKainCol = 0;
            foreach ($tokenByCol as $col => $tok) {
                if ($tok === 'sampel kain' || $tok === 'sample kain') {
                    $sampleKainCol = (int)$col;
                    break;
                }
            }
            if ($sampleKainCol > 0) {
                $map['sample'] = $sampleKainCol;
            }

            if (!isset($map['no_cp'])) {
                continue;
            }

            return [
                'map' => $map,
                'start_index' => $ri + ($hasSubHeader ? ($subHeaderOffset + 1) : 1),
            ];
        }

        return [
            'map' => [
                'import_no' => 1,
                'plan_date' => 2,
                'no_cp' => 3,
                'label' => 4,
                'cust_color' => 5,
                'kode_lab' => 6,
                'qty' => 7,
                'speed' => 8,
                'vlot_resp' => 9,
                'plan_description' => 10,
                'est_tmbng_plrtm_lalab' => 11,
                'est_plrtm_prdks' => 12,
                'actual_larut_lalab_start' => 13,
                'actual_larut_lalab_finish' => 14,
                'plan_start' => 15,
                'plan_end' => 16,
                'actual_start' => 17,
                'actual_end' => 18,
                'actual_vlot' => 20,
                'sisa_saturator' => 21,
                'sample' => 22,
                'down_time' => 23,
                'actual_larut_prdks_start' => 24,
                'actual_larut_prdks_finish' => 25,
                'actual_operator' => 26,
                'actual_shift' => 27,
            ],
            'start_index' => 0,
        ];
    }
}

if (!function_exists('cpPlanningImportMapRows')) {
    function cpPlanningImportMapRows(array $rawRows, string $periodDateIso): array
    {
        $header = cpPlanningImportDetectHeader($rawRows);
        $colMap = $header['map'];
        $startIndex = (int)($header['start_index'] ?? 0);

        $rows = [];
        $skipped = 0;
        $expectedNo = 1;
        $hasStarted = false;
        $emptyAfterStarted = 0;

        $cellValue = static function (array $cells, string $field) use ($colMap): string {
            if (!isset($colMap[$field])) {
                return '';
            }
            $idx = (int)$colMap[$field];
            return cpPlanningImportText($cells[$idx] ?? '');
        };
        $cellValueByIndex = static function (array $cells, int $idx): string {
            if ($idx <= 0) {
                return '';
            }
            return cpPlanningImportText($cells[$idx] ?? '');
        };

        for ($i = $startIndex; $i < count($rawRows); $i++) {
            $cells = is_array($rawRows[$i]['cells'] ?? null) ? $rawRows[$i]['cells'] : [];
            if (empty($cells)) {
                if ($hasStarted) {
                    $emptyAfterStarted++;
                    if ($emptyAfterStarted >= 2) {
                        break;
                    }
                }
                continue;
            }

            $importNo = $cellValue($cells, 'import_no');
            $cpNo = $cellValue($cells, 'no_cp');

            if ($cpNo === '' && $importNo === '') {
                if ($hasStarted) {
                    $emptyAfterStarted++;
                    if ($emptyAfterStarted >= 2) {
                        break;
                    }
                }
                continue;
            }

            $hasImportNo = isset($colMap['import_no']);
            if ($hasImportNo) {
                if (!preg_match('/^\d+$/', $importNo)) {
                    if ($hasStarted) {
                        break;
                    }
                    $skipped++;
                    continue;
                }

                $noInt = (int)$importNo;
                if (!$hasStarted) {
                    if ($noInt !== 1) {
                        $skipped++;
                        continue;
                    }
                } else {
                    if ($noInt !== $expectedNo) {
                        break;
                    }
                }
            }

            if ($cpNo === '' || !cpPlanningImportLooksLikeCpNo($cpNo)) {
                if ($hasStarted && $hasImportNo) {
                    break;
                }
                $skipped++;
                continue;
            }

            $planDate = cpPlanningImportDateDisplay($cellValue($cells, 'plan_date'));
            if ($planDate === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDateIso)) {
                $planDate = substr($periodDateIso, 8, 2) . '/' . substr($periodDateIso, 5, 2) . '/' . substr($periodDateIso, 0, 4);
            }

            $planDescription = $cellValue($cells, 'plan_description');
            $planStartRaw = $cellValue($cells, 'plan_start');
            $planEndRaw = $cellValue($cells, 'plan_end');

            // Fallback khusus template monitoring paddry:
            // jika header "Rencana Celup" tidak terpetakan, ambil dari posisi relatif.
            if ($planStartRaw === '' || $planEndRaw === '') {
                $altPlanStartIdx = 0;
                $altPlanEndIdx = 0;

                if (isset($colMap['est_plrtm_prdks'])) {
                    $base = (int)$colMap['est_plrtm_prdks'];
                    if ($base > 0) {
                        $altPlanStartIdx = $base + 3;
                        $altPlanEndIdx = $base + 4;
                    }
                }

                if (($altPlanStartIdx <= 0 || $altPlanEndIdx <= 0) && isset($colMap['actual_larut_lalab_finish'])) {
                    $baseFinish = (int)$colMap['actual_larut_lalab_finish'];
                    if ($baseFinish > 0) {
                        if ($altPlanStartIdx <= 0) {
                            $altPlanStartIdx = $baseFinish + 1;
                        }
                        if ($altPlanEndIdx <= 0) {
                            $altPlanEndIdx = $baseFinish + 2;
                        }
                    }
                }

                if ($planStartRaw === '' && $altPlanStartIdx > 0) {
                    $planStartRaw = $cellValueByIndex($cells, $altPlanStartIdx);
                }
                if ($planEndRaw === '' && $altPlanEndIdx > 0) {
                    $planEndRaw = $cellValueByIndex($cells, $altPlanEndIdx);
                }
            }

            $rows[] = [
                'no_cp' => $cpNo,
                'label' => $cellValue($cells, 'label'),
                'cust_color' => $cellValue($cells, 'cust_color'),
                'kode_lab' => $cellValue($cells, 'kode_lab'),
                'material' => $cellValue($cells, 'material'),
                'qty' => $cellValue($cells, 'qty'),
                'posisi_hari_ini' => $cellValue($cells, 'posisi_hari_ini'),
                'speed' => $cellValue($cells, 'speed'),
                'resp_lipat' => $cellValue($cells, 'resp_lipat'),
                'status_resp' => $cellValue($cells, 'status_resp'),
                'vlot_resp' => $cellValue($cells, 'vlot_resp'),
                'bon_resp' => $cellValue($cells, 'bon_resp'),
                'plan_description' => $planDescription,
                'kategori' => $planDescription,
                'plan_date' => $planDate,
                'est_tmbng_plrtm_lalab' => cpPlanningImportTime($cellValue($cells, 'est_tmbng_plrtm_lalab')),
                'actual_timbang_lalab_start' => cpPlanningImportTime($cellValue($cells, 'actual_timbang_lalab_start')),
                'actual_timbang_lalab_finish' => cpPlanningImportTime($cellValue($cells, 'actual_timbang_lalab_finish')),
                'actual_larut_lalab_start' => cpPlanningImportTime($cellValue($cells, 'actual_larut_lalab_start')),
                'actual_larut_lalab_finish' => cpPlanningImportTime($cellValue($cells, 'actual_larut_lalab_finish')),
                'est_plrtm_prdks' => cpPlanningImportTime($cellValue($cells, 'est_plrtm_prdks')),
                'actual_larut_prdks_start' => cpPlanningImportTime($cellValue($cells, 'actual_larut_prdks_start')),
                'actual_larut_prdks_finish' => cpPlanningImportTime($cellValue($cells, 'actual_larut_prdks_finish')),
                'plan_start' => cpPlanningImportTime($planStartRaw),
                'plan_end' => cpPlanningImportTime($planEndRaw),
                'actual_start' => cpPlanningImportTime($cellValue($cells, 'actual_start')),
                'actual_end' => cpPlanningImportTime($cellValue($cells, 'actual_end')),
                'actual_topping_paddry_start' => cpPlanningImportTime($cellValue($cells, 'actual_topping_paddry_start')),
                'actual_topping_paddry_finish' => cpPlanningImportTime($cellValue($cells, 'actual_topping_paddry_finish')),
                'actual_vlot' => $cellValue($cells, 'actual_vlot'),
                'sisa_saturator' => $cellValue($cells, 'sisa_saturator'),
                'down_time' => $cellValue($cells, 'down_time'),
                'sample' => $cellValue($cells, 'sample'),
                'actual_operator' => $cellValue($cells, 'actual_operator'),
                'actual_shift' => $cellValue($cells, 'actual_shift'),
                'rko' => cpPlanningImportDateDisplay($cellValue($cells, 'rko')),
                'next_routing' => $cellValue($cells, 'next_routing'),
            ];

            $hasStarted = true;
            $emptyAfterStarted = 0;
            if ($hasImportNo && preg_match('/^\d+$/', $importNo)) {
                $expectedNo = ((int)$importNo) + 1;
            }

            if (count($rows) >= 300) {
                break;
            }
        }

        return [
            'rows' => $rows,
            'skipped_count' => $skipped,
        ];
    }
}

if ($action === 'import_paddry_excel') {
    $planType = cpPlanningImportText($_POST['plan_type'] ?? '');
    $machineId = cpPlanningImportText($_POST['machine_id'] ?? '');
    $periodDate = cpPlanningImportText($_POST['period_date'] ?? '');

    if (!cpPlanningImportIsPaddryPlanType($planType)) {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'Import Excel hanya tersedia untuk tipe planning Paddry.',
        ]);
    }
    if ($machineId === '') {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'Machine tidak valid.',
        ]);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'Periode tidak valid.',
        ]);
    }

    if (!isset($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'File Excel belum dipilih.',
        ]);
    }

    $fileError = (int)($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($fileError !== UPLOAD_ERR_OK) {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'Gagal upload file Excel (kode error: ' . $fileError . ').',
        ]);
    }

    $fileName = (string)($_FILES['import_file']['name'] ?? '');
    $tmpName = (string)($_FILES['import_file']['tmp_name'] ?? '');
    $fileSize = (int)($_FILES['import_file']['size'] ?? 0);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($ext !== 'xlsx') {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'Format file harus .xlsx',
        ]);
    }
    if ($fileSize <= 0) {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'File Excel kosong.',
        ]);
    }
    if ($fileSize > (10 * 1024 * 1024)) {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => 'Ukuran file terlalu besar. Maksimal 10 MB.',
        ]);
    }

    try {
        $rawRows = cpPlanningImportReadXlsxRows($tmpName);
        $mapped = cpPlanningImportMapRows($rawRows, $periodDate);
        $rows = $mapped['rows'];
        $skipped = (int)($mapped['skipped_count'] ?? 0);

        if (!is_array($rows) || count($rows) === 0) {
            cpPlanningImportJsonExit([
                'success' => false,
                'message' => 'Tidak ada data CP valid yang bisa diimport dari file Excel.',
                'data' => [
                    'rows' => [],
                    'skipped_count' => $skipped,
                    'total_row_read' => count($rawRows),
                    'sheet_name' => cpPlanningImportText($GLOBALS['cpPlanningImportLastSheetName'] ?? ''),
                    'sheet_path' => cpPlanningImportText($GLOBALS['cpPlanningImportLastSheetPath'] ?? ''),
                ],
            ]);
        }

        $previewCp = [];
        foreach ($rows as $r) {
            $cp = cpPlanningImportText($r['no_cp'] ?? '');
            if ($cp === '') {
                continue;
            }
            $previewCp[] = $cp;
            if (count($previewCp) >= 10) {
                break;
            }
        }

        cpPlanningImportJsonExit([
            'success' => true,
            'message' => count($rows) . ' baris data Excel siap diimport.',
            'data' => [
                'rows' => $rows,
                'skipped_count' => $skipped,
                'total_row_read' => count($rawRows),
                'parsed_cp_count' => count($rows),
                'preview_cp' => $previewCp,
                'sheet_name' => cpPlanningImportText($GLOBALS['cpPlanningImportLastSheetName'] ?? ''),
                'sheet_path' => cpPlanningImportText($GLOBALS['cpPlanningImportLastSheetPath'] ?? ''),
            ],
        ]);
    } catch (Throwable $e) {
        cpPlanningImportJsonExit([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}

cpPlanningImportJsonExit([
    'success' => false,
    'message' => 'Aksi tidak valid.',
]);

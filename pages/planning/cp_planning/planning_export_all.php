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

session_start();
if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}
// Lepas lock session secepatnya agar request internal (load_paddry) tidak deadlock
// saat memakai session/cookie yang sama.
if (function_exists('session_write_close')) {
    session_write_close();
}

include '../../../koneksi.php';

if (!function_exists('cpPlanningExportFetchRowsByAction')) {
    function cpPlanningExportFetchRowsByAction(string $action, string $machineId, string $periodDate): array
    {
        $action = trim($action);
        $machineId = trim($machineId);
        $periodDate = trim($periodDate);
        if ($action === '' || $machineId === '' || $periodDate === '') {
            return [];
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $endpoint = $scheme . '://' . $host . '/gg_app/pages/planning/cp_planning/cp_planning.php?action=' . rawurlencode($action);
        $cookie = session_name() . '=' . session_id();
        $postBody = http_build_query([
            'machine_id' => $machineId,
            'period_date' => $periodDate,
        ]);

        $raw = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postBody,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Cookie: ' . $cookie,
                ],
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $resp = curl_exec($ch);
            if ($resp !== false) {
                $raw = (string)$resp;
            }
            curl_close($ch);
        }

        if ($raw === '') {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                        . "Cookie: " . $cookie . "\r\n",
                    'content' => $postBody,
                    'timeout' => 120,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $fallback = @file_get_contents($endpoint, false, $context);
            if ($fallback !== false) {
                $raw = (string)$fallback;
            }
        }

        if ($raw === '') {
            return [];
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || ($json['success'] ?? false) !== true) {
            return [];
        }

        $data = $json['data'] ?? [];
        return is_array($data) ? $data : [];
    }
}

$layout = trim((string)($_POST['layout'] ?? ($_GET['layout'] ?? '')));
$planType = trim((string)($_POST['plan_type'] ?? ($_GET['plan_type'] ?? '')));
$periodDate = trim((string)($_POST['period_date'] ?? ($_GET['period_date'] ?? '')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodDate)) {
    http_response_code(400);
    echo 'Periode tidak valid.';
    exit;
}

$layoutKey = strtolower($layout);
if ($layoutKey === '') {
    $planKey = strtolower($planType);
    if ($planKey === 'paddry' || $planKey === 'planning paddry') {
        $layoutKey = 'paddry';
    } elseif ($planKey === 'bakar bulu') {
        $layoutKey = 'bakar_bulu';
    } elseif ($planKey === 'scouring') {
        $layoutKey = 'scouring';
    } elseif ($planKey === 'presett' || $planKey === 'pre sett') {
        $layoutKey = 'presett';
    }
}

$layoutMap = [
    'paddry' => [
        'table' => 'dbo.cpp_paddry',
        'title' => 'CP Planning Paddry',
        'header_top' => [
            ['No', 2, 1],
            ['CP No', 2, 1],
            ['Label', 2, 1],
            ['Cust Color', 2, 1],
            ['Kode Lab', 2, 1],
            ['Material Name', 2, 1],
            ['Planning Qty', 2, 1],
            ['Posisi Hari Ini', 2, 1],
            ['Speed', 2, 1],
            ['Resep Lipat', 2, 1],
            ['Status Resep', 2, 1],
            ['Vlot Resep', 2, 1],
            ['Bon Penimbangan', 2, 1],
            ['Kategori Penimbangan', 2, 1],
            ['Tgl', 2, 1],
            ['Est Tmbng Plrtn LA/LAB', 2, 1],
            ['Act. Tmbg LAB', 1, 2],
            ['Act. Tmbg LA', 1, 2],
            ['Act. Larut LAB', 1, 2],
            ['Act. Larut LA', 1, 2],
            ['Est Plrtm Prdks', 2, 1],
            ['Act. Larut Produksi', 1, 2],
            ['Rencana Celup', 1, 2],
            ['Aktual Celup', 1, 2],
            ['Aktual Topping Paddry', 1, 2],
            ['Vlot Aktual', 2, 1],
            ['Sisa Larut', 2, 1],
            ['Sample Kain', 2, 1],
            ['RKO', 2, 1],
            ['Next Routing', 2, 1],
            ['Last Update', 2, 1],
            ['Update By', 2, 1],
        ],
        'header_sub_start' => 17,
        'header_sub' => [
            ['Start', 17], 'Finish',
            'Start', 'Finish',
            'Start', 'Finish',
            'Start', 'Finish',
            ['Start', 26], 'Finish',
            'Start', 'Finish',
            'Start', 'Finish',
            'Start', 'Finish',
        ],
        'columns' => [
            ['CP No', ['cp_no', 'no_cp']],
            ['Label', ['label', 'label_jual']],
            ['Cust Color', ['cust_color']],
            ['Kode Lab', ['kode_lab']],
            ['Material Name', ['material_name', 'material']],
            ['Planning Qty', ['qty']],
            ['Posisi Hari Ini', ['posisi_hari_ini', 'routing_name']],
            ['Speed', ['speed']],
            ['Resep Lipat', ['resep_lipat', 'resp_lipat']],
            ['Status Resep', ['status_resep', 'status_resp']],
            ['Vlot Resep', ['vlot_resep', 'vlot_resp']],
            ['Bon Penimbangan', ['bon_resep', 'bon_resp']],
            ['Kategori Penimbangan', ['plan_description', 'kategori_penimbangan', 'ket', 'kategori']],
            ['Tgl', ['tgl', 'plan_date']],
            ['Est Tmbng Plrtn LA/LAB', ['est_tmbng_plrtn_lalab', 'est_tmbng_plrtm_lalab']],
            ['Act. Tmbg LAB Start', ['aktual_timbang_lab_start', 'actual_timbang_lab_start', 'aktual_timbang_lalab_start', 'actual_timbang_lalab_start']],
            ['Act. Tmbg LAB Finish', ['aktual_timbang_lab_finish', 'actual_timbang_lab_finish', 'aktual_timbang_lalab_finish', 'actual_timbang_lalab_finish']],
            ['Act. Tmbg LA Start', ['aktual_timbang_la_start', 'actual_timbang_la_start']],
            ['Act. Tmbg LA Finish', ['aktual_timbang_la_finish', 'actual_timbang_la_finish']],
            ['Act. Larut LAB Start', ['aktual_larut_lab_start', 'actual_larut_lab_start', 'aktual_larut_lalab_start', 'actual_larut_lalab_start']],
            ['Act. Larut LAB Finish', ['aktual_larut_lab_finish', 'actual_larut_lab_finish', 'aktual_larut_lalab_finish', 'actual_larut_lalab_finish']],
            ['Act. Larut LA Start', ['aktual_larut_la_start', 'actual_larut_la_start']],
            ['Act. Larut LA Finish', ['aktual_larut_la_finish', 'actual_larut_la_finish']],
            ['Est Plrtm Prdks', ['est_plrtn_prdks', 'est_plrtm_prdks']],
            ['Act. Larut Produksi Start', ['aktual_larut_prdks_start', 'actual_larut_prdks_start', 'aktual_larut_produksi_start', 'actual_larut_produksi_start']],
            ['Act. Larut Produksi Finish', ['aktual_larut_prdks_finish', 'actual_larut_prdks_finish', 'aktual_larut_produksi_finish', 'actual_larut_produksi_finish']],
            ['Rencana Celup Start', ['rencana_start', 'plan_start']],
            ['Rencana Celup Finish', ['rencana_finish', 'plan_finish', 'plan_end']],
            ['Aktual Celup Start', ['aktual_start', 'actual_start']],
            ['Aktual Celup Finish', ['aktual_finish', 'actual_finish', 'actual_end']],
            ['Aktual Topping Paddry Start', ['aktual_topping_paddry_start', 'actual_topping_paddry_start', 'aktual_topping_start', 'actual_topping_start']],
            ['Aktual Topping Paddry Finish', ['aktual_topping_paddry_finish', 'actual_topping_paddry_finish', 'aktual_topping_finish', 'actual_topping_finish']],
            ['Vlot Aktual', ['vlot_aktual', 'actual_vlot']],
            ['Sisa Larut', ['sisa_larut', 'sisa_saturator']],
            ['Sample Kain', ['sample_kain', 'sample']],
            ['RKO', ['rko']],
            ['Next Routing', ['next_routing']],
            ['Last Update', ['last_update', 'modified_at', 'created_at']],
            ['Update By', ['update_by', 'updated_by', 'modified_by', 'created_by']],
        ],
    ],
    'bakar_bulu' => [
        'table' => 'dbo.cpp_bakar_bulu',
        'title' => 'CP Planning Bakar Bulu',
        'header_top' => [
            ['No', 2, 1],
            ['CP No', 2, 1],
            ['Label Jual', 2, 1],
            ['Cust Color', 2, 1],
            ['Kode Lab', 2, 1],
            ['Routing Name', 2, 1],
            ['Qty', 2, 1],
            ['Material Name', 2, 1],
            ['Paddry Planning', 1, 4],
            ['Plant Description', 2, 1],
            ['Actual', 1, 5],
            ['Posisi Hari Ini', 2, 1],
            ['Next Routing', 2, 1],
            ['Last Update', 2, 1],
            ['Updated By', 2, 1],
        ],
        'header_sub_start' => 9,
        'header_sub' => [
            ['Machine', 9], 'Date', 'Start', 'End',
            ['Date', 14], 'Start', 'End', 'Shift', 'Realisasi',
        ],
        'columns' => [
            ['CP No', ['cp_no']],
            ['Label Jual', ['label_jual', 'label']],
            ['Cust Color', ['cust_color']],
            ['Kode Lab', ['kode_lab']],
            ['Routing Name', ['routing_name']],
            ['Qty', ['qty']],
            ['Material Name', ['material_name', 'material']],
            ['Plan Machine', ['plan_machine']],
            ['Plan Date', ['plan_date']],
            ['Plan Start', ['plan_start']],
            ['Plan End', ['plan_end']],
            ['Plant Description', ['plan_description']],
            ['Actual Date', ['actual_date']],
            ['Actual Start', ['actual_start']],
            ['Actual End', ['actual_end']],
            ['Actual Shift', ['actual_shift']],
            ['Actual Realisasi', ['actual_realisasi']],
            ['Posisi Hari Ini', ['posisi_hari_ini', 'routing_name']],
            ['Next Routing', ['next_routing']],
            ['Last Update', ['last_update', 'modified_at', 'created_at']],
            ['Updated By', ['updated_by', 'update_by', 'modified_by', 'created_by']],
        ],
    ],
    'scouring' => [
        'table' => 'dbo.cpp_scouring',
        'title' => 'CP Planning Scouring',
        'header_top' => [
            ['No', 2, 1],
            ['CP No', 2, 1],
            ['Label Jual', 2, 1],
            ['Cust Color', 2, 1],
            ['Kode Lab', 2, 1],
            ['Qty', 2, 1],
            ['Material Name', 2, 1],
            ['Speed', 2, 1],
            ['Grammature', 2, 1],
            ['Machine', 2, 1],
            ['Paddry Planning', 1, 2],
            ['Plan Description', 2, 1],
            ['Aktual Proses', 1, 3],
            ['Operator', 2, 1],
            ['Shift', 2, 1],
            ['Down Time (Mnt)', 2, 1],
            ['Realisasi', 2, 1],
            ['Posisi Hari Ini', 2, 1],
            ['Next Routing', 2, 1],
            ['Last Update', 2, 1],
            ['Updated By', 2, 1],
        ],
        'header_sub_start' => 11,
        'header_sub' => [
            ['Tgl', 11], 'Paddry',
            ['Tgl', 14], 'Start', 'Finish',
        ],
        'columns' => [
            ['CP No', ['cp_no']],
            ['Label Jual', ['label_jual', 'label']],
            ['Cust Color', ['cust_color']],
            ['Kode Lab', ['kode_lab']],
            ['Qty', ['qty']],
            ['Material Name', ['material_name', 'material']],
            ['Speed', ['speed']],
            ['Grammature', ['grammature']],
            ['Machine', ['plan_machine']],
            ['Paddry Tgl', ['plan_date']],
            ['Paddry', ['plan_paddry']],
            ['Plan Description', ['plan_description']],
            ['Aktual Tgl', ['actual_date']],
            ['Aktual Start', ['actual_start']],
            ['Aktual Finish', ['actual_end']],
            ['Operator', ['actual_operator']],
            ['Shift', ['actual_shift']],
            ['Down Time (Mnt)', ['down_time']],
            ['Realisasi', ['actual_realisasi']],
            ['Posisi Hari Ini', ['posisi_hari_ini', 'routing_name']],
            ['Next Routing', ['next_routing']],
            ['Last Update', ['last_update', 'modified_at', 'created_at']],
            ['Updated By', ['updated_by', 'update_by', 'modified_by', 'created_by']],
        ],
    ],
    'presett' => [
        'table' => 'dbo.cpp_presett',
        'title' => 'CP Planning PreSett',
        'header_top' => [
            ['No', 2, 1],
            ['No CP', 2, 1],
            ['Urut Plan', 2, 1],
            ['Label Jual', 2, 1],
            ['Cust Color', 2, 1],
            ['Kode Lab', 2, 1],
            ['Material Name', 2, 1],
            ['Qty', 2, 1],
            ['Ket', 2, 1],
            ['Posisi Hari Ini', 2, 1],
            ['Rencana Paddry', 1, 2],
            ['Aktual Proses', 1, 2],
            ['Operator', 2, 1],
            ['Shift', 2, 1],
            ['Status', 2, 1],
            ['Downtime (Mnt)', 2, 1],
            ['Keterangan', 2, 1],
            ['Last Update', 2, 1],
            ['Update By', 2, 1],
        ],
        'header_sub_start' => 11,
        'header_sub' => ['Tgl', 'Paddry', 'Start', 'Finish'],
        'columns' => [
            ['No CP', ['cp_no']],
            ['Urut Plan', ['urut_plan']],
            ['Label Jual', ['label_jual', 'label']],
            ['Cust Color', ['cust_color']],
            ['Kode Lab', ['kode_lab']],
            ['Material Name', ['material_name', 'material']],
            ['Qty', ['qty']],
            ['Ket', ['ket']],
            ['Posisi Hari Ini', ['posisi_hari_ini', 'routing_name']],
            ['Rencana Paddry Tgl', ['plan_date']],
            ['Rencana Paddry', ['plan_machine']],
            ['Aktual Start', ['actual_start']],
            ['Aktual Finish', ['actual_end']],
            ['Operator', ['actual_operator']],
            ['Shift', ['actual_shift']],
            ['Status', ['actual_wheel_no', 'status']],
            ['Downtime (Mnt)', ['down_time']],
            ['Keterangan', ['keterangan', 'plan_description']],
            ['Last Update', ['last_update', 'modified_at', 'created_at']],
            ['Update By', ['updated_by', 'update_by', 'modified_by', 'created_by']],
        ],
    ],
];

if (!isset($layoutMap[$layoutKey])) {
    http_response_code(400);
    echo 'Layout export all tidak didukung.';
    exit;
}

$tableName = $layoutMap[$layoutKey]['table'];
$titleBase = $layoutMap[$layoutKey]['title'];
$columns = $layoutMap[$layoutKey]['columns'];
$headerTop = $layoutMap[$layoutKey]['header_top'] ?? [];
$headerSub = $layoutMap[$layoutKey]['header_sub'] ?? [];
$headerSubStart = isset($layoutMap[$layoutKey]['header_sub_start']) ? (int)$layoutMap[$layoutKey]['header_sub_start'] : 1;

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
    return $ts === false ? '' : date('Y-m-d', $ts);
};

$toDisplayDate = static function ($value) use ($toIsoDate): string {
    $iso = $toIsoDate($value);
    if ($iso === '') {
        return '';
    }
    $ts = strtotime($iso);
    return $ts === false ? $iso : date('d/m/Y', $ts);
};

$toDisplayTime = static function ($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('H:i');
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d{2}:\d{2}/', $raw)) {
        return substr($raw, 0, 5);
    }
    $ts = strtotime($raw);
    return $ts === false ? $raw : date('H:i', $ts);
};

$toText = static function ($value): string {
    if ($value instanceof DateTimeInterface) {
        return $value->format('d/m/Y H:i:s');
    }
    return trim((string)($value ?? ''));
};

$rowValue = static function (array $row, array $keys, $default = '') {
    foreach ($keys as $k) {
        $key = strtolower((string)$k);
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }
    }
    return $default;
};

$xmlEsc = static function ($value): string {
    $text = (string)$value;
    if (function_exists('mb_convert_encoding')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
    } elseif (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
};

$sheetNameSafe = static function (string $name): string {
    $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name) ?? $name;
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    if ($name === '') {
        $name = 'Sheet';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 31, 'UTF-8');
    }
    return substr($name, 0, 31);
};

$machineNameMap = [];
$stmtMachine = sqlsrv_query(
    $conn,
    "SELECT LTRIM(RTRIM(CAST(machine_id AS NVARCHAR(100)))) AS machine_id,
            LTRIM(RTRIM(CAST(machine_name AS NVARCHAR(200)))) AS machine_name
     FROM dbo.ms_machine
     WHERE (? = '' OR LOWER(LTRIM(RTRIM(CAST(planning_type AS NVARCHAR(100))))) = LOWER(LTRIM(RTRIM(?))))
     ORDER BY machine_name ASC, machine_id ASC",
    [$planType, $planType]
);
if ($stmtMachine !== false) {
    while ($m = sqlsrv_fetch_array($stmtMachine, SQLSRV_FETCH_ASSOC)) {
        $mId = trim((string)($m['machine_id'] ?? ''));
        if ($mId === '') {
            continue;
        }
        $machineNameMap[strtoupper($mId)] = trim((string)($m['machine_name'] ?? ''));
    }
    sqlsrv_free_stmt($stmtMachine);
}

$sql = "SELECT * FROM {$tableName} WHERE period_date = ? ORDER BY machine_id ASC, seq_no ASC, id ASC";
$stmt = sqlsrv_query($conn, $sql, [$periodDate]);
if ($stmt === false) {
    http_response_code(500);
    echo 'Gagal query data planning.';
    exit;
}

$groupedRows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rowAssoc = [];
    foreach ($r as $k => $v) {
        $rowAssoc[strtolower((string)$k)] = $v;
    }
    $machineId = trim((string)($rowAssoc['machine_id'] ?? ''));
    if ($machineId === '') {
        $machineId = 'UNKNOWN';
    }
    if (!isset($groupedRows[$machineId])) {
        $groupedRows[$machineId] = [];
    }
    $groupedRows[$machineId][] = $rowAssoc;
}
sqlsrv_free_stmt($stmt);

if ($layoutKey === 'paddry' && !empty($groupedRows)) {
    foreach ($groupedRows as $machineId => $rowsRaw) {
        $machineIdText = trim((string)$machineId);
        if ($machineIdText === '' || strtoupper($machineIdText) === 'UNKNOWN') {
            continue;
        }

        $fetchedRows = cpPlanningExportFetchRowsByAction('load_paddry', $machineIdText, $periodDate);
        if (empty($fetchedRows)) {
            continue;
        }

        $normalizedRows = [];
        foreach ($fetchedRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowAssoc = [];
            foreach ($row as $k => $v) {
                if (is_string($k)) {
                    $rowAssoc[strtolower($k)] = $v;
                }
            }
            if (!isset($rowAssoc['cp_no']) && isset($rowAssoc['no_cp'])) {
                $rowAssoc['cp_no'] = $rowAssoc['no_cp'];
            }
            if (!isset($rowAssoc['material_name']) && isset($rowAssoc['material'])) {
                $rowAssoc['material_name'] = $rowAssoc['material'];
            }
            $normalizedRows[] = $rowAssoc;
        }

        if (!empty($normalizedRows)) {
            $groupedRows[$machineId] = $normalizedRows;
        }
    }
}

$allSheets = [];
foreach ($groupedRows as $machineId => $rowsRaw) {
    $machineKey = strtoupper(trim((string)$machineId));
    $machineName = trim((string)($machineNameMap[$machineKey] ?? ''));
    if ($machineName === '' || strtoupper($machineName) === $machineKey) {
        $sheetTitle = $machineId;
    } else {
        $sheetTitle = $machineName;
    }

    $lines = [];
    $seq = 0;
    foreach ($rowsRaw as $row) {
        $seq++;
        $line = [(string)$seq];
        foreach ($columns as $col) {
            $valRaw = $rowValue($row, $col[1], '');
            $colLabel = strtolower((string)$col[0]);
            if (strpos($colLabel, 'tgl') !== false || strpos($colLabel, 'date') !== false || $colLabel === 'rko') {
                $line[] = $toDisplayDate($valRaw);
            } elseif (
                strpos($colLabel, 'start') !== false ||
                strpos($colLabel, 'finish') !== false ||
                strpos($colLabel, 'end') !== false ||
                strpos($colLabel, 'est tmbng') !== false ||
                strpos($colLabel, 'est plrtm') !== false
            ) {
                $line[] = $toDisplayTime($valRaw);
            } else {
                $line[] = $toText($valRaw);
            }
        }
        $lines[] = $line;
    }

    $headers = ['No'];
    foreach ($columns as $col) {
        $headers[] = (string)$col[0];
    }

    $allSheets[] = [
        'title' => $sheetTitle,
        'rows' => $lines,
        'machine_id' => $machineId,
    ];
}

if (!empty($allSheets)) {
    usort($allSheets, static function (array $a, array $b): int {
        $aTitle = (string)($a['title'] ?? '');
        $bTitle = (string)($b['title'] ?? '');
        return strnatcasecmp($aTitle, $bTitle);
    });
}

if (empty($allSheets)) {
    $allSheets[] = [
        'title' => 'Planning',
        'rows' => [['Tidak ada data planning pada tanggal ' . $periodDate . '.']],
        'machine_id' => '-',
    ];
}

$emitSheet = static function (array $sheet) use ($xmlEsc, $sheetNameSafe, $titleBase, $periodDate, $planType, $columns, $headerTop, $headerSub, $headerSubStart): void {
    $rows = $sheet['rows'];
    $sheetTitle = $sheetNameSafe((string)($sheet['title'] ?? 'Sheet'));
    $totalColumns = max(1, count($columns) + 1);
    $headerRowCount = (!empty($headerTop) ? 1 : 1) + (!empty($headerSub) ? 1 : 0);
    $expandedRows = 2 + $headerRowCount + max(1, count($rows));
    $mergeAcross = max(0, $totalColumns - 1);

    echo '<Worksheet ss:Name="' . $xmlEsc($sheetTitle) . '">';
    echo '<Table ss:ExpandedColumnCount="' . $totalColumns . '" ss:ExpandedRowCount="' . $expandedRows . '" x:FullColumns="1" x:FullRows="1">';

    echo '<Row><Cell ss:StyleID="Title" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">' . $xmlEsc($titleBase . ' - ' . $sheetTitle) . '</Data></Cell></Row>';
    echo '<Row><Cell ss:StyleID="Meta" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">Periode: ' . $xmlEsc($periodDate) . ' | Plan Type: ' . $xmlEsc($planType !== '' ? $planType : '-') . '</Data></Cell></Row>';

    if (!empty($headerTop)) {
        echo '<Row>';
        foreach ($headerTop as $head) {
            $text = (string)($head[0] ?? '');
            $rowspan = (int)($head[1] ?? 1);
            $colspan = (int)($head[2] ?? 1);
            $attrAcross = $colspan > 1 ? ' ss:MergeAcross="' . ($colspan - 1) . '"' : '';
            $attrDown = $rowspan > 1 ? ' ss:MergeDown="' . ($rowspan - 1) . '"' : '';
            echo '<Cell ss:StyleID="Header"' . $attrAcross . $attrDown . '><Data ss:Type="String">' . $xmlEsc($text) . '</Data></Cell>';
        }
        echo '</Row>';
    } else {
        echo '<Row>';
        echo '<Cell ss:StyleID="Header"><Data ss:Type="String">No</Data></Cell>';
        foreach ($columns as $colMeta) {
            echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . $xmlEsc((string)$colMeta[0]) . '</Data></Cell>';
        }
        echo '</Row>';
    }

    if (!empty($headerSub)) {
        echo '<Row>';
        $isFirstSubCell = true;
        foreach ($headerSub as $sub) {
            $subText = '';
            $subIndex = 0;

            if (is_array($sub)) {
                $subText = (string)($sub[0] ?? ($sub['label'] ?? ''));
                if (isset($sub[1])) {
                    $subIndex = (int)$sub[1];
                } elseif (isset($sub['index'])) {
                    $subIndex = (int)$sub['index'];
                }
            } else {
                $subText = (string)$sub;
            }

            $indexAttr = '';
            if ($subIndex > 0) {
                $indexAttr = ' ss:Index="' . $subIndex . '"';
            } elseif ($isFirstSubCell && $headerSubStart > 1) {
                $indexAttr = ' ss:Index="' . $headerSubStart . '"';
            }

            echo '<Cell ss:StyleID="HeaderSub"' . $indexAttr . '><Data ss:Type="String">' . $xmlEsc($subText) . '</Data></Cell>';
            $isFirstSubCell = false;
        }
        echo '</Row>';
    }

    if (empty($rows)) {
        echo '<Row><Cell ss:StyleID="Empty" ss:MergeAcross="' . $mergeAcross . '"><Data ss:Type="String">Tidak ada data.</Data></Cell></Row>';
    } else {
        foreach ($rows as $line) {
            echo '<Row>';
            foreach ($line as $cell) {
                echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . $xmlEsc((string)$cell) . '</Data></Cell>';
            }
            echo '</Row>';
        }
    }

    echo '</Table>';
    echo '</Worksheet>';
};

$periodToken = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $periodDate);
$layoutToken = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $layoutKey !== '' ? $layoutKey : 'planning');
$filename = 'planning_export_all_' . $layoutToken . '_' . $periodToken . '.xls';

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
echo '<Styles>';
echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10"/><Borders/><Interior/><NumberFormat/><Protection/></Style>';
echo '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="12"/><Interior ss:Color="#D9E1F2" ss:Pattern="Solid"/></Style>';
echo '<Style ss:ID="Meta"><Font ss:Size="10"/><Interior ss:Color="#F7F7F7" ss:Pattern="Solid"/></Style>';
echo '<Style ss:ID="Header"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#DEEAF6" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="HeaderSub"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Interior ss:Color="#EAF2FB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="Data"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="Empty"><Font ss:Italic="1" ss:Color="#6B7280"/><Interior ss:Color="#F9FAFB" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '</Styles>';

foreach ($allSheets as $sheetData) {
    $emitSheet($sheetData);
}

echo '</Workbook>';
exit;
